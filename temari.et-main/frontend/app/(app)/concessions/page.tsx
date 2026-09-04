"use client"

import { Ban, CheckCircle2, Clock, HandCoins, Plus, Trash2, XCircle } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { ConcessionSheet } from "@/components/fees/concession-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { runBulk } from "@/components/ui/bulk-actions"
import { BulkDecisionDialog } from "@/components/ui/bulk-decision-dialog"
import { Checkbox } from "@/components/ui/checkbox"
import { useTranslation } from "@/lib/i18n"
import { formatETB } from "@/lib/utils"
import type {
  ConcessionCategory,
  ConcessionStats,
  ConcessionStatus,
  FeeConcession,
} from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { useScopeFilters } from "@/lib/use-scope-filters"

const STATUS_TINT: Record<ConcessionStatus, string> = {
  pending: "border-warning/30 bg-warning/10 text-warning",
  active: "border-success/30 bg-success/10 text-success",
  rejected: "border-border bg-muted text-muted-foreground",
  revoked: "border-border bg-muted text-muted-foreground",
}

const CATEGORIES: ConcessionCategory[] = [
  "sibling",
  "staff_child",
  "merit",
  "hardship",
  "scholarship",
  "other",
]

export default function ConcessionsPage() {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [stats, setStats] = useState<ConcessionStats | null>(null)
  const [statsVersion, setStatsVersion] = useState(0)
  const [createOpen, setCreateOpen] = useState(false)
  const [bulkDecision, setBulkDecision] = useState<{
    rows: FeeConcession[]
    decision: "approved" | "rejected"
  } | null>(null)
  const [reprice, setReprice] = useState(true)

  const canManage = permissions.includes("fees.manage")
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)

  const table = useServerTable<FeeConcession>({
    endpoint: "/fee-concessions",
    exportEndpoint: "/fee-concessions/export",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: hasBranch || isGlobal,
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("concessions.loadFailed"),
  })

  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    let cancelled = false
    apiFetch<{ data: ConcessionStats }>("/fee-concessions/stats")
      .then((res) => !cancelled && setStats(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [hasBranch, isGlobal, active.branchId, active.schoolId, statsVersion])

  function refresh() {
    table.refetch()
    setStatsVersion((v) => v + 1)
  }

  async function transition(row: FeeConcession, action: "approve" | "reject" | "revoke") {
    try {
      // Approval also reaches the student's OPEN bills (a registration
      // invoice issued moments before the suggestion) — paid history stays.
      const res = await apiFetch<{ meta?: { repriced_invoices?: number } }>(
        `/fee-concessions/${row.id}/${action}`,
        {
          method: "POST",
          body: action === "approve" ? { apply_to_open_invoices: true } : undefined,
        },
      )
      const repriced = res.meta?.repriced_invoices ?? 0
      toast.success(
        repriced > 0
          ? t("concessions.toasts.approveRepriced", { count: repriced })
          : t(`concessions.toasts.${action}`),
      )
      refresh()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function handleDelete(row: FeeConcession) {
    try {
      await apiFetch(`/fee-concessions/${row.id}`, { method: "DELETE" })
      toast.success(t("concessions.deleted"))
      refresh()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  function valueOf(row: FeeConcession): string {
    if (row.discount_type === "full_scholarship") return t("statuses.scholarship")
    if (row.discount_type === "percentage") return `${Number(row.discount_value)}%`
    return formatETB(row.discount_value)
  }

  const columns: DataTableColumn<FeeConcession>[] = [
    ...(isGlobal
      ? [
          {
            key: "branch_name",
            label: tc("columns.branch"),
            render: (row: FeeConcession) => (
              <span className="text-xs text-muted-foreground">
                {row.branch_name ?? t("concessions.allBranches")}
              </span>
            ),
            exportValue: (row: FeeConcession) => row.branch_name ?? "",
          } as DataTableColumn<FeeConcession>,
        ]
      : []),
    {
      key: "subject",
      label: t("concessions.subject"),
      primary: true,
      render: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">
            {row.student_name ?? row.parent_name ?? "—"}
          </span>
          <span className="text-xs text-muted-foreground">
            {row.student_id ? (
              row.student_public_id ? (
                <CopyableId value={row.student_public_id} />
              ) : (
                t("concessions.subjects.student")
              )
            ) : (
              t("concessions.guardianAllChildren")
            )}
          </span>
        </span>
      ),
      exportValue: (row) => row.student_name ?? row.parent_name ?? "",
    },
    {
      key: "category",
      label: t("concessions.category"),
      render: (row) => t(`concessions.categories.${row.category}`),
      exportValue: (row) => row.category_label,
    },
    {
      key: "value",
      label: t("concessions.value"),
      render: (row) => <span className="font-medium tabular-nums">{valueOf(row)}</span>,
      exportValue: (row) => valueOf(row),
    },
    {
      key: "scope",
      label: t("concessions.scope"),
      mobileHidden: true,
      render: (row) => (
        <span className="flex flex-col text-xs">
          <span>
            {row.fee_types === null
              ? t("concessions.allFees")
              : row.fee_types.map((type) => t(`feeTypes.${type}`)).join(", ")}
          </span>
          <span className="text-muted-foreground">
            {row.academic_year_name
              ? [row.academic_year_name, row.term_name].filter(Boolean).join(" · ")
              : t("concessions.lifetime")}
          </span>
        </span>
      ),
      exportValue: (row) =>
        [
          row.fee_types === null ? t("concessions.allFees") : row.fee_types.join(","),
          row.academic_year_name ?? t("concessions.lifetime"),
        ].join(" · "),
    },
    {
      key: "invoices_count",
      label: t("concessions.applied"),
      mobileHidden: true,
      render: (row) => row.invoices_count ?? 0,
      exportValue: (row) => String(row.invoices_count ?? 0),
    },
    {
      key: "status",
      label: tc("filters.status"),
      sortable: true,
      render: (row) => (
        <span className="flex flex-col gap-0.5">
          <Badge variant="outline" className={STATUS_TINT[row.status]}>
            {t(`concessions.statuses.${row.status}`)}
          </Badge>
          {row.source !== "manual" && (
            <span className="text-[11px] text-muted-foreground">
              {t("concessions.autoSuggested")}
            </span>
          )}
        </span>
      ),
      exportValue: (row) => row.status_label,
    },
  ]

  const scopeFilters = useScopeFilters(table.filters)

  const filterDefs: DataTableFilter[] = [
    {
      key: "status",
      label: tc("filters.status"),
      options: (["pending", "active", "rejected", "revoked"] as ConcessionStatus[]).map(
        (s) => ({ label: t(`concessions.statuses.${s}`), value: s })
      ),
    },
    {
      key: "category",
      label: t("concessions.category"),
      options: CATEGORIES.map((c) => ({
        label: t(`concessions.categories.${c}`),
        value: c,
      })),
    },
  ]

  // Suggestions arrive in batches after each intake, so the review lane sweeps.
  function openBulk(rows: FeeConcession[], decision: "approved" | "rejected") {
    const pending = rows.filter((r) => r.status === "pending")
    if (pending.length === 0) {
      toast.info(t("concessions.bulk.nonePending"))
      return
    }
    setReprice(decision === "approved")
    setBulkDecision({ rows: pending, decision })
  }

  const bulkActions = canManage
    ? [
        {
          label: t("concessions.approve"),
          icon: CheckCircle2,
          onClick: (rows: FeeConcession[]) => openBulk(rows, "approved"),
        },
        {
          label: t("concessions.reject"),
          icon: XCircle,
          destructive: true,
          onClick: (rows: FeeConcession[]) => openBulk(rows, "rejected"),
        },
      ]
    : undefined

  const rowActions = canManage
    ? [
        {
          label: t("concessions.approve"),
          icon: CheckCircle2,
          onClick: (row: FeeConcession) => transition(row, "approve"),
          hidden: (row: FeeConcession) => row.status !== "pending",
        },
        {
          label: t("concessions.reject"),
          icon: XCircle,
          onClick: (row: FeeConcession) => transition(row, "reject"),
          hidden: (row: FeeConcession) => row.status !== "pending",
        },
        {
          label: t("concessions.revoke"),
          icon: Ban,
          destructive: true,
          onClick: (row: FeeConcession) => transition(row, "revoke"),
          hidden: (row: FeeConcession) => row.status !== "active",
        },
        {
          label: tc("actions.delete"),
          icon: Trash2,
          destructive: true,
          onClick: (row: FeeConcession) =>
            confirmDelete(
              () => handleDelete(row),
              tc("confirmDelete.named", {
                name: row.student_name ?? row.parent_name ?? "",
              })
            ),
          hidden: (row: FeeConcession) => (row.invoices_count ?? 0) > 0,
        },
      ]
    : []

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("concessions.title")}
        description={t("concessions.subtitle")}
        actions={
          canManage ? (
            <Button className="h-11" onClick={() => setCreateOpen(true)}>
              <Plus className="size-4" />
              {t("concessions.create")}
            </Button>
          ) : undefined
        }
      />

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 px-4 md:px-8 xl:grid-cols-3">
            <StatCard
              label={t("concessions.stats.pending")}
              value={stats ? stats.pending_count : null}
              hint={t("concessions.stats.pendingHint")}
              icon={Clock}
              onClick={() =>
                table.setFilter("status", table.filters.status === "pending" ? "" : "pending")
              }
            />
            <StatCard
              label={t("concessions.stats.active")}
              value={stats ? stats.active_count : null}
              icon={CheckCircle2}
            />
            <StatCard
              label={t("concessions.stats.granted")}
              value={stats ? formatETB(stats.granted_value) : null}
              hint={
                stats
                  ? t("concessions.stats.grantedInvoices", { count: stats.granted_invoices })
                  : undefined
              }
              icon={HandCoins}
              className="col-span-2 xl:col-span-1"
            />
          </div>

          <DataTable
            columns={columns}
            data={table.rows}
            loading={table.loading}
            serverMode
            searchable
            searchValue={table.searchInput}
            onSearchChange={table.setSearchInput}
            searchPlaceholder={t("concessions.searchPlaceholder")}
            filters={[...scopeFilters, ...filterDefs]}
            filterValues={table.filters}
            onFilterChange={table.setFilter}
            onSortChange={table.onSortChange}
            onExport={table.handleExport}
            actions={rowActions.length > 0 ? rowActions : undefined}
            bulkActions={bulkActions}
            emptyMessage={t("concessions.empty")}
            exportFilename="concessions"
            pagination={table.pagination}
          />
        </>
      )}

      <ConcessionSheet
        open={createOpen}
        onOpenChange={setCreateOpen}
        onSaved={() => refresh()}
      />

      {/* Decide a batch of pending suggestions. */}
      <BulkDecisionDialog
        open={bulkDecision !== null}
        onOpenChange={(v) => {
          if (!v) setBulkDecision(null)
        }}
        mode={bulkDecision?.decision === "rejected" ? "reject" : "approve"}
        title={
          bulkDecision?.decision === "rejected"
            ? t("concessions.bulk.rejectTitle", { count: bulkDecision?.rows.length ?? 0 })
            : t("concessions.bulk.approveTitle", { count: bulkDecision?.rows.length ?? 0 })
        }
        description={
          bulkDecision?.decision === "rejected"
            ? t("concessions.bulk.rejectDesc")
            : t("concessions.bulk.approveDesc")
        }
        noteLabel={t("concessions.bulk.noteLabel")}
        notePlaceholder={t("concessions.bulk.notePlaceholder")}
        confirmLabel={
          bulkDecision?.decision === "rejected"
            ? t("concessions.reject")
            : t("concessions.approve")
        }
        extra={
          // Approving only changes FUTURE bills unless the officer says to
          // reprice what is already open — same choice as the single-row path.
          bulkDecision?.decision === "approved" ? (
            <label className="bg-muted/40 flex items-start gap-2.5 rounded-xl px-3.5 py-3 text-sm">
              <Checkbox
                checked={reprice}
                onCheckedChange={(checked) => setReprice(checked === true)}
                className="mt-0.5"
              />
              <span>
                {t("concessions.bulk.applyOpen")}
                <span className="text-muted-foreground block text-xs">
                  {t("concessions.bulk.applyOpenHint")}
                </span>
              </span>
            </label>
          ) : undefined
        }
        onConfirm={async () => {
          if (!bulkDecision) return
          await runBulk({
            url: "/fee-concessions/bulk/decide",
            ids: bulkDecision.rows.map((r) => r.id),
            body: {
              decision: bulkDecision.decision,
              apply_to_open_invoices: bulkDecision.decision === "approved" ? reprice : undefined,
            },
            countKey: "decided",
            success: (count) =>
              bulkDecision.decision === "approved"
                ? t("concessions.bulk.approved", { count })
                : t("concessions.bulk.rejected", { count }),
            tc,
          })
          setBulkDecision(null)
          refresh()
        }}
      />
    </div>
  )
}
