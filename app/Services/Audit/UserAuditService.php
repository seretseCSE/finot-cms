<?php

namespace App\Services\Audit;

use App\Models\User;
use Illuminate\Support\Facades\Request;

class UserAuditService
{
    /**
     * Log a failed login attempt to the audit channel.
     */
    public function logFailedLogin(User $user, string $event, array $context = []): void
    {
        $logData = [
            'event' => $event,
            'user_id' => $user->id,
            'phone' => $user->phone,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'timestamp' => now()->toDateTimeString(),
            'failed_attempts' => $user->failed_login_attempts,
            'is_locked' => $user->is_locked,
            'locked_until' => $user->locked_until?->toDateTimeString(),
        ];

        $logData = array_merge($logData, $context);

        logger()->channel('audit')->warning('Failed login attempt', $logData);
    }
}
