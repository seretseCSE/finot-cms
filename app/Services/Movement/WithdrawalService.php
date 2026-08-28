<?php

namespace App\Services\Movement;

use App\Enums\WithdrawalRequestStatus;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function apply(User $actor, StudentEnrollment $enrollment, string $reason, ?string $destination = null): WithdrawalRequest
    {
        if (! $actor->can('withdrawal.apply') && ! $actor->hasRole(['superadmin', 'admin'])) {
            throw ValidationException::withMessages(['actor' => 'Not allowed to apply for withdrawal.']);
        }

        if (! $enrollment->isActive()) {
            throw ValidationException::withMessages(['enrollment' => 'Enrollment is not active.']);
        }

        if ($actor->hasRole('student') && (int) $actor->member_id !== (int) $enrollment->member_id) {
            throw ValidationException::withMessages(['actor' => 'You can only withdraw your own enrollment.']);
        }

        $open = WithdrawalRequest::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', [
                WithdrawalRequestStatus::Pending->value,
                WithdrawalRequestStatus::EducationApproved->value,
            ])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages(['enrollment' => 'A withdrawal request is already in progress.']);
        }

        $request = WithdrawalRequest::query()->create([
            'member_id' => $enrollment->member_id,
            'enrollment_id' => $enrollment->id,
            'class_id' => $enrollment->class_id,
            'requested_by' => $actor->id,
            'reason' => $reason,
            'destination' => $destination,
            'requested_at' => now(),
            'status' => WithdrawalRequestStatus::Pending,
        ]);

        $this->notifier->toUsers(
            User::permission('withdrawal.approve')->get(),
            'movement.withdrawal',
            ['member' => $enrollment->student_full_name, 'status' => 'pending'],
            null,
            'withdrawal-pending-'.$request->id
        );

        activity()->causedBy($actor)->performedOn($request)->log('withdrawal.applied');

        return $request;
    }

    public function approve(WithdrawalRequest $request, User $actor): WithdrawalRequest
    {
        if (! $actor->can('withdrawal.approve') && ! $actor->hasRole('superadmin')) {
            throw ValidationException::withMessages(['actor' => 'Not allowed to approve withdrawal.']);
        }

        if ($request->status !== WithdrawalRequestStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending requests can be approved.']);
        }

        $request->update([
            'status' => WithdrawalRequestStatus::EducationApproved,
            'education_decided_by' => $actor->id,
            'education_decided_at' => now(),
        ]);

        $this->notifier->toUsers(
            User::permission('withdrawal.finalize')->get(),
            'movement.withdrawal',
            ['member' => $request->member?->full_name, 'status' => 'education_approved'],
            null,
            'withdrawal-hr-'.$request->id
        );

        activity()->causedBy($actor)->performedOn($request)->log('withdrawal.approved');

        return $request->fresh();
    }

    public function reject(WithdrawalRequest $request, User $actor, string $remarks): WithdrawalRequest
    {
        if (! $actor->can('withdrawal.approve') && ! $actor->hasRole('superadmin')) {
            throw ValidationException::withMessages(['actor' => 'Not allowed to reject withdrawal.']);
        }

        $request->update([
            'status' => WithdrawalRequestStatus::Rejected,
            'education_decided_by' => $actor->id,
            'education_decided_at' => now(),
            'destination' => $request->destination,
        ]);

        activity()->causedBy($actor)->performedOn($request)->log('withdrawal.rejected: '.$remarks);

        return $request->fresh();
    }

    public function finalize(WithdrawalRequest $request, User $actor, ?string $effectiveDate = null): WithdrawalRequest
    {
        if (! $actor->can('withdrawal.finalize') && ! $actor->hasRole('superadmin')) {
            throw ValidationException::withMessages(['actor' => 'Not allowed to finalize withdrawal.']);
        }

        if ($request->status !== WithdrawalRequestStatus::EducationApproved) {
            throw ValidationException::withMessages(['status' => 'Education Head must approve first.']);
        }

        DB::transaction(function () use ($request, $actor, $effectiveDate): void {
            $enrollment = StudentEnrollment::query()->findOrFail($request->enrollment_id);
            $enrollment->update([
                'status' => 'Withdrawn',
                'removed_at' => now(),
                'withdrawal_reason' => 'Other',
                'withdrawal_notes' => $request->reason,
                'completion_date' => $effectiveDate ?: now()->toDateString(),
            ]);

            $request->update([
                'status' => WithdrawalRequestStatus::Finalized,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'effective_date' => $effectiveDate ?: now()->toDateString(),
            ]);
        });

        activity()->causedBy($actor)->performedOn($request)->log('withdrawal.finalized');

        return $request->fresh();
    }
}
