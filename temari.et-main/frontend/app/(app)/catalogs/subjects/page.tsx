"use client"

import { Pencil, Trash2 } from "lucide-react"

import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { CatalogPillNav, useCatalogs } from "@/components/catalogs/catalogs-shell"
import {
  SUBJECT_CATEGORIES,
  SUBJECT_ROOM_TYPES,
  SubjectSheet,
} from "@/components/catalogs/subject-sheet"
import { Badge } from "@/components/ui/badge"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { gradeSetLabel } from "@/lib/subjects"
import { useServerTable } from "@/lib/use-server-table"
import type { GradeLevel, Paginated, Subject } from "@/lib/types"

export default function CatalogSubjectsPage() {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { refreshOverview } = useCatalogs()

  const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([])
  const [editing, setEditing] = useState<Subject | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)

  useEffect(() => {
    // Platform studio needs the full national ladder regardless of workspace.
    apiFetch<Paginated<GradeLevel>>("/grade-levels?all=1")
      .then((res) => setGradeLevels(res.data))
      .catch(() => setGradeLevels([]))
  }, [])

  const table = useServerTable<Subject>({
    endpoint: "/catalogs/subjects",
    exportEndpoint: "/catalogs/subjects/export",
    defaultSort: { key: "code", dir: "asc" },
    loadFailedMessage: t("loadFailed"),
  })

  function windowLabel(row: Subject): string {
    return gradeSetLabel(row.grade_sorts ?? [], gradeLevels) ?? t("subjects.allGrades")
  }

  function handleSaved(subject: Subject) {
    table.refetch()
    refreshOverview()
    void subject
  }

  /** Reversible visibility flip — no confirmation by design. */
  async function toggleActive(row: Subject, active: boolean) {
    try {
      await apiFetch(`/catalogs/subjects/${row.id}`, {
        method: "PUT",
        body: {
          code: row.code,
          name: row.name,
          category: row.category,
          weight: row.weight ?? 3,
          room_type: row.room_type ?? null,
          is_active: active,
        },
      })
      toast.success(t("subjects.updated"))
      table.refetch()
      refreshOverview()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function handleDelete(row: Subject) {
    try {
      await apiFetch(`/catalogs/subjects/${row.id}`, { method: "DELETE" })
      toast.success(t("subjects.deleted"))
      table.refetch()
      refreshOverview()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<Subject>[] = useMemo(
    () => [
      {
        key: "code",
        label: t("fields.code"),
        sortable: true,
        render: (row) => <span className="font-mono text-xs">{row.code}</span>,
      },
      {
        key: "name",
        label: t("fields.name"),
        sortable: true,
        primary: true,
        render: (row) => <span className="font-medium">{row.name}</span>,
      },
      {
        key: "category",
        label: t("subjects.category"),
        sortable: true,
        render: (row) =>
          row.category ? (
            <Badge variant="secondary">{t(`subjects.categories.${row.category}`)}</Badge>
          ) : (
            "—"
          ),
        exportValue: (row) => row.category ?? "",
      },
      {
        key: "grades",
        label: t("subjects.gradeWindow"),
        mobileHidden: true,
        render: (row) => <span className="text-xs text-muted-foreground">{windowLabel(row)}</span>,
        exportValue: (row) => windowLabel(row),
      },
      {
        key: "weight",
        label: t("subjects.weight"),
        sortable: true,
        mobileHidden: true,
        render: (row) => (
          <span className="inline-flex items-center gap-1" title={t(`subjects.weights.${row.weight ?? 3}`)}>
            {[1, 2, 3, 4, 5].map((step) => (
              <span
                key={step}
                className={
                  step <= (row.weight ?? 3)
                    ? "size-1.5 rounded-full bg-primary"
                    : "size-1.5 rounded-full bg-muted"
                }
              />
            ))}
          </span>
        ),
        exportValue: (row) => String(row.weight ?? 3),
      },
      {
        key: "room_type",
        label: t("subjects.roomType"),
        mobileHidden: true,
        render: (row) =>
          row.room_type ? (
            <Badge variant="outline">{t(`subjects.roomTypes.${row.room_type}`)}</Badge>
          ) : (
            <span className="text-xs text-muted-foreground">{t("subjects.ownClassroom")}</span>
          ),
        exportValue: (row) => row.room_type ?? "",
      },
      {
        key: "origin",
        label: t("subjects.origin"),
        render: (row) =>
          row.school_id === null ? (
            <Badge className="bg-primary/10 text-primary" variant="secondary">
              {t("subjects.platform")}
            </Badge>
          ) : (
            <span className="max-w-36 truncate text-xs text-muted-foreground" title={row.school_name ?? ""}>
              {row.school_name ?? t("subjects.custom")}
            </span>
          ),
        exportValue: (row) => (row.school_id === null ? "Platform" : (row.school_name ?? "Custom")),
      },
      {
        key: "assignments_count",
        label: t("columns.usage"),
        mobileHidden: true,
        render: (row) => (
          <span className="text-xs tabular-nums text-muted-foreground">
            {t("subjects.usageCount", { count: row.assignments_count ?? 0 })}
          </span>
        ),
        exportValue: (row) => String(row.assignments_count ?? 0),
      },
      {
        key: "is_active",
        label: tc("columns.status"),
        sortable: true,
        render: (row) =>
          row.school_id === null ? (
            <div onClick={(e) => e.stopPropagation()}>
              <Switch
                checked={row.is_active}
                onCheckedChange={(v) => toggleActive(row, v)}
                aria-label={tc("columns.status")}
              />
            </div>
          ) : (
            <Badge variant={row.is_active ? "default" : "secondary"}>
              {row.is_active ? tc("states.active") : tc("states.inactive")}
            </Badge>
          ),
        exportValue: (row) => (row.is_active ? "Active" : "Inactive"),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps -- windowLabel/toggleActive derive from stable state
    [t, tc, gradeLevels],
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("items.subjects.title")}
        description={t("items.subjects.description")}
        backHref="/catalogs"
        backLabel={t("title")}
        actions={
          <SubjectSheet
            gradeLevels={gradeLevels}
            subject={editing}
            open={sheetOpen}
            onOpenChange={(v) => {
              setSheetOpen(v)
              if (!v) setEditing(null)
            }}
            onSaved={handleSaved}
            showTrigger
          />
        }
      />
      <div className="lg:hidden">
        <CatalogPillNav />
      </div>

      <DataTable
        serverMode
        searchable
        columns={columns}
        data={table.rows}
        loading={table.loading}
        total={table.total}
        searchValue={table.searchInput}
        onSearchChange={table.setSearchInput}
        searchPlaceholder={t("subjects.searchPlaceholder")}
        filters={[
          {
            key: "category",
            label: t("subjects.category"),
            options: SUBJECT_CATEGORIES.map((c) => ({
              label: t(`subjects.categories.${c}`),
              value: c,
            })),
          },
          {
            key: "room_type",
            label: t("subjects.roomType"),
            options: SUBJECT_ROOM_TYPES.map((r) => ({
              label: t(`subjects.roomTypes.${r}`),
              value: r,
            })),
          },
          {
            key: "scope",
            label: t("subjects.origin"),
            options: [
              { label: t("subjects.platform"), value: "platform" },
              { label: t("subjects.custom"), value: "custom" },
            ],
          },
          {
            key: "is_active",
            label: tc("columns.status"),
            options: [
              { label: tc("states.active"), value: "true" },
              { label: tc("states.inactive"), value: "false" },
            ],
          },
        ]}
        filterValues={table.filters}
        onFilterChange={table.setFilter}
        onSortChange={table.onSortChange}
        onExport={table.handleExport}
        pagination={table.pagination}
        actions={[
          {
            label: tc("actions.edit"),
            icon: Pencil,
            primary: true,
            hidden: (row) => row.school_id !== null,
            onClick: (row) => {
              setEditing(row)
              setSheetOpen(true)
            },
          },
          {
            label: tc("actions.delete"),
            icon: Trash2,
            destructive: true,
            hidden: (row) => row.school_id !== null,
            onClick: (row) =>
              confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.name })),
          },
        ]}
        emptyMessage={t("subjects.empty")}
        exportFilename="platform-subjects"
      />
    </div>
  )
}
