"use client"

import type { AppNotification } from "@/lib/types"
import { useLocale } from "@/lib/i18n"
import { cn } from "@/lib/utils"

import { CATEGORY_META, FALLBACK_META, timeAgo } from "./meta"

/**
 * One feed row, shared by the bell popover and the /notifications page.
 * Unread rows carry a tinted background + dot; the whole row is one tap
 * target (44px+) that marks-read-and-navigates.
 */
export function NotificationRow({
  notification,
  onOpen,
  dense = false,
}: {
  notification: AppNotification
  onOpen: (notification: AppNotification) => void
  dense?: boolean
}) {
  const { locale } = useLocale()
  const meta = CATEGORY_META[notification.category] ?? FALLBACK_META
  const Icon = meta.icon
  const unread = notification.read_at === null

  return (
    <button
      type="button"
      onClick={() => onOpen(notification)}
      className={cn(
        "pressable flex w-full items-start gap-3 rounded-xl px-3 text-left transition-colors",
        dense ? "py-2.5" : "py-3",
        unread ? "bg-primary/[0.04] hover:bg-primary/[0.07]" : "hover:bg-muted/60",
      )}
    >
      <span
        className={cn(
          "mt-0.5 flex shrink-0 items-center justify-center rounded-full",
          dense ? "size-8" : "size-9",
          meta.bubble,
        )}
        aria-hidden
      >
        <Icon className={dense ? "size-4" : "size-[18px]"} strokeWidth={2} />
      </span>

      <span className="min-w-0 flex-1">
        <span className="flex items-baseline justify-between gap-2">
          <span
            className={cn(
              "truncate text-sm",
              unread ? "font-semibold" : "font-medium text-foreground/90",
            )}
          >
            {notification.title}
          </span>
          <span className="shrink-0 text-[11px] tabular-nums text-muted-foreground">
            {timeAgo(notification.created_at, locale)}
          </span>
        </span>
        <span
          className={cn(
            "mt-0.5 block text-xs leading-snug text-muted-foreground",
            dense ? "line-clamp-1" : "line-clamp-2",
          )}
        >
          {notification.body}
        </span>
      </span>

      {unread && (
        <span className="mt-2 size-2 shrink-0 rounded-full bg-primary" aria-hidden />
      )}
    </button>
  )
}
