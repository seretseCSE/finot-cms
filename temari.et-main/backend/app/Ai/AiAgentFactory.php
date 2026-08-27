<?php

namespace App\Ai;

use App\Ai\Agents\ParentAgent;
use App\Ai\Agents\PlatformAgent;
use App\Ai\Agents\StaffAgent;
use App\Ai\Agents\StudentTutorAgent;
use App\Ai\Agents\TemariAgent;
use App\Enums\AiLane;
use App\Enums\AiSurface;
use App\Models\AiConversation;
use App\Models\User;

/**
 * Conversation row → composed agent with its frozen context. The ONLY
 * builder of AiContext for chat: everything (surface, school, branch, focus
 * child) comes from the stored conversation, so a workspace switch
 * mid-session can never widen what the session may see. Staff conversations
 * compose EVERY staff lane the user currently holds in the frozen scope —
 * roles gained/lost since creation grow/shrink the tool set, and each tool
 * still re-checks the kernel per call.
 */
class AiAgentFactory
{
    public function forConversation(AiConversation $conversation, User $user): TemariAgent
    {
        $context = new AiContext(
            user: $user,
            lane: $conversation->lane,
            school: $conversation->school,
            branch: $conversation->branch,
            student: $conversation->lane === AiLane::Student
                ? $user->studentProfile()->with('currentEnrollment.gradeLevel', 'currentEnrollment.section', 'currentEnrollment.branch.school')->first()
                : $conversation->student,
        );

        if ($conversation->lane->surface() === AiSurface::School) {
            $held = array_values(array_filter(
                AiLane::availableFor($user, $conversation->school_id, $conversation->branch_id),
                fn (AiLane $lane): bool => $lane->surface() === AiSurface::School,
            ));

            // The stored primary lane always composes in (matching the old
            // fixed-lane behavior when a role was since revoked — its tools
            // deny gracefully per call).
            if (! in_array($conversation->lane, $held, true)) {
                $held[] = $conversation->lane;
            }

            return new StaffAgent($context, $held);
        }

        return $this->forContext($context);
    }

    public function forContext(AiContext $context): TemariAgent
    {
        return match ($context->lane) {
            AiLane::Student => new StudentTutorAgent($context),
            AiLane::Parent => new ParentAgent($context),
            AiLane::Platform => new PlatformAgent($context),
            default => new StaffAgent($context, [$context->lane]),
        };
    }
}
