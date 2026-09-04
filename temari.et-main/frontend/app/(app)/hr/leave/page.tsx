"use client"

import { CheckCircle2, Trash2, X, XCircle } from "lucide-react"

import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { LeaveBalancesGrid } from "@/components/hr/leave-balances"
import { LeaveDecisionDialog } from "@/components/hr/leave-decision-dialog"
import { LeaveRequestSheet } from "@/components/hr/leave-request-sheet"
import { LeaveSettings } from "@/components/hr/leave-settings"
import { Badge } from "@/components/ui/badge"
import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import { runBulk } from "@/components/ui/bulk-actions"
import { BulkDecisionDialog } from "@/components/ui/bulk-decision-dialog"
import { Checkbox } from "@/components/ui/checkbox"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  Employee,
  EmployeeLeaveBalances,
  Holiday,
  LeaveRequest,
  LeaveRequestStatus,
  LeaveType,
  Paginated,
} from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { cn } from "@/lib/utils"

const STATUSES: LeaveRequestStatus[] = ["pending", "approved", "rejected", "cancelled"]

const STATUS_BADGES: Record<LeaveRequestStatus, string> = {
  pending: "bg-warning/10 text-warning border-transparent",
  approved: "bg-success/10 text-success border-transparent",
  rejected: "bg-destructive/10 text-destructive border-transparent",
  cancelled: "bg-muted text-muted-foreground border-transparent",
}

type View = "requests" | "balances" | "settings"

export default function LeavePage() {
  const { t } = useTranslation("hr")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const canManage = permissions.includes("leave.manage")
  const canSettings = permissions.includes("hr.settings.manage")
  const hasBranch = active.branchId != null

  // School-wide workspace: the register is one-branch-at-a-time, so school
  // managers pick which branch to work as (no workspace switch needed).
  const { needsBranch } = useBranchScope()
  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && pickedBranchId != null)
  const branchParam = !hasBranch && pickedBranchId != null ? `&branch_id=${pickedBranchId}` : ""

  const [view, setView] = useState<View>("requests")

  // Shared reference data (types + holidays + employees for the sheet).
  const [leaveTypes, setLeaveTypes] = useState<LeaveType[]>([])
  const [holidays, setHolidays] = useState<Holiday[]>([])
  const [employees, setEmployees] = useState<{ id: number; name: string }[]>([])
  const [refDataVersion, setRefDataVersion] = useState(0)

  const [sheetOpen, setSheetOpen] = useState(false)
  const [decision, setDecision] = useState<{
    request: LeaveRequest
    mode: "approve" | "reject"
  } | null>(null)
  // Sweeping the queue: the selected rows plus which way they are going.
  const [bulkDecision, setBulkDecision] = useState<{
    rows: LeaveRequest[]
    mode: "approve" | "reject"
  } | null>(null)
  const [allowExceed, setAllowExceed] = useState(false)

  // Balances tab state.
  const [balances, setBalances] = useState<EmployeeLeaveBalances[] | null>(null)
  const [yearWindow, setYearWindow] = useState<{ start: string; end: string } | null>(null)

  const table = useServerTable<LeaveRequest>({
    endpoint: "/hr/leave-requests",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: branchReady,
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}-${pickedBranchId ?? ""}`,
    extraParams:
      !hasBranch && pickedBranchId != null ? { branch_id: String(pickedBranchId) } : undefined,
    loadFailedMessage: t("leave.loadFailed"),
  })

  useEffect(() => {
    if (!branchReady) return
    let cancelled = false
    apiFetch<{ data: LeaveType[] }>("/hr/leave-types?all=1")
      .then((res) => !cancelled && setLeaveTypes(res.data))
      .catch(() => {})
    apiFetch<{ data: Holiday[] }>("/hr/holidays")
      .then((res) => !cancelled && setHolidays(res.data))
      .catch(() => {})
    if (canManage) {
      apiFetch<Paginated<Employee>>(`/employees?per_page=100&is_active=true${branchParam}`)
        .then(
          (res) =>
            !cancelled &&
            setEmployees(res.data.map((e) => ({ id: e.id, name: e.full_name }))),
        )
        .catch(() => {})
    }
    return () => {
      cancelled = true
    }
  }, [branchReady, branchParam, active.branchId, canManage, refDataVersion])

  useEffect(() => {
    if (!branchReady || view !== "balances") return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on scope change
    setBalances(null)
    apiFetch<{ data: EmployeeLeaveBalances[]; meta: { year_start: string; year_end: string } }>(
      branchParam ? `/hr/leave-balances?${branchParam.slice(1)}` : "/hr/leave-balances",
    )
      .then((res) => {
        if (cancelled) return
        setBalances(res.data)
        setYearWindow({ start: res.meta.year_start, end: res.meta.year_end })
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : t("leave.loadFailed"))
        setBalances([])
      })
    return () => {
      cancelled = true
    }
  }, [branchReady, branchParam, view, active.branchId, refDataVersion, t])

  const refreshAll = useCallback(() => {
    table.refetch()
    setRefDataVersion((v) => v + 1)
  }, [table])

  async function cancelRequest(request: LeaveRequest) {
    try {
      await apiFetch(`/hr/leave-requests/${request.id}/cancel`, { method: "POST" })
      toast.success(t("leave.cancelled"))
      refreshAll()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    }
  }

  async function deleteRequest(request: LeaveRequest) {
    try {
      await apiFetch(`/hr/leave-requests/${request.id}`, { method: "DELETE" })
      toast.success(t("leave.deleted"))
      refreshAll()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    }
  }

  const columns: DataTableColumn<LeaveRequest>[] = [
    {
      key: "employee",
      label: t("leave.columns.employee"),
      primary: true,
      render: (row) => <span className="font-medium">{row.employee_name}</span>,
      exportValue: (row) => row.employee_name ?? "",
    },
    {
      key: "type",
      label: t("leave.columns.type"),
      render: (row) => (
        <span>
          {row.leave_type_name}
          {row.is_half_day && (
            <span className="text-xs text-muted-foreground"> · {t("leave.halfDay")}</span>
          )}
        </span>
      ),
      exportValue: (row) => row.leave_type_name ?? "",
    },
    {
      key: "start_date",
      label: t("leave.columns.window"),
      sortable: true,
      render: (row) => (
        <span className="tabular-nums">
          {row.start_date === row.end_date
            ? row.start_date
            : `${row.start_date} → ${row.end_date}`}
        </span>
      ),
      exportValue: (row) => `${row.start_date} → ${row.end_date}`,
    },
    {
      key: "days",
      label: t("leave.columns.days"),
      sortable: true,
      render: (row) => <span className="tabular-nums">{row.days}</span>,
      exportValue: (row) => String(row.days),
    },
    {
      key: "status",
      label: t("leave.columns.status"),
      sortable: true,
      render: (row) => (
        <Badge className={STATUS_BADGES[row.status]}>
          {t(`leave.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => t(`leave.statuses.${row.status}`),
    },
    {
      key: "decided_by",
      label: t("leave.columns.decidedBy"),
      mobileHidden: true,
      render: (row) => (
        <span className="text-sm text-muted-foreground">{row.decided_by_name ?? "—"}</span>
      ),
      exportValue: (row) => row.decided_by_name ?? "",
    },
  ]

  const filterDefs: DataTableFilter[] = [
    {
      key: "status",
      label: tc("filters.status"),
      options: STATUSES.map((s) => ({ label: t(`leave.statuses.${s}`), value: s })),
    },
    {
      key: "leave_type_id",
      label: t("leave.columns.type"),
      options: leaveTypes.map((lt) => ({ label: lt.name, value: String(lt.id) })),
    },
  ]

  const rowActions = canManage
    ? [
        {
          label: t("leave.actions.approve"),
          icon: CheckCircle2,
          hidden: (row: LeaveRequest) => row.status !== "pending",
          onClick: (row: LeaveRequest) => setDecision({ request: row, mode: "approve" }),
        },
        {
          label: t("leave.actions.reject"),
          icon: XCircle,
          hidden: (row: LeaveRequest) => row.status !== "pending",
          onClick: (row: LeaveRequest) => setDecision({ request: row, mode: "reject" }),
        },
        {
          label: t("leave.actions.cancel"),
          icon: X,
          hidden: (row: LeaveRequest) =>
            !(row.status === "pending" || row.status === "approved"),
          onClick: (row: LeaveRequest) =>
            confirmDelete(() => cancelRequest(row), t("leave.cancelConfirm")),
        },
        {
          label: t("leave.actions.delete"),
          icon: Trash2,
          destructive: true,
          onClick: (row: LeaveRequest) =>
            confirmDelete(
              () => deleteRequest(row),
              tc("confirmDelete.named", { name: row.employee_name ?? "" }),
            ),
        },
      ]
    : undefined

  // Only pending rows can be decided, so the sweep works on those; a selection
  // of already-decided rows says so instead of opening an empty decision.
  function openBulkDecision(rows: LeaveRequest[], mode: "approve" | "reject") {
    const pending = rows.filter((r) => r.status === "pending")
    if (pending.length === 0) {
      toast.info(t("leave.bulk.nonePending"))
      return
    }
    setAllowExceed(false)
    setBulkDecision({ rows: pending, mode })
  }

  const bulkActions = canManage
    ? [
        {
          label: t("leave.actions.approve"),
          icon: CheckCircle2,
          onClick: (rows: LeaveRequest[]) => openBulkDecision(rows, "approve"),
        },
        {
          label: t("leave.actions.reject"),
          icon: XCircle,
          destructive: true,
          onClick: (rows: LeaveRequest[]) => openBulkDecision(rows, "reject"),
        },
      ]
    : undefined

  const views: { key: View; label: string; visible: boolean }[] = [
    { key: "requests", label: t("leave.tabs.requests"), visible: true },
    { key: "balances", label: t("leave.tabs.balances"), visible: true },
    { key: "settings", label: t("leave.tabs.settings"), visible: true },
  ]

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("leave.title")}
        description={t("leave.subtitle")}
        actions={
          canManage && branchReady && view === "requests" ? (
            <LeaveRequestSheet
              open={sheetOpen}
              onOpenChange={setSheetOpen}
              onSaved={refreshAll}
              leaveTypes={leaveTypes.filter((lt) => lt.is_active)}
              employees={employees}
              branchId={!hasBranch ? pickedBranchId : null}
              showTrigger
            />
          ) : undefined
        }
      />

      {/* School-wide: which branch's register to work as. */}
      {needsBranch && (
        <div className="page-gutter">
          <BranchScopePicker value={pickedBranchId} onChange={setPickedBranchId} />
        </div>
      )}

      {!branchReady ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("leave.noBranch")}
          </div>
        </div>
      ) : (
        <>
          {/* View switcher — pill segmented control per the toolbar language. */}
          <div className="page-gutter">
            <div className="inline-flex rounded-full border bg-card p-1 shadow-xs">
              {views
                .filter((v) => v.visible)
                .map((v) => (
                  <button
                    key={v.key}
                    type="button"
                    onClick={() => setView(v.key)}
                    aria-pressed={view === v.key}
                    className={cn(
                      "pressable min-h-9 rounded-full px-4 text-sm font-medium transition-colors",
                      view === v.key
                        ? "bg-primary text-primary-foreground"
                        : "text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {v.label}
                  </button>
                ))}
            </div>
          </div>

          {view === "requests" && (
            <DataTable
              columns={columns}
              data={table.rows}
              loading={table.loading}
              serverMode
              searchable
              searchValue={table.searchInput}
              onSearchChange={table.setSearchInput}
              searchPlaceholder={tc("actions.search")}
              filters={filterDefs}
              filterValues={table.filters}
              onFilterChange={table.setFilter}
              onSortChange={table.onSortChange}
              actions={rowActions}
              bulkActions={bulkActions}
              emptyMessage={t("leave.empty")}
              exportFilename="leave-requests"
              pagination={table.pagination}
            />
          )}

          {view === "balances" && (
            <div className="page-gutter">
              <LeaveBalancesGrid balances={balances} yearWindow={yearWindow} />
            </div>
          )}

          {view === "settings" && (
            <div className="page-gutter">
              <LeaveSettings
                leaveTypes={leaveTypes}
                holidays={holidays}
                branchId={active.branchId}
                canManage={canSettings}
                onChanged={refreshAll}
              />
            </div>
          )}
        </>
      )}

      <LeaveDecisionDialog
        request={decision?.request ?? null}
        mode={decision?.mode ?? "approve"}
        open={decision !== null}
        onOpenChange={(v) => {
          if (!v) setDecision(null)
        }}
        onDecided={refreshAll}
      />

      {/* Decide the whole selection at once. */}
      <BulkDecisionDialog
        open={bulkDecision !== null}
        onOpenChange={(v) => {
          if (!v) setBulkDecision(null)
        }}
        mode={bulkDecision?.mode ?? "approve"}
        title={
          bulkDecision?.mode === "reject"
            ? t("leave.bulk.rejectTitle", { count: bulkDecision?.rows.length ?? 0 })
            : t("leave.bulk.approveTitle", { count: bulkDecision?.rows.length ?? 0 })
        }
        description={t("leave.bulk.desc")}
        noteLabel={t("leave.decisionNote")}
        notePlaceholder={
          bulkDecision?.mode === "reject"
            ? t("leave.rejectNotePlaceholder")
            : t("leave.decisionNoteOptional")
        }
        confirmLabel={
          bulkDecision?.mode === "reject"
            ? t("leave.actions.reject")
            : t("leave.actions.approve")
        }
        extra={
          // A sweep must never quietly grant days nobody has left: the server
          // skips over-balance rows unless this is ticked deliberately.
          bulkDecision?.mode === "approve" ? (
            <label className="bg-warning/10 flex items-start gap-2.5 rounded-xl px-3.5 py-3 text-sm">
              <Checkbox
                checked={allowExceed}
                onCheckedChange={(checked) => setAllowExceed(checked === true)}
                className="mt-0.5"
              />
              <span>
                {t("leave.allowExceed")}
                <span className="text-muted-foreground block text-xs">
                  {t("leave.bulk.exceedHint")}
                </span>
              </span>
            </label>
          ) : undefined
        }
        onConfirm={async (note) => {
          if (!bulkDecision) return
          await runBulk({
            url: "/hr/leave-requests/bulk/decide",
            ids: bulkDecision.rows.map((r) => r.id),
            body: {
              decision: bulkDecision.mode === "approve" ? "approved" : "rejected",
              decision_note: note || undefined,
              allow_exceeding_balance: bulkDecision.mode === "approve" ? allowExceed : undefined,
            },
            countKey: "decided",
            success: (count) =>
              bulkDecision.mode === "approve"
                ? t("leave.bulk.approved", { count })
                : t("leave.bulk.rejected", { count }),
            tc,
          })
          setBulkDecision(null)
          refreshAll()
        }}
      />
    </div>
  )
}
