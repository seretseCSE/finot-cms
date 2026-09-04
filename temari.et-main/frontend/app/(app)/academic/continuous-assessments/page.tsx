"use client"

import { Pencil, Plus, Trash2 } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import {
  ContinuousAssessmentSheet,
  describeTarget,
} from "@/components/grading/continuous-assessment-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { groupTermsByYear, termFullLabel } from "@/components/academic/term-select"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { ContinuousAssessment, Paginated, Subject, Term } from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"

export default function ContinuousAssessmentsPage() {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [books, setBooks] = useState<ContinuousAssessment[] | null>(null)
  const [terms, setTerms] = useState<Term[]>([])
  const [subjects, setSubjects] = useState<Subject[]>([])
  // Branch-scoped grade offering, session-cached across pages.
  const { grades: gradeLevels } = useGradeLevels()
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})
  const [reloadKey, setReloadKey] = useState(0)
  // School → Branch narrowing for the school-wide workspace — applied
  // server-side (refetch); term/status filters stay client-side.
  const scope = useScopeQuery({
    values: filterValues,
    setFilter: (key, value) => setFilterValues((prev) => ({ ...prev, [key]: value })),
  })

  const [editing, setEditing] = useState<ContinuousAssessment | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)

  const canManage = permissions.includes("grades.manage")
  const hasWorkspace = !isPlatform && active.schoolId !== null
  const isGlobal = hasWorkspace && active.branchId === null

  useEffect(() => {
    if (!hasWorkspace) return
    let cancelled = false
    apiFetch<Paginated<Term>>("/terms?per_page=100")
      .then((res) => {
        if (cancelled) return
        setTerms(res.data)
        const current = res.data.find((x) => x.status === "active")?.id ?? res.data[0]?.id ?? null
        setFilterValues((prev) =>
          prev.term_id === undefined && current !== null
            ? { ...prev, term_id: String(current) }
            : prev,
        )
      })
      .catch(() => setTerms([]))
    apiFetch<Paginated<Subject>>("/subjects?per_page=100")
      .then((res) => !cancelled && setSubjects(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [hasWorkspace, active.schoolId, active.branchId])

  useEffect(() => {
    if (!hasWorkspace) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading
    setBooks(null)
    apiFetch<Paginated<ContinuousAssessment>>(`/continuous-assessments?per_page=100${scope.params}`)
      .then((res) => !cancelled && setBooks(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setBooks([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [hasWorkspace, active.schoolId, active.branchId, reloadKey, scope.key])

  const reload = () => setReloadKey((k) => k + 1)

  async function handleDelete(book: ContinuousAssessment) {
    try {
      await apiFetch(`/continuous-assessments/${book.id}`, { method: "DELETE" })
      toast.success(t("continuousAssessment.deleted"))
      reload()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<ContinuousAssessment>[] = useMemo(
    () => [
      ...(isGlobal
        ? [
            {
              key: "branch_name",
              label: tc("columns.branch"),
              render: (row: ContinuousAssessment) => (
                <span className="text-muted-foreground text-xs">{row.branch_name ?? "—"}</span>
              ),
            } as DataTableColumn<ContinuousAssessment>,
          ]
        : []),
      {
        key: "name",
        label: t("continuousAssessment.name"),
        sortable: true,
        primary: true,
        render: (row) => <span className="font-medium">{row.name}</span>,
      },
      {
        key: "term_name",
        label: t("continuousAssessment.term"),
        render: (row) => row.term_name ?? "—",
      },
      {
        key: "applies_to",
        label: t("continuousAssessment.appliesTo"),
        mobileHidden: true,
        render: (row) => (
          <div className="flex flex-wrap gap-1">
            {row.targets.map((target, i) => (
              <Badge key={i} variant="outline" className="h-5 px-1.5 text-[11px] font-normal">
                {describeTarget(target, t)}
              </Badge>
            ))}
          </div>
        ),
        exportValue: (row) => row.targets.map((target) => describeTarget(target, t)).join(" | "),
      },
      {
        key: "items",
        label: t("continuousAssessment.items"),
        render: (row) => (
          <div className="flex flex-wrap gap-1">
            {row.items.map((item) => (
              <Badge key={item.id} variant="secondary" className="h-5 px-1.5 text-[11px]">
                {item.name} · {item.weight}%
              </Badge>
            ))}
          </div>
        ),
        exportValue: (row) => row.items.map((i) => `${i.name} ${i.weight}%`).join(", "),
      },
      {
        key: "is_active",
        label: tc("columns.status"),
        mobileHidden: true,
        render: (row) => (
          <Badge variant={row.is_active ? "default" : "secondary"}>
            {row.is_active ? tc("states.active") : tc("states.inactive")}
          </Badge>
        ),
        exportValue: (row) => (row.is_active ? "Active" : "Inactive"),
      },
    ],
    [t, tc, isGlobal],
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("continuousAssessment.title")}
        description={t("continuousAssessment.subtitle")}
        actions={
          canManage && hasWorkspace ? (
            <Button
              onClick={() => {
                setEditing(null)
                setSheetOpen(true)
              }}
            >
              <Plus className="size-4" />
              {t("continuousAssessment.add")}
            </Button>
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
          data={books ?? []}
          loading={books === null}
          searchKeys={["name"]}
          searchPlaceholder={tc("actions.search")}
          filters={[
            ...scope.filters,
            {
              key: "term_id",
              label: t("continuousAssessment.term"),
              options: groupTermsByYear(terms).flatMap((group) =>
                group.terms.map((term) => ({
                  label: termFullLabel(term),
                  value: String(term.id),
                })),
              ),
            },
          ]}
          filterValues={filterValues}
          onFilterChange={(key, value) => setFilterValues((prev) => ({ ...prev, [key]: value }))}
          actions={
            canManage
              ? [
                  {
                    label: tc("actions.edit"),
                    icon: Pencil,
                    primary: true,
                    onClick: (row: ContinuousAssessment) => {
                      setEditing(row)
                      setSheetOpen(true)
                    },
                  },
                  {
                    label: tc("actions.delete"),
                    icon: Trash2,
                    destructive: true,
                    onClick: (row: ContinuousAssessment) =>
                      confirmDelete(
                        () => handleDelete(row),
                        tc("confirmDelete.named", { name: row.name }),
                      ),
                  },
                ]
              : undefined
          }
          emptyMessage={`${t("continuousAssessment.empty")} ${t("continuousAssessment.emptyHint")}`}
          exportFilename="continuous-assessments"
        />
      )}

      <ContinuousAssessmentSheet
        book={editing}
        terms={terms}
        subjects={subjects}
        gradeLevels={gradeLevels}
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        onSaved={reload}
      />
    </div>
  )
}
