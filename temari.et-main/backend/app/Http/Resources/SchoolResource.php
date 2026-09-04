<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin School
 */
class SchoolResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->logoUrl(),
            'phone' => $this->phone,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'registration_gate' => $this->registrationGate(),
            'calendar_mode' => $this->calendarMode(),
            'clock_mode' => $this->clockMode(),
            'promotion_threshold' => $this->promotionThreshold(),
            'teacher_assessments_enabled' => $this->teacherAssessmentsEnabled(),
            'lesson_plan_department_review' => $this->lessonPlanDepartmentReviewEnabled(),
            'employee_account_job_titles' => $this->employeeAccountJobTitles(),
            'attendance_sms_enabled' => $this->attendanceSmsEnabled(),
            'attendance_sms_late' => $this->attendanceSmsLate(),
            'device_auto_absent' => $this->deviceAutoAbsent(),
            'device_absent_cutoff' => $this->deviceAbsentCutoff(),
            'device_late_grace' => $this->deviceLateGrace(),
            'sibling_discount_percent' => $this->siblingDiscountPercent(),
            'sibling_min_children' => $this->siblingMinChildren(),
            'staff_child_discount_percent' => $this->staffChildDiscountPercent(),
            'fee_proration' => $this->feeProration(),
            'fee_reminders_enabled' => $this->feeRemindersEnabled(),
            'fee_reminder_days_before' => $this->feeReminderDaysBefore(),
            'fee_reminder_overdue_every' => $this->feeReminderOverdueEvery(),
            'fee_reminder_overdue_max' => $this->feeReminderOverdueMax(),
            'finance_self_approval' => $this->financeSelfApprovalAllowed(),
            'director_finance_access' => $this->directorFinanceAccessEnabled(),
            'chat_teacher_parent_approval' => $this->chatApprovalMode(),
            'chat_students_enabled' => $this->chatStudentsEnabled(),
            'chat_template_mode' => $this->chatTemplateMode(),
            'report_card_skills' => $this->reportCardSkills(),
            'report_card_per_page' => $this->reportCardPerPage(),
            'report_card_subject_ranks' => $this->reportCardSubjectRanks(),
            'report_card_grading_criteria' => $this->reportCardGradingCriteria(),
            'branches_count' => $this->whenCounted('branches'),
            // List-table vitals — present only when queried withListStats().
            'students_count' => $this->whenHas('students_count'),
            'teachers_count' => $this->whenHas('teachers_count'),
            'grade_min' => $this->whenHas('grade_min'),
            'grade_max' => $this->whenHas('grade_max'),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'principal' => $this->when(
                $this->relationLoaded('contactMemberships'),
                fn () => ContactResource::fromMemberships($this->contactMemberships, Role::Principal),
            ),
            'school_admin' => $this->when(
                $this->relationLoaded('contactMemberships'),
                fn () => ContactResource::fromMemberships($this->contactMemberships, Role::SchoolAdmin),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
