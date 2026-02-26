# Password Security System

## Overview
Comprehensive password security system with strength validation, password history tracking, and both client-side and server-side validation.

## Features

### 🔐 Password Strength Requirements
- **Minimum Length**: 8 characters
- **Uppercase Letter**: At least one (A-Z)
- **Lowercase Letter**: At least one (a-z)
- **Number**: At least one (0-9)
- **Special Character**: Optional but recommended

### 📜 Password History
- **History Count**: Last 3 passwords stored
- **Reuse Prevention**: Cannot reuse last 3 passwords
- **Storage Format**: JSON array of bcrypt hashes
- **Automatic Cleanup**: Keeps only last N passwords

### 🌐 Dual Validation
- **Client-Side**: Real-time Alpine.js validation
- **Server-Side**: Laravel validation rules
- **Visual Feedback**: Strength indicator and requirement checklist
- **Error Handling**: Comprehensive error messages

## Implementation Files

### 1. Validation Rules

#### PasswordStrengthRule
**File**: `app/Rules/PasswordStrengthRule.php`
```php
// Validates password strength requirements
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter  
- At least one number
```

#### PasswordHistoryRule
**File**: `app/Rules/PasswordHistoryRule.php`
```php
// Prevents reuse of last N passwords
- Checks against stored password hashes
- Configurable history count (default: 3)
- Uses bcrypt_check for comparison
```

### 2. Database Schema

#### Migration
**File**: `database/migrations/2026_02_17_230000_add_password_history_to_users_table.php`
```sql
ALTER TABLE users 
ADD COLUMN password_history JSON NULL;
```

#### User Model Updates
**File**: `app/Models/User.php`
```php
// Fillable fields
'password_history' => 'array',

// Casts
'password_history' => 'array',

// Methods
updatePassword($newPassword, $maxHistoryCount = 3)
isPasswordInHistory($password, $maxHistoryCount = 3)
getPasswordHistory($maxCount = 3)
```

### 3. Frontend Components

#### PasswordChangeForm Component
**File**: `app/Filament/Forms/Components/PasswordChangeForm.php`
- Form component with validation logic
- Strength calculation methods
- Password history checking
- Alpine.js integration

#### Blade Template
**File**: `resources/views/filament/forms/components/password-change-form.blade.php`
- Real-time validation with Alpine.js
- Visual strength indicator
- Requirement checklist
- Error message display

### 4. Backend Controller

#### PasswordChangeController
**File**: `app/Http/Controllers/PasswordChangeController.php`
```php
// Handles password change requests
- Validates current password
- Applies strength and history rules
- Updates password with history tracking
- Returns JSON responses
```

### 5. Routes
**File**: `routes/web.php`
```php
Route::middleware(['auth'])->group(function () {
    Route::post('/user/change-password', 'changePassword');
    Route::get('/user/password-requirements', 'getPasswordRequirements');
});
```

## Usage Examples

### Basic Password Change
```php
// In Filament form
<x-password-change-form />

// Manual validation
use App\Rules\PasswordStrengthRule;
use App\Rules\PasswordHistoryRule;

$request->validate([
    'password' => ['required', new PasswordStrengthRule()],
]);
```

### User Model Methods
```php
$user = Auth::user();

// Update password with history tracking
$user->updatePassword('NewSecurePassword123!', 3);

// Check if password is in history
if ($user->isPasswordInHistory('OldPassword123', 3)) {
    // Password was used before
}

// Get password history
$history = $user->getPasswordHistory(3);
```

### Validation Rules
```php
// Password strength validation
$rule = new PasswordStrengthRule();
$rule->validate('password', 'weakpass', fn($msg) => $msg('password'));

// Password history validation
$rule = new PasswordHistoryRule($user, 3);
$rule->validate('password', 'oldpass', fn($msg) => $msg('password'));
```

## Client-Side Validation

### Alpine.js Implementation
```javascript
// Real-time validation
validatePasswordStrength() {
    const password = this.newPassword;
    this.errors = {};
    
    // Check requirements
    this.hasMinLength = password.length >= 8;
    this.hasUppercase = /[A-Z]/.test(password);
    this.hasLowercase = /[a-z]/.test(password);
    this.hasNumber = /[0-9]/.test(password);
    
    // Calculate strength
    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    
    this.strength = ['weak', 'medium', 'strong', 'very-strong'][strength - 1] || 'weak';
}
```

### Visual Feedback
```html
<!-- Strength indicator -->
<div class="flex items-center space-x-2">
    <div class="flex-1 bg-gray-200 rounded-full h-2">
        <div class="h-2 rounded-full transition-all duration-300"
             :class="getStrengthClass()"
             :style="`width: ${getStrengthPercentage()}%`">
        </div>
    </div>
    <span class="text-sm font-medium" :class="getStrengthTextColor()">
        {{ strength }}
    </span>
</div>

<!-- Requirement checklist -->
<ul class="space-y-1 text-xs text-gray-600">
    <li :class="hasMinLength ? 'text-green-600' : 'text-gray-400'">
        ✓ At least 8 characters
    </li>
    <li :class="hasUppercase ? 'text-green-600' : 'text-gray-400'">
        ✓ At least one uppercase letter (A-Z)
    </li>
    <li :class="hasLowercase ? 'text-green-600' : 'text-gray-400'">
        ✓ At least one lowercase letter (a-z)
    </li>
    <li :class="hasNumber ? 'text-green-600' : 'text-gray-400'">
        ✓ At least one number (0-9)
    </li>
</ul>
```

## Security Features

### Password History Tracking
```php
// Password update with history
public function updatePassword(string $newPassword, int $maxHistoryCount = 3): void
{
    $currentPasswordHash = $this->password;
    
    // Add current password to history
    $history = $this->password_history ?? [];
    array_unshift($history, $currentPasswordHash);
    
    // Keep only last N passwords
    $history = array_slice($history, 0, $maxHistoryCount);
    
    // Update password and history
    $this->update([
        'password' => $newPassword,
        'password_history' => $history,
        'temp_password_changed' => true,
    ]);
}
```

### History Validation
```php
// Check if password was used before
public function isPasswordInHistory(string $password, int $maxHistoryCount = 3): bool
{
    $history = $this->password_history ?? [];
    $recentHistory = array_slice($history, 0, $maxHistoryCount);
    
    foreach ($recentHistory as $oldPasswordHash) {
        if (Hash::check($password, $oldPasswordHash)) {
            return true;
        }
    }
    
    return false;
}
```

### Strength Calculation
```php
public function getPasswordStrength(string $password): string
{
    $strength = 0;
    
    if (strlen($password) >= 8) $strength++;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $strength++;
    
    $levels = ['weak', 'medium', 'strong', 'very-strong'];
    return $levels[min($strength - 1, 3)] ?? 'weak';
}
```

## Database Schema

### Users Table Structure
```sql
CREATE TABLE users (
    -- ... other fields
    password VARCHAR(255) NOT NULL,
    password_history JSON NULL,
    -- ... other fields
);
```

### Password History Format
```json
[
    "$2y$10$abcdefghijklmnopqrstuvwx.yz1234567890",
    "$2y$10$zyxwvutsrqponmlkjihgfedcba9876543210",
    "$2y$10$0987654321zyxwvutsrqponmlkjihgfedcba"
]
```

## API Endpoints

### Change Password
```http
POST /user/change-password
Content-Type: application/json
Authorization: Bearer {token}

{
    "current_password": "OldPassword123!",
    "new_password": "NewSecurePassword456!",
    "new_password_confirmation": "NewSecurePassword456!"
}
```

### Response
```json
{
    "success": "Password changed successfully."
}
```

### Error Response
```json
{
    "errors": {
        "new_password": [
            "Password must contain at least one uppercase letter (A-Z).",
            "You cannot reuse your last 3 passwords."
        ]
    }
}
```

## Testing

### Unit Tests
```php
public function testPasswordStrengthRule()
{
    $rule = new PasswordStrengthRule();
    
    // Valid passwords
    $rule->validate('password', 'StrongPass123', fn() => null);
    $rule->validate('password', 'AnotherPass456', fn() => null);
    
    // Invalid passwords
    $this->assertFalse($rule->validate('password', 'weak', fn() => null));
    $rule->validate('password', 'weak', fn($msg) => $msg('password'));
}

public function testPasswordHistoryRule()
{
    $user = User::factory()->create();
    $rule = new PasswordHistoryRule($user, 3);
    
    // Test password reuse
    $user->updatePassword('FirstPassword123');
    $this->assertFalse($rule->validate('password', 'FirstPassword123', fn() => null));
}
```

### Feature Tests
```php
public function testPasswordChange()
{
    $user = User::factory()->create([
        'password' => Hash::make('OldPassword123')
    ]);
    
    $response = $this->actingAs($user)->post('/user/change-password', [
        'current_password' => 'OldPassword123',
        'new_password' => 'NewSecurePassword456!',
        'new_password_confirmation' => 'NewSecurePassword456!'
    ]);
    
    $response->assertJson(['success' => 'Password changed successfully.']);
    $this->assertTrue(Hash::check('NewSecurePassword456!', $user->fresh()->password));
}
```

## Configuration

### Validation Rules Registration
```php
// In Form Request
public function rules(): array
{
    return [
        'password' => [
            'required',
            'min:8',
            new PasswordStrengthRule(),
            new PasswordHistoryRule(auth()->user(), 3),
        ],
    ];
}
```

### Customization Options
```php
// Change history count
$user->updatePassword($newPassword, 5); // Keep last 5 passwords

// Custom strength requirements
class CustomPasswordStrengthRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Custom validation logic
    }
}
```

## Security Considerations

### Password Storage
- **Hashing**: All passwords stored as bcrypt hashes
- **History**: Only hashed passwords stored, never plain text
- **Cleanup**: Automatic cleanup of old password history

### Validation Security
- **Client-Side**: For UX only, server-side for security
- **Rate Limiting**: Consider adding rate limiting to password changes
- **Session Security**: Require current password verification

### Best Practices
- **Minimum Length**: 8 characters minimum
- **Complexity**: Mix of character types
- **History**: Prevent immediate reuse
- **Expiration**: Consider password expiration policies

## Troubleshooting

### Common Issues
1. **Migration Conflicts**: Run `php artisan migrate:fresh` for clean setup
2. **Validation Not Working**: Check service provider registration
3. **History Not Saving**: Verify JSON column is properly cast
4. **Client-Side Errors**: Check Alpine.js initialization

### Debug Commands
```bash
# Check migration status
php artisan migrate:status

# Test validation rules
php artisan tinker
>>> $rule = new App\Rules\PasswordStrengthRule();
>>> $rule->validate('password', 'TestPass123', fn() => null)

# Check user model
php artisan tinker
>>> $user = App\Models\User::first();
>>> $user->getPasswordHistory();
```

## Future Enhancements

### Planned Features
- **Password Expiration**: Automatic password expiration
- **Two-Factor Auth**: Integration with 2FA systems
- **Breached Password Check**: Integration with breach databases
- **Password Policies**: Configurable password policies per role

### Integration Options
- **LDAP Integration**: External authentication systems
- **SSO Providers**: Single sign-on compatibility
- **Mobile Apps**: API endpoints for mobile applications
- **Password Managers**: Enhanced support for password managers

The password security system provides comprehensive protection with both client-side and server-side validation, password history tracking, and visual feedback for users.
