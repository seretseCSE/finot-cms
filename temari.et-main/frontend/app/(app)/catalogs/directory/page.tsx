"use client"

import { BadgeCheck, Pencil, Trash2 } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { CatalogPillNav, useCatalogs } from "@/components/catalogs/catalogs-shell"
import { DirectoryEntrySheet } from "@/components/catalogs/directory-entry-sheet"
import { Badge } from "@/components/ui/badge"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useServerTable } from "@/lib/use-server-table"
import type { SchoolDirectoryEntry } from "@/lib/types"

export default function CatalogDirectoryPage() {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { refreshOverview } = useCatalogs()

  const [regions, setRegions] = useState<string[]>([])
  const [editing, setEditing] = useState<SchoolDirectoryEntry | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)

  useEffect(() => {
    apiFetch<{ data: string[] }>("/catalogs/school-directory/regions")
      .then((res) => setRegions(res.data))
      .catch(() => setRegions([]))
  }, [])

  const table = useServerTable<SchoolDirectoryEntry>({
    endpoint: "/catalogs/school-directory",
    exportEndpoint: "/catalogs/school-directory/export",
    defaultSort: { key: "name", dir: "asc" },
    loadFailedMessage: t("loadFailed"),
  })

  function handleSaved() {
    table.refetch()
    refreshOverview()
  }

  async function handleVerify(row: SchoolDirectoryEntry) {
    try {
      await apiFetch(`/school-directory/${row.id}/verify`, { method: "PATCH" })
      toast.success(t("directory.verifiedToast"))
      handleSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function handleDelete(row: SchoolDirectoryEntry) {
    try {
      await apiFetch(`/school-directory/${row.id}`, { method: "DELETE" })
      toast.success(t("directory.deleted"))
      handleSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  function locationLabel(row: SchoolDirectoryEntry): string {
    return [row.city, row.zone, row.region].filter(Boolean).join(" · ") || "—"
  }

  const columns: DataTableColumn<SchoolDirectoryEntry>[] = useMemo(
    () => [
      {
        key: "name",
        label: t("fields.name"),
        sortable: true,
        primary: true,
        render: (row) => (
          <span className="min-w-0">
            <span className="block truncate font-medium">{row.name}</span>
            {row.created_by_school_name && (
              <span className="block truncate text-xs text-muted-foreground">
                {t("directory.addedBy", { school: row.created_by_school_name })}
              </span>
            )}
          </span>
        ),
      },
      {
        key: "region",
        label: t("directory.location"),
        sortable: true,
        render: (row) => (
          <span className="text-xs text-muted-foreground">{locationLabel(row)}</span>
        ),
        exportValue: (row) => locationLabel(row),
      },
      {
        key: "on_platform",
        label: t("directory.onPlatform"),
        mobileHidden: true,
        render: (row) =>
          row.school_id !== null ? (
            <Badge className="bg-primary/10 text-primary" variant="secondary">
              {t("directory.onTemari")}
            </Badge>
          ) : (
            <span className="text-xs text-muted-foreground">{t("directory.offPlatform")}</span>
          ),
        exportValue: (row) => (row.school_id !== null ? "On Temari" : "Off-platform"),
      },
      {
        key: "is_verified",
        label: t("directory.verified"),
        sortable: true,
        render: (row) =>
          row.is_verified ? (
            <Badge className="gap-1 bg-success/10 text-success" variant="secondary">
              <BadgeCheck className="size-3" />
              {t("directory.verifiedBadge")}
            </Badge>
          ) : (
            <Badge className="bg-warning/10 text-warning" variant="secondary">
              {t("directory.needsReview")}
            </Badge>
          ),
        exportValue: (row) => (row.is_verified ? "Verified" : "Needs review"),
      },
    ],
    [t],
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("items.directory.title")}
        description={t("items.directory.description")}
        backHref="/catalogs"
        backLabel={t("title")}
        actions={
          <DirectoryEntrySheet
            entry={editing}
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
        searchPlaceholder={t("directory.searchPlaceholder")}
        filters={[
          ...(regions.length > 0
            ? [
                {
                  key: "region",
                  label: t("directory.region"),
                  options: regions.map((r) => ({ label: r, value: r })),
                },
              ]
            : []),
          {
            key: "is_verified",
            label: t("directory.verified"),
            options: [
              { label: t("directory.verifiedBadge"), value: "true" },
              { label: t("directory.needsReview"), value: "false" },
            ],
          },
          {
            key: "on_platform",
            label: t("directory.onPlatform"),
            options: [
              { label: t("directory.onTemari"), value: "true" },
              { label: t("directory.offPlatform"), value: "false" },
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
            label: t("directory.verify"),
            icon: BadgeCheck,
            hidden: (row) => row.is_verified,
            onClick: (row) => handleVerify(row),
          },
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
            // Rows backed by a Temari school are auto-maintained — no delete.
            hidden: (row) => row.school_id !== null,
            onClick: (row) =>
              confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.name })),
          },
        ]}
        emptyMessage={t("directory.empty")}
        exportFilename="school-directory"
      />
    </div>
  )
}
