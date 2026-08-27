<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountLinkRequest;
use App\Models\Student;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registrar review queue for self-signup student-ID claims (see
 * SignupController). Approving links the claimant's user account to the
 * student record; rejecting leaves the claimant as a plain public account.
 */
class AccountLinkRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasContextPermission('students.update'), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $requests = AccountLinkRequest::query()
            ->where('status', AccountLinkRequest::STATUS_PENDING)
            ->whereHas('student', function ($s) use ($branch, $schoolScopeId): void {
                if ($branch !== null) {
                    $s->where(fn ($q) => $q->where('branch_id', $branch->id)
                        ->orWhereHas('enrollments', fn ($e) => $e->where('branch_id', $branch->id)));
                } elseif ($schoolScopeId !== null) {
                    $s->where(fn ($q) => $q->where('school_id', $schoolScopeId)
                        ->orWhereHas('enrollments', fn ($e) => $e->where('school_id', $schoolScopeId)));
                }
            })
            ->with(['user:id,name,phone,created_at', 'student:id,first_name,father_name,grandfather_name,public_id,primary_phone,user_id'])
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $requests->map(fn (AccountLinkRequest $link): array => [
                'id' => $link->id,
                'created_at' => $link->created_at,
                'claimant' => [
                    'name' => $link->user?->name,
                    'phone' => $link->user?->phone,
                ],
                'student' => [
                    'id' => $link->student?->id,
                    'full_name' => $link->student?->full_name,
                    'public_id' => $link->student?->public_id,
                    'primary_phone' => $link->student?->primary_phone,
                ],
            ])->values(),
        ]);
    }

    public function approve(Request $request, AccountLinkRequest $accountLinkRequest): JsonResponse
    {
        $student = $accountLinkRequest->student;
        abort_if($student === null, 404);
        $this->authorize('update', $student);

        if ($accountLinkRequest->status !== AccountLinkRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['This claim has already been reviewed.']]);
        }

        if ($student->user_id !== null) {
            throw ValidationException::withMessages(['status' => ['This student already has a login account — reject the claim instead.']]);
        }

        if (Student::where('user_id', $accountLinkRequest->user_id)->exists()) {
            throw ValidationException::withMessages(['status' => ['The claimant\'s account is already linked to another student.']]);
        }

        DB::transaction(function () use ($request, $accountLinkRequest, $student): void {
            $student->forceFill(['user_id' => $accountLinkRequest->user_id])->save();
            $accountLinkRequest->forceFill([
                'status' => AccountLinkRequest::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ])->save();
            ActivityLogger::log($request->user(), 'student.account_claim_approved', $student);
        });

        app(Notifier::class)->toUser($accountLinkRequest->user, 'family.account_link_decided', [
            'student' => $student->full_name,
            'public_id' => $student->public_id,
            'status' => 'approved',
        ], [
            'link' => '/me/student',
            'smsKey' => 'auth.account_link_approved_sms',
        ]);

        return response()->json(['message' => 'Claim approved — the account is now linked to the student.']);
    }

    public function reject(Request $request, AccountLinkRequest $accountLinkRequest): JsonResponse
    {
        $student = $accountLinkRequest->student;
        abort_if($student === null, 404);
        $this->authorize('update', $student);

        if ($accountLinkRequest->status !== AccountLinkRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['This claim has already been reviewed.']]);
        }

        $accountLinkRequest->forceFill([
            'status' => AccountLinkRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();
        ActivityLogger::log($request->user(), 'student.account_claim_rejected', $student);

        app(Notifier::class)->toUser($accountLinkRequest->user, 'family.account_link_decided', [
            'student' => $student->full_name,
            'public_id' => $student->public_id,
            'status' => 'rejected',
        ]);

        return response()->json(['message' => 'Claim rejected.']);
    }
}
