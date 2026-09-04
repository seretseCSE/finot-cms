"use client"

import { MoreHorizontal, Pencil, Pin, PinOff, Search, Sparkles, SquarePen, Trash2 } from "lucide-react"
import { useMemo, useState } from "react"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AiConversationSummary } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The ChatGPT-style session rail: search, New chat, and the session list
 * grouped Pinned / Today / Yesterday / Previous 7 days / Older, with
 * rename / pin / delete per row.
 */
export function SessionRail({
  conversations,
  loading,
  selectedId,
  onSelect,
  onNewChat,
  onChanged,
}: {
  conversations: AiConversationSummary[]
  loading: boolean
  selectedId: number | null
  onSelect: (conversation: AiConversationSummary | null) => void
  onNewChat: () => void
  onChanged: () => void
}) {
  const { t } = useTranslation("ai")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const [query, setQuery] = useState("")
  const [renaming, setRenaming] = useState<AiConversationSummary | null>(null)
  const [renameValue, setRenameValue] = useState("")
  const [renameBusy, setRenameBusy] = useState(false)

  const groups = useMemo(() => {
    const filtered = query.trim() === ""
      ? conversations
      : conversations.filter((c) => c.title.toLowerCase().includes(query.trim().toLowerCase()))

    const buckets: { key: string; items: AiConversationSummary[] }[] = [
      { key: "pinned", items: [] },
      { key: "today", items: [] },
      { key: "yesterday", items: [] },
      { key: "week", items: [] },
      { key: "older", items: [] },
    ]

    const now = new Date()
    const startOfDay = (offsetDays: number) => {
      const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() - offsetDays)
      return d.getTime()
    }

    for (const conversation of filtered) {
      if (conversation.pinned) {
        buckets[0].items.push(conversation)
        continue
      }
      const at = new Date(conversation.last_message_at ?? conversation.created_at).getTime()
      if (at >= startOfDay(0)) buckets[1].items.push(conversation)
      else if (at >= startOfDay(1)) buckets[2].items.push(conversation)
      else if (at >= startOfDay(7)) buckets[3].items.push(conversation)
      else buckets[4].items.push(conversation)
    }

    return buckets.filter((bucket) => bucket.items.length > 0)
  }, [conversations, query])

  const togglePin = (conversation: AiConversationSummary) => {
    void apiFetch(`/ai/conversations/${conversation.id}`, {
      method: "PATCH",
      body: { pinned: !conversation.pinned },
    }).then(onChanged)
  }

  const remove = (conversation: AiConversationSummary) => {
    confirmDelete(async () => {
      await apiFetch(`/ai/conversations/${conversation.id}`, { method: "DELETE" })
      if (selectedId === conversation.id) onSelect(null)
      onChanged()
    }, t("session.deleteConfirm"))
  }

  const saveRename = async () => {
    if (renaming === null || renameValue.trim() === "") return
    setRenameBusy(true)
    try {
      await apiFetch(`/ai/conversations/${renaming.id}`, {
        method: "PATCH",
        body: { title: renameValue.trim() },
      })
      setRenaming(null)
      onChanged()
    } finally {
      setRenameBusy(false)
    }
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="space-y-1.5 p-3">
        <button
          type="button"
          onClick={onNewChat}
          className="pressable flex w-full items-center gap-2.5 rounded-xl border bg-card px-3 py-2 text-sm font-medium shadow-xs transition-colors hover:bg-accent"
        >
          <SquarePen className="size-4" /> {t("newChat")}
        </button>
        <div className="relative">
          <Search className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t("searchPlaceholder")}
            className="h-9 border-transparent bg-muted/50 ps-8 shadow-none"
          />
        </div>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
        {loading ? (
          <div className="space-y-2 px-1 pt-1">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-9 w-full rounded-lg" />
            ))}
          </div>
        ) : groups.length === 0 ? (
          <div className="pt-8">
            <EmptyState icon={Sparkles} title={t("pickTitle")} description={t("pickBody")} compact />
          </div>
        ) : (
          groups.map((group) => (
            <div key={group.key} className="mb-2">
              <p className="px-2 pt-2 pb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                {t(`groups.${group.key}`)}
              </p>
              {group.items.map((conversation) => (
                <div
                  key={conversation.id}
                  className={cn(
                    "group flex w-full items-center gap-1 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-accent",
                    selectedId === conversation.id && "bg-accent",
                  )}
                >
                  <button
                    type="button"
                    onClick={() => onSelect(conversation)}
                    className="min-w-0 flex-1 truncate text-start"
                    title={conversation.title}
                  >
                    {conversation.pinned && (
                      <Pin className="me-1 inline size-3 text-muted-foreground" />
                    )}
                    {conversation.title}
                  </button>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 data-[state=open]:opacity-100"
                        aria-label={t("session.rename")}
                      >
                        <MoreHorizontal className="size-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem
                        onClick={() => {
                          setRenaming(conversation)
                          setRenameValue(conversation.title)
                        }}
                      >
                        <Pencil className="size-4" /> {t("session.rename")}
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={() => togglePin(conversation)}>
                        {conversation.pinned ? (
                          <>
                            <PinOff className="size-4" /> {t("session.unpin")}
                          </>
                        ) : (
                          <>
                            <Pin className="size-4" /> {t("session.pin")}
                          </>
                        )}
                      </DropdownMenuItem>
                      <DropdownMenuItem variant="destructive" onClick={() => remove(conversation)}>
                        <Trash2 className="size-4" /> {t("session.delete")}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              ))}
            </div>
          ))
        )}
      </div>

      <Dialog open={renaming !== null} onOpenChange={(open) => !open && setRenaming(null)}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>{t("session.renameTitle")}</DialogTitle>
          </DialogHeader>
          <Input
            value={renameValue}
            onChange={(e) => setRenameValue(e.target.value)}
            maxLength={120}
            onKeyDown={(e) => {
              if (e.key === "Enter") void saveRename()
            }}
          />
          <DialogFooter>
            <Button onClick={() => void saveRename()} loading={renameBusy} disabled={renameValue.trim() === ""}>
              {t("session.renameSave")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {confirmDialog}
    </div>
  )
}
