<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Support\Ethiopia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // The invoice being paid anchors the branch — never the request
        // context (school-wide staff pay any branch).
        $branchId = $this->route('invoice')?->branch_id;

        $method = PaymentMethod::tryFrom((string) $this->input('method'));
        $needsAccount = $method?->needsAccount() ?? false;

        // Where the money landed is essential for reconciliation — required
        // for bank/wallet channels whenever the branch has an account to pick.
        // Cash and "other" never take an account.
        $accountRequired = $needsAccount
            && $branchId !== null
            && BankAccount::query()->usableByBranch((int) $branchId)->exists();

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_account_id' => [
                $accountRequired ? 'required' : 'nullable',
                $needsAccount ? 'integer' : Rule::in([null]),
                function (string $attribute, mixed $value, \Closure $fail) use ($branchId): void {
                    if ($value === null) {
                        return;
                    }

                    $usable = BankAccount::query()
                        ->whereKey($value)
                        ->when($branchId !== null, fn ($q) => $q->usableByBranch((int) $branchId))
                        ->exists();

                    if (! $usable) {
                        $fail('Pick a bank account that is active for this branch.');
                    }
                },
            ],
            'reference' => ['nullable', 'string', 'max:255'],
            // Money already received — never a future date (Addis wall clock).
            'paid_at' => ['nullable', 'date', 'before_or_equal:'.Ethiopia::today()],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Select the account or wallet the money was received into.',
            'bank_account_id.in' => 'Cash and other payments do not take a collection account.',
            'paid_at.before_or_equal' => __('dates.day_future'),
        ];
    }
}
