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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'phone', 'address', 'settings', 'is_active'])]
class School extends Model
{
    use SoftDeletes;

    /**
     * Normalise the school phone to the canonical local form on every write.
     * Accepts mobile or geographic office landlines (see PhoneNumber::normalizeContact).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::set(
            fn (?string $value): ?string => $value === null ? null : (PhoneNumber::normalizeContact($value) ?? $value),
        );
    }

    /** Signed URL for the school logo — private files, never direct links. */
    public function logoUrl(): ?string
    {
        return $this->logo_path !== null ? s3Url($this->logo_path) : null;
    }

    /**
     * Enrollment gate policy: `soft` (default) lets registrars provisionally
     * activate an enrollment before the registration fee is settled; `hard`
     * only activates on payment/scholarship.
     */
    public function registrationGate(): string
    {
        $gate = $this->settings['registration_gate'] ?? 'soft';

        return in_array($gate, ['soft', 'hard'], true) ? $gate : 'soft';
    }

    /**
     * Minimum annual average (0–100) for a promotion suggestion. MoE policy
     * default is 50; schools may tighten or relax it.
     */
    public function promotionThreshold(): float
    {
        $value = (float) ($this->settings['promotion_threshold'] ?? 50);

        return max(0, min(100, $value));
    }

    /**
     * Whether teachers may define their own free-form assessments on
     * marklists with no continuous-assessment plan. Off by default: the
     * office defines the plan; branches that trust teachers opt in.
     */
    public function teacherAssessmentsEnabled(): bool
    {
        return (bool) ($this->settings['teacher_assessments_enabled'] ?? false);
    }

    /**
     * Job titles whose employees get a portal account at hire (school default,
     * branch-overridable). The four role-mapped titles are always included —
     * their branch memberships cannot exist without a user account.
     *
     * @return list<string>
     */
    public function employeeAccountJobTitles(): array
    {
        $configured = $this->settings['employee_account_job_titles'] ?? null;

        return is_array($configured)
            ? JobTitles::sanitizeAccountTitles($configured)
            : JobTitles::defaultAccountTitles();
    }

    /** Whether guardians are texted/emailed when a student is marked absent. */
    public function attendanceSmsEnabled(): bool
    {
        return (bool) ($this->settings['attendance_sms_enabled'] ?? true);
    }

    /** Whether late marks also alert guardians (absent-only by default). */
    public function attendanceSmsLate(): bool
    {
        return (bool) ($this->settings['attendance_sms_late'] ?? false);
    }

    /**
     * Device attendance mode: after the cutoff, unscanned students are marked
     * absent automatically. Off by default — only branches that actually run
     * RFID gates should turn it on, or manual registers get clobbered.
     */
    public function deviceAutoAbsent(): bool
    {
        return (bool) ($this->settings['device_auto_absent'] ?? false);
    }

    /** Local (Addis) time the auto-absent sweep runs, H:i. */
    public function deviceAbsentCutoff(): string
    {
        $value = $this->settings['device_absent_cutoff'] ?? '09:30';

        return preg_match('/^\d{2}:\d{2}$/', (string) $value) ? $value : '09:30';
    }

    /** Grace minutes after the expected start before a scan counts as late. */
    public function deviceLateGrace(): int
    {
        $value = (int) ($this->settings['device_late_grace'] ?? 15);

        return max(0, min(120, $value));
    }

    /**
     * Sibling-discount policy: percentage off for families with
     * `siblingMinChildren()`+ enrolled children. 0 = policy off. Generates
     * PENDING concession suggestions only — finance approves each one.
     */
    public function siblingDiscountPercent(): float
    {
        return max(0, min(100, (float) ($this->settings['sibling_discount_percent'] ?? 0)));
    }

    /** How many concurrently enrolled children trigger the sibling policy. */
    public function siblingMinChildren(): int
    {
        return max(2, min(10, (int) ($this->settings['sibling_min_children'] ?? 2)));
    }

    /**
     * Employee-child discount: percentage off when a guardian is an active
     * employee of the school. 0 = policy off. Suggestion-only, like siblings.
     */
    public function staffChildDiscountPercent(): float
    {
        return max(0, min(100, (float) ($this->settings['staff_child_discount_percent'] ?? 0)));
    }

    /**
     * Mid-period joiners on recurring fees: `full` (default) bills the whole
     * period; `daily` prorates by the days remaining in the period.
     */
    public function feeProration(): string
    {
        $value = $this->settings['fee_proration'] ?? 'full';

        return in_array($value, ['full', 'daily'], true) ? $value : 'full';
    }

    /**
     * Whether one person may both record AND approve an expense. Off by
     * default (four-eyes rule); small schools with a single finance person
     * opt in explicitly.
     */
    public function financeSelfApprovalAllowed(): bool
    {
        return (bool) ($this->settings['finance_self_approval'] ?? false);
    }

    /**
     * Whether branch directors get finance authority (fee structures, payment
     * recording, the finance books). Off by default — in Ethiopian schools the
     * director is the academic head; money is the finance officer's and the
     * principal's domain. Changeable only at school scope (updateSettings),
     * so a director can never grant it to themselves.
     */
    public function directorFinanceAccessEnabled(): bool
    {
        return (bool) ($this->settings['director_finance_access'] ?? false);
    }

    /**
     * Whether employees holding an active department_head position review
     * lesson plans alongside the director/principal (the paper ritual's
     * "department head signs first"). Off by default — most schools run the
     * director/principal chain only.
     */
    public function lessonPlanDepartmentReviewEnabled(): bool
    {
        return (bool) ($this->settings['lesson_plan_department_review'] ?? false);
    }

    /**
     * The digital communication book (ADR-019): whether a teacher's outbound
     * messages to families wait for a director's approval before the family
     * sees them. Modes: 'all' (every message — the default, conservative fit
     * for Ethiopian school culture), 'first' (only until the director has
     * approved one message by that teacher in the thread), 'off'. Legacy
     * boolean values map true→all / false→off. Branches may override
     * (Branch::effectiveChatApprovalMode).
     */
    public function chatApprovalMode(): string
    {
        return self::normalizeChatApprovalMode($this->settings['chat_teacher_parent_approval'] ?? null) ?? 'all';
    }

    public static function normalizeChatApprovalMode(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? 'all' : 'off';
        }

        return in_array($value, ['off', 'first', 'all'], true) ? $value : null;
    }

    /**
     * How preset chat templates bind teachers writing to families:
     * 'suggested' (default — the picker is a convenience, free text allowed)
     * or 'required' (family-reaching messages must BE a template; moderators
     * are exempt). Branches may override (Branch::effectiveChatTemplateMode).
     */
    public function chatTemplateMode(): string
    {
        $value = $this->settings['chat_template_mode'] ?? null;

        return in_array($value, ['suggested', 'required'], true) ? $value : 'suggested';
    }

    /**
     * Whether students participate in chat (classroom channels, DMs to their
     * teachers). OFF by default — age-appropriateness is the school's call.
     */
    public function chatStudentsEnabled(): bool
    {
        return (bool) ($this->settings['chat_students_enabled'] ?? false);
    }

    /**
     * The school-defined behavioral/skill checklist printed on report cards
     * (the "Academic/Behavioral Assessment" panel). Empty (the default) = no
     * panel and no entry surface. Rows: {key, group, label{en,am,om}}, rated
     * on the fixed E/VG/S/NI scale. Branch-overridable.
     *
     * @return list<array{key: string, group: string, label: array{en: string, am: string, om: string}}>
     */
    public function reportCardSkills(): array
    {
        return ReportCardSettings::normalize((array) ($this->settings['report_card_skills'] ?? []));
    }

    /** Semester report cards per printed page: 1 (relaxed), 2 (stacked halves) or 4 (quarter grid). */
    public function reportCardPerPage(): int
    {
        $value = (int) ($this->settings['report_card_per_page'] ?? 1);

        return in_array($value, [1, 2, 4], true) ? $value : 1;
    }

    /** Whether the semester card shows each subject's section rank beside the mark. Off by default. */
    public function reportCardSubjectRanks(): bool
    {
        return (bool) ($this->settings['report_card_subject_ranks'] ?? false);
    }

    /** Whether the yearly card prints the grading-criteria legend. Off by default. */
    public function reportCardGradingCriteria(): bool
    {
        return (bool) ($this->settings['report_card_grading_criteria'] ?? false);
    }

    /**
     * The calendar every date in this school's UI, SMS and documents renders
     * in: `ethiopian` (the national default) or `gregorian`. Display-only —
     * storage is always Gregorian/UTC; official PDFs always print BOTH.
     */
    public function calendarMode(): string
    {
        $value = $this->settings['calendar_mode'] ?? 'ethiopian';

        return in_array($value, ['ethiopian', 'gregorian'], true) ? $value : 'ethiopian';
    }

    /**
     * How times of day are written: `ethiopian` (the default — the day counted
     * from dawn, "2:00 ጠዋት" = 8:00 AM, how schools speak their bell times) or
     * `standard` (8:00 AM). Display-only; storage stays 24h wall time.
     */
    public function clockMode(): string
    {
        $value = $this->settings['clock_mode'] ?? 'ethiopian';

        return in_array($value, ['standard', 'ethiopian'], true) ? $value : 'ethiopian';
    }

    /** Master switch for the automated fee-reminder ladder. */
    public function feeRemindersEnabled(): bool
    {
        return (bool) ($this->settings['fee_reminders_enabled'] ?? true);
    }

    /** Days BEFORE the due date the "upcoming" reminder goes out (0 = skip). */
    public function feeReminderDaysBefore(): int
    {
        return max(0, min(30, (int) ($this->settings['fee_reminder_days_before'] ?? 3)));
    }

    /** Days between overdue reminders once the due date has passed. */
    public function feeReminderOverdueEvery(): int
    {
        return max(1, min(60, (int) ($this->settings['fee_reminder_overdue_every'] ?? 7)));
    }

    /** How many overdue reminders are sent before the ladder stops. */
    public function feeReminderOverdueMax(): int
    {
        return max(0, min(10, (int) ($this->settings['fee_reminder_overdue_max'] ?? 3)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'ai_plan_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * School Plan AI entitlement: School AI (leadership analytics + teacher
     * copilot) is on while ai_plan_until is today or later. Deliberately NOT
     * in Fillable — only the platform grant endpoint and school_plan gateway
     * fulfilment write it (forceFill), never school-side updates.
     */
    public function aiPlanActive(): bool
    {
        return $this->ai_plan_until !== null && $this->ai_plan_until->endOfDay()->isFuture();
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<StudentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * List-table vitals — branches, current students, active teachers and the
     * grade span served — as indexed subselects so a page of schools stays a
     * single query. Pair with the matching resource fields.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithListStats(Builder $query): void
    {
        // The grade span is the union of the school's branch OFFERINGS (their
        // configured matrices), not whichever sections happen to exist yet.
        $gradeEdge = fn (string $direction) => GradeOffering::gradeEdge($direction, 'school');

        $query
            ->withCount([
                'branches',
                'enrollments as students_count' => fn (Builder $q) => $q
                    ->where('status', EnrollmentStatus::Active->value),
                'employees as teachers_count' => fn (Builder $q) => $q
                    ->where('is_active', true)
                    ->whereHas('activePositions', fn (Builder $p) => $p->where('job_title', JobTitles::TEACHER)),
            ])
            ->addSelect(['grade_min' => $gradeEdge('asc'), 'grade_max' => $gradeEdge('desc')]);
    }

    /**
     * Active school-scoped contacts (principal / school_admin). Surfaces "who
     * runs this school" — eager load `.user` for name/phone.
     *
     * @return HasMany<Membership, $this>
     */
    public function contactMemberships(): HasMany
    {
        return $this->hasMany(Membership::class)
            ->whereNull('branch_id')
            ->whereIn('role', [Role::Principal->value, Role::SchoolAdmin->value])
            ->where('is_active', true);
    }
}
