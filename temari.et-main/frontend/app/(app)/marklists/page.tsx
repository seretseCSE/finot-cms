"use client"

import { ArrowRight, UserPen } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { BranchScopePicker } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { TermSelect } from "@/components/academic/term-select"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { MarklistRegisterRow, MarklistStatus, Paginated, Term } from "@/lib/types"
import { cn } from "@/lib/utils"

// Status tints: submitted rows glow amber ("waiting on the director"),
// approved rows green ("countersigned") — same tokens as the promotion board.
const STATUS_BADGE: Record<MarklistStatus, string> = {
  draft: "bg-muted text-muted-foreground",
  submitted: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
}
const STATUS_ROW: Partial<Record<MarklistStatus, string>> = {
  submitted: "bg-warning/[0.07] hover:bg-warning/[0.12]",
  approved: "bg-success/[0.07] hover:bg-success/[0.12]",
}

/** Register row + flat keys the filters and search match on. */
type RegisterRow = MarklistRegisterRow & {
  grade_key: string
  section_key: string
  subject_key: string
  teacher_key: string
  /** Flattened workflow status — DataTable's filters read flat keys only. */
  status: MarklistStatus
}

export default function MarklistsPage() {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const [terms, setTerms] = useState<Term[] | null>(null)
  const [termId, setTermId] = useState<string>("")
  const [branchFilter, setBranchFilter] = useState<number | null>(null)
  const [rows, setRows] = useState<RegisterRow[] | null>(null)
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})
  // Applied once per register load; any manual filter change wins after that.
  const defaultScopeApplied = useRef(false)

  const supervisor =
    permissions.includes("grades.manage") || permissions.includes("grades.approve")
  const hasWorkspace = !isPlatform && active.schoolId !== null
  const needsBranchPick = hasWorkspace && active.branchId === null

  useEffect(() => {
    if (!hasWorkspace) return
    let cancelled = false
    apiFetch<Paginated<Term>>("/terms?per_page=100")
      .then((res) => {
        if (cancelled) return
        setTerms(res.data)
        const current = res.data.find((x) => x.status === "active")?.id ?? res.data[0]?.id
        if (current) setTermId((prev) => prev || String(current))
      })
      .catch(() => setTerms([]))
    return () => {
      cancelled = true
    }
  }, [hasWorkspace, active.schoolId, active.branchId])

  useEffect(() => {
    if (!hasWorkspace || !termId) return
    let cancelled = false
    /* eslint-disable react-hooks/set-state-in-effect -- reset for the new query */
    setRows(null)
    defaultScopeApplied.current = false
    /* eslint-enable react-hooks/set-state-in-effect */
    const branchParam = branchFilter !== null ? `&branch_id=${branchFilter}` : ""
    apiFetch<{ data: MarklistRegisterRow[] }>(
      `/marklists?term_id=${termId}&per_page=100${branchParam}`,
    )
      // DataTable rows key off `id` — the assignment id is the natural key.
      .then(
        (res) =>
          !cancelled &&
          setRows(
            res.data.map((r) => ({
              ...r,
              id: r.subject_assignment_id,
              grade_key: r.section.grade_level ?? "",
              section_key: r.section.name ?? "",
              subject_key: r.subject.name ?? "",
              teacher_key: r.teacher_name ?? "",
              status: r.marklist?.status ?? "draft",
            })),
          ),
      )
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [hasWorkspace, active.schoolId, active.branchId, termId, branchFilter])

  // Supervisors land on a register with hundreds of rows — default the view
  // to the first grade's first section so it opens fast and focused.
  // Teachers see only their own assignments; never narrow those by default.
  useEffect(() => {
    if (!supervisor || rows === null || rows.length === 0 || defaultScopeApplied.current) return
    defaultScopeApplied.current = true
    const grades = [...new Set(rows.map((r) => r.grade_key).filter(Boolean))].sort((a, b) =>
      a.localeCompare(b, undefined, { numeric: true }),
    )
    const firstGrade = grades[0]
    if (!firstGrade) return
    const sections = [
      ...new Set(rows.filter((r) => r.grade_key === firstGrade).map((r) => r.section_key)),
    ].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-shot default scope
    setFilterValues((prev) => {
      if (prev.grade_key || prev.section_key) return prev
      return { ...prev, grade_key: firstGrade, section_key: sections[0] ?? "" }
    })
  }, [supervisor, rows])

  // A stale section filter from another grade would blank the table.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear the dependent filter
    setFilterValues((prev) => {
      if (!prev.section_key || !prev.grade_key || rows === null) return prev
      const grades = prev.grade_key.split(",").filter(Boolean)
      const valid = new Set(
        rows.filter((r) => grades.includes(r.grade_key)).map((r) => r.section_key),
      )
      const kept = prev.section_key.split(",").filter((s) => valid.has(s))
      return kept.length === prev.section_key.split(",").filter(Boolean).length
        ? prev
        : { ...prev, section_key: kept.join(",") }
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps -- rows only carries the option universe
  }, [filterValues.grade_key])

  const columns: DataTableColumn<RegisterRow>[] = useMemo(
    () => [
      {
        key: "subject",
        label: t("marklists.subject"),
        primary: true,
        render: (row) => (
          <p className="min-w-0 truncate font-medium">
            {row.subject.name}
            <span className="text-muted-foreground font-normal">
              {" · "}
              {row.section.grade_level} {row.section.name}
            </span>
          </p>
        ),
        exportValue: (row) =>
          `${row.subject.name} — ${row.section.grade_level ?? ""} ${row.section.name ?? ""}`.trim(),
      },
      ...(supervisor
        ? [
            {
              key: "teacher_key",
              label: t("marklists.teacher"),
              mobileHidden: true,
              render: (row: RegisterRow) => row.teacher_name ?? "—",
              exportValue: (row: RegisterRow) => row.teacher_name ?? "",
            } as DataTableColumn<RegisterRow>,
          ]
        : []),
      {
        key: "assessments_count",
        label: t("continuousAssessment.items"),
        mobileHidden: true,
        render: (row) => <span className="tabular-nums">{row.assessments_count}</span>,
        exportValue: (row) => String(row.assessments_count),
      },
      {
        key: "status",
        label: t("marklists.status"),
        render: (row) => (
          <span className="inline-flex items-center gap-1.5">
            <Badge variant="outline" className={cn("border", STATUS_BADGE[row.status])}>
              {t(`marklists.statuses.${row.status}`)}
            </Badge>
            {/* On-behalf entry happened here — the approval queue must see it. */}
            {row.marklist?.assisted_by_name && (
              <span
                title={t("marklists.grid.assistActive", {
                  name: row.marklist.assisted_by_name,
                })}
                aria-label={t("marklists.grid.assistActive", {
                  name: row.marklist.assisted_by_name,
                })}
                className="inline-flex size-5 items-center justify-center rounded-full bg-warning/15 text-warning"
              >
                <UserPen className="size-3" />
              </span>
            )}
          </span>
        ),
        exportValue: (row) => row.status,
      },
      {
        key: "open",
        label: "",
        mobileHidden: true,
        render: () => (
          <Button variant="ghost" size="sm" className="gap-1.5">
            {t("marklists.open")}
            <ArrowRight className="size-3.5" />
          </Button>
        ),
        exportValue: () => "",
      },
    ],
    [t, supervisor],
  )

  // Filter options come from the loaded register itself.
  const gradeOptions = useMemo(
    () =>
      [...new Set((rows ?? []).map((r) => r.grade_key).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b, undefined, { numeric: true }),
      ),
    [rows],
  )
  const sectionOptions = useMemo(() => {
    const wanted = (filterValues.grade_key ?? "").split(",").filter(Boolean)
    const inScope = (rows ?? []).filter(
      (r) => wanted.length === 0 || wanted.includes(r.grade_key),
    )
    return [...new Set(inScope.map((r) => r.section_key).filter(Boolean))].sort((a, b) =>
      a.localeCompare(b, undefined, { numeric: true }),
    )
  }, [rows, filterValues.grade_key])
  const subjectOptions = useMemo(
    () =>
      [...new Set((rows ?? []).map((r) => r.subject_key).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b),
      ),
    [rows],
  )
  const teacherOptions = useMemo(
    () =>
      [...new Set((rows ?? []).map((r) => r.teacher_key).filter(Boolean))].sort((a, b) =>
        a.localeCompare(b),
      ),
    [rows],
  )

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("marklists.title")}
        description={supervisor ? t("marklists.queueSubtitle") : t("marklists.subtitle")}
        actions={
          hasWorkspace ? (
            <div className="flex flex-wrap items-center gap-2">
              {needsBranchPick && (
                <BranchScopePicker value={branchFilter} onChange={setBranchFilter} />
              )}
              <TermSelect
                terms={terms ?? []}
                value={termId}
                onValueChange={setTermId}
                placeholder={t("marklists.term")}
                aria-label={t("marklists.term")}
                className="h-9 w-full md:w-64"
              />
            </div>
          ) : undefined
        }
      />

      {!hasWorkspace ? (
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("noBranch")}
          </div>
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={rows ?? []}
          loading={rows === null}
          searchKeys={["teacher_key", "subject_key"]}
          searchPlaceholder={tc("actions.search")}
          filters={[
            ...(gradeOptions.length > 0
              ? [
                  {
                    key: "grade_key",
                    label: t("reports.grade"),
                    options: gradeOptions.map((name) => ({ label: name, value: name })),
                  },
                ]
              : []),
            ...(sectionOptions.length > 0
              ? [
                  {
                    key: "section_key",
                    label: t("reportCards.section"),
                    // Cascades from grade (grade always has a default here).
                    dependsOn: "grade_key",
                    options: sectionOptions.map((name) => ({ label: name, value: name })),
                  },
                ]
              : []),
            ...(subjectOptions.length > 0
              ? [
                  {
                    key: "subject_key",
                    label: t("marklists.subjectFilter"),
                    options: subjectOptions.map((name) => ({ label: name, value: name })),
                  },
                ]
              : []),
            ...(supervisor && teacherOptions.length > 0
              ? [
                  {
                    key: "teacher_key",
                    label: t("marklists.teacher"),
                    options: teacherOptions.map((name) => ({ label: name, value: name })),
                  },
                ]
              : []),
            {
              key: "status",
              label: t("marklists.status"),
              options: (["draft", "submitted", "approved"] as MarklistStatus[]).map((s) => ({
                label: t(`marklists.statuses.${s}`),
                value: s,
              })),
            },
          ]}
          filterValues={filterValues}
          onFilterChange={(key, value) => setFilterValues((prev) => ({ ...prev, [key]: value }))}
          rowClassName={(row) => STATUS_ROW[row.status]}
          onRowClick={(row) => router.push(`/marklists/${row.subject_assignment_id}`)}
          emptyMessage={t("marklists.empty")}
          exportFilename="marklists"
        />
      )}
    </div>
  )
}
