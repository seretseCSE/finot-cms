<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasContextPermission('fees.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionForScope('fees.view', $invoice->school_id, $invoice->branch_id)
            && $user->operatesInBranch($invoice->branch);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionForScope('fees.manage', $invoice->school_id, $invoice->branch_id)
            && $user->operatesInBranch($invoice->branch);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionForScope('payments.record', $invoice->school_id, $invoice->branch_id)
            && $user->operatesInBranch($invoice->branch);
    }
}
