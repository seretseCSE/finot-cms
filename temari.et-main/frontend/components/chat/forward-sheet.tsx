"use client"

import { Search } from "lucide-react"
import { useMemo, useState } from "react"
import { toast } from "sonner"

import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { ChatConversation } from "@/lib/types"

import { ConversationAvatar } from "./chat-thread"
import { conversationTitle, conversationSubtitle } from "./labels"
import { useConversations } from "./use-chat"

/**
 * Forward picker (Telegram-style): choose an existing conversation to copy the
 * selected message(s) into. Reachability + the communication-book gate are
 * re-checked server-side, so a target you can't post to fails cleanly.
 */
export function ForwardSheet({
  open,
  onOpenChange,
  count,
  sourceConversationId,
  onForward,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** How many messages are being forwarded (for the subtitle). */
  count: number
  sourceConversationId: number
  /** Runs the forward against the chosen target; resolves when done. */
  onForward: (targetId: number) => Promise<void>
}) {
  const { t } = useTranslation("chat")
  const { conversations, loading } = useConversations()
  const [q, setQ] = useState("")
  const [busyId, setBusyId] = useState<number | null>(null)

  const targets = useMemo(() => {
    const needle = q.trim().toLowerCase()
    return conversations
      .filter((c) => c.id !== sourceConversationId && !c.archived)
      .filter((c) => {
        if (!needle) return true
        const title = conversationTitle(c, t).toLowerCase()
        return title.includes(needle)
      })
  }, [conversations, sourceConversationId, q, t])

  async function pick(target: ChatConversation) {
    if (busyId !== null) return
    setBusyId(target.id)
    try {
      await onForward(target.id)
      toast.success(t("forward.done", { title: conversationTitle(target, t) }))
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("thread.sendFailed"))
    } finally {
      setBusyId(null)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-md">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("forward.title")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("forward.subtitle", { count })}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody>
          <div className="space-y-2">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <input
                value={q}
                onChange={(event) => setQ(event.target.value)}
                placeholder={t("forward.search")}
                className="h-11 w-full rounded-xl border bg-muted/30 pl-9 pr-3 text-sm outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30"
              />
            </div>

            {loading ? (
              <div className="space-y-1.5 pt-1">
                {[...Array(5)].map((_, i) => (
                  <div key={i} className="flex items-center gap-2.5 px-2 py-1.5">
                    <Skeleton className="size-9 rounded-full" />
                    <Skeleton className="h-3.5 w-40" />
                  </div>
                ))}
              </div>
            ) : (
              <ul className="max-h-[60vh] space-y-0.5 overflow-y-auto">
                {targets.map((target) => {
                  const subtitle = conversationSubtitle(target, t)
                  return (
                    <li key={target.id}>
                      <button
                        type="button"
                        disabled={busyId !== null}
                        onClick={() => void pick(target)}
                        className="pressable flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left hover:bg-accent/60 disabled:opacity-60"
                      >
                        <ConversationAvatar conversation={target} />
                        <span className="min-w-0 flex-1">
                          <span className="block truncate text-sm font-medium">{conversationTitle(target, t)}</span>
                          {subtitle && <span className="block truncate text-[11px] text-muted-foreground">{subtitle}</span>}
                        </span>
                      </button>
                    </li>
                  )
                })}
                {targets.length === 0 && (
                  <p className="px-2 py-8 text-center text-xs text-muted-foreground">{t("list.noResults")}</p>
                )}
              </ul>
            )}
          </div>
        </ResponsiveSheetBody>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
