"use client"

import { useEffect, useState } from "react"

import { ChatThread } from "@/components/chat/chat-thread"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * The assignment's private teacher↔student thread — a chat CONTEXT
 * conversation (ADR-019). Resolves the conversation for this assignment
 * (staff lane passes the student; the /me lane is the student), then the
 * full chat thread takes over: attachments, voice notes, reactions,
 * replies, realtime.
 */
export function AssignmentChat({
  assignmentId,
  studentId,
  lane,
  className,
}: {
  assignmentId: number
  /** Staff lane only — which student's thread. */
  studentId?: number
  lane: "staff" | "me"
  className?: string
}) {
  const { t } = useTranslation("chat")
  const [conversationId, setConversationId] = useState<number | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false

    const path =
      lane === "staff"
        ? `/assignments/${assignmentId}/thread?student_id=${studentId}`
        : `/me/lms/assignments/${assignmentId}/thread`

    apiFetch<{ data: { conversation_id: number } }>(path)
      .then((res) => {
        if (!cancelled) setConversationId(res.data.conversation_id)
      })
      .catch(() => {
        if (!cancelled) setFailed(true)
      })

    return () => {
      cancelled = true
    }
  }, [assignmentId, studentId, lane])

  if (failed) {
    return (
      <p className={cn("px-4 py-6 text-center text-xs text-muted-foreground", className)}>
        {t("thread.sendFailed")}
      </p>
    )
  }

  if (conversationId === null) {
    return (
      <div className={cn("space-y-2 p-4", className)}>
        <Skeleton className="h-10 w-3/4 rounded-2xl" />
        <Skeleton className="ml-auto h-10 w-2/3 rounded-2xl" />
        <Skeleton className="h-10 w-1/2 rounded-2xl" />
      </div>
    )
  }

  return (
    <div className={cn("flex min-h-0 flex-1 flex-col", className)}>
      <ChatThread conversationId={conversationId} embedded hideHeader />
    </div>
  )
}
