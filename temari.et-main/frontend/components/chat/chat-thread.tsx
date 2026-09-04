"use client"

import {
  ArrowLeft,
  BellOff,
  Check,
  ChevronDown,
  Eye,
  Forward,
  Info,
  Loader2,
  Megaphone,
  MessagesSquare,
  Pin,
  PinOff,
  ShieldCheck,
  Trash2,
  X,
} from "lucide-react"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
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
import { Textarea } from "@/components/ui/textarea"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { ChatConversation, ChatMessage } from "@/lib/types"
import { cn } from "@/lib/utils"

import { Composer } from "./composer"
import { ConversationInfoSheet } from "./conversation-info-sheet"
import { ForwardSheet } from "./forward-sheet"
import { DateMarker, MessageBubble } from "./message-bubble"
import { useThread } from "./use-chat"
import { conversationTitle, conversationSubtitle } from "./labels"
import { fmtDate } from "@/lib/dates"

/**
 * One open conversation — reusable everywhere chat mounts (the /messages
 * split pane, and any future context surface). Handles: scroll-anchored
 * history with load-older, day markers, sender grouping, read tracking,
 * reply/edit state, the audit banner for directors, and the composer with
 * all its capabilities.
 */
export function ChatThread({
  conversationId,
  onBack,
  onChanged,
  embedded = false,
  hideHeader = false,
}: {
  conversationId: number
  /** Mobile back navigation (hidden on desktop split pane). */
  onBack?: () => void
  /** Notifies the parent list that unread/last-message changed. */
  onChanged?: () => void
  embedded?: boolean
  /** Context mounts (assignment sheets…) already show who the thread is with. */
  hideHeader?: boolean
}) {
  const { t } = useTranslation("chat")
  const { user } = useAuth()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { openPreview, previewDialog } = useMediaPreview()
  const {
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
    decideMessage,
    markRead,
  } = useThread(conversationId)

  const [replyTo, setReplyTo] = useState<ChatMessage | null>(null)
  const [editing, setEditing] = useState<ChatMessage | null>(null)
  const [infoOpen, setInfoOpen] = useState(false)
  const [selecting, setSelecting] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [forwardIds, setForwardIds] = useState<number[] | null>(null)
  const [pinIndex, setPinIndex] = useState(0)
  const [returning, setReturning] = useState<ChatMessage | null>(null)
  const [returnNote, setReturnNote] = useState("")
  const [deciding, setDeciding] = useState(false)
  const scrollRef = useRef<HTMLDivElement>(null)
  const stickToBottom = useRef(true)
  const loadingOlderRef = useRef(false)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [atBottom, setAtBottom] = useState(true)
  const [newCount, setNewCount] = useState(0)

  // Track whether the user is near the bottom (auto-scroll + FAB visibility),
  // and page older history in automatically when they near the top.
  function handleScroll() {
    const el = scrollRef.current
    if (!el) return
    const near = el.scrollHeight - el.scrollTop - el.clientHeight < 120
    stickToBottom.current = near
    setAtBottom(near)
    if (near) setNewCount(0)
    if (el.scrollTop < 100 && hasMore && !loadingOlderRef.current) void loadOlderAnchored()
  }

  // Prepend a page of history without the view jumping (manual anchoring —
  // Safari has no overflow-anchor).
  async function loadOlderAnchored() {
    const el = scrollRef.current
    if (!el || loadingOlderRef.current) return
    loadingOlderRef.current = true
    setLoadingOlder(true)
    const prevHeight = el.scrollHeight
    const prevTop = el.scrollTop
    try {
      await loadOlder()
    } catch {
      // keep the button/scroll path available for a retry
    } finally {
      setTimeout(() => {
        const node = scrollRef.current
        if (node) node.scrollTop = node.scrollHeight - prevHeight + prevTop
        loadingOlderRef.current = false
        setLoadingOlder(false)
      }, 30)
    }
  }

  function scrollToBottom(smooth = true) {
    const el = scrollRef.current
    el?.scrollTo({ top: el.scrollHeight, behavior: smooth ? "smooth" : "auto" })
    setNewCount(0)
  }

  // Follow the newest message when pinned to the bottom; count it as unseen
  // otherwise. Keyed on the TAIL message so history prepends don't trigger it.
  const tailKey = messages.length > 0 ? (messages[messages.length - 1].client_uuid ?? String(messages[messages.length - 1].id)) : ""
  useEffect(() => {
    if (!tailKey) return
    if (stickToBottom.current) {
      const el = scrollRef.current
      el?.scrollTo({ top: el.scrollHeight })
    } else {
      setNewCount((count) => count + 1)
    }
     
  }, [tailKey])

  // Read pointer: the newest visible message, debounced by the hook itself.
  const lastId = useMemo(() => {
    for (let i = messages.length - 1; i >= 0; i--) {
      if (messages[i].id > 0 && !messages[i].sending) return messages[i].id
    }
    return 0
  }, [messages])

  useEffect(() => {
    if (lastId > 0) {
      markRead(lastId)
      onChanged?.()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- onChanged identity is not a trigger
  }, [lastId, markRead])

  const othersMaxRead = useMemo(
    () => Math.max(0, ...Object.values(reads).map((value) => Number(value))),
    [reads],
  )

  // Tap a reply preview → scroll to the quoted message, paging older history
  // in until it appears (bounded so a very deep quote can't loop forever).
  async function jumpToMessage(messageId: number) {
    let more = hasMore
    for (let attempt = 0; attempt < 25; attempt++) {
      const el = scrollRef.current?.querySelector<HTMLElement>(`[data-message-id="${messageId}"]`)
      if (el) {
        stickToBottom.current = false
        el.scrollIntoView({ behavior: "smooth", block: "center" })
        el.classList.remove("chat-jump-flash")
        void el.offsetWidth // restart the animation when re-tapped
        el.classList.add("chat-jump-flash")
        return
      }
      if (!more) return
      try {
        more = await loadOlder()
      } catch {
        return
      }
      await new Promise((resolve) => setTimeout(resolve, 30)) // let React commit the older page
    }
  }

  if (loading || !conversation) {
    return (
      <div className={cn("flex h-full flex-col", !embedded && "rounded-2xl border bg-card shadow-xs")}>
        <div className="flex items-center gap-3 border-b px-4 py-3">
          <Skeleton className="size-9 rounded-full" />
          <div className="space-y-1.5">
            <Skeleton className="h-3.5 w-40" />
            <Skeleton className="h-3 w-24" />
          </div>
        </div>
        <div className="flex-1 space-y-4 p-4">
          {[...Array(5)].map((_, i) => (
            <Skeleton key={i} className={cn("h-12 w-52 rounded-2xl", i % 2 === 1 && "ml-auto")} />
          ))}
        </div>
      </div>
    )
  }

  const title = conversationTitle(conversation, t)
  const subtitle = conversationSubtitle(conversation, t)
  const audit = conversation.access === "audit"
  const isMember = conversation.access === "member"
  const canEmergency =
    conversation.kind === "channel" && !!conversation.can_moderate && conversation.posting === "admins"

  const pinned = conversation.pinned_messages ?? []
  const currentPinned = pinned.length > 0 ? pinned[pinIndex % pinned.length] : null
  const canDeleteSelected =
    selectedIds.size > 0 &&
    [...selectedIds].every((id) => {
      const m = messages.find((mm) => mm.id === id)
      return !!m && (m.author?.id === user?.id || !!conversation.can_moderate)
    })

  async function handleSend(payload: Parameters<typeof send>[0]) {
    if (editing) {
      await editMessage(editing.id, payload.body ?? "")
      setEditing(null)
      return
    }
    const message = await send(payload, { id: user!.id, name: user!.name, avatar_url: null })
    if (message?.status === "pending") toast.info(t("approval.sentForApproval"))
    onChanged?.()
  }

  async function decide(message: ChatMessage, action: "approve" | "reject", note?: string) {
    setDeciding(true)
    try {
      await decideMessage(message.id, action, note)
      toast.success(action === "approve" ? t("approval.approved") : t("approval.rejectedToast"))
      onChanged?.()
    } catch {
      toast.error(t("thread.sendFailed"))
    } finally {
      setDeciding(false)
    }
  }

  function enterSelect(message: ChatMessage) {
    setSelecting(true)
    setSelectedIds(new Set([message.id]))
  }

  function toggleSelect(message: ChatMessage) {
    setSelectedIds((current) => {
      const next = new Set(current)
      if (next.has(message.id)) next.delete(message.id)
      else next.add(message.id)
      return next
    })
  }

  function exitSelect() {
    setSelecting(false)
    setSelectedIds(new Set())
  }

  async function pin(message: ChatMessage) {
    try {
      await pinMessage(message.id)
      toast.success(message.pinned ? t("thread.unpinned") : t("thread.pinnedToast"))
    } catch {
      toast.error(t("thread.sendFailed"))
    }
  }

  async function runForward(targetId: number) {
    if (!conversation || forwardIds === null) return
    await forwardMessages(targetId, conversation.id, forwardIds)
    setForwardIds(null)
    exitSelect()
  }

  function deleteSelected() {
    const ids = [...selectedIds]
    if (ids.length === 0) return
    confirmDelete(async () => {
      for (const id of ids) await removeMessage(id)
      exitSelect()
    })
  }

  return (
    <div className={cn("flex h-full min-h-0 flex-col overflow-hidden", !embedded && "md:rounded-2xl md:border md:bg-card md:shadow-xs")}>
      {/* Selection toolbar — replaces the header while multi-selecting. */}
      {selecting && (
        <div className="flex items-center gap-1 border-b bg-card/95 px-2 py-2 backdrop-blur-xl md:px-3">
          <Button variant="ghost" size="icon" className="size-9 rounded-full" onClick={exitSelect} aria-label={t("actions.cancel")} title={t("actions.cancel")}>
            <X className="size-5" />
          </Button>
          <span className="flex-1 px-1 text-sm font-semibold">{t("select.count", { count: selectedIds.size })}</span>
          <Button
            variant="ghost"
            size="icon"
            className="size-9 rounded-full"
            disabled={selectedIds.size === 0}
            onClick={() => setForwardIds([...selectedIds])}
            aria-label={t("actions.forward")}
            title={t("actions.forward")}
          >
            <Forward className="size-5" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="size-9 rounded-full text-destructive"
            disabled={!canDeleteSelected}
            onClick={deleteSelected}
            aria-label={t("actions.remove")}
            title={t("actions.remove")}
          >
            <Trash2 className="size-5" />
          </Button>
        </div>
      )}

      {/* Header */}
      {!hideHeader && !selecting && (
      <div className="flex items-center gap-2.5 border-b bg-card/90 px-3 py-2.5 backdrop-blur-xl md:px-4">
        {onBack && (
          <Button variant="ghost" size="icon" className="size-9 rounded-full md:hidden" onClick={onBack} aria-label={t("actions.back")} title={t("actions.back")}>
            <ArrowLeft className="size-5" />
          </Button>
        )}

        <button
          type="button"
          className="flex min-w-0 flex-1 items-center gap-2.5 text-left"
          onClick={() => setInfoOpen(true)}
        >
          <ConversationAvatar conversation={conversation} />
          <span className="min-w-0">
            <span className="flex items-center gap-1.5 truncate text-sm font-semibold">
              {title}
              {conversation.muted && <BellOff className="size-3 shrink-0 text-muted-foreground" />}
              {conversation.pinned && <Pin className="size-3 shrink-0 text-muted-foreground" />}
            </span>
            {subtitle && <span className="block truncate text-xs text-muted-foreground">{subtitle}</span>}
          </span>
        </button>

        <Button variant="ghost" size="icon" className="size-9 rounded-full" onClick={() => setInfoOpen(true)} aria-label={t("info.title")} title={t("info.title")}>
          <Info className="size-4.5" />
        </Button>
      </div>
      )}

      {/* Audit banner */}
      {audit && (
        <div className="flex items-center gap-2 border-b bg-warning/10 px-4 py-2 text-xs text-warning-foreground">
          <Eye className="size-3.5 shrink-0 text-warning" />
          {t("thread.auditBanner")}
        </div>
      )}

      {/* Pinned bar — tap to jump; taps cycle through multiple pins. */}
      {currentPinned && !selecting && (
        <div className="flex items-center gap-2 border-b bg-card/80 px-3 py-1.5 md:px-4">
          <Pin className="size-3.5 shrink-0 text-primary" />
          <button
            type="button"
            className="min-w-0 flex-1 text-left"
            onClick={() => {
              void jumpToMessage(currentPinned.id)
              if (pinned.length > 1) setPinIndex((i) => (i + 1) % pinned.length)
            }}
          >
            <span className="block text-[11px] font-medium text-primary">
              {t("thread.pinnedTitle")}
              {pinned.length > 1 ? ` · ${(pinIndex % pinned.length) + 1}/${pinned.length}` : ""}
            </span>
            <span className="block truncate text-xs text-muted-foreground">{pinnedPreview(currentPinned, t)}</span>
          </button>
          {conversation.can_pin && (
            <Button
              variant="ghost"
              size="icon"
              className="size-7 rounded-full text-muted-foreground"
              onClick={() => void pin(currentPinned)}
              aria-label={t("actions.unpin")}
              title={t("actions.unpin")}
            >
              <PinOff className="size-3.5" />
            </Button>
          )}
        </div>
      )}

      {/* Messages */}
      <div className="relative min-h-0 flex-1">
      <div ref={scrollRef} onScroll={handleScroll} className="h-full overflow-y-auto overscroll-contain px-3 pb-4 md:px-4">
        {hasMore && (
          <div className="flex h-10 items-center justify-center pt-3">
            {loadingOlder ? (
              <Loader2 className="size-4 animate-spin text-muted-foreground" />
            ) : (
              <Button variant="outline" size="sm" className="h-8 rounded-full text-xs" onClick={() => void loadOlderAnchored()}>
                {t("thread.loadEarlier")}
              </Button>
            )}
          </div>
        )}

        {messages.length === 0 && (
          <EmptyState
            icon={MessagesSquare}
            title={t("thread.emptyTitle")}
            description={conversation.can_post ? t("thread.emptyBody") : undefined}
            className="mt-16"
            compact
          />
        )}

        {messages.map((message, index) => {
          const previous = messages[index - 1]
          const own = message.author?.id === user?.id
          const grouped =
            !!previous &&
            previous.kind !== "system" &&
            previous.author?.id === message.author?.id &&
            sameDay(previous.created_at, message.created_at) &&
            minutesBetween(previous.created_at, message.created_at) < 5

          return (
            <div
              key={message.client_uuid ?? message.id}
              data-message-id={message.id > 0 ? message.id : undefined}
              className="rounded-xl"
            >
              {(!previous || !sameDay(previous.created_at, message.created_at)) && (
                <DateMarker label={dayLabel(message.created_at, t)} />
              )}
              <MessageBubble
                message={message}
                conversation={conversation}
                own={own}
                groupedWithPrevious={grouped}
                seen={own && conversation.kind !== "channel" && message.id <= othersMaxRead}
                onReply={conversation.can_post ? setReplyTo : undefined}
                onReact={isMember ? (m, emoji) => void react(m.id, emoji) : undefined}
                onEdit={(m) => setEditing(m)}
                onRemove={(m) => confirmDelete(() => removeMessage(m.id))}
                onPin={(m) => void pin(m)}
                canPin={conversation.can_pin}
                onForward={isMember ? (m) => setForwardIds([m.id]) : undefined}
                onSelect={isMember ? enterSelect : undefined}
                selecting={selecting}
                selected={selectedIds.has(message.id)}
                onToggleSelect={toggleSelect}
                viewerId={user?.id}
                onPreview={openPreview}
                onJumpTo={(id) => void jumpToMessage(id)}
                onApprove={deciding ? undefined : (m) => void decide(m, "approve")}
                onReturn={
                  deciding
                    ? undefined
                    : (m) => {
                        setReturnNote("")
                        setReturning(m)
                      }
                }
                canModerate={conversation.can_moderate}
              />
            </div>
          )
        })}
      </div>

      {/* Scroll-to-latest FAB — appears while reading history; badges the
          messages that arrived meanwhile. */}
      {!atBottom && (
        <Button
          variant="secondary"
          size="icon"
          onClick={() => scrollToBottom()}
          aria-label={t("thread.scrollToLatest")}
          title={t("thread.scrollToLatest")}
          className="absolute right-3 bottom-3 size-11 rounded-full border bg-card shadow-md hover:bg-accent"
        >
          <ChevronDown className="size-5" />
          {newCount > 0 && (
            <span className="absolute -top-1.5 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
              {newCount > 99 ? "99+" : newCount}
            </span>
          )}
        </Button>
      )}
      </div>

      {/* Approval notice for gated senders */}
      {conversation.can_post && conversation.needs_approval && (
        <div className="flex items-center gap-2 border-t bg-info/10 px-4 py-1.5 text-[11px] text-info">
          <ShieldCheck className="size-3.5 shrink-0" />
          {t("approval.composerNotice")}
        </div>
      )}

      {/* Edit banner */}
      {editing && (
        <div className="flex items-center justify-between border-t bg-muted/60 px-4 py-1.5 text-xs">
          <span className="text-muted-foreground">{t("thread.editing")}</span>
          <Button variant="ghost" size="icon" className="size-6 rounded-full" onClick={() => setEditing(null)} aria-label={t("actions.cancel")} title={t("actions.cancel")}>
            <X className="size-3.5" />
          </Button>
        </div>
      )}

      {/* Composer / read-only footer */}
      {conversation.can_post ? (
        <Composer
          conversation={conversation}
          replyTo={replyTo}
          onCancelReply={() => setReplyTo(null)}
          onSend={handleSend}
          canEmergency={canEmergency}
        />
      ) : (
        <div className="flex items-center justify-center gap-2 border-t bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
          {conversation.kind === "channel" && conversation.posting === "admins" ? (
            <>
              <Megaphone className="size-3.5" /> {t("thread.announcementOnly")}
            </>
          ) : (
            <>
              <Check className="size-3.5" /> {t("thread.readOnly")}
            </>
          )}
        </div>
      )}

      <ConversationInfoSheet
        conversation={conversation}
        open={infoOpen}
        onOpenChange={setInfoOpen}
        onConversationChange={setConversation}
        onListChanged={onChanged}
      />

      {forwardIds !== null && (
        <ForwardSheet
          open={forwardIds !== null}
          onOpenChange={(open) => !open && setForwardIds(null)}
          count={forwardIds.length}
          sourceConversationId={conversation.id}
          onForward={runForward}
        />
      )}

      {/* Return-with-note (communication book) */}
      <Dialog open={returning !== null} onOpenChange={(open) => !open && setReturning(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("approval.rejectTitle")}</DialogTitle>
            <DialogDescription>{t("approval.rejectSubtitle")}</DialogDescription>
          </DialogHeader>
          <Textarea
            value={returnNote}
            onChange={(event) => setReturnNote(event.target.value)}
            placeholder={t("approval.notePlaceholder")}
            rows={3}
          />
          <DialogFooter>
            <Button variant="outline" className="h-11 flex-1 rounded-xl" onClick={() => setReturning(null)}>
              {t("actions.cancel")}
            </Button>
            <Button
              variant="destructive"
              className="h-11 flex-1 rounded-xl"
              loading={deciding}
              onClick={async () => {
                if (returning) {
                  await decide(returning, "reject", returnNote.trim() || undefined)
                  setReturning(null)
                }
              }}
            >
              {t("approval.reject")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      {confirmDialog}
      {previewDialog}
    </div>
  )
}

export function ConversationAvatar({ conversation, className }: { conversation: ChatConversation; className?: string }) {
  if (conversation.kind === "direct") {
    return (
      <PersonAvatar
        name={conversation.display.title ?? "?"}
        photoUrl={conversation.display.avatar_url}
        className={cn("size-9", className)}
      />
    )
  }

  return (
    <span
      className={cn(
        "flex size-9 shrink-0 items-center justify-center rounded-full",
        conversation.posting === "admins" ? "bg-warning/15 text-warning" : "bg-primary/10 text-primary",
        className,
      )}
    >
      {conversation.posting === "admins" ? <Megaphone className="size-4" /> : <MessagesSquare className="size-4" />}
    </span>
  )
}

/** One-line preview for the pinned bar: the text (mention tokens stripped) or a media placeholder. */
function pinnedPreview(message: ChatMessage, t: (key: string) => string): string {
  if (message.body) return message.body.replace(/@\[user:(\d+)\]/g, "@").trim()
  return t("thread.attachmentPlaceholder")
}

export function sameDay(a: string, b: string): boolean {
  return new Date(a).toDateString() === new Date(b).toDateString()
}

export function minutesBetween(a: string, b: string): number {
  return Math.abs(new Date(b).getTime() - new Date(a).getTime()) / 60_000
}

export function dayLabel(iso: string, t: (key: string) => string): string {
  const date = new Date(iso)
  const today = new Date()
  const yesterday = new Date(Date.now() - 86_400_000)
  if (date.toDateString() === today.toDateString()) return t("thread.today")
  if (date.toDateString() === yesterday.toDateString()) return t("thread.yesterday")
  return fmtDate(date)
}
