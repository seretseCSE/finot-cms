"use client"

import { useRouter } from "next/navigation"
import { Bell, CheckCheck, Loader2 } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"

import { useNotifications } from "@/components/app-shell/notifications-provider"
import { NotificationRow } from "@/components/notifications/notification-row"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AppNotification, NotificationCategory } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

/**
 * The full notification feed — an app-like screen on mobile: filter pills
 * (all / unread / per category), day-grouped rows, tap = mark read + follow
 * the deep link, incremental "load more" (3G-first: 25 rows a page).
 */

const CATEGORY_FILTERS: NotificationCategory[] = [
  "approvals", "finance", "attendance", "academics", "lms", "movement", "hr", "family", "tutoring", "security", "system",
]

interface Meta {
  current_page: number
  last_page: number
  unread?: number
}

export default function NotificationsPage() {
  const { t } = useTranslation("common")
  const { unread, bump, refresh } = useNotifications()
  const router = useRouter()

  const [filter, setFilter] = useState<"all" | "unread" | NotificationCategory>("all")
  const [items, setItems] = useState<AppNotification[] | null>(null)
  const [meta, setMeta] = useState<Meta | null>(null)
  const [loadingMore, setLoadingMore] = useState(false)

  const query = useCallback(
    (page: number) => {
      const params = new URLSearchParams({ per_page: "25", page: String(page) })
      if (filter === "unread") params.set("filter", "unread")
      else if (filter !== "all") params.set("category", filter)
      return `/me/notifications?${params.toString()}`
    },
    [filter],
  )

  const changeFilter = useCallback((next: typeof filter) => {
    setFilter(next)
    setItems(null) // show skeletons while the refetch runs
  }, [])

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: AppNotification[]; meta: Meta }>(query(1))
      .then((res) => {
        if (cancelled) return
        setItems(res.data)
        setMeta(res.meta)
      })
      .catch(() => !cancelled && setItems([]))
    return () => {
      cancelled = true
    }
  }, [query])

  const loadMore = useCallback(() => {
    if (meta === null || meta.current_page >= meta.last_page || loadingMore) return
    setLoadingMore(true)
    apiFetch<{ data: AppNotification[]; meta: Meta }>(query(meta.current_page + 1))
      .then((res) => {
        setItems((current) => [...(current ?? []), ...res.data])
        setMeta(res.meta)
      })
      .catch(() => {})
      .finally(() => setLoadingMore(false))
  }, [meta, loadingMore, query])

  const openNotification = useCallback(
    (notification: AppNotification) => {
      if (notification.read_at === null) {
        bump(-1)
        setItems((current) =>
          current?.map((n) =>
            n.id === notification.id ? { ...n, read_at: new Date().toISOString() } : n,
          ) ?? null,
        )
        apiFetch(`/me/notifications/${notification.id}/read`, { method: "POST" }).catch(() => {})
      }
      if (notification.link) router.push(notification.link)
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

  // Day buckets: Today / Yesterday / localized date headers.
  const groups = useMemo(() => {
    if (!items) return []
    const dayLabel = (iso: string) => {
      const date = new Date(iso)
      const today = new Date()
      const yesterday = new Date(today)
      yesterday.setDate(today.getDate() - 1)
      if (date.toDateString() === today.toDateString()) return t("notifications.today")
      if (date.toDateString() === yesterday.toDateString()) return t("notifications.yesterday")
      return fmtDate(iso)
    }
    const buckets: { label: string; rows: AppNotification[] }[] = []
    for (const row of items) {
      const label = dayLabel(row.created_at)
      const last = buckets[buckets.length - 1]
      if (last && last.label === label) last.rows.push(row)
      else buckets.push({ label, rows: [row] })
    }
    return buckets
  }, [items, t])

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("notifications.title")}
        description={t("notifications.subtitle")}
        actions={
          unread > 0 ? (
            <Button variant="outline" size="sm" className="gap-1.5" onClick={markAllRead}>
              <CheckCheck className="size-4" />
              {t("notifications.markAllRead")}
            </Button>
          ) : undefined
        }
      />

      <div className="page-gutter">
        <div className="mx-auto max-w-2xl space-y-4">
          {/* Filter pills — horizontally scrollable on mobile. */}
          <div className="scrollbar-none -mx-1 flex gap-2 overflow-x-auto px-1 pb-0.5">
            {(["all", "unread"] as const).map((key) => (
              <FilterPill key={key} active={filter === key} onClick={() => changeFilter(key)}>
                {t(`notifications.filters.${key}`)}
                {key === "unread" && unread > 0 && (
                  <span className="bg-primary/15 text-primary ml-1 rounded-full px-1.5 text-[10px] font-bold tabular-nums">
                    {unread > 99 ? "99+" : unread}
                  </span>
                )}
              </FilterPill>
            ))}
            <span className="bg-border my-2 w-px shrink-0" aria-hidden />
            {CATEGORY_FILTERS.map((category) => (
              <FilterPill
                key={category}
                active={filter === category}
                onClick={() => changeFilter(category)}
              >
                {t(`notifications.categories.${category}`)}
              </FilterPill>
            ))}
          </div>

          {items === null ? (
            <div className="space-y-2">
              {Array.from({ length: 6 }).map((_, i) => (
                <Skeleton key={i} className="h-16 w-full rounded-xl" />
              ))}
            </div>
          ) : items.length === 0 ? (
            <EmptyState
              icon={Bell}
              title={t("notifications.emptyTitle")}
              description={t("notifications.emptyBody")}
            />
          ) : (
            <>
              {groups.map((group) => (
                <section key={group.label}>
                  <h2 className="text-muted-foreground px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider">
                    {group.label}
                  </h2>
                  <div className="bg-card space-y-0.5 rounded-2xl border p-1.5">
                    {group.rows.map((notification) => (
                      <NotificationRow
                        key={notification.id}
                        notification={notification}
                        onOpen={openNotification}
                      />
                    ))}
                  </div>
                </section>
              ))}

              {meta !== null && meta.current_page < meta.last_page && (
                <Button
                  variant="outline"
                  className="h-11 w-full"
                  onClick={loadMore}
                  disabled={loadingMore}
                >
                  {loadingMore && <Loader2 className="size-4 animate-spin" />}
                  {t("notifications.loadMore")}
                </Button>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}

function FilterPill({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        "pressable inline-flex min-h-9 shrink-0 items-center rounded-full border px-3.5 text-xs font-medium transition-colors",
        active
          ? "border-primary/40 bg-primary/10 text-primary"
          : "text-muted-foreground hover:bg-muted",
      )}
    >
      {children}
    </button>
  )
}
