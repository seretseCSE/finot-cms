<?php

namespace App\Models\Traits;

use App\Models\UserSession;

trait HasUserSessions
{
    /**
     * Get the user's active sessions.
     */
    public function activeSessions()
    {
        return $this->hasMany(UserSession::class)->active();
    }

    /**
     * Get the user's total sessions count.
     */
    public function sessionsCount()
    {
        return $this->hasMany(UserSession::class)->count();
    }

    /**
     * Get the user's active sessions count.
     */
    public function activeSessionsCount()
    {
        return $this->activeSessions()->count();
    }

    /**
     * Check if user has reached maximum allowed sessions.
     */
    public function hasMaxSessions(): bool
    {
        return $this->activeSessionsCount() >= 3;
    }

    /**
     * Get the oldest active session for the user.
     */
    public function oldestActiveSession()
    {
        return $this->activeSessions()->orderBy('last_activity', 'asc')->first();
    }

    /**
     * Terminate all user sessions.
     */
    public function terminateAllSessions(): void
    {
        $this->hasMany(UserSession::class)->delete();
    }

    /**
     * Terminate a specific session.
     */
    public function terminateSession(string $sessionToken): bool
    {
        return $this->hasMany(UserSession::class)
            ->where('session_token', $sessionToken)
            ->delete() > 0;
    }

    /**
     * Get session information for display.
     */
    public function getSessionsInfo(): array
    {
        return $this->activeSessions()
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'device_info' => $this->parseDeviceInfo($session->device_info),
                    'ip_address' => $session->ip_address,
                    'last_activity' => $session->last_activity->diffForHumans(),
                    'is_current' => $session->session_token === session('session_token'),
                ];
            })
            ->toArray();
    }

    /**
     * Parse device info for better display.
     */
    private function parseDeviceInfo(?string $deviceInfo): string
    {
        if (!$deviceInfo) {
            return 'Unknown Device';
        }

        // Extract browser and OS from user agent
        $browser = 'Unknown Browser';
        $os = 'Unknown OS';

        // Simple regex patterns for common browsers and OS
        if (preg_match('/Chrome\/[\d.]+/', $deviceInfo)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/', $deviceInfo)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/', $deviceInfo)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/[\d.]+/', $deviceInfo)) {
            $browser = 'Edge';
        }

        if (preg_match('/Windows/i', $deviceInfo)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac/i', $deviceInfo)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $deviceInfo)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $deviceInfo)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $deviceInfo)) {
            $os = 'iOS';
        }

        return "{$browser} on {$os}";
    }
}
