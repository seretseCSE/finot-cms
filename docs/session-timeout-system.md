# Session Timeout Management System

## Overview
Comprehensive session timeout system that automatically logs out users after 30 minutes of inactivity, with client-side warnings, API exemptions for PWA background sync, and user-friendly notifications.

## Features

### ⏰ Session Management
- **30-minute timeout** after inactivity
- **Activity tracking** on every request
- **Automatic logout** with session cleanup
- **Session extension** on user activity

### 🌐 Client-Side Features
- **Real-time warnings** 5 minutes before timeout
- **Interactive modal** for session extension
- **Activity monitoring** (mouse, keyboard, scroll, touch)
- **Graceful logout** with notifications

### 📱 API Exemptions
- **PWA background sync** endpoints exempt
- **Token-based auth** for API routes
- **JSON request** detection
- **Selective middleware** application

## Implementation Files

### 1. Environment Configuration
**File**: `.env`
```env
SESSION_LIFETIME=30  # Changed from 120 to 30 minutes
```

### 2. SessionActivityMiddleware
**File**: `app/Http/Middleware/SessionActivityMiddleware.php`

**Purpose**: Tracks user activity and enforces session timeout.

**Features**:
- Updates `last_activity` timestamp on each request
- Checks for session expiration (30 minutes)
- Auto-logout with flash message
- API endpoint exemptions
- Route exemptions (login, logout)

**Logic Flow**:
```php
if (Auth::check()) {
    $lastActivity = Session::get('last_activity', $now);
    $inactiveMinutes = $now->diffInMinutes($lastActivityTime);
    
    if ($inactiveMinutes >= sessionLifetime) {
        Auth::logout();
        Session::flush();
        Session::flash('session_expired', 'Session expired message');
        return redirect()->route('filament.admin.login');
    }
    
    Session::put('last_activity', $now->toDateTimeString());
}
```

### 3. SessionTimeoutWarning Component
**Files**:
- `app/View/Components/SessionTimeoutWarning.php` - Component class
- `resources/views/components/session-timeout-warning.blade.php` - Frontend implementation

**Features**:
- **Alpine.js** integration for real-time monitoring
- **Activity detection** (mouse, keyboard, scroll, touch)
- **Warning modal** 5 minutes before timeout
- **Session extension** API calls
- **Auto-logout** handling

### 4. Session Controller
**File**: `app/Http/Controllers/SessionController.php`

**Purpose**: Provides API endpoints for session management.

**Endpoints**:
- `POST /api/session/extend` - Extend current session
- `GET /api/session/status` - Get session status

### 5. Middleware Registration
**Files**:
- `bootstrap/app.php` - Middleware alias registration
- `config/filament.php` - Panel middleware configuration

**Registration**:
```php
// bootstrap/app.php
$middleware->alias([
    'session.activity' => \App\Http\Middleware\SessionActivityMiddleware::class,
]);

// config/filament.php
'middleware' => [
    'web',
    'auth',
    'force.password.change',
    'session.activity',
],
```

## Client-Side Implementation

### Alpine.js Session Manager
```javascript
sessionTimeoutManager({
    sessionLifetime: 30,        // minutes
    warningTime: 5,            // Show warning 5 minutes before
    checkInterval: 30,         // Check every 30 seconds
    lastActivity: timestamp,
    isActive: false,
    warningShown: false,
    timeoutModal: false
})
```

### Activity Monitoring
```javascript
// Track user activity
const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

events.forEach(event => {
    document.addEventListener(event, () => {
        this.updateLastActivity();
    }, true);
});
```

### Session Extension
```javascript
extendSession() {
    fetch('/api/session/extend', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => {
        if (response.ok) {
            this.updateLastActivity();
            this.timeoutModal = false;
        } else {
            this.logoutNow();
        }
    });
}
```

## API Endpoints

### Extend Session
```http
POST /api/session/extend
Content-Type: application/json
Authorization: Bearer {token}

Response:
{
    "success": true,
    "message": "Session extended",
    "last_activity": "2024-02-17 23:45:00",
    "expires_at": "2024-02-18 00:15:00"
}
```

### Session Status
```http
GET /api/session/status
Authorization: Bearer {token}

Response:
{
    "authenticated": true,
    "user_id": 1,
    "last_activity": "2024-02-17 23:00:00",
    "session_lifetime": 30,
    "inactive_minutes": 15,
    "remaining_minutes": 15,
    "expires_at": "2024-02-18 00:00:00",
    "will_timeout_soon": false
}
```

## Middleware Behavior

### Route Exemptions
The middleware automatically skips:
- **API endpoints** (`/api/*` or JSON requests)
- **Unauthenticated users**
- **Login/logout routes** (to prevent redirect loops)

### Session Expiration Logic
```php
$sessionLifetime = config('session.lifetime', 30); // 30 minutes
$inactiveMinutes = $now->diffInMinutes($lastActivityTime);

if ($inactiveMinutes >= $sessionLifetime) {
    // Auto-logout
    Auth::logout();
    Session::flush();
    Session::flash('session_expired', 'Your session has expired...');
    return redirect()->route('filament.admin.login');
}
```

### Activity Tracking
```php
// Update last activity on each request
Session::put('last_activity', $now->toDateTimeString());
```

## Frontend Features

### Warning Modal
```html
<div x-show="timeoutModal" class="fixed inset-0 z-50">
    <div class="bg-white rounded-lg shadow-xl">
        <h3>Session Timeout Warning</h3>
        <p>Your session will expire in <span x-text="remainingMinutes"></span> minutes.</p>
        <button @click="extendSession()">Stay Logged In</button>
        <button @click="logoutNow()">Log Out Now</button>
    </div>
</div>
```

### Session Expired Notification
```html
<div x-show="sessionExpired" class="fixed top-4 right-4 bg-yellow-50 border border-yellow-200 rounded-md">
    <div class="flex">
        <svg class="h-5 w-5 text-yellow-400">...</svg>
        <div class="ml-3">
            <p class="text-sm font-medium text-yellow-800">Session Expired</p>
            <p class="text-sm text-yellow-700">Your session has expired due to inactivity.</p>
        </div>
    </div>
</div>
```

## Usage Examples

### Basic Setup
```php
// Component usage in Filament layout
<x-session-timeout-warning />

// Middleware is automatically applied to Filament panel
```

### API Integration for PWA
```javascript
// PWA background sync (exempt from timeout)
fetch('/api/sync/data', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    }
});
```

### Manual Session Extension
```javascript
// Extend session programmatically
fetch('/api/session/extend', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
});
```

## Configuration

### Environment Variables
```env
SESSION_LIFETIME=30          # Session timeout in minutes
SESSION_DRIVER=database      # Session storage
```

### Middleware Registration
```php
// bootstrap/app.php
$middleware->alias([
    'session.activity' => \App\Http\Middleware\SessionActivityMiddleware::class,
]);

// config/filament.php
'middleware' => [
    'web',
    'auth',
    'session.activity', // Applied to all Filament routes
],
```

### Customization Options
```php
// Customize warning time and check interval
$sessionLifetime = config('session.lifetime', 30);
$warningTime = 5; // Show warning 5 minutes before
$checkInterval = 30; // Check every 30 seconds
```

## Security Considerations

### Session Security
- **Automatic cleanup** on timeout
- **Activity tracking** prevents session hijacking
- **Secure logout** with session flush
- **CSRF protection** for API calls

### API Security
- **Authentication required** for session endpoints
- **Token-based auth** for PWA background sync
- **Request validation** and error handling
- **Rate limiting** considerations

### Best Practices
- **HTTPS** for all authenticated requests
- **Secure session storage** (database)
- **Regular activity updates** to prevent timeout
- **User-friendly notifications** for better UX

## Testing

### Middleware Tests
```php
public function testSessionTimeout()
{
    $user = User::factory()->create();
    
    // Simulate session timeout
    session(['last_activity' => now()->subMinutes(31)]);
    
    $response = $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertRedirect('/admin/login');
    
    $this->assertSessionHas('session_expired');
}

public function testApiExemption()
{
    $user = User::factory()->create();
    
    // API endpoint should not trigger timeout
    $response = $this->actingAs($user)
        ->post('/api/session/extend')
        ->assertOk();
}
```

### Frontend Tests
```javascript
// Test session timeout warning
describe('Session Timeout', () => {
    it('shows warning 5 minutes before timeout', () => {
        cy.clock();
        cy.visit('/admin/dashboard');
        
        // Simulate 25 minutes of inactivity
        cy.tick(25 * 60 * 1000);
        
        // Should show warning modal
        cy.get('[x-show="timeoutModal"]').should('be.visible');
    });
});
```

## Troubleshooting

### Common Issues

1. **Session Not Timing Out**
   - Check `SESSION_LIFETIME` in `.env`
   - Verify middleware registration
   - Ensure middleware is applied to routes

2. **Warning Not Showing**
   - Check Alpine.js initialization
   - Verify component is included in layout
   - Check browser console for JavaScript errors

3. **API Endpoints Blocked**
   - Verify API exemptions in middleware
   - Check route registration
   - Ensure authentication headers are present

4. **Session Not Extending**
   - Check `/api/session/extend` endpoint
   - Verify CSRF token is present
   - Check network requests in browser dev tools

### Debug Commands
```bash
# Check session configuration
php artisan config:show session

# Test middleware registration
php artisan about

# Check current session
php artisan tinker
>>> session()->get('last_activity')
>>> session()->get('session_expired')
```

## Performance Considerations

### Database Sessions
- **Session cleanup** for expired sessions
- **Index optimization** on session table
- **Regular maintenance** for session storage

### Client-Side Performance
- **Efficient event listeners** (throttled)
- **Minimal DOM manipulation**
- **Memory cleanup** on component destroy

### API Performance
- **Lightweight endpoints** for session management
- **Caching considerations** for session status
- **Rate limiting** for abuse prevention

## Future Enhancements

### Planned Features
- **Customizable timeout** per user role
- **Session analytics** and reporting
- **Multi-device session** management
- **Push notifications** for session warnings

### Integration Options
- **WebSocket support** for real-time updates
- **Service Worker** integration for PWA
- **Mobile app** session synchronization
- **LDAP integration** for enterprise auth

The session timeout system provides comprehensive security with excellent user experience through intelligent activity tracking, timely warnings, and graceful handling of session expiration.
