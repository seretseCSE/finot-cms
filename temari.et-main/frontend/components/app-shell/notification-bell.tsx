"use client"

import Link from "next/link"
import { useRouter } from "next/navigation"
import { Bell, CheckCheck } from "lucide-react"
import { useCallback, useEffect, useState } from "react"

import { NotificationRow } from "@/components/notifications/notification-row"
import { Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AppNotification } from "@/lib/types"
import { cn } from "@/lib/utils"

import { useNotifications } from "./notifications-provider"

/**
 * The bell. Mobile (`variant="link"`): a badge-carrying icon that goes
 * straight to the app-like /notifications screen — no popover on a phone.
 * Desktop (`variant="popover"`): the eight freshest rows inline, mark-all,
 * and a "view all" tail. Both read one shared unread poller.
 */
export function NotificationBell({
  variant = "popover",
  className,
}: {
  variant?: "popover" | "link"
  className?: string
}) {
  const { t } = useTranslation("common")
  const { unread, bump, refresh } = useNotifications()
  const router = useRouter()
  const [open, setOpen] = useState(false)
  const [items, setItems] = useState<AppNotification[] | null>(null)

  // Fetch fresh rows each time the popover opens (cheap: 8 rows).
  useEffect(() => {
    if (!open) return
    let cancelled = false
    apiFetch<{ data: AppNotification[] }>("/me/notifications?per_page=8")
      .then((res) => !cancelled && setItems(res.data))
      .catch(() => !cancelled && setItems([]))
    return () => {
      cancelled = true
    }
  }, [open])

  const openNotification = useCallback(
    (notification: AppNotification) => {
      setOpen(false)
      if (notification.read_at === null) {
        bump(-1)
        apiFetch(`/me/notifications/${notification.id}/read`, { method: "POST" }).catch(() => {})
      }
      router.push(notification.link ?? "/notifications")
    },
    [bump, router],
  )

  const markAllRead = useCallback(() => {
    bump("clear")
    setItems((current) =>
      current?.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })) ?? null,
    )
    apiFetch("/me/notifications/read-all", { method: "POST" }).then(refresh).catch(() => {})
  }, [bump, refresh])

  const badge =
    unread > 0 ? (
      <span
        className="bg-destructive text-destructive-foreground absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold leading-none"
        aria-hidden
      >
        {unread > 99 ? "99+" : unread}
      </span>
    ) : null

  const label = t("notifications.title")

  if (variant === "link") {
    return (
      <Link
        href="/notifications"
        aria-label={label}
        title={label}
        className={cn(
          "pressable text-muted-foreground hover:text-foreground relative flex size-9 items-center justify-center rounded-lg transition-colors",
          className,
        )}
      >
        <Bell className="size-5" strokeWidth={2} />
        {badge}
      </Link>
    )
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label={label}
          title={label}
          className={cn(
            "pressable text-muted-foreground hover:text-foreground hover:bg-sidebar-accent relative flex size-9 items-center justify-center rounded-lg transition-colors",
            className,
          )}
        >
          <Bell className="size-[18px]" strokeWidth={2} />
          {badge}
        </button>
      </PopoverTrigger>
      <PopoverContent align="start" side="right" sideOffset={10} className="w-[380px] p-0">
        <div className="flex items-center justify-between border-b px-4 py-2.5">
          <p className="text-sm font-semibold">{label}</p>
          {unread > 0 && (
            <Button variant="ghost" size="sm" className="h-7 gap-1.5 px-2 text-xs" onClick={markAllRead}>
              <CheckCheck className="size-3.5" />
              {t("notifications.markAllRead")}
            </Button>
          )}
        </div>

        <div className="max-h-[420px] overflow-y-auto p-1.5">
          {items === null ? (
            <div className="space-y-2 p-2">
              <Skeleton className="h-12 w-full rounded-xl" />
              <Skeleton className="h-12 w-full rounded-xl" />
              <Skeleton className="h-12 w-full rounded-xl" />
            </div>
          ) : items.length === 0 ? (
            <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
              <Bell className="size-7 text-muted-foreground/50" strokeWidth={1.5} />
              <p className="text-sm font-medium">{t("notifications.emptyTitle")}</p>
              <p className="text-xs text-muted-foreground">{t("notifications.emptyBody")}</p>
            </div>
          ) : (
            items.map((notification) => (
              <NotificationRow
                key={notification.id}
                notification={notification}
                onOpen={openNotification}
                dense
              />
            ))
          )}
        </div>

        <div className="border-t p-1.5">
          <Button
            asChild
            variant="ghost"
            className="h-9 w-full justify-center text-sm font-medium text-primary hover:text-primary"
            onClick={() => setOpen(false)}
          >
            <Link href="/notifications">{t("notifications.viewAll")}</Link>
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}
