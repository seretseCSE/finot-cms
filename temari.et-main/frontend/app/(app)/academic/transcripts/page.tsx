"use client"

import { Download, Printer, ScrollText } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import {
  transcriptExportColumns,
  transcriptExportRows,
} from "@/components/grading/transcript-export"
import { TranscriptYearPicker } from "@/components/grading/transcript-year-picker"
import { Badge } from "@/components/ui/badge"
import { BranchScopePicker } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { DataTable, exportExcel, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import { fmtDate } from "@/lib/dates"
import type {
  Paginated,
  Section,
  Term,
  Transcript,
  TranscriptRegisterRow,
} from "@/lib/types"

const ALL = "all"
/** Backend cap per batch call. */
const CHUNK = 60
/** Print-batch hard cap — the official batch PDF renders one page per
 * student in a single pass, so a run stays section-sized (matches the
 * backend's transcript_batch / register batch limit). */
const MAX_PRINT = CHUNK

type Row = TranscriptRegisterRow & { id: number }

/** Academic year, as distilled from /terms — homeroom teachers only get
 * `timetable.view`, never `academic_years.view`, so the picker must never
 * hit /academic-years directly. See report-cards / continuous-assessments. */
interface YearOption {
  id: number
  name: string
}

export default function TranscriptsPage() {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [branchFilter, setBranchFilter] = useState<number | null>(null)
  const [years, setYears] = useState<YearOption[]>([])
  const [yearId, setYearId] = useState<string>("")
  const [sections, setSections] = useState<Section[]>([])
  const [gradeId, setGradeId] = useState<string>("")
  const [sectionId, setSectionId] = useState<string>(ALL)
  const [rows, setRows] = useState<Row[] | null>(null)
  const [exporting, setExporting] = useState(false)
  // Years-covered narrowing for the printed/exported sheets — null = the
  // complete record (default); a subset prints PARTIAL transcripts.
  const [coveredYearIds, setCoveredYearIds] = useState<number[] | null>(null)

  // Supervisors (grades.view) browse every section; a homeroom teacher
  // (grades.manage_own only) reads ONLY their own homeroom, so the pickers
  // must list exactly those and disappear when there's nothing to choose.
  const isSupervisor = permissions.includes("grades.view")
  const hasWorkspace = !isPlatform && active.schoolId !== null
  const needsBranchPick = hasWorkspace && active.branchId === null
  // Years + sections are branch-scoped; school managers pick a branch first.
  const scopeReady = hasWorkspace && (active.branchId !== null || branchFilter !== null)
  const branchParam = needsBranchPick && branchFilter !== null ? `&branch_id=${branchFilter}` : ""

  useEffect(() => {
    if (!scopeReady) return
    let cancelled = false
    // Terms are readable via timetable.view (which homeroom teachers hold),
    // unlike /academic-years which requires the branch-wide academic_years.view.
    apiFetch<Paginated<Term>>(`/terms?per_page=100${branchParam}`)
      .then((res) => {
        if (cancelled) return
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
        const current =
          res.data.find((x) => x.status === "active")?.academic_year_id ??
          res.data[0]?.academic_year_id
        if (current) setYearId((prev) => prev || String(current))
      })
      .catch(() => setYears([]))
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- branchParam derives from branchFilter
  }, [scopeReady, active.branchId, branchFilter])

  // Sections behind the grade/section pickers. Supervisors browse the whole
  // branch; a homeroom teacher gets only the sections they homeroom for the
  // picked year — mirrors TranscriptController::allowedSectionIds, so the
  // pickers never offer a class whose register would come back empty.
  useEffect(() => {
    if (!scopeReady) return
    if (!isSupervisor && !yearId) return
    let cancelled = false
    const homeroomParam =
      !isSupervisor && yearId ? `&mine_homeroom=1&academic_year_id=${yearId}` : ""
    apiFetch<Paginated<Section>>(`/sections?per_page=100${branchParam}${homeroomParam}`)
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- branchParam derives from branchFilter
  }, [scopeReady, active.branchId, branchFilter, isSupervisor, yearId])

  // The register is browsed one grade at a time — a whole branch is not a
  // printable batch.
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

  // A homeroom teacher never chooses where there's no choice: hide the grade
  // picker when they homeroom a single grade, the section picker when a single
  // section (the whole-grade "All sections" already covers their classes).
  const distinctSectionCount = useMemo(() => new Set(sections.map((s) => s.id)).size, [sections])
  const showGradeSelect = isSupervisor || gradeOptions.length > 1
  const showSectionSelect = isSupervisor || distinctSectionCount > 1

  // Switching year can change which grades are on offer (a teacher's homeroom
  // moves); drop a grade that no longer exists rather than query for nothing.
  useEffect(() => {
    if (gradeOptions.length === 0) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear the stale filter
    setGradeId((prev) =>
      prev && !gradeOptions.some((g) => String(g.id) === prev) ? "" : prev,
    )
  }, [gradeOptions])

  // With a single homeroom grade, pick it automatically so the register loads
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

  const ready = scopeReady && Boolean(yearId) && Boolean(gradeId)

  useEffect(() => {
    if (!ready) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new query
    setRows(null)
    const params = new URLSearchParams()
    if (sectionId !== ALL) params.set("section_id", sectionId)
    else params.set("grade_level_id", gradeId)
    apiFetch<{ data: TranscriptRegisterRow[] }>(
      `/academic-years/${yearId}/transcript-register?${params}`,
    )
      .then((res) => {
        if (cancelled) return
        setRows(res.data.map((r) => ({ ...r, id: r.student_id })))
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [ready, active.branchId, yearId, gradeId, sectionId])

  function printUrl(studentIds: number[]): string {
    const years = coveredYearIds === null ? "" : `&years=${coveredYearIds.join(",")}`
    return `/print/transcripts?year_id=${yearId}&ids=${studentIds.join(",")}${years}`
  }

  function printSelected(selected: Row[]) {
    if (selected.length > MAX_PRINT) {
      toast.error(t("transcripts.tooMany", { max: MAX_PRINT }))
      return
    }
    window.open(printUrl(selected.map((r) => r.student_id)), "_blank")
  }

  async function exportSelected(selected: Row[]) {
    setExporting(true)
    try {
      const ids = selected.map((r) => r.student_id)
      const yearsQuery = (coveredYearIds ?? [])
        .map((id) => `&academic_year_ids[]=${id}`)
        .join("")
      const all: Transcript[] = []
      for (let i = 0; i < ids.length; i += CHUNK) {
        const params = ids
          .slice(i, i + CHUNK)
          .map((id) => `student_ids[]=${id}`)
          .join("&")
        const res = await apiFetch<{ data: Transcript[] }>(
          `/reports/transcripts?academic_year_id=${yearId}&${params}${yearsQuery}`,
        )
        all.push(...res.data)
      }
      exportExcel(
        transcriptExportColumns({
          student: t("transcripts.student"),
          publicId: "ID",
          year: t("transcripts.year"),
          grade: t("reports.grade"),
          school: t("transcript.school"),
          branch: t("transcripts.branch"),
          sem1: `${t("transcript.semesterAverage")} 1`,
          sem2: `${t("transcript.semesterAverage")} 2`,
          annual: t("transcript.annualAverage"),
        }),
        transcriptExportRows(all),
        "transcripts",
      )
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setExporting(false)
    }
  }

  const columns: DataTableColumn<Row>[] = useMemo(
    () => [
      {
        key: "full_name",
        label: t("transcripts.student"),
        primary: true,
        render: (row) => (
          <div className="flex min-w-0 items-center gap-2.5">
            <PersonAvatar name={row.full_name ?? "?"} photoUrl={row.photo_url} className="size-8" />
            <div className="min-w-0">
              <p className="truncate font-medium">{row.full_name ?? "—"}</p>
              {row.public_id && (
                <p className="text-muted-foreground text-[11px]">{row.public_id}</p>
              )}
            </div>
          </div>
        ),
        exportValue: (row) => row.full_name ?? "",
      },
      {
        key: "section_name",
        label: t("reportCards.section"),
        render: (row) =>
          [row.grade_level_name, row.section_name].filter(Boolean).join(" ") || "—",
        exportValue: (row) =>
          [row.grade_level_name, row.section_name].filter(Boolean).join(" "),
      },
      {
        key: "terms_count",
        label: t("transcripts.readiness"),
        sortable: true,
        render: (row) =>
          row.terms_count > 0 ? (
            <span className="text-sm tabular-nums">
              {t("transcripts.readinessValue", {
                years: row.years_count,
                terms: row.terms_count,
              })}
            </span>
          ) : (
            <Badge variant="outline" className="text-muted-foreground">
              {t("transcripts.notReady")}
            </Badge>
          ),
        exportValue: (row) => String(row.terms_count),
      },
      {
        key: "last_computed_at",
        label: t("transcripts.lastComputed"),
        mobileHidden: true,
        render: (row) =>
          row.last_computed_at ? (
            <span className="text-muted-foreground text-xs">
              {fmtDate(row.last_computed_at)}
            </span>
          ) : (
            "—"
          ),
        exportValue: (row) => row.last_computed_at ?? "",
      },
      {
        key: "actions_view",
        label: "",
        render: (row) => (
          <div className="flex justify-end" onClick={(e) => e.stopPropagation()}>
            <Button variant="ghost" size="sm" asChild>
              <a href={printUrl([row.student_id])} target="_blank" rel="noreferrer">
                <ScrollText className="size-3.5" />
                {t("transcripts.view")}
              </a>
            </Button>
          </div>
        ),
        exportValue: () => "",
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps -- printUrl only reads yearId
    [t, yearId],
  )

  return (
    <div className="space-y-6 pb-10">
      <PageHeader
        title={t("transcripts.title")}
        description={t("transcripts.subtitle")}
        actions={
          hasWorkspace ? (
            <div className="flex flex-wrap items-center gap-2">
              {needsBranchPick && (
                <BranchScopePicker value={branchFilter} onChange={setBranchFilter} />
              )}
              <Select value={yearId} onValueChange={setYearId}>
                <SelectTrigger className="h-9 w-full md:w-48" aria-label={t("transcripts.year")}>
                  <SelectValue placeholder={t("transcripts.year")} />
                </SelectTrigger>
                <SelectContent>
                  {years.map((year) => (
                    <SelectItem key={year.id} value={String(year.id)}>
                      {year.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {showGradeSelect && (
                <Select value={gradeId} onValueChange={setGradeId}>
                  <SelectTrigger
                    className="h-9 w-[calc(50%-0.25rem)] md:w-36"
                    aria-label={t("reports.grade")}
                  >
                    <SelectValue placeholder={t("transcripts.selectGrade")} />
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
              <TranscriptYearPicker
                options={years.map((year) => ({ id: year.id, label: year.name }))}
                value={coveredYearIds}
                onChange={setCoveredYearIds}
              />
            </div>
          ) : undefined
        }
      />

      {!scopeReady ? (
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("noBranch")}
          </div>
        </div>
      ) : !ready ? (
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("transcripts.selectGradeHint")}
          </div>
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={rows ?? []}
          loading={rows === null}
          searchKeys={["full_name", "public_id"]}
          searchPlaceholder={tc("actions.search")}
          emptyMessage={`${t("transcripts.empty")} ${t("transcripts.emptyHint")}`}
          exportFilename="transcript-register"
          bulkActions={[
            {
              label: t("transcripts.printSelected"),
              icon: Printer,
              onClick: printSelected,
            },
            {
              label: exporting ? t("transcripts.exporting") : t("transcripts.exportExcel"),
              icon: Download,
              onClick: (selected) => void exportSelected(selected),
            },
          ]}
        />
      )}
    </div>
  )
}
