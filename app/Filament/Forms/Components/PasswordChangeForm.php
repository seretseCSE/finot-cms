<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

class PasswordChangeForm extends Component
{
    protected string $view = 'filament.forms.components.password-change-form';

    public function getStatePath(): string
    {
        return 'data';
    }

    public function getModel(): Model
    {
        return $this->getContainer()->make('auth')->user();
    }

    public function getCurrentPassword(): string
    {
        return '';
    }

    public function getNewPassword(): string
    {
        return '';
    }

    public function getNewPasswordConfirmation(): string
    {
        return '';
    }

    public function validatePassword(string $password): array
    {
        $errors = [];

        // Minimum 8 characters
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        // At least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter (A-Z).';
        }

        // At least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter (a-z).';
        }

        // At least one number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number (0-9).';
        }

        return $errors;
    }

    public function checkPasswordHistory(string $password): bool
    {
        $user = $this->getModel();
        return !$user->isPasswordInHistory($password, 3);
    }

    public function getPasswordStrength(string $password): string
    {
        $strength = 0;
        
        if (strlen($password) >= 8) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $strength++;

        if ($strength <= 2) return 'weak';
        if ($strength <= 3) return 'medium';
        if ($strength <= 4) return 'strong';
        return 'very-strong';
    }
}

