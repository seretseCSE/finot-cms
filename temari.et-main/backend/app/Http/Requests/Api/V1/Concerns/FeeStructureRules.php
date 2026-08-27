<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Enums\FeeType;
use App\Models\BankAccount;
use App\Models\FeeStructure;
use Illuminate\Validation\Rule;

/**
 * Shared fee validation. Registration fees are the minimal shape (name, year,
 * amount, grades); every other type may carry a billing window, notifications
 * and a late penalty (fixed, or incremental every N days).
 */
trait FeeStructureRules
{
    use ResolvesTargetBranchId;

    /**
     * The branch the fee's collection accounts are judged against: the fee
     * being updated anchors it; on create it is the target branch (explicit
     * `branch_id`, else the active context).
     */
    protected function feeBranchId(): ?int
    {
        $fee = $this->route('fee_structure');

        return $fee instanceof FeeStructure ? (int) $fee->branch_id : $this->targetBranchId();
    }

    /**
     * @return array<string, mixed>
     */
    protected function feeRules(): array
    {
        $isRegistration = $this->input('type') === FeeType::Registration->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(FeeType::class)],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],

            // Collection accounts — each must be a school account enabled for
            // the active branch (both switches on). Empty = none preferred.
            'bank_account_ids' => ['nullable', 'array'],
            'bank_account_ids.*' => [
                'integer',
                'distinct',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $branchId = $this->feeBranchId();

                    $usable = BankAccount::query()
                        ->whereKey($value)
                        ->when($branchId !== null, fn ($q) => $q->usableByBranch((int) $branchId))
                        ->exists();

                    if (! $usable) {
                        $fail('Pick a bank account that is active for this branch.');
                    }
                },
            ],

            // Empty = the fee applies to every grade.
            'grade_level_ids' => ['nullable', 'array'],
            'grade_level_ids.*' => ['integer', 'exists:grade_levels,id'],

            // Schedule + notifications + penalty — not applicable to registration.
            'starts_on' => [Rule::prohibitedIf($isRegistration), 'nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'due_on' => [Rule::prohibitedIf($isRegistration), 'nullable', 'date', 'after_or_equal:starts_on'],
            // Recurring engine (monthly/quarterly/semester/yearly): Ethiopian
            // due day-of-month + the auto-generation switch.
            'billing_day' => [Rule::prohibitedIf($isRegistration), 'nullable', 'integer', 'min:1', 'max:30'],
            'auto_generate' => [Rule::prohibitedIf($isRegistration), 'sometimes', 'boolean'],
            'notify_parents' => [Rule::prohibitedIf($isRegistration), 'sometimes', 'boolean'],
            'notify_students' => [Rule::prohibitedIf($isRegistration), 'sometimes', 'boolean'],
            'penalty_type' => [Rule::prohibitedIf($isRegistration), 'nullable', Rule::in(['fixed', 'incremental'])],
            'penalty_amount' => ['nullable', 'required_with:penalty_type', 'numeric', 'min:0', 'max:9999999999'],
            'penalty_increment_days' => ['nullable', 'required_if:penalty_type,incremental', 'integer', 'min:1', 'max:365'],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
