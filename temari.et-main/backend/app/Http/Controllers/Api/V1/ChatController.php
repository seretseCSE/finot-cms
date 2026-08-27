<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Branch;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationUserState;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Chat\ChannelProvisioner;
use App\Services\Chat\ChatDirectory;
use App\Services\Chat\ChatPresenter;
use App\Services\Chat\ChatService;
use App\Services\Chat\ConversationAccess;
use App\Support\ActivityLogger;
use App\Support\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The chat engine's HTTP surface (ADR-019). ONE controller serves both lanes
 * — the staff routes (/chat/*) and the relationship-lane aliases (/me/chat/*)
 * — because every decision is made per conversation by ConversationAccess,
 * never by request headers. Realtime is an upgrade, not the source of truth:
 * clients poll these endpoints and Reverb merely delivers the same payloads
 * earlier (ChatPresenter keeps the shapes identical).
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationAccess $access,
        private readonly ChatService $chat,
        private readonly ChatDirectory $directory,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Conversations
    |--------------------------------------------------------------------------
    */

    public function index(Request $request, ChannelProvisioner $provisioner): JsonResponse
    {
        $user = $request->user();

        $this->provisionFor($user, $provisioner);

        $ids = $this->access->accessibleIds($user);

        $conversations = Conversation::query()
            ->whereIn('id', $ids)
            ->with([
                'student:id,user_id,first_name,father_name,grandfather_name',
                'branch:id,name',
                'participants' => fn ($q) => $q->whereNull('left_at')->with('user:id,name,avatar_path')->limit(6),
            ])
            ->tap(fn ($query) => SearchTerm::apply($query, $request->string('q')->trim()->value(), fn ($w, string $n) => $w
                ->where('title', 'ilike', SearchTerm::contains($n))))
            ->when(
                in_array($request->input('filter'), ['direct', 'group', 'channel'], true),
                fn ($q) => $q->where('kind', $request->input('filter')),
            )
            ->get();

        $lastMessages = ChatMessage::query()
            ->whereIn('id', function ($q) use ($ids): void {
                $q->selectRaw('max(id)')
                    ->from('chat_messages')
                    ->whereIn('conversation_id', $ids)
                    ->where('status', ChatMessage::STATUS_SENT)
                    ->whereNull('deleted_at')
                    ->groupBy('conversation_id');
            })
            ->with('author:id,name')
            ->get()
            ->keyBy('conversation_id');

        $this->primeContextTitles($conversations);

        $unread = $this->unreadByConversation($user, $ids);

        $states = ConversationUserState::query()
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', $ids)
            ->get()
            ->keyBy('conversation_id');

        $rows = $conversations
            ->map(function (Conversation $c) use ($user, $lastMessages, $unread, $states): array {
                $state = $states->get($c->id);
                $last = $lastMessages->get($c->id);

                return ChatPresenter::conversation($c, [
                    'display' => $this->displayFor($user, $c),
                    'unread' => $unread[$c->id] ?? 0,
                    'muted' => $state?->isMuted() ?? false,
                    'pinned' => $state?->pinned_at !== null,
                    'last_message' => $last === null ? null : [
                        'body' => str($last->body ?? '')->limit(90)->toString(),
                        'kind' => $last->kind,
                        'meta' => $last->kind === 'system' ? $last->meta : null,
                        'author_name' => $last->author?->name,
                        'author_id' => $last->user_id,
                        'has_attachments' => ! empty($last->attachments),
                        'created_at' => $last->created_at,
                    ],
                ]);
            })
            ->sortBy([
                fn (array $a, array $b): int => ($b['pinned'] <=> $a['pinned']),
                fn (array $a, array $b): int => strcmp(
                    (string) ($b['last_message']['created_at'] ?? $b['created_at']),
                    (string) ($a['last_message']['created_at'] ?? $a['created_at']),
                ),
            ])
            ->values();

        if ($request->input('filter') === 'unread') {
            $rows = $rows->filter(fn (array $row): bool => $row['unread'] > 0)->values();
        }

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $mode = $this->access->accessMode($user, $conversation);

        abort_if($mode === null, 403);

        if ($mode === ConversationAccess::MODE_AUDIT) {
            // Oversight is legitimate but never silent.
            ActivityLogger::log($user, 'chat.audit_view', $conversation, [],
                (int) $conversation->school_id, $conversation->branch_id);
        }

        $conversation->load([
            'student:id,user_id,first_name,father_name,grandfather_name',
            'branch:id,name',
            'participants' => fn ($q) => $q->with('user:id,name,avatar_path'),
            'targets' => fn ($q) => $q->with(['branch:id,name', 'section:id,name', 'gradeLevel:id,name']),
        ]);

        $state = ConversationUserState::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->first();

        $members = match (true) {
            // Family directs: the derived audience (guardians + student).
            $conversation->kind === 'direct' && $conversation->student_id !== null => $this->access->audienceUsers($conversation)->map(fn (User $u): array => [
                'id' => $u->id, 'name' => $u->name, 'avatar_url' => $u->avatarUrl(), 'role' => 'member',
            ])->values()->all(),
            // Channels broadcast to a RULE-derived audience — there is no member
            // roster to show (the info sheet surfaces "who this reaches" instead);
            // the creator participant exists only to grant them posting access.
            $conversation->kind === 'channel' => [],
            default => $conversation->participants->map(fn ($p): array => [
                'id' => $p->user?->id,
                'name' => $p->user?->name,
                'avatar_url' => $p->user?->avatarUrl(),
                'role' => $p->role,
                'left' => ! $p->isActive(),
            ])->values()->all(),
        };

        $pinned = $conversation->messages()
            ->whereNotNull('pinned_at')
            ->where('status', ChatMessage::STATUS_SENT)
            ->with(['author:id,name,avatar_path', 'replyTo' => fn ($q) => $q->withTrashed()->with('author:id,name')])
            ->orderByDesc('pinned_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => ChatPresenter::conversation($conversation, [
            'display' => $this->displayFor($user, $conversation),
            'access' => $mode,
            'can_post' => $this->access->canPost($user, $conversation),
            'needs_approval' => $this->access->requiresApproval($user, $conversation),
            'can_moderate' => $this->access->canModerate($user, $conversation),
            'can_pin' => $this->access->canManagePins($user, $conversation),
            'pinned_messages' => $pinned->map(fn (ChatMessage $m): array => ChatPresenter::message($m))->values()->all(),
            'muted' => $state?->isMuted() ?? false,
            'pinned' => $state?->pinned_at !== null,
            'members' => $members,
            'targets' => $conversation->targets->map(fn ($t): array => [
                'audience' => $t->audience,
                'branch_id' => $t->branch_id,
                'branch_name' => $t->branch?->name,
                'grade_level_id' => $t->grade_level_id,
                'grade_name' => $t->gradeLevel?->name,
                'section_id' => $t->section_id,
                'section_name' => $t->section?->name,
                'job_title' => $t->job_title,
            ])->values()->all(),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'kind' => ['required', Rule::in(['direct', 'group', 'channel'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'user_ids' => ['nullable', 'array', 'max:100'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'posting' => ['nullable', Rule::in(['all', 'admins'])],
            'targets' => ['nullable', 'array', 'max:80'],
            'targets.*.audience' => ['required_with:targets', Rule::in(['staff', 'parents', 'students'])],
            'targets.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'targets.*.grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'targets.*.section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'targets.*.job_title' => ['nullable', 'string', 'max:40'],
        ]);

        $conversation = match ($data['kind']) {
            'direct' => $this->storeDirect($user, $data),
            'group' => $this->storeGroup($request, $user, $data),
            'channel' => $this->storeChannel($request, $user, $data),
        };

        return response()->json([
            'data' => ChatPresenter::conversation($conversation, [
                'display' => $this->displayFor($user, $conversation),
            ]),
            'message' => 'Conversation ready.',
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $mode = $this->access->accessMode($user, $conversation);

        abort_if($mode === null, 403);

        $limit = min(50, max(10, (int) $request->input('limit', 30)));
        $canModerate = $this->access->canModerate($user, $conversation);

        $messages = $conversation->messages()
            ->withTrashed()
            ->with(['author:id,name,avatar_path', 'replyTo' => fn ($q) => $q->withTrashed()->with('author:id,name'), 'reactions'])
            ->where(function ($q) use ($user, $canModerate): void {
                $q->where('status', ChatMessage::STATUS_SENT)
                    ->orWhere('user_id', $user->id);

                if ($canModerate) {
                    $q->orWhere('status', ChatMessage::STATUS_PENDING);
                }
            })
            ->when($request->filled('before'), fn ($q) => $q->where('id', '<', (int) $request->input('before')))
            ->when($request->filled('after'), fn ($q) => $q->where('id', '>', (int) $request->input('after')))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        // "Seen" pointers — small conversations only; channels skip them.
        $reads = $conversation->kind === 'channel' ? [] : ConversationUserState::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->whereNotNull('last_read_message_id')
            ->pluck('last_read_message_id', 'user_id')
            ->all();

        return response()->json([
            'data' => $messages->map(fn (ChatMessage $m): array => ChatPresenter::message($m)),
            'meta' => [
                'has_more' => $messages->count() === $limit,
                'reads' => $reads,
            ],
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->access->canPost($user, $conversation), 403, 'You cannot post in this conversation.');

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachments'],
            'kind' => ['nullable', Rule::in(['text', 'voice'])],
            'attachments' => ['nullable', 'array', 'max:6', 'required_without:body'],
            'attachments.*.name' => ['required', 'string', 'max:255'],
            'attachments.*.path' => ['required', 'string', 'max:500', 'starts_with:chat/'],
            'attachments.*.size' => ['nullable', 'integer'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:120'],
            'attachments.*.duration' => ['nullable', 'integer', 'max:600'],
            'reply_to_id' => ['nullable', 'integer'],
            'client_uuid' => ['nullable', 'uuid'],
            'emergency' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['emergency'])) {
            abort_unless(
                $conversation->kind === 'channel' && $user->hasPermissionForScope(
                    'chat.announce', (int) $conversation->school_id, $conversation->branch_id,
                ),
                403,
                'Emergency notices are for announcement channels only.',
            );
        }

        if (! empty($data['reply_to_id'])) {
            abort_unless(
                $conversation->messages()->where('id', $data['reply_to_id'])->exists(),
                422,
                'The message you replied to is not in this conversation.',
            );
        }

        // Template-required mode: family-reaching text must BE a preset.
        $this->access->assertTemplateCompliance($user, $conversation, $data['body'] ?? null);

        // Persist only the stable descriptor — never signed URLs a client may echo back.
        if (isset($data['attachments'])) {
            $data['attachments'] = array_map(
                fn (array $file): array => array_intersect_key($file, array_flip(['name', 'path', 'size', 'mime_type', 'duration'])),
                $data['attachments'],
            );
        }

        $message = $this->chat->send($user, $conversation, $data);
        $message->load(['author:id,name,avatar_path', 'replyTo.author:id,name', 'reactions']);

        // The author has obviously read their own message.
        $this->chat->markRead($user, $conversation, $message->id);

        return response()->json([
            'data' => ChatPresenter::message($message),
            'message' => $message->isPending() ? 'Sent for approval.' : 'Sent.',
        ], 201);
    }

    public function forward(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->access->canPost($user, $conversation), 403, 'You cannot post in this conversation.');

        $data = $request->validate([
            'source_conversation_id' => ['required', 'integer'],
            'message_ids' => ['required', 'array', 'min:1', 'max:30'],
            'message_ids.*' => ['integer'],
        ]);

        $source = Conversation::query()->findOrFail((int) $data['source_conversation_id']);
        abort_if($this->access->accessMode($user, $source) === null, 403, 'You cannot access those messages.');

        $sources = ChatMessage::query()
            ->where('conversation_id', $source->id)
            ->whereIn('id', $data['message_ids'])
            ->where('status', ChatMessage::STATUS_SENT)
            ->where('kind', '!=', 'system')
            ->with(['author:id,name', 'conversation:id,title'])
            ->orderBy('id')
            ->get();

        abort_if($sources->isEmpty(), 422, 'Nothing to forward.');

        // Template-required mode: forwarding free text into a family thread
        // would bypass the preset gate — attachments/voice may still travel.
        if ($this->access->requiresTemplate($user, $conversation)
            && $sources->contains(fn (ChatMessage $m): bool => trim((string) $m->body) !== '')) {
            abort(422, 'Your school requires preset messages when writing to families — text cannot be forwarded here.');
        }

        $created = $this->chat->forward($user, $conversation, $sources);

        return response()->json([
            'data' => ['count' => count($created)],
            'message' => 'Forwarded.',
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Per-user state
    |--------------------------------------------------------------------------
    */

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($this->access->accessMode($user, $conversation) === null, 403);

        $data = $request->validate(['message_id' => ['required', 'integer']]);

        $this->chat->markRead($user, $conversation, (int) $data['message_id']);

        return response()->json(['message' => 'Read.']);
    }

    public function mute(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($this->access->accessMode($user, $conversation) === null, 403);

        $data = $request->validate(['minutes' => ['nullable', 'integer', 'min:0']]);

        $this->chat->state($user, $conversation)->update([
            'muted_until' => empty($data['minutes'])
                ? null
                : now()->addMinutes(min((int) $data['minutes'], 60 * 24 * 365 * 5)),
        ]);

        return response()->json(['message' => 'Notification preference saved.']);
    }

    public function pin(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_if($this->access->accessMode($user, $conversation) === null, 403);

        $state = $this->chat->state($user, $conversation);
        $state->update(['pinned_at' => $state->pinned_at === null ? now() : null]);

        return response()->json(['message' => 'Saved.']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $ids = $this->access->accessibleIds($user);

        return response()->json(['data' => ['count' => array_sum($this->unreadByConversation($user, $ids))]]);
    }

    /*
    |--------------------------------------------------------------------------
    | Group membership
    |--------------------------------------------------------------------------
    */

    public function addParticipants(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->kind === 'group', 422);
        abort_unless($this->manageableGroup($user, $conversation), 403);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'max:50'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $added = [];
        foreach (array_unique($data['user_ids']) as $userId) {
            $member = User::query()->find((int) $userId);
            if ($member === null || ! $this->access->isStaffAt($member, $conversation)) {
                continue;
            }

            $participant = $conversation->participants()->firstOrCreate(['user_id' => $member->id], []);
            if ($participant->left_at !== null) {
                $participant->update(['left_at' => null]);
            }
            $added[] = $member->name;
        }

        if ($added !== []) {
            $this->chat->systemMessage($conversation, 'joined', ['names' => implode(', ', $added)]);
        }

        return response()->json(['message' => 'Members added.']);
    }

    public function removeParticipant(Request $request, Conversation $conversation, User $member): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->kind === 'group', 422);
        abort_unless(
            $member->id === $user->id || $this->manageableGroup($user, $conversation),
            403,
        );

        $participant = $conversation->participants()->where('user_id', $member->id)->firstOrFail();
        $participant->update(['left_at' => now()]);

        $this->chat->systemMessage($conversation, $member->id === $user->id ? 'left' : 'removed', ['names' => $member->name]);

        return response()->json(['message' => $member->id === $user->id ? 'You left the group.' : 'Member removed.']);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        $allowed = match ($conversation->kind) {
            'group' => $this->manageableGroup($user, $conversation),
            'channel' => $conversation->system_key === null && $user->hasPermissionForScope(
                'chat.announce', (int) $conversation->school_id, $conversation->branch_id,
            ),
            default => false,
        };
        abort_unless($allowed, 403);

        $conversation->update(['archived_at' => $conversation->archived_at === null ? now() : null]);

        return response()->json(['message' => $conversation->archived_at === null ? 'Conversation reopened.' : 'Conversation archived.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Moderation — the communication book
    |--------------------------------------------------------------------------
    */

    public function approvals(Request $request): JsonResponse
    {
        $user = $request->user();
        $scopes = $this->moderatorScopes($user);

        if ($scopes === []) {
            return response()->json(['data' => []]);
        }

        $pending = ChatMessage::query()
            ->where('status', ChatMessage::STATUS_PENDING)
            ->whereHas('conversation', function ($q) use ($scopes): void {
                $q->where(function ($outer) use ($scopes): void {
                    foreach ($scopes as $scope) {
                        $outer->orWhere(function ($s) use ($scope): void {
                            $s->where('school_id', $scope['school_id']);
                            if ($scope['branch_id'] !== null) {
                                $s->where('branch_id', $scope['branch_id']);
                            }
                        });
                    }
                });
            })
            ->with([
                'author:id,name,avatar_path',
                'conversation.student:id,user_id,first_name,father_name,grandfather_name',
            ])
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $pending->map(fn (ChatMessage $m): array => [
            ...ChatPresenter::message($m),
            'conversation' => ChatPresenter::conversation($m->conversation, [
                'display' => $this->displayFor($user, $m->conversation),
            ]),
        ])]);
    }

    /*
    |--------------------------------------------------------------------------
    | Search / directory / uploads
    |--------------------------------------------------------------------------
    */

    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $ids = $this->access->accessibleIds($user);

        $messages = ChatMessage::query()
            ->whereIn('conversation_id', $ids)
            ->where('status', ChatMessage::STATUS_SENT)
            ->tap(fn ($q) => SearchTerm::apply($q, $data['q'], fn ($w, string $n) => $w
                ->where('search_text', 'ilike', SearchTerm::contains($n))))
            ->with(['author:id,name', 'conversation.student:id,user_id,first_name,father_name,grandfather_name'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json(['data' => $messages->map(fn (ChatMessage $m): array => [
            'id' => $m->id,
            'conversation_id' => (int) $m->conversation_id,
            'body' => str($m->body ?? '')->limit(140)->toString(),
            'author_name' => $m->author?->name,
            'created_at' => $m->created_at,
            'conversation' => ChatPresenter::conversation($m->conversation, [
                'display' => $this->displayFor($user, $m->conversation),
            ]),
        ])]);
    }

    /** Staff-lane new-chat picker: staff in scope + reachable students. */
    public function partners(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $user->activeSchoolId();

        abort_if($schoolId === null, 422, 'Select a school context first.');

        $q = $request->filled('q') ? (string) $request->string('q') : null;
        $branchId = $user->activeBranchId();

        return response()->json(['data' => [
            'staff' => $this->directory->staffFor($user, $schoolId, $branchId, $q)
                ->map(fn (User $u): array => ['user_id' => $u->id, 'name' => $u->name, 'avatar_url' => $u->avatarUrl()])
                ->values(),
            'students' => $this->directory->studentsFor($user, $schoolId, $branchId, $q)
                ->map(fn (Student $s): array => [
                    'student_id' => $s->id,
                    'name' => $s->full_name,
                    'guardians' => $s->guardians->count(),
                ])
                ->values(),
        ]]);
    }

    /** Family-lane new-chat picker: per child — teachers, homeroom, office. */
    public function familyPartners(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->directory->familyPartners($request->user())]);
    }

    /**
     * The building blocks for a channel / mass message: the staff roles that
     * carry a login (so a staff channel can be narrowed by job title), and the
     * grades + sections in scope (so a families channel can be narrowed to a
     * grade or a single classroom). Scoped to the caller's active school — a
     * concrete branch narrows it, the school-wide workspace returns every
     * branch (each row branch-tagged) plus the branch catalog so the composer
     * can target one branch or blast the whole school. One request, no N+1.
     */
    public function channelOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $user->activeSchoolId();

        abort_if($schoolId === null, 422, 'Select a school context first.');
        abort_unless(
            $user->hasPermissionForScope('chat.announce', $schoolId, $user->activeBranchId()),
            403,
        );

        $branchId = $user->activeBranchId();

        $roles = $branchId !== null
            ? (Branch::query()->find($branchId)?->effectiveEmployeeAccountJobTitles() ?? [])
            : (School::query()->find($schoolId)?->employeeAccountJobTitles() ?? []);

        $sections = Section::query()
            ->where('sections.school_id', $schoolId)
            ->where('sections.is_active', true)
            ->when($branchId !== null, fn ($q) => $q->where('sections.branch_id', $branchId))
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->orderBy('grade_levels.sort_order')
            ->orderBy('sections.name')
            ->get([
                'sections.id', 'sections.name', 'sections.grade_level_id', 'sections.branch_id',
                'grade_levels.name as grade_name', 'grade_levels.sort_order as grade_sort',
            ]);

        // Grades come from the sections that actually exist in scope, so the
        // grade picker and the section picker can never disagree.
        $grades = $sections
            ->unique('grade_level_id')
            ->map(fn ($s): array => [
                'id' => (int) $s->grade_level_id,
                'name' => $s->grade_name,
                'sort' => (int) $s->grade_sort,
            ])
            ->sortBy('sort')
            ->values();

        $branches = $branchId !== null
            ? collect()
            : Branch::query()
                ->where('school_id', $schoolId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $b): array => ['id' => (int) $b->id, 'name' => $b->name]);

        return response()->json(['data' => [
            'roles' => array_values($roles),
            'grades' => $grades,
            'sections' => $sections->map(fn ($s): array => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'grade_level_id' => (int) $s->grade_level_id,
                'branch_id' => $s->branch_id === null ? null : (int) $s->branch_id,
            ])->values(),
            'branches' => $branches->values(),
            'needs_branch' => $branchId === null,
        ]]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,mp3,m4a,ogg,webm,wav,mp4,zip',
            ],
        ]);

        $file = $request->file('file');
        $path = $file->store('chat/'.$request->user()->id, ['disk' => config('filesystems.default')]);

        return response()->json(['data' => [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'url' => s3Url($path),
        ]], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** @param  array<string, mixed>  $data */
    private function storeDirect(User $user, array $data): Conversation
    {
        $target = isset($data['user_id']) ? User::query()->findOrFail((int) $data['user_id']) : null;

        // Family thread — anchored on the student's active enrollment scope.
        if (! empty($data['student_id'])) {
            $student = Student::query()->findOrFail((int) $data['student_id']);
            $enrollment = StudentEnrollment::query()
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->firstOr(fn () => abort(422, 'This student has no active enrollment.'));

            if ($target !== null && $target->id !== $user->id) {
                // Family side opening a thread with a staff member.
                abort_unless(
                    $this->directory->familyReachesStaff($user, $student, $target),
                    403,
                    'You can only message your child\'s teachers and school office.',
                );

                return $this->chat->direct($user, (int) $enrollment->school_id, (int) $enrollment->branch_id, $student, $target);
            }

            // Staff side opening the family thread.
            abort_unless(
                $this->directory->staffReachesStudent($user, $student),
                403,
                'You can only message the families of your own students.',
            );

            return $this->chat->direct($user, (int) $enrollment->school_id, (int) $enrollment->branch_id, $student, $user);
        }

        // Staff↔staff — both sides must share the caller's active scope.
        abort_if($target === null || $target->id === $user->id, 422, 'Pick someone to message.');

        $schoolId = $user->activeSchoolId();
        abort_if($schoolId === null, 422, 'Select a school context first.');

        abort_unless(
            $this->directory->isStaffReachable($target, $schoolId, $user->activeBranchId()),
            403,
            'That person is not in your school.',
        );

        return $this->chat->direct($user, $schoolId, $user->activeBranchId(), null, $target);
    }

    /** @param  array<string, mixed>  $data */
    private function storeGroup(Request $request, User $user, array $data): Conversation
    {
        $schoolId = $user->activeSchoolId();
        abort_if($schoolId === null, 422, 'Select a school context first.');
        abort_if(empty($data['title']), 422, 'Groups need a name.');
        abort_unless($this->access->staffScopes($user) !== [], 403);

        $memberIds = collect($data['user_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(function (int $id) use ($user, $schoolId): bool {
                $member = User::query()->find($id);

                return $member !== null && $this->directory->isStaffReachable($member, $schoolId, $user->activeBranchId());
            })
            ->values()
            ->all();

        return $this->chat->createGroup($user, $schoolId, $user->activeBranchId(), (string) $data['title'], $memberIds);
    }

    /** @param  array<string, mixed>  $data */
    private function storeChannel(Request $request, User $user, array $data): Conversation
    {
        $schoolId = $user->activeSchoolId();
        $branchId = $user->activeBranchId();

        abort_if($schoolId === null, 422, 'Select a school context first.');
        abort_unless(
            $user->hasPermissionForScope('chat.announce', $schoolId, $branchId),
            403,
        );
        abort_if(empty($data['title']), 422, 'Channels need a name.');
        abort_if(empty($data['targets']), 422, 'Pick at least one audience.');

        foreach ($data['targets'] as $target) {
            if (! empty($target['branch_id'])) {
                abort_unless(
                    Branch::query()->where('id', $target['branch_id'])->where('school_id', $schoolId)->exists(),
                    422, 'That branch is not in your school.',
                );
            }
            if (! empty($target['section_id'])) {
                abort_unless(
                    Section::query()->where('id', $target['section_id'])->where('school_id', $schoolId)->exists(),
                    422, 'That section is not in your school.',
                );
            }
            if (! empty($target['grade_level_id'])) {
                abort_unless(GradeLevel::query()->whereKey($target['grade_level_id'])->exists(), 422);
            }
        }

        return $this->chat->createChannel(
            $user, $schoolId, $branchId,
            (string) $data['title'],
            $data['posting'] ?? 'all',
            $data['targets'],
        );
    }

    private function manageableGroup(User $user, Conversation $conversation): bool
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->whereIn('role', ['owner', 'moderator'])
            ->exists();
    }

    /**
     * @return list<array{school_id: int, branch_id: ?int}>
     */
    private function moderatorScopes(User $user): array
    {
        $scopes = [];

        foreach ($this->access->staffScopes($user) as $scope) {
            if ($user->hasPermissionForScope('chat.moderate', $scope['school_id'], $scope['branch_id'])) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /**
     * Unread per conversation: sent messages above the read pointer, not
     * authored by the viewer — one grouped query for the whole list.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function unreadByConversation(User $user, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('chat_messages as m')
            ->selectRaw('m.conversation_id, count(*) as unread')
            ->leftJoin('conversation_user_states as s', function ($join) use ($user): void {
                $join->on('s.conversation_id', '=', 'm.conversation_id')
                    ->where('s.user_id', '=', $user->id);
            })
            ->whereIn('m.conversation_id', $ids)
            ->where('m.status', ChatMessage::STATUS_SENT)
            ->whereNull('m.deleted_at')
            ->where(function ($q) use ($user): void {
                $q->whereNull('m.user_id')->orWhere('m.user_id', '!=', $user->id);
            })
            ->whereRaw('m.id > coalesce(s.last_read_message_id, 0)')
            ->groupBy('m.conversation_id')
            ->pluck('unread', 'conversation_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * What the viewer's list row shows: family threads show the child to
     * staff and the staff member to the family; staff directs show the other
     * person; groups/channels their titles; context threads (assignment ×
     * student…) name the person for staff and the anchored work for families.
     *
     * @return array{title: ?string, subtitle: ?string, avatar_url: ?string}
     */
    private function displayFor(User $user, Conversation $conversation): array
    {
        if ($conversation->kind === 'context') {
            return $this->contextDisplay($user, $conversation);
        }

        if ($conversation->kind === 'direct') {
            if ($conversation->student_id !== null) {
                $staff = $conversation->participants->first(fn ($p): bool => $p->user !== null)?->user;
                $familySide = in_array((int) $conversation->student_id, $this->access->childIds($user), true)
                    || $this->access->ownStudentId($user) === (int) $conversation->student_id;

                return $familySide
                    ? ['title' => $staff?->name, 'subtitle' => $conversation->student?->full_name, 'avatar_url' => $staff?->avatarUrl()]
                    : ['title' => $conversation->student?->full_name, 'subtitle' => $staff?->name, 'avatar_url' => null];
            }

            $other = $conversation->participants->first(fn ($p): bool => (int) $p->user_id !== $user->id)?->user;

            return ['title' => $other?->name, 'subtitle' => null, 'avatar_url' => $other?->avatarUrl()];
        }

        return ['title' => $conversation->title, 'subtitle' => null, 'avatar_url' => null];
    }

    /**
     * Context threads never carry a stored title — name them from the domain
     * object they anchor to. Staff see WHO (the student, with the work as the
     * subtitle); the family sees WHAT (the work, with the teacher underneath).
     *
     * @return array{title: ?string, subtitle: ?string, avatar_url: ?string}
     */
    private function contextDisplay(User $user, Conversation $conversation): array
    {
        $contextTitle = $this->contextTitle($conversation);
        $student = $conversation->student;

        $familySide = $conversation->student_id !== null
            && (in_array((int) $conversation->student_id, $this->access->childIds($user), true)
                || $this->access->ownStudentId($user) === (int) $conversation->student_id);

        if ($familySide) {
            $staff = $conversation->participants
                ->first(fn ($p): bool => $p->user !== null
                    && (int) $p->user_id !== (int) ($student?->user_id ?? 0))
                ?->user;

            return [
                'title' => $contextTitle ?? $staff?->name,
                'subtitle' => $contextTitle === null ? null : $staff?->name,
                'avatar_url' => $staff?->avatarUrl(),
            ];
        }

        return [
            'title' => $student?->full_name ?? $contextTitle,
            'subtitle' => $student === null ? null : $contextTitle,
            'avatar_url' => null,
        ];
    }

    /** Per-request cache of context object titles, primed in bulk by index(). */
    private array $contextTitles = [];

    /** @param  Collection<int, Conversation>  $conversations */
    private function primeContextTitles($conversations): void
    {
        foreach ($conversations->where('kind', 'context')->groupBy('context_type') as $type => $group) {
            $missing = $group->pluck('context_id')
                ->filter()
                ->unique()
                ->reject(fn ($id): bool => array_key_exists((int) $id, $this->contextTitles[$type] ?? []));

            if ($missing->isEmpty()) {
                continue;
            }

            $titles = match ($type) {
                'assignment' => Assignment::query()->whereIn('id', $missing)->pluck('title', 'id'),
                default => collect(),
            };

            foreach ($missing as $id) {
                $this->contextTitles[$type][(int) $id] = $titles->get((int) $id);
            }
        }
    }

    private function contextTitle(Conversation $conversation): ?string
    {
        if ($conversation->context_type === null || $conversation->context_id === null) {
            return null;
        }

        if (! array_key_exists((int) $conversation->context_id, $this->contextTitles[$conversation->context_type] ?? [])) {
            $this->primeContextTitles(collect([$conversation]));
        }

        return $this->contextTitles[$conversation->context_type][(int) $conversation->context_id] ?? null;
    }

    private function provisionFor(User $user, ChannelProvisioner $provisioner): void
    {
        $schoolIds = collect($this->access->staffScopes($user))->pluck('school_id');

        $studentIds = $this->access->childIds($user);
        $ownId = $this->access->ownStudentId($user);
        if ($ownId !== null) {
            $studentIds[] = $ownId;
        }

        if ($studentIds !== []) {
            $schoolIds = $schoolIds->merge(
                StudentEnrollment::query()
                    ->whereIn('student_id', $studentIds)
                    ->where('status', 'active')
                    ->pluck('school_id'),
            );
        }

        foreach ($schoolIds->unique() as $schoolId) {
            $school = School::query()->find($schoolId);
            if ($school !== null) {
                $provisioner->ensureForSchool($school);
            }
        }
    }
}
