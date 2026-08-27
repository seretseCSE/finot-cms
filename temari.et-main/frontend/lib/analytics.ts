import posthog from "posthog-js"

import type { User } from "@/lib/types"

/**
 * Thin wrapper over posthog-js — the only place the app talks to analytics.
 * Every function no-ops when NEXT_PUBLIC_POSTHOG_KEY is unset (local dev) or
 * on the server, so callers never guard. Distinct id is the backend user id,
 * which merges the browser session with the events the API captures
 * server-side (App\Services\Analytics uses the same id).
 */

function enabled(): boolean {
  return typeof window !== "undefined" && Boolean(process.env.NEXT_PUBLIC_POSTHOG_KEY)
}

/** Record a product event (dot-namespaced like the backend: "fees.invoice_opened"). */
export function track(event: string, properties?: Record<string, unknown>) {
  if (enabled()) posthog.capture(event, properties)
}

/** Tie this browser to the signed-in account (call whenever the session user loads). */
export function identifyUser(user: User) {
  if (!enabled()) return
  posthog.identify(String(user.id), {
    name: user.name,
    preferred_language: user.preferred_language,
  })
}

/** Forget the person on logout so a shared device never mixes accounts. */
export function resetAnalytics() {
  if (enabled()) posthog.reset()
}

/**
 * Stamp the active workspace on every subsequent event so anything captured
 * client-side slices by school/branch, mirroring the backend's group keys.
 */
export function setAnalyticsWorkspace(schoolId: number | null, branchId: number | null) {
  if (!enabled()) return
  posthog.register({ school_id: schoolId, branch_id: branchId })
  if (schoolId) posthog.group("school", `school:${schoolId}`)
  if (branchId) posthog.group("branch", `branch:${branchId}`)
}

/** Report a handled error (error boundaries, catch blocks worth alerting on). */
export function captureError(error: unknown, properties?: Record<string, unknown>) {
  if (enabled()) posthog.captureException(error, properties)
}
