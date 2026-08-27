"use client"

import { useMemo } from "react"

import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useSharedData } from "@/lib/shared-data"

/**
 * Shared, always-fresh reference LIST for dropdowns and filters (academic
 * years, terms, sections, subjects…). Drop-in replacement for the
 * `useEffect + apiFetch + useState` boilerplate:
 *
 *   const { items: years } = useRefList<AcademicYear>("/academic-years")
 *   const { items: terms } = useRefList<Term>("/terms?per_page=100")
 *
 * What it adds over an inline fetch:
 *  - One request per workspace × path serves every consumer for 30s — a page
 *    whose filters, sheet and wizard all need years costs one call.
 *  - Cached results render instantly (no flicker) and revalidate silently.
 *  - Any successful mutation on the same resource (`POST /academic-years`,
 *    `PATCH /terms/5`, …) auto-invalidates it everywhere — create a semester
 *    in one screen and every open picker updates without a reload.
 *
 * The key is scoped by the active workspace (the backend scopes by the
 * X-School-Id / X-Branch-Id headers) so a context switch never leaks data.
 * Pass `path: null` to disable (e.g. while a parent filter is unset).
 */
export function useRefList<T>(path: string | null, options: { enabled?: boolean } = {}) {
  const { enabled = true } = options
  const { active } = useSchoolContext()

  const resource = path ? path.replace(/^\//, "").split(/[/?#]/, 1)[0] : null
  const key =
    path && enabled ? `ref:${active.schoolId ?? ""}:${active.branchId ?? ""}:${path}` : null

  const { data, loading, refresh } = useSharedData<T[]>(
    key,
    // Works for both `{ data: T[] }` and paginated `{ data: T[], meta }`.
    () => apiFetch<{ data: T[] }>(path as string).then((r) => r.data),
    { tags: resource ? [resource] : [] },
  )

  const items = useMemo(() => data ?? [], [data])

  return { items, loading, refresh }
}
