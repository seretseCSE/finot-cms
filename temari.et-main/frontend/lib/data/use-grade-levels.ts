"use client"

import { useMemo } from "react"

import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { invalidateShared, useSharedData } from "@/lib/shared-data"
import type { GradeLevel } from "@/lib/types"

/**
 * Shared grade-level source. `/grade-levels` is scoped server-side to the
 * active workspace (branch → its grade × program offering; school-wide → the
 * union across branches; platform → the full national ladder), so every filter
 * and form using this hook automatically shows only the grades the branch
 * actually offers. Pass `all: true` for surfaces that genuinely need the full
 * ladder (the branch editor itself, platform catalog studio).
 *
 * Backed by the shared stale-while-revalidate store: one request per
 * workspace serves every consumer, and any mutation touching branches or the
 * offering auto-invalidates the `grade-levels` tag — no reload needed.
 */
export function bustGradeLevels() {
  invalidateShared("grade-levels")
}

export function useGradeLevels(options: { all?: boolean; enabled?: boolean } = {}) {
  const { all = false, enabled = true } = options
  const { active } = useSchoolContext()
  const key = all ? "grade-levels:all" : `grade-levels:${active.schoolId ?? ""}:${active.branchId ?? ""}`

  const { data, loading } = useSharedData<GradeLevel[]>(
    enabled ? key : null,
    () =>
      apiFetch<{ data: GradeLevel[] }>(all ? "/grade-levels?all=1" : "/grade-levels").then(
        (r) => r.data,
      ),
    { tags: ["grade-levels"] },
  )

  const grades = useMemo(() => data ?? [], [data])

  return { grades, loading }
}
