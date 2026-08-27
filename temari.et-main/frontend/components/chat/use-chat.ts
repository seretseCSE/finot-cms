"use client"

import { useCallback, useEffect, useMemo, useRef, useState } from "react"

import { hasStaffMembership } from "@/components/app-shell/nav-config"
import { useWorkspaceSurface } from "@/components/app-shell/use-workspace-surface"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { getEcho } from "@/lib/echo"
import type { ChatAttachment, ChatConversation, ChatMessage } from "@/lib/types"

/**
 * Chat data layer (ADR-019). HTTP polling is the reliability floor (3G);
 * the Reverb socket is a latency upgrade delivering the SAME payload shapes.
 * Sends carry a client uuid so an offline/flaky retry can never duplicate.
 */

const LIST_POLL_MS = 30_000
const THREAD_POLL_MS = 20_000

/**
 * Which chat lane this workspace speaks (ADR-012/019): the STAFF workspace
 * uses /chat, every personal workspace (family, tutoring) uses /me/chat — a
 * dual-hat director sees school conversations in the school workspace and
 * family conversations in My family, never a merge. Counts only real staff
 * memberships (active, non-relationship roles), so an ex-teacher who kept a
 * parent hat lands on the family lane, not a dead staff lane.
 */
export function useChatBase(): string {
  const { user } = useAuth()
  const surface = useWorkspaceSurface()
  return useMemo(
    () => (surface === "staff" && hasStaffMembership(user) ? "/chat" : "/me/chat"),
    [surface, user],
  )
}

export function useConversations() {
  const base = useChatBase()
  const { user } = useAuth()
  const [conversations, setConversations] = useState<ChatConversation[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(false)

  const refresh = useCallback(async () => {
    try {
      const res = await apiFetch<{ data: ChatConversation[] }>(`${base}/conversations`)
      setConversations(res.data)
      setError(false)
    } catch {
      setError(true)
    } finally {
      setLoading(false)
    }
  }, [base])

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: ChatConversation[] }>(`${base}/conversations`)
      .then((res) => {
        if (cancelled) return
        setConversations(res.data)
        setError(false)
      })
      .catch(() => {
        if (!cancelled) setError(true)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    const timer = window.setInterval(refresh, LIST_POLL_MS)
    return () => {
      cancelled = true
      window.clearInterval(timer)
    }
  }, [base, refresh])

  // Socket upgrade: any bump on my personal lane refreshes the list early.
  useEffect(() => {
    const echo = getEcho()
    if (!echo || !user?.id) return

    const channelName = `chat.user.${user.id}`
    const channel = echo.private(channelName)
    channel.listen(".chat.message", refresh)

    return () => {
      echo.leave(channelName)
    }
  }, [user?.id, refresh])

  return { conversations, loading, error, refresh, setConversations }
}

export interface SendPayload {
  body?: string
  attachments?: ChatAttachment[]
  kind?: "text" | "voice"
  reply_to_id?: number
  emergency?: boolean
}

export function useThread(conversationId: number | null) {
  const base = useChatBase()
  const [conversation, setConversation] = useState<ChatConversation | null>(null)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [reads, setReads] = useState<Record<string, number>>({})
  // Loading is DERIVED (which conversation has data) — no sync setState on switch.
  const [loadedId, setLoadedId] = useState<number | null>(null)
  const [hasMore, setHasMore] = useState(false)
  const lastReadSent = useRef(0)
  const loading = conversationId !== null && loadedId !== conversationId

  const mergeMessages = useCallback(
    (incoming: ChatMessage[], prepend = false) => {
      setMessages((current) => {
        const byId = new Map<number, ChatMessage>()
        const byUuid = new Map<string, number>()
        const fresh = incoming.filter((m) => m.conversation_id === conversationId)
        const ordered = prepend ? [...fresh, ...current] : [...current, ...fresh]

        for (const message of ordered) {
          // A confirmed send replaces its optimistic twin (matched by uuid).
          if (message.client_uuid && byUuid.has(message.client_uuid)) {
            byId.delete(byUuid.get(message.client_uuid)!)
          }
          if (message.client_uuid) byUuid.set(message.client_uuid, message.id)
          byId.set(message.id, { ...byId.get(message.id), ...message })
        }

        return [...byId.values()].sort((a, b) => a.id - b.id)
      })
    },
    [conversationId],
  )

  /** Read the freshest loaded list without holding it in a ref. */
  const currentMessages = useCallback((): ChatMessage[] => {
    let snapshot: ChatMessage[] = []
    setMessages((current) => {
      snapshot = current
      return current
    })
    return snapshot
  }, [])

  const fetchLatest = useCallback(async () => {
    if (!conversationId) return
    const after = lastRealId(currentMessages())
    const query = after > 0 ? `?after=${after}` : ""
    const res = await apiFetch<{ data: ChatMessage[]; meta: { has_more: boolean; reads: Record<string, number> } }>(
      `${base}/conversations/${conversationId}/messages${query}`,
    )
    if (after === 0) setHasMore(res.meta.has_more)
    setReads(res.meta.reads ?? {})
    if (res.data.length > 0) mergeMessages(res.data)
  }, [base, conversationId, mergeMessages, currentMessages])

  useEffect(() => {
    if (!conversationId) return
    let cancelled = false
    lastReadSent.current = 0

    Promise.all([
      apiFetch<{ data: ChatConversation }>(`${base}/conversations/${conversationId}`),
      apiFetch<{ data: ChatMessage[]; meta: { has_more: boolean; reads: Record<string, number> } }>(
        `${base}/conversations/${conversationId}/messages`,
      ),
    ])
      .then(([detail, list]) => {
        if (cancelled) return
        setConversation(detail.data)
        setHasMore(list.meta.has_more)
        setReads(list.meta.reads ?? {})
        setMessages(list.data)
        setLoadedId(conversationId)
      })
      .catch(() => undefined)

    const timer = window.setInterval(() => {
      fetchLatest().catch(() => undefined)
    }, THREAD_POLL_MS)

    return () => {
      cancelled = true
      window.clearInterval(timer)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- fetchLatest is poll-only
  }, [base, conversationId])

  // Socket upgrade for the open conversation.
  useEffect(() => {
    if (!conversationId) return
    const echo = getEcho()
    if (!echo) return

    const channelName = `chat.conversation.${conversationId}`
    const channel = echo.private(channelName)
    channel.listen(".chat.message", (event: { message: ChatMessage }) => mergeMessages([event.message]))
    channel.listen(".chat.message.updated", (event: { message: ChatMessage }) => mergeMessages([event.message]))

    return () => {
      echo.leave(channelName)
    }
  }, [conversationId, mergeMessages])

  const loadOlder = useCallback(async (): Promise<boolean> => {
    if (!conversationId) return false
    const first = currentMessages().find((m) => !m.sending)
    if (!first) return false
    const res = await apiFetch<{ data: ChatMessage[]; meta: { has_more: boolean } }>(
      `${base}/conversations/${conversationId}/messages?before=${first.id}`,
    )
    setHasMore(res.meta.has_more)
    mergeMessages(res.data, true)
    return res.meta.has_more
  }, [base, conversationId, mergeMessages, currentMessages])

  const markRead = useCallback(
    (messageId: number) => {
      if (!conversationId || messageId <= lastReadSent.current) return
      lastReadSent.current = messageId
      apiFetch(`${base}/conversations/${conversationId}/read`, {
        method: "POST",
        body: { message_id: messageId },
        pendingKey: "pending.actions.saving",
      }).catch(() => undefined)
    },
    [base, conversationId],
  )

  const send = useCallback(
    async (payload: SendPayload, author: { id: number; name: string; avatar_url: string | null }) => {
      if (!conversationId) return
      const uuid = crypto.randomUUID()
      const optimistic: ChatMessage = {
        id: -Date.now(),
        conversation_id: conversationId,
        kind: payload.kind ?? "text",
        body: payload.body ?? null,
        attachments: (payload.attachments ?? []).map((file) => ({ ...file })),
        meta: null,
        status: "sent",
        review_note: null,
        author,
        reply_to: null,
        reactions: [],
        removed: false,
        edited_at: null,
        client_uuid: uuid,
        created_at: new Date().toISOString(),
        sending: true,
      }
      mergeMessages([optimistic])

      try {
        const res = await apiFetch<{ data: ChatMessage }>(
          `${base}/conversations/${conversationId}/messages`,
          { method: "POST", body: { ...payload, client_uuid: uuid }, pendingKey: "pending.actions.saving" },
        )
        mergeMessages([res.data])
        return res.data
      } catch (error) {
        setMessages((current) =>
          current.map((m) => (m.client_uuid === uuid ? { ...m, sending: false, failed: true } : m)),
        )
        throw error
      }
    },
    [base, conversationId, mergeMessages],
  )

  const react = useCallback(
    async (messageId: number, emoji: string) => {
      const res = await apiFetch<{ data: ChatMessage }>(`${base}/messages/${messageId}/reactions`, {
        method: "POST",
        body: { emoji },
        pendingKey: "pending.actions.saving",
      })
      mergeMessages([res.data])
    },
    [base, mergeMessages],
  )

  const editMessage = useCallback(
    async (messageId: number, body: string) => {
      const res = await apiFetch<{ data: ChatMessage }>(`${base}/messages/${messageId}`, {
        method: "PUT",
        body: { body },
        pendingKey: "pending.actions.saving",
      })
      mergeMessages([res.data])
    },
    [base, mergeMessages],
  )

  const removeMessage = useCallback(
    async (messageId: number) => {
      await apiFetch(`${base}/messages/${messageId}`, {
        // Confirmed by the caller: chat-thread.tsx wraps both the single-message and
        // bulk-select paths in confirmDelete(). This hook is the transport, not the trigger.
        // eslint-disable-next-line temari/require-delete-confirmation -- see above
        method: "DELETE",
        pendingKey: "pending.actions.deleting",
      })
      setMessages((current) =>
        current.map((m) =>
          m.id === messageId ? { ...m, removed: true, body: null, attachments: [] } : m,
        ),
      )
    },
    [base],
  )

  const refreshConversation = useCallback(async () => {
    if (!conversationId) return
    const res = await apiFetch<{ data: ChatConversation }>(`${base}/conversations/${conversationId}`)
    setConversation(res.data)
  }, [base, conversationId])

  const pinMessage = useCallback(
    async (messageId: number) => {
      const res = await apiFetch<{ data: ChatMessage }>(`${base}/messages/${messageId}/pin`, {
        method: "POST",
        pendingKey: "pending.actions.saving",
      })
      mergeMessages([res.data])
      await refreshConversation()
    },
    [base, mergeMessages, refreshConversation],
  )

  const forwardMessages = useCallback(
    async (targetId: number, sourceConversationId: number, messageIds: number[]) => {
      await apiFetch(`${base}/conversations/${targetId}/forward`, {
        method: "POST",
        body: { source_conversation_id: sourceConversationId, message_ids: messageIds },
        pendingKey: "pending.actions.saving",
      })
    },
    [base],
  )

  /**
   * Inline communication-book decision (moderators only — always the staff
   * lane). Approve delivers to the family; reject returns it to the teacher.
   */
  const decideMessage = useCallback(async (messageId: number, action: "approve" | "reject", note?: string) => {
    await apiFetch(`/chat/messages/${messageId}/${action}`, {
      method: "POST",
      body: action === "reject" ? { note: note || undefined } : undefined,
    })
    setMessages((current) =>
      current.map((m) =>
        m.id === messageId
          ? { ...m, status: action === "approve" ? "sent" : "rejected", review_note: note ?? null }
          : m,
      ),
    )
  }, [])

  return {
    conversation,
    setConversation,
    messages,
    reads,
    loading,
    hasMore,
    loadOlder,
    send,
    react,
    editMessage,
    removeMessage,
    pinMessage,
    forwardMessages,
    refreshConversation,
    decideMessage,
    markRead,
    refreshLatest: fetchLatest,
  }
}

function lastRealId(messages: ChatMessage[]): number {
  for (let i = messages.length - 1; i >= 0; i--) {
    if (!messages[i].sending && messages[i].id > 0) return messages[i].id
  }
  return 0
}

/** Upload one composer file to R2, returning the attachment descriptor. */
export async function uploadChatFile(base: string, file: File, name: string) {
  const form = new FormData()
  form.append("file", file, name)
  const res = await apiFetch<{
    data: { name: string; path: string; size: number; mime_type: string; url: string }
  }>(`${base}/uploads`, { method: "POST", body: form, pendingKey: "pending.actions.uploading" })
  return res.data
}
