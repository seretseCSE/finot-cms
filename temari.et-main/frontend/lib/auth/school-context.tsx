"use client"

import { createContext, useContext, useEffect, useMemo, useState } from "react"

import { setAnalyticsWorkspace } from "@/lib/analytics"
import { apiFetch, getActiveContext, setActiveContext } from "@/lib/api"
import { DEFAULT_CALENDAR_PREFS, setCalendarPrefs } from "@/lib/dates"
import { useSharedData } from "@/lib/shared-data"
import type { ContextOption, ContextsResponse } from "@/lib/types"

import { useAuth } from "./auth-context"

/**
 * The shared, always-fresh source of the user's switchable workspaces
 * (`/auth/contexts`). Every consumer (the workspace switcher, BranchField,
 * scope filters, device pickers…) reads THIS hook so they share one request —
 * and when a school or branch is created/renamed/deactivated anywhere in the
 * app, the mutation auto-invalidates the `contexts` tag and they all update
 * without a reload.
 */
export function useContextsResponse(enabled = true) {
  return useSharedData<ContextsResponse>(
    enabled ? "contexts" : null,
    () => apiFetch<{ data: ContextsResponse }>("/auth/contexts").then((r) => r.data),
    { tags: ["contexts"] },
  )
}

interface ActiveContext {
  schoolId: number | null
  branchId: number | null
}

interface SchoolContextValue {
  /** All contexts the user may switch into (platform / school / branch). */
  options: ContextOption[]
  active: ActiveContext
  activeOption: ContextOption | null
  isPlatform: boolean
  switchTo: (option: ContextOption) => void
}

const SchoolContext = createContext<SchoolContextValue | null>(null)

function buildOptions(data: ContextsResponse): ContextOption[] {
  const options: ContextOption[] = []

  if (data.is_platform) {
    return [
      {
        id: "platform",
        schoolId: null,
        branchId: null,
        schoolName: null,
        branchName: null,
      },
    ]
  }

  for (const school of data.schools) {
    if (school.can_manage) {
      options.push({
        id: `s${school.id}`,
        schoolId: school.id,
        branchId: null,
        schoolName: school.name,
        branchName: null,
        schoolLogoUrl: school.logo_url ?? null,
        calendarMode: school.calendar_mode ?? DEFAULT_CALENDAR_PREFS.calendar,
        clockMode: school.clock_mode ?? DEFAULT_CALENDAR_PREFS.clock,
      })
    }
    for (const branch of school.branches) {
      options.push({
        id: `s${school.id}-b${branch.id}`,
        schoolId: school.id,
        branchId: branch.id,
        schoolName: school.name,
        branchName: branch.name,
        schoolLogoUrl: school.logo_url ?? null,
        calendarMode: branch.calendar_mode ?? school.calendar_mode ?? DEFAULT_CALENDAR_PREFS.calendar,
        clockMode: branch.clock_mode ?? school.clock_mode ?? DEFAULT_CALENDAR_PREFS.clock,
      })
    }
  }

  return options
}

export function SchoolProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth()
  const [active, setActive] = useState<ActiveContext>({ schoolId: null, branchId: null })

  // Hydrate the active context from localStorage on the client.
  useEffect(() => {
    const stored = getActiveContext()
    // eslint-disable-next-line react-hooks/set-state-in-effect -- client-only storage hydration
    setActive({
      schoolId: stored.schoolId ? Number(stored.schoolId) : null,
      branchId: stored.branchId ? Number(stored.branchId) : null,
    })
  }, [])

  // The switchable contexts come from the shared store: fetched once, kept
  // fresh by mutation auto-invalidation (a new branch/school shows up
  // immediately) and by foreground revalidation.
  const { data: contextsData } = useContextsResponse(!!user)
  const isPlatform = contextsData?.is_platform ?? false
  const options = useMemo(
    () => (contextsData ? buildOptions(contextsData) : []),
    [contextsData],
  )

  // Default to the first concrete branch context when nothing valid is set,
  // so branch-scoped pages work immediately (e.g. a director's only branch).
  // Re-runs on every refresh, so a deleted/deactivated workspace also falls
  // back instead of leaving the user stranded in a dead context.
  useEffect(() => {
    if (!contextsData || options.length === 0) return
    const stored = getActiveContext()
    const storedBranch = stored.branchId ? Number(stored.branchId) : null
    const storedSchool = stored.schoolId ? Number(stored.schoolId) : null
    const matches = options.some((o) => o.schoolId === storedSchool && o.branchId === storedBranch)
    if (!matches) {
      // Platform users default to the global context (no school/branch filter).
      // Regular users default to their first concrete branch.
      const fallback = contextsData.is_platform
        ? (options.find((o) => o.schoolId === null) ?? options[0])
        : (options.find((o) => o.branchId !== null) ?? options[0])
      if (fallback) {
        setActiveContext(fallback.schoolId, fallback.branchId)
        // eslint-disable-next-line react-hooks/set-state-in-effect -- rare fallback when the stored workspace no longer exists
        setActive({ schoolId: fallback.schoolId, branchId: fallback.branchId })
      }
    }
  }, [contextsData, options])

  const activeOption = useMemo(
    () =>
      options.find((o) => o.schoolId === active.schoolId && o.branchId === active.branchId) ?? null,
    [options, active],
  )

  // Stamp the workspace's date/time display prefs into the module store so
  // every fmtDate/fmtTime call renders on this school's calendar & clock.
  useEffect(() => {
    setCalendarPrefs({
      calendar: activeOption?.calendarMode ?? DEFAULT_CALENDAR_PREFS.calendar,
      clock: activeOption?.clockMode ?? DEFAULT_CALENDAR_PREFS.clock,
    })
  }, [activeOption])

  // Stamp the workspace on analytics too, so every client-side event slices
  // by school/branch exactly like the backend's captures.
  useEffect(() => {
    setAnalyticsWorkspace(active.schoolId, active.branchId)
  }, [active.schoolId, active.branchId])

  const value = useMemo<SchoolContextValue>(
    () => ({
      options,
      active,
      activeOption,
      isPlatform,
      switchTo: (option) => {
        // Persist the selection, then do a full document navigation to the
        // dashboard. This guarantees every page re-fetches against the new
        // workspace with no stale in-memory data, and avoids landing on a
        // detail page that belonged to the previous workspace (which the new
        // workspace may not be able to access).
        setActiveContext(option.schoolId, option.branchId)
        setActive({ schoolId: option.schoolId, branchId: option.branchId })
        if (typeof window !== "undefined") window.location.assign("/dashboard")
      },
    }),
    [options, active, activeOption, isPlatform],
  )

  return <SchoolContext.Provider value={value}>{children}</SchoolContext.Provider>
}

export function useSchoolContext(): SchoolContextValue {
  const context = useContext(SchoolContext)
  if (!context) throw new Error("useSchoolContext must be used within SchoolProvider")
  return context
}
