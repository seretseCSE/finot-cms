<?php

namespace App\Listeners;

use App\Models\UserSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordUserSession
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $request = request();
        
        // Generate unique session token
        $sessionToken = Str::random(255);
        
        // Store session token in session for later use
        session(['session_token' => $sessionToken]);
        
        // Check user's active sessions count
        $activeSessions = UserSession::forUser($user->id)
            ->active()
            ->count();
        
        // If user has 3 or more active sessions, delete the oldest one
        if ($activeSessions >= 3) {
            $oldestSession = UserSession::forUser($user->id)
                ->active()
                ->orderBy('last_activity', 'asc')
                ->first();
                
            if ($oldestSession) {
                $oldestSession->delete();
            }
        }
        
        // Create new session record
        UserSession::create([
            'user_id' => $user->id,
            'session_token' => $sessionToken,
            'device_info' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'last_activity' => now(),
        ]);
    }
}
