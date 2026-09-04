"use client"

import { useMemo } from "react"

import { useAuth } from "./auth-context"
import { useSchoolContext } from "./school-context"

/** Kept in step with User::DIRECTOR_FINANCE_GATED on the backend. */
const DIRECTOR_FINANCE_GATED = new Set([
  "fees.manage",
  "payments.record",
  "finance.books.view",
  "finance.books.manage",
  "finance.books.approve",
])

/**
 * The permissions effective in the user's ACTIVE school/branch context, derived
 * from the roles of the memberships that apply there. This powers context-aware
 * navigation: a user who is a director in one branch and a principal in another
 * sees only the director's menu while that branch is active, and the principal's
 * menu after switching schools — instead of the union of every role they hold.
 *
 * Platform staff keep their full global permission set. The backend remains the
 * source of truth for data access; this only shapes what the UI offers.
 */
export function useEffectivePermissions(): string[] {
  const { user } = useAuth()
  const { active, isPlatform } = useSchoolContext()

  return useMemo(() => {
    if (!user) return []

    // Temari.et staff operate across everything — keep the global union.
    if (isPlatform) return user.permissions

    // Before a concrete context resolves (initial hydration), fall back to the
    // global union so the nav isn't briefly empty.
    if (active.schoolId === null && active.branchId === null) return user.permissions

    // Defensive: a stale /auth/me payload without the role→permission map would
    // otherwise yield an empty nav. Degrade to the previous (global) behaviour.
    const map = user.role_permissions
    if (!map || Object.keys(map).length === 0) return user.permissions

    const effective = new Set<string>()

    for (const m of user.memberships ?? []) {
      if (!m.is_active) continue

      // A branch membership applies when that branch is active. A school-level
      // membership (principal / school_admin, branch_id null) applies to any
      // context within its school — including drilling into one of its branches.
      const appliesToBranch = m.branch_id !== null && m.branch_id === active.branchId
      const appliesToSchool =
        m.branch_id === null && m.school_id !== null && m.school_id === active.schoolId

      if (appliesToBranch || appliesToSchool) {
        // Director finance gate: mirrors the backend kernel — the director
        // role carries no money permissions unless the school enabled it.
        const gated = m.role === "director" && m.director_finance_access === false

        for (const permission of map[m.role] ?? []) {
          if (gated && DIRECTOR_FINANCE_GATED.has(permission)) continue
          effective.add(permission)
        }
      }
    }

    return [...effective]
  }, [user, active, isPlatform])
}
