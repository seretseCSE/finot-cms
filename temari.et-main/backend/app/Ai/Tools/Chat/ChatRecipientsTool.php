<?php

namespace App\Ai\Tools\Chat;

use App\Ai\Tools\AiTool;
use App\Models\Conversation;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Chat\ChatDirectory;
use App\Services\Chat\ConversationAccess;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Who may this user message in the chat engine (ADR-019)? A strict mirror of
 * the new-chat picker: staff lanes see reachable colleagues + the families
 * of students they may open a thread with (ownership-bounded for teachers)
 * + the announcement channels they can post in; family lanes see their
 * children's teachers/homeroom/office via the live guardian link. The tool
 * only LISTS — sending always happens app-side by the user's own tap, so a
 * hallucinated id can never reach anyone (the chat endpoints re-validate).
 */
class ChatRecipientsTool extends AiTool
{
    public function description(): Stringable|string
    {
        return 'List who the user can message in the school chat: staff colleagues, the family threads of students they may contact, and announcement channels they can post in. Use q to search by name. ALWAYS call this before proposing a send_message block — recipient ids must come from this tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        $directory = app(ChatDirectory::class);
        $input = $request->all();

        if ($this->context->lane->isFamilyLane()) {
            return $this->familySide($directory, trim((string) ($input['q'] ?? '')));
        }

        if ($this->context->schoolId() === null) {
            return $this->deny('Messaging works inside a school workspace only.');
        }

        $audience = (string) ($input['audience'] ?? '');
        $q = trim((string) ($input['q'] ?? ''));
        $data = [];

        if ($audience === '' || $audience === 'staff') {
            $data['staff'] = $directory
                ->staffFor($this->context->user, $this->context->schoolId(), $this->context->branchId(), $q === '' ? null : $q)
                ->map(fn (User $u): array => ['user_id' => $u->id, 'name' => $u->name])
                ->values();
        }

        if ($audience === '' || $audience === 'families') {
            $data['families'] = $this->familyThreads($directory, $q);
        }

        if ($audience === '' || $audience === 'channels') {
            $data['channels'] = $this->postableChannels();
        }

        if (collect($data)->every(fn ($rows): bool => count($rows) === 0)) {
            return $this->ok([
                'staff' => [], 'families' => [], 'channels' => [],
                'note' => $q !== ''
                    ? 'No one matched that name — try another spelling or a shorter part of the name.'
                    : 'No chat recipients are available in this workspace.',
            ]);
        }

        return $this->ok($data + [
            'note' => 'family = the thread with that student\'s parents/guardians (never a single parent directly). Sending always goes through the app\'s Send card — you only draft.',
        ]);
    }

    /**
     * Staff side: students whose FAMILY thread this user may open — the
     * directory already applies the ownership/supervisory split.
     *
     * @return list<array<string, mixed>>
     */
    private function familyThreads(ChatDirectory $directory, string $q): array
    {
        $students = $directory->studentsFor(
            $this->context->user,
            $this->context->schoolId(),
            $this->context->branchId(),
            $q === '' ? null : $q,
        );

        if ($students->isEmpty()) {
            return [];
        }

        $students->loadMissing('guardians.parentProfile:id,first_name,father_name');

        $enrollments = StudentEnrollment::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'active')
            ->with(['gradeLevel:id,name', 'section:id,name'])
            ->get()
            ->keyBy('student_id');

        return $students->map(function (Student $student) use ($enrollments): array {
            $enrollment = $enrollments->get($student->id);

            return [
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'class' => $enrollment === null ? null : trim(($enrollment->gradeLevel?->name ?? '').' '.($enrollment->section?->name ?? '')),
                'guardians' => $student->guardians
                    ->map(fn ($link): string => trim(($link->parentProfile?->first_name ?? '').' '.($link->parentProfile?->father_name ?? '')))
                    ->filter(fn (string $name): bool => $name !== '')
                    ->values(),
            ];
        })->values()->all();
    }

    /**
     * Announcement channels the user is in AND may post to (admin-posted
     * channels require chat.announce — canPost already decides).
     *
     * @return list<array<string, mixed>>
     */
    private function postableChannels(): array
    {
        $access = app(ConversationAccess::class);

        $ids = $access->accessibleIds($this->context->user);

        if ($ids === []) {
            return [];
        }

        return Conversation::query()
            ->whereIn('id', $ids)
            ->where('kind', 'channel')
            ->where('school_id', $this->context->schoolId())
            ->when($this->context->branchId() !== null, fn ($query) => $query->where(function ($w): void {
                $w->whereNull('branch_id')->orWhere('branch_id', $this->context->branchId());
            }))
            ->with('targets:id,conversation_id,audience')
            ->limit(25)
            ->get()
            ->filter(fn (Conversation $c): bool => $access->canPost($this->context->user, $c))
            ->take(10)
            ->map(fn (Conversation $c): array => [
                'conversation_id' => $c->id,
                'title' => $c->title,
                'audience' => $c->targets->pluck('audience')->unique()->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * Family lane: the guardian's/student's reachable partners per child —
     * derived from the LIVE guardian link, exactly like the /me chat picker.
     */
    private function familySide(ChatDirectory $directory, string $q): string
    {
        $cards = collect($directory->familyPartners($this->context->user))
            ->map(fn (array $card): array => [
                'student_id' => $card['student_id'],
                'student_name' => $card['student_name'],
                'branch' => $card['branch_name'],
                'partners' => collect($card['partners'])
                    ->when($q !== '', fn ($partners) => $partners->filter(
                        fn (array $p): bool => mb_stripos((string) $p['name'], $q) !== false,
                    ))
                    ->map(fn (array $p): array => [
                        'user_id' => $p['user_id'],
                        'name' => $p['name'],
                        'role' => $p['role'],
                        'subject' => $p['subject'],
                    ])
                    ->values(),
            ]);

        if ($cards->isEmpty()) {
            return $this->deny('No school contacts are available — messaging opens once a child has an active enrollment.');
        }

        return $this->ok([
            'children' => $cards,
            'note' => 'Each partner is messaged about ONE child: a send_message block for them needs BOTH the user_id and that child\'s student_id.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Name search (person, student or channel). Omit to list everyone available.'),
            'audience' => $schema->string()->description('Staff lanes only — narrow to one group: staff, families, or channels. Omit for all.'),
        ];
    }
}
