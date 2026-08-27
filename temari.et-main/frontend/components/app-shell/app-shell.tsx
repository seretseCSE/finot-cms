"use client"

import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"

import { ChatLauncherProvider } from "@/components/chat/chat-launcher"
import { useAuth } from "@/lib/auth/auth-context"
import { SchoolProvider } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"

import { LogoMark } from "@/components/ui/logo"

import { BottomNav } from "./bottom-nav"
import { GlobalSearch } from "./global-search"
import { MobileHeader } from "./mobile-header"
import { NotificationsProvider } from "./notifications-provider"
import { PendingGuard } from "@/components/app-shell/pending-guard"
import { Sidebar } from "./sidebar"

const SIDEBAR_KEY = "sidebar_collapsed"

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth()
  const { t } = useTranslation("common")
  const router = useRouter()
  const [collapsed, setCollapsed] = useState(false)

  useEffect(() => {
    const stored = localStorage.getItem(SIDEBAR_KEY)
    // eslint-disable-next-line react-hooks/set-state-in-effect -- client-only storage hydration
    if (stored !== null) setCollapsed(stored === "true")
  }, [])

  function handleToggle() {
    setCollapsed((prev) => {
      const next = !prev
      localStorage.setItem(SIDEBAR_KEY, String(next))
      return next
    })
  }

  useEffect(() => {
    if (!loading && !user) {
      router.replace("/login")
    }
  }, [loading, user, router])

  if (loading || !user) {
    return (
      <div className="flex min-h-svh flex-col items-center justify-center gap-4">
        <LogoMark size="lg" className="animate-pulse" />
        <p className="text-muted-foreground text-sm">{t("states.loading")}</p>
      </div>
    )
  }

  return (
    <SchoolProvider>
      <NotificationsProvider>
        <ChatLauncherProvider>
          <div className="flex min-h-svh">
            <Sidebar collapsed={collapsed} onToggle={handleToggle} />
            <div className="flex min-w-0 flex-1 flex-col">
              <MobileHeader />
              <main className="flex-1 pb-28 pt-6 md:pb-8 md:pt-6">{children}</main>
              <GlobalSearch />
              <BottomNav />
            </div>
          </div>
          {/* Warns before leaving while saves/uploads are still in flight. */}
          <PendingGuard />
        </ChatLauncherProvider>
      </NotificationsProvider>
    </SchoolProvider>
  )
}
