"use client"

import { History, Sparkles, SquarePen } from "lucide-react"
import { useRouter, useSearchParams } from "next/navigation"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { useWorkspaceSurface } from "@/components/app-shell/use-workspace-surface"
import { AiHome } from "@/components/ai/ai-home"
import { AiThread, type AiInitialMessage } from "@/components/ai/ai-thread"
import { createAiConversation, useAiContext, useAiConversations } from "@/components/ai/use-ai"
import { SessionRail } from "@/components/ai/session-rail"
import { Button } from "@/components/ui/button"
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet"
import { useMediaQuery } from "@/hooks/use-media-query"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { AiConversationSummary, AiLane, AiSurface } from "@/lib/types"
import { cn } from "@/lib/utils"

/** Legacy deep links (`/ai?lane=teacher&q=…`) map to the lane's surface. */
const LANE_SURFACE: Record<AiLane, AiSurface> = {
  student: "family",
  parent: "family",
  teacher: "school",
  leadership: "school",
  registrar: "school",
  finance: "school",
  platform: "platform",
}

/**
 * Temari AI — the ChatGPT-style surface. Desktop: chats sidebar | full-bleed
 * pane (home with an always-visible composer, or the open thread). Mobile:
 * the home IS the screen (composer immediately available); history opens as
 * a drawer and threads push a full-screen overlay — native-app navigation,
 * URL-synced via ?c=.
 */
export function AiScreen() {
  const { t } = useTranslation("ai")
  const { user } = useAuth()
  const router = useRouter()
  const searchParams = useSearchParams()
  const isDesktop = useMediaQuery("(min-width: 768px)")

  const { context, loading: contextLoading, refresh: refreshContext } = useAiContext()
  const { conversations, loading: listLoading, refresh } = useAiConversations()
  const [starting, setStarting] = useState(false)
  const [historyOpen, setHistoryOpen] = useState(false)
  const [pendingInitial, setPendingInitial] = useState<AiInitialMessage | null>(null)

  const assistants = useMemo(() => context?.assistants ?? [], [context])

  // The workspace decides the assistant — no picker. An explicit
  // ?surface= pin wins; otherwise the ACTIVE workspace surface picks:
  // family/tutoring workspaces get the family assistant, the school
  // workspace a staff assistant — so a dual-hat director who opens /ai
  // from My family never lands on the school copilot.
  const surfaceParam = searchParams.get("surface") as AiSurface | null
  const workspaceSurface = useWorkspaceSurface()
  const assistant = useMemo(() => {
    const bySurface = (s: AiSurface) => assistants.find((a) => a.surface === s)
    if (surfaceParam) return bySurface(surfaceParam) ?? assistants[0] ?? null
    if (workspaceSurface !== "staff") return bySurface("family") ?? assistants[0] ?? null
    return assistants.find((a) => a.surface !== "family") ?? assistants[0] ?? null
  }, [assistants, surfaceParam, workspaceSurface])

  // Only the active assistant's lane exists here: school AI history never
  // lists (or opens) inside the family workspace, and vice versa — a
  // cross-lane ?c= deep link simply falls back to the home screen.
  const laneConversations = useMemo(
    () => conversations.filter((c) => c.surface === assistant?.surface),
    [conversations, assistant],
  )

  const selectedId = searchParams.get("c") ? Number(searchParams.get("c")) : null
  const selected = useMemo(
    () => laneConversations.find((c) => c.id === selectedId) ?? null,
    [laneConversations, selectedId],
  )

  const select = useCallback(
    (conversation: AiConversationSummary | null) => {
      router.replace(conversation ? `/ai?c=${conversation.id}` : "/ai", { scroll: false })
    },
    [router],
  )

  // Home composer → create the session and drop into the thread; the typed
  // message rides along and auto-sends there.
  const start = useCallback(
    async (surface: AiSurface, text: string, files: File[]) => {
      setStarting(true)
      try {
        const conversation = await createAiConversation(surface)
        setPendingInitial({ conversationId: conversation.id, text, files })
        await refresh()
        select(conversation)
      } catch (error) {
        toast.error(error instanceof Error ? error.message : t("thread.error"))
      } finally {
        setStarting(false)
      }
    },
    [refresh, select, t],
  )

  // After an exchange the server names a fresh chat with a queued job that
  // runs a beat later — refresh once, then keep polling briefly until the
  // "New chat" placeholder is replaced (otherwise the title only appears on
  // the next page load).
  const refreshUntilTitled = useCallback(
    async (conversationId: number) => {
      for (let attempt = 0; attempt < 8; attempt++) {
        const list = await refresh()
        const convo = list?.find((c) => c.id === conversationId)
        if (!convo || convo.title !== "New chat") return
        await new Promise((resolve) => setTimeout(resolve, 1500))
      }
    },
    [refresh],
  )

  // Deep link from other pages (e.g. "Create with AI" on /lms/exams):
  // /ai?lane=teacher&q=… (legacy lane param → its surface) auto-starts a
  // session with the given prompt. Fires once; if the surface is not
  // available here, the query is ignored and the user lands on the AI home.
  const autoStartRef = useRef(false)
  useEffect(() => {
    if (autoStartRef.current || contextLoading || starting || selectedId !== null) return
    const lane = searchParams.get("lane") as AiLane | null
    const q = searchParams.get("q")
    const surface = lane ? LANE_SURFACE[lane] : null
    if (!surface || !q || !assistants.some((a) => a.surface === surface)) return
    autoStartRef.current = true
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-shot kickoff of the deep-linked prompt
    void start(surface, q, [])
  }, [assistants, contextLoading, searchParams, selectedId, start, starting])
  const entitlementFor = useCallback(
    (surface: AiSurface) => assistants.find((a) => a.surface === surface)?.entitlement ?? null,
    [assistants],
  )

  const newChat = useCallback(() => {
    setHistoryOpen(false)
    select(null)
  }, [select])

  const firstName = user?.name?.split(" ")[0] ?? null

  const home = (
    <AiHome
      assistant={assistant}
      loading={contextLoading}
      sending={starting}
      userName={firstName}
      maxAttachments={context?.limits.max_attachments ?? 3}
      desktop={isDesktop}
      onStart={(text, files) => assistant && void start(assistant.surface, text, files)}
    />
  )

  const thread = selected !== null && (
    <AiThread
      key={selected.id}
      conversation={selected}
      entitlement={entitlementFor(selected.surface)}
      maxAttachments={context?.limits.max_attachments ?? 3}
      initial={pendingInitial?.conversationId === selected.id ? pendingInitial : null}
      onInitialConsumed={() => setPendingInitial(null)}
      onChanged={() => void refreshUntilTitled(selected.id)}
      onQuotaChanged={refreshContext}
      onBack={!isDesktop ? () => select(null) : undefined}
    />
  )

  const rail = (onPicked?: () => void) => (
    <SessionRail
      conversations={laneConversations}
      loading={listLoading}
      selectedId={selectedId}
      onSelect={(conversation) => {
        onPicked?.()
        select(conversation)
      }}
      onNewChat={newChat}
      onChanged={() => void refresh()}
    />
  )

  return (
    <div className="-mt-6 flex h-[calc(100svh-10.85rem-env(safe-area-inset-top))] overflow-hidden md:-mb-8 md:h-svh">
      {/* Desktop chats sidebar */}
      <aside className="hidden w-72 shrink-0 flex-col border-e bg-muted/20 md:flex lg:w-80">
        <div className="flex items-center gap-2 px-4 pt-4 pb-1">
          <Sparkles className="size-4 text-primary" />
          <h1 className="font-display text-lg font-semibold tracking-tight">{t("title")}</h1>
        </div>
        {rail()}
      </aside>

      {/* Main pane */}
      <div className={cn("flex min-w-0 flex-1 flex-col", selected !== null && !isDesktop && "hidden")}>
        {/* Mobile top bar: history drawer + new chat */}
        <div className="flex items-center gap-1 px-2 py-1.5 md:hidden">
          <Button
            variant="ghost"
            size="icon"
            className="size-9 rounded-full"
            onClick={() => setHistoryOpen(true)}
            title={t("history")}
            aria-label={t("history")}
          >
            <History className="size-5" />
          </Button>
          <span className="flex items-center gap-1.5 font-display text-sm font-semibold tracking-tight">
            <Sparkles className="size-4 text-primary" /> {t("title")}
          </span>
          <Button
            variant="ghost"
            size="icon"
            className="ms-auto size-9 rounded-full"
            onClick={newChat}
            title={t("newChat")}
            aria-label={t("newChat")}
          >
            <SquarePen className="size-5" />
          </Button>
        </div>

        {selected !== null && isDesktop ? thread : home}
      </div>

      {/* Mobile: an open thread pushes a full-screen overlay (native-app). */}
      {selected !== null && !isDesktop && (
        <div className="fixed inset-0 z-50 flex flex-col bg-background pt-safe pb-safe md:hidden">
          {thread}
        </div>
      )}

      {/* Mobile history drawer */}
      <Sheet open={historyOpen} onOpenChange={setHistoryOpen}>
        <SheetContent side="left" className="flex w-full max-w-[85vw] flex-col gap-0 p-0">
          <SheetHeader className="px-4 pt-4 pb-0">
            <SheetTitle className="flex items-center gap-2 text-base">
              <Sparkles className="size-4 text-primary" /> {t("history")}
            </SheetTitle>
          </SheetHeader>
          {rail(() => setHistoryOpen(false))}
        </SheetContent>
      </Sheet>
    </div>
  )
}
