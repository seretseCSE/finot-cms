<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class PasswordHistoryRule implements ValidationRule
{
    protected $user;
    protected $maxHistoryCount;

    public function __construct($user, int $maxHistoryCount = 3)
    {
        $this->user = $user;
        $this->maxHistoryCount = $maxHistoryCount;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || !$this->user) {
            return;
        }

        $passwordHistory = $this->getPasswordHistory();

        if (empty($passwordHistory)) {
            return;
        }

        foreach ($passwordHistory as $oldPasswordHash) {
            if (Hash::check($value, $oldPasswordHash)) {
                $fail('You cannot reuse your last ' . $this->maxHistoryCount . ' passwords.');
                return;
            }
        }
    }

    /**
     * Get password history from user
     */
    protected function getPasswordHistory(): array
    {
        $history = $this->user->password_history;
        
        if (empty($history)) {
            return [];
        }

        if (is_string($history)) {
            $history = json_decode($history, true) ?? [];
        }

        return array_slice($history, 0, $this->maxHistoryCount);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'You cannot reuse your last ' . $this->maxHistoryCount . ' passwords.';
    }
}
