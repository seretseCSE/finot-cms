<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'school_id', 'branch_id',
    'first_name', 'father_name', 'grandfather_name', 'gender', 'phone', 'photo_path',
    'birth_date', 'email', 'marital_status', 'nationality',
    'country', 'state', 'city', 'sub_city', 'woreda', 'house_no',
    'professional_level', 'retirement_on', 'check_in', 'check_out', 'is_active',
])]
#[Hidden(['search_text'])]
class Employee extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'retirement_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Normalise the contact phone to the canonical local form on every write.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => $value === null ? null : (PhoneNumber::normalize($value) ?? $value),
        );
    }

    /**
     * Full patronymic name (first + father + grandfather).
     *
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
     * Signed URL for the staff photo — private files, never direct links.
     * A real ACCESSOR (`$employee->photo_url`), matching Student/ParentProfile.
     *
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->photo_path !== null ? s3Url($this->photo_path) : null);
    }

    /**
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
     * @return HasMany<EmployeePosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    /**
     * @return HasMany<SubjectAssignment, $this>
     */
    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(SubjectAssignment::class);
    }

    /**
     * Current (not-ended) positions.
     *
     * @return HasMany<EmployeePosition, $this>
     */
    public function activePositions(): HasMany
    {
        return $this->positions()->whereNull('ended_on');
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<EmployeeAttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(EmployeeAttendanceRecord::class);
    }

    /**
     * @return HasMany<EmployeeQualification, $this>
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(EmployeeQualification::class);
    }

    /**
     * @return HasMany<EmployeeAllowance, $this>
     */
    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    /**
     * @return HasMany<EmployeeDeduction, $this>
     */
    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    /**
     * @return HasMany<EmployeeAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmployeeAttachment::class);
    }

    /**
     * Teaching capability rows (subject × grade this person can teach).
     *
     * @return HasMany<TeacherSubject, $this>
     */
    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }

    /**
     * UNAVAILABLE windows the timetable must respect (part-time days,
     * standing commitments). Availability is the default.
     *
     * @return HasMany<TeacherAvailability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(TeacherAvailability::class);
    }

    /**
     * Replace the teaching-capability rows with the given set.
     *
     * @param  list<array{subject_id: int, grade_level_id: int}>  $rows
     */
    public function syncTeacherSubjects(array $rows): void
    {
        $this->teacherSubjects()->delete();

        if ($rows !== []) {
            $unique = collect($rows)
                ->unique(fn (array $r): string => $r['subject_id'].':'.$r['grade_level_id'])
                ->values()
                ->all();

            $this->teacherSubjects()->createMany($unique);
        }
    }

    /** Whether the employee currently holds the given job title. */
    public function holdsJobTitle(string $jobTitle): bool
    {
        return $this->activePositions()->where('job_title', $jobTitle)->exists();
    }

    /**
     * Diff-sync the position rows: rows with an `id` update in place, rows
     * without are created, existing rows missing from the payload are
     * soft-deleted. In-place updates matter — attachments point at position ids.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncPositions(array $rows): void
    {
        $this->diffSyncChildren($this->positions(), $rows);
    }

    /**
     * Diff-sync the qualification rows (same contract as syncPositions —
     * attachments point at qualification ids, so rows update in place).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncQualifications(array $rows): void
    {
        $this->diffSyncChildren($this->qualifications(), $rows);
    }

    /**
     * @param  HasMany<covariant Model, $this>  $relation
     * @param  list<array<string, mixed>>  $rows
     */
    private function diffSyncChildren(HasMany $relation, array $rows): void
    {
        $keepIds = array_values(array_filter(array_column($rows, 'id')));

        $relation->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))->get()
            ->each(fn (Model $row) => $row->delete());

        foreach ($rows as $row) {
            $attributes = array_diff_key($row, ['id' => null]);

            if (! empty($row['id'])) {
                $relation->getRelated()->newQuery()
                    ->whereKey($row['id'])
                    ->where($relation->getForeignKeyName(), $this->id)
                    ->first()
                    ?->update($attributes);
            } else {
                $relation->create($attributes);
            }
        }
    }

    /**
     * Replace the allowance lines with the given set (few rows per employee, so
     * a delete + bulk insert is simpler and cheaper than diffing).
     *
     * @param  list<array{name: string, amount: string|float|int}>  $allowances
     */
    public function syncAllowances(array $allowances): void
    {
        $this->allowances()->delete();

        if ($allowances !== []) {
            $this->allowances()->createMany($allowances);
        }
    }

    /**
     * Replace the deduction lines with the given set.
     *
     * @param  list<array{name: string, amount: string|float|int}>  $deductions
     */
    public function syncDeductions(array $deductions): void
    {
        $this->deductions()->delete();

        if ($deductions !== []) {
            $this->deductions()->createMany($deductions);
        }
    }

    /**
     * Mirrors a membership assignment onto this user's HR record. Memberships
     * and employees both track "who works where, active or not" — without this,
     * assigning/moving someone via the Users page silently leaves their Employees-page
     * profile pointing at the old branch (or marked inactive).
     */
    public static function syncAssignment(int $userId, int $schoolId, int $branchId): void
    {
        static::where('user_id', $userId)->update([
            'is_active' => true,
            'school_id' => $schoolId,
            'branch_id' => $branchId,
        ]);
    }

    /**
     * Mirrors a membership activate/deactivate onto this user's HR record for
     * that specific branch, so the Employees page can't disagree with the Users page.
     */
    public static function syncBranchAccess(int $userId, int $branchId, bool $isActive): void
    {
        static::where('user_id', $userId)->where('branch_id', $branchId)->update(['is_active' => $isActive]);
    }
}
