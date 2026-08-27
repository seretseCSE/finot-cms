<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AiLane;
use App\Enums\AiSurface;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\School;
use App\Models\StudentGuardian;
use App\Services\Ai\AiEntitlementService;
use App\Services\Ai\ChatAttachments;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\ConversationMessage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /ai chat sessions surface. Conversations are STRICTLY self-scoped
 * (no supervisory read exists); the lane and school/branch context are
 * frozen at creation (ADR-010/012 mirrored — see AiConversation). The
 * transcript itself lives in the Laravel AI SDK conversation tables.
 */
class AiConversationController extends Controller
{
    /**
     * The AI home payload: which assistant SURFACES this user may open HERE
     * (active workspace) — the user never picks a lane; each surface lists
     * the lanes composing it plus one entitlement (lanes of a surface share
     * a billing plan by construction). First surface = the default.
     */
    public function context(Request $request, AiEntitlementService $entitlements): JsonResponse
    {
        $user = $request->user();
        $schoolId = $user->activeSchoolId();
        $school = $schoolId !== null ? School::query()->find($schoolId) : null;

        $surfaces = AiLane::surfacesFor($user, $schoolId, $user->activeBranchId());

        return response()->json(['data' => [
            'assistants' => collect($surfaces)->map(fn (array $lanes, string $surface): array => [
                'surface' => $surface,
                'lanes' => array_map(fn (AiLane $lane): string => $lane->value, $lanes),
                'entitlement' => $entitlements->resolve($user, $lanes[0], $school),
            ])->values(),
            'limits' => [
                'max_prompt_length' => (int) config('temari-ai.max_prompt_length'),
                'max_attachments' => (int) config('temari-ai.max_attachments'),
            ],
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = AiConversation::query()
            ->ownedBy($request->user())
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->orderByRaw('pinned_at desc nulls last')
            ->orderByDesc('last_message_at')
            ->paginate(min($request->integer('per_page', 50), 100));

        return response()->json([
            'data' => collect($conversations->items())->map(fn (AiConversation $c): array => $this->present($c)),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function store(Request $request, ConversationStore $store): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // The surface is derived from the workspace; the client may pin
            // it (the family portal entry) but never picks a lane. `lane` is
            // the legacy alias older deep links send — only its surface counts.
            'surface' => ['nullable', Rule::enum(AiSurface::class)],
            'lane' => ['nullable', Rule::enum(AiLane::class)],
            'student_id' => ['nullable', 'integer'],
        ]);

        $schoolId = $user->activeSchoolId();
        $branchId = $user->activeBranchId();

        $surfaces = AiLane::surfacesFor($user, $schoolId, $branchId);

        $requested = isset($data['surface'])
            ? AiSurface::from($data['surface'])
            : (isset($data['lane']) ? AiLane::from($data['lane'])->surface() : null);

        $surfaceKey = $requested?->value ?? array_key_first($surfaces);

        abort_unless(
            $surfaceKey !== null && isset($surfaces[$surfaceKey]),
            403,
            'That assistant is not available for your account here.',
        );

        // First lane of the surface = the stored primary (priority-ordered).
        $lane = $surfaces[$surfaceKey][0];

        // Family conversations carry no school context; staff ones freeze it.
        $isFamily = $lane->isFamilyLane();

        $studentId = null;
        if ($lane === AiLane::Parent && ! empty($data['student_id'])) {
            $link = StudentGuardian::query()
                ->where('parent_id', $user->parentProfile()->value('id') ?? 0)
                ->where('student_id', (int) $data['student_id'])
                ->where('is_active', true)
                ->exists();
            abort_unless($link, 403, 'This student is not linked to your account.');
            $studentId = (int) $data['student_id'];
        }

        $uuid = $store->storeConversation($user->id, 'New chat');

        $conversation = AiConversation::create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'lane' => $lane->value,
            'school_id' => $isFamily ? null : $schoolId,
            'branch_id' => $isFamily ? null : $branchId,
            'student_id' => $studentId,
            'title' => 'New chat',
            'last_message_at' => now(),
        ]);

        return response()->json(['data' => $this->present($conversation)], 201);
    }

    public function update(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'min:1', 'max:120'],
            'pinned' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('title', $data)) {
            $conversation->title = $data['title'];
        }

        if (array_key_exists('pinned', $data)) {
            $conversation->pinned_at = $data['pinned'] ? now() : null;
        }

        $conversation->save();

        return response()->json(['data' => $this->present($conversation)]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }

    /** The transcript (user + assistant turns only — tool chatter stays internal). */
    public function messages(Request $request, AiConversation $conversation, ChatAttachments $chatAttachments): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->uuid)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (ConversationMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => (string) $message->content,
                'attachments' => $chatAttachments->present($message->attachments),
                'created_at' => $message->created_at?->toISOString(),
            ]);

        return response()->json(['data' => [
            'conversation' => $this->present($conversation),
            'messages' => $messages,
        ]]);
    }

    /**
     * One attachment's bytes (image thumbnails / file downloads). Strictly
     * self-scoped like the transcript; the payload lives base64-encoded on
     * the SDK message row and is decoded on demand.
     */
    public function attachment(Request $request, AiConversation $conversation, string $message, int $index): Response
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);

        $row = ConversationMessage::query()
            ->where('conversation_id', $conversation->uuid)
            ->whereKey($message)
            ->firstOrFail();

        $attachment = collect($row->attachments ?? [])->values()->get($index);
        abort_unless(is_array($attachment) && filled($attachment['base64'] ?? null), 404);

        $bytes = base64_decode((string) $attachment['base64'], true);
        abort_if($bytes === false, 404);

        $name = str_replace(['"', "\r", "\n"], '', (string) ($attachment['name'] ?? 'attachment'));

        return response($bytes, 200, [
            'Content-Type' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            'lane' => $conversation->lane->value,
            'surface' => $conversation->lane->surface()->value,
            'title' => $conversation->title,
            'pinned' => $conversation->pinned_at !== null,
            'student_id' => $conversation->student_id,
            'school_id' => $conversation->school_id,
            'branch_id' => $conversation->branch_id,
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            'created_at' => $conversation->created_at?->toISOString(),
        ];
    }
}
