<?php

namespace App\Listeners;

use App\Models\UserSession;
use Illuminate\Auth\Events\Logout;

class CleanupUserSession
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        $user = $event->user;
        $sessionToken = session('session_token');
        
        // Delete the current session from database
        if ($user && $sessionToken) {
            UserSession::where('user_id', $user->id)
                ->where('session_token', $sessionToken)
                ->delete();
        }
        
        // Clear session token from session
        session()->forget('session_token');
    }
}
