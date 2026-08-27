"use client"

import { MessageSquareText } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { CatalogPillNav, useCatalogs } from "@/components/catalogs/catalogs-shell"
import { CATEGORY_META, FALLBACK_META } from "@/components/notifications/meta"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { NotificationCategory, NotificationEventRow } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The platform SMS whitelist (Temari.et staff): every notification event the
 * system can emit, grouped by category, with one switch — may it send SMS?
 * SMS costs real money per message, so this is an OPERATOR decision; in-app
 * and email behavior is code-defined and not editable here.
 */

interface EventsPayload {
  events: NotificationEventRow[]
  categories: NotificationCategory[]
}

/** "finance.invoice_issued" → "Invoice issued" (event keys are data, not UI copy). */
function humanize(event: string): string {
  const tail = event.split(".").pop() ?? event
  const text = tail.replaceAll("_", " ")
  return text.charAt(0).toUpperCase() + text.slice(1)
}

export default function NotificationEventsCatalogPage() {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { refreshOverview } = useCatalogs()

  const [rows, setRows] = useState<NotificationEventRow[] | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: EventsPayload }>("/catalogs/notification-events")
      .then((res) => !cancelled && setRows(res.data.events))
      .catch(() => !cancelled && setRows([]))
    return () => {
      cancelled = true
    }
  }, [])

  const groups = useMemo(() => {
    if (!rows) return []
    const byCategory = new Map<NotificationCategory, NotificationEventRow[]>()
    for (const row of rows) {
      byCategory.set(row.category, [...(byCategory.get(row.category) ?? []), row])
    }
    return [...byCategory.entries()]
  }, [rows])

  const smsCount = rows?.filter((r) => r.sms_enabled).length ?? 0

  async function toggle(event: string, enabled: boolean) {
    if (!rows || saving) return
    const previous = rows
    const next = rows.map((r) => (r.event === event ? { ...r, sms_enabled: enabled } : r))
    setRows(next)
    setSaving(true)
    try {
      await apiFetch("/catalogs/notification-events", {
        method: "PUT",
        body: { sms_whitelist: next.filter((r) => r.sms_enabled).map((r) => r.event) },
      })
      refreshOverview()
      toast.success(t("notifications.saved"))
    } catch (error) {
      setRows(previous)
      toast.error(error instanceof ApiError ? error.message : t("notifications.saved"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-4">
      <PageHeader title={t("notifications.title")} description={t("notifications.subtitle")} />

      <div className="lg:hidden">
        <CatalogPillNav />
      </div>

      <div className="page-gutter">
        <div className="max-w-3xl space-y-4">
          <div className="border-info/30 bg-info/5 text-foreground/90 flex items-start gap-3 rounded-2xl border p-4 text-sm">
            <MessageSquareText className="text-info mt-0.5 size-4 shrink-0" />
            <p>
              {t("notifications.costNote")}{" "}
              <span className="font-semibold tabular-nums">
                {t("notifications.enabledCount", { count: smsCount })}
              </span>
            </p>
          </div>

          {rows === null ? (
            <>
              <Skeleton className="h-44 w-full rounded-2xl" />
              <Skeleton className="h-44 w-full rounded-2xl" />
            </>
          ) : (
            groups.map(([category, events]) => {
              const meta = CATEGORY_META[category] ?? FALLBACK_META
              const Icon = meta.icon
              return (
                <Card key={category}>
                  <CardHeader className="pb-2">
                    <CardTitle className="flex items-center gap-2.5 text-base">
                      <span
                        className={cn("flex size-7 items-center justify-center rounded-full", meta.bubble)}
                        aria-hidden
                      >
                        <Icon className="size-3.5" strokeWidth={2} />
                      </span>
                      {tc(`notifications.categories.${category}`)}
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-0.5">
                    {events.map((row) => (
                      <div
                        key={row.event}
                        className="flex items-center justify-between gap-3 rounded-xl px-2 py-2.5 hover:bg-muted/40"
                      >
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-1.5">
                            <p className="text-sm font-medium">{humanize(row.event)}</p>
                            {row.severity === "critical" && (
                              <Badge variant="outline" className="border-destructive/40 text-destructive h-4.5 px-1.5 text-[10px]">
                                {t("notifications.critical")}
                              </Badge>
                            )}
                          </div>
                          <p className="text-muted-foreground truncate font-mono text-[11px]">{row.event}</p>
                        </div>
                        <Switch
                          checked={row.sms_enabled}
                          onCheckedChange={(checked) => toggle(row.event, checked)}
                          aria-label={`${humanize(row.event)} — SMS`}
                        />
                      </div>
                    ))}
                  </CardContent>
                </Card>
              )
            })
          )}
        </div>
      </div>
    </div>
  )
}
