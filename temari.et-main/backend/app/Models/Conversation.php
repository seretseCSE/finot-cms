<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ONE conversation engine for every chat surface (ADR-019). See the
 * conversations migration for the four kinds and their membership models.
 * Access is NEVER checked here — App\Services\Chat\ConversationAccess is the
 * single gate for visibility, posting rights and the approval gate.
 */
#[Fillable([
    'school_id', 'branch_id', 'kind', 'title', 'section_id', 'student_id',
    'context_type', 'context_id', 'direct_key', 'system_key', 'created_by', 'settings',
    'last_message_at', 'archived_at',
])]
class Conversation extends Model
{
    use SoftDeletes;

    public const KINDS = ['direct', 'group', 'channel', 'context'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @param  array<string, mixed>|null  $default */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /** Channels where only chat.announce holders post (announcement lanes). */
    public function adminPostedOnly(): bool
    {
        return $this->setting('posting', 'all') === 'admins';
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * The dedupe key that guarantees ONE direct thread per pair. Family
     * directs key on student × staff user (all guardians of the child share
     * the thread); staff↔staff directs key on the sorted user pair.
     */
    public static function directKeyFor(int $schoolId, ?int $studentId, int $userA, ?int $userB = null): string
    {
        if ($studentId !== null) {
            return "s{$schoolId}:st{$studentId}:u{$userA}";
        }

        $ids = [$userA, $userB];
        sort($ids);

        return "s{$schoolId}:u{$ids[0]}:u{$ids[1]}";
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /** @return HasMany<ConversationTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(ConversationTarget::class);
    }

    /** @return HasMany<ConversationUserState, $this> */
    public function userStates(): HasMany
    {
        return $this->hasMany(ConversationUserState::class);
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
