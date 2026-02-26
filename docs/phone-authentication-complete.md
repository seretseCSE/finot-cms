# Phone-Based Authentication Implementation

## Overview
Complete phone-based authentication system for Filament admin panel, replacing email-based login with Ethiopian mobile number authentication.

## Architecture

### 1. Custom User Provider
**File**: `app/Auth/PhoneUserProvider.php`

**Features**:
- Extends Laravel's `EloquentUserProvider`
- Finds users by phone number instead of email
- Supports fallback to ID for compatibility
- Handles remember tokens with phone lookup

**Key Methods**:
```php
retrieveByCredentials() // Finds user by phone
retrieveById() // Tries phone first, then ID
retrieveByToken() // Phone-based token lookup
validateCredentials() // Password verification
```

### 2. Auth Service Provider
**File**: `app/Providers/AuthServiceProvider.php`

**Purpose**: Registers the custom phone user provider with Laravel's authentication system.

```php
Auth::provider('phone', function ($app, array $config) {
    return new PhoneUserProvider($app['hash'], $config['model']);
});
```

### 3. Authentication Configuration
**File**: `config/auth.php`

**Changes**:
- Updated user provider driver from 'eloquent' to 'phone'
- Maintains same model configuration

```php
'providers' => [
    'users' => [
        'driver' => 'phone', // Changed from 'eloquent'
        'model' => App\Models\User::class,
    ],
],
```

### 4. Custom Filament Login Action
**File**: `app/Filament/Actions/FilamentPhoneLoginAction.php`

**Features**:
- Extends Filament's Action class
- Phone-based authentication logic
- Security features (rate limiting, account locking)
- Ethiopian phone validation
- Bilingual error messages

**Authentication Flow**:
1. Rate limiting (5 attempts)
2. User existence check
3. Account status validation
4. Password verification
5. Failed attempt tracking
6. Account locking after 5 failures

### 5. Updated Login Page
**File**: `app/Filament/Pages/Auth/Login.php`

**Changes**:
- Uses custom `FilamentPhoneLoginAction`
- Removes email field completely
- Maintains Filament's base login functionality

## User Model Updates

### Phone as Primary Identifier
```php
// Authentication methods
public function getAuthIdentifierName() {
    return 'phone'; // Changed from 'email'
}

public function username(): string {
    return $this->phone;
}

// Phone-based lookup
public function findForPhone(string $phone): ?User {
    return $this->where('phone', $phone)->first();
}
```

### Security Methods
```php
public function isAccountLocked(): bool;
public function needsPasswordChange(): bool;
public function resetFailedAttempts(): void;
public function incrementFailedAttempts(): void;
```

## Database Schema

### Users Table Structure
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,  -- Primary identifier
    email VARCHAR(255) NULL,             -- Optional metadata only
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_locked BOOLEAN DEFAULT FALSE,
    temp_password_changed BOOLEAN DEFAULT FALSE,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    department_id BIGINT NULL,
    language_preference ENUM('am', 'en') DEFAULT 'am',
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Security Features

### 1. Account Locking
- **Trigger**: 5 failed login attempts
- **Duration**: 30 minutes
- **Automatic Reset**: On successful login
- **Status Check**: Before each login attempt

### 2. Rate Limiting
- **Limit**: 5 attempts per session
- **Duration**: Based on Laravel's rate limiting
- **Message**: Shows remaining time

### 3. Account Status Validation
- **Active Check**: `is_active = true`
- **Lock Check**: `is_locked = false` and `locked_until` in past
- **Temporary Password**: `temp_password_changed = true`

### 4. Ethiopian Phone Validation
- **Format**: `+251[97][0-9]{8}`
- **Valid Prefixes**: 9 (Ethio Telecom), 7 (other operators)
- **Custom Rule**: `EthiopianPhoneRule`

## Usage Examples

### User Registration
```php
// Create user with phone as primary identifier
$user = User::create([
    'name' => 'John Doe',
    'phone' => '+251912345678',
    'password' => Hash::make('password'),
    'language_preference' => 'am',
]);

// Email is optional (metadata only)
$user->update(['email' => 'john@example.com']);
```

### Authentication
```php
// Standard Laravel auth works with phone
if (Auth::attempt(['phone' => '+251912345678', 'password' => 'password'])) {
    // User authenticated
}

// Find user by phone
$user = User::findForPhone('+251912345678');
```

### Security Methods
```php
// Check account status
if ($user->isAccountLocked()) {
    $lockTime = $user->locked_until->diffInMinutes(now());
}

// Reset failed attempts
$user->resetFailedAttempts();

// Increment failed attempts
$user->incrementFailedAttempts();
```

## Configuration

### Auth Configuration
```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users', // Uses phone provider
    ],
],

'providers' => [
    'users' => [
        'driver' => 'phone', // Custom phone provider
        'model' => App\Models\User::class,
    ],
],
```

### Service Provider Registration
```php
// bootstrap/providers.php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class, // Phone provider registration
];
```

## Migration Notes

### Fresh Installation
For fresh installations, the users table is created with phone as the primary identifier from the start.

### Existing Installations
For existing installations with email-based users:
1. Run migration to add phone field
2. Populate phone numbers for existing users
3. Update authentication configuration
4. Test phone-based login

## Testing

### Authentication Tests
```php
public function test_phone_authentication()
{
    $user = User::create([
        'name' => 'Test User',
        'phone' => '+251912345678',
        'password' => Hash::make('password'),
    ]);

    $this->post('/login', [
        'phone' => '+251912345678',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
}

public function test_phone_validation()
{
    $response = $this->post('/login', [
        'phone' => 'invalid-phone',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('phone');
}
```

### User Provider Tests
```php
public function test_phone_user_provider()
{
    $provider = new PhoneUserProvider(app('hash'), User::class);
    
    $user = $provider->retrieveByCredentials([
        'phone' => '+251912345678',
        'password' => 'password',
    ]);
    
    $this->assertInstanceOf(User::class, $user);
    $this->assertEquals('+251912345678', $user->phone);
}
```

## Troubleshooting

### Common Issues

1. **Provider Not Found**: Ensure `AuthServiceProvider` is registered in `bootstrap/providers.php`
2. **Migration Conflicts**: Run `php artisan migrate:fresh` for clean installation
3. **Authentication Fails**: Check `config/auth.php` provider configuration
4. **Phone Validation**: Verify Ethiopian phone format with `EthiopianPhoneRule`

### Debug Commands
```bash
# Check provider registration
php artisan about

# Test authentication
php artisan tinker
>>> Auth::attempt(['phone' => '+251912345678', 'password' => 'password'])

# Check user model
php artisan tinker
>>> User::findForPhone('+251912345678')
```

## Security Considerations

### Phone Number Privacy
- Store phone numbers securely
- Implement rate limiting
- Use HTTPS for all requests
- Consider phone number masking in logs

### Account Protection
- Monitor failed login attempts
- Implement account recovery options
- Set up security notifications
- Regular security audits

## Future Enhancements

### Planned Features
- SMS-based 2FA authentication
- Phone number verification via SMS
- Account recovery via mobile number
- Integration with Ethiopian SMS gateways

### Integration Options
- Ethiopian telecom APIs for validation
- Mobile app authentication
- WhatsApp Business integration
- USSD-based authentication

## Benefits

### User Experience
- **Faster Login**: No need to remember email
- **Mobile-Friendly**: Optimized for Ethiopian mobile users
- **Bilingual**: Amharic/English support
- **Secure**: Advanced security features

### Administrative Benefits
- **Unique Identification**: Phone numbers are unique per person
- **Contact Method**: Direct communication channel
- **Verification**: Phone-based verification options
- **Local Context**: Designed for Ethiopian users

The phone-based authentication system provides a secure, user-friendly login experience specifically designed for Ethiopian church management applications.
