<?php

namespace App\Ai\Tools\Platform;

use App\Ai\Tools\AiTool;
use App\Models\AiSubscription;
use App\Models\Branch;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Temari.et platform lane: the business at a glance. Platform staff only.
 */
class PlatformOverviewTool extends AiTool
{
    public function description(): Stringable|string
    {
        return 'Platform overview: schools/branches on Temari, active student enrollments, user counts, schools with an active AI School Plan, and active B2C AI subscriptions. Use for platform growth or adoption questions.';
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->context->user->isPlatformUser()) {
            return $this->deny('Platform staff only.');
        }

        return $this->ok([
            'schools' => [
                'total' => School::query()->count(),
                'active' => School::query()->where('is_active', true)->count(),
                'with_active_ai_plan' => School::query()->whereDate('ai_plan_until', '>=', today())->count(),
            ],
            'branches' => Branch::query()->where('is_active', true)->count(),
            'active_enrollments' => StudentEnrollment::query()
                ->whereIn('status', ['pending', 'active'])
                ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
                ->count(),
            'users' => User::query()->count(),
            'active_ai_subscriptions' => AiSubscription::query()
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->count(),
            'approved_tutors' => TutorProfile::query()->where('status', 'approved')->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
