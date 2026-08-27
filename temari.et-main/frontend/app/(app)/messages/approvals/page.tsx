"use client"

import { ArrowUpRight, ShieldCheck } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import {
  ConversationAvatar,
  dayLabel,
  minutesBetween,
  sameDay,
} from "@/components/chat/chat-thread"
import { conversationSubtitle, conversationTitle } from "@/components/chat/labels"
import { DateMarker, MessageBubble } from "@/components/chat/message-bubble"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { EmptyState } from "@/components/ui/empty-state"
import { useMediaPreview } from "@/components/ui/media-preview"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { Textarea } from "@/components/ui/textarea"
import { apiFetch, ApiError } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { ChatConversation, ChatMessage } from "@/lib/types"

type PendingMessage = ChatMessage & { conversation: ChatConversation }

/**
 * The digital communication book (ADR-019): teacher→family messages waiting
 * for a director. Messages render as real chat bubbles (same MessageBubble
 * as the thread — attachments, voice notes, previews all work), grouped per
 * conversation with the approve/return actions on each bubble. Approve
 * delivers to the family; return sends it back to the teacher with a note.
 * Families never see undecided or rejected messages.
 */
export default function ChatApprovalsPage() {
  const { t } = useTranslation("chat")
  const router = useRouter()
  const { user } = useAuth()
  const { openPreview, previewDialog } = useMediaPreview()
  const [pending, setPending] = useState<PendingMessage[] | null>(null)
  const [rejecting, setRejecting] = useState<PendingMessage | null>(null)
  const [note, setNote] = useState("")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: PendingMessage[] }>("/chat/approvals")
      .then((res) => {
        if (!cancelled) setPending(res.data)
      })
      .catch(() => {
        if (!cancelled) setPending([])
      })
    return () => {
      cancelled = true
    }
  }, [])

  // One card per conversation, its pending messages in order — reads like
  // the actual thread, not an abstract list of rows.
  const groups = useMemo(() => {
    if (!pending) return null
    const map = new Map<number, { conversation: ChatConversation; messages: PendingMessage[] }>()
    for (const message of pending) {
      const group = map.get(message.conversation_id) ?? {
        conversation: message.conversation,
        messages: [],
      }
      group.messages.push(message)
      map.set(message.conversation_id, group)
    }
    return [...map.values()]
  }, [pending])

  async function decide(message: ChatMessage, action: "approve" | "reject", reviewNote?: string) {
    setBusy(true)
    try {
      await apiFetch(`/chat/messages/${message.id}/${action}`, {
        method: "POST",
        body: action === "reject" ? { note: reviewNote || undefined } : undefined,
      })
      setPending((current) => (current ?? []).filter((m) => m.id !== message.id))
      toast.success(action === "approve" ? t("approval.approved") : t("approval.rejectedToast"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("thread.sendFailed"))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("approval.pageTitle")} description={t("approval.pageSubtitle")} backHref="/messages" />

      <div className="page-gutter">
        <div className="mx-auto max-w-3xl space-y-4">
          {groups === null ? (
            [...Array(3)].map((_, i) => <Skeleton key={i} className="h-40 rounded-2xl" />)
          ) : groups.length === 0 ? (
            <EmptyState icon={ShieldCheck} title={t("approval.emptyTitle")} description={t("approval.emptyBody")} />
          ) : (
            groups.map(({ conversation, messages }) => (
              <section key={conversation.id} className="overflow-hidden rounded-2xl border bg-card shadow-xs">
                {/* Conversation header — mirrors the thread header. */}
                <header className="flex items-center gap-2.5 border-b px-3 py-2.5 md:px-4">
                  <button
                    type="button"
                    className="flex min-w-0 flex-1 items-center gap-2.5 text-left"
                    onClick={() => router.push(`/messages?c=${conversation.id}`)}
                  >
                    <ConversationAvatar conversation={conversation} />
                    <span className="min-w-0">
                      <span className="block truncate text-sm font-semibold">
                        {conversationTitle(conversation, t)}
                      </span>
                      {conversationSubtitle(conversation, t) && (
                        <span className="block truncate text-xs text-muted-foreground">
                          {conversationSubtitle(conversation, t)}
                        </span>
                      )}
                    </span>
                  </button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-8 shrink-0 rounded-full text-xs"
                    onClick={() => router.push(`/messages?c=${conversation.id}`)}
                  >
                    {t("approval.openThread")} <ArrowUpRight className="size-3.5" />
                  </Button>
                </header>

                {/* The pending messages, exactly as they look in the thread. */}
                <div className="bg-muted/30 px-3 pb-4 md:px-4">
                  {messages.map((message, index) => {
                    const previous = messages[index - 1]
                    const grouped =
                      !!previous &&
                      previous.author?.id === message.author?.id &&
                      sameDay(previous.created_at, message.created_at) &&
                      minutesBetween(previous.created_at, message.created_at) < 5

                    return (
                      <div key={message.id}>
                        {(!previous || !sameDay(previous.created_at, message.created_at)) && (
                          <DateMarker label={dayLabel(message.created_at, t)} />
                        )}
                        <MessageBubble
                          message={message}
                          conversation={conversation}
                          own={message.author?.id === user?.id}
                          groupedWithPrevious={grouped}
                          showAuthor
                          canModerate
                          onPreview={openPreview}
                          onApprove={busy ? undefined : (m) => void decide(m, "approve")}
                          onReturn={
                            busy
                              ? undefined
                              : (m) => {
                                  setNote("")
                                  setRejecting(m as PendingMessage)
                                }
                          }
                        />
                      </div>
                    )
                  })}
                </div>
              </section>
            ))
          )}
        </div>
      </div>

      <Dialog open={rejecting !== null} onOpenChange={(open) => !open && setRejecting(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("approval.rejectTitle")}</DialogTitle>
            <DialogDescription>{t("approval.rejectSubtitle")}</DialogDescription>
          </DialogHeader>
          <Textarea
            value={note}
            onChange={(event) => setNote(event.target.value)}
            placeholder={t("approval.notePlaceholder")}
            rows={3}
          />
          <DialogFooter>
            <Button variant="outline" className="h-11 flex-1 rounded-xl" onClick={() => setRejecting(null)}>
              {t("actions.cancel")}
            </Button>
            <Button
              variant="destructive"
              className="h-11 flex-1 rounded-xl"
              loading={busy}
              onClick={async () => {
                if (rejecting) {
                  await decide(rejecting, "reject", note.trim())
                  setRejecting(null)
                }
              }}
            >
              {t("approval.reject")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      {previewDialog}
    </div>
  )
}
