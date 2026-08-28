<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalRequest;

class WithdrawalPrintController extends Controller
{
    public function __invoke(WithdrawalRequest $withdrawal)
    {
        $user = auth()->user();
        abort_unless(
            $user
            && (
                $user->hasRole('superadmin')
                || $user->can('withdrawal.approve')
                || $user->can('withdrawal.finalize')
                || (int) $user->member_id === (int) $withdrawal->member_id
            ),
            403
        );

        $withdrawal->load(['member', 'enrollment', 'class', 'requestedBy', 'educationDecidedBy', 'finalizedBy']);

        return view('print.withdrawal', compact('withdrawal'));
    }
}
