<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\School;
use App\Support\DateFormatter;
use App\Support\JobTitles;
use App\Support\ReportCardSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * The Branch settings hub: per-branch academic policy OVERRIDES on top of the
 * school defaults. Directors tune their own branch; principals tune any
 * branch of their school (`branch_settings.manage`, scope-checked). A null
 * override means "inherit the school default" — the payload always carries
 * both layers so the UI can show what is inherited vs pinned.
 */
class BranchSettingsController extends Controller
{
    public function show(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeBranch($request, $branch);

        return response()->json(['data' => $this->payload($branch)]);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->authorizeBranch($request, $branch);

        $data = $request->validate([
            // null clears the override → the branch inherits the school default.
            'registration_gate' => ['sometimes', 'nullable', 'in:soft,hard'],
            // Calendar & clock display overrides.
            'calendar_mode' => ['sometimes', 'nullable', 'in:ethiopian,gregorian'],
            'clock_mode' => ['sometimes', 'nullable', 'in:standard,ethiopian'],
            'promotion_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'teacher_assessments_enabled' => ['sometimes', 'nullable', 'boolean'],
            'employee_account_job_titles' => ['sometimes', 'nullable', 'array'],
            'employee_account_job_titles.*' => [Rule::in(JobTitles::ALL)],
            'attendance_sms_enabled' => ['sometimes', 'nullable', 'boolean'],
            'attendance_sms_late' => ['sometimes', 'nullable', 'boolean'],
            'device_auto_absent' => ['sometimes', 'nullable', 'boolean'],
            'device_absent_cutoff' => ['sometimes', 'nullable', 'date_format:H:i'],
            'device_late_grace' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
            // Concession policy overrides — 0 turns a policy off for the branch.
            'sibling_discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'sibling_min_children' => ['sometimes', 'nullable', 'integer', 'min:2', 'max:10'],
            'staff_child_discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            // Billing & reminder-ladder overrides.
            'fee_proration' => ['sometimes', 'nullable', 'in:full,daily'],
            'fee_reminders_enabled' => ['sometimes', 'nullable', 'boolean'],
            'fee_reminder_days_before' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:30'],
            'fee_reminder_overdue_every' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:60'],
            'fee_reminder_overdue_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            // Chat (ADR-019) overrides.
            'chat_teacher_parent_approval' => ['sometimes', 'nullable', 'in:off,first,all'],
            'chat_students_enabled' => ['sometimes', 'nullable', 'boolean'],
            'chat_template_mode' => ['sometimes', 'nullable', 'in:suggested,required'],
            // Report-card overrides (skills list replaces the school's wholesale).
            ...ReportCardSettings::skillRules(),
            'report_card_per_page' => ['sometimes', 'nullable', 'integer', 'in:1,2,4'],
            'report_card_subject_ranks' => ['sometimes', 'nullable', 'boolean'],
            'report_card_grading_criteria' => ['sometimes', 'nullable', 'boolean'],
        ]);

        if (isset($data['report_card_skills'])) {
            $data['report_card_skills'] = ReportCardSettings::normalize($data['report_card_skills']);
        }

        $settings = $branch->settings ?? [];

        foreach ([
            'registration_gate', 'calendar_mode', 'clock_mode',
            'promotion_threshold', 'teacher_assessments_enabled',
            'employee_account_job_titles',
            'attendance_sms_enabled', 'attendance_sms_late',
            'device_auto_absent', 'device_absent_cutoff', 'device_late_grace',
            'sibling_discount_percent', 'sibling_min_children', 'staff_child_discount_percent',
            'fee_proration', 'fee_reminders_enabled', 'fee_reminder_days_before',
            'fee_reminder_overdue_every', 'fee_reminder_overdue_max',
            'chat_teacher_parent_approval', 'chat_students_enabled', 'chat_template_mode',
            'report_card_skills', 'report_card_per_page',
            'report_card_subject_ranks', 'report_card_grading_criteria',
        ] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if ($data[$key] === null) {
                unset($settings[$key]);
            } else {
                $settings[$key] = $data[$key];
            }
        }

        $branch->update(['settings' => $settings ?: null]);

        Cache::forget("display-modes:{$branch->school_id}:{$branch->id}");
        DateFormatter::flushMemo();

        return response()->json([
            'data' => $this->payload($branch->refresh()),
            'message' => 'Branch settings saved.',
        ]);
    }

    private function authorizeBranch(Request $request, Branch $branch): void
    {
        abort_unless(
            $request->user()->hasPermissionForScope('branch_settings.manage', $branch->school_id, $branch->id),
            403,
        );
    }

    /** @return array<string, mixed> */
    private function payload(Branch $branch): array
    {
        $branch->loadMissing('school');

        return [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'school_name' => $branch->school?->name,
            'effective' => [
                'registration_gate' => $branch->effectiveRegistrationGate(),
                'calendar_mode' => $branch->effectiveCalendarMode(),
                'clock_mode' => $branch->effectiveClockMode(),
                'promotion_threshold' => $branch->effectivePromotionThreshold(),
                'teacher_assessments_enabled' => $branch->effectiveTeacherAssessmentsEnabled(),
                'employee_account_job_titles' => $branch->effectiveEmployeeAccountJobTitles(),
                'attendance_sms_enabled' => $branch->effectiveAttendanceSmsEnabled(),
                'attendance_sms_late' => $branch->effectiveAttendanceSmsLate(),
                'device_auto_absent' => $branch->effectiveDeviceAutoAbsent(),
                'device_absent_cutoff' => $branch->effectiveDeviceAbsentCutoff(),
                'device_late_grace' => $branch->effectiveDeviceLateGrace(),
                'sibling_discount_percent' => $branch->effectiveSiblingDiscountPercent(),
                'sibling_min_children' => $branch->effectiveSiblingMinChildren(),
                'staff_child_discount_percent' => $branch->effectiveStaffChildDiscountPercent(),
                'fee_proration' => $branch->effectiveFeeProration(),
                'fee_reminders_enabled' => $branch->effectiveFeeRemindersEnabled(),
                'fee_reminder_days_before' => $branch->effectiveFeeReminderDaysBefore(),
                'fee_reminder_overdue_every' => $branch->effectiveFeeReminderOverdueEvery(),
                'fee_reminder_overdue_max' => $branch->effectiveFeeReminderOverdueMax(),
                'chat_teacher_parent_approval' => $branch->effectiveChatApprovalMode(),
                'chat_students_enabled' => $branch->effectiveChatStudentsEnabled(),
                'chat_template_mode' => $branch->effectiveChatTemplateMode(),
                'report_card_skills' => $branch->effectiveReportCardSkills(),
                'report_card_per_page' => $branch->effectiveReportCardPerPage(),
                'report_card_subject_ranks' => $branch->effectiveReportCardSubjectRanks(),
                'report_card_grading_criteria' => $branch->effectiveReportCardGradingCriteria(),
            ],
            'overrides' => [
                'registration_gate' => $branch->settings['registration_gate'] ?? null,
                'calendar_mode' => $branch->settings['calendar_mode'] ?? null,
                'clock_mode' => $branch->settings['clock_mode'] ?? null,
                'promotion_threshold' => isset($branch->settings['promotion_threshold'])
                    ? (float) $branch->settings['promotion_threshold']
                    : null,
                'teacher_assessments_enabled' => $branch->settings['teacher_assessments_enabled'] ?? null,
                'employee_account_job_titles' => $branch->settings['employee_account_job_titles'] ?? null,
                'attendance_sms_enabled' => $branch->settings['attendance_sms_enabled'] ?? null,
                'attendance_sms_late' => $branch->settings['attendance_sms_late'] ?? null,
                'device_auto_absent' => $branch->settings['device_auto_absent'] ?? null,
                'device_absent_cutoff' => $branch->settings['device_absent_cutoff'] ?? null,
                'device_late_grace' => $branch->settings['device_late_grace'] ?? null,
                'sibling_discount_percent' => isset($branch->settings['sibling_discount_percent'])
                    ? (float) $branch->settings['sibling_discount_percent']
                    : null,
                'sibling_min_children' => isset($branch->settings['sibling_min_children'])
                    ? (int) $branch->settings['sibling_min_children']
                    : null,
                'staff_child_discount_percent' => isset($branch->settings['staff_child_discount_percent'])
                    ? (float) $branch->settings['staff_child_discount_percent']
                    : null,
                'fee_proration' => $branch->settings['fee_proration'] ?? null,
                'fee_reminders_enabled' => $branch->settings['fee_reminders_enabled'] ?? null,
                'fee_reminder_days_before' => isset($branch->settings['fee_reminder_days_before'])
                    ? (int) $branch->settings['fee_reminder_days_before']
                    : null,
                'fee_reminder_overdue_every' => isset($branch->settings['fee_reminder_overdue_every'])
                    ? (int) $branch->settings['fee_reminder_overdue_every']
                    : null,
                'fee_reminder_overdue_max' => isset($branch->settings['fee_reminder_overdue_max'])
                    ? (int) $branch->settings['fee_reminder_overdue_max']
                    : null,
                'chat_teacher_parent_approval' => School::normalizeChatApprovalMode($branch->settings['chat_teacher_parent_approval'] ?? null),
                'chat_students_enabled' => $branch->settings['chat_students_enabled'] ?? null,
                'chat_template_mode' => $branch->settings['chat_template_mode'] ?? null,
                'report_card_skills' => $branch->settings['report_card_skills'] ?? null,
                'report_card_per_page' => isset($branch->settings['report_card_per_page'])
                    ? (int) $branch->settings['report_card_per_page']
                    : null,
                'report_card_subject_ranks' => $branch->settings['report_card_subject_ranks'] ?? null,
                'report_card_grading_criteria' => $branch->settings['report_card_grading_criteria'] ?? null,
            ],
            'school_defaults' => [
                'registration_gate' => $branch->school?->registrationGate() ?? 'soft',
                'calendar_mode' => $branch->school?->calendarMode() ?? 'ethiopian',
                'clock_mode' => $branch->school?->clockMode() ?? 'ethiopian',
                'promotion_threshold' => $branch->school?->promotionThreshold() ?? 50.0,
                'teacher_assessments_enabled' => $branch->school?->teacherAssessmentsEnabled() ?? false,
                'employee_account_job_titles' => $branch->school?->employeeAccountJobTitles() ?? JobTitles::defaultAccountTitles(),
                'attendance_sms_enabled' => $branch->school?->attendanceSmsEnabled() ?? true,
                'attendance_sms_late' => $branch->school?->attendanceSmsLate() ?? false,
                'device_auto_absent' => $branch->school?->deviceAutoAbsent() ?? false,
                'device_absent_cutoff' => $branch->school?->deviceAbsentCutoff() ?? '09:30',
                'device_late_grace' => $branch->school?->deviceLateGrace() ?? 15,
                'sibling_discount_percent' => $branch->school?->siblingDiscountPercent() ?? 0,
                'sibling_min_children' => $branch->school?->siblingMinChildren() ?? 2,
                'staff_child_discount_percent' => $branch->school?->staffChildDiscountPercent() ?? 0,
                'fee_proration' => $branch->school?->feeProration() ?? 'full',
                'fee_reminders_enabled' => $branch->school?->feeRemindersEnabled() ?? true,
                'fee_reminder_days_before' => $branch->school?->feeReminderDaysBefore() ?? 3,
                'fee_reminder_overdue_every' => $branch->school?->feeReminderOverdueEvery() ?? 7,
                'fee_reminder_overdue_max' => $branch->school?->feeReminderOverdueMax() ?? 3,
                'chat_teacher_parent_approval' => $branch->school?->chatApprovalMode() ?? 'all',
                'chat_students_enabled' => $branch->school?->chatStudentsEnabled() ?? false,
                'chat_template_mode' => $branch->school?->chatTemplateMode() ?? 'suggested',
                'report_card_skills' => $branch->school?->reportCardSkills() ?? [],
                'report_card_per_page' => $branch->school?->reportCardPerPage() ?? 1,
                'report_card_subject_ranks' => $branch->school?->reportCardSubjectRanks() ?? false,
                'report_card_grading_criteria' => $branch->school?->reportCardGradingCriteria() ?? false,
            ],
        ];
    }
}
