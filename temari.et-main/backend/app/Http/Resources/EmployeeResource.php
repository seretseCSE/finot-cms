<?php

namespace App\Http\Resources;

use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeAttachment;
use App\Models\EmployeeDeduction;
use App\Models\EmployeePosition;
use App\Models\EmployeeQualification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Pay data (salary, allowances, deductions) is payroll territory —
        // employees.view alone (e.g. a registrar browsing staff) never sees it.
        $showsPay = (bool) $request->user()?->hasPermissionForScope(
            'payroll.view',
            $this->school_id,
            $this->branch_id,
        );

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'first_name' => $this->first_name,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'full_name' => trim(implode(' ', array_filter([
                $this->first_name, $this->father_name, $this->grandfather_name,
            ]))),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'photo_url' => $this->photo_url,
            'birth_date' => $this->birth_date?->toDateString(),
            'email' => $this->email,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,
            'state' => $this->state,
            'city' => $this->city,
            'sub_city' => $this->sub_city,
            'woreda' => $this->woreda,
            'house_no' => $this->house_no,
            'professional_level' => $this->professional_level,
            'retirement_on' => $this->retirement_on?->toDateString(),
            // Time columns are stored as H:i:s — the form only cares about H:i.
            'check_in' => $this->check_in ? substr((string) $this->check_in, 0, 5) : null,
            'check_out' => $this->check_out ? substr((string) $this->check_out, 0, 5) : null,
            'is_active' => $this->is_active,
            'positions' => $this->whenLoaded('positions', fn () => $this->positions
                ->map(fn (EmployeePosition $p) => [
                    'id' => $p->id,
                    'job_title' => $p->job_title,
                    'employment_type' => $p->employment_type?->value,
                    'employment_type_label' => $p->employment_type?->label(),
                    'salary_level' => $showsPay ? $p->salary_level : null,
                    'salary' => $showsPay ? $p->salary : null,
                    'hired_on' => $p->hired_on?->toDateString(),
                    'last_promoted_on' => $p->last_promoted_on?->toDateString(),
                    'ended_on' => $p->ended_on?->toDateString(),
                    'is_primary' => $p->is_primary,
                ])
                ->values()),
            // Convenience summaries for the table: current job titles + the
            // primary position (salary anchor).
            'active_job_titles' => $this->whenLoaded('positions', fn () => $this->positions
                ->whereNull('ended_on')
                ->pluck('job_title')
                ->values()),
            'primary_position' => $this->whenLoaded('positions', function () use ($showsPay) {
                $primary = $this->positions->whereNull('ended_on')->firstWhere('is_primary', true)
                    ?? $this->positions->whereNull('ended_on')->first();

                return $primary === null ? null : [
                    'id' => $primary->id,
                    'job_title' => $primary->job_title,
                    'employment_type' => $primary->employment_type?->value,
                    'salary' => $showsPay ? $primary->salary : null,
                    'hired_on' => $primary->hired_on?->toDateString(),
                ];
            }),
            'qualifications' => $this->whenLoaded('qualifications', fn () => $this->qualifications
                ->map(fn (EmployeeQualification $q) => [
                    'id' => $q->id,
                    'education_level' => $q->education_level,
                    'field_of_study' => $q->field_of_study,
                    'institution' => $q->institution,
                    'graduation_year' => $q->graduation_year,
                ])
                ->values()),
            'allowances' => $this->when($showsPay, fn () => $this->whenLoaded('allowances', fn () => $this->allowances
                ->map(fn (EmployeeAllowance $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'amount' => $a->amount,
                ])
                ->values())),
            'deductions' => $this->when($showsPay, fn () => $this->whenLoaded('deductions', fn () => $this->deductions
                ->map(fn (EmployeeDeduction $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'amount' => $d->amount,
                ])
                ->values())),
            'teacher_subjects' => $this->whenLoaded('teacherSubjects', fn () => $this->teacherSubjects
                ->map(fn ($ts) => [
                    'id' => $ts->id,
                    'subject_id' => $ts->subject_id,
                    'subject_code' => $ts->subject?->code,
                    'subject_name' => $ts->subject?->name,
                    'grade_level_id' => $ts->grade_level_id,
                    'grade_level_name' => $ts->gradeLevel?->name,
                    'grade_level_sort' => $ts->gradeLevel?->sort_order,
                ])
                ->values()),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments
                ->map(fn (EmployeeAttachment $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'url' => $a->url(),
                    'mime_type' => $a->mime_type,
                    'size' => $a->size,
                    'employee_position_id' => $a->employee_position_id,
                    'employee_qualification_id' => $a->employee_qualification_id,
                    'created_at' => $a->created_at,
                ])
                ->values()),
            // The linked account with its (scope-filtered) memberships — powers the
            // Access column and branch management on the staff table.
            'user' => $this->whenLoaded('user', fn () => $this->user !== null
                ? new UserListResource($this->user)
                : null),
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch?->school?->name),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'created_at' => $this->created_at,
        ];
    }
}
