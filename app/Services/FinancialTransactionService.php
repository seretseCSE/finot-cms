<?php

namespace App\Services;

class FinancialTransactionService
{
    public function processBeforeSave(array $data): array
    {
        $user = auth()->user();

        $data['approved_by'] = $user?->id;
        $data['approved_at'] = now();
        $data['recorded_by'] = $user?->id;

        return $data;
    }
}
