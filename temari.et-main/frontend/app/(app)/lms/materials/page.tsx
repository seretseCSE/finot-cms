"use client"

import { CirclePlay, FileUp, Link2, Pencil, Pin, Plus, StickyNote, Trash2 } from "lucide-react"
import { useSearchParams } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { MaterialEditor } from "@/components/lms/material-editor"
import { LmsScopeFilterBar, useLmsScope } from "@/components/lms/scope-filter"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { CourseMaterial, Paginated } from "@/lib/types"

const TYPE_ICONS = { file: FileUp, link: Link2, youtube: CirclePlay, text: StickyNote } as const

type MaterialRow = CourseMaterial & {
  type_label: string
  sections_label: string
  posted_by: string
  // Not a property of the material itself (targets can span sections in
  // several grades) — stamped from the scope filter that produced this
  // result set, so the table's own client-side filter never re-excludes rows
  // the server already scoped by grade.
  grade_level_id: number | null
}

export default function MaterialsPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  // ⌘K palette deep-link: /lms/materials?q=… pre-fills the table search.
  const deepLinkedSearch = useSearchParams().get("q") ?? ""

  const scope = useLmsScope({ allowLibrary: isPlatform && permissions.includes("exam_prep.manage") })
  // "platform" = the exam-prep library lane (school_id null rows, posted by
  // Temari.et content staff). Otherwise — for a platform admin who picked a
  // school, or for ordinary school staff — this is a real school's materials.
  const platform = isPlatform && scope.library
  const browsingSchool = isPlatform && !scope.library && scope.schoolId !== null
  const canAdd = platform || (!isPlatform && active.schoolId !== null)
  const hasContext = platform || browsingSchool || (!isPlatform && active.schoolId !== null)

  const [materials, setMaterials] = useState<MaterialRow[] | null>(null)
  const [editing, setEditing] = useState<CourseMaterial | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})

  const typeLabel = useCallback(
    (type: CourseMaterial["type"]) =>
      t(`materials.type${type === "file" ? "File" : type === "link" ? "Link" : type === "youtube" ? "Youtube" : "Text"}`),
    [t],
  )

  const load = useCallback(() => {
    if (!hasContext) return
    apiFetch<Paginated<CourseMaterial>>(`/course-materials?per_page=100${scope.params}`)
      .then((res) =>
        // Client-mode filters/search read FLAT row keys — flatten everything.
        setMaterials(
          res.data.map((material) => ({
            ...material,
            type_label: typeLabel(material.type),
            sections_label:
              [...new Set((material.targets ?? []).map((target) => target.section_name).filter(Boolean))].join(
                ", ",
              ) || "—",
            posted_by: material.created_by_name ?? "—",
            grade_level_id: scope.gradeLevelId,
          })),
        ),
      )
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setMaterials([])
      })
  }, [hasContext, scope.params, scope.gradeLevelId, tc, typeLabel])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setMaterials(null)
    load()
  }, [load, active.schoolId, active.branchId])

  async function handleDelete(material: CourseMaterial) {
    try {
      await apiFetch(`/course-materials/${material.id}`, { method: "DELETE" })
      toast.success(t("materials.deleted"))
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<MaterialRow>[] = [
    {
      key: "title",
      label: t("materials.materialTitle"),
      sortable: true,
      primary: true,
      render: (row) => {
        const Icon = TYPE_ICONS[row.type]
        return (
          <div className="flex min-w-0 items-center gap-2.5">
            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent">
              <Icon className="size-4 text-muted-foreground" strokeWidth={1.75} />
            </div>
            <div className="min-w-0">
              <p className="flex items-center gap-1.5 truncate font-medium">
                {row.title}
                {row.is_pinned && <Pin className="size-3.5 shrink-0 text-primary" />}
              </p>
              <p className="text-xs text-muted-foreground">{row.type_label}</p>
            </div>
          </div>
        )
      },
      exportValue: (row) => row.title,
    },
    ...(platform
      ? ([
          {
            key: "subject_name",
            label: t("materials.subject"),
            sortable: true,
            render: (row) => row.subject_name ?? t("banks.anySubject"),
          },
        ] satisfies DataTableColumn<MaterialRow>[])
      : ([
          {
            key: "sections_label",
            label: t("materials.sections"),
            render: (row) =>
              row.sections_label === "—" ? (
                <span className="text-muted-foreground">—</span>
              ) : (
                <div className="flex max-w-52 flex-wrap gap-1">
                  {row.sections_label.split(", ").map((name) => (
                    <Badge key={name} variant="outline" className="font-normal text-muted-foreground">
                      {name}
                    </Badge>
                  ))}
                </div>
              ),
          },
        ] satisfies DataTableColumn<MaterialRow>[])),
    {
      key: "branch_name",
      label: tc("columns.branch"),
      mobileHidden: true,
      render: (row) => row.branch_name ?? "—",
    },
    {
      key: "is_active",
      label: tc("columns.status"),
      sortable: true,
      render: (row) => (
        <Badge variant={row.is_active ? "default" : "secondary"}>
          {row.is_active ? tc("states.active") : tc("states.inactive")}
        </Badge>
      ),
      exportValue: (row) => (row.is_active ? "Active" : "Inactive"),
    },
    {
      key: "posted_by",
      label: t("materials.postedBy"),
      mobileHidden: true,
    },
  ]

  const distinct = (values: (string | null | undefined)[]) =>
    [...new Set(values.filter((v): v is string => Boolean(v) && v !== "—"))].sort()

  const filters: DataTableFilter[] = []
  if (scope.gradeOptions.length > 0) {
    filters.push({
      key: "grade_level_id",
      label: t("scope.grade"),
      options: scope.gradeOptions.map((grade) => ({ label: grade.name, value: String(grade.id) })),
    })
  }
  if (materials) {
    filters.push({
      key: "type_label",
      label: t("materials.type"),
      options: distinct(materials.map((m) => m.type_label)).map((v) => ({ value: v, label: v })),
    })
    if (platform) {
      filters.push({
        key: "subject_name",
        label: t("materials.subject"),
        options: distinct(materials.map((m) => m.subject_name)).map((v) => ({ value: v, label: v })),
      })
    } else if (scope.gradeOptions.length > 0) {
      // Cascades from grade: hidden until one is picked, options narrowed to it.
      filters.push({
        key: "sections_label",
        label: t("materials.sections"),
        dependsOn: "grade_level_id",
        options: (gradeValue: string) => {
          const grades = gradeValue.split(",").filter(Boolean)
          const inGrade = materials.filter((m) => grades.includes(String(m.grade_level_id ?? "")))
          return distinct(inGrade.flatMap((m) => m.sections_label.split(", "))).map((v) => ({
            value: v,
            label: v,
          }))
        },
      })
    } else {
      filters.push({
        key: "sections_label",
        label: t("materials.sections"),
        options: distinct(materials.flatMap((m) => m.sections_label.split(", "))).map((v) => ({
          value: v,
          label: v,
        })),
      })
    }
    filters.push({
      key: "is_active",
      label: tc("columns.status"),
      options: [
        { label: tc("states.active"), value: "true" },
        { label: tc("states.inactive"), value: "false" },
      ],
    })
  }

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={platform ? t("materials.platformTitle") : t("materials.title")}
        description={platform ? t("materials.platformSubtitle") : t("materials.subtitle")}
        actions={
          canAdd ? (
            <Button
              className="h-11"
              onClick={() => {
                setEditing(null)
                setSheetOpen(true)
              }}
            >
              <Plus className="size-4" /> {t("materials.add")}
            </Button>
          ) : undefined
        }
      />

      <LmsScopeFilterBar {...scope} hideGrade />

      <MaterialEditor
        material={editing}
        platform={platform}
        open={sheetOpen}
        onOpenChange={(open) => {
          setSheetOpen(open)
          if (!open) setEditing(null)
        }}
        onSaved={load}
      />

      {!hasContext ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("common.noContext")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={materials ?? []}
          loading={materials === null}
          searchKeys={["title", "subject_name", "sections_label"]}
          initialSearch={deepLinkedSearch}
          searchPlaceholder={t("materials.search")}
          filters={filters}
          filterValues={{
            ...filterValues,
            grade_level_id: scope.gradeLevelId !== null ? String(scope.gradeLevelId) : "",
          }}
          onFilterChange={(key, value) => {
            if (key === "grade_level_id") {
              scope.setGradeLevelId(value ? Number(value.split(",")[0]) : null)
              return
            }
            setFilterValues((prev) => ({ ...prev, [key]: value }))
          }}
          actions={[
            {
              label: tc("actions.edit"),
              icon: Pencil,
              primary: true,
              hidden: (row) => !row.can_edit,
              onClick: (row) => {
                setEditing(row)
                setSheetOpen(true)
              },
            },
            {
              label: tc("actions.delete"),
              icon: Trash2,
              destructive: true,
              hidden: (row) => !row.can_delete,
              onClick: (row) =>
                confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.title })),
            },
          ]}
          emptyMessage={t("materials.empty")}
          exportFilename="materials"
        />
      )}
    </div>
  )
}
