<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\TransferRequestStatus;
use App\Support\PhoneNumber;
use App\Support\PublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'school_id', 'branch_id',
    'first_name', 'father_name', 'grandfather_name', 'mother_name',
    'gender', 'date_of_birth', 'national_student_id', 'fayda_hash',
    'primary_phone', 'email', 'citizenship', 'marital_status', 'photo_path',
    'languages', 'blood_type', 'health_notes',
    'birth_country', 'birth_state', 'birth_city', 'birth_sub_city', 'birth_woreda',
    'country', 'state', 'city', 'sub_city', 'woreda', 'house_no',
    'is_active',
])]
#[Hidden(['fayda_hash', 'search_text'])]
class Student extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'date',
            'languages' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Public-facing code (H8R6WV) — students carry their own because many
        // (young children) have no user account.
        static::creating(function (self $student): void {
            $student->public_id ??= PublicId::generate('students');
        });
    }

    /**
     * Normalise the contact phone to the canonical local form on every write.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function primaryPhone(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => $value === null ? null : (PhoneNumber::normalize($value) ?? $value),
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->first_name,
            $this->father_name,
            $this->grandfather_name,
        ]))));
    }

    /**
     * The student's OWN login (nullable — young children have none).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<StudentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * @return HasMany<StudentGuardian, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    /**
     * Relationship-lane grades access (ADR-012): the student's own account,
     * or an active guardian link that carries can_view_grades. Used by the
     * official-PDF lane so families get the same QR-bearing documents staff
     * print — never a staff policy.
     */
    public function familyMayViewGrades(User $user): bool
    {
        if ($this->user_id !== null && $this->user_id === $user->id) {
            return true;
        }

        $parentId = $user->parentProfile()->value('id');

        if ($parentId === null) {
            return false;
        }

        return $this->guardians()
            ->where('parent_id', $parentId)
            ->where('is_active', true)
            ->where('can_view_grades', true)
            ->exists();
    }

    /**
     * @return HasMany<StudentAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(StudentAttachment::class);
    }

    /**
     * @return BelongsToMany<HealthCondition, $this>
     */
    public function healthConditions(): BelongsToMany
    {
        return $this->belongsToMany(HealthCondition::class, 'student_health_conditions')
            ->withPivot('severity', 'notes', 'medication')
            ->withTimestamps();
    }

    /**
     * Signed URL for the student photo — private files, never direct links.
     * A real ACCESSOR (`$student->photo_url`), not a plain method: report
     * payloads read the attribute and silently got null before.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path !== null ? s3Url($this->photo_path) : null);
    }

    /**
     * The student's most recent live (pending or active) enrollment, if any.
     * Pending rows count — a just-registered student must surface their seat
     * even before the registration fee clears.
     *
     * @return HasOne<StudentEnrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)
            ->whereIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Active->value])
            ->latestOfMany('academic_year_id');
    }

    /**
     * Every (school_id, branch_id) scope in which school staff may SEE this
     * student: the registration branch (provenance) plus every branch the
     * student has ever been enrolled at. Students themselves are global
     * persons (ADR-011) — staff authority over them always flows through one
     * of these scopes, judged with User::hasPermissionForScope().
     *
     * Former schools stay in this set on purpose: they keep a read-only
     * archive view of their own era. LIVE authority (edit, documents, health,
     * guardians) uses activeAdminScopes() instead.
     *
     * @return list<array{0: ?int, 1: ?int}>
     */
    public function adminScopes(): array
    {
        $scopes = [];

        if ($this->branch_id !== null) {
            $scopes[] = [$this->school_id !== null ? (int) $this->school_id : null, (int) $this->branch_id];
        }

        foreach ($this->enrollments()->get(['school_id', 'branch_id']) as $enrollment) {
            $scopes[] = [(int) $enrollment->school_id, (int) $enrollment->branch_id];
        }

        return array_values(array_unique($scopes, SORT_REGULAR));
    }

    /**
     * The scopes holding live CUSTODY of this student — the only places that
     * may mutate the record (profile, documents, health data, guardian links,
     * new enrollments). Custody follows the enrollment:
     *
     *  1. every branch with a pending/active enrollment;
     *  2. else the branch of the most recent enrollment — the last school to
     *     hold the student keeps custody of a withdrawn/graduated/mid-rollover
     *     record until the student moves on;
     *  3. else (never enrolled) the registration branch.
     *
     * A transfer therefore hands custody forward the moment the receiving
     * branch opens its enrollment: the sending school drops to the read-only
     * archive lane (adminScopes) and gains zero forward visibility.
     *
     * @return list<array{0: ?int, 1: ?int}>
     */
    public function activeAdminScopes(): array
    {
        $enrollments = $this->enrollments()->get(['id', 'school_id', 'branch_id', 'status']);

        $live = $enrollments
            ->filter(fn (StudentEnrollment $e): bool => in_array(
                $e->status,
                [EnrollmentStatus::Pending, EnrollmentStatus::Active],
                true,
            ))
            ->map(fn (StudentEnrollment $e): array => [(int) $e->school_id, (int) $e->branch_id])
            ->all();

        if ($live !== []) {
            return array_values(array_unique($live, SORT_REGULAR));
        }

        if ($enrollments->isNotEmpty()) {
            $latest = $enrollments->sortByDesc('id')->first();

            return [[(int) $latest->school_id, (int) $latest->branch_id]];
        }

        if ($this->branch_id !== null) {
            return [[$this->school_id !== null ? (int) $this->school_id : null, (int) $this->branch_id]];
        }

        return [];
    }

    /**
     * The handover snapshot serving the user's archive view: the latest
     * approved transfer OUT of a school where the user may see this student.
     * Frozen at approval (ADR-017) — the former school reads the file as the
     * student left it, never the live record.
     *
     * @return array<string, mixed>|null
     */
    public function archiveSnapshotFor(User $user, string $permission = 'students.view'): ?array
    {
        $schoolIds = collect($this->adminScopes())
            ->filter(fn (array $scope): bool => $user->hasPermissionForScope($permission, $scope[0], $scope[1]))
            ->pluck(0)
            ->filter()
            ->unique()
            ->values();

        if ($schoolIds->isEmpty()) {
            return null;
        }

        return StudentTransferRequest::query()
            ->where('student_id', $this->id)
            ->where('status', TransferRequestStatus::Approved)
            ->whereIn('from_school_id', $schoolIds)
            ->whereNotNull('handover_snapshot')
            ->latest('decided_at')
            ->first()
            ?->handover_snapshot;
    }

    /**
     * True when the user's authority over this student is archive-only: they
     * may see the record through a former scope but hold no live custody. The
     * detail payload is trimmed accordingly (no documents/health, no foreign
     * enrollment data).
     */
    public function isArchiveOnlyFor(User $user, string $permission = 'students.view'): bool
    {
        if ($user->isSuperAdmin() || $user->hasPlatformPermission($permission)) {
            return false;
        }

        foreach ($this->activeAdminScopes() as [$schoolId, $branchId]) {
            if ($user->hasPermissionForScope($permission, $schoolId, $branchId)) {
                return false;
            }
        }

        return true;
    }
}
