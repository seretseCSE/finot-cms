<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and VALIDATES the active school/branch context for the request from
 * the X-School-Id / X-Branch-Id headers. Both halves are verified together:
 *
 *  - the school must be one the user is related to (platform staff: any school);
 *  - the branch must actually belong to that school AND be one the user may
 *    operate in (platform staff / school manager / branch member).
 *
 * Anything that fails validation resolves to null — never to a guessed value —
 * so a forged header can only ever REDUCE what the request may do (the kernel
 * is deny-by-default with no context).
 */
class SetActiveContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $schoolId = $request->hasHeader('X-School-Id') ? (int) $request->header('X-School-Id') : null;
        $branchId = $request->hasHeader('X-Branch-Id') ? (int) $request->header('X-Branch-Id') : null;

        $schoolAllowed = $schoolId !== null
            && ($user->isPlatformUser() || $user->relatedToSchool($schoolId));

        $branchAllowed = false;

        if ($schoolAllowed && $branchId !== null) {
            $branch = Branch::find($branchId);

            $branchAllowed = $branch !== null
                && (int) $branch->school_id === $schoolId
                && ($user->isPlatformUser() || $user->canAccessBranch($branch));
        }

        $user->setActiveContext(
            $schoolAllowed ? $schoolId : null,
            $branchAllowed ? $branchId : null,
        );

        return $next($request);
    }
}
