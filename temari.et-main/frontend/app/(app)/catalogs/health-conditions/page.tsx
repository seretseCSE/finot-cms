"use client"

import { Pencil, Trash2 } from "lucide-react"

import { useMemo, useState } from "react"
import { toast } from "sonner"

import { CatalogPillNav, useCatalogs } from "@/components/catalogs/catalogs-shell"
import {
  HEALTH_CATEGORIES,
  HealthConditionSheet,
} from "@/components/catalogs/health-condition-sheet"
import { Badge } from "@/components/ui/badge"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useServerTable } from "@/lib/use-server-table"
import type { HealthCondition } from "@/lib/types"

export default function CatalogHealthConditionsPage() {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { refreshOverview } = useCatalogs()

  const [editing, setEditing] = useState<HealthCondition | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)

  const table = useServerTable<HealthCondition>({
    endpoint: "/catalogs/health-conditions",
    exportEndpoint: "/catalogs/health-conditions/export",
    defaultSort: { key: "name", dir: "asc" },
    loadFailedMessage: t("loadFailed"),
  })

  function handleSaved() {
    table.refetch()
    refreshOverview()
  }

  /** Reversible visibility flip — hides the condition from registration pickers. */
  async function toggleActive(row: HealthCondition, active: boolean) {
    try {
      await apiFetch(`/catalogs/health-conditions/${row.id}`, {
        method: "PUT",
        body: { name: row.name, category: row.category, is_active: active },
      })
      toast.success(t("health.updated"))
      handleSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function handleDelete(row: HealthCondition) {
    try {
      await apiFetch(`/catalogs/health-conditions/${row.id}`, { method: "DELETE" })
      toast.success(t("health.deleted"))
      handleSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<HealthCondition>[] = useMemo(
    () => [
      {
        key: "name",
        label: t("fields.name"),
        sortable: true,
        primary: true,
        render: (row) => <span className="font-medium">{row.name}</span>,
      },
      {
        key: "category",
        label: t("health.category"),
        sortable: true,
        render: (row) => <Badge variant="secondary">{t(`health.categories.${row.category}`)}</Badge>,
        exportValue: (row) => row.category,
      },
      {
        key: "students_count",
        label: t("columns.usage"),
        mobileHidden: true,
        render: (row) => (
          <span className="text-xs tabular-nums text-muted-foreground">
            {t("health.studentsCount", { count: row.students_count ?? 0 })}
          </span>
        ),
        exportValue: (row) => String(row.students_count ?? 0),
      },
      {
        key: "is_active",
        label: tc("columns.status"),
        sortable: true,
        render: (row) => (
          <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
            <Switch
              checked={row.is_active ?? true}
              onCheckedChange={(v) => toggleActive(row, v)}
              aria-label={tc("columns.status")}
            />
            <span className="text-xs text-muted-foreground">
              {(row.is_active ?? true) ? tc("states.active") : tc("states.inactive")}
            </span>
          </div>
        ),
        exportValue: (row) => ((row.is_active ?? true) ? "Active" : "Inactive"),
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps -- toggleActive is stable per render batch
    [t, tc],
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("items.healthConditions.title")}
        description={t("items.healthConditions.description")}
        backHref="/catalogs"
        backLabel={t("title")}
        actions={
          <HealthConditionSheet
            condition={editing}
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
        searchPlaceholder={t("health.searchPlaceholder")}
        filters={[
          {
            key: "category",
            label: t("health.category"),
            options: HEALTH_CATEGORIES.map((c) => ({
              label: t(`health.categories.${c}`),
              value: c,
            })),
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
            onClick: (row) => {
              setEditing(row)
              setSheetOpen(true)
            },
          },
          {
            label: tc("actions.delete"),
            icon: Trash2,
            destructive: true,
            onClick: (row) =>
              confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.name })),
          },
        ]}
        emptyMessage={t("health.empty")}
        exportFilename="health-conditions"
      />
    </div>
  )
}
