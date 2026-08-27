"use client"

import { Plus, Trash2 } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { CourseEditor } from "@/components/lms/course-editor"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { Course, Paginated } from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"

const STATUS_TINT: Record<Course["status"], string> = {
  draft: "bg-muted text-muted-foreground",
  published: "bg-success/10 text-success",
  archived: "bg-warning/10 text-warning",
}

export default function CoursesPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const platform = isPlatform && permissions.includes("exam_prep.manage")
  const hasContext = platform || active.schoolId !== null

  const [courses, setCourses] = useState<Course[] | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  // Branch narrowing for the school-wide workspace — applied server-side
  // (refetch); the status filter stays client-side. The platform course
  // library has no branches, so scope stays inert there.
  const scope = useScopeQuery()

  const load = useCallback(() => {
    if (!hasContext) return
    apiFetch<Paginated<Course>>(`/courses?per_page=100${platform ? "&platform=1" : scope.params}`)
      .then((res) => setCourses(res.data))
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setCourses([])
      })
  }, [hasContext, platform, scope.params, tc])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setCourses(null)
    load()
  }, [load, active.schoolId, active.branchId])

  async function handleDelete(course: Course) {
    try {
      const res = await apiFetch<{ message: string }>(`/courses/${course.id}`, { method: "DELETE" })
      toast.success(res.message)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<Course>[] = [
    {
      key: "title",
      label: t("courses.courseTitle"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.title}</p>
          <p className="truncate text-xs text-muted-foreground">
            {[
              row.subject_name,
              row.targets?.length
                ? row.targets.map((target) => target.section_name).filter(Boolean).join(", ")
                : row.section_name,
              row.stream ? t(`courses.stream${row.stream === "natural" ? "Natural" : "Social"}`) : null,
            ]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
      ),
    },
    {
      key: "lessons_count",
      label: t("courses.lessons"),
      sortable: true,
      render: (row) => (
        <span className="tabular-nums">
          {row.modules_count ?? 0} / {row.lessons_count ?? 0}
        </span>
      ),
    },
    {
      key: "status",
      label: tc("columns.status"),
      render: (row) => (
        <Badge variant="outline" className={`border-transparent ${STATUS_TINT[row.status]}`}>
          {t(`courses.statuses.${row.status}`)}
        </Badge>
      ),
    },
  ]

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={platform ? t("courses.platformTitle") : t("courses.title")}
        description={platform ? t("courses.platformSubtitle") : t("courses.subtitle")}
        actions={
          hasContext ? (
            <Button
              className="h-11"
              onClick={() => {
                setEditingId(null)
                setEditorOpen(true)
              }}
            >
              <Plus className="size-4" /> {t("courses.add")}
            </Button>
          ) : undefined
        }
      />

      <CourseEditor
        courseId={editingId}
        platform={platform}
        open={editorOpen}
        onOpenChange={setEditorOpen}
        onSaved={load}
      />

      {!hasContext ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("common.noContext")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={courses ?? []}
          loading={courses === null}
          searchKeys={["title", "subject_name"]}
          searchPlaceholder={tc("actions.search")}
          filters={[
            ...(platform ? [] : scope.filters),
            {
              key: "status",
              label: tc("columns.status"),
              options: (["draft", "published", "archived"] as const).map((status) => ({
                value: status,
                label: t(`courses.statuses.${status}`),
              })),
            },
          ]}
          filterValues={scope.values}
          onFilterChange={scope.setFilter}
          onRowClick={(row) => {
            setEditingId(row.id)
            setEditorOpen(true)
          }}
          actions={[
            {
              label: tc("actions.delete"),
              icon: Trash2,
              destructive: true,
              onClick: (row: Course) =>
                confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.title })),
            },
          ]}
          emptyMessage={t("courses.empty")}
          exportFilename="courses"
        />
      )}
    </div>
  )
}
