"use client"

import { ExternalLink, X } from "lucide-react"
import dynamic from "next/dynamic"
import { useRouter } from "next/navigation"
import * as React from "react"
import { toast } from "sonner"

import { conversationSubtitle, conversationTitle } from "@/components/chat/labels"
import { useChatBase } from "@/components/chat/use-chat"
import { Button } from "@/components/ui/button"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Sheet, SheetClose, SheetContent, SheetTitle } from "@/components/ui/sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { useMediaQuery } from "@/hooks/use-media-query"
import { ApiError, apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { ChatConversation, ParentRow } from "@/lib/types"

// The thread is heavy (composer, voice notes, reactions…) — load it only
// when a chat actually opens, never as part of a table page's bundle.
const ChatThread = dynamic(
  () => import("@/components/chat/chat-thread").then((m) => m.ChatThread),
  {
    ssr: false,
    loading: () => (
      <div className="space-y-4 p-4">
        {[...Array(4)].map((_, i) => (
          <Skeleton key={i} className={i % 2 === 1 ? "ml-auto h-12 w-52 rounded-2xl" : "h-12 w-52 rounded-2xl"} />
        ))}
      </div>
    ),
  },
)

export type ChatLaunchChild = {
  student_id: number
  full_name: string
  grade_level?: string | null
  branch?: string | null
}

/**
 * What a chat button points at. Per ADR-019 there is no staff↔parent-user
 * direct: "message a parent" ALWAYS means the child's family thread, so a
 * parent target resolves to a student first (child picker when several).
 */
export type ChatLaunchTarget =
  | { kind: "user"; userId: number; name?: string | null }
  | { kind: "student"; studentId: number; name?: string | null }
  | { kind: "parent"; parentId: number; name?: string | null; children?: ChatLaunchChild[] }

type LauncherContextValue = {
  /** False outside the provider or for family-lane (/me) users. */
  available: boolean
  openChat: (target: ChatLaunchTarget) => Promise<void>
  /** Whether a chat button for this target makes sense (never yourself). */
  canTarget: (target: ChatLaunchTarget) => boolean
}

const LauncherContext = React.createContext<LauncherContextValue>({
  available: false,
  openChat: async () => undefined,
  canTarget: () => false,
})

export function useChatLauncher(): LauncherContextValue {
  return React.useContext(LauncherContext)
}

/**
 * One app-wide way to jump into a direct chat from anywhere a person is
 * shown (tables, contact popovers, profiles). Reuses the chat engine's
 * find-or-create direct endpoint — desktop opens the thread in a side
 * sheet without leaving the page; mobile pushes the full-screen thread
 * at /messages, like the native app flow.
 */
export function ChatLauncherProvider({ children }: { children: React.ReactNode }) {
  const { t } = useTranslation("chat")
  const { user } = useAuth()
  const router = useRouter()
  const base = useChatBase()
  // Same breakpoint as the /messages split pane.
  const isDesktop = useMediaQuery("(min-width: 768px)")

  const [conversation, setConversation] = React.useState<ChatConversation | null>(null)
  const [threadOpen, setThreadOpen] = React.useState(false)
  const [picker, setPicker] = React.useState<{ name?: string | null; children: ChatLaunchChild[] } | null>(null)
  const [pickerOpen, setPickerOpen] = React.useState(false)
  const busyRef = React.useRef(false)

  const available = base === "/chat"

  const createDirect = React.useCallback(
    async (body: { user_id?: number; student_id?: number }) => {
      if (busyRef.current) return
      busyRef.current = true
      try {
        const res = await apiFetch<{ data: ChatConversation }>("/chat/conversations", {
          method: "POST",
          body: { kind: "direct", ...body },
        })
        setPickerOpen(false)
        if (isDesktop) {
          setConversation(res.data)
          setThreadOpen(true)
        } else {
          router.push(`/messages?c=${res.data.id}`)
        }
      } catch (error) {
        toast.error(error instanceof ApiError ? error.message : t("launcher.failed"))
      } finally {
        busyRef.current = false
      }
    },
    [isDesktop, router, t],
  )

  const openChat = React.useCallback(
    async (target: ChatLaunchTarget) => {
      if (target.kind === "user") {
        if (target.userId === user?.id) return
        await createDirect({ user_id: target.userId })
        return
      }

      if (target.kind === "student") {
        await createDirect({ student_id: target.studentId })
        return
      }

      // Parent target → resolve to a child's family thread.
      let kids = target.children
      if (kids === undefined) {
        try {
          const res = await apiFetch<{ data: ParentRow }>(`/parents/${target.parentId}`)
          kids = (res.data.children ?? []).map((c) => ({
            student_id: c.student_id,
            full_name: c.full_name,
            grade_level: c.grade_level,
            branch: c.branch,
          }))
        } catch (error) {
          toast.error(error instanceof ApiError ? error.message : t("launcher.failed"))
          return
        }
      }

      if (kids.length === 0) {
        toast.info(t("launcher.noChildren"))
        return
      }

      if (kids.length === 1) {
        await createDirect({ student_id: kids[0].student_id })
        return
      }

      setPicker({ name: target.name, children: kids })
      setPickerOpen(true)
    },
    [createDirect, t, user?.id],
  )

  const canTarget = React.useCallback(
    (target: ChatLaunchTarget): boolean =>
      available && !(target.kind === "user" && target.userId === user?.id),
    [available, user?.id],
  )

  const value = React.useMemo(
    () => ({ available, openChat, canTarget }),
    [available, openChat, canTarget],
  )

  const title = conversation ? conversationTitle(conversation, t) : ""
  const subtitle = conversation ? conversationSubtitle(conversation, t) : null

  function openFull() {
    if (!conversation) return
    setThreadOpen(false)
    router.push(`/messages?c=${conversation.id}`)
  }

  return (
    <LauncherContext.Provider value={value}>
      {children}

      {/* Child picker — a parent with several children anchors per student. */}
      <ResponsiveSheet open={pickerOpen} onOpenChange={setPickerOpen}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("launcher.pickChildTitle")}</ResponsiveSheetTitle>
            <ResponsiveSheetDescription>
              {t("launcher.pickChildBody", { name: picker?.name ?? "" })}
            </ResponsiveSheetDescription>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody>
            <div className="space-y-2">
              {(picker?.children ?? []).map((child) => (
                <button
                  key={child.student_id}
                  type="button"
                  className="pressable flex min-h-14 w-full items-center gap-3 rounded-xl border bg-card px-3 py-2 text-left transition-colors hover:bg-accent"
                  onClick={() => void createDirect({ student_id: child.student_id })}
                >
                  <PersonAvatar name={child.full_name} />
                  <span className="min-w-0">
                    <span className="block truncate text-sm font-medium">{child.full_name}</span>
                    {(child.grade_level || child.branch) && (
                      <span className="block truncate text-xs text-muted-foreground">
                        {[child.grade_level, child.branch].filter(Boolean).join(" · ")}
                      </span>
                    )}
                  </span>
                </button>
              ))}
            </div>
          </ResponsiveSheetBody>
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Desktop quick-chat sheet — mobile routes to /messages instead. */}
      <Sheet
        open={threadOpen}
        onOpenChange={(open) => {
          setThreadOpen(open)
          if (!open) setConversation(null)
        }}
      >
        <SheetContent
          showCloseButton={false}
          aria-describedby={undefined}
          className="gap-0 p-0 data-[side=right]:sm:max-w-lg"
        >
          <SheetTitle className="sr-only">{t("launcher.title")}</SheetTitle>
          {conversation && (
            <>
              <div className="flex items-center gap-2.5 border-b px-3 py-2.5">
                <PersonAvatar name={title || "?"} photoUrl={conversation.display?.avatar_url} className="size-9" />
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-semibold">{title}</span>
                  {subtitle && <span className="block truncate text-xs text-muted-foreground">{subtitle}</span>}
                </span>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-9 rounded-full"
                  onClick={openFull}
                  aria-label={t("launcher.openFull")}
                  title={t("launcher.openFull")}
                >
                  <ExternalLink className="size-4" />
                </Button>
                <SheetClose asChild>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="size-9 rounded-full"
                    aria-label={t("actions.close")}
                    title={t("actions.close")}
                  >
                    <X className="size-4.5" />
                  </Button>
                </SheetClose>
              </div>
              <div className="min-h-0 flex-1">
                <ChatThread conversationId={conversation.id} embedded hideHeader />
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>
    </LauncherContext.Provider>
  )
}
