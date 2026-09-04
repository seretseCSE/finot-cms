"use client"

import { useEffect, useState } from "react"

import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import type { DashboardData } from "@/lib/types"

/**
 * ONE aggregated request per landing — the backend assembles every block the
 * caller's permissions allow in the active context (and caches the heavy
 * aggregates server-side), so the dashboard paints from a single round trip.
 * The result is keyed by the request identity, so a workspace switch reads as
 * loading without any manual reset.
 */
export function useDashboard(): {
  data: DashboardData | null
  error: boolean
  retry: () => void
} {
  const { active } = useSchoolContext()
  const key = `${active.schoolId ?? ""}|${active.branchId ?? ""}`
  const [attempt, setAttempt] = useState(0)
  const [result, setResult] = useState<{
    key: string
    data: DashboardData | null
    error: boolean
  } | null>(null)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: DashboardData }>("/dashboard")
      .then(
        (res) => !cancelled && setResult({ key, data: res.data, error: false })
      )
      .catch(() => !cancelled && setResult({ key, data: null, error: true }))
    return () => {
      cancelled = true
    }
  }, [key, attempt])

  return {
    data: result?.key === key ? result.data : null,
    error: result?.key === key ? result.error : false,
    retry: () => setAttempt((n) => n + 1),
  }
}
