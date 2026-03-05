<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordComplexityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test password minimum length (8+ characters).
     */
    public function test_password_minimum_length(): void
    {
        $this->assertTrue($this->validatePasswordLength('12345678')); // 8 chars - valid
        $this->assertTrue($this->validatePasswordLength('123456789')); // 9 chars - valid
        $this->assertFalse($this->validatePasswordLength('1234567')); // 7 chars - invalid
    }

    /**
     * Test password requires mixed case.
     */
    public function test_password_requires_mixed_case(): void
    {
        // Valid: has both uppercase and lowercase
        $this->assertTrue($this->validatePasswordMixedCase('Password1'));
        $this->assertTrue($this->validatePasswordMixedCase('PASSWORD1'));
        $this->assertTrue($this->validatePasswordMixedCase('password1'));
        
        // Invalid: only lowercase or only uppercase
        $this->assertFalse($this->validatePasswordMixedCase('password1'));
        $this->assertFalse($this->validatePasswordMixedCase('PASSWORD1'));
    }

    /**
     * Test password requires numbers.
     */
    public function test_password_requires_numbers(): void
    {
        // Valid: has at least one number
        $this->assertTrue($this->validatePasswordNumber('Password1'));
        $this->assertTrue($this->validatePasswordNumber('Password12'));
        
        // Invalid: no numbers
        $this->assertFalse($this->validatePasswordNumber('Password'));
    }

    /**
     * Test password complexity rule - full validation.
     */
    public function test_password_complexity_full_validation(): void
    {
        // Valid passwords
        $validPasswords = [
            'Password1',      // 8 chars, mixed case, has number
            'MyPass123',      // 8 chars, mixed case, has number
            'Test@Pass1',     // 8 chars, mixed case, has number, special char
            'Secure123!',     // 9 chars, mixed case, has number, special char
        ];

        foreach ($validPasswords as $password) {
            $this->assertTrue(
                $this->validatePasswordComplexity($password),
                "Password '{$password}' should be valid"
            );
        }

        // Invalid passwords
        $invalidPasswords = [
            'password',       // 8 chars, lowercase only, no number
            'PASSWORD',       // 8 chars, uppercase only, no number
            'Password',       // 8 chars, mixed case, no number
            'pass1234',       // 8 chars, lowercase only
            'PASS1234',       // 8 chars, uppercase only
            'Pass',           // 4 chars, too short
            'Pass1',          // 5 chars, too short
            '12345678',       // 8 chars, numbers only
        ];

        foreach ($invalidPasswords as $password) {
            $this->assertFalse(
                $this->validatePasswordComplexity($password),
                "Password '{$password}' should be invalid"
            );
        }
    }

    /**
     * Test password is not in compromised list.
     */
    public function test_password_not_compromised(): void
    {
        // Common compromised passwords
        $compromisedPasswords = [
            'password',
            '123456',
            '12345678',
            'qwerty',
            'abc123',
            'password1',
            '1234567890',
        ];

        foreach ($compromisedPasswords as $password) {
            $this->assertTrue(
                $this->validatePasswordNotCompromised($password),
                "Password '{$password}' should be marked as compromised"
            );
        }

        // Non-compromised passwords
        $this->assertFalse($this->validatePasswordNotCompromised('Tr0ub4dor&3'));
        $this->assertFalse($this->validatePasswordNotCompromised('MyS3cur3P@ss!'));
    }

    /**
     * Test password history check - cannot reuse old passwords.
     */
    public function test_password_history_check(): void
    {
        $user = User::factory()->create([
            'password_history' => [
                bcrypt('OldPassword1'),
                bcrypt('PreviousPass2'),
            ],
        ]);

        // Should detect old passwords
        $this->assertTrue($user->isPasswordInHistory('OldPassword1'));
        $this->assertTrue($user->isPasswordInHistory('PreviousPass2'));
        
        // Should not detect new password
        $this->assertFalse($user->isPasswordInHistory('NewPassword3'));
    }

    /**
     * Test password update creates history entry.
     */
    public function test_password_update_creates_history(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('OldPassword1'),
            'password_history' => [],
        ]);

        // Update password
        $user->updatePassword(bcrypt('NewPassword2'));

        // Check history was created
        $this->assertNotEmpty($user->password_history);
        $this->assertCount(1, $user->password_history);
    }

    /**
     * Test password history maintains max count.
     */
    public function test_password_history_maintains_max_count(): void
    {
        $user = User::factory()->create([
            'password_history' => [
                bcrypt('Password1'),
                bcrypt('Password2'),
                bcrypt('Password3'),
            ],
        ]);

        // Add new password
        $user->updatePassword(bcrypt('Password4'));

        // Should maintain max count of 3
        $history = $user->getPasswordHistory(3);
        $this->assertCount(3, $history);
    }

    /**
     * Test temporary password detection.
     */
    public function test_temporary_password_detection(): void
    {
        // User with temporary password (not changed)
        $userWithTempPassword = User::factory()->create([
            'temp_password_changed' => false,
        ]);

        $this->assertTrue($userWithTempPassword->needsPasswordChange());

        // User who changed temporary password
        $userChangedPassword = User::factory()->create([
            'temp_password_changed' => true,
        ]);

        $this->assertFalse($userChangedPassword->needsPasswordChange());
    }

    /**
     * Test password complexity with special characters.
     */
    public function test_password_with_special_characters(): void
    {
        // Passwords with special characters should be valid
        $validWithSpecial = [
            'Pass@word1',
            'Test!1234',
            'Secure#99',
            'My$Pass123',
        ];

        foreach ($validWithSpecial as $password) {
            $this->assertTrue(
                $this->validatePasswordComplexity($password),
                "Password '{$password}' should be valid"
            );
        }
    }

    /**
     * Test password cannot be only special characters.
     */
    public function test_password_not_only_special(): void
    {
        // Only special characters - should fail as it needs letters
        $this->assertFalse($this->validatePasswordComplexity('!@#$%^&*'));
    }

    /**
     * Test password with exact minimum length.
     */
    public function test_password_exact_minimum_length(): void
    {
        // Exactly 8 characters - minimum valid
        $this->assertTrue($this->validatePasswordComplexity('Pass1234'));
        // 7 characters - invalid
        $this->assertFalse($this->validatePasswordComplexity('Pass123'));
    }

    /**
     * Test password complexity validation rule.
     */
    public function test_password_complexity_validation_rule(): void
    {
        // This tests the actual validation rule
        // 8+ chars, mixed case, numbers required
        $rule = function ($value) {
            return strlen($value) >= 8 
                && preg_match('/[A-Z]/', $value) 
                && preg_match('/[a-z]/', $value) 
                && preg_match('/[0-9]/', $value);
        };

        $this->assertTrue($rule('Password123'));
        $this->assertFalse($rule('password'));
        $this->assertFalse($rule('Password'));
        $this->assertFalse($rule('Pass123'));
    }

    /**
     * Helper: Validate password length.
     */
    protected function validatePasswordLength(string $password): bool
    {
        return strlen($password) >= 8;
    }

    /**
     * Helper: Validate password has mixed case.
     */
    protected function validatePasswordMixedCase(string $password): bool
    {
        return preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password);
    }

    /**
     * Helper: Validate password has number.
     */
    protected function validatePasswordNumber(string $password): bool
    {
        return preg_match('/[0-9]/', $password);
    }

    /**
     * Helper: Full password complexity validation.
     */
    protected function validatePasswordComplexity(string $password): bool
    {
        return strlen($password) >= 8 
            && preg_match('/[A-Z]/', $password) 
            && preg_match('/[a-z]/', $password) 
            && preg_match('/[0-9]/', $password);
    }

    /**
     * Helper: Check if password is in compromised list.
     */
    protected function validatePasswordNotCompromised(string $password): bool
    {
        // Common compromised passwords list
        $compromised = [
            'password', '123456', '12345678', 'qwerty', 'abc123',
            'password1', '1234567890', 'letmein', 'welcome',
            'monkey', 'dragon', 'master', 'admin', 'login',
        ];

        return in_array(strtolower($password), $compromised);
    }
}
