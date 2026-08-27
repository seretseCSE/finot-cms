"use client"

import {
  BarChart3,
  CalendarOff,
  HandCoins,
  UserCheck,
  Users,
} from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { HrTrendCharts } from "@/components/hr/hr-trend-charts"
import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  HrAttendanceReportRow,
  HrOverviewReport,
  HrTrendsReport,
  EmployeeAttendanceStatus,
  Paginated,
  Term,
} from "@/lib/types"
import { cn, formatETB } from "@/lib/utils"

/** Local-timezone ISO date — `toISOString()` would shift the day near midnight. */
function localIso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(
    d.getDate(),
  ).padStart(2, "0")}`
}

function today(): string {
  return localIso(new Date())
}

type PresetKey = "last7" | "last15" | "last30" | "thisMonth"

function presetRange(preset: PresetKey): { from: string; to: string } {
  const to = today()
  const [y, m, d] = to.split("-").map(Number)
  const back = (days: number) => {
    const dt = new Date(y, m - 1, d)
    dt.setDate(dt.getDate() - (days - 1))
    return localIso(dt)
  }
  switch (preset) {
    case "last7":
      return { from: back(7), to }
    case "last15":
      return { from: back(15), to }
    case "last30":
      return { from: back(30), to }
    case "thisMonth":
      return { from: `${to.slice(0, 7)}-01`, to }
  }
}

const PRESETS: PresetKey[] = ["last7", "last15", "last30", "thisMonth"]

const MIX_STATUSES: EmployeeAttendanceStatus[] = [
  "present",
  "late",
  "half_day",
  "absent",
  "excused",
]

const MIX_COLORS: Record<EmployeeAttendanceStatus, string> = {
  present: "bg-success",
  late: "bg-warning",
  half_day: "bg-warning/50",
  absent: "bg-destructive",
  excused: "bg-info",
}

/** ≥95% is healthy, 80–95% needs a look, below 80% is a problem. */
type RateBand = "high" | "mid" | "low" | "none"

function rateBand(rate: number | null): RateBand {
  if (rate == null) return "none"
  if (rate >= 95) return "high"
  if (rate >= 80) return "mid"
  return "low"
}

const RATE_BAR: Record<RateBand, string> = {
  high: "bg-success",
  mid: "bg-warning",
  low: "bg-destructive",
  none: "bg-muted",
}

type ReportRow = HrAttendanceReportRow & { rate_band: RateBand }

/** Ranked label → count breakdown with proportional bars and share of total. */
function BreakdownCard({
  title,
  entries,
  labelFor,
}: {
  title: string
  entries: [string, number][]
  labelFor: (key: string) => string
}) {
  const sorted = [...entries].sort((a, b) => b[1] - a[1])
  const total = sorted.reduce((sum, [, count]) => sum + count, 0)
  const max = Math.max(1, ...sorted.map(([, count]) => count))

  return (
    <section className="rounded-2xl border bg-card p-4 shadow-xs">
      <h2 className="font-display text-base font-semibold">{title}</h2>
      <ul className="mt-3 space-y-2.5">
        {sorted.map(([key, count], index) => (
          <li key={key} className="space-y-1">
            <div className="flex items-center justify-between gap-3 text-sm">
              <span className="min-w-0 truncate">{labelFor(key)}</span>
              <span className="shrink-0 tabular-nums text-muted-foreground">
                <span className="font-medium text-foreground">{count}</span>
                {total > 0 && (
                  <span className="ml-1.5 text-xs">
                    {Math.round((count / total) * 100)}%
                  </span>
                )}
              </span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
              <div
                className={cn(
                  "h-full rounded-full",
                  index === 0 ? "bg-primary" : "bg-primary/50",
                )}
                style={{ width: `${(count / max) * 100}%` }}
              />
            </div>
          </li>
        ))}
      </ul>
    </section>
  )
}

export default function HrReportsPage() {
  const { t } = useTranslation("hr")
  const { t: ts } = useTranslation("employees")
  const { active } = useSchoolContext()

  const [{ from, to }, setRange] = useState(() => presetRange("last30"))
  const [overview, setOverview] = useState<HrOverviewReport | null>(null)
  const [trends, setTrends] = useState<HrTrendsReport | null>(null)
  const [attendanceRows, setAttendanceRows] = useState<ReportRow[] | null>(null)
  const [workingDays, setWorkingDays] = useState<number | null>(null)
  const [terms, setTerms] = useState<Term[]>([])

  const hasBranch = active.branchId != null

  // School-wide workspace: reports are per-branch, so school managers pick
  // which branch to inspect (no workspace switch needed).
  const { needsBranch } = useBranchScope()
  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && pickedBranchId != null)
  const branchParam = !hasBranch && pickedBranchId != null ? `&branch_id=${pickedBranchId}` : ""

  const activePreset = PRESETS.find((p) => {
    const r = presetRange(p)
    return r.from === from && r.to === to
  })

  useEffect(() => {
    if (!branchReady || !from || !to || from > to) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on window/scope change
    setOverview(null)
    setTrends(null)
    setAttendanceRows(null)
    const params = `from=${from}&to=${to}${branchParam}`
    // The three reports are independent — fire them in parallel.
    apiFetch<{ data: HrOverviewReport }>(`/hr/reports/overview?${params}`)
      .then((res) => !cancelled && setOverview(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : t("reports.loadFailed"))
      })
    apiFetch<{ data: HrTrendsReport }>(`/hr/reports/trends?${params}`)
      .then((res) => !cancelled && setTrends(res.data))
      .catch(() => {})
    apiFetch<{ data: HrAttendanceReportRow[]; meta: { working_days: number } }>(
      `/hr/reports/attendance?${params}`,
    )
      .then((res) => {
        if (cancelled) return
        setAttendanceRows(
          res.data.map((row) => ({
            ...row,
            id: row.employee_id,
            rate_band: rateBand(row.attendance_rate),
          })),
        )
        setWorkingDays(res.meta.working_days)
      })
      .catch(() => !cancelled && setAttendanceRows([]))
    return () => {
      cancelled = true
    }
  }, [branchReady, branchParam, from, to, active.branchId, t])

  // Semester/quarter windows for the reporting-window shortcut — one cheap
  // branch-scoped fetch. /terms is readable via timetable.view; if the role
  // can't read it the picker simply stays hidden (termWindows is empty).
  useEffect(() => {
    if (!branchReady) return
    let cancelled = false
    apiFetch<Paginated<Term>>(`/terms?per_page=100${branchParam}`)
      .then((res) => !cancelled && setTerms(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [branchReady, branchParam, active.branchId])

  // Jump-to windows grouped per academic year: "Full year" + each semester /
  // quarter, clamped to today (HR data can't exist in the future).
  const termWindows = useMemo(() => {
    const max = today()
    const clamp = (ends: string | null) => (ends && ends < max ? ends : max)
    type Item = { key: string; label: string; from: string; to: string }
    const groups = new Map<
      string,
      { key: string; label: string; from: string; to: string; items: Item[] }
    >()
    // The school-wide (All branches) context returns every branch's copy of
    // the same calendar — dedupe by year + term name, first window wins.
    const seen = new Set<string>()
    for (const term of terms) {
      if (!term.starts_on || term.starts_on > max) continue
      const yearKey = term.academic_year_name ?? String(term.academic_year_id)
      const dedupeKey = `${yearKey}|${term.name}`
      if (seen.has(dedupeKey)) continue
      seen.add(dedupeKey)
      const item: Item = {
        key: `t:${term.id}`,
        label: term.name,
        from: term.starts_on,
        to: clamp(term.ends_on),
      }
      const group = groups.get(yearKey)
      if (!group) {
        groups.set(yearKey, {
          key: yearKey,
          label: yearKey,
          from: item.from,
          to: item.to,
          items: [item],
        })
      } else {
        group.items.push(item)
        if (item.from < group.from) group.from = item.from
        if (item.to > group.to) group.to = item.to
      }
    }
    // Newest year first; terms chronological within their year.
    return [...groups.values()]
      .sort((a, b) => b.from.localeCompare(a.from))
      .map((group) => ({
        key: group.key,
        label: group.label,
        items: [
          {
            key: `y:${group.key}`,
            label: t("reports.termWindow.fullYear"),
            from: group.from,
            to: group.to,
          },
          ...group.items.sort((a, b) => a.from.localeCompare(b.from)),
        ],
      }))
  }, [terms, t])

  // Reflect the current range back into the picker; a hand-tuned range
  // simply shows the placeholder again.
  const activeTermKey =
    termWindows.flatMap((g) => g.items).find((i) => i.from === from && i.to === to)?.key ?? ""

  const applyTermWindow = (key: string) => {
    const win = termWindows.flatMap((g) => g.items).find((i) => i.key === key)
    if (win) setRange({ from: win.from, to: win.to })
  }

  const attendanceFilters: DataTableFilter[] = useMemo(() => {
    const job_titles = [
      ...new Set((attendanceRows ?? []).flatMap((row) => row.job_titles)),
    ].sort()
    const filters: DataTableFilter[] = []
    if (job_titles.length > 1) {
      filters.push({
        key: "job_titles",
        label: t("reports.attendanceTable.filters.jobTitle"),
        options: job_titles.map((d) => ({
          label: ts(`jobTitles.${d}`),
          value: d,
        })),
      })
    }
    filters.push({
      key: "rate_band",
      label: t("reports.attendanceTable.filters.rate"),
      options: (["high", "mid", "low", "none"] as const).map((band) => ({
        label: t(`reports.attendanceTable.rateBands.${band}`),
        value: band,
      })),
    })
    return filters
  }, [attendanceRows, t, ts])

  const countCell = (value: number, tone?: "destructive" | "warning") => (
    <span
      className={cn(
        "tabular-nums",
        value === 0 && "text-muted-foreground/40",
        value > 0 && tone === "destructive" && "font-medium text-destructive",
        value > 0 && tone === "warning" && "text-warning",
      )}
    >
      {value}
    </span>
  )

  const attendanceColumns: DataTableColumn<ReportRow>[] = [
    {
      key: "employee_name",
      label: t("reports.attendanceTable.employee"),
      primary: true,
      sortable: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.employee_name}</p>
          {row.job_titles.length > 0 && (
            <p className="truncate text-xs text-muted-foreground">
              {row.job_titles.map((d) => ts(`jobTitles.${d}`)).join(" · ")}
            </p>
          )}
        </div>
      ),
      exportValue: (row) => row.employee_name,
    },
    {
      key: "present",
      label: t("reports.attendanceTable.present"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.present),
      exportValue: (row) => String(row.present),
    },
    {
      key: "late",
      label: t("reports.attendanceTable.late"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.late, "warning"),
      exportValue: (row) => String(row.late),
    },
    {
      key: "half_day",
      label: t("reports.attendanceTable.halfDay"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.half_day),
      exportValue: (row) => String(row.half_day),
    },
    {
      key: "absent",
      label: t("reports.attendanceTable.absent"),
      sortable: true,
      render: (row) => countCell(row.absent, "destructive"),
      exportValue: (row) => String(row.absent),
    },
    {
      key: "excused",
      label: t("reports.attendanceTable.excused"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.excused),
      exportValue: (row) => String(row.excused),
    },
    {
      key: "leave_days",
      label: t("reports.attendanceTable.leaveDays"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.leave_days),
      exportValue: (row) => String(row.leave_days),
    },
    {
      key: "attendance_rate",
      label: t("reports.attendanceTable.rate"),
      sortable: true,
      render: (row) =>
        row.attendance_rate == null ? (
          <span className="text-muted-foreground/40">—</span>
        ) : (
          <span className="flex items-center gap-2">
            <span className="hidden h-1.5 w-14 overflow-hidden rounded-full bg-muted sm:block">
              <span
                className={cn("block h-full rounded-full", RATE_BAR[row.rate_band])}
                style={{ width: `${Math.min(100, row.attendance_rate)}%` }}
              />
            </span>
            <span className="tabular-nums">{row.attendance_rate}%</span>
          </span>
        ),
      exportValue: (row) => (row.attendance_rate == null ? "" : `${row.attendance_rate}%`),
    },
  ]

  const recorded = overview?.attendance.recorded ?? 0

  return (
    <div className="space-y-6">
      {/* Reporting window (term shortcut + presets + range) sits in the
          header's action slot — top right on desktop, stacked on mobile. */}
      <PageHeader
        title={t("reports.title")}
        description={t("reports.subtitle")}
        actions={
          branchReady ? (
            <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
              {termWindows.length > 0 && (
                <Select value={activeTermKey} onValueChange={applyTermWindow}>
                  <SelectTrigger className="h-9 w-full sm:w-44">
                    <SelectValue placeholder={t("reports.termWindow.placeholder")} />
                  </SelectTrigger>
                  <SelectContent>
                    {termWindows.map((group) => (
                      <SelectGroup key={group.key}>
                        <SelectLabel>{group.label}</SelectLabel>
                        {group.items.map((item) => (
                          <SelectItem key={item.key} value={item.key}>
                            {item.label}
                          </SelectItem>
                        ))}
                      </SelectGroup>
                    ))}
                  </SelectContent>
                </Select>
              )}
              <div className="no-scrollbar inline-flex max-w-full items-center gap-0.5 overflow-x-auto rounded-full border bg-card p-1 shadow-xs">
                {PRESETS.map((preset) => (
                  <button
                    key={preset}
                    type="button"
                    onClick={() => setRange(presetRange(preset))}
                    aria-pressed={activePreset === preset}
                    className={cn(
                      "pressable whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition-colors",
                      activePreset === preset
                        ? "bg-primary text-primary-foreground"
                        : "text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {t(`reports.presets.${preset}`)}
                  </button>
                ))}
              </div>
              <div className="flex items-center gap-2">
                <DatePicker
                  value={from}
                  onChange={(v) => setRange((r) => ({ ...r, from: v }))}
                  max={to}
                  clearable={false}
                  aria-label={t("reports.from")}
                  className="w-36"
                />
                <span className="text-xs text-muted-foreground">–</span>
                <DatePicker
                  value={to}
                  onChange={(v) => setRange((r) => ({ ...r, to: v }))}
                  min={from}
                  max={today()}
                  clearable={false}
                  aria-label={t("reports.to")}
                  className="w-36"
                />
              </div>
            </div>
          ) : undefined
        }
      />

      {/* School-wide: which branch to inspect. */}
      {needsBranch && (
        <div className="page-gutter">
          <BranchScopePicker value={pickedBranchId} onChange={setPickedBranchId} />
        </div>
      )}

      {!branchReady ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("reports.noBranch")}
          </div>
        </div>
      ) : (
        <>
          {/* ── Headline stats ── */}
          <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
              label={t("reports.cards.activeEmployees")}
              value={overview ? overview.headcount.active : null}
              icon={Users}
              hint={
                overview
                  ? t("reports.cards.activeEmployeesHint", {
                      female: overview.headcount.female,
                      male: overview.headcount.male,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("reports.cards.attendanceRate")}
              value={
                overview
                  ? overview.attendance.attendance_rate != null
                    ? `${overview.attendance.attendance_rate}%`
                    : "—"
                  : null
              }
              icon={UserCheck}
              hint={
                overview
                  ? t("reports.cards.attendanceRateHint", {
                      recorded: overview.attendance.recorded,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("reports.cards.leaveDays")}
              value={overview ? overview.leave.approved_days : null}
              icon={CalendarOff}
              hint={
                overview
                  ? t("reports.cards.leaveDaysHint", {
                      pending: overview.leave.pending_requests,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("reports.cards.payrollCost")}
              value={
                overview
                  ? overview.payroll
                    ? formatETB(overview.payroll.employer_cost)
                    : "—"
                  : null
              }
              icon={HandCoins}
              hint={
                overview?.payroll
                  ? t("reports.cards.payrollCostHint", { name: overview.payroll.name })
                  : undefined
              }
            />
          </div>

          {/* ── Trend charts: daily register, weekday pattern, leave, payroll, tenure ── */}
          <div className="page-gutter space-y-5">
            <HrTrendCharts trends={trends} />
          </div>

          {/* ── Composition ── */}
          <div className="page-gutter grid grid-cols-1 gap-5 lg:grid-cols-2">
            {overview === null ? (
              <>
                <Skeleton className="h-56 rounded-2xl" />
                <Skeleton className="h-56 rounded-2xl" />
              </>
            ) : (
              <>
                <BreakdownCard
                  title={t("reports.composition.byJobTitle")}
                  entries={Object.entries(overview.by_job_title)}
                  labelFor={(key) => ts(`jobTitles.${key}`)}
                />
                <BreakdownCard
                  title={t("reports.composition.byEmploymentType")}
                  entries={Object.entries(overview.by_employment_type)}
                  labelFor={(key) =>
                    key === "unspecified"
                      ? t("reports.composition.unspecified")
                      : ts(`employmentTypes.${key}`)
                  }
                />
              </>
            )}
          </div>

          {/* ── Attendance mix + leave by type ── */}
          <div className="page-gutter grid grid-cols-1 gap-5 lg:grid-cols-2">
            {overview === null ? (
              <>
                <Skeleton className="h-48 rounded-2xl" />
                <Skeleton className="h-48 rounded-2xl" />
              </>
            ) : (
              <>
                <section className="rounded-2xl border bg-card p-4 shadow-xs">
                  <h2 className="font-display text-base font-semibold">
                    {t("reports.attendanceMix.title")}
                  </h2>
                  {recorded === 0 ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                      {t("reports.attendanceMix.empty")}
                    </p>
                  ) : (
                    <>
                      <div className="mt-4 flex h-3 overflow-hidden rounded-full bg-muted">
                        {MIX_STATUSES.map((status) => {
                          const count = overview.attendance.by_status[status] ?? 0
                          if (count === 0) return null
                          return (
                            <div
                              key={status}
                              className={MIX_COLORS[status]}
                              style={{ width: `${(count / recorded) * 100}%` }}
                            />
                          )
                        })}
                      </div>
                      <ul className="mt-4 space-y-2">
                        {MIX_STATUSES.map((status) => {
                          const count = overview.attendance.by_status[status] ?? 0
                          if (count === 0) return null
                          return (
                            <li
                              key={status}
                              className="flex items-center justify-between gap-3 text-sm"
                            >
                              <span className="flex min-w-0 items-center gap-2">
                                <span
                                  className={cn(
                                    "size-2.5 shrink-0 rounded-full",
                                    MIX_COLORS[status],
                                  )}
                                />
                                <span className="truncate">
                                  {t(`attendance.statuses.${status}`)}
                                </span>
                              </span>
                              <span className="shrink-0 tabular-nums text-muted-foreground">
                                <span className="font-medium text-foreground">
                                  {count}
                                </span>
                                <span className="ml-1.5 text-xs">
                                  {Math.round((count / recorded) * 100)}%
                                </span>
                              </span>
                            </li>
                          )
                        })}
                      </ul>
                    </>
                  )}
                </section>

                <section className="rounded-2xl border bg-card p-4 shadow-xs">
                  <h2 className="font-display text-base font-semibold">
                    {t("reports.leaveByType.title")}
                  </h2>
                  {overview.leave.by_type.length === 0 ? (
                    <p className="mt-3 text-sm text-muted-foreground">
                      {t("reports.leaveByType.empty")}
                    </p>
                  ) : (
                    <ul className="mt-3 space-y-2.5">
                      {(() => {
                        const maxDays = Math.max(
                          1,
                          ...overview.leave.by_type.map((row) => Number(row.days)),
                        )
                        return overview.leave.by_type.map((row) => (
                          <li key={row.name} className="space-y-1">
                            <div className="flex items-center justify-between gap-3 text-sm">
                              <span className="min-w-0 truncate">
                                {row.name}
                                <span className="ml-2 text-xs text-muted-foreground">
                                  {row.is_paid ? t("leave.paid") : t("leave.unpaid")}
                                </span>
                              </span>
                              <span className="shrink-0 tabular-nums text-muted-foreground">
                                <span className="font-medium text-foreground">
                                  {Number(row.days)}
                                </span>{" "}
                                <span className="text-xs">
                                  {t("reports.leaveByType.days").toLowerCase()} ·{" "}
                                  {row.requests}{" "}
                                  {t("reports.leaveByType.requests").toLowerCase()}
                                </span>
                              </span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                              <div
                                className="h-full rounded-full bg-primary/70"
                                style={{ width: `${(Number(row.days) / maxDays) * 100}%` }}
                              />
                            </div>
                          </li>
                        ))
                      })()}
                    </ul>
                  )}
                </section>
              </>
            )}
          </div>

          {/* ── Per-employee attendance detail ── */}
          <div className="page-gutter flex items-baseline justify-between gap-3">
            <h2 className="font-display text-base font-semibold">
              {t("reports.attendanceTable.title")}
            </h2>
            {workingDays != null && (
              <span className="text-xs text-muted-foreground tabular-nums">
                {t("reports.attendanceTable.workingDays", { days: workingDays })}
              </span>
            )}
          </div>
          {attendanceRows === null ? (
            <div className="page-gutter">
              <Skeleton className="h-64 rounded-2xl" />
            </div>
          ) : attendanceRows.length === 0 ? (
            <div className="page-gutter">
              <div className="rounded-2xl border bg-card shadow-xs">
                <EmptyState
                  icon={BarChart3}
                  title={t("reports.attendanceTable.empty")}
                  compact
                />
              </div>
            </div>
          ) : (
            <DataTable
              columns={attendanceColumns}
              data={attendanceRows}
              searchKeys={["employee_name"]}
              searchPlaceholder={t("reports.attendanceTable.searchPlaceholder")}
              filters={attendanceFilters}
              emptyMessage={t("reports.attendanceTable.empty")}
              exportFilename="hr-attendance-report"
            />
          )}
        </>
      )}
    </div>
  )
}
