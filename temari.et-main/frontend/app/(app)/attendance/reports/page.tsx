"use client"

import {
  AlertTriangle,
  Award,
  BarChart3,
  CalendarX2,
  ClipboardCheck,
  Clock3,
  ScanLine,
  TrendingUp,
  UserCheck,
  Users,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import {
  AttendanceDailyChart,
  AttendancePatternCharts,
  RATE_BAR,
  rateBand,
} from "@/components/attendance/report-charts"
import { useBranchScope } from "@/components/ui/branch-select"
import {
  DataTable,
  type DataTableColumn,
} from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs, type ProfileTab } from "@/components/ui/profile-tabs"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AttendanceReportDeviceOption,
  AttendanceReportOverview,
  AttendanceReportStudentRow,
  AttendanceReportTrends,
  AttendanceStatus,
  AttendanceStudentFlag,
  Paginated,
  School,
  Section,
  Term,
} from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { cn } from "@/lib/utils"
import { addisToday } from "@/lib/dates"

/** Local-timezone ISO date — `toISOString()` would shift the day near midnight. */
function localIso(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(
    d.getDate(),
  ).padStart(2, "0")}`
}

/** "Today" on the Addis wall clock — the register lines up with device scans. */
function today(): string {
  return addisToday()
}

type PresetKey = "last7" | "last15" | "last30" | "thisMonth"

const PRESETS: PresetKey[] = ["last7", "last15", "last30", "thisMonth"]

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

const MARK_DOT: Record<AttendanceStatus, string> = {
  present: "bg-success",
  late: "bg-warning",
  absent: "bg-destructive",
  excused: "bg-info",
}

const STATUS_ORDER: AttendanceStatus[] = ["present", "late", "absent", "excused"]

type StudentRow = AttendanceReportStudentRow & { id: number }

/**
 * League table one level below the current scope (schools → branches →
 * grades → sections): a proportional status meter + attendance rate per row.
 */
function BreakdownCard({
  title,
  rows,
  statusLabel,
}: {
  title: string
  rows: AttendanceReportTrends["breakdown"]["rows"]
  statusLabel: (status: AttendanceStatus) => string
}) {
  const { t } = useTranslation("attendance")

  return (
    <section className="rounded-2xl border bg-card p-4 shadow-xs">
      <h2 className="font-display text-base font-semibold">{title}</h2>
      {rows.length === 0 ? (
        <p className="mt-3 flex h-40 items-center justify-center text-sm text-muted-foreground">
          {t("reports.noData")}
        </p>
      ) : (
        <ul className="mt-3 space-y-3">
          {rows.map((row) => (
            <li key={row.id} className="space-y-1">
              <div className="flex items-center justify-between gap-3 text-sm">
                <span className="min-w-0 truncate font-medium">{row.name}</span>
                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                  {t("reports.breakdown.students", { count: row.students })}
                  <span
                    className={cn(
                      "ml-2 inline-block rounded-full px-1.5 py-0.5 text-[11px] font-semibold text-white",
                      RATE_BAR[rateBand(row.rate)],
                    )}
                  >
                    {row.rate == null ? "—" : `${row.rate}%`}
                  </span>
                </span>
              </div>
              <div
                className="flex h-2 overflow-hidden rounded-full bg-muted"
                title={STATUS_ORDER.map(
                  (status) => `${statusLabel(status)}: ${row[status]}`,
                ).join(" · ")}
              >
                {STATUS_ORDER.map((status) =>
                  row[status] === 0 ? null : (
                    <div
                      key={status}
                      className={cn(MARK_DOT[status], "border-r border-card last:border-r-0")}
                      style={{ width: `${(row[status] / row.marks) * 100}%` }}
                    />
                  ),
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

export default function AttendanceReportsPage() {
  const { t } = useTranslation("attendance")
  const router = useRouter()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  // Two lanes (ADR-010): supervisors aggregate their whole scope; homeroom
  // teachers (attendance.view_own only) get the same page capped to their
  // sections — the backend enforces the cap, the UI just says so.
  const supervisory = permissions.includes("attendance.reports.view")
  const canOpenStudent = permissions.includes("students.view")

  const hasBranch = active.branchId != null
  const { needsBranch, branches } = useBranchScope()

  // The three report tabs — URL-synced so deep links and back work.
  const [tab, setTab] = useProfileTabs(["overview", "patterns", "students"] as const, "overview")

  // ── Scope + filters ──────────────────────────────────────────────────────
  const [{ from, to }, setRange] = useState(() => presetRange("last30"))
  const [schoolId, setSchoolId] = useState<string>("")
  const [branchId, setBranchId] = useState<string>("")
  const [gradeId, setGradeId] = useState<string>("")
  const [sectionId, setSectionId] = useState<string>("")
  const [source, setSource] = useState<string>("")
  const [deviceId, setDeviceId] = useState<string>("")
  const [flag, setFlag] = useState<"" | AttendanceStudentFlag>("")

  // Option lists.
  const [schools, setSchools] = useState<School[]>([])
  // Branch-scoped grade offering, session-cached across pages.
  const { grades } = useGradeLevels()
  const [sections, setSections] = useState<Section[]>([])
  const [devices, setDevices] = useState<AttendanceReportDeviceOption[]>([])
  const [terms, setTerms] = useState<Term[]>([])

  useEffect(() => {
    if (!isPlatform) return
    apiFetch<Paginated<School>>("/schools?per_page=100&sort=name&dir=asc")
      .then((res) => setSchools(res.data))
      .catch(() => {})
  }, [isPlatform])

  // Semester/quarter windows for the date filter — one cheap branch-scoped
  // fetch; /terms is readable via timetable.view, which homeroom teachers
  // hold too (same lane as the rosters page). Platform staff span schools
  // with different calendars, so no term shortcut is offered there.
  useEffect(() => {
    if (isPlatform) return
    let cancelled = false
    const params = new URLSearchParams({ per_page: "100" })
    if (!hasBranch && branchId) params.set("branch_id", branchId)
    apiFetch<Paginated<Term>>(`/terms?${params.toString()}`)
      .then((res) => !cancelled && setTerms(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [isPlatform, hasBranch, branchId, active.branchId])

  // Jump-to windows grouped per academic year: "Full year" + each semester /
  // quarter, clamped to today (attendance can't exist in the future).
  const termWindows = useMemo(() => {
    const max = today()
    const clamp = (ends: string | null) => (ends && ends < max ? ends : max)
    type Item = { key: string; label: string; from: string; to: string }
    const groups = new Map<string, { key: string; label: string; from: string; to: string; items: Item[] }>()
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
        groups.set(yearKey, { key: yearKey, label: yearKey, from: item.from, to: item.to, items: [item] })
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
          { key: `y:${group.key}`, label: t("reports.termWindow.fullYear"), from: group.from, to: group.to },
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

  // Sections are offered once a CONCRETE branch is known (workspace branch or
  // the picked one). Teachers only get the sections they homeroom.
  const concreteBranchId = hasBranch ? active.branchId : branchId ? Number(branchId) : null
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- stale section from the previous branch
    setSectionId("")
    if (concreteBranchId == null || isPlatform) {
      setSections([])
      return
    }
    let cancelled = false
    const params = new URLSearchParams()
    if (!hasBranch) params.set("branch_id", String(concreteBranchId))
    if (!supervisory) params.set("homeroom_only", "1")
    apiFetch<Paginated<Section>>(`/sections?${params.toString()}`)
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [concreteBranchId, hasBranch, isPlatform, supervisory])

  const sectionOptions = useMemo(
    () =>
      gradeId ? sections.filter((s) => String(s.grade_level_id) === gradeId) : sections,
    [sections, gradeId],
  )

  // Supervisors filter across every grade the branch offers; a homeroom teacher
  // only ever reports on the classes they homeroom, so their grade list comes
  // from those sections — never the whole grade catalogue.
  const gradeOptions = useMemo(() => {
    if (supervisory) return grades
    const seen = new Map<number, { id: number; name: string }>()
    for (const section of sections) {
      const grade = section.grade_level
      if (grade && !seen.has(grade.id)) seen.set(grade.id, { id: grade.id, name: grade.name })
    }
    return [...seen.values()]
  }, [supervisory, grades, sections])

  // Nothing to choose = no picker: a teacher with one homeroom grade/section
  // sees the report straight away.
  const showGradeSelect = supervisory || gradeOptions.length > 1
  const showSectionSelect =
    concreteBranchId != null && sections.length > 0 && (supervisory || sections.length > 1)

  // The scope + filter query string shared by all three report endpoints.
  const reportParams = useMemo(() => {
    const p = new URLSearchParams({ from, to })
    if (isPlatform && schoolId) p.set("school_id", schoolId)
    if (!hasBranch && branchId) p.set("branch_id", branchId)
    if (gradeId) p.set("grade_level_id", gradeId)
    if (sectionId) p.set("section_id", sectionId)
    if (source) p.set("source", source)
    if (deviceId) p.set("device_id", deviceId)
    return p.toString()
  }, [from, to, isPlatform, schoolId, hasBranch, branchId, gradeId, sectionId, source, deviceId])

  // ── Overview + trends ────────────────────────────────────────────────────
  const [overview, setOverview] = useState<AttendanceReportOverview | null>(null)
  const [trends, setTrends] = useState<AttendanceReportTrends | null>(null)

  useEffect(() => {
    if (!from || !to || from > to) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on window/scope change
    setOverview(null)
    setTrends(null)
    apiFetch<{ data: AttendanceReportOverview; meta: { devices: AttendanceReportDeviceOption[] } }>(
      `/attendance-reports/overview?${reportParams}`,
    )
      .then((res) => {
        if (cancelled) return
        setOverview(res.data)
        setDevices(res.meta.devices)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : t("reports.loadFailed"))
      })
    apiFetch<{ data: AttendanceReportTrends }>(`/attendance-reports/trends?${reportParams}`)
      .then((res) => !cancelled && setTrends(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [reportParams, from, to, active.schoolId, active.branchId, t])

  // ── Per-student ledger (server-driven, fetched once its tab opens) ──────
  const table = useServerTable<StudentRow>({
    endpoint: "/attendance-reports/students",
    exportEndpoint: "/attendance-reports/students/export",
    defaultSort: { key: "absent", dir: "desc" },
    enabled: tab === "students",
    refreshKey: `${reportParams}|${flag}`,
    extraParams: useMemo(() => {
      const p: Record<string, string> = { from, to }
      if (isPlatform && schoolId) p.school_id = schoolId
      if (!hasBranch && branchId) p.branch_id = branchId
      if (gradeId) p.grade_level_id = gradeId
      if (sectionId) p.section_id = sectionId
      if (source) p.source = source
      if (deviceId) p.device_id = deviceId
      if (flag) p.flag = flag
      return p
    }, [from, to, isPlatform, schoolId, hasBranch, branchId, gradeId, sectionId, source, deviceId, flag]),
    loadFailedMessage: t("reports.loadFailed"),
  })

  const rows = useMemo(
    () => table.rows.map((row) => ({ ...row, id: row.student_id })),
    [table.rows],
  )

  const activePreset = PRESETS.find((p) => {
    const r = presetRange(p)
    return r.from === from && r.to === to
  })

  const rateDelta =
    overview?.totals.attendance_rate != null && overview.totals.previous_rate != null
      ? Math.round((overview.totals.attendance_rate - overview.totals.previous_rate) * 10) / 10
      : null

  const deviceShare =
    overview && overview.sources.device + overview.sources.manual > 0
      ? Math.round(
          (overview.sources.device / (overview.sources.device + overview.sources.manual)) * 100,
        )
      : null

  const countCell = (value: number, tone?: "destructive" | "warning" | "info") => (
    <span
      className={cn(
        "tabular-nums",
        value === 0 && "text-muted-foreground/40",
        value > 0 && tone === "destructive" && "font-medium text-destructive",
        value > 0 && tone === "warning" && "text-warning",
        value > 0 && tone === "info" && "text-info",
      )}
    >
      {value}
    </span>
  )

  const columns: DataTableColumn<StudentRow>[] = [
    {
      key: "name",
      label: t("reports.table.student"),
      primary: true,
      sortable: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.name}</p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.grade, row.section && t("reports.table.sectionShort", { name: row.section })]
              .filter(Boolean)
              .join(" · ") || "—"}
          </p>
        </div>
      ),
      exportValue: (row) => row.name,
    },
    {
      key: "last_marks",
      label: t("reports.table.recent"),
      mobileHidden: true,
      render: (row) => (
        <span className="flex items-center gap-1">
          {row.last_marks.length === 0 ? (
            <span className="text-muted-foreground/40">—</span>
          ) : (
            row.last_marks.map((mark) => (
              <span
                key={mark.date}
                title={`${mark.date} — ${t(`statuses.${mark.status}`)}`}
                className={cn("size-2 rounded-full", MARK_DOT[mark.status])}
              />
            ))
          )}
        </span>
      ),
      exportValue: () => "",
    },
    {
      key: "present",
      label: t("statuses.present"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.present),
      exportValue: (row) => String(row.present),
    },
    {
      key: "late",
      label: t("statuses.late"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.late, "warning"),
      exportValue: (row) => String(row.late),
    },
    {
      key: "absent",
      label: t("statuses.absent"),
      sortable: true,
      render: (row) => countCell(row.absent, "destructive"),
      exportValue: (row) => String(row.absent),
    },
    {
      key: "excused",
      label: t("statuses.excused"),
      sortable: true,
      mobileHidden: true,
      render: (row) => countCell(row.excused, "info"),
      exportValue: (row) => String(row.excused),
    },
    {
      key: "recorded",
      label: t("reports.table.recorded"),
      sortable: true,
      mobileHidden: true,
      render: (row) => <span className="tabular-nums text-muted-foreground">{row.recorded}</span>,
      exportValue: (row) => String(row.recorded),
    },
    {
      key: "absent_streak",
      label: t("reports.table.streak"),
      mobileHidden: true,
      render: (row) =>
        row.absent_streak >= 2 ? (
          <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
            <AlertTriangle className="size-3" strokeWidth={2} />
            {t("reports.table.streakDays", { count: row.absent_streak })}
          </span>
        ) : (
          <span className="text-muted-foreground/40">—</span>
        ),
      exportValue: (row) => String(row.absent_streak ?? ""),
    },
    {
      key: "rate",
      label: t("reports.table.rate"),
      sortable: true,
      render: (row) =>
        row.attendance_rate == null ? (
          <span className="text-muted-foreground/40">—</span>
        ) : (
          <span className="flex items-center gap-2">
            <span className="hidden h-1.5 w-14 overflow-hidden rounded-full bg-muted sm:block">
              <span
                className={cn(
                  "block h-full rounded-full",
                  RATE_BAR[rateBand(row.attendance_rate)],
                )}
                style={{ width: `${Math.min(100, row.attendance_rate)}%` }}
              />
            </span>
            <span className="tabular-nums">{row.attendance_rate}%</span>
          </span>
        ),
      exportValue: (row) =>
        row.attendance_rate == null ? "" : `${row.attendance_rate}%`,
    },
  ]

  const flagPills: { key: "" | AttendanceStudentFlag; label: string }[] = [
    { key: "", label: t("reports.flags.all") },
    { key: "chronic", label: t("reports.flags.chronic") },
    { key: "frequent_late", label: t("reports.flags.frequentLate") },
    { key: "perfect", label: t("reports.flags.perfect") },
  ]

  return (
    <div className="space-y-6">
      {/* Reporting window (term shortcut + presets + range) sits in the
          header's action slot — top right on desktop, stacked on mobile. */}
      <PageHeader
        title={t("reports.title")}
        description={t("reports.subtitle")}
        actions={
          <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
            {!isPlatform && termWindows.length > 0 && (
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
        }
      />

      {/* ── Tabs (left) + scope & detail filters (right), one line ── */}
      <div className="page-gutter flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <ProfileTabBar
          tabs={
            [
              { key: "overview", label: t("reports.tabs.overview"), icon: BarChart3 },
              { key: "patterns", label: t("reports.tabs.patterns"), icon: TrendingUp },
              { key: "students", label: t("reports.tabs.students"), icon: Users },
            ] satisfies ProfileTab<"overview" | "patterns" | "students">[]
          }
          value={tab}
          onChange={setTab}
        />
        <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center lg:justify-end">
          {isPlatform && (
            <Select
              value={schoolId || "all"}
              onValueChange={(v) => setSchoolId(v === "all" ? "" : v)}
            >
              <SelectTrigger className="h-9 sm:w-48">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("reports.scope.allSchools")}</SelectItem>
                {schools.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>
                    {s.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {needsBranch && (
            <Select
              value={branchId || "all"}
              onValueChange={(v) => setBranchId(v === "all" ? "" : v)}
            >
              <SelectTrigger className="h-9 sm:w-44">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("reports.scope.allBranches")}</SelectItem>
                {branches.map((b) => (
                  <SelectItem key={b.id} value={String(b.id)}>
                    {b.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {showGradeSelect && (
            <Select value={gradeId || "all"} onValueChange={(v) => setGradeId(v === "all" ? "" : v)}>
              <SelectTrigger className="h-9 sm:w-36">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("reports.filters.allGrades")}</SelectItem>
                {gradeOptions.map((g) => (
                  <SelectItem key={g.id} value={String(g.id)}>
                    {g.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {showSectionSelect && (
            <Select
              value={sectionId || "all"}
              onValueChange={(v) => setSectionId(v === "all" ? "" : v)}
            >
              <SelectTrigger className="h-9 sm:w-44">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("reports.filters.allSections")}</SelectItem>
                {sectionOptions.map((s) => (
                  <SelectItem key={s.id} value={String(s.id)}>
                    {s.grade_level?.name ? `${s.grade_level.name} — ${s.name}` : s.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          <Select
            value={source || "all"}
            onValueChange={(v) => {
              setSource(v === "all" ? "" : v)
              if (v === "manual") setDeviceId("")
            }}
          >
            <SelectTrigger className="h-9 sm:w-36">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{t("reports.filters.allSources")}</SelectItem>
              <SelectItem value="device">{t("source.device")}</SelectItem>
              <SelectItem value="manual">{t("source.manual")}</SelectItem>
            </SelectContent>
          </Select>
          {devices.length > 0 && source !== "manual" && (
            <Select
              value={deviceId || "all"}
              onValueChange={(v) => setDeviceId(v === "all" ? "" : v)}
            >
              <SelectTrigger className="h-9 sm:w-44">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t("reports.filters.allDevices")}</SelectItem>
                {devices.map((d) => (
                  <SelectItem key={d.id} value={String(d.id)}>
                    {d.location ? `${d.name} · ${d.location}` : d.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </div>
      </div>

      {tab === "overview" && (
        <>
      {/* ── Headline stats ── */}
      <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard
          label={t("reports.cards.rate")}
          value={
            overview
              ? overview.totals.attendance_rate != null
                ? `${overview.totals.attendance_rate}%`
                : "—"
              : null
          }
          icon={UserCheck}
          hint={
            rateDelta != null
              ? rateDelta >= 0
                ? t("reports.cards.rateUp", { delta: rateDelta })
                : t("reports.cards.rateDown", { delta: Math.abs(rateDelta) })
              : overview
                ? t("reports.cards.rateHint", { days: overview.window.school_days })
                : undefined
          }
        />
        <StatCard
          label={t("reports.cards.students")}
          value={overview ? overview.totals.students : null}
          icon={Users}
          hint={
            overview
              ? t("reports.cards.studentsHint", {
                  marks: overview.totals.marks,
                  days: overview.window.school_days,
                })
              : undefined
          }
        />
        <StatCard
          label={t("reports.cards.absences")}
          value={overview ? overview.absences.total : null}
          icon={CalendarX2}
          hint={
            overview
              ? t("reports.cards.absencesHint", {
                  female: overview.absences.by_gender.female,
                  male: overview.absences.by_gender.male,
                })
              : undefined
          }
        />
        <StatCard
          label={t("reports.cards.late")}
          value={overview ? overview.punctuality.late : null}
          icon={Clock3}
          hint={
            overview?.punctuality.average_late_check_in
              ? t("reports.cards.lateHint", {
                  time: overview.punctuality.average_late_check_in,
                })
              : undefined
          }
        />
      </div>

      {/* ── Watchlist + register health (chronic/perfect drill into the table) ── */}
      <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard
          label={t("reports.cards.chronic")}
          value={overview ? overview.absences.chronic_students : null}
          icon={AlertTriangle}
          hint={t("reports.cards.chronicHint")}
          onClick={() => {
            setFlag("chronic")
            setTab("students")
          }}
        />
        <StatCard
          label={t("reports.cards.perfect")}
          value={overview ? overview.absences.perfect_students : null}
          icon={Award}
          hint={t("reports.cards.perfectHint")}
          onClick={() => {
            setFlag("perfect")
            setTab("students")
          }}
        />
        <StatCard
          label={t("reports.cards.coverage")}
          value={
            overview ? (overview.coverage.rate != null ? `${overview.coverage.rate}%` : "—") : null
          }
          icon={ClipboardCheck}
          hint={
            overview
              ? t("reports.cards.coverageHint", {
                  recorded: overview.coverage.recorded,
                  expected: overview.coverage.expected,
                })
              : undefined
          }
        />
        <StatCard
          label={t("reports.cards.deviceShare")}
          value={overview ? (deviceShare != null ? `${deviceShare}%` : "—") : null}
          icon={ScanLine}
          hint={
            overview
              ? t("reports.cards.deviceShareHint", {
                  device: overview.sources.device,
                  manual: overview.sources.manual,
                })
              : undefined
          }
        />
      </div>

      {/* ── Daily register + league table ── */}
      <div className="page-gutter space-y-5">
        <AttendanceDailyChart trends={trends} />
        {trends === null ? (
          <div className="h-56 animate-pulse rounded-2xl border bg-card" />
        ) : (
          <BreakdownCard
            title={t(`reports.breakdown.titles.${trends.breakdown.group}`)}
            rows={trends.breakdown.rows}
            statusLabel={(status) => t(`statuses.${status}`)}
          />
        )}
      </div>
        </>
      )}

      {tab === "patterns" && (
        <>
      {/* ── Weekday / capture / arrival charts ── */}
      <div className="page-gutter space-y-5">
        <AttendancePatternCharts trends={trends} />
        {overview && overview.sources.devices.length > 0 && (
          <section className="rounded-2xl border bg-card p-4 shadow-xs">
            <h2 className="font-display text-base font-semibold">
              {t("reports.devices.title")}
            </h2>
            <p className="mt-0.5 text-xs text-muted-foreground">
              {t("reports.devices.hint")}
            </p>
            <ul className="mt-3 space-y-2.5">
              {(() => {
                const max = Math.max(1, ...overview.sources.devices.map((d) => d.marks))
                return overview.sources.devices.map((device) => (
                  <li key={device.id} className="space-y-1">
                    <div className="flex items-center justify-between gap-3 text-sm">
                      <span className="flex min-w-0 items-center gap-1.5 truncate">
                        <ScanLine className="size-3.5 shrink-0 text-info" strokeWidth={1.75} />
                        <span className="truncate">
                          {device.name}
                          {device.location && (
                            <span className="ml-1.5 text-xs text-muted-foreground">
                              {device.location}
                            </span>
                          )}
                        </span>
                      </span>
                      <span className="shrink-0 tabular-nums text-muted-foreground">
                        <span className="font-medium text-foreground">{device.marks}</span>
                        <span className="ml-1.5 text-xs">
                          {t("reports.devices.late", { count: device.late })}
                        </span>
                      </span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                      <div
                        className="h-full rounded-full bg-chart-1"
                        style={{ width: `${(device.marks / max) * 100}%` }}
                      />
                    </div>
                  </li>
                ))
              })()}
            </ul>
          </section>
        )}
      </div>
        </>
      )}

      {tab === "students" && (
        <>
      {/* ── Per-student ledger ── */}
      <div className="page-gutter">
        <p className="text-xs text-muted-foreground">{t("reports.table.hint")}</p>
      </div>
      {!table.loading && rows.length === 0 && !table.searchInput && !flag ? (
        <div className="page-gutter">
          <div className="rounded-2xl border bg-card shadow-xs">
            <EmptyState icon={BarChart3} title={t("reports.table.empty")} compact />
          </div>
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={rows}
          loading={table.loading}
          serverMode
          searchable
          searchValue={table.searchInput}
          onSearchChange={table.setSearchInput}
          searchPlaceholder={t("reports.table.searchPlaceholder")}
          onSortChange={table.onSortChange}
          pagination={table.pagination}
          onExport={table.handleExport}
          exportFilename="attendance-report"
          emptyMessage={t("reports.table.empty")}
          onRowClick={
            canOpenStudent ? (row) => router.push(`/students/${row.student_id}`) : undefined
          }
          toolbarSlot={
            <div className="no-scrollbar flex max-w-full items-center gap-0.5 overflow-x-auto rounded-full border bg-card p-1">
              {flagPills.map((pill) => (
                <button
                  key={pill.key}
                  type="button"
                  onClick={() => setFlag(pill.key)}
                  aria-pressed={flag === pill.key}
                  className={cn(
                    "pressable whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium transition-colors",
                    flag === pill.key
                      ? "bg-primary text-primary-foreground"
                      : "text-muted-foreground hover:text-foreground",
                  )}
                >
                  {pill.label}
                </button>
              ))}
            </div>
          }
        />
      )}
        </>
      )}
    </div>
  )
}
