# Active Session Tracking System

## Overview

The FINOTE CMS now includes a comprehensive active session tracking system that monitors and manages user sessions across multiple devices.

## Features

### ✅ **Core Functionality**
- **Session Recording**: Automatically tracks user logins with device info and IP addresses
- **Device Limit**: Enforces maximum 3 concurrent sessions per user
- **Auto Cleanup**: Removes expired sessions (30 minutes timeout)
- **Real-time Updates**: Updates session activity on each request
- **Session Termination**: Allows manual session termination

### ✅ **Security Features**
- **Device Fingerprinting**: Captures browser and OS information
- **IP Tracking**: Records source IP addresses
- **Session Tokens**: Unique tokens for each session
- **Automatic Logout**: Terminates expired sessions

## Database Schema

### `user_sessions` Table
```sql
- id (bigint, primary)
- user_id (bigint, foreign key → users.id)
- session_token (varchar 255, unique)
- device_info (text, nullable) - User agent string
- ip_address (varchar 45, nullable) - Source IP
- last_activity (timestamp) - Last activity time
- created_at, updated_at (timestamps)
```

## Implementation Details

### 📁 **Files Created**

#### Models
- `app/Models/UserSession.php` - Session model with scopes and methods
- `app/Models/Traits/HasUserSessions.php` - User trait for session management

#### Listeners
- `app/Listeners/RecordUserSession.php` - Handles login events
- `app/Listeners/CleanupUserSession.php` - Handles logout events

#### Middleware
- `app/Http/Middleware/SessionTimeoutMiddleware.php` - Updates activity and cleans expired sessions

#### Resources
- `app/Filament/Resources/UserSessionResource.php` - Admin interface for session management
- `app/Filament/Resources/UserSessionResource/Pages/` - Resource pages

#### Migration
- `database/migrations/2026_02_19_054729_create_user_sessions_table.php`

### 🔧 **Configuration**

#### Middleware Registration
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        \App\Http\Middleware\SessionTimeoutMiddleware::class,
    ]);
})
```

#### Event Listeners
```php
// app/Providers/AppServiceProvider.php
\Event::listen(Login::class, RecordUserSession::class);
\Event::listen(Logout::class, CleanupUserSession::class);
```

## Usage Examples

### 📊 **Check User Sessions**
```php
$user = User::find(1);
$activeSessions = $user->activeSessionsCount(); // Returns int
$sessionsInfo = $user->getSessionsInfo(); // Returns array with device info
```

### 🔄 **Session Management**
```php
// Check if user has max sessions
if ($user->hasMaxSessions()) {
    // User has 3+ active sessions
}

// Terminate specific session
$user->terminateSession('session_token_here');

// Terminate all sessions
$user->terminateAllSessions();
```

### 🕐 **Active Session Query**
```php
// Get all active sessions (last 30 minutes)
$activeSessions = UserSession::active()->get();

// Get user's active sessions
$userActiveSessions = UserSession::forUser($userId)->active()->get();
```

## Admin Interface

### 📋 **UserSession Resource**
- **Location**: Security & System → Active Sessions
- **Features**:
  - View all active sessions
  - Filter by user
  - Filter active/expired sessions
  - Terminate individual sessions
  - Bulk session termination
  - Device information display

### 📱 **Device Information**
The system parses user agent strings to display:
- **Browser**: Chrome, Firefox, Safari, Edge
- **OS**: Windows, macOS, Linux, Android, iOS
- **Format**: "Chrome on Windows"

## Security Considerations

### 🔒 **Session Security**
1. **Token-based**: Each session has unique 255-character token
2. **Time-based**: Sessions expire after 30 minutes of inactivity
3. **Device-limited**: Maximum 3 concurrent sessions per user
4. **IP-tracked**: Source IP addresses are recorded
5. **Auto-cleanup**: Expired sessions are automatically removed

### 🛡️ **Protection Against**
- Session hijacking (unique tokens)
- Account sharing (device limits)
- Stale sessions (timeout cleanup)
- Unauthorized access (IP tracking)

## Testing

### 🧪 **Test Command**
```bash
php artisan app:test-session-tracking
```

The test command verifies:
- Database table exists
- Models are working
- Event listeners are registered
- Middleware is active
- Cleanup mechanism functions

## Performance

### ⚡ **Optimizations**
- **Database Indexes**: 
  - `(user_id, last_activity)` for user session queries
  - `session_token` for session lookups
- **Efficient Queries**: Uses scopes for common operations
- **Bulk Operations**: Cleanup runs on each request

### 📈 **Scalability**
- **Concurrent Users**: Supports unlimited concurrent users
- **Session Storage**: Minimal overhead per session
- **Cleanup**: Automatic prevents database bloat

## Monitoring

### 📊 **Session Statistics**
```php
// Total active sessions
$totalActive = UserSession::active()->count();

// Sessions by user
$userSessions = User::withCount('activeSessions')->get();

// Expired sessions count
$expiredCount = UserSession::where('last_activity', '<', now()->subMinutes(30))->count();
```

## Troubleshooting

### 🔧 **Common Issues**

#### Sessions not recording
- Check event listeners are registered
- Verify middleware is applied
- Ensure User model has HasUserSessions trait

#### Sessions not expiring
- Check SessionTimeoutMiddleware is in web group
- Verify cleanup logic in middleware
- Check server timezone settings

#### Performance issues
- Add database indexes if missing
- Optimize session cleanup frequency
- Consider session table partitioning for large scale

## Future Enhancements

### 🚀 **Potential Improvements**
- **Geolocation**: IP-based location tracking
- **Session Analytics**: Detailed usage patterns
- **Alert System**: Notifications for suspicious activity
- **Session Sharing**: Allow users to name their sessions
- **Mobile App**: Native app session management

---

**Status**: ✅ **Fully Implemented and Tested**

The active session tracking system is now ready for production use in the FINOTE church management system.
