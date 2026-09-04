<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Support\GradeOffering;
use App\Support\JobTitles;
use App\Support\PhoneNumber;
use App\Support\ReportCardSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'school_id', 'name', 'code', 'country', 'state', 'city',
    'sub_city', 'woreda', 'house_no', 'phone', 'longitude', 'latitude', 'settings', 'is_active',
])]
class Branch extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'longitude' => 'decimal:7',
            'latitude' => 'decimal:7',
        ];
    }

    /**
     * Normalise the branch phone to the canonical local form on every write.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => $value === null ? null : (PhoneNumber::normalizeContact($value) ?? $value),
        );
    }

    /**
     * @return BelongsTo<School, $this>
     */
    /**
     * Registration-fee gate this BRANCH enforces: its own override when set,
     * else the school default (soft). Policy is school-owned, enforcement is
     * branch-owned — directors may tighten/loosen their own branch.
     */
    public function effectiveRegistrationGate(): string
    {
        $gate = $this->settings['registration_gate'] ?? null;

        return in_array($gate, ['soft', 'hard'], true)
            ? $gate
            : ($this->school?->registrationGate() ?? 'soft');
    }

    /** Promotion pass mark for this branch: override, else school default (50). */
    public function effectivePromotionThreshold(): float
    {
        $value = $this->settings['promotion_threshold'] ?? null;

        if (! is_numeric($value)) {
            return $this->school?->promotionThreshold() ?? 50.0;
        }

        return max(0, min(100, (float) $value));
    }

    /** Teachers may define free-form assessments: override, else school default (off). */
    public function effectiveTeacherAssessmentsEnabled(): bool
    {
        $value = $this->settings['teacher_assessments_enabled'] ?? null;

        return is_bool($value) ? $value : ($this->school?->teacherAssessmentsEnabled() ?? false);
    }

    /**
     * Job titles whose employees get a portal account at hire: branch override,
     * else the school default. Role-mapped titles are always included.
     *
     * @return list<string>
     */
    public function effectiveEmployeeAccountJobTitles(): array
    {
        $override = $this->settings['employee_account_job_titles'] ?? null;

        return is_array($override)
            ? JobTitles::sanitizeAccountTitles($override)
            : ($this->school?->employeeAccountJobTitles() ?? JobTitles::defaultAccountTitles());
    }

    /** Communication-book mode (off|first|all): override, else school default ('all'). */
    public function effectiveChatApprovalMode(): string
    {
        return School::normalizeChatApprovalMode($this->settings['chat_teacher_parent_approval'] ?? null)
            ?? ($this->school?->chatApprovalMode() ?? 'all');
    }

    /** Chat template mode (suggested|required): override, else school default. */
    public function effectiveChatTemplateMode(): string
    {
        $value = $this->settings['chat_template_mode'] ?? null;

        return in_array($value, ['suggested', 'required'], true)
            ? $value
            : ($this->school?->chatTemplateMode() ?? 'suggested');
    }

    /** Student participation in chat: override, else school default (off). */
    public function effectiveChatStudentsEnabled(): bool
    {
        $value = $this->settings['chat_students_enabled'] ?? null;

        return is_bool($value) ? $value : ($this->school?->chatStudentsEnabled() ?? false);
    }

    /** Attendance alerts to guardians: branch override, else school default (on). */
    public function effectiveAttendanceSmsEnabled(): bool
    {
        $value = $this->settings['attendance_sms_enabled'] ?? null;

        return is_bool($value) ? $value : ($this->school?->attendanceSmsEnabled() ?? true);
    }

    /** Alert on late marks too: override, else school default (absent-only). */
    public function effectiveAttendanceSmsLate(): bool
    {
        $value = $this->settings['attendance_sms_late'] ?? null;

        return is_bool($value) ? $value : ($this->school?->attendanceSmsLate() ?? false);
    }

    /** Device mode — auto-absent sweep after cutoff: override, else school default (off). */
    public function effectiveDeviceAutoAbsent(): bool
    {
        $value = $this->settings['device_auto_absent'] ?? null;

        return is_bool($value) ? $value : ($this->school?->deviceAutoAbsent() ?? false);
    }

    /** Auto-absent cutoff (local H:i): override, else school default (09:30). */
    public function effectiveDeviceAbsentCutoff(): string
    {
        $value = $this->settings['device_absent_cutoff'] ?? null;

        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value)
            ? $value
            : ($this->school?->deviceAbsentCutoff() ?? '09:30');
    }

    /** Late-grace minutes for device scans: override, else school default (15). */
    public function effectiveDeviceLateGrace(): int
    {
        $value = $this->settings['device_late_grace'] ?? null;

        return is_numeric($value)
            ? max(0, min(120, (int) $value))
            : ($this->school?->deviceLateGrace() ?? 15);
    }

    /** Sibling-discount percent (0 = off): override, else school default. */
    public function effectiveSiblingDiscountPercent(): float
    {
        $value = $this->settings['sibling_discount_percent'] ?? null;

        return is_numeric($value)
            ? max(0, min(100, (float) $value))
            : ($this->school?->siblingDiscountPercent() ?? 0);
    }

    /** Enrolled children that trigger the sibling policy: override, else school default (2). */
    public function effectiveSiblingMinChildren(): int
    {
        $value = $this->settings['sibling_min_children'] ?? null;

        return is_numeric($value)
            ? max(2, min(10, (int) $value))
            : ($this->school?->siblingMinChildren() ?? 2);
    }

    /** Employee-child discount percent (0 = off): override, else school default. */
    public function effectiveStaffChildDiscountPercent(): float
    {
        $value = $this->settings['staff_child_discount_percent'] ?? null;

        return is_numeric($value)
            ? max(0, min(100, (float) $value))
            : ($this->school?->staffChildDiscountPercent() ?? 0);
    }

    /** Mid-period joiner billing (full | daily): override, else school default. */
    public function effectiveFeeProration(): string
    {
        $value = $this->settings['fee_proration'] ?? null;

        return in_array($value, ['full', 'daily'], true)
            ? $value
            : ($this->school?->feeProration() ?? 'full');
    }

    /** Calendar dates render in (ethiopian | gregorian): override, else school default. */
    public function effectiveCalendarMode(): string
    {
        $value = $this->settings['calendar_mode'] ?? null;

        return in_array($value, ['ethiopian', 'gregorian'], true)
            ? $value
            : ($this->school?->calendarMode() ?? 'ethiopian');
    }

    /** Clock convention (standard | ethiopian day-count): override, else school default. */
    public function effectiveClockMode(): string
    {
        $value = $this->settings['clock_mode'] ?? null;

        return in_array($value, ['standard', 'ethiopian'], true)
            ? $value
            : ($this->school?->clockMode() ?? 'ethiopian');
    }

    /** Report-card skill checklist: override list, else school default ([]). */
    public function effectiveReportCardSkills(): array
    {
        $value = $this->settings['report_card_skills'] ?? null;

        return is_array($value)
            ? ReportCardSettings::normalize($value)
            : ($this->school?->reportCardSkills() ?? []);
    }

    /** Semester cards per printed page (1 | 2 | 4): override, else school default (1). */
    public function effectiveReportCardPerPage(): int
    {
        $value = $this->settings['report_card_per_page'] ?? null;

        return is_numeric($value) && in_array((int) $value, [1, 2, 4], true)
            ? (int) $value
            : ($this->school?->reportCardPerPage() ?? 1);
    }

    /** Per-subject ranks on the semester card: override, else school default (off). */
    public function effectiveReportCardSubjectRanks(): bool
    {
        $value = $this->settings['report_card_subject_ranks'] ?? null;

        return is_bool($value) ? $value : ($this->school?->reportCardSubjectRanks() ?? false);
    }

    /** Grading-criteria legend on the yearly card: override, else school default (off). */
    public function effectiveReportCardGradingCriteria(): bool
    {
        $value = $this->settings['report_card_grading_criteria'] ?? null;

        return is_bool($value) ? $value : ($this->school?->reportCardGradingCriteria() ?? false);
    }

    /** Automated fee-reminder ladder on/off: override, else school default (on). */
    public function effectiveFeeRemindersEnabled(): bool
    {
        $value = $this->settings['fee_reminders_enabled'] ?? null;

        return is_bool($value) ? $value : ($this->school?->feeRemindersEnabled() ?? true);
    }

    /** Days before due for the upcoming reminder: override, else school default (3). */
    public function effectiveFeeReminderDaysBefore(): int
    {
        $value = $this->settings['fee_reminder_days_before'] ?? null;

        return is_numeric($value)
            ? max(0, min(30, (int) $value))
            : ($this->school?->feeReminderDaysBefore() ?? 3);
    }

    /** Days between overdue reminders: override, else school default (7). */
    public function effectiveFeeReminderOverdueEvery(): int
    {
        $value = $this->settings['fee_reminder_overdue_every'] ?? null;

        return is_numeric($value)
            ? max(1, min(60, (int) $value))
            : ($this->school?->feeReminderOverdueEvery() ?? 7);
    }

    /** Overdue reminders before the ladder stops: override, else school default (3). */
    public function effectiveFeeReminderOverdueMax(): int
    {
        $value = $this->settings['fee_reminder_overdue_max'] ?? null;

        return is_numeric($value)
            ? max(0, min(10, (int) $value))
            : ($this->school?->feeReminderOverdueMax() ?? 3);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * The branch's active director. Surfaces "who runs this branch" — eager load
     * `.user` for name/phone.
     *
     * @return HasOne<Membership, $this>
     */
    public function directorMembership(): HasOne
    {
        return $this->hasOne(Membership::class)
            ->where('role', Role::Director->value)
            ->where('is_active', true)
            ->latestOfMany();
    }

    /**
     * @return HasMany<AcademicYear, $this>
     */
    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * @return HasMany<SchoolProgram, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(SchoolProgram::class);
    }

    /**
     * @return HasMany<StudentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * List-table vitals — current students, active teachers, sections and the
     * grade span served — as indexed subselects so a page of branches stays a
     * single query. Pair with the matching resource fields.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithListStats(Builder $query): void
    {
        // The grade span is the branch's OFFERING (its configured matrix), not
        // whichever sections happen to exist yet.
        $gradeEdge = fn (string $direction) => GradeOffering::gradeEdge($direction);

        $query
            ->withCount([
                'enrollments as students_count' => fn (Builder $q) => $q
                    ->where('status', EnrollmentStatus::Active->value),
                'employees as teachers_count' => fn (Builder $q) => $q
                    ->where('is_active', true)
                    ->whereHas('activePositions', fn (Builder $p) => $p->where('job_title', JobTitles::TEACHER)),
                'sections as sections_count' => fn (Builder $q) => $q->where('is_active', true),
            ])
            ->addSelect(['grade_min' => $gradeEdge('asc'), 'grade_max' => $gradeEdge('desc')]);
    }
}
