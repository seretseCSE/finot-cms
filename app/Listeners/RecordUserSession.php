<?php

namespace App\Listeners;

use App\Models\UserSession;
use Illuminate\Auth\Events\Login;

class RecordUserSession
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;
        $request = request();

        // Use Laravel's session ID for consistency with TrackUserSessions middleware
        $sessionToken = session()->getId();

        // Store session token in session for later use
        session(['session_token' => $sessionToken]);

        // Check if this session token already exists (prevents unique constraint violation)
        $existingSession = UserSession::where('session_token', $sessionToken)->first();
        if ($existingSession) {
            $existingSession->update([
                'user_id' => $user->id,
                'device_info' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'last_activity' => now(),
            ]);

            return;
        }

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
