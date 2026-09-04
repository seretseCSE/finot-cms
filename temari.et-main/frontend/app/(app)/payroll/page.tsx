"use client"

import { Eye, Plus, Trash2 } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { PayrollRunDetail } from "@/components/employees/payroll-run-detail"
import { PayrollRunSheet } from "@/components/employees/payroll-run-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { PayrollRun, PayrollStatus } from "@/lib/types"
import { formatETB } from "@/lib/utils"
import { useScopeFilters } from "@/lib/use-scope-filters"
import { useServerTable } from "@/lib/use-server-table"

const STATUSES: PayrollStatus[] = ["draft", "approved", "paid"]

const STATUS_VARIANT: Record<PayrollStatus, "default" | "secondary" | "outline"> = {
  draft: "secondary",
  approved: "outline",
  paid: "default",
}

export default function PayrollPage() {
  const { t } = useTranslation("payroll")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const canManage = permissions.includes("payroll.manage")
  const canApprove = permissions.includes("payroll.approve")
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers create runs from the school-wide workspace too — the
  // dialog asks for the target branch (BranchField).
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  const [createOpen, setCreateOpen] = useState(false)
  const [detailId, setDetailId] = useState<number | null>(null)
  const [detailOpen, setDetailOpen] = useState(false)

  const table = useServerTable<PayrollRun>({
    endpoint: "/payroll-runs",
    defaultSort: { key: "period_start", dir: "desc" },
    enabled: hasBranch || isGlobal,
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("loadFailed"),
  })

  async function handleDelete(run: PayrollRun) {
    try {
      await apiFetch(`/payroll-runs/${run.id}`, { method: "DELETE" })
      toast.success(t("deleted"))
      await table.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Something went wrong.")
    }
  }

  const scopeFilters = useScopeFilters(table.filters)

  const columns: DataTableColumn<PayrollRun>[] = [
    ...(isGlobal
      ? [
          {
            key: "branch_name",
            label: tc("columns.branch"),
            render: (row: PayrollRun) => (
              <span className="text-xs text-muted-foreground">
                {[row.school_name, row.branch_name].filter(Boolean).join(" · ") || "—"}
              </span>
            ),
            exportValue: (row: PayrollRun) =>
              [row.school_name, row.branch_name].filter(Boolean).join(" · "),
          } as DataTableColumn<PayrollRun>,
        ]
      : []),
    {
      key: "name",
      label: t("columns.name"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="leading-tight">
          <span className="font-medium">{row.name}</span>
          <span className="block text-xs text-muted-foreground tabular-nums">
            {row.period_start} → {row.period_end}
          </span>
        </div>
      ),
      exportValue: (row) => row.name,
    },
    {
      key: "employee_count",
      label: t("columns.employees"),
      mobileHidden: true,
      render: (row) => <span className="tabular-nums">{row.employee_count ?? "—"}</span>,
      exportValue: (row) => String(row.employee_count ?? ""),
    },
    {
      key: "gross_total",
      label: t("columns.gross"),
      mobileHidden: true,
      render: (row) => <span className="tabular-nums">{formatETB(row.gross_total)}</span>,
      exportValue: (row) => row.gross_total,
    },
    {
      key: "net_total",
      label: t("columns.net"),
      sortable: true,
      render: (row) => (
        <span className="font-medium tabular-nums">{formatETB(row.net_total)}</span>
      ),
      exportValue: (row) => row.net_total,
    },
    {
      key: "status",
      label: tc("columns.status"),
      sortable: true,
      render: (row) => (
        <Badge variant={STATUS_VARIANT[row.status]}>{t(`statuses.${row.status}`)}</Badge>
      ),
      exportValue: (row) => row.status,
    },
  ]

  return (
    <div className="space-y-6">
      {confirmDialog}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {t("title")}
          </h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        {canManage && canTargetBranch && (
          <Button className="h-11" onClick={() => setCreateOpen(true)}>
            <Plus className="size-4" />
            {t("create")}
          </Button>
        )}
      </div>

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={table.rows}
          loading={table.loading}
          serverMode
          searchable
          searchValue={table.searchInput}
          onSearchChange={table.setSearchInput}
          searchPlaceholder={tc("actions.search")}
          filters={[
            ...scopeFilters,
            {
              key: "status",
              label: tc("filters.status"),
              options: STATUSES.map((status) => ({
                label: t(`statuses.${status}`),
                value: status,
              })),
            },
          ]}
          filterValues={table.filters}
          onFilterChange={table.setFilter}
          onSortChange={table.onSortChange}
          onRowClick={(row) => {
            setDetailId(row.id)
            setDetailOpen(true)
          }}
          actions={[
            {
              label: t("actions.view"),
              icon: Eye,
              onClick: (row: PayrollRun) => {
                setDetailId(row.id)
                setDetailOpen(true)
              },
            },
            ...(canManage
              ? [
                  {
                    label: tc("actions.delete"),
                    icon: Trash2,
                    destructive: true,
                    hidden: (row: PayrollRun) => row.status !== "draft",
                    onClick: (row: PayrollRun) =>
                      confirmDelete(
                        () => handleDelete(row),
                        tc("confirmDelete.named", { name: row.name }),
                      ),
                  },
                ]
              : []),
          ]}
          emptyMessage={t("empty")}
          exportFilename="payroll"
          pagination={table.pagination}
        />
      )}

      <PayrollRunSheet
        open={createOpen}
        onOpenChange={setCreateOpen}
        onSaved={(run) => {
          table.refetch()
          setDetailId(run.id)
          setDetailOpen(true)
        }}
      />

      <PayrollRunDetail
        runId={detailId}
        canManage={canManage}
        canApprove={canApprove}
        open={detailOpen}
        onOpenChange={(v) => {
          setDetailOpen(v)
          if (!v) setDetailId(null)
        }}
        onChanged={() => table.refetch()}
      />
    </div>
  )
}
