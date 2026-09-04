<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\QuestionRules;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Sanitized WYSIWYG rich text (descriptions, lesson bodies) — the same
     * pipeline as question stems/exam instructions. Null when empty.
     */
    protected function cleanRichText(?string $html): ?string
    {
        if ($html === null || (trim(strip_tags($html)) === '' && ! str_contains($html, '<img'))) {
            return null;
        }

        return QuestionRules::normalizeStemMedia(QuestionRules::sanitizeStem($html));
    }

    /**
     * Resolve the branch a branch-anchored request acts on. An explicit
     * `branch_id` (body or query) wins — school managers operate on any of
     * their branches straight from the school-wide workspace, no context
     * switch — otherwise the validated X-Branch-Id context applies. Aborts
     * with 422 when neither names a branch.
     *
     * Scope guard: non-platform users may only target branches of their
     * ACTIVE school. This only resolves WHICH branch; the caller's
     * hasPermissionForScope() against the returned branch stays the authority
     * gate (deny-by-default), so a forged id never grants beyond memberships.
     */
    protected function targetBranch(Request $request): Branch
    {
        $user = $request->user();

        $branchId = $request->filled('branch_id')
            ? (int) $request->input('branch_id')
            : $user?->activeBranchId();

        if ($branchId === null) {
            throw new HttpException(422, 'Select a branch to continue.');
        }

        $branch = Branch::findOrFail($branchId);

        if (! $user?->isPlatformUser() && (int) $branch->school_id !== $user?->activeSchoolId()) {
            throw new HttpException(422, 'Select a branch to continue.');
        }

        return $branch;
    }

    /**
     * Resolve the active branch from context, or null for platform users and
     * school managers operating in the no-branch context. Use this for list
     * endpoints that should show all data across all branches for such staff.
     */
    protected function activeBranchOrNull(Request $request): ?Branch
    {
        $user = $request->user();
        $branchId = $user?->activeBranchId();

        if ($branchId !== null) {
            return Branch::findOrFail($branchId);
        }

        if ($user?->isPlatformUser()) {
            return null;
        }

        $schoolId = $user?->activeSchoolId();
        if ($schoolId !== null && $user?->managesSchool($schoolId)) {
            return null;
        }

        throw new HttpException(422, 'Select a branch to continue.');
    }

    /**
     * Optional branch narrowing for school-wide lists: with no branch context
     * active, an explicit `branch_id` query param narrows the (already
     * school-scoped) list to one branch — how the school-wide workspace loads
     * options for a chosen target branch. Null in a concrete branch context
     * (the context already scopes) or when no filter was sent.
     */
    protected function branchFilterId(Request $request, ?Branch $branch): ?int
    {
        if ($branch !== null || ! $request->filled('branch_id')) {
            return null;
        }

        return $request->integer('branch_id');
    }

    /**
     * Platform-only school narrowing for cross-school lists: with no school
     * scope of their own, platform staff may pass an explicit `school_id`
     * query param to narrow the list to one school (the school step of the
     * shared School → Branch scope filters). Inert for school staff — their
     * scope already pins a school, so the param can never widen anything.
     */
    protected function schoolFilterId(Request $request, ?Branch $branch): ?int
    {
        if ($branch !== null || $this->activeSchoolScopeId($request) !== null || ! $request->filled('school_id')) {
            return null;
        }

        return $request->integer('school_id');
    }

    /**
     * In a no-branch context, platform staff see every school while school
     * managers see every branch in their active school.
     */
    protected function activeSchoolScopeId(Request $request): ?int
    {
        $user = $request->user();
        $schoolId = $user?->activeSchoolId();

        if ($user?->isPlatformUser()) {
            return null;
        }

        if ($schoolId !== null && $user?->managesSchool($schoolId)) {
            return $schoolId;
        }

        return null;
    }
}
