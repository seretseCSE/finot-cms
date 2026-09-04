"use client"

import { ChevronLeft, ChevronRight, Radio } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
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
import type { Device, DeviceEventRow, DeviceEventStatus } from "@/lib/types"
import { cn } from "@/lib/utils"

import { ScopeSelects, type ScopeValue } from "./scope-selects"
import { fmtTime } from "@/lib/dates"

const STATUSES: DeviceEventStatus[] = [
  "processed",
  "pending",
  "unknown_card",
  "inactive_card",
  "no_enrollment",
  "closed_term",
]

const STATUS_TONE: Record<DeviceEventStatus, string> = {
  processed: "border-transparent bg-success/10 text-success",
  pending: "border-transparent bg-muted text-muted-foreground",
  unknown_card: "border-transparent bg-destructive/10 text-destructive",
  inactive_card: "border-transparent bg-warning/10 text-warning",
  no_enrollment: "border-transparent bg-warning/10 text-warning",
  closed_term: "border-transparent bg-muted text-muted-foreground",
}

function localTime(iso: string): string {
  return fmtTime(iso)
}

/**
 * The raw tap log — every scan a terminal reported with its processing
 * outcome. This is the admin's "is the gate actually working?" view.
 */
export function ScanLogTab() {
  const { t } = useTranslation("devices")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()

  const [devices, setDevices] = useState<Device[]>([])
  const [scope, setScope] = useState<ScopeValue>({ schoolId: null, branchId: null })
  const [deviceId, setDeviceId] = useState<string>("all")
  const [status, setStatus] = useState<string>("all")
  const [date, setDate] = useState<string>("")
  const [page, setPage] = useState(1)
  const [rows, setRows] = useState<DeviceEventRow[] | null>(null)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: Device[] }>("/devices")
      .then((res) => !cancelled && setDevices(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [active.branchId, active.schoolId])

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on filter change
    setRows(null)
    const params = new URLSearchParams({ page: String(page), per_page: "25" })
    if (scope.schoolId != null) params.set("school_id", String(scope.schoolId))
    if (scope.branchId != null) params.set("branch_id", String(scope.branchId))
    if (deviceId !== "all") params.set("device_id", deviceId)
    if (status !== "all") params.set("status", status)
    if (date) params.set("date", date)
    apiFetch<{
      data: DeviceEventRow[]
      meta: { last_page: number; total: number }
    }>(`/device-events?${params}`)
      .then((res) => {
        if (cancelled) return
        setRows(res.data)
        setLastPage(res.meta.last_page)
        setTotal(res.meta.total)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
  }, [scope.schoolId, scope.branchId, deviceId, status, date, page, active.branchId, active.schoolId, tc])

  return (
    <div className="page-gutter space-y-4">
      {/* Filters: school → branch → device, then outcome + day */}
      <div className="flex flex-wrap items-end gap-2">
        <ScopeSelects
          value={scope}
          onChange={(v) => {
            setScope(v)
            setDeviceId("all")
            setPage(1)
          }}
          allOption
        />
        <Select
          value={deviceId}
          onValueChange={(v) => {
            setDeviceId(v)
            setPage(1)
          }}
        >
          <SelectTrigger className="h-9 w-44 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t("scans.allDevices")}</SelectItem>
            {devices
              .filter((d) => scope.branchId == null || d.branch_id === scope.branchId)
              .filter((d) => scope.schoolId == null || d.school_id === scope.schoolId)
              .map((d) => (
                <SelectItem key={d.id} value={String(d.id)}>
                  {d.name}
                </SelectItem>
              ))}
          </SelectContent>
        </Select>
        <Select
          value={status}
          onValueChange={(v) => {
            setStatus(v)
            setPage(1)
          }}
        >
          <SelectTrigger className="h-9 w-44 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">{t("scans.allOutcomes")}</SelectItem>
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {t(`scans.statuses.${s}`)}
              </SelectItem>
            ))}
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
        <span className="ml-auto text-xs text-muted-foreground tabular-nums">
          {t("scans.total", { count: total })}
        </span>
      </div>

      {/* Log */}
      {rows === null ? (
        <Skeleton className="h-72 w-full rounded-2xl" />
      ) : rows.length === 0 ? (
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState icon={Radio} title={t("scans.empty")} compact />
        </div>
      ) : (
        <div className="overflow-x-auto rounded-2xl border bg-card shadow-xs">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/30 text-left text-xs text-muted-foreground">
                <th className="px-4 py-2.5 font-medium">{t("scans.columns.time")}</th>
                <th className="px-4 py-2.5 font-medium">{t("scans.columns.person")}</th>
                <th className="px-4 py-2.5 font-medium max-sm:hidden">{t("scans.columns.card")}</th>
                <th className="px-4 py-2.5 font-medium max-sm:hidden">{t("scans.columns.device")}</th>
                <th className="px-4 py-2.5 font-medium">{t("scans.columns.outcome")}</th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {rows.map((row) => (
                <tr key={row.id}>
                  <td className="px-4 py-2 whitespace-nowrap tabular-nums">
                    <span className="block">{localTime(row.scanned_at)}</span>
                    <span className="block text-xs text-muted-foreground">
                      {row.scanned_at.slice(0, 10)}
                    </span>
                  </td>
                  <td className="px-4 py-2">
                    <span className="block text-sm font-medium">{row.holder_name ?? "—"}</span>
                    {row.holder_type && (
                      <span className="block text-xs text-muted-foreground">
                        {t(`cards.holderTypes.${row.holder_type}`)}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-2 font-mono text-xs max-sm:hidden">{row.card_uid}</td>
                  <td className="px-4 py-2 text-xs max-sm:hidden">{row.device_name ?? "—"}</td>
                  <td className="px-4 py-2">
                    <Badge className={cn("text-[11px] whitespace-nowrap", STATUS_TONE[row.status])}>
                      {t(`scans.statuses.${row.status}`)}
                    </Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pager */}
      {lastPage > 1 && (
        <div className="flex items-center justify-end gap-2">
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
      )}
    </div>
  )
}
