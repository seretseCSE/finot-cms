# Forced Password Change System

## Overview
Comprehensive forced password change system that ensures users with temporary passwords must change them before accessing the application. The system includes middleware-based enforcement, dedicated Filament page, and automatic password history tracking.

## Architecture

### 1. ForcePasswordChange Middleware
**File**: `app/Http/Middleware/ForcePasswordChange.php`

**Purpose**: Checks `temp_password_changed` flag on every authenticated request and redirects users who haven't changed their temporary password.

**Features**:
- Runs on every authenticated request
- Checks `temp_password_changed` flag
- Allows access to password change page and logout
- Stores intended URL for redirect after password change
- Redirects to `/admin/change-password` when needed

**Logic Flow**:
```php
if (Auth::check() && !Auth::user()->temp_password_changed) {
    if (!in_array($currentRoute, $allowedRoutes)) {
        session(['url.intended' => $request->fullUrl()]);
        return redirect()->route('filament.admin.change-password');
    }
}
```

### 2. ChangeInitialPassword Filament Page
**File**: `app/Filament/Pages/Auth/ChangeInitialPassword.php`

**Purpose**: Dedicated page for users to change their temporary password with full validation and history tracking.

**Features**:
- Current password verification
- New password strength validation
- Password history checking (last 3 passwords)
- Automatic `temp_password_changed` flag update
- Redirect to intended page after successful change

**Form Fields**:
- **Current Password**: Required for verification
- **New Password**: With strength and history validation
- **Confirm New Password**: Must match new password

### 3. Middleware Registration
**Files**:
- `bootstrap/app.php` - Middleware alias registration
- `config/filament.php` - Panel middleware configuration
- `app/Providers/Filament/AdminPanelProvider.php` - Panel setup

**Registration**:
```php
// bootstrap/app.php
$middleware->alias([
    'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
]);

// config/filament.php
'middleware' => [
    'web',
    'auth',
    'force.password.change', // Applied to all authenticated routes
],
```

## Implementation Details

### Middleware Behavior

#### Allowed Routes
Users who haven't changed their password can only access:
- `filament.admin.change-password` - Password change page
- `filament.admin.logout` - Logout functionality
- `filament.admin.auth.logout` - Auth logout

#### Redirect Logic
1. User tries to access any protected route
2. Middleware checks `temp_password_changed` flag
3. If `false`, stores intended URL in session
4. Redirects to password change page
5. After successful change, redirects to intended URL

#### Session Management
```php
// Store intended URL
session(['url.intended' => $request->fullUrl()]);

// Retrieve and clear after password change
$intendedUrl = session()->pull('url.intended', route('filament.admin.pages.dashboard'));
```

### Password Change Page Features

#### Form Validation
```php
TextInput::make('current_password')
    ->password()
    ->required()
    ->helperText('Enter your current password to continue');

TextInput::make('new_password')
    ->password()
    ->required()
    ->rules([
        new PasswordStrengthRule(),
        new PasswordHistoryRule(Auth::user(), 3),
    ]);

TextInput::make('new_password_confirmation')
    ->password()
    ->required()
    ->same('new_password');
```

#### Password Update Process
```php
public function changePassword(): void
{
    $data = $this->form->getState();
    $user = Auth::user();
    
    // Verify current password
    if (!Hash::check($data['current_password'], $user->password)) {
        $this->addError('current_password', 'Current password is incorrect.');
        return;
    }
    
    // Update password with history tracking
    $user->updatePassword($data['new_password'], 3);
    
    // Mark temporary password as changed
    $user->update(['temp_password_changed' => true]);
    
    // Redirect to intended page
    $this->redirect($intendedUrl);
}
```

#### Client-Side Features
- **Real-time password strength indicator**
- **Requirement checklist** with visual feedback
- **Strength bar** with color coding
- **Form validation** with error messages

### Database Schema

#### Users Table Structure
```sql
CREATE TABLE users (
    -- ... other fields
    password VARCHAR(255) NOT NULL,
    password_history JSON NULL,
    temp_password_changed BOOLEAN DEFAULT FALSE,
    -- ... other fields
);
```

#### Password History Format
```json
[
    "$2y$10$abcdefghijklmnopqrstuvwx.yz1234567890",
    "$2y$10$zyxwvutsrqponmlkjihgfedcba9876543210",
    "$2y$10$0987654321zyxwvutsrqponmlkjihgfedcba"
]
```

## Usage Examples

### Creating Users with Temporary Passwords
```php
// User who must change password
User::create([
    'name' => 'New User',
    'phone' => '+251912345678',
    'password' => Hash::make('temp123'),
    'temp_password_changed' => false, // Forces change
]);

// User who can skip password change
User::create([
    'name' => 'Existing User',
    'phone' => '+251923456789',
    'password' => Hash::make('SecurePass123!'),
    'temp_password_changed' => true, // No change required
]);
```

### Manual Password Change
```php
// Force user to change password
$user->update(['temp_password_changed' => false]);

// Mark password as changed
$user->update(['temp_password_changed' => true]);

// Update password with history tracking
$user->updatePassword('NewSecurePassword456!', 3);
```

### Testing the System
```php
// Test user creation
$user = User::create([
    'name' => 'Test User',
    'phone' => '+251912345678',
    'password' => Hash::make('temp123'),
    'temp_password_changed' => false,
]);

// Test middleware behavior
$this->actingAs($user)
    ->get('/admin/dashboard')
    ->assertRedirect('/admin/change-password');

// Test password change
$this->actingAs($user)
    ->post('/admin/change-password', [
        'current_password' => 'temp123',
        'new_password' => 'NewSecurePass456!',
        'new_password_confirmation' => 'NewSecurePass456!',
    ])
    ->assertRedirect('/admin/dashboard');
```

## Security Features

### Password Verification
- **Current Password Check**: Users must verify current password
- **Strength Validation**: Enforces strong password requirements
- **History Prevention**: Cannot reuse last 3 passwords
- **Automatic Hashing**: Secure password storage

### Session Security
- **Intended URL Storage**: Preserves user's destination
- **Logout Access**: Users can always log out
- **Route Protection**: Middleware protects all authenticated routes

### Account Security
- **Temporary Password Flag**: Clear indication of required action
- **Automatic Updates**: System manages flag automatically
- **History Tracking**: Prevents password reuse

## Frontend Features

### Visual Feedback
```html
<!-- Password strength indicator -->
<div class="flex items-center space-x-2">
    <div class="flex-1 bg-gray-200 rounded-full h-2">
        <div id="strength-bar" class="h-2 rounded-full transition-all duration-300"></div>
    </div>
    <span id="strength-text" class="text-sm font-medium">Weak</span>
</div>

<!-- Requirement checklist -->
<ul class="space-y-1 text-xs text-gray-600">
    <li id="req-length" class="text-gray-400">✓ At least 8 characters</li>
    <li id="req-uppercase" class="text-gray-400">✓ At least one uppercase letter (A-Z)</li>
    <li id="req-lowercase" class="text-gray-400">✓ At least one lowercase letter (a-z)</li>
    <li id="req-number" class="text-gray-400">✓ At least one number (0-9)</li>
</ul>
```

### JavaScript Validation
```javascript
// Real-time strength calculation
newPasswordInput.addEventListener('input', function() {
    const password = this.value;
    const hasLength = password.length >= 8;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    
    // Update visual indicators
    updateRequirementIndicators(hasLength, hasUppercase, hasLowercase, hasNumber);
    updateStrengthBar(calculateStrength(password));
});
```

## Configuration

### Middleware Registration
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
    ]);
})
```

### Panel Configuration
```php
// config/filament.php
'panels' => [
    'admin' => [
        'middleware' => [
            'web',
            'auth',
            'force.password.change',
        ],
        'pages' => [
            \App\Filament\Pages\Auth\ChangeInitialPassword::class,
        ],
    ],
],
```

### Route Registration
```php
// Panel automatically registers routes:
// /admin/change-password - Password change page
// /admin/logout - Logout (always allowed)
```

## Testing

### Middleware Tests
```php
public function testForcePasswordChangeMiddleware()
{
    $user = User::factory()->create(['temp_password_changed' => false]);
    
    // Test redirect to password change
    $response = $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertRedirect('/admin/change-password');
    
    // Test intended URL storage
    $this->assertSessionHas('url.intended', 'http://localhost/admin/dashboard');
}

public function testPasswordChangePageAccess()
{
    $user = User::factory()->create(['temp_password_changed' => false]);
    
    // Test password change page is accessible
    $response = $this->actingAs($user)
        ->get('/admin/change-password')
        ->assertOk();
}
```

### Password Change Tests
```php
public function testPasswordChangeFlow()
{
    $user = User::factory()->create([
        'password' => Hash::make('temp123'),
        'temp_password_changed' => false,
    ]);
    
    // Test successful password change
    $response = $this->actingAs($user)
        ->post('/admin/change-password', [
            'current_password' => 'temp123',
            'new_password' => 'NewSecurePass456!',
            'new_password_confirmation' => 'NewSecurePass456!',
        ])
        ->assertRedirect('/admin/dashboard');
    
    // Verify flag is updated
    $this->assertTrue($user->fresh()->temp_password_changed);
    
    // Verify password is updated
    $this->assertTrue(Hash::check('NewSecurePass456!', $user->fresh()->password));
}
```

## Troubleshooting

### Common Issues

1. **Middleware Not Working**
   - Verify middleware is registered in `bootstrap/app.php`
   - Check panel configuration in `config/filament.php`
   - Ensure provider is registered in `bootstrap/providers.php`

2. **Page Not Found**
   - Verify page class is registered in panel configuration
   - Check that page class exists and extends correct base class
   - Ensure view file exists in correct location

3. **Password History Not Working**
   - Verify `password_history` column exists in database
   - Check that User model has correct casts
   - Ensure `updatePassword` method is being called

4. **Redirect Loop**
   - Verify password change page is in allowed routes list
   - Check that `temp_password_changed` flag is properly updated
   - Ensure middleware doesn't run on password change page

### Debug Commands
```bash
# Check middleware registration
php artisan about

# Test user creation
php artisan tinker
>>> $user = User::create([
...     'name' => 'Test',
...     'phone' => '+251912345678',
...     'password' => Hash::make('temp123'),
...     'temp_password_changed' => false
... ]);

# Test middleware
php artisan tinker
>>> $user = User::first();
>>> $user->temp_password_changed;
```

## Security Considerations

### Password Security
- **Strong Requirements**: Enforce minimum 8 characters with complexity
- **History Prevention**: Track last 3 passwords to prevent reuse
- **Secure Storage**: All passwords stored as bcrypt hashes
- **Current Password**: Require verification for password changes

### Session Security
- **Intended URL**: Preserve user's destination securely
- **Logout Access**: Always allow users to log out
- **Route Protection**: Middleware protects all authenticated routes

### Best Practices
- **Temporary Passwords**: Use strong temporary passwords
- **Expiration**: Consider setting expiration for temporary passwords
- **Notifications**: Consider email/SMS notifications for password changes
- **Audit Trail**: Log password changes for security auditing

## Future Enhancements

### Planned Features
- **Password Expiration**: Automatic password expiration policies
- **Two-Factor Auth**: Integration with 2FA systems
- **Email Notifications**: Password change confirmations
- **Admin Override**: Allow administrators to force password changes

### Integration Options
- **LDAP Integration**: External authentication systems
- **SSO Providers**: Single sign-on compatibility
- **Mobile Apps**: API endpoints for mobile applications
- **Password Policies**: Role-based password requirements

The forced password change system provides comprehensive security for temporary passwords while maintaining excellent user experience through intuitive interface and clear visual feedback.
