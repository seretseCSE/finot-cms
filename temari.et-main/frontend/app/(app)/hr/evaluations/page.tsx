"use client"

import { CheckCheck, ClipboardCheck, Eye, Plus, Settings2 } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { EvaluationEditor, EVALUATION_STATUS_BADGE, scoreTone } from "@/components/hr/evaluation-editor"
import { EvaluationTemplateSheet } from "@/components/hr/evaluation-template-sheet"
import { Badge } from "@/components/ui/badge"
import { BranchScopePicker } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { TermSelect } from "@/components/academic/term-select"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { fmtDate } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type { Employee, Paginated, TeacherEvaluationRow, Term } from "@/lib/types"
import { cn } from "@/lib/utils"

/** Register row + flat keys for the DataTable search/filters. */
type Row = TeacherEvaluationRow & { teacher_key: string; evaluator_key: string }

/**
 * Teacher performance appraisals (MoE continuous appraisal). Supervisors run
 * the per-semester register — start, score, share, chase acknowledgments;
 * evaluated staff land on their own record cards and sign there.
 */
export default function TeacherEvaluationsPage() {
  const { t } = useTranslation("hr")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const canManage = permissions.includes("evaluations.manage")
  const supervisor = canManage || permissions.includes("evaluations.view")
  const hasWorkspace = !isPlatform && active.schoolId !== null
  const needsBranchPick = hasWorkspace && supervisor && active.branchId === null

  const [branchFilter, setBranchFilter] = useState<number | null>(null)
  const [terms, setTerms] = useState<Term[]>([])
  const [termId, setTermId] = useState("")
  const [rows, setRows] = useState<Row[] | null>(null)
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})

  const [editorId, setEditorId] = useState<number | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [templateOpen, setTemplateOpen] = useState(false)

  // "New appraisal" dialog state.
  const [createOpen, setCreateOpen] = useState(false)
  const [employees, setEmployees] = useState<Employee[] | null>(null)
  const [employeeId, setEmployeeId] = useState("")
  const [creating, setCreating] = useState(false)

  const scopeReady = hasWorkspace && (!supervisor || active.branchId !== null || branchFilter !== null)
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
      })
      .catch(() => setTerms([]))
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- branchParam derives from branchFilter
  }, [scopeReady, active.branchId, branchFilter])

  const load = useCallback(() => {
    if (!scopeReady || !termId) return
    setRows(null)
    const mine = supervisor ? "" : "&mine=1"
    apiFetch<{ data: TeacherEvaluationRow[] }>(
      `/hr/evaluations?term_id=${termId}${mine}${branchParam}`,
    )
      .then((res) =>
        setRows(
          res.data.map((row) => ({
            ...row,
            teacher_key: row.employee.name ?? "",
            evaluator_key: row.evaluator_name ?? "",
          })),
        ),
      )
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc stable enough
  }, [scopeReady, termId, supervisor, branchParam])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset + refetch for the new scope
    load()
  }, [load])

  function openCreate() {
    setEmployeeId("")
    setCreateOpen(true)
    if (employees !== null) return
    apiFetch<Paginated<Employee>>(`/employees?per_page=100&is_active=1${branchParam}`)
      .then((res) => setEmployees(res.data))
      .catch(() => setEmployees([]))
  }

  async function create() {
    if (!employeeId) return
    setCreating(true)
    try {
      const res = await apiFetch<{ data: { id: number } }>("/hr/evaluations", {
        method: "POST",
        body: { employee_id: Number(employeeId), term_id: Number(termId) },
      })
      setCreateOpen(false)
      setEditorId(res.data.id)
      setEditorOpen(true)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setCreating(false)
    }
  }

  const stats = useMemo(() => {
    if (rows === null) return null
    const withScore = rows.filter((r) => r.overall_score !== null && r.status !== "draft")
    return {
      total: rows.length,
      submitted: rows.filter((r) => r.status === "submitted").length,
      acknowledged: rows.filter((r) => r.status === "acknowledged").length,
      average:
        withScore.length > 0
          ? Math.round(
              (withScore.reduce((sum, r) => sum + (r.overall_score ?? 0), 0) / withScore.length) * 10,
            ) / 10
          : null,
    }
  }, [rows])

  const columns: DataTableColumn<Row>[] = useMemo(
    () => [
      {
        key: "teacher_key",
        label: t("evaluations.teacher"),
        primary: true,
        render: (row) => (
          <div className="flex min-w-0 items-center gap-2.5">
            <PersonAvatar
              name={row.employee.name ?? "?"}
              photoUrl={row.employee.photo_url}
              className="size-7 shrink-0"
            />
            <span className="truncate font-medium">{row.employee.name ?? "—"}</span>
          </div>
        ),
        exportValue: (row) => row.employee.name ?? "",
      },
      {
        key: "overall_score",
        label: t("evaluations.overall"),
        render: (row) => (
          <span className={cn("text-sm font-semibold tabular-nums", scoreTone(row.overall_score))}>
            {row.overall_score ?? "—"}
          </span>
        ),
        exportValue: (row) => (row.overall_score !== null ? String(row.overall_score) : ""),
      },
      {
        key: "status",
        label: t("evaluations.status"),
        render: (row) => (
          <Badge variant="outline" className={cn("border", EVALUATION_STATUS_BADGE[row.status])}>
            {t(`evaluations.statuses.${row.status}`)}
          </Badge>
        ),
        exportValue: (row) => row.status,
      },
      {
        key: "evaluator_key",
        label: t("evaluations.evaluator"),
        mobileHidden: true,
        render: (row) => (
          <span className="text-muted-foreground text-xs">{row.evaluator_name ?? "—"}</span>
        ),
      },
      {
        key: "submitted_at",
        label: t("evaluations.sharedOn"),
        mobileHidden: true,
        render: (row) => (
          <span className="text-muted-foreground text-xs">
            {row.submitted_at ? fmtDate(row.submitted_at) : "—"}
          </span>
        ),
        exportValue: (row) => row.submitted_at ?? "",
      },
    ],
    [t],
  )

  if (!hasWorkspace || (!supervisor && !permissions.includes("evaluations.view_own"))) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("evaluations.title")} />
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {!hasWorkspace ? t("evaluations.noWorkspace") : tc("errors.forbidden")}
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6 pb-10">
      <PageHeader
        title={t("evaluations.title")}
        description={supervisor ? t("evaluations.subtitle") : t("evaluations.mineSubtitle")}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {needsBranchPick && <BranchScopePicker value={branchFilter} onChange={setBranchFilter} />}
            <TermSelect
              terms={terms}
              value={termId}
              onValueChange={setTermId}
              placeholder={t("evaluations.term")}
              aria-label={t("evaluations.term")}
              className="h-9 w-full md:w-56"
            />
            {canManage && (
              <>
                <Button
                  variant="outline"
                  size="icon"
                  className="size-9"
                  onClick={() => setTemplateOpen(true)}
                  aria-label={t("evaluations.template.title")}
                  title={t("evaluations.template.title")}
                >
                  <Settings2 className="size-4" />
                </Button>
                <Button size="sm" onClick={openCreate} disabled={!termId}>
                  <Plus className="size-4" />
                  {t("evaluations.new")}
                </Button>
              </>
            )}
          </div>
        }
      />

      {!scopeReady ? (
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("evaluations.noWorkspace")}
          </div>
        </div>
      ) : supervisor ? (
        <>
          {/* Semester pulse */}
          <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
            {stats === null ? (
              [0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-24 rounded-2xl" />)
            ) : (
              <>
                <StatCard label={t("evaluations.statTotal")} value={stats.total} icon={ClipboardCheck} />
                <StatCard
                  label={t("evaluations.statAverage")}
                  value={stats.average ?? "—"}
                  icon={ClipboardCheck}
                  hint={t("evaluations.outOf100")}
                />
                <StatCard
                  label={t("evaluations.statuses.submitted")}
                  value={stats.submitted}
                  icon={Eye}
                  hint={t("evaluations.awaitingSignature")}
                />
                <StatCard
                  label={t("evaluations.statuses.acknowledged")}
                  value={stats.acknowledged}
                  icon={CheckCheck}
                />
              </>
            )}
          </div>

          <DataTable
            columns={columns}
            data={rows ?? []}
            loading={rows === null}
            dense
            searchKeys={["teacher_key", "evaluator_key"]}
            searchPlaceholder={tc("actions.search")}
            filters={[
              {
                key: "status",
                label: t("evaluations.status"),
                options: (["draft", "submitted", "acknowledged"] as const).map((status) => ({
                  label: t(`evaluations.statuses.${status}`),
                  value: status,
                })),
              },
            ]}
            filterValues={filterValues}
            onFilterChange={(key, value) => setFilterValues((prev) => ({ ...prev, [key]: value }))}
            actions={[
              {
                label: t("evaluations.open"),
                icon: Eye,
                primary: true,
                onClick: (row) => {
                  setEditorId(row.id)
                  setEditorOpen(true)
                },
              },
            ]}
            emptyMessage={t("evaluations.emptyRegister")}
            exportFilename="teacher-appraisals"
          />
        </>
      ) : (
        /* The employee's own lane: signed + waiting records as cards. */
        <div className="page-gutter space-y-3">
          {rows === null ? (
            [0, 1].map((i) => <Skeleton key={i} className="h-24 rounded-2xl" />)
          ) : rows.length === 0 ? (
            <EmptyState
              icon={ClipboardCheck}
              title={t("evaluations.emptyMineTitle")}
              description={t("evaluations.emptyMineBody")}
            />
          ) : (
            rows.map((row) => (
              <button
                key={row.id}
                type="button"
                onClick={() => {
                  setEditorId(row.id)
                  setEditorOpen(true)
                }}
                className="bg-card hover:bg-accent/40 flex w-full items-center gap-3.5 rounded-2xl border p-4 text-left shadow-xs transition-colors"
              >
                <div
                  className={cn(
                    "flex size-14 shrink-0 flex-col items-center justify-center rounded-2xl border",
                    row.status === "submitted" ? "border-warning/40 bg-warning/5" : "border-success/40 bg-success/5",
                  )}
                >
                  <span className={cn("font-display text-lg font-bold tabular-nums", scoreTone(row.overall_score))}>
                    {row.overall_score ?? "—"}
                  </span>
                  <span className="text-muted-foreground text-[9px] uppercase">/100</span>
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">{row.term.name}</p>
                  <p className="text-muted-foreground text-xs">
                    {row.evaluator_name}
                    {row.submitted_at ? ` · ${fmtDate(row.submitted_at)}` : ""}
                  </p>
                  <Badge
                    variant="outline"
                    className={cn("mt-1.5 border", EVALUATION_STATUS_BADGE[row.status])}
                  >
                    {row.status === "submitted"
                      ? t("evaluations.actionNeeded")
                      : t(`evaluations.statuses.${row.status}`)}
                  </Badge>
                </div>
              </button>
            ))
          )}
        </div>
      )}

      <EvaluationEditor
        evaluationId={editorId}
        canManage={canManage}
        open={editorOpen}
        onOpenChange={setEditorOpen}
        onChanged={load}
      />
      <EvaluationTemplateSheet open={templateOpen} onOpenChange={setTemplateOpen} />

      {/* New appraisal: pick the employee, the semester is already chosen. */}
      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t("evaluations.new")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <p className="text-muted-foreground text-xs">{t("evaluations.newHint")}</p>
            <Select value={employeeId} onValueChange={setEmployeeId}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t("evaluations.pickEmployee")} />
              </SelectTrigger>
              <SelectContent emptyNotice={tc("emptySelect.none")}>
                {(employees ?? [])
                  .filter((e) => !rows?.some((r) => r.employee.id === e.id))
                  .map((employee) => (
                    <SelectItem key={employee.id} value={String(employee.id)}>
                      {employee.full_name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setCreateOpen(false)}
              disabled={creating}
            >
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" onClick={() => void create()} loading={creating} disabled={!employeeId}>
              {t("evaluations.start")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
