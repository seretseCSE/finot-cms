"use client"

import { ChevronLeft, ChevronRight, Mail, MessageSquareText } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { AttendanceNotificationRow } from "@/lib/types"
import { cn } from "@/lib/utils"

import { ScopeSelects, type ScopeValue } from "./scope-selects"

/**
 * The guardian-alert ledger: every absence/late SMS and email the school sent,
 * plus the month's SMS meter — schools see exactly what parents were told and
 * what their SMS bill is buying.
 */
export function MessagesTab() {
  const { t } = useTranslation("devices")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()

  const [scope, setScope] = useState<ScopeValue>({ schoolId: null, branchId: null })
  const [channel, setChannel] = useState<string>("all")
  const [date, setDate] = useState<string>("")
  const [page, setPage] = useState(1)
  const [rows, setRows] = useState<AttendanceNotificationRow[] | null>(null)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [smsThisMonth, setSmsThisMonth] = useState<number | null>(null)

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on filter change
    setRows(null)
    const params = new URLSearchParams({ page: String(page), per_page: "25" })
    if (scope.schoolId != null) params.set("school_id", String(scope.schoolId))
    if (scope.branchId != null) params.set("branch_id", String(scope.branchId))
    if (channel !== "all") params.set("channel", channel)
    if (date) params.set("date", date)
    apiFetch<{
      data: AttendanceNotificationRow[]
      meta: { last_page: number; total: number; sms_this_month: number }
    }>(`/attendance-notifications?${params}`)
      .then((res) => {
        if (cancelled) return
        setRows(res.data)
        setLastPage(res.meta.last_page)
        setTotal(res.meta.total)
        setSmsThisMonth(res.meta.sms_this_month)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
  }, [scope.schoolId, scope.branchId, channel, date, page, active.branchId, active.schoolId, tc])

  return (
    <div className="page-gutter space-y-4">
      {/* Meter + filters */}
      <div className="flex flex-wrap items-center gap-2">
        {smsThisMonth !== null && (
          <span className="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/5 px-3 py-1.5 text-xs font-medium text-primary tabular-nums">
            <MessageSquareText className="size-3.5" />
            {t("messages.meter", { count: smsThisMonth })}
          </span>
        )}
        <div className="ml-auto flex flex-wrap items-end gap-2">
          <ScopeSelects
            value={scope}
            onChange={(v) => {
              setScope(v)
              setPage(1)
            }}
            allOption
          />
          <Select
            value={channel}
            onValueChange={(v) => {
              setChannel(v)
              setPage(1)
            }}
          >
            <SelectTrigger className="h-9 w-36 text-xs">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("messages.allChannels")}</SelectItem>
              <SelectItem value="sms">SMS</SelectItem>
              <SelectItem value="email">{t("messages.email")}</SelectItem>
            </SelectContent>
          </Select>
          <DatePicker
            value={date}
            onChange={(value) => {
              setDate(value)
              setPage(1)
            }}
            className="h-9 sm:w-44"
          />
        </div>
      </div>

      {rows === null ? (
        <Skeleton className="h-72 w-full rounded-2xl" />
      ) : rows.length === 0 ? (
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState icon={MessageSquareText} title={t("messages.empty")} compact />
        </div>
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card shadow-xs">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/30 text-left text-xs text-muted-foreground">
                <th className="px-4 py-2.5 font-medium">{t("messages.columns.date")}</th>
                <th className="px-4 py-2.5 font-medium">{t("messages.columns.student")}</th>
                <th className="px-4 py-2.5 font-medium">{t("messages.columns.guardian")}</th>
                <th className="px-4 py-2.5 font-medium max-sm:hidden">
                  {t("messages.columns.about")}
                </th>
                <th className="px-4 py-2.5 font-medium">{t("messages.columns.channel")}</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {rows.map((row) => (
                <tr key={row.id}>
                  <td className="px-4 py-2 text-xs whitespace-nowrap tabular-nums">{row.date}</td>
                  <td className="px-4 py-2 text-sm font-medium">{row.student_name ?? "—"}</td>
                  <td className="px-4 py-2">
                    <ContactActionCell value={row.recipient} name={row.guardian_name ?? undefined}>
                      <div className="text-left leading-tight">
                        <span className="block text-sm">{row.guardian_name ?? "—"}</span>
                        <span className="block font-mono text-xs text-muted-foreground tabular-nums">
                          {row.recipient}
                        </span>
                      </div>
                    </ContactActionCell>
                  </td>
                  <td className="px-4 py-2 max-sm:hidden">
                    <Badge
                      className={cn(
                        "text-[11px] whitespace-nowrap",
                        row.status === "absent"
                          ? "border-transparent bg-destructive/10 text-destructive"
                          : "border-transparent bg-warning/10 text-warning",
                      )}
                    >
                      {t(`messages.about.${row.status}`)}
                    </Badge>
                  </td>
                  <td className="px-4 py-2">
                    <span className="inline-flex items-center gap-1.5 text-xs">
                      {row.channel === "sms" ? (
                        <MessageSquareText className="size-3.5 text-muted-foreground" />
                      ) : (
                        <Mail className="size-3.5 text-muted-foreground" />
                      )}
                      {row.channel === "sms" ? "SMS" : t("messages.email")}
                      {row.result === "failed" && (
                        <Badge className="border-transparent bg-destructive/10 text-[10px] text-destructive">
                          {t("messages.failed")}
                        </Badge>
                      )}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {lastPage > 1 && (
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground tabular-nums">
            {t("scans.total", { count: total })}
          </span>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="icon"
              className="size-8"
              disabled={page <= 1}
              onClick={() => setPage((p) => p - 1)}
              aria-label={tc("attachment.previous")}
            >
              <ChevronLeft className="size-4" />
            </Button>
            <span className="text-xs text-muted-foreground tabular-nums">
              {page} / {lastPage}
            </span>
            <Button
              variant="outline"
              size="icon"
              className="size-8"
              disabled={page >= lastPage}
              onClick={() => setPage((p) => p + 1)}
              aria-label={tc("attachment.next")}
            >
              <ChevronRight className="size-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
