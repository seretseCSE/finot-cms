"use client"

import Link from "next/link"

import { LogoMark } from "@/components/ui/logo"
import { cn } from "@/lib/utils"

import { GlobalSearchTrigger } from "./global-search"
import { NotificationBell } from "./notification-bell"
import { useWorkspaceSurface } from "./use-workspace-surface"
import { WorkspaceSwitcher } from "./workspace-switcher"

/**
 * Top app bar shown only on mobile (the desktop sidebar is hidden below `md`).
 * Brand mark + workspace switcher; profile, language, theme and sign-out live
 * in the bottom "Menu" sheet.
 */
export function MobileHeader({ className }: { className?: string }) {
  // The brand mark goes home WITHIN the active workspace — a parent never
  // gets bounced into the staff dashboard by tapping the logo.
  const surface = useWorkspaceSurface()
  const home = surface === "family" ? "/me" : surface === "tutor" ? "/tutoring" : "/dashboard"
  return (
    <div
      className={cn(
        "bg-background/90 supports-[backdrop-filter]:bg-background/70 pt-safe sticky top-0 z-30 border-b backdrop-blur-xl md:hidden",
        className,
      )}
    >
      <div className="flex items-center gap-2 px-3 py-2">
        <Link href={home} aria-label="Temari home" className="pressable shrink-0">
          <LogoMark size="sm" />
        </Link>
        <div className="min-w-0 flex-1">
          <WorkspaceSwitcher compact />
        </div>
        <GlobalSearchTrigger variant="icon" />
        <NotificationBell variant="link" />
      </div>
    </div>
  )
}
