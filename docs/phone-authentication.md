# Phone-Based Authentication System

## Overview
The Filament login system has been modified to use phone numbers instead of email addresses for authentication, specifically designed for Ethiopian mobile numbers.

## Features

### 🔐 Phone Number Authentication
- **Primary Login Field**: Phone number instead of email
- **Ethiopian Mobile Format**: +251XXXXXXXXX (Ethiopian mobile numbers only)
- **Bilingual Labels**: Phone Number / ስልክ ቁጥር
- **Input Validation**: Strict Ethiopian phone number validation

### 🛡️ Security Features
- **Failed Login Attempts**: Tracks failed attempts and locks account after 5 tries
- **Account Locking**: 30-minute lockout after multiple failed attempts
- **Account Status**: Checks for active/inactive status
- **Rate Limiting**: Prevents brute force attacks

### 📱 Ethiopian Phone Validation
- **Format**: +251[97][0-9]{8}
- **Valid Prefixes**: 9 (Ethio Telecom), 7 (other operators)
- **Length**: 13 characters total (+251 + 9 digits)
- **Example**: +251912345678

## Implementation Files

### 1. Custom Login Page
**File**: `app/Filament/Pages/Auth/Login.php`
- Extends Filament's base Login class
- Replaces email field with phone field
- Implements phone-based authentication logic
- Handles account locking and failed attempts

### 2. User Model Updates
**File**: `app/Models/User.php`
- Added phone-based authentication methods
- Updated fillable fields for new user attributes
- Added account status methods
- Phone number as primary identifier

### 3. Phone Validation Rule
**File**: `app/Rules/EthiopianPhoneRule.php`
- Validates Ethiopian mobile number format
- Custom validation messages
- Regex pattern for Ethiopian numbers

### 4. Panel Provider
**File**: `app/Providers/Filament/AdminPanelProvider.php`
- Registers custom login page
- Configures Filament panel settings
- Adds custom branding

### 5. Migration
**File**: `database/migrations/2026_02_17_220000_update_users_phone_unique.php`
- Makes phone field unique
- Removes email unique constraint
- Updates field constraints

## Usage Examples

### User Registration
```php
// Create user with phone number
$user = User::create([
    'name' => 'John Doe',
    'phone' => '+251912345678',
    'password' => Hash::make('password'),
    'is_active' => true,
    'language_preference' => 'am',
]);
```

### Authentication Check
```php
// Find user by phone
$user = User::where('phone', '+251912345678')->first();

// Check account status
if ($user->isAccountLocked()) {
    // Account is locked
}

// Check if password change needed
if ($user->needsPasswordChange()) {
    // User needs to change temporary password
}
```

### Phone Validation
```php
// In form requests
'phone' => ['required', new EthiopianPhoneRule()],

// In controller validation
$request->validate([
    'phone' => 'required|regex:/^\+251[97][0-9]{8}$/',
]);
```

## Security Features

### Failed Login Attempts
```php
// User model handles failed attempts automatically
$user->incrementFailedAttempts(); // Increments counter
$user->resetFailedAttempts(); // Resets on successful login

// Check if account is locked
if ($user->isAccountLocked()) {
    $lockTimeRemaining = $user->locked_until->diffInMinutes(now());
}
```

### Account Status Checks
- **Active**: User can login normally
- **Inactive**: User cannot login (deactivated)
- **Locked**: User temporarily locked due to failed attempts
- **Temporary Password**: User must change password on first login

## Ethiopian Phone Number Format

### Valid Formats
- ✅ `+251912345678` (Ethio Telecom)
- ✅ `+251712345678` (Other operators)
- ✅ `+251923456789` (Other valid prefixes)

### Invalid Formats
- ❌ `251912345678` (missing +)
- ❌ `+251812345678` (invalid prefix)
- ❌ `+25191234567` (too short)
- ❌ `+2519123456789` (too long)
- ❌ `0912345678` (local format)

## Database Schema

### Users Table Updates
```sql
-- Phone field becomes unique primary identifier
ALTER TABLE users 
MODIFY phone VARCHAR(20) UNIQUE NOT NULL,
MODIFY email VARCHAR(255) NULL,
DROP INDEX users_email_unique;

-- Additional security fields
ALTER TABLE users 
ADD COLUMN is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN is_locked BOOLEAN DEFAULT FALSE,
ADD COLUMN temp_password_changed BOOLEAN DEFAULT FALSE,
ADD COLUMN failed_login_attempts INT DEFAULT 0,
ADD COLUMN locked_until TIMESTAMP NULL,
ADD COLUMN department_id BIGINT NULL,
ADD COLUMN language_preference ENUM('am', 'en') DEFAULT 'am';
```

## Frontend Features

### Login Form
- **Phone Input**: Tel input type with proper validation
- **Bilingual Labels**: English/Amharic text
- **Helper Text**: Format examples and guidance
- **Error Messages**: Clear validation feedback

### User Experience
- **Auto-focus**: Phone field automatically focused
- **Tab Navigation**: Proper tab order
- **Mobile Friendly**: Optimized for mobile devices
- **Accessibility**: Proper ARIA labels and semantic HTML

## Configuration

### Filament Panel
```php
// config/filament.php
'panels' => [
    'admin' => [
        'provider' => \App\Providers\Filament\AdminPanelProvider::class,
    ],
],
```

### Authentication Guards
```php
// No changes needed - uses default Laravel auth
// Phone number works as username field
```

## Testing

### Phone Validation Tests
```php
public function test_ethiopian_phone_validation()
{
    $rule = new EthiopianPhoneRule();
    
    // Valid numbers
    $this->assertTrue($rule->validate('phone', '+251912345678', fn() => null));
    $this->assertTrue($rule->validate('phone', '+251712345678', fn() => null));
    
    // Invalid numbers
    $this->assertFalse($rule->validate('phone', '251912345678', fn() => null));
    $this->assertFalse($rule->validate('phone', '+251812345678', fn() => null));
}
```

### Authentication Tests
```php
public function test_phone_login()
{
    $user = User::create([
        'name' => 'Test User',
        'phone' => '+251912345678',
        'password' => Hash::make('password'),
    ]);
    
    $response = $this->post('/admin/login', [
        'phone' => '+251912345678',
        'password' => 'password',
    ]);
    
    $this->assertAuthenticatedAs($user);
}
```

## Migration Notes

### Important Steps
1. **Backup Database**: Before running migrations
2. **Update Existing Users**: Add phone numbers to existing user records
3. **Test Migration**: Run in development first
4. **Update Forms**: Ensure all forms use phone instead of email

### Rollback Plan
```bash
# If issues occur, rollback migration
php artisan migrate:rollback --step=1

# Then restore email-based authentication
```

## Troubleshooting

### Common Issues
1. **Phone Number Format**: Ensure +251 prefix is included
2. **Unique Constraint**: Handle duplicate phone numbers
3. **Migration Conflicts**: Check existing phone field constraints
4. **Authentication**: Verify Laravel auth configuration

### Debug Mode
```php
// Enable debug mode for detailed error messages
APP_DEBUG=true

// Check authentication logs
tail -f storage/logs/laravel.log
```

## Security Considerations

### Phone Number Privacy
- Store phone numbers securely
- Implement rate limiting
- Use HTTPS for all requests
- Consider phone number masking in logs

### Account Protection
- Implement 2FA for sensitive operations
- Monitor failed login attempts
- Set up account recovery options
- Regular security audits

## Future Enhancements

### Planned Features
- SMS-based 2FA authentication
- Phone number verification
- Account recovery via SMS
- Multi-language support expansion

### Integration Options
- Ethiopian SMS gateways
- Phone number lookup services
- Mobile app integration
- WhatsApp Business API
