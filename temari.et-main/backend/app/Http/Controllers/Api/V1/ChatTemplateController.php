<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatMessageTemplate;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Analytics\Analytics;
use App\Services\Chat\ConversationAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Preset chat messages, school-curated (ADR-019 sidecar). Two read lanes:
 * the STUDIO list for chat.moderate holders managing the library, and the
 * PICKER list (?conversation_id=) any posting staff member uses — templates
 * arrive placeholder-resolved in the family's language, ready to send.
 */
class ChatTemplateController extends Controller
{
    public function __construct(private readonly ConversationAccess $access)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->filled('conversation_id')) {
            return $this->forConversation($request);
        }

        $user = $request->user();
        abort_unless($user->hasContextPermission('chat.moderate'), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        $templates = ChatMessageTemplate::query()
            ->where('school_id', $schoolId ?? 0)
            ->when($branch !== null, fn ($q) => $q->where(
                fn ($qq) => $qq->whereNull('branch_id')->orWhere('branch_id', $branch->id),
            ))
            ->with('branch:id,name')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates->map(fn (ChatMessageTemplate $t): array => self::row($t))]);
    }

    /** The composer's picker: active templates resolved for one conversation. */
    private function forConversation(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::query()->findOrFail($request->integer('conversation_id'));

        abort_unless($this->access->canPost($user, $conversation), 403);
        abort_unless($this->access->isStaffAt($user, $conversation), 403);

        // The family reads in ITS language: a direct family thread resolves to
        // the primary guardian's preferred language, anything else to the
        // sender's own UI language.
        $language = $this->familyLanguage($conversation) ?? $user->preferred_language ?? 'en';

        $templates = ChatMessageTemplate::query()
            ->where('school_id', $conversation->school_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('branch_id')
                ->when($conversation->branch_id !== null, fn ($qq) => $qq->orWhere('branch_id', $conversation->branch_id)))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $templates
                ->map(fn (ChatMessageTemplate $t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'category' => $t->category,
                    'resolved_body' => $t->resolveFor($conversation, $user, $language),
                ])
                ->filter(fn (array $row): bool => $row['resolved_body'] !== '')
                ->values(),
            'meta' => [
                'language' => $language,
                'required' => $this->access->requiresTemplate($user, $conversation),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $contextBranch = $this->activeBranchOrNull($request);
        $schoolId = $contextBranch?->school_id ?? $this->activeSchoolScopeId($request);
        abort_unless($schoolId !== null, 422, 'Pick a school workspace first.');

        $data = $this->validated($request);
        $branchId = array_key_exists('branch_id', $data)
            ? ($data['branch_id'] !== null ? (int) $data['branch_id'] : null)
            : $contextBranch?->id;

        abort_unless(
            $user->hasPermissionForScope('chat.moderate', $schoolId, $branchId),
            403,
        );

        $template = ChatMessageTemplate::create([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'category' => $data['category'],
            'body' => self::cleanBody($data['body']),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $user->id,
        ]);

        Analytics::capture($user, 'chat.template_created', [
            'template_id' => $template->id,
            'category' => $template->category,
        ], $schoolId, $branchId);

        return response()->json([
            'data' => self::row($template),
            'message' => 'Template created.',
        ], 201);
    }

    public function update(Request $request, ChatMessageTemplate $template): JsonResponse
    {
        $this->authorizeManage($request->user(), $template);

        $data = $this->validated($request, updating: true);

        $template->update([
            ...collect($data)->only(['name', 'category', 'is_active'])->all(),
            ...(isset($data['body']) ? ['body' => self::cleanBody($data['body'])] : []),
        ]);

        return response()->json([
            'data' => self::row($template->fresh('branch:id,name')),
            'message' => 'Template updated.',
        ]);
    }

    public function destroy(Request $request, ChatMessageTemplate $template): JsonResponse
    {
        $this->authorizeManage($request->user(), $template);

        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'category' => [$required, Rule::in(ChatMessageTemplate::CATEGORIES)],
            'body' => [$required, 'array'],
            'body.en' => ['nullable', 'string', 'max:2000'],
            'body.am' => ['nullable', 'string', 'max:2000'],
            'body.om' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
        ]);
    }

    /** @param  array<string, mixed>  $body
     * @return array<string, string> */
    private static function cleanBody(array $body): array
    {
        $clean = collect(['en', 'am', 'om'])
            ->mapWithKeys(fn (string $lang): array => [$lang => trim((string) ($body[$lang] ?? ''))])
            ->filter(fn (string $text): bool => $text !== '')
            ->all();

        abort_if($clean === [], 422, 'Write the message in at least one language.');

        return $clean;
    }

    private function authorizeManage(User $user, ChatMessageTemplate $template): void
    {
        abort_unless(
            $user->hasPermissionForScope('chat.moderate', $template->school_id, $template->branch_id),
            403,
        );
    }

    /** Primary guardian's language of a direct family thread, if any. */
    private function familyLanguage(Conversation $conversation): ?string
    {
        if ($conversation->kind !== 'direct' || $conversation->student_id === null) {
            return null;
        }

        $conversation->loadMissing('student.guardians.parentProfile.user:id,preferred_language');

        // Only ACTIVE guardian links speak for the family.
        $guardians = ($conversation->student?->guardians ?? collect())
            ->filter(fn ($link): bool => (bool) $link->is_active);
        $link = $guardians->firstWhere('is_primary', true) ?? $guardians->first();

        return $link?->parentProfile?->user?->preferred_language;
    }

    /** @return array<string, mixed> */
    private static function row(ChatMessageTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'category' => $template->category,
            'body' => $template->body,
            'is_active' => $template->is_active,
            'branch_id' => $template->branch_id,
            'branch_name' => $template->branch?->name,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
