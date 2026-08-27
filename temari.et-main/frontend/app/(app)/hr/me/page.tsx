"use client"

import { Ban, CalendarOff, Check, ClipboardList, Clock3, UserCheck, X } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { LeaveRequestSheet } from "@/components/hr/leave-request-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { fmtWeekday } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type {
  EmployeeLeaveBalances,
  LeaveRequest,
  LeaveRequestStatus,
  LeaveType,
  Locale,
  MyAttendanceRecord,
  Paginated,
  EmployeeAttendanceStatus,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const STATUS_BADGES: Record<LeaveRequestStatus, string> = {
  pending: "bg-warning/10 text-warning border-transparent",
  approved: "bg-success/10 text-success border-transparent",
  rejected: "bg-destructive/10 text-destructive border-transparent",
  cancelled: "bg-muted text-muted-foreground border-transparent",
}

const STATUS_TILES: Record<
  LeaveRequestStatus,
  { icon: typeof Clock3; className: string }
> = {
  pending: { icon: Clock3, className: "bg-warning/10 text-warning" },
  approved: { icon: Check, className: "bg-success/10 text-success" },
  rejected: { icon: X, className: "bg-destructive/10 text-destructive" },
  cancelled: { icon: Ban, className: "bg-muted text-muted-foreground" },
}

const REQUEST_STATUSES: LeaveRequestStatus[] = [
  "pending",
  "approved",
  "rejected",
  "cancelled",
]

const ATTENDANCE_STATUSES: EmployeeAttendanceStatus[] = [
  "present",
  "late",
  "half_day",
  "absent",
  "excused",
]

/** Dot + chip colors for the 30-day strip and status filters. */
const ATTENDANCE_DOTS: Record<EmployeeAttendanceStatus, string> = {
  present: "bg-success",
  late: "bg-warning",
  half_day: "bg-warning/50",
  absent: "bg-destructive",
  excused: "bg-info",
}

const ATTENDANCE_BADGES: Record<EmployeeAttendanceStatus, string> = {
  present: "bg-success/10 text-success border-transparent",
  late: "bg-warning/10 text-warning border-transparent",
  half_day: "bg-warning/10 text-warning border-transparent",
  absent: "bg-destructive/10 text-destructive border-transparent",
  excused: "bg-info/10 text-info border-transparent",
}

/** Local-timezone ISO date — `toISOString()` would shift the day near midnight. */
function localIso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(
    d.getDate(),
  ).padStart(2, "0")}`
}

function weekdayLabel(dateIso: string, locale: Locale): string {
  return fmtWeekday(dateIso, true, locale)
}

/** Quiet filter pill used by both panels — click the active one to reset. */
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
      aria-pressed={active}
      className={cn(
        "pressable inline-flex h-7 items-center gap-1.5 rounded-full border px-2.5 text-xs font-medium transition-colors",
        active
          ? "border-primary/25 bg-primary/10 text-primary"
          : "border-transparent bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground",
      )}
    >
      {children}
    </button>
  )
}

/**
 * Staff self-service: my balances, my requests, my attendance. Everything on
 * this page resolves server-side to the signed-in user's own staff profile —
 * it never accepts an employee id.
 */
export default function MyHrPage() {
  const { t, locale } = useTranslation("hr")
  const { active } = useSchoolContext()

  const [balances, setBalances] = useState<EmployeeLeaveBalances[] | null>(null)
  const [requests, setRequests] = useState<LeaveRequest[] | null>(null)
  const [attendance, setAttendance] = useState<MyAttendanceRecord[] | null>(null)
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([])
  const [noProfile, setNoProfile] = useState(false)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [version, setVersion] = useState(0)

  const [requestFilter, setRequestFilter] = useState<LeaveRequestStatus | null>(null)
  const [attendanceFilter, setAttendanceFilter] = useState<EmployeeAttendanceStatus | null>(
    null,
  )

  const hasBranch = active.branchId != null

  useEffect(() => {
    if (!hasBranch) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on scope change
    setNoProfile(false)

    apiFetch<{ data: EmployeeLeaveBalances[] }>("/hr/leave-balances?mine=1")
      .then((res) => !cancelled && setBalances(res.data))
      .catch((error) => {
        if (cancelled) return
        if (error instanceof ApiError && error.status === 422) setNoProfile(true)
        setBalances([])
      })
    apiFetch<Paginated<LeaveRequest>>("/hr/leave-requests?mine=1&per_page=25")
      .then((res) => !cancelled && setRequests(res.data))
      .catch(() => !cancelled && setRequests([]))
    apiFetch<{ data: MyAttendanceRecord[] }>("/hr/attendance/mine")
      .then((res) => !cancelled && setAttendance(res.data))
      .catch(() => !cancelled && setAttendance([]))
    apiFetch<{ data: LeaveType[] }>("/hr/leave-types")
      .then((res) => !cancelled && setLeaveTypes(res.data))
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [hasBranch, active.branchId, version])

  async function cancelRequest(request: LeaveRequest) {
    try {
      await apiFetch(`/hr/leave-requests/${request.id}/cancel`, { method: "POST" })
      toast.success(t("leave.cancelled"))
      setVersion((v) => v + 1)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    }
  }

  const myBalances = balances?.[0]?.balances ?? []

  const requestCounts = useMemo(() => {
    const counts = {} as Record<LeaveRequestStatus, number>
    for (const r of requests ?? []) counts[r.status] = (counts[r.status] ?? 0) + 1
    return counts
  }, [requests])

  const visibleRequests = useMemo(
    () => (requests ?? []).filter((r) => !requestFilter || r.status === requestFilter),
    [requests, requestFilter],
  )

  const attendanceByDate = useMemo(() => {
    const map = new Map<string, MyAttendanceRecord>()
    for (const r of attendance ?? []) map.set(r.date, r)
    return map
  }, [attendance])

  /** The last 30 calendar days, oldest first, for the dot strip. */
  const last30Days = useMemo(() => {
    return Array.from({ length: 30 }, (_, i) => {
      const d = new Date()
      d.setDate(d.getDate() - (29 - i))
      return localIso(d)
    })
  }, [])

  const attendanceCounts = useMemo(() => {
    const counts = {} as Record<EmployeeAttendanceStatus, number>
    for (const r of attendance ?? []) counts[r.status] = (counts[r.status] ?? 0) + 1
    return counts
  }, [attendance])

  const visibleAttendance = useMemo(
    () =>
      (attendance ?? []).filter(
        (r) => !attendanceFilter || r.status === attendanceFilter,
      ),
    [attendance, attendanceFilter],
  )

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("me.title")}
        description={t("me.subtitle")}
        actions={
          hasBranch && !noProfile ? (
            <Button className="h-11" onClick={() => setSheetOpen(true)}>
              {t("me.requestLeave")}
            </Button>
          ) : undefined
        }
      />

      {!hasBranch ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("me.noBranch")}
          </div>
        </div>
      ) : noProfile ? (
        <div className="page-gutter">
          <div className="rounded-2xl border bg-card shadow-xs">
            <EmptyState icon={ClipboardList} title={t("me.noProfile")} />
          </div>
        </div>
      ) : (
        <div className="page-gutter mx-auto w-full max-w-5xl space-y-6">
          {/* ── Leave balances ─────────────────────────────────────────── */}
          <section className="space-y-3">
            <h2 className="font-display text-base font-semibold">
              {t("me.balancesTitle")}
            </h2>
            {balances === null ? (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton key={i} className="h-28 rounded-2xl" />
                ))}
              </div>
            ) : myBalances.length === 0 ? (
              <div className="rounded-2xl border border-dashed px-6 py-8 text-center text-sm text-muted-foreground">
                {t("me.balancesEmpty")}
              </div>
            ) : (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                {myBalances.map((b) => {
                  const takenPct =
                    b.entitled && b.entitled > 0
                      ? Math.min(100, (b.taken / b.entitled) * 100)
                      : 0
                  const pendingPct =
                    b.entitled && b.entitled > 0
                      ? Math.min(100 - takenPct, (b.pending / b.entitled) * 100)
                      : 0
                  return (
                    <div
                      key={b.leave_type_id}
                      className="flex flex-col rounded-2xl border bg-card p-4 shadow-xs"
                    >
                      <div className="flex items-baseline justify-between gap-2">
                        <p className="min-w-0 truncate text-[13px] text-muted-foreground">
                          {b.leave_type_name}
                        </p>
                        <span className="shrink-0 text-[11px] font-medium text-muted-foreground/70">
                          {b.is_paid ? t("leave.paid") : t("leave.unpaid")}
                        </span>
                      </div>
                      <p className="font-display mt-1 text-2xl font-semibold tracking-tight tabular-nums">
                        {b.entitled == null
                          ? b.taken
                          : t("leave.balances.remaining", { days: b.remaining ?? 0 })}
                      </p>
                      {b.entitled != null && (
                        <div className="mt-2.5 flex h-1.5 overflow-hidden rounded-full bg-muted">
                          <div
                            className="h-full bg-primary"
                            style={{ width: `${takenPct}%` }}
                          />
                          <div
                            className="h-full bg-warning/60"
                            style={{ width: `${pendingPct}%` }}
                          />
                        </div>
                      )}
                      <p className="mt-2 text-xs text-muted-foreground tabular-nums">
                        {b.entitled == null
                          ? t("leave.balances.unlimited")
                          : t("leave.balances.taken", {
                              taken: b.taken,
                              entitled: b.entitled,
                            })}
                        {b.pending > 0 && (
                          <span className="text-warning">
                            {" "}
                            · {t("leave.balances.pending", { days: b.pending })}
                          </span>
                        )}
                      </p>
                    </div>
                  )
                })}
              </div>
            )}
          </section>

          <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-5">
            {/* ── My requests ──────────────────────────────────────────── */}
            <section className="overflow-hidden rounded-2xl border bg-card shadow-xs lg:col-span-3">
              <header className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                <h2 className="font-display text-base font-semibold">
                  {t("me.requestsTitle")}
                </h2>
                {(requests?.length ?? 0) > 0 && (
                  <div className="flex flex-wrap items-center gap-1.5">
                    <FilterPill
                      active={requestFilter === null}
                      onClick={() => setRequestFilter(null)}
                    >
                      {t("me.requestsFilterAll")}
                      <span className="tabular-nums opacity-70">{requests?.length}</span>
                    </FilterPill>
                    {REQUEST_STATUSES.filter((s) => (requestCounts[s] ?? 0) > 0).map(
                      (s) => (
                        <FilterPill
                          key={s}
                          active={requestFilter === s}
                          onClick={() =>
                            setRequestFilter((prev) => (prev === s ? null : s))
                          }
                        >
                          {t(`leave.statuses.${s}`)}
                          <span className="tabular-nums opacity-70">
                            {requestCounts[s]}
                          </span>
                        </FilterPill>
                      ),
                    )}
                  </div>
                )}
              </header>

              {requests === null ? (
                <div className="space-y-3 p-4">
                  {Array.from({ length: 3 }).map((_, i) => (
                    <Skeleton key={i} className="h-14 rounded-xl" />
                  ))}
                </div>
              ) : requests.length === 0 ? (
                <EmptyState
                  icon={CalendarOff}
                  title={t("me.requestsEmpty")}
                  action={
                    <Button size="sm" onClick={() => setSheetOpen(true)}>
                      {t("me.requestLeave")}
                    </Button>
                  }
                  compact
                />
              ) : visibleRequests.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                  {t("me.filterEmpty")}
                </p>
              ) : (
                <ul className="divide-y">
                  {visibleRequests.map((request) => {
                    const tile = STATUS_TILES[request.status]
                    const TileIcon = tile.icon
                    return (
                      <li
                        key={request.id}
                        className="flex flex-wrap items-center gap-3 px-4 py-3"
                      >
                        <span
                          className={cn(
                            "flex size-9 shrink-0 items-center justify-center rounded-xl",
                            tile.className,
                          )}
                        >
                          <TileIcon className="size-4" strokeWidth={2} />
                        </span>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">
                            {request.leave_type_name}
                            {request.is_half_day && (
                              <span className="text-xs font-normal text-muted-foreground">
                                {" "}
                                · {t("leave.halfDay")}
                              </span>
                            )}
                          </p>
                          <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                            {request.start_date === request.end_date
                              ? request.start_date
                              : `${request.start_date} → ${request.end_date}`}{" "}
                            · {request.days} {t("leave.columns.days").toLowerCase()}
                          </p>
                          {request.decision_note && (
                            <p className="mt-1 border-l-2 border-border pl-2 text-xs text-muted-foreground italic">
                              {request.decision_note}
                            </p>
                          )}
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                          <Badge className={STATUS_BADGES[request.status]}>
                            {t(`leave.statuses.${request.status}`)}
                          </Badge>
                          {request.status === "pending" && (
                            <Button
                              variant="ghost"
                              size="sm"
                              className="text-destructive"
                              onClick={() => cancelRequest(request)}
                            >
                              {t("leave.actions.cancel")}
                            </Button>
                          )}
                        </div>
                      </li>
                    )
                  })}
                </ul>
              )}
            </section>

            {/* ── My attendance ────────────────────────────────────────── */}
            <section className="overflow-hidden rounded-2xl border bg-card shadow-xs lg:col-span-2">
              <header className="flex items-baseline justify-between gap-2 border-b px-4 py-3">
                <h2 className="font-display text-base font-semibold">
                  {t("me.attendanceTitle")}
                </h2>
                <span className="text-xs text-muted-foreground">
                  {t("me.attendanceHint")}
                </span>
              </header>

              {attendance === null ? (
                <div className="space-y-3 p-4">
                  <Skeleton className="h-8 rounded-lg" />
                  <Skeleton className="h-40 rounded-xl" />
                </div>
              ) : attendance.length === 0 ? (
                <EmptyState icon={UserCheck} title={t("me.attendanceEmpty")} compact />
              ) : (
                <>
                  <div className="space-y-3 border-b px-4 py-3.5">
                    {/* 30-day strip: one square per calendar day, oldest first */}
                    <div className="flex flex-wrap gap-1">
                      {last30Days.map((date) => {
                        const record = attendanceByDate.get(date)
                        return (
                          <span
                            key={date}
                            title={`${date} · ${
                              record
                                ? t(`attendance.statuses.${record.status}`)
                                : t("me.notRecorded")
                            }`}
                            className={cn(
                              "size-3.5 rounded-[5px]",
                              record ? ATTENDANCE_DOTS[record.status] : "bg-muted",
                            )}
                          />
                        )
                      })}
                    </div>
                    {/* Status chips double as list filters */}
                    <div className="flex flex-wrap gap-1.5">
                      {ATTENDANCE_STATUSES.filter(
                        (s) => (attendanceCounts[s] ?? 0) > 0,
                      ).map((s) => (
                        <FilterPill
                          key={s}
                          active={attendanceFilter === s}
                          onClick={() =>
                            setAttendanceFilter((prev) => (prev === s ? null : s))
                          }
                        >
                          <span
                            className={cn("size-2 rounded-full", ATTENDANCE_DOTS[s])}
                          />
                          {t(`attendance.statuses.${s}`)}
                          <span className="tabular-nums opacity-70">
                            {attendanceCounts[s]}
                          </span>
                        </FilterPill>
                      ))}
                    </div>
                  </div>

                  {visibleAttendance.length === 0 ? (
                    <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                      {t("me.filterEmpty")}
                    </p>
                  ) : (
                    <ul className="divide-y">
                      {visibleAttendance.map((record) => (
                        <li key={record.date} className="px-4 py-2.5 text-sm">
                          <div className="flex items-center justify-between gap-3">
                            <span className="min-w-0 truncate tabular-nums">
                              <span className="text-muted-foreground">
                                {weekdayLabel(record.date, locale)}
                              </span>{" "}
                              {record.date}
                            </span>
                            <span className="flex shrink-0 items-center gap-3">
                              {(record.check_in || record.check_out) && (
                                <span className="text-xs text-muted-foreground tabular-nums">
                                  {record.check_in ?? "—"} – {record.check_out ?? "—"}
                                </span>
                              )}
                              <Badge className={ATTENDANCE_BADGES[record.status]}>
                                {t(`attendance.statuses.${record.status}`)}
                              </Badge>
                            </span>
                          </div>
                          {record.note && (
                            <p className="mt-1 truncate text-xs text-muted-foreground italic">
                              {record.note}
                            </p>
                          )}
                        </li>
                      ))}
                    </ul>
                  )}
                </>
              )}
            </section>
          </div>
        </div>
      )}

      <LeaveRequestSheet
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        onSaved={() => setVersion((v) => v + 1)}
        leaveTypes={leaveTypes}
      />
    </div>
  )
}
