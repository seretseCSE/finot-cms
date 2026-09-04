"use client"

import { Plus } from "lucide-react"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { AssignmentEditor } from "@/components/lms/assignment-editor"
import { AssignmentStatusBadge, formatDateTime } from "@/components/lms/shared"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { LmsAssignment, Paginated } from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"

export default function AssignmentsPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const router = useRouter()
  const { active } = useSchoolContext()

  const hasContext = active.schoolId !== null

  const [assignments, setAssignments] = useState<LmsAssignment[] | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  // Branch narrowing for the school-wide workspace — applied server-side
  // (refetch); status/grade filters stay client-side.
  const scope = useScopeQuery()

  const load = useCallback(() => {
    if (!hasContext) return
    apiFetch<Paginated<LmsAssignment>>(`/assignments?per_page=100${scope.params}`)
      .then((res) => setAssignments(res.data))
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setAssignments([])
      })
  }, [hasContext, scope.params, tc])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setAssignments(null)
    load()
  }, [load, active.schoolId, active.branchId])

  const columns: DataTableColumn<LmsAssignment>[] = [
    {
      key: "title",
      label: t("assignments.assignmentTitle"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.title}</p>
          <p className="text-xs text-muted-foreground">
            {[row.subject_name, row.grade_level_name, row.section_name].filter(Boolean).join(" · ")}
          </p>
        </div>
      ),
    },
    {
      key: "grade_level_name",
      label: t("banks.grade"),
      sortable: true,
      render: (row) =>
        [row.grade_level_name, row.section_name].filter(Boolean).join(" · ") || "—",
      mobileHidden: true,
    },
    {
      key: "due_at",
      label: t("assignments.dueAt"),
      sortable: true,
      render: (row) => formatDateTime(row.due_at),
    },
    {
      key: "submissions_count",
      label: t("assignments.submissions"),
      sortable: true,
      render: (row) => <span className="tabular-nums">{row.submissions_count ?? 0}</span>,
    },
    {
      key: "max_score",
      label: t("assignments.maxScore"),
      render: (row) => (row.max_score !== null ? Number(row.max_score) : "—"),
      mobileHidden: true,
    },
    {
      key: "status",
      label: tc("columns.status"),
      render: (row) => <AssignmentStatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("assignments.title")}
        description={t("assignments.subtitle")}
        actions={
          hasContext ? (
            <Button className="h-11" onClick={() => setSheetOpen(true)}>
              <Plus className="size-4" /> {t("assignments.add")}
            </Button>
          ) : undefined
        }
      />

      <AssignmentEditor
        assignment={null}
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        onSaved={load}
      />

      {!hasContext ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("common.noContext")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={assignments ?? []}
          loading={assignments === null}
          searchKeys={["title", "subject_name", "section_name", "grade_level_name"]}
          searchPlaceholder={t("assignments.search")}
          filters={[
            ...scope.filters,
            {
              key: "status",
              label: tc("columns.status"),
              options: ["draft", "published", "closed"].map((status) => ({
                value: status,
                label: t(`assignments.statuses.${status}`),
              })),
            },
            {
              key: "grade_level_name",
              label: t("banks.grade"),
              options: [
                ...new Set(
                  (assignments ?? [])
                    .map((row) => row.grade_level_name)
                    .filter((grade): grade is string => Boolean(grade)),
                ),
              ].map((grade) => ({ value: grade, label: grade })),
            },
          ]}
          filterValues={scope.values}
          onFilterChange={scope.setFilter}
          onRowClick={(row) => router.push(`/lms/assignments/${row.id}`)}
          emptyMessage={t("assignments.empty")}
          exportFilename="assignments"
        />
      )}
    </div>
  )
}
