<?php

namespace App\Services;

use App\Enums\Roles;

class FinancialTransactionService
{
    /**
     * Process transaction data before saving
     */
    public function processBeforeSave(array $data): array
    {
        $user = auth()->user();

        // Auto-approve transactions for users with appropriate permissions
        if ($user && ($user->hasRole(Roles::FINANCE_MANAGERS))) {
            $data['approved_by'] = $user->id;
            $data['approved_at'] = now();
        }

        // Set recorded by
        $data['recorded_by'] = $user?->id;

        return $data;
    }
}
