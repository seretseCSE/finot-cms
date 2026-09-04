"use client"

import { Pencil, Trash2 } from "lucide-react"

import { useMemo, useState } from "react"
import { toast } from "sonner"

import { BankSheet } from "@/components/catalogs/bank-sheet"
import { CatalogPillNav, useCatalogs } from "@/components/catalogs/catalogs-shell"
import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { Badge } from "@/components/ui/badge"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useServerTable } from "@/lib/use-server-table"
import type { Bank } from "@/lib/types"

export default function CatalogBanksPage() {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { refreshOverview } = useCatalogs()

  const [editing, setEditing] = useState<Bank | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)

  const table = useServerTable<Bank>({
    endpoint: "/catalogs/banks",
    exportEndpoint: "/catalogs/banks/export",
    defaultSort: { key: "name", dir: "asc" },
    loadFailedMessage: t("loadFailed"),
  })

  function handleSaved() {
    table.refetch()
    refreshOverview()
  }

  /** Reversible visibility flip — hides the bank from school pickers. */
  async function toggleActive(row: Bank, active: boolean) {
    try {
      await apiFetch(`/catalogs/banks/${row.id}`, {
        method: "PUT",
        body: { code: row.code, name: row.name, type: row.type, is_active: active },
      })
      toast.success(t("banks.updated"))
      handleSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function handleDelete(row: Bank) {
    try {
      await apiFetch(`/catalogs/banks/${row.id}`, { method: "DELETE" })
      toast.success(t("banks.deleted"))
      handleSaved()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<Bank>[] = useMemo(
    () => [
      {
        key: "name",
        label: t("fields.name"),
        sortable: true,
        primary: true,
        render: (row) => (
          <span className="flex items-center gap-2.5">
            <BankLogo bank={row} size={32} />
            <span className="min-w-0">
              <span className="block truncate font-medium">{row.name}</span>
              <span className="block font-mono text-xs text-muted-foreground">{row.code}</span>
            </span>
          </span>
        ),
      },
      {
        key: "type",
        label: t("banks.type"),
        sortable: true,
        render: (row) => (
          <Badge variant={row.type === "wallet" ? "default" : "secondary"}>
            {t(`banks.types.${row.type}`)}
          </Badge>
        ),
        exportValue: (row) => row.type,
      },
      {
        key: "accounts_count",
        label: t("columns.usage"),
        mobileHidden: true,
        render: (row) => (
          <span className="text-xs tabular-nums text-muted-foreground">
            {t("banks.accountsCount", { count: row.accounts_count ?? 0 })}
          </span>
        ),
        exportValue: (row) => String(row.accounts_count ?? 0),
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
        title={t("items.banks.title")}
        description={t("items.banks.description")}
        backHref="/catalogs"
        backLabel={t("title")}
        actions={
          <BankSheet
            bank={editing}
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
        searchPlaceholder={t("banks.searchPlaceholder")}
        filters={[
          {
            key: "type",
            label: t("banks.type"),
            options: [
              { label: t("banks.types.bank"), value: "bank" },
              { label: t("banks.types.wallet"), value: "wallet" },
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
        emptyMessage={t("banks.empty")}
        exportFilename="banks-catalog"
      />
    </div>
  )
}
