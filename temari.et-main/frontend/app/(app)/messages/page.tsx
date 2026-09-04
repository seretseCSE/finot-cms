"use client"

import { MessagesSquare, NotebookText } from "lucide-react"

import { Button } from "@/components/ui/button"
import { useRouter, useSearchParams } from "next/navigation"
import { Suspense, useCallback, useEffect, useState } from "react"

import { ApprovalsChip, ConversationList } from "@/components/chat/conversation-list"
import { ChatThread } from "@/components/chat/chat-thread"
import { NewChatDialog } from "@/components/chat/new-chat-dialog"
import { useChatBase, useConversations } from "@/components/chat/use-chat"
import { EmptyState } from "@/components/ui/empty-state"
import { useMediaQuery } from "@/hooks/use-media-query"
import { apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * Messages (ADR-019). Desktop: split pane (rail | thread). Mobile: the rail
 * is the screen; opening a thread pushes a full-screen overlay above the
 * bottom nav — native-app navigation, URL-synced via ?c= so notification
 * deep links land in the right thread.
 */
export default function MessagesPage() {
  return (
    <Suspense fallback={null}>
      <MessagesScreen />
    </Suspense>
  )
}

function MessagesScreen() {
  const { t } = useTranslation("chat")
  const router = useRouter()
  const searchParams = useSearchParams()
  const permissions = useEffectivePermissions()
  const base = useChatBase()

  const { conversations, loading, refresh } = useConversations()
  const [newChatOpen, setNewChatOpen] = useState(false)
  const [pendingCount, setPendingCount] = useState(0)
  // ONE ChatThread mounts at a time — CSS-only hiding would leave two live
  // instances polling and marking reads in parallel.
  const isDesktop = useMediaQuery("(min-width: 768px)")

  const selectedId = searchParams.get("c") ? Number(searchParams.get("c")) : null
  const canModerate = base === "/chat" && permissions.includes("chat.moderate")

  const select = useCallback(
    (conversation: { id: number } | null) => {
      router.replace(conversation ? `/messages?c=${conversation.id}` : "/messages", { scroll: false })
    },
    [router],
  )

  // The communication book badge for moderators.
  useEffect(() => {
    if (!canModerate) return
    let cancelled = false
    const load = () =>
      apiFetch<{ data: unknown[] }>("/chat/approvals")
        .then((res) => {
          if (!cancelled) setPendingCount(res.data.length)
        })
        .catch(() => undefined)
    void load()
    const timer = window.setInterval(load, 60_000)
    return () => {
      cancelled = true
      window.clearInterval(timer)
    }
  }, [canModerate])

  return (
    <div className="-mt-6 flex h-[calc(100svh-10.85rem-env(safe-area-inset-top))] overflow-hidden md:-mb-8 md:h-svh">
      {/* Rail — full-bleed sidebar (the whole screen on mobile). */}
      <div
        className={cn(
          "flex h-full w-full flex-col md:w-90 md:shrink-0 md:border-e md:bg-muted/20 lg:w-100",
          selectedId !== null && "hidden md:flex",
        )}
      >
        <div className="flex items-center justify-between px-4 pt-2 md:pt-4">
          <h1 className="font-display text-xl font-semibold tracking-tight md:text-lg">{t("title")}</h1>
          {canModerate && (
            <Button
              variant="ghost"
              size="icon-sm"
              className="text-muted-foreground"
              onClick={() => router.push("/messages/templates")}
              aria-label={t("templates.manage")}
              title={t("templates.manage")}
            >
              <NotebookText className="size-4" />
            </Button>
          )}
        </div>
        <ConversationList
          conversations={conversations}
          loading={loading}
          selectedId={selectedId}
          onSelect={select}
          onNewChat={() => setNewChatOpen(true)}
          approvalsSlot={
            canModerate ? (
              <ApprovalsChip count={pendingCount} onOpen={() => router.push("/messages/approvals")} />
            ) : undefined
          }
        />
      </div>

      {/* Thread — desktop pane, full-bleed. */}
      <div className="hidden min-w-0 flex-1 md:block">
        {selectedId !== null && isDesktop ? (
          <ChatThread key={selectedId} conversationId={selectedId} embedded onChanged={refresh} />
        ) : (
          <div className="flex h-full items-center justify-center">
            <EmptyState icon={MessagesSquare} title={t("pickTitle")} description={t("pickBody")} compact />
          </div>
        )}
      </div>

      {/* Thread — mobile full-screen push (covers the bottom nav, z-40). */}
      {selectedId !== null && !isDesktop && (
        <div className="fixed inset-0 z-50 flex flex-col bg-background pt-safe pb-safe md:hidden">
          <ChatThread key={selectedId} conversationId={selectedId} onBack={() => select(null)} onChanged={refresh} />
        </div>
      )}

      <NewChatDialog
        open={newChatOpen}
        onOpenChange={setNewChatOpen}
        onCreated={(conversation) => {
          void refresh()
          select(conversation)
        }}
      />
    </div>
  )
}
