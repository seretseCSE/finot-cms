"use client"

import { useCallback, useEffect, useState } from "react"

import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import type { AiContextInfo, AiConversationSummary, AiSurface } from "@/lib/types"

/**
 * Assistants + entitlements for the ACTIVE workspace. Reloads on workspace
 * switch — a principal moving from School A to School B gets School B's
 * assistant/quotas (existing sessions keep their frozen context server-side).
 */
export function useAiContext() {
  const { active } = useSchoolContext()
  const [context, setContext] = useState<AiContextInfo | null>(null)
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(() => {
    return apiFetch<{ data: AiContextInfo }>("/ai/context")
      .then((res) => setContext(res.data))
      .catch(() => setContext(null))
  }, [])

  // Initial `loading` covers the first fetch; workspace switches refresh in
  // place (stale lanes for a beat beats a full-screen flash).
  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: AiContextInfo }>("/ai/context")
      .then((res) => {
        if (!cancelled) setContext(res.data)
      })
      .catch(() => {
        if (!cancelled) setContext(null)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [active.schoolId, active.branchId])

  return { context, loading, refresh }
}

export function useAiConversations() {
  const [conversations, setConversations] = useState<AiConversationSummary[]>([])
  const [loading, setLoading] = useState(true)

  const refresh = useCallback(() => {
    return apiFetch<{ data: AiConversationSummary[] }>("/ai/conversations")
      .then((res) => {
        setConversations(res.data)
        return res.data
      })
      .catch(() => undefined)
  }, [])

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: AiConversationSummary[] }>("/ai/conversations")
      .then((res) => {
        if (!cancelled) setConversations(res.data)
      })
      .catch(() => undefined)
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
    }
  }, [])

  return { conversations, setConversations, loading, refresh }
}

export function createAiConversation(surface: AiSurface, studentId?: number | null) {
  return apiFetch<{ data: AiConversationSummary }>("/ai/conversations", {
    method: "POST",
    body: { surface, student_id: studentId ?? undefined },
    pendingKey: "pending.actions.saving",
  }).then((res) => res.data)
}
