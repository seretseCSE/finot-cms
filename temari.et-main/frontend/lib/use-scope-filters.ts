"use client"

import { useCallback, useMemo, useState } from "react"

import type { DataTableFilter } from "@/components/ui/data-table"
import { useContextsResponse, useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { ContextSchool } from "@/lib/types"

/**
 * The cascading School → Branch narrowing filters every scoped list table
 * shares: Temari platform staff first pick a school, then one of ITS
 * branches; school managers working in the All-branches workspace get a
 * branch filter for their own school. Inside a concrete branch workspace the
 * context already narrows everything, so no filters render.
 *
 * Pair with `useServerTable`: spread the returned defs into the page's
 * `filters` array — the backend list endpoints accept the matching
 * `school_id` / `branch_id` params.
 */
export function useScopeFilters(activeFilters: Record<string, string>): DataTableFilter[] {
  const { t } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()

  const inBranchContext = active.branchId != null
  const wantsFilters = !inBranchContext && (isPlatform || active.schoolId != null)

  // Shared, auto-refreshing contexts source — one request app-wide, and a
  // just-created school/branch appears in the filters without a reload.
  const { data: contextsData } = useContextsResponse(wantsFilters)
  const schools: ContextSchool[] = useMemo(() => contextsData?.schools ?? [], [contextsData])

  return useMemo(() => {
    if (!wantsFilters || schools.length === 0) return []

    const defs: DataTableFilter[] = []

    // School managers only ever see their own school — skip the school step.
    const school =
      !isPlatform && active.schoolId != null
        ? (schools.find((s) => s.id === active.schoolId) ?? null)
        : schools.length === 1
          ? schools[0]
          : (schools.find((s) => String(s.id) === activeFilters["school_id"]) ?? null)

    const hasSchoolStep = isPlatform && schools.length > 1
    if (hasSchoolStep) {
      defs.push({
        key: "school_id",
        label: t("filters.school"),
        options: schools.map((s) => ({ label: s.name, value: String(s.id) })),
        serverOnly: true,
      })
    }

    // The branch step appears once a school is unambiguous. When a school
    // step renders, the branch cascades from it (dependsOn) so clearing or
    // switching the school also clears a stale branch selection.
    if (school && school.branches.length > 1) {
      defs.push({
        key: "branch_id",
        label: t("filters.branch"),
        options: school.branches.map((b) => ({ label: b.name, value: String(b.id) })),
        serverOnly: true,
        ...(hasSchoolStep ? { dependsOn: "school_id" } : {}),
      })
    }

    return defs
  }, [wantsFilters, schools, isPlatform, active.schoolId, activeFilters, t])
}

/**
 * The same School → Branch narrowing for CLIENT-mode tables (small lists
 * loaded in one request). The page owns one controlled `filterValues` state
 * for the whole DataTable; this wraps it with the scope defs (marked
 * `serverOnly` so client-side row matching skips them) plus the query-string
 * fragment to append to the fetch URL — changing a scope filter refetches
 * server-side instead of hiding rows the client never loaded.
 *
 * Usage:
 *   const scope = useScopeQuery()
 *   apiFetch(`/terms?per_page=100${scope.params}`)  // refetch on scope.key
 *   <DataTable filters={[...scope.filters, ...defs]}
 *              filterValues={scope.values} onFilterChange={scope.setFilter} />
 */
export function useScopeQuery(external?: {
  values: Record<string, string>
  setFilter: (key: string, value: string) => void
}) {
  const [internal, setInternal] = useState<Record<string, string>>({})

  const internalSetFilter = useCallback(
    (key: string, value: string) => setInternal((prev) => ({ ...prev, [key]: value })),
    [],
  )

  const values = external?.values ?? internal
  const setFilter = external?.setFilter ?? internalSetFilter

  const filters = useScopeFilters(values)

  // Only the first (comma-joined) selection travels — scope narrowing is a
  // single-choice lens, and the backend expects one id per param.
  const schoolId = (values["school_id"] ?? "").split(",")[0] ?? ""
  const branchId = (values["branch_id"] ?? "").split(",")[0] ?? ""

  const params = useMemo(() => {
    let p = ""
    if (schoolId) p += `&school_id=${schoolId}`
    if (branchId) p += `&branch_id=${branchId}`
    return p
  }, [schoolId, branchId])

  return {
    /** Scope filter defs — spread FIRST into the DataTable `filters` array. */
    filters,
    /** Controlled filter values — pass as DataTable `filterValues`. */
    values,
    /** Controlled filter setter — pass as DataTable `onFilterChange`. */
    setFilter,
    /** `&school_id=…&branch_id=…` fragment for the list fetch URL. */
    params,
    /** Refetch dependency — changes whenever the scope selection changes. */
    key: params,
  }
}
