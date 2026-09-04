"use client"

import { BellOff, Check, Mic, Paperclip, Pin, Search, ShieldCheck, SquarePen, X } from "lucide-react"
import { useMemo, useState } from "react"

import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { ChatConversation, ChatSearchHit } from "@/lib/types"
import { cn } from "@/lib/utils"

import { ConversationAvatar } from "./chat-thread"
import { conversationTitle, conversationSubtitle } from "./labels"
import { useChatBase } from "./use-chat"
import { fmtDate, fmtTime, fmtWeekday } from "@/lib/dates"

type Filter = "all" | "unread" | "direct" | "group" | "channel"

/**
 * The conversations rail: search (titles locally + message bodies via the
 * server), filter chips, pinned-first ordering (server-sorted), unread
 * badges, and the new-chat entry point.
 */
export function ConversationList({
  conversations,
  loading,
  selectedId,
  onSelect,
  onNewChat,
  approvalsSlot,
}: {
  conversations: ChatConversation[]
  loading: boolean
  selectedId: number | null
  onSelect: (conversation: ChatConversation | { id: number }) => void
  onNewChat: () => void
  approvalsSlot?: React.ReactNode
}) {
  const { t } = useTranslation("chat")
  const base = useChatBase()
  const [q, setQ] = useState("")
  const [filter, setFilter] = useState<Filter>("all")
  const [hits, setHits] = useState<ChatSearchHit[] | null>(null)
  const [searching, setSearching] = useState(false)

  const filtered = useMemo(() => {
    let list = conversations
    if (filter === "unread") list = list.filter((c) => (c.unread ?? 0) > 0)
    else if (filter !== "all") list = list.filter((c) => c.kind === filter || (filter === "group" && c.kind === "context"))
    if (q.trim()) {
      const needle = q.trim().toLowerCase()
      list = list.filter((c) => (conversationTitle(c, t) ?? "").toLowerCase().includes(needle))
    }
    return list
  }, [conversations, filter, q, t])

  async function searchMessages(value: string) {
    setQ(value)
    if (value.trim().length < 2) {
      setHits(null)
      return
    }
    setSearching(true)
    try {
      const res = await apiFetch<{ data: ChatSearchHit[] }>(`${base}/search?q=${encodeURIComponent(value.trim())}`)
      setHits(res.data)
    } catch {
      setHits(null)
    } finally {
      setSearching(false)
    }
  }

  const filters: Filter[] = ["all", "unread", "direct", "group", "channel"]

  return (
    <div className="flex h-full min-h-0 flex-col">
      {/* Toolbar */}
      <div className="space-y-2.5 px-3 pb-2.5 pt-3 md:px-4">
        <div className="flex items-center gap-2">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <input
              value={q}
              onChange={(event) => void searchMessages(event.target.value)}
              placeholder={t("list.searchPlaceholder")}
              className="h-10 w-full rounded-full border bg-muted/30 pl-9 pr-8 text-sm outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30"
            />
            {q && (
              <button
                type="button"
                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground"
                onClick={() => {
                  setQ("")
                  setHits(null)
                }}
                aria-label={t("actions.cancel")}
              >
                <X className="size-4" />
              </button>
            )}
          </div>
          <Button size="icon" className="size-10 shrink-0 rounded-full" onClick={onNewChat} aria-label={t("list.newChat")} title={t("list.newChat")}>
            <SquarePen className="size-4.5" />
          </Button>
        </div>

        <div className="scrollbar-none -mx-1 flex gap-1.5 overflow-x-auto px-1">
          {filters.map((value) => (
            <button
              key={value}
              type="button"
              onClick={() => setFilter(value)}
              className={cn(
                "shrink-0 rounded-full border px-3 py-1 text-xs font-medium transition-colors",
                filter === value
                  ? "border-primary bg-primary text-primary-foreground"
                  : "bg-card text-muted-foreground hover:bg-accent",
              )}
            >
              {t(`list.filters.${value}`)}
            </button>
          ))}
        </div>

        {approvalsSlot}
      </div>

      {/* Body */}
      <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-4 md:px-2.5">
        {loading ? (
          <div className="space-y-1 px-1 pt-1">
            {[...Array(7)].map((_, i) => (
              <div key={i} className="flex items-center gap-3 rounded-xl p-2.5">
                <Skeleton className="size-11 rounded-full" />
                <div className="flex-1 space-y-1.5">
                  <Skeleton className="h-3.5 w-2/5" />
                  <Skeleton className="h-3 w-4/5" />
                </div>
              </div>
            ))}
          </div>
        ) : hits !== null ? (
          <MessageHits hits={hits} searching={searching} onSelect={(hit) => onSelect({ id: hit.conversation_id })} />
        ) : filtered.length === 0 ? (
          <EmptyState
            icon={SquarePen}
            title={q ? t("list.noResults") : t("list.emptyTitle")}
            description={q ? undefined : t("list.emptyBody")}
            className="mt-14"
            compact
          />
        ) : (
          <ul className="space-y-0.5">
            {filtered.map((conversation) => (
              <ConversationRow
                key={conversation.id}
                conversation={conversation}
                active={conversation.id === selectedId}
                onSelect={() => onSelect(conversation)}
              />
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}

function ConversationRow({
  conversation,
  active,
  onSelect,
}: {
  conversation: ChatConversation
  active: boolean
  onSelect: () => void
}) {
  const { t } = useTranslation("chat")
  const unread = conversation.unread ?? 0
  const last = conversation.last_message

  return (
    <li>
      <button
        type="button"
        onClick={onSelect}
        className={cn(
          "pressable flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors",
          active ? "bg-primary/10" : "hover:bg-accent/60",
        )}
      >
        <ConversationAvatar conversation={conversation} className="size-11" />

        <span className="min-w-0 flex-1">
          <span className="flex items-center gap-1.5">
            <span className={cn("truncate text-sm", unread > 0 ? "font-semibold" : "font-medium")}>
              {conversationTitle(conversation, t)}
            </span>
            {conversation.pinned && <Pin className="size-3 shrink-0 text-muted-foreground" />}
            {conversation.muted && <BellOff className="size-3 shrink-0 text-muted-foreground" />}
            <span className="ml-auto shrink-0 text-[10px] tabular-nums text-muted-foreground">
              {last ? relativeTime(last.created_at, t) : ""}
            </span>
          </span>

          <span className="mt-0.5 flex items-center gap-1">
            <span className={cn("line-clamp-1 flex-1 text-xs", unread > 0 ? "text-foreground" : "text-muted-foreground")}>
              {last ? (
                <>
                  {last.author_name && conversation.kind !== "direct" && (
                    <span className="font-medium">{last.author_name.split(" ")[0]}: </span>
                  )}
                  {last.kind === "voice" ? (
                    <span className="inline-flex items-center gap-1">
                      <Mic className="size-3" /> {t("list.voiceMessage")}
                    </span>
                  ) : last.kind === "system" ? (
                    t(`system.${String(last.meta?.event ?? "")}`, (last.meta?.params ?? {}) as Record<string, string>)
                  ) : (
                    <>
                      {last.has_attachments && <Paperclip className="mr-0.5 inline size-3" />}
                      {stripMentions(last.body)}
                    </>
                  )}
                </>
              ) : (
                <span className="italic">{conversationSubtitle(conversation, t)}</span>
              )}
            </span>
            {unread > 0 && (
              <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-semibold text-primary-foreground">
                {unread > 99 ? "99+" : unread}
              </span>
            )}
          </span>
        </span>
      </button>
    </li>
  )
}

function MessageHits({
  hits,
  searching,
  onSelect,
}: {
  hits: ChatSearchHit[]
  searching: boolean
  onSelect: (hit: ChatSearchHit) => void
}) {
  const { t } = useTranslation("chat")

  if (searching && hits.length === 0) {
    return <Skeleton className="mx-2 mt-2 h-14 rounded-xl" />
  }

  if (hits.length === 0) {
    return <EmptyState icon={Search} title={t("list.noResults")} className="mt-14" compact />
  }

  return (
    <div>
      <p className="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        {t("list.messageResults")}
      </p>
      <ul className="space-y-0.5">
        {hits.map((hit) => (
          <li key={hit.id}>
            <button
              type="button"
              onClick={() => onSelect(hit)}
              className="pressable flex w-full flex-col gap-0.5 rounded-xl px-3 py-2 text-left hover:bg-accent/60"
            >
              <span className="flex w-full items-center gap-2 text-xs">
                <span className="truncate font-medium">{conversationTitle(hit.conversation, t)}</span>
                <span className="ml-auto shrink-0 tabular-nums text-muted-foreground">{relativeTime(hit.created_at, t)}</span>
              </span>
              <span className="line-clamp-2 text-xs text-muted-foreground">
                {hit.author_name && <span className="font-medium">{hit.author_name}: </span>}
                {stripMentions(hit.body)}
              </span>
            </button>
          </li>
        ))}
      </ul>
    </div>
  )
}

/** Approvals entry chip for chat.moderate holders. */
export function ApprovalsChip({ count, onOpen }: { count: number; onOpen: () => void }) {
  const { t } = useTranslation("chat")
  if (count === 0) return null

  return (
    <button
      type="button"
      onClick={onOpen}
      className="pressable flex w-full items-center gap-2.5 rounded-xl border border-warning/40 bg-warning/10 px-3 py-2.5 text-left"
    >
      <span className="flex size-8 items-center justify-center rounded-full bg-warning/20 text-warning">
        <ShieldCheck className="size-4" />
      </span>
      <span className="flex-1 text-xs font-medium">{t("approval.chip", { count })}</span>
      <Check className="size-4 text-warning" />
    </button>
  )
}

function stripMentions(body: string | null | undefined): string {
  return (body ?? "").replace(/@\[user:\d+\]/g, "@…")
}

function relativeTime(iso: string, t: (key: string, vars?: Record<string, string | number>) => string): string {
  const date = new Date(iso)
  const now = new Date()
  const minutes = Math.floor((now.getTime() - date.getTime()) / 60_000)
  if (minutes < 1) return t("time.now")
  if (minutes < 60) return t("time.minutes", { count: minutes })
  if (date.toDateString() === now.toDateString()) {
    return fmtTime(date)
  }
  if (minutes < 60 * 24 * 6) return fmtWeekday(date, true)
  return fmtDate(date, { noYear: true })
}
