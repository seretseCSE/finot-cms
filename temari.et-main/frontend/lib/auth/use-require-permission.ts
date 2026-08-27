"use client"

import { useRouter } from "next/navigation"
import { useEffect } from "react"

import { useAuth } from "./auth-context"
import { useEffectivePermissions } from "./use-effective-permissions"

type Mode = "any" | "all"

/**
 * Client-side route guard. Redirects to /unauthorized when the signed-in user
 * lacks the required permission(s). Returns the resolved state so a page can
 * hold its render until access is confirmed, avoiding a flash of protected
 * content before the redirect fires.
 *
 * Backend policies remain the source of truth for authorization — this only
 * mirrors them so that direct URL navigation lands on the Unauthorized page
 * instead of a broken view that immediately errors out on its data fetch.
 *
 * `mode` "any" (default) authorizes when the user holds at least one of the
 * listed permissions; "all" requires every one of them.
 *
 * Authorization is judged against the permissions effective in the ACTIVE
 * school/branch context (see useEffectivePermissions), so visiting a page by URL
 * that the user's current role can't access — e.g. a director opening /branches
 * while operating in their branch — redirects to /unauthorized, matching the
 * backend's context-scoped policies.
 */
export function useRequirePermission(
  required: string | string[],
  mode: Mode = "any",
): { authorized: boolean; loading: boolean } {
  const { user, loading } = useAuth()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const perms = Array.isArray(required) ? required : [required]
  const authorized =
    !!user &&
    (mode === "all"
      ? perms.every((p) => permissions.includes(p))
      : perms.some((p) => permissions.includes(p)))

  useEffect(() => {
    // AppShell already handles the unauthenticated case (redirect to /login);
    // here we only bounce an authenticated user who lacks the permission.
    if (!loading && user && !authorized) {
      router.replace("/unauthorized")
    }
  }, [loading, user, authorized, router])

  return { authorized, loading }
}
