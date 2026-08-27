<?php

namespace App\Services\Ai;

use App\Enums\AiLane;
use App\Models\AiSubscription;
use App\Models\School;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The one answer to "may this user talk to the AI, and how much?" —
 * mirroring the monetization contract (CLAUDE.md §11):
 *
 *  - Family lanes (student/parent): the B2C AI upgrade (199 ETB/mo through
 *    the gateway) = `premium`; without it a small free daily teaser.
 *  - Staff lanes: the school's School Plan (schools.ai_plan_until) = `school`;
 *    without it a tiny `staff_free` teaser (the upsell hook).
 *  - Platform staff: unlimited.
 *
 * Frontends read this via GET /ai/entitlement; the chat endpoint enforces it
 * server-side on every prompt. Pricing itself never leaves config.
 */
class AiEntitlementService
{
    public function __construct(private readonly AiUsageService $usage) {}

    /**
     * @return array{plan: string, daily_limit: int, used_today: int, remaining: int|null, subscription_ends_at: string|null, school_plan_until: string|null}
     */
    public function resolve(User $user, AiLane $lane, ?School $school): array
    {
        $subscriptionEnd = null;
        $schoolPlanUntil = null;

        if ($user->isPlatformUser() || $lane === AiLane::Platform) {
            $plan = 'platform';
        } elseif ($lane->isFamilyLane()) {
            $subscriptionEnd = AiSubscription::query()
                ->activeFor($user->id)
                ->max('ends_at');
            $plan = $subscriptionEnd !== null ? 'premium' : 'free';
        } else {
            $schoolPlanUntil = $school?->ai_plan_until?->toDateString();
            $plan = ($school !== null && $school->aiPlanActive()) ? 'school' : 'staff_free';
        }

        $limit = (int) config('temari-ai.quotas.'.$plan, 0);
        $used = $this->usage->messagesUsedToday($user);

        return [
            'plan' => $plan,
            'daily_limit' => $limit,
            'used_today' => $used,
            'remaining' => $limit < 0 ? null : max(0, $limit - $used),
            'subscription_ends_at' => $subscriptionEnd !== null ? (string) $subscriptionEnd : null,
            'school_plan_until' => $schoolPlanUntil,
        ];
    }

    /**
     * Gate a prompt. 402 = no entitlement at all (feature off for the plan),
     * 429 = entitled but today's quota is spent — the frontend maps both to
     * the upgrade/limit surfaces.
     */
    public function assertCanPrompt(User $user, AiLane $lane, ?School $school): array
    {
        $entitlement = $this->resolve($user, $lane, $school);

        if ($entitlement['daily_limit'] === 0) {
            throw new HttpException(402, 'An AI subscription is required.');
        }

        if ($entitlement['remaining'] !== null && $entitlement['remaining'] <= 0) {
            throw new HttpException(429, 'Daily AI limit reached.');
        }

        return $entitlement;
    }
}
