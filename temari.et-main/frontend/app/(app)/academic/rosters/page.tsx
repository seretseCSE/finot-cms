"use client"

import {
  CalendarRange,
  ChevronDown,
  Download,
  FileSpreadsheet,
  FileText,
  Loader2,
  RefreshCcw,
  Save,
  Snowflake,
  Table2,
  TriangleAlert,
} from "lucide-react"
import dynamic from "next/dynamic"
import Link from "next/link"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import {
  downloadCsv,
  termRosterCsv,
  yearRosterCsv,
} from "@/components/grading/roster-export"
import {
  ReportCardExtras,
  type ExtrasTarget,
} from "@/components/grading/report-card-extras"
import {
  ReportCardPrintDialog,
  type PrintTarget,
} from "@/components/grading/report-card-print-dialog"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { BranchScopePicker } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { TermSelect } from "@/components/academic/term-select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useDocumentDownload } from "@/lib/use-document"
import { useTranslation } from "@/lib/i18n"
import { fmtDate } from "@/lib/dates"
import type {
  Paginated,
  Section,
  Term,
  TermRoster,
  TermRosterMeta,
  TermRosterRow,
  YearRoster,
  YearRosterMeta,
  YearRosterStudent,
} from "@/lib/types"

/** Academic year, as distilled from /terms — homeroom teachers only get
 * `timetable.view`, never `academic_years.view`, so the picker must never
 * hit /academic-years directly. See transcripts. */
interface YearOption {
  id: number
  name: string
}

// The registers are heavy tables — load per mode, keep the shell light.
const RosterTable = dynamic(
  () => import("@/components/grading/roster-table").then((m) => m.RosterTable),
  { ssr: false, loading: () => <Skeleton className="h-96 rounded-2xl" /> },
)
const YearlyRosterTable = dynamic(
  () => import("@/components/grading/yearly-roster-table").then((m) => m.YearlyRosterTable),
  { ssr: false, loading: () => <Skeleton className="h-96 rounded-2xl" /> },
)

const ALL = "all"

export default function RostersPage() {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { download: downloadDoc, generating } = useDocumentDownload()

  const [mode, setMode] = useProfileTabs(["semester", "yearly"] as const, "semester")
  const [branchFilter, setBranchFilter] = useState<number | null>(null)
  const [terms, setTerms] = useState<Term[]>([])
  const [termId, setTermId] = useState<string>("")
  const [years, setYears] = useState<YearOption[]>([])
  const [yearId, setYearId] = useState<string>("")
  const [sections, setSections] = useState<Section[]>([])
  const [gradeId, setGradeId] = useState<string>("")
  const [sectionId, setSectionId] = useState<string>(ALL)
  const [termData, setTermData] = useState<{ data: TermRoster; meta: TermRosterMeta } | null>(null)
  const [yearData, setYearData] = useState<{ data: YearRoster; meta: YearRosterMeta } | null>(null)
  const [reloadKey, setReloadKey] = useState(0)
  const [computing, setComputing] = useState(false)
  const [confirmCompute, setConfirmCompute] = useState(false)
  const [pendingMarklists, setPendingMarklists] = useState(0)

  // Report-card surfaces: the print (download/print) dialog and the
  // extra-assessment (conduct + skills) sheet.
  const [printTarget, setPrintTarget] = useState<PrintTarget | null>(null)
  const [extrasTarget, setExtrasTarget] = useState<ExtrasTarget | null>(null)

  // Inline conduct quick-entry (semester tab), keyed by enrollment id. A REF,
  // not state: the table's inputs are uncontrolled, so typing a mark for a
  // whole class never re-renders 100 rows × 14 columns on a low-end phone.
  // Only the dirty COUNT is state (it drives the Save button).
  const conductEdits = useRef<Record<number, string>>({})
  const [dirtyConduct, setDirtyConduct] = useState(0)
  const [savingConduct, setSavingConduct] = useState(false)

  // A roster printed while marklists are still open shows partial numbers —
  // warn the supervisor and point at the submission monitor.
  useEffect(() => {
    if (!termId || !permissions.includes("grades.approve")) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new term
    setPendingMarklists(0)
    apiFetch<{ meta: { total: number; approved: number } }>(
      `/terms/${termId}/marklist-status`,
    )
      .then((res) => !cancelled && setPendingMarklists(res.meta.total - res.meta.approved))
      .catch(() => {})
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- permissions list is stable per workspace
  }, [termId, reloadKey])

  const canManage = permissions.includes("grades.manage")
  // The whole-grade PDF renders server-side under a supervisory scope; homeroom
  // teachers (grades.manage_own only) stay on the CSV lane, which the API
  // already scopes to their sections. Mirrors RosterDocument::authorize.
  const canExportPdf = permissions.includes("grades.view")
  // Supervisors (grades.view) browse every section; a homeroom teacher
  // (grades.manage_own only) reads ONLY their own homeroom — mirrors
  // RosterController::allowedSectionIds — so the pickers must list exactly
  // those, never a teaching-only class that would render an empty sheet.
  const isSupervisor = canExportPdf
  const hasWorkspace = !isPlatform && active.schoolId !== null
  const needsBranchPick = hasWorkspace && active.branchId === null
  // Terms/years/sections are branch-scoped; school managers pick a branch first.
  const scopeReady = hasWorkspace && (active.branchId !== null || branchFilter !== null)
  const branchParam = needsBranchPick && branchFilter !== null ? `&branch_id=${branchFilter}` : ""

  useEffect(() => {
    if (!scopeReady) return
    let cancelled = false
    apiFetch<Paginated<Term>>(`/terms?per_page=100${branchParam}`)
      .then((res) => {
        if (cancelled) return
        setTerms(res.data)
        const current = res.data.find((x) => x.status === "active")?.id ?? res.data[0]?.id
        if (current) setTermId((prev) => prev || String(current))
        // Years are readable via timetable.view (which homeroom teachers hold),
        // unlike /academic-years which requires the branch-wide academic_years.view.
        const seen = new Map<number, YearOption>()
        for (const term of res.data) {
          if (!seen.has(term.academic_year_id)) {
            seen.set(term.academic_year_id, {
              id: term.academic_year_id,
              name: term.academic_year_name ?? "",
            })
          }
        }
        setYears([...seen.values()])
        const currentYear =
          res.data.find((x) => x.status === "active")?.academic_year_id ??
          res.data[0]?.academic_year_id
        if (currentYear) setYearId((prev) => prev || String(currentYear))
      })
      .catch(() => {
        setTerms([])
        setYears([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- branchParam derives from branchFilter
  }, [scopeReady, active.branchId, branchFilter])

  // The academic year the sheet is anchored to: the selected term's year in
  // semester mode, the picked year in yearly mode. Homeroom scoping follows it.
  const anchorYearId =
    mode === "semester"
      ? terms.find((x) => String(x.id) === termId)?.academic_year_id
      : yearId
        ? Number(yearId)
        : undefined

  // Sections behind the grade/section pickers. Supervisors get the whole
  // branch; a homeroom teacher gets only the sections they homeroom for the
  // anchored year (year-aware, so past-year sheets resolve correctly).
  useEffect(() => {
    if (!scopeReady) return
    if (!isSupervisor && !anchorYearId) return
    let cancelled = false
    const homeroomParam =
      !isSupervisor && anchorYearId ? `&mine_homeroom=1&academic_year_id=${anchorYearId}` : ""
    apiFetch<Paginated<Section>>(`/sections?per_page=100${branchParam}${homeroomParam}`)
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- branchParam derives from branchFilter
  }, [scopeReady, active.branchId, branchFilter, isSupervisor, anchorYearId])

  // The sheet is browsed one grade at a time (a whole branch is not a roster).
  const gradeOptions = useMemo(() => {
    const seen = new Map<number, { id: number; name: string; sort: number }>()
    for (const section of sections) {
      const grade = section.grade_level
      if (grade && !seen.has(grade.id)) {
        seen.set(grade.id, { id: grade.id, name: grade.name, sort: grade.sort_order ?? 0 })
      }
    }
    return [...seen.values()].sort((a, b) => a.sort - b.sort)
  }, [sections])

  const sectionOptions = useMemo(
    () => (gradeId ? sections.filter((s) => s.grade_level?.id === Number(gradeId)) : []),
    [sections, gradeId],
  )

  // A homeroom teacher never needs to choose where there's no choice: hide the
  // grade picker when they homeroom one grade, the section picker when one
  // section (a whole-grade "All sections" already covers their classes).
  const distinctSectionCount = useMemo(() => new Set(sections.map((s) => s.id)).size, [sections])
  const showGradeSelect = isSupervisor || gradeOptions.length > 1
  const showSectionSelect = isSupervisor || distinctSectionCount > 1

  // Switching the anchor year can change which grades are on offer (a teacher's
  // homeroom moves); drop a grade that no longer exists rather than query for
  // nothing.
  useEffect(() => {
    if (gradeOptions.length === 0) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear the stale filter
    setGradeId((prev) =>
      prev && !gradeOptions.some((g) => String(g.id) === prev) ? "" : prev,
    )
  }, [gradeOptions])

  // With a single homeroom grade, pick it automatically so the sheet loads
  // without the teacher touching a hidden dropdown.
  useEffect(() => {
    if (isSupervisor || gradeOptions.length !== 1) return
    const only = String(gradeOptions[0].id)
    // eslint-disable-next-line react-hooks/set-state-in-effect -- auto-select the sole grade
    setGradeId((prev) => (prev === only ? prev : only))
  }, [isSupervisor, gradeOptions])

  // Grade change invalidates a section picked under another grade.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear the dependent filter
    setSectionId((prev) =>
      prev !== ALL && !sectionOptions.some((s) => String(s.id) === prev) ? ALL : prev,
    )
  }, [sectionOptions])

  const anchorId = mode === "semester" ? termId : yearId
  const ready = scopeReady && Boolean(anchorId) && Boolean(gradeId)

  useEffect(() => {
    if (!ready) return
    let cancelled = false
    /* eslint-disable react-hooks/set-state-in-effect -- reset for the new query */
    setTermData(null)
    setYearData(null)
    conductEdits.current = {}
    setDirtyConduct(0)
    /* eslint-enable react-hooks/set-state-in-effect */
    const params = new URLSearchParams()
    if (sectionId !== ALL) params.set("section_id", sectionId)
    else params.set("grade_level_id", gradeId)
    const endpoint =
      mode === "semester"
        ? `/terms/${termId}/roster?${params}`
        : `/academic-years/${yearId}/roster?${params}`

    apiFetch<{ data: TermRoster & YearRoster; meta: TermRosterMeta & YearRosterMeta }>(endpoint)
      .then((res) => {
        if (cancelled) return
        if (mode === "semester") setTermData(res)
        else setYearData(res)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        if (mode === "semester") setTermData({ data: { columns: [], rows: [] }, meta: null! })
        else setYearData({ data: { columns: [], students: [] }, meta: null! })
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [ready, active.branchId, mode, termId, yearId, gradeId, sectionId, reloadKey])

  async function compute() {
    setComputing(true)
    try {
      await apiFetch(`/terms/${termId}/compute-results`, { method: "POST" })
      toast.success(t("rosters.recomputed"))
      setReloadKey((k) => k + 1)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setComputing(false)
      setConfirmCompute(false)
    }
  }

  function exportCsv() {
    const labels = {
      student: t("rosters.student"),
      section: t("reportCards.section"),
      period: t("rosters.period"),
      total: t("rosters.total"),
      average: t("rosters.average"),
      rank: t("rosters.rank"),
      yearAvg: t("rosters.yearAvg"),
    }
    if (mode === "semester" && termData) {
      const term = terms.find((x) => String(x.id) === termId)
      downloadCsv(
        termRosterCsv(termData.data.columns, termData.data.rows, labels),
        `roster-${(term?.name ?? "term").replaceAll(" ", "-").toLowerCase()}.csv`,
      )
    } else if (mode === "yearly" && yearData) {
      downloadCsv(
        yearRosterCsv(yearData.data.columns, yearData.data.students, yearData.meta.terms, labels),
        `roster-${(yearData.meta.year.name ?? "year").replaceAll(" ", "-").toLowerCase()}.csv`,
      )
    }
  }

  function exportPdf() {
    const params: Record<string, unknown> = {
      scope: mode === "yearly" ? "year" : "term",
    }
    if (mode === "yearly") params.academic_year_id = Number(yearId)
    else params.term_id = Number(termId)
    if (sectionId !== ALL) params.section_id = Number(sectionId)
    else params.grade_level_id = Number(gradeId)
    void downloadDoc("roster", undefined, params)
  }

  // ── Conduct quick-entry (semester tab) ─────────────────────────────────────
  // Closed semesters are read-only (TermGate) — never render editable inputs
  // the backend would reject.
  const termClosed = terms.find((x) => String(x.id) === termId)?.status === "closed"
  const canEditConduct =
    !termClosed && (canManage || permissions.includes("grades.manage_own"))

  // Both stable (useCallback []) — they only touch the ref, and the table's
  // column memo depends on their identity.
  const editConduct = useCallback((row: TermRosterRow, conduct: string) => {
    conductEdits.current[row.student_enrollment_id] = conduct
    // State only when the dirty COUNT changes — per-keystroke edits stay in
    // the ref and never re-render the table.
    setDirtyConduct(Object.keys(conductEdits.current).length)
  }, [])

  const conductValue = useCallback(
    (row: TermRosterRow) =>
      conductEdits.current[row.student_enrollment_id] ?? row.conduct ?? "",
    [],
  )

  async function saveConduct() {
    if (!termData || dirtyConduct === 0) return
    setSavingConduct(true)
    try {
      // Comments ride along untouched — the quick lane edits conduct only.
      const byEnrollment = new Map(
        termData.data.rows.map((row) => [row.student_enrollment_id, row]),
      )
      await apiFetch(`/terms/${termId}/conduct`, {
        method: "POST",
        body: {
          rows: Object.entries(conductEdits.current).map(([enrollmentId, conduct]) => ({
            student_enrollment_id: Number(enrollmentId),
            conduct: conduct || null,
            comment: byEnrollment.get(Number(enrollmentId))?.comment ?? null,
          })),
        },
      })
      toast.success(t("reportCards.conductSaved"))
      conductEdits.current = {}
      setDirtyConduct(0)
      setReloadKey((k) => k + 1)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSavingConduct(false)
    }
  }

  // ── Report-card surfaces ───────────────────────────────────────────────────
  const reportCardMeta =
    mode === "semester" ? termData?.meta?.report_card : yearData?.meta?.report_card
  const termName = terms.find((x) => String(x.id) === termId)?.name ?? ""

  function openTermExtras(row: TermRosterRow) {
    setExtrasTarget({
      studentName: row.full_name,
      terms: [
        {
          termId: Number(termId),
          termName,
          enrollmentId: row.student_enrollment_id,
          conduct: conductEdits.current[row.student_enrollment_id] ?? row.conduct,
          comment: row.comment,
          skills: row.skills,
          editable: canEditConduct,
        },
      ],
    })
  }

  function openYearExtras(student: YearRosterStudent) {
    if (!yearData) return
    const editableBase = canManage || permissions.includes("grades.manage_own")
    setExtrasTarget({
      studentName: student.full_name,
      terms: student.terms.map((line) => {
        const term = yearData.meta.terms.find((x) => x.id === line.term_id)
        return {
          termId: line.term_id,
          termName: term?.name ?? "",
          enrollmentId: line.student_enrollment_id,
          conduct: line.conduct,
          comment: line.comment,
          skills: line.skills,
          editable: editableBase && term?.status !== "closed",
        }
      }),
    })
  }

  function applyExtras(
    savedTermId: number,
    patch: { conduct: string | null; comment: string | null; skills: Record<string, string> | null },
  ) {
    if (!extrasTarget) return
    const enrollmentId = extrasTarget.terms.find((x) => x.termId === savedTermId)?.enrollmentId
    if (enrollmentId === undefined) return
    setTermData((prev) =>
      prev === null
        ? prev
        : {
            ...prev,
            data: {
              ...prev.data,
              rows: prev.data.rows.map((row) =>
                row.student_enrollment_id === enrollmentId ? { ...row, ...patch } : row,
              ),
            },
          },
    )
    setYearData((prev) =>
      prev === null
        ? prev
        : {
            ...prev,
            data: {
              ...prev.data,
              students: prev.data.students.map((student) => ({
                ...student,
                terms: student.terms.map((line) =>
                  line.term_id === savedTermId && line.student_enrollment_id === enrollmentId
                    ? { ...line, ...patch }
                    : line,
                ),
              })),
            },
          },
    )
    // The inline quick-edit for this row is superseded by the modal's save.
    if (enrollmentId in conductEdits.current) {
      delete conductEdits.current[enrollmentId]
      setDirtyConduct(Object.keys(conductEdits.current).length)
    }
  }

  function openTranscript(studentId: number) {
    window.open(`/print/transcript/${studentId}`, "_blank")
  }

  const toPrintStudents = (
    list: { student_id: number; full_name: string | null }[],
  ) => list.map((s) => ({ id: s.student_id, name: s.full_name }))

  const loading = ready && (mode === "semester" ? termData === null : yearData === null)
  const meta = mode === "semester" ? termData?.meta : yearData?.meta
  const isEmpty =
    mode === "semester"
      ? termData !== null && termData.data.rows.length === 0
      : yearData !== null && yearData.data.students.length === 0
  const showSection = sectionId === ALL
  const frozenAt = meta?.computed_at
    ? fmtDate(meta.computed_at)
    : null
  // Recomputing rebuilds ONE semester's frozen results, so it only makes sense
  // on the semester sheet — the yearly view reads those same rows.
  const canRecompute = canManage && mode === "semester" && Boolean(termId)

  return (
    <div className="space-y-4 pb-10">
      <PageHeader
        title={t("rosters.title")}
        description={t("rosters.subtitle")}
        actions={
          hasWorkspace ? (
            <div className="flex flex-wrap items-center gap-2">
              {needsBranchPick && (
                <BranchScopePicker value={branchFilter} onChange={setBranchFilter} />
              )}
              {mode === "semester" ? (
                <TermSelect
                  terms={terms}
                  value={termId}
                  onValueChange={setTermId}
                  placeholder={t("reports.term")}
                  aria-label={t("reports.term")}
                  className="h-9 w-full md:w-52"
                />
              ) : (
                <Select value={yearId} onValueChange={setYearId}>
                  <SelectTrigger className="h-9 w-full md:w-52" aria-label={t("rosters.year")}>
                    <SelectValue placeholder={t("rosters.year")} />
                  </SelectTrigger>
                  <SelectContent>
                    {years.map((year) => (
                      <SelectItem key={year.id} value={String(year.id)}>
                        {year.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              {showGradeSelect && (
                <Select value={gradeId} onValueChange={setGradeId}>
                  <SelectTrigger
                    className="h-9 w-[calc(50%-0.25rem)] md:w-36"
                    aria-label={t("reports.grade")}
                  >
                    <SelectValue placeholder={t("rosters.selectGrade")} />
                  </SelectTrigger>
                  <SelectContent>
                    {gradeOptions.map((grade) => (
                      <SelectItem key={grade.id} value={String(grade.id)}>
                        {grade.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              {showSectionSelect && (
                <Select value={sectionId} onValueChange={setSectionId} disabled={!gradeId}>
                  <SelectTrigger
                    className="h-9 w-[calc(50%-0.25rem)] md:w-36"
                    aria-label={t("reportCards.section")}
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL}>{t("reportCards.allSections")}</SelectItem>
                    {sectionOptions.map((section) => (
                      <SelectItem key={section.id} value={String(section.id)}>
                        {section.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              {mode === "semester" && canEditConduct && dirtyConduct > 0 && (
                <Button size="sm" onClick={saveConduct} loading={savingConduct}>
                  <Save className="size-4" />
                  {t("reportCards.saveConduct")} ({dirtyConduct})
                </Button>
              )}
              {canExportPdf ? (
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={loading || !ready || generating}
                    >
                      {generating ? (
                        <Loader2 className="size-4 animate-spin" />
                      ) : (
                        <Download className="size-4" />
                      )}
                      {generating ? t("rosters.exporting") : t("rosters.export")}
                      {!generating && <ChevronDown className="size-3.5 opacity-60" />}
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-44">
                    <DropdownMenuItem onSelect={exportPdf} disabled={generating}>
                      {generating ? (
                        <Loader2 className="size-4 animate-spin" />
                      ) : (
                        <FileText className="size-4" />
                      )}
                      {t("rosters.exportPdf")}
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={exportCsv}>
                      <FileSpreadsheet className="size-4" />
                      {t("rosters.exportExcel")}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              ) : (
                <Button variant="outline" size="sm" onClick={exportCsv} loading={loading} disabled={!ready}>
                  <Download className="size-4" />
                  {t("rosters.exportExcel")}
                </Button>
              )}
              {canManage && mode === "semester" && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setConfirmCompute(true)}
                  loading={computing}
                  disabled={!termId}
                >
                  <RefreshCcw className="size-4" />
                  {t("rosters.recompute")}
                </Button>
              )}
            </div>
          ) : undefined
        }
      />

      <div className="page-gutter">
        <ProfileTabBar
          tabs={[
            { key: "semester" as const, label: t("rosters.semesterTab"), icon: Table2 },
            { key: "yearly" as const, label: t("rosters.yearlyTab"), icon: CalendarRange },
          ]}
          value={mode}
          onChange={setMode}
        />
      </div>

      {!scopeReady ? (
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("noBranch")}
          </div>
        </div>
      ) : !ready ? (
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("rosters.selectGradeHint")}
          </div>
        </div>
      ) : (
        <div className="page-gutter space-y-3">
          {mode === "semester" && pendingMarklists > 0 && (
            <Link
              href="/academic/grading-reports?tab=submissions"
              className="border-warning/30 bg-warning/10 hover:bg-warning/15 flex items-center gap-2.5 rounded-2xl border px-4 py-3 text-sm transition-colors"
            >
              <TriangleAlert className="text-warning size-4 shrink-0" />
              <span className="min-w-0 flex-1">
                {t("rosters.incompleteMarklists", { count: pendingMarklists })}
              </span>
              <span className="text-warning shrink-0 text-xs font-medium">
                {t("rosters.viewSubmissions")}
              </span>
            </Link>
          )}
          {/* Frozen-snapshot note: the roster always matches issued report cards. */}
          {frozenAt && (
            <p className="text-muted-foreground flex items-center gap-1.5 text-xs">
              <Snowflake className="size-3.5" />
              {t("rosters.frozenAt", { date: frozenAt })}
            </p>
          )}
          {loading ? (
            <div className="space-y-2">
              {[0, 1, 2, 3, 4, 5].map((i) => (
                <Skeleton key={i} className="h-14 rounded-2xl" />
              ))}
            </div>
          ) : isEmpty ? (
            <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
              <p>{t("rosters.empty")}</p>
              <p className="mt-1 text-xs">{t("rosters.emptyHint")}</p>
              {/* The hint offers a preview — give it the button instead of
                  sending the user hunting for it in the header. */}
              {canRecompute && (
                <Button
                  variant="outline"
                  size="sm"
                  className="mt-4"
                  onClick={() => setConfirmCompute(true)}
                  loading={computing}
                >
                  <RefreshCcw className="size-4" />
                  {t("rosters.recomputeNow")}
                </Button>
              )}
            </div>
          ) : mode === "semester" && termData ? (
            <RosterTable
              // Remount per selection: the conduct inputs are uncontrolled,
              // so a term/section switch must never reuse their DOM values.
              key={`${termId}-${gradeId}-${sectionId}-${reloadKey}`}
              columns={termData.data.columns}
              rows={termData.data.rows}
              showSection={showSection}
              conductEditable={canEditConduct}
              onConductEdit={editConduct}
              conductValue={conductValue}
              onExtras={openTermExtras}
              onPrint={(rows) =>
                setPrintTarget({
                  mode: "semester",
                  termId: Number(termId),
                  students: toPrintStudents(rows),
                })
              }
              onTranscript={(row) => openTranscript(row.student_id)}
              onBulkPrint={(rows) =>
                setPrintTarget({
                  mode: "semester",
                  termId: Number(termId),
                  students: toPrintStudents(rows),
                })
              }
              onBulkTranscripts={(rows) =>
                setPrintTarget({
                  mode: "transcripts",
                  yearId: anchorYearId,
                  students: toPrintStudents(rows),
                })
              }
            />
          ) : mode === "yearly" && yearData ? (
            <YearlyRosterTable
              columns={yearData.data.columns}
              students={yearData.data.students}
              terms={yearData.meta.terms}
              showSection={showSection}
              onExtras={openYearExtras}
              onPrint={(rows) =>
                setPrintTarget({
                  mode: "yearly",
                  yearId: Number(yearId),
                  students: toPrintStudents(rows),
                })
              }
              onTranscript={(row) => openTranscript(row.student_id)}
              onBulkPrint={(rows) =>
                setPrintTarget({
                  mode: "yearly",
                  yearId: Number(yearId),
                  students: toPrintStudents(rows),
                })
              }
              onBulkTranscripts={(rows) =>
                setPrintTarget({
                  mode: "transcripts",
                  yearId: Number(yearId),
                  students: toPrintStudents(rows),
                })
              }
            />
          ) : null}
        </div>
      )}

      <ReportCardPrintDialog
        target={printTarget}
        onOpenChange={(open) => !open && setPrintTarget(null)}
      />

      <ReportCardExtras
        target={extrasTarget}
        skills={reportCardMeta?.skills ?? []}
        onOpenChange={(open) => !open && setExtrasTarget(null)}
        onSaved={applyExtras}
      />

      {/* Recomputing rewrites the semester's frozen numbers — say exactly what
          that means before it runs. */}
      <AlertDialog open={confirmCompute} onOpenChange={(open) => !computing && setConfirmCompute(open)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("rosters.recomputeTitle")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("rosters.recomputeBody", { term: termName })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={computing}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={computing}
              onClick={(e) => {
                e.preventDefault()
                void compute()
              }}
            >
              {t("rosters.recompute")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
