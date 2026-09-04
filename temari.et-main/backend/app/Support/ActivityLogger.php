<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Analytics\Analytics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Thin helper for writing to the unified activity_logs table. Keeps controllers
 * and actions free of logging boilerplate. Every audit entry is mirrored to
 * product analytics (Analytics::capture) so "who did what when" is queryable
 * in PostHog without a second call site.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        ?User $actor,
        string $action,
        ?Model $subject = null,
        array $properties = [],
        ?int $schoolId = null,
        ?int $branchId = null,
    ): ActivityLog {
        Analytics::capture($actor, $action, [
            ...$properties,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
        ], $schoolId, $branchId);

        return ActivityLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'properties' => $properties ?: null,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
