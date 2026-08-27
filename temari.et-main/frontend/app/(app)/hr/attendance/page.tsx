"use client"

import { CalendarOff, PenLine, ScanLine, StickyNote, UserCheck } from "lucide-react"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { TimePicker } from "@/components/ui/time-picker"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { JOB_TITLES } from "@/lib/data"
import { useTranslation } from "@/lib/i18n"
import type {
  EmployeeAttendanceRosterEntry,
  EmployeeAttendanceStatus,
} from "@/lib/types"
import { cn } from "@/lib/utils"
import { addisToday } from "@/lib/dates"

const STATUSES: EmployeeAttendanceStatus[] = [
  "present",
  "late",
  "half_day",
  "absent",
  "excused",
]

// Status conventions per DESIGN.md §3: green = present, amber = late/partial,
// red = absent, blue/neutral = informational.
const STATUS_STYLES: Record<EmployeeAttendanceStatus, string> = {
  present: "bg-success text-white border-success",
  late: "bg-warning text-white border-warning",
  half_day: "bg-warning text-white border-warning",
  absent: "bg-destructive text-white border-destructive",
  excused: "bg-info text-white border-info",
}

interface RosterMeta {
  date: string
  is_weekend: boolean
  holiday: { id: number; name: string } | null
}

function today(): string {
  // "Today" on the Addis wall clock — UTC is a day behind between 21:00 and
  // midnight local, and the register must line up with device scans.
  return addisToday()
}

export default function EmployeeAttendancePage() {
  const { t } = useTranslation("hr")
  const { t: ts } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [date, setDate] = useState<string>(today())
  const [roster, setRoster] = useState<EmployeeAttendanceRosterEntry[] | null>(null)
  const [meta, setMeta] = useState<RosterMeta | null>(null)
  const [saving, setSaving] = useState(false)
  // Unsaved local edits pause live refresh so a device sync never clobbers them.
  const dirtyRef = useRef(false)

  const canRecord = permissions.includes("employee_attendance.record")
  const hasBranch = active.branchId != null

  // School-wide workspace: the register is one-branch-at-a-time, so school
  // managers pick which branch's roster to take (no workspace switch needed).
  const { needsBranch } = useBranchScope()
  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && pickedBranchId != null)
  const branchParam = !hasBranch && pickedBranchId != null ? `&branch_id=${pickedBranchId}` : ""

  useEffect(() => {
    if (!branchReady || !date) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on date/branch change
    setRoster(null)
    dirtyRef.current = false
    apiFetch<{ data: EmployeeAttendanceRosterEntry[]; meta: RosterMeta }>(
      `/hr/attendance?date=${date}${branchParam}`
    )
      .then((res) => {
        if (cancelled) return
        setRoster(res.data)
        setMeta(res.meta)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(
          error instanceof ApiError ? error.message : t("attendance.loadFailed")
        )
        setRoster([])
      })
    return () => {
      cancelled = true
    }
  }, [branchReady, branchParam, date, active.branchId, t])

  // Today's register refreshes itself so RFID-gate marks stream in while the
  // page is open — paused the moment there are unsaved edits.
  const isToday = date === today()
  useEffect(() => {
    if (!branchReady || !isToday) return
    const id = setInterval(() => {
      if (dirtyRef.current) return
      apiFetch<{ data: EmployeeAttendanceRosterEntry[]; meta: RosterMeta }>(
        `/hr/attendance?date=${date}${branchParam}`
      )
        .then((res) => {
          if (!dirtyRef.current) {
            setRoster(res.data)
            setMeta(res.meta)
          }
        })
        .catch(() => {})
    }, 45000)
    return () => clearInterval(id)
  }, [branchReady, branchParam, date, isToday])

  function patchEntry(
    employeeId: number,
    patch: Partial<EmployeeAttendanceRosterEntry>
  ) {
    dirtyRef.current = true
    setRoster((prev) =>
      (prev ?? []).map((r) =>
        r.employee_id === employeeId ? { ...r, ...patch } : r
      )
    )
  }

  function markAllPresent() {
    dirtyRef.current = true
    setRoster((prev) =>
      (prev ?? []).map((r) =>
        r.on_leave ? r : { ...r, status: r.status ?? "present" }
      )
    )
  }

  async function save() {
    const marked = (roster ?? []).filter((r) => r.status != null)
    if (marked.length === 0) return
    setSaving(true)
    try {
      await apiFetch("/hr/attendance", {
        method: "POST",
        body: {
          ...(!hasBranch && pickedBranchId != null ? { branch_id: pickedBranchId } : {}),
          date,
          records: marked.map((r) => ({
            employee_id: r.employee_id,
            status: r.status,
            check_in: r.check_in || undefined,
            check_out: r.check_out || undefined,
            note: r.note || undefined,
          })),
        },
      })
      dirtyRef.current = false
      toast.success(t("attendance.saved"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    } finally {
      setSaving(false)
    }
  }

  const markedCount = (roster ?? []).filter((r) => r.status != null).length

  // DataTable rows: id + the scalar the status filter matches against.
  const rows = useMemo(
    () =>
      (roster ?? []).map((r) => ({
        ...r,
        id: r.employee_id,
        status_key: r.on_leave ? "on_leave" : (r.status ?? "unmarked"),
      })),
    [roster]
  )

  const statusLabel = (key: string) =>
    key === "on_leave"
      ? t("attendance.onLeave")
      : key === "unmarked"
        ? t("attendance.unmarked")
        : t(`attendance.statuses.${key}`)

  const columns: DataTableColumn<EmployeeAttendanceRosterEntry>[] = [
    {
      key: "employee_name",
      label: t("leave.columns.employee"),
      sortable: true,
      primary: true,
      // Name + phone with the tap-to-act Call / Copy popover — same
      // behaviour as the director cell on the branches table.
      render: (row) =>
        row.phone ? (
          <ContactActionCell value={row.phone} name={row.employee_name}>
            <div className="text-left leading-tight">
              <span className="block text-sm font-medium">{row.employee_name}</span>
              <span className="block font-mono text-xs text-muted-foreground tabular-nums">
                {row.phone}
              </span>
            </div>
          </ContactActionCell>
        ) : (
          <span className="text-sm font-medium">{row.employee_name}</span>
        ),
      exportValue: (row) => row.employee_name,
    },
    {
      key: "job_titles",
      label: ts("columns.jobTitle"),
      mobileHidden: true,
      render: (row) =>
        row.job_titles.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {row.job_titles.map((code) => (
              <Badge key={code} variant="secondary" className="text-[11px]">
                {ts(`jobTitles.${code}`)}
              </Badge>
            ))}
          </div>
        ) : (
          "—"
        ),
      exportValue: (row) =>
        row.job_titles.map((code) => ts(`jobTitles.${code}`)).join(", "),
    },
    {
      key: "status_key",
      label: t("leave.columns.status"),
      sortable: true,
      render: (row) =>
        row.on_leave ? (
          <Badge className="border-transparent bg-info/10 text-info whitespace-nowrap">
            {t("attendance.onLeaveUntil", {
              type: row.on_leave.leave_type_name ?? t("attendance.onLeave"),
              date: row.on_leave.until,
            })}
          </Badge>
        ) : (
          <div className="flex gap-1 whitespace-nowrap">
            {STATUSES.map((status) => (
              <button
                key={status}
                type="button"
                disabled={!canRecord}
                onClick={(e) => {
                  e.stopPropagation()
                  patchEntry(row.employee_id, { status })
                }}
                className={cn(
                  "pressable min-h-8 rounded-full border px-2.5 text-xs font-medium transition-colors disabled:opacity-60",
                  row.status === status
                    ? STATUS_STYLES[status]
                    : "bg-background text-muted-foreground hover:bg-muted"
                )}
              >
                {t(`attendance.statuses.${status}`)}
              </button>
            ))}
          </div>
        ),
      exportValue: (row) => statusLabel(row.status_key ?? "unmarked"),
    },
    {
      key: "check_in",
      label: t("attendance.checkIn"),
      render: (row) => (
        <TimePicker
          value={row.check_in ?? ""}
          disabled={!canRecord || !!row.on_leave || row.status == null}
          onClick={(e) => e.stopPropagation()}
          onChange={(value) =>
            patchEntry(row.employee_id, { check_in: value || null })
          }
          className="h-9 w-40 text-sm"
        />
      ),
      exportValue: (row) => row.check_in ?? "",
    },
    {
      key: "check_out",
      label: t("attendance.checkOut"),
      render: (row) => (
        <TimePicker
          value={row.check_out ?? ""}
          disabled={!canRecord || !!row.on_leave || row.status == null}
          onClick={(e) => e.stopPropagation()}
          onChange={(value) =>
            patchEntry(row.employee_id, { check_out: value || null })
          }
          className="h-9 w-40 text-sm"
        />
      ),
      exportValue: (row) => row.check_out ?? "",
    },
    {
      key: "source",
      label: t("attendance.source.label"),
      mobileHidden: true,
      // Only saved marks carry a source — schools audit device vs manual here.
      render: (row) =>
        row.source ? (
          <span
            className={cn(
              "inline-flex items-center gap-1.5 text-xs whitespace-nowrap",
              row.source === "device" ? "text-info" : "text-muted-foreground"
            )}
          >
            {row.source === "device" ? (
              <ScanLine className="size-3.5" strokeWidth={1.75} />
            ) : (
              <PenLine className="size-3.5" strokeWidth={1.75} />
            )}
            {t(`attendance.source.${row.source}`)}
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">—</span>
        ),
      exportValue: (row) => (row.source ? t(`attendance.source.${row.source}`) : ""),
    },
    {
      key: "note",
      label: t("attendance.note"),
      render: (row) => {
        const editable = canRecord && !row.on_leave && row.status != null

        return (
          <Popover>
            <PopoverTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                disabled={!editable && !row.note}
                aria-label={t("attendance.note")}
                onClick={(e) => e.stopPropagation()}
                className={cn(row.note ? "text-primary" : "text-muted-foreground")}
              >
                <StickyNote className="size-4" strokeWidth={1.75} />
              </Button>
            </PopoverTrigger>
            <PopoverContent
              align="end"
              className="w-72 space-y-2"
              onClick={(e) => e.stopPropagation()}
            >
              <p className="text-sm font-medium">
                {t("attendance.note")} — {row.employee_name}
              </p>
              <textarea
                value={row.note ?? ""}
                readOnly={!editable}
                rows={3}
                autoFocus
                onChange={(e) =>
                  patchEntry(row.employee_id, { note: e.target.value || null })
                }
                placeholder={t("attendance.notePlaceholder")}
                className="w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              />
            </PopoverContent>
          </Popover>
        )
      },
      exportValue: (row) => row.note ?? "",
    },
  ]

  const filterDefs: DataTableFilter[] = [
    {
      key: "status_key",
      label: tc("filters.status"),
      options: [
        ...STATUSES.map((s) => ({
          label: t(`attendance.statuses.${s}`),
          value: s,
        })),
        { label: t("attendance.unmarked"), value: "unmarked" },
        { label: t("attendance.onLeave"), value: "on_leave" },
      ],
    },
    {
      key: "job_titles",
      label: ts("columns.jobTitle"),
      options: JOB_TITLES.map((code) => ({
        label: ts(`jobTitles.${code}`),
        value: code,
      })),
    },
  ]

  return (
    <div className="space-y-6">
      {/* Date lives in the header's action slot (top right on desktop,
          stacked full-width under the title on mobile) — same as the
          student attendance page. */}
      <PageHeader
        title={t("attendance.title")}
        description={t("attendance.subtitle")}
        actions={
          branchReady ? (
            <div className="min-w-0 flex-1 space-y-1.5 sm:w-44 sm:flex-none">
              <label className="text-sm font-medium">{t("attendance.date")}</label>
              <DatePicker
                value={date}
                onChange={(value) => setDate(value)}
                max={today()}
                clearable={false}
                className="w-full"
              />
            </div>
          ) : undefined
        }
      />

      {/* School-wide: which branch's register to take. */}
      {needsBranch && (
        <div className="page-gutter">
          <BranchScopePicker value={pickedBranchId} onChange={setPickedBranchId} />
        </div>
      )}

      {!branchReady ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("attendance.noBranch")}
          </div>
        </div>
      ) : (
        <>
          {/* Summary + actions */}
          <div className="page-gutter flex flex-wrap items-center justify-end gap-3">
            {roster !== null && roster.length > 0 && (
              <div className="flex items-center gap-2">
                {isToday && (
                  <span className="inline-flex items-center gap-1.5 text-xs text-success">
                    <span className="size-1.5 animate-pulse rounded-full bg-success" />
                    {t("attendance.live")}
                  </span>
                )}
                <span className="text-sm text-muted-foreground tabular-nums">
                  {t("attendance.summary", {
                    marked: markedCount,
                    total: roster.length,
                  })}
                </span>
                {canRecord && (
                  <>
                    <Button variant="outline" size="sm" onClick={markAllPresent}>
                      {t("attendance.markAllPresent")}
                    </Button>
                    <Button
                      size="sm"
                      onClick={save}
                      loading={saving} disabled={markedCount === 0}
                    >
                      {t("attendance.save")}
                    </Button>
                  </>
                )}
              </div>
            )}
          </div>

          {/* Holiday / weekend context banner */}
          {meta && (meta.holiday || meta.is_weekend) && (
            <div className="page-gutter">
              <div className="flex items-center gap-2.5 rounded-xl bg-info/10 px-4 py-3 text-sm text-info">
                <CalendarOff className="size-4 shrink-0" strokeWidth={1.75} />
                {meta.holiday
                  ? t("attendance.holiday", { name: meta.holiday.name })
                  : t("attendance.weekend")}
              </div>
            </div>
          )}

          {roster !== null && roster.length === 0 ? (
            <div className="page-gutter">
              <div className="rounded-2xl border bg-card shadow-xs">
                <EmptyState icon={UserCheck} title={t("attendance.empty")} compact />
              </div>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={rows}
              loading={roster === null}
              searchKeys={["employee_name", "phone"]}
              searchPlaceholder={tc("actions.search")}
              filters={filterDefs}
              emptyMessage={t("attendance.empty")}
              exportFilename={`employee-attendance-${date}`}
            />
          )}
        </>
      )}
    </div>
  )
}
