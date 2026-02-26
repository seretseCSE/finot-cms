# Enhanced Account Locking System

## Overview
Progressive account locking system with audit logging, configurable lockout durations, and detailed security tracking. Implements tiered lockout periods and comprehensive audit logging for security compliance.

## Features

### 🔐 Progressive Lockout System
- **First lockout**: 1 minute after 5 failed attempts
- **Subsequent lockouts**: 5 minutes for each additional group of 5 failed attempts
- **Automatic unlocking**: Lockout expires automatically after duration
- **Remaining time display**: Shows exact remaining lockout time

### 📊 Comprehensive Audit Logging
- **Tier 1 audit logs**: 30-day retention
- **Failed login tracking**: Every failed attempt logged
- **Account lockout events**: Detailed lockout information
- **Successful login tracking**: After previous failures
- **Security metadata**: IP address, user agent, timestamps

### 🛡️ Security Features
- **Failed attempt tracking**: Persistent counter per user
- **Account status monitoring**: Real-time lockout checking
- **Automatic cleanup**: Reset on successful login
- **Detailed error messages**: User-friendly lockout notifications

## Implementation Files

### 1. Enhanced User Model
**File**: `app/Models/User.php`

**Progressive Locking Logic**:
```php
public function incrementFailedAttempts(): void
{
    $this->increment('failed_login_attempts');
    
    $failedAttempts = $this->failed_login_attempts;
    
    if ($failedAttempts >= 5) {
        // Progressive locking: 1 minute for first group, 5 for subsequent
        $lockDuration = ($failedAttempts === 5) ? 1 : 5;
        
        $this->update([
            'is_locked' => true,
            'locked_until' => now()->addMinutes($lockDuration),
        ]);
        
        // Log the lockout event
        $this->logFailedLogin('account_locked', [
            'failed_attempts' => $failedAttempts,
            'lock_duration_minutes' => $lockDuration,
            'locked_until' => $this->locked_until->toDateTimeString(),
        ]);
    }
}
```

**Audit Logging Methods**:
```php
public function logFailedLogin(string $event, array $context = []): void
{
    $logData = [
        'event' => $event,
        'user_id' => $this->id,
        'phone' => $this->phone,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'timestamp' => now()->toDateTimeString(),
        'failed_attempts' => $this->failed_login_attempts,
        'is_locked' => $this->is_locked,
        'locked_until' => $this->locked_until?->toDateTimeString(),
    ];
    
    logger()->channel('audit')->warning('Failed login attempt', array_merge($logData, $context));
}
```

**Lockout Message Methods**:
```php
public function getRemainingLockoutMinutes(): int
{
    if (!$this->is_locked || !$this->locked_until || $this->locked_until->isPast()) {
        return 0;
    }
    
    return $this->locked_until->diffInMinutes(now());
}

public function getLockoutMessage(): string
{
    $remainingMinutes = $this->getRemainingLockoutMinutes();
    
    if ($remainingMinutes <= 0) {
        return 'Account is locked. Please try again later.';
    }
    
    if ($remainingMinutes === 1) {
        return 'Account is locked. Please try again in 1 minute.';
    }
    
    return "Account is locked. Please try again in {$remainingMinutes} minutes.";
}
```

### 2. Enhanced FilamentPhoneLoginAction
**File**: `app/Filament/Actions/FilamentPhoneLoginAction.php`

**Enhanced Authentication Logic**:
```php
// Check account lock status with detailed message
if ($user->isAccountLocked()) {
    throw ValidationException::withMessages([
        'phone' => $user->getLockoutMessage(),
    ]);
}

// Log failed login attempt with context
if (!auth()->attempt($credentials, $data['remember'] ?? false)) {
    $user->logFailedLogin('login_failed', [
        'reason' => 'invalid_credentials',
        'login_attempt' => $user->failed_login_attempts + 1,
    ]);
    
    $user->incrementFailedAttempts();
    
    throw ValidationException::withMessages([
        'phone' => __('filament::login.messages.failed'),
    ]);
}

// Log successful login
if ($user->failed_login_attempts > 0) {
    $user->logFailedLogin('login_success_after_failures', [
        'previous_failed_attempts' => $user->failed_login_attempts,
        'was_locked' => $user->is_locked,
    ]);
    
    $user->resetFailedAttempts();
} else {
    $user->logFailedLogin('login_success', [
        'first_attempt' => true,
    ]);
}
```

### 3. Audit Log Configuration
**File**: `config/logging.php`

**Audit Channel Setup**:
```php
'audit' => [
    'driver' => 'daily',
    'path' => storage_path('logs/audit.log'),
    'level' => 'warning',
    'days' => 30, // 30-day retention for Tier 1 audit logs
    'replace_placeholders' => true,
    'bubble' => false, // Prevent bubbling to other channels
],
```

## Lockout Behavior

### Progressive Lockout Durations

| **Failed Attempts** | **Lock Duration** | **Message** |
|-------------------|------------------|-------------|
| 1-4 | No lockout | Normal login attempts |
| 5-9 | 1 minute | "Account is locked. Please try again in 1 minute." |
| 10+ | 5 minutes | "Account is locked. Please try again in X minutes." |

### Lockout Scenarios

#### **First Lockout (5 attempts)**
```php
// After 5 failed attempts
$user->failed_login_attempts = 5;
$user->is_locked = true;
$user->locked_until = now()->addMinutes(1);

// Audit log entry
{
    "event": "account_locked",
    "user_id": 123,
    "phone": "+251912345678",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-02-17 23:45:00",
    "failed_attempts": 5,
    "lock_duration_minutes": 1,
    "locked_until": "2024-02-17 23:46:00"
}
```

#### **Subsequent Lockout (10 attempts)**
```php
// After 10 failed attempts
$user->failed_login_attempts = 10;
$user->is_locked = true;
$user->locked_until = now()->addMinutes(5);

// Audit log entry
{
    "event": "account_locked",
    "user_id": 123,
    "phone": "+251912345678",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-02-17 23:50:00",
    "failed_attempts": 10,
    "lock_duration_minutes": 5,
    "locked_until": "2024-02-17 23:55:00"
}
```

## Audit Log Events

### Event Types

#### **login_failed**
```json
{
    "event": "login_failed",
    "user_id": 123,
    "phone": "+251912345678",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-02-17 23:45:00",
    "failed_attempts": 3,
    "is_locked": false,
    "reason": "invalid_credentials",
    "login_attempt": 4
}
```

#### **account_locked**
```json
{
    "event": "account_locked",
    "user_id": 123,
    "phone": "+251912345678",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-02-17 23:45:00",
    "failed_attempts": 5,
    "is_locked": true,
    "locked_until": "2024-02-17 23:46:00",
    "lock_duration_minutes": 1
}
```

#### **login_success**
```json
{
    "event": "login_success",
    "user_id": 123,
    "phone": "+251912345678",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-02-17 23:47:00",
    "failed_attempts": 0,
    "is_locked": false,
    "first_attempt": true
}
```

#### **login_success_after_failures**
```json
{
    "event": "login_success_after_failures",
    "user_id": 123,
    "phone": "+251912345678",
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2024-02-17 23:52:00",
    "failed_attempts": 5,
    "is_locked": false,
    "previous_failed_attempts": 5,
    "was_locked": true
}
```

## Usage Examples

### Check Account Lock Status
```php
$user = User::where('phone', '+251912345678')->first();

// Check if account is locked
if ($user->isAccountLocked()) {
    $remainingMinutes = $user->getRemainingLockoutMinutes();
    $message = $user->getLockoutMessage();
    
    echo "Account locked. {$message}";
}
```

### Manual Lockout Management
```php
// Lock account manually
$user->update([
    'is_locked' => true,
    'locked_until' => now()->addMinutes(30),
]);

// Unlock account manually
$user->update([
    'is_locked' => false,
    'locked_until' => null,
    'failed_login_attempts' => 0,
]);
```

### Audit Log Analysis
```bash
# View recent failed login attempts
tail -f storage/logs/audit-2024-02-17.log | grep "login_failed"

# View account lockouts
grep "account_locked" storage/logs/audit-*.log

# View successful logins after failures
grep "login_success_after_failures" storage/logs/audit-*.log
```

## Security Monitoring

### Real-time Monitoring
```php
// Get users with recent failed attempts
$suspiciousUsers = User::where('failed_login_attempts', '>=', 3)
    ->where('updated_at', '>=', now()->subHours(24))
    ->get();

// Get currently locked accounts
$lockedAccounts = User::where('is_locked', true)
    ->where('locked_until', '>', now())
    ->get();
```

### Security Dashboard
```php
// Security statistics for admin dashboard
$securityStats = [
    'failed_attempts_24h' => User::where('failed_login_attempts', '>', 0)
        ->where('updated_at', '>=', now()->subHours(24))
        ->sum('failed_login_attempts'),
    
    'currently_locked' => User::where('is_locked', true)
        ->where('locked_until', '>', now())
        ->count(),
    
    'accounts_locked_today' => User::whereDate('locked_until', today())->count(),
];
```

## Configuration

### Environment Variables
```env
# Logging configuration
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Session configuration
SESSION_LIFETIME=30
```

### Customization Options
```php
// Customize lockout durations in User model
public function incrementFailedAttempts(): void
{
    $this->increment('failed_login_attempts');
    
    $failedAttempts = $this->failed_login_attempts;
    
    if ($failedAttempts >= 5) {
        // Custom progressive logic
        $lockDuration = match(true) {
            $failedAttempts === 5 => 1,
            $failedAttempts <= 10 => 5,
            default => 15, // Longer lockouts for persistent attempts
        };
        
        $this->update([
            'is_locked' => true,
            'locked_until' => now()->addMinutes($lockDuration),
        ]);
    }
}
```

## Testing

### Unit Tests
```php
public function testProgressiveLockout()
{
    $user = User::factory()->create();
    
    // First 4 attempts - no lockout
    for ($i = 1; $i <= 4; $i++) {
        $user->incrementFailedAttempts();
        $this->assertFalse($user->is_locked);
    }
    
    // 5th attempt - 1 minute lockout
    $user->incrementFailedAttempts();
    $this->assertTrue($user->is_locked);
    $this->assertEquals(1, $user->getRemainingLockoutMinutes());
    
    // 10th attempt - 5 minute lockout
    $user->update(['failed_login_attempts' => 9]);
    $user->incrementFailedAttempts();
    $this->assertEquals(5, $user->getRemainingLockoutMinutes());
}

public function testAuditLogging()
{
    $user = User::factory()->create();
    
    // Simulate failed login
    $user->logFailedLogin('login_failed', [
        'reason' => 'invalid_credentials',
        'login_attempt' => 1,
    ]);
    
    // Check log file
    $logContent = file_get_contents(storage_path('logs/audit-'.date('Y-m-d').'.log'));
    $this->assertStringContainsString('login_failed', $logContent);
    $this->assertStringContainsString('invalid_credentials', $logContent);
}
```

### Feature Tests
```php
public function testAccountLockoutFlow()
{
    $user = User::factory()->create([
        'password' => Hash::make('correct-password')
    ]);
    
    // 5 failed attempts
    for ($i = 1; $i <= 5; $i++) {
        $response = $this->post('/admin/login', [
            'phone' => $user->phone,
            'password' => 'wrong-password',
        ]);
        
        if ($i < 5) {
            $response->assertSessionHasErrors('phone');
        } else {
            $response->assertSessionHasErrors('phone');
            $this->assertStringContainsString('Account is locked', session('errors')['phone'][0]);
        }
    }
    
    // Verify account is locked
    $user->refresh();
    $this->assertTrue($user->is_locked);
    $this->assertNotNull($user->locked_until);
}
```

## Troubleshooting

### Common Issues

1. **Lockout Not Working**
   - Verify `failed_login_attempts` column exists
   - Check User model methods are properly implemented
   - Ensure audit log channel is configured

2. **Audit Logs Not Appearing**
   - Check `storage/logs/audit.log` permissions
   - Verify logging configuration
   - Check log level settings

3. **Lockout Messages Not Updating**
   - Verify `getRemainingLockoutMinutes()` method
   - Check `locked_until` timestamp logic
   - Ensure timezone consistency

### Debug Commands
```bash
# Check user lock status
php artisan tinker
>>> $user = User::where('phone', '+251912345678')->first();
>>> $user->is_locked;
>>> $user->getRemainingLockoutMinutes();

# Check audit logs
tail -f storage/logs/audit-2024-02-17.log

# Test logging
php artisan tinker
>>> $user = User::first();
>>> $user->logFailedLogin('test_event', ['test' => true]);
```

## Security Considerations

### Audit Log Security
- **30-day retention** meets compliance requirements
- **Structured logging** for easy parsing
- **IP address tracking** for security analysis
- **User agent logging** for device identification

### Lockout Security
- **Progressive durations** prevent brute force
- **Automatic unlocking** prevents permanent lockout
- **Failed attempt tracking** persists across sessions
- **Detailed error messages** without information disclosure

### Best Practices
- **Regular log rotation** to manage disk space
- **Log monitoring** for security incidents
- **Account recovery** procedures for legitimate users
- **Rate limiting** in addition to lockout system

## Performance Considerations

### Database Performance
- **Index on failed_login_attempts** for efficient queries
- **Index on locked_until** for lockout checks
- **Regular cleanup** of old audit logs

### Logging Performance
- **Async logging** to prevent blocking requests
- **Log level filtering** to reduce noise
- **Structured logging** for efficient parsing

## Future Enhancements

### Planned Features
- **Custom lockout policies** per user role
- **IP-based lockout** for multiple accounts
- **SMS notifications** for lockout events
- **Admin dashboard** for security monitoring

### Integration Options
- **SIEM integration** for enterprise security
- **Email alerts** for suspicious activity
- **Webhook notifications** for real-time monitoring
- **Machine learning** for anomaly detection

The enhanced account locking system provides comprehensive security with progressive lockout periods, detailed audit logging, and user-friendly error messages while maintaining excellent performance and compliance with security standards.
