"use client"

import {
  BookMarked,
  BookX,
  ClipboardCheck,
  Eye,
  PackageMinus,
  PackagePlus,
  Pencil,
  Plus,
  ShoppingCart,
  Tags,
  Trash2,
  Undo2,
} from "lucide-react"
import { useSearchParams } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { AssetUnitSheet } from "@/components/inventory/asset-sheets"
import { runBulk, useBulkConfirm } from "@/components/ui/bulk-actions"
import { InventoryCategoriesDialog } from "@/components/inventory/categories-dialog"
import { ItemDetailSheet } from "@/components/inventory/item-detail-sheet"
import { InventoryItemSheet } from "@/components/inventory/item-sheet"
import { MovementSheet, type MovementKind } from "@/components/inventory/movement-sheet"
import { InventoryOverview } from "@/components/inventory/overview-tab"
import { PO_TONE, PurchaseOrderDetailSheet, PurchaseOrderSheet } from "@/components/inventory/po-sheets"
import {
  REQUISITION_TONE,
  RequestItemsSheet,
  RequisitionDetailSheet,
} from "@/components/inventory/requisition-sheets"
import {
  STOCK_TAKE_TONE,
  StartStockTakeDialog,
  StockTakeCountSheet,
} from "@/components/inventory/stock-take-sheets"
import { IssueTextbooksSheet } from "@/components/inventory/textbook-sheets"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { ApiError, apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { fmtDate } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type {
  AssetUnit,
  InventoryCategory,
  InventoryItem,
  InventoryStats,
  Paginated,
  PurchaseOrder,
  Requisition,
  StockTake,
  TextbookLoan,
} from "@/lib/types"
import { useScopeFilters } from "@/lib/use-scope-filters"
import { useServerTable } from "@/lib/use-server-table"
import { cn } from "@/lib/utils"

const TAB_KEYS = [
  "overview",
  "items",
  "requisitions",
  "purchase-orders",
  "textbooks",
  "stock-takes",
] as const
type TabKey = (typeof TAB_KEYS)[number]

const TAB_LABEL_KEY: Record<TabKey, string> = {
  overview: "tabs.overview",
  items: "tabs.items",
  requisitions: "tabs.requisitions",
  "purchase-orders": "tabs.purchaseOrders",
  textbooks: "textbooks.tab",
  "stock-takes": "tabs.stockTakes",
}

/**
 * The school store hub — guided, not a wall of CRUD. Overview is the home
 * (the storekeeper's verbs as cards); Items carries the register with a
 * per-item drill-in holding the bin card and asset units; the remaining
 * tabs are the workflows (requests, orders, textbooks, counts).
 */
export default function InventoryPage() {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { confirmBulk, bulkDialog } = useBulkConfirm()
  const { user } = useAuth()
  const { active } = useSchoolContext()
  const searchParams = useSearchParams()
  const workspace = `${active.schoolId ?? ""}-${active.branchId ?? ""}`
  const permissions = useEffectivePermissions()

  const canManage = permissions.includes("inventory.manage")
  const canApprove = permissions.includes("inventory.approve")
  const canView = permissions.includes("inventory.view") || canManage
  const canRequest = permissions.includes("inventory.request") || canManage

  const visibleTabs = useMemo(
    () =>
      TAB_KEYS.filter((key) => {
        if (key === "requisitions") return canView || canApprove || canRequest
        if (key === "purchase-orders") return canView || canApprove
        return canView
      }),
    [canView, canApprove, canRequest]
  )
  const [tab, setTab] = useProfileTabs(visibleTabs, canView ? "overview" : "requisitions")
  const tabs = useMemo(
    () => visibleTabs.map((key) => ({ key, label: t(TAB_LABEL_KEY[key]) })),
    [visibleTabs, t]
  )

  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const [refresh, setRefresh] = useState(0)
  const bump = () => setRefresh((n) => n + 1)

  // Categories back the item form, filters and the stock-take dialog.
  const [categories, setCategories] = useState<InventoryCategory[]>([])
  useEffect(() => {
    if (!canView && !canRequest) return
    let cancelled = false
    apiFetch<{ data: InventoryCategory[] }>("/inventory/categories?include_inactive=1")
      .then((res) => !cancelled && setCategories(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [workspace, refresh, canView, canRequest])

  // Stats power the Overview attention lists and the Items tiles.
  const [stats, setStats] = useState<InventoryStats | null>(null)
  useEffect(() => {
    if (!canView) return
    let cancelled = false
    apiFetch<{ data: InventoryStats }>("/inventory/items/stats")
      .then((res) => !cancelled && setStats(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [canView, workspace, refresh])

  // ── Items & stock ─────────────────────────────────────────────────
  const items = useServerTable<InventoryItem>({
    endpoint: "/inventory/items",
    defaultSort: { key: "name", dir: "asc" },
    enabled: tab === "items" && canView,
    refreshKey: `${workspace}|${refresh}`,
  })

  const [itemSheet, setItemSheet] = useState<{ item: InventoryItem | null } | null>(null)
  const [itemDetail, setItemDetail] = useState<InventoryItem | null>(null)
  const [movementSheet, setMovementSheet] = useState<{
    kind: MovementKind
    item?: InventoryItem | null
  } | null>(null)
  const [categoriesOpen, setCategoriesOpen] = useState(false)

  // ⌘K deep link: /inventory?asset_tag=X7K2QF opens that unit directly.
  const [deepLinkUnit, setDeepLinkUnit] = useState<AssetUnit | null>(null)
  const assetTag = searchParams.get("asset_tag")
  useEffect(() => {
    if (!assetTag || !canView) return
    let cancelled = false
    apiFetch<Paginated<AssetUnit>>(`/inventory/assets?search=${encodeURIComponent(assetTag)}&per_page=1`)
      .then((res) => !cancelled && res.data[0] && setDeepLinkUnit(res.data[0]))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [assetTag, canView])

  const itemColumns: DataTableColumn<InventoryItem>[] = [
    {
      key: "name",
      label: t("items.name"),
      primary: true,
      sortable: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="flex items-center gap-1.5 truncate font-medium">
            <span className="truncate">{row.name}</span>
            {row.is_asset && (
              <Badge variant="outline" className="shrink-0 rounded-full text-[10px]">
                {t("items.assetBadge")}
              </Badge>
            )}
            {!row.is_active && (
              <Badge
                variant="outline"
                className="shrink-0 rounded-full text-[10px] text-muted-foreground"
              >
                {t("items.inactive")}
              </Badge>
            )}
          </p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.category_name, row.code].filter(Boolean).join(" · ")}
          </p>
        </div>
      ),
      exportValue: (row) => row.name,
    },
    {
      key: "quantity_on_hand",
      label: t("items.onHand"),
      sortable: true,
      className: "text-right",
      render: (row) => {
        const qty = Number(row.quantity_on_hand ?? 0)
        const reorder = row.reorder_level != null ? Number(row.reorder_level) : null
        const out = qty <= 0
        const low = !out && reorder != null && qty <= reorder
        return (
          <span className="inline-flex items-center gap-1.5 tabular-nums">
            {(out || low) && (
              <Badge
                variant="outline"
                className={cn(
                  "rounded-full text-[10px]",
                  out
                    ? "border-destructive/30 bg-destructive/10 text-destructive"
                    : "border-warning/30 bg-warning/10 text-warning"
                )}
              >
                {out ? t("items.outBadge") : t("items.lowBadge")}
              </Badge>
            )}
            <span className={cn("font-medium", out && "text-destructive")}>
              {qty} {t(`units.${row.unit}`)}
            </span>
          </span>
        )
      },
      exportValue: (row) => String(Number(row.quantity_on_hand ?? 0)),
    },
    {
      key: "reorder_level",
      label: t("items.reorderLevel"),
      sortable: true,
      mobileHidden: true,
      className: "text-right",
      render: (row) =>
        row.reorder_level != null ? (
          <span className="text-muted-foreground tabular-nums">{Number(row.reorder_level)}</span>
        ) : (
          <span className="text-muted-foreground/40">—</span>
        ),
      exportValue: (row) => (row.reorder_level != null ? String(Number(row.reorder_level)) : ""),
    },
  ]

  // ── Requisitions ──────────────────────────────────────────────────
  const requisitions = useServerTable<Requisition>({
    endpoint: "/inventory/requisitions",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: tab === "requisitions",
    refreshKey: `${workspace}|${refresh}`,
  })

  const [requestSheet, setRequestSheet] = useState<{ requisition: Requisition | null } | null>(null)
  const [requisitionDetail, setRequisitionDetail] = useState<Requisition | null>(null)

  const requisitionColumns: DataTableColumn<Requisition>[] = [
    {
      key: "requested_by_name",
      label: t("requisitions.requester"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.requested_by_name}</p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.purpose, row.branch_name].filter(Boolean).join(" · ") || "—"}
          </p>
        </div>
      ),
      exportValue: (row) => row.requested_by_name ?? "",
    },
    {
      key: "items_count",
      label: t("requisitions.items"),
      className: "text-right",
      mobileHidden: true,
      render: (row) => (
        <span className="tabular-nums">{row.items_count ?? row.items?.length ?? 0}</span>
      ),
      exportValue: (row) => String(row.items_count ?? 0),
    },
    {
      key: "status",
      label: t("requisitions.status"),
      sortable: true,
      render: (row) => (
        <Badge variant="outline" className={cn("rounded-full", REQUISITION_TONE[row.status])}>
          {t(`requisitions.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => row.status,
    },
    {
      key: "created_at",
      label: t("requisitions.requestedOn"),
      sortable: true,
      render: (row) => fmtDate(row.created_at),
      exportValue: (row) => row.created_at,
    },
    {
      key: "decided_by_name",
      label: t("requisitions.decidedBy"),
      mobileHidden: true,
      render: (row) => row.decided_by_name ?? "—",
      exportValue: (row) => row.decided_by_name ?? "",
    },
  ]

  // ── Purchase orders ───────────────────────────────────────────────
  const purchaseOrders = useServerTable<PurchaseOrder>({
    endpoint: "/inventory/purchase-orders",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: tab === "purchase-orders" && (canView || canApprove),
    refreshKey: `${workspace}|${refresh}`,
  })

  const [poSheet, setPoSheet] = useState<{ order: PurchaseOrder | null } | null>(null)
  const [poDetail, setPoDetail] = useState<PurchaseOrder | null>(null)

  const poColumns: DataTableColumn<PurchaseOrder>[] = [
    {
      key: "supplier_name",
      label: t("po.supplier"),
      primary: true,
      sortable: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.supplier_name}</p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.ordered_by_name, row.branch_name].filter(Boolean).join(" · ")}
          </p>
        </div>
      ),
      exportValue: (row) => row.supplier_name,
    },
    {
      key: "total_cost",
      label: t("po.total"),
      sortable: true,
      className: "text-right",
      render: (row) => (
        <span className="font-medium tabular-nums">{Number(row.total_cost).toLocaleString()}</span>
      ),
      exportValue: (row) => row.total_cost,
    },
    {
      key: "status",
      label: t("po.status"),
      sortable: true,
      render: (row) => (
        <Badge variant="outline" className={cn("rounded-full", PO_TONE[row.status])}>
          {t(`po.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => row.status,
    },
    {
      key: "expected_on",
      label: t("po.expectedOn"),
      sortable: true,
      mobileHidden: true,
      render: (row) => (row.expected_on ? fmtDate(row.expected_on) : "—"),
      exportValue: (row) => row.expected_on ?? "",
    },
    {
      key: "created_at",
      label: t("requisitions.requestedOn"),
      sortable: true,
      mobileHidden: true,
      render: (row) => fmtDate(row.created_at),
      exportValue: (row) => row.created_at,
    },
  ]

  // ── Textbooks ─────────────────────────────────────────────────────
  const textbooks = useServerTable<TextbookLoan>({
    endpoint: "/inventory/textbooks",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: tab === "textbooks" && canView,
    refreshKey: `${workspace}|${refresh}`,
  })

  const [issueBooksOpen, setIssueBooksOpen] = useState(false)
  const [losingLoan, setLosingLoan] = useState<TextbookLoan | null>(null)
  const [lostNote, setLostNote] = useState("")
  const [loanBusy, setLoanBusy] = useState(false)

  async function returnLoan(loan: TextbookLoan) {
    setLoanBusy(true)
    try {
      await apiFetch("/inventory/textbooks/return", {
        method: "POST",
        body: { ids: [loan.id] },
      })
      toast.success(t("textbooks.returnedToast"))
      bump()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setLoanBusy(false)
    }
  }

  async function markLoanLost() {
    if (!losingLoan) return
    setLoanBusy(true)
    try {
      await apiFetch(`/inventory/textbooks/${losingLoan.id}/lost`, {
        method: "POST",
        body: { note: lostNote || null },
      })
      toast.success(t("textbooks.lostToast"))
      setLosingLoan(null)
      bump()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setLoanBusy(false)
    }
  }

  const LOAN_TONE: Record<TextbookLoan["status"], string> = {
    out: "border-info/30 bg-info/10 text-info",
    returned: "border-success/30 bg-success/10 text-success",
    lost: "border-destructive/30 bg-destructive/10 text-destructive",
  }

  const textbookColumns: DataTableColumn<TextbookLoan>[] = [
    {
      key: "student_name",
      label: t("textbooks.student"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.student_name}</p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.section_name, row.student_public_id, row.branch_name]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
      ),
      exportValue: (row) => row.student_name ?? "",
    },
    {
      key: "item_name",
      label: t("textbooks.book"),
      render: (row) => <span className="truncate">{row.item_name}</span>,
      exportValue: (row) => row.item_name ?? "",
    },
    {
      key: "status",
      label: t("textbooks.status"),
      sortable: true,
      render: (row) => (
        <Badge variant="outline" className={cn("rounded-full", LOAN_TONE[row.status])}>
          {t(`textbooks.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => row.status,
    },
    {
      key: "created_at",
      label: t("textbooks.issuedOn"),
      sortable: true,
      mobileHidden: true,
      render: (row) => fmtDate(row.created_at),
      exportValue: (row) => row.created_at,
    },
  ]

  // ── Stock takes ───────────────────────────────────────────────────
  const stockTakes = useServerTable<StockTake>({
    endpoint: "/inventory/stock-takes",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: tab === "stock-takes" && canView,
    refreshKey: `${workspace}|${refresh}`,
  })

  const [startTakeOpen, setStartTakeOpen] = useState(false)
  const [countingTake, setCountingTake] = useState<StockTake | null>(null)

  const stockTakeColumns: DataTableColumn<StockTake>[] = [
    {
      key: "category_name",
      label: t("stockTakes.category"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">
            {row.category_name ?? t("stockTakes.allCategories")}
          </p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.note, row.branch_name].filter(Boolean).join(" · ") || "—"}
          </p>
        </div>
      ),
      exportValue: (row) => row.category_name ?? "",
    },
    {
      key: "counted_count",
      label: t("stockTakes.progress"),
      className: "text-right",
      render: (row) => (
        <span className="tabular-nums">
          {row.counted_count ?? 0}/{row.lines_count ?? 0}
        </span>
      ),
      exportValue: (row) => `${row.counted_count ?? 0}/${row.lines_count ?? 0}`,
    },
    {
      key: "status",
      label: t("stockTakes.status"),
      sortable: true,
      render: (row) => (
        <Badge variant="outline" className={cn("rounded-full", STOCK_TAKE_TONE[row.status])}>
          {t(`stockTakes.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => row.status,
    },
    {
      key: "started_by_name",
      label: t("stockTakes.startedBy"),
      mobileHidden: true,
      render: (row) => row.started_by_name ?? "—",
      exportValue: (row) => row.started_by_name ?? "",
    },
    {
      key: "created_at",
      label: t("stockTakes.startedOn"),
      sortable: true,
      render: (row) => fmtDate(row.created_at),
      exportValue: (row) => row.created_at,
    },
  ]

  // School → Branch narrowing following the active tab's filter state.
  const scopeFilters = useScopeFilters(
    tab === "requisitions"
      ? requisitions.filters
      : tab === "purchase-orders"
        ? purchaseOrders.filters
        : tab === "stock-takes"
          ? stockTakes.filters
          : tab === "textbooks"
            ? textbooks.filters
            : items.filters
  )

  const categoryFilter = {
    key: "inventory_category_id",
    label: t("items.category"),
    options: categories
      .filter((c) => c.is_active)
      .map((c) => ({ value: String(c.id), label: c.name })),
  }

  const headerActions =
    tab === "items" && canManage ? (
      <div className="flex flex-wrap items-center gap-2">
        <Button
          variant="outline"
          className="h-11"
          onClick={() => setCategoriesOpen(true)}
          title={t("categories.manage")}
          aria-label={t("categories.manage")}
        >
          <Tags className="size-4" />
          <span className="hidden sm:inline">{t("categories.manage")}</span>
        </Button>
        <Button className="h-11" onClick={() => setItemSheet({ item: null })}>
          <Plus className="size-4" />
          {t("items.addToStore")}
        </Button>
      </div>
    ) : tab === "requisitions" && canRequest ? (
      <Button className="h-11" onClick={() => setRequestSheet({ requisition: null })}>
        <ShoppingCart className="size-4" />
        {t("requisitions.new")}
      </Button>
    ) : tab === "purchase-orders" && canManage ? (
      <Button className="h-11" onClick={() => setPoSheet({ order: null })}>
        <Plus className="size-4" />
        {t("po.new")}
      </Button>
    ) : tab === "textbooks" && canManage ? (
      <Button className="h-11" onClick={() => setIssueBooksOpen(true)}>
        <BookMarked className="size-4" />
        {t("textbooks.issue")}
      </Button>
    ) : tab === "stock-takes" && canManage ? (
      <Button className="h-11" onClick={() => setStartTakeOpen(true)}>
        <ClipboardCheck className="size-4" />
        {t("stockTakes.new")}
      </Button>
    ) : null

  return (
    <div className="space-y-6">
      <PageHeader title={t("title")} description={t("subtitle")} actions={headerActions} />

      {tabs.length > 1 && (
        <div className="page-gutter">
          <ProfileTabBar tabs={tabs} value={tab} onChange={setTab} />
        </div>
      )}

      {/* ══ Overview — the guided home ══ */}
      {tab === "overview" && canView && (
        <InventoryOverview
          stats={stats}
          canManage={canManage}
          canApprove={canApprove}
          canRequest={canRequest}
          refreshKey={`${workspace}|${refresh}`}
          onAddToStore={() => setItemSheet({ item: null })}
          onReceive={() => setMovementSheet({ kind: "receive" })}
          onIssue={() => setMovementSheet({ kind: "issue" })}
          onStartCount={() => setStartTakeOpen(true)}
          onRequestItems={() => setRequestSheet({ requisition: null })}
          onOpenItem={(item) => setItemDetail(item)}
          onOpenRequisition={(requisition) => setRequisitionDetail(requisition)}
          onGoToTab={(next) => setTab(next as TabKey)}
        />
      )}

      {/* ══ Items & stock ══ */}
      {tab === "items" && canView && (
        <DataTable
          columns={itemColumns}
          data={items.rows}
          loading={items.loading}
          total={items.total}
          serverMode
          searchable
          searchValue={items.searchInput}
          onSearchChange={items.setSearchInput}
          searchPlaceholder={t("items.searchPlaceholder")}
          filters={[
            ...scopeFilters,
            categoryFilter,
            {
              key: "low_stock",
              label: t("items.lowStockFilter"),
              options: [{ value: "1", label: t("items.lowStockFilter") }],
            },
            {
              key: "is_asset",
              label: t("items.asset"),
              options: [
                { value: "true", label: t("items.assetBadge") },
                { value: "false", label: t("items.unit") },
              ],
            },
          ]}
          filterValues={items.filters}
          onFilterChange={items.setFilter}
          onSortChange={items.onSortChange}
          pagination={items.pagination}
          emptyMessage={t("items.empty")}
          exportFilename="inventory-items"
          actions={[
            {
              label: t("items.open"),
              icon: Eye,
              primary: true,
              onClick: (row) => setItemDetail(row),
            },
            {
              label: t("quick.receive"),
              icon: PackagePlus,
              hidden: () => !canManage,
              onClick: (row) => setMovementSheet({ kind: "receive", item: row }),
            },
            {
              label: t("quick.issue"),
              icon: PackageMinus,
              hidden: () => !canManage,
              onClick: (row) => setMovementSheet({ kind: "issue", item: row }),
            },
            {
              label: tc("actions.edit"),
              icon: Pencil,
              hidden: () => !canManage,
              onClick: (row) => setItemSheet({ item: row }),
            },
            {
              label: tc("actions.delete"),
              icon: Trash2,
              destructive: true,
              hidden: () => !canManage,
              onClick: (row) =>
                confirmDelete(async () => {
                  try {
                    await apiFetch(`/inventory/items/${row.id}`, { method: "DELETE" })
                    toast.success(t("items.deleted"))
                    bump()
                  } catch (error) {
                    toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
                  }
                }, t("items.deleteBody")),
            },
          ]}
        />
      )}

      {/* ══ Requisitions ══ */}
      {tab === "requisitions" && (
        <DataTable
          columns={requisitionColumns}
          data={requisitions.rows}
          loading={requisitions.loading}
          total={requisitions.total}
          serverMode
          searchable
          searchValue={requisitions.searchInput}
          onSearchChange={requisitions.setSearchInput}
          searchPlaceholder={t("requisitions.searchPlaceholder")}
          filters={[
            ...scopeFilters,
            {
              key: "status",
              label: t("requisitions.status"),
              options: (
                ["pending", "approved", "declined", "issued", "cancelled"] as const
              ).map((status) => ({
                value: status,
                label: t(`requisitions.statuses.${status}`),
              })),
            },
            ...(canView || canApprove
              ? [
                  {
                    key: "mine",
                    label: t("requisitions.mineFilter"),
                    options: [{ value: "1", label: t("requisitions.mineFilter") }],
                  },
                ]
              : []),
          ]}
          filterValues={requisitions.filters}
          onFilterChange={requisitions.setFilter}
          onSortChange={requisitions.onSortChange}
          pagination={requisitions.pagination}
          emptyMessage={canView ? t("requisitions.empty") : t("requisitions.emptyMine")}
          exportFilename="store-requests"
          actions={[
            {
              label: t("requisitions.view"),
              icon: Eye,
              primary: true,
              onClick: (row) => setRequisitionDetail(row),
            },
          ]}
        />
      )}

      {/* ══ Purchase orders ══ */}
      {tab === "purchase-orders" && (canView || canApprove) && (
        <DataTable
          columns={poColumns}
          data={purchaseOrders.rows}
          loading={purchaseOrders.loading}
          total={purchaseOrders.total}
          serverMode
          searchable
          searchValue={purchaseOrders.searchInput}
          onSearchChange={purchaseOrders.setSearchInput}
          searchPlaceholder={t("po.searchPlaceholder")}
          filters={[
            ...scopeFilters,
            {
              key: "status",
              label: t("po.status"),
              options: (
                ["pending", "approved", "declined", "received", "cancelled"] as const
              ).map((status) => ({ value: status, label: t(`po.statuses.${status}`) })),
            },
          ]}
          filterValues={purchaseOrders.filters}
          onFilterChange={purchaseOrders.setFilter}
          onSortChange={purchaseOrders.onSortChange}
          pagination={purchaseOrders.pagination}
          emptyMessage={t("po.empty")}
          exportFilename="purchase-orders"
          actions={[
            {
              label: t("po.view"),
              icon: Eye,
              primary: true,
              onClick: (row) => setPoDetail(row),
            },
          ]}
        />
      )}

      {/* ══ Textbooks ══ */}
      {tab === "textbooks" && canView && (
        <DataTable
          columns={textbookColumns}
          data={textbooks.rows}
          loading={textbooks.loading}
          total={textbooks.total}
          serverMode
          searchable
          searchValue={textbooks.searchInput}
          onSearchChange={textbooks.setSearchInput}
          searchPlaceholder={t("textbooks.searchPlaceholder")}
          filters={[
            ...scopeFilters,
            {
              key: "status",
              label: t("textbooks.status"),
              options: (["out", "returned", "lost"] as const).map((status) => ({
                value: status,
                label: t(`textbooks.statuses.${status}`),
              })),
            },
          ]}
          filterValues={textbooks.filters}
          onFilterChange={textbooks.setFilter}
          onSortChange={textbooks.onSortChange}
          pagination={textbooks.pagination}
          emptyMessage={t("textbooks.empty")}
          exportFilename="textbook-loans"
          bulkActions={
            canManage
              ? [
                  {
                    // End-of-year collection: a whole section's books at once.
                    label: t("textbooks.markReturned"),
                    icon: Undo2,
                    onClick: (rows: TextbookLoan[]) => {
                      const out = rows.filter((r) => r.status === "out")
                      if (out.length === 0) {
                        toast.info(t("textbooks.bulk.noneOut"))
                        return
                      }
                      confirmBulk({
                        title: t("textbooks.bulk.returnTitle", { count: out.length }),
                        description: t("textbooks.bulk.returnDesc"),
                        confirmLabel: t("textbooks.markReturned"),
                        action: async () => {
                          await runBulk({
                            url: "/inventory/textbooks/return",
                            ids: out.map((r) => r.id),
                            countKey: "returned",
                            success: (count) => t("textbooks.bulk.returned", { count }),
                            tc,
                          })
                          bump()
                        },
                      })
                    },
                  },
                ]
              : undefined
          }
          actions={[
            {
              label: t("textbooks.markReturned"),
              icon: Undo2,
              primary: true,
              hidden: (row) => !canManage || row.status !== "out",
              disabled: () => loanBusy,
              onClick: (row) => void returnLoan(row),
            },
            {
              label: t("textbooks.markLost"),
              icon: BookX,
              destructive: true,
              hidden: (row) => !canManage || row.status !== "out",
              onClick: (row) => {
                setLostNote("")
                setLosingLoan(row)
              },
            },
          ]}
        />
      )}

      {/* ══ Stock takes ══ */}
      {tab === "stock-takes" && canView && (
        <DataTable
          columns={stockTakeColumns}
          data={stockTakes.rows}
          loading={stockTakes.loading}
          total={stockTakes.total}
          serverMode
          filters={[
            ...scopeFilters,
            {
              key: "status",
              label: t("stockTakes.status"),
              options: (["in_progress", "posted", "cancelled"] as const).map((status) => ({
                value: status,
                label: t(`stockTakes.statuses.${status}`),
              })),
            },
          ]}
          filterValues={stockTakes.filters}
          onFilterChange={stockTakes.setFilter}
          onSortChange={stockTakes.onSortChange}
          pagination={stockTakes.pagination}
          emptyMessage={t("stockTakes.empty")}
          exportFilename="stock-takes"
          actions={[
            {
              label: t("stockTakes.continue"),
              icon: Eye,
              primary: true,
              onClick: (row) => setCountingTake(row),
            },
          ]}
        />
      )}

      {/* ── Sheets & dialogs ── */}
      <InventoryItemSheet
        item={itemSheet?.item ?? null}
        categories={categories}
        open={!!itemSheet}
        onOpenChange={(open) => !open && setItemSheet(null)}
        onSaved={bump}
      />
      <ItemDetailSheet
        item={itemDetail}
        canManage={canManage}
        open={!!itemDetail}
        onOpenChange={(open) => !open && setItemDetail(null)}
        onAction={(kind, item) => setMovementSheet({ kind, item })}
        onEdit={(item) => setItemSheet({ item })}
        onChanged={bump}
      />
      <MovementSheet
        kind={movementSheet?.kind ?? "receive"}
        item={movementSheet?.item}
        open={!!movementSheet}
        onOpenChange={(open) => !open && setMovementSheet(null)}
        onSaved={bump}
      />
      <RequestItemsSheet
        requisition={requestSheet?.requisition}
        open={!!requestSheet}
        onOpenChange={(open) => !open && setRequestSheet(null)}
        onSaved={bump}
      />
      <RequisitionDetailSheet
        requisition={requisitionDetail}
        canApprove={canApprove}
        canManage={canManage}
        currentUserId={user?.id ?? null}
        open={!!requisitionDetail}
        onOpenChange={(open) => !open && setRequisitionDetail(null)}
        onChanged={bump}
        onEdit={(requisition) => setRequestSheet({ requisition })}
      />
      <PurchaseOrderSheet
        order={poSheet?.order ?? null}
        open={!!poSheet}
        onOpenChange={(open) => !open && setPoSheet(null)}
        onSaved={bump}
      />
      <PurchaseOrderDetailSheet
        order={poDetail}
        canApprove={canApprove}
        canManage={canManage}
        currentUserId={user?.id ?? null}
        open={!!poDetail}
        onOpenChange={(open) => !open && setPoDetail(null)}
        onChanged={bump}
        onEdit={(order) => setPoSheet({ order })}
      />
      <IssueTextbooksSheet open={issueBooksOpen} onOpenChange={setIssueBooksOpen} onSaved={bump} />
      <StartStockTakeDialog
        categories={categories}
        open={startTakeOpen}
        onOpenChange={setStartTakeOpen}
        onStarted={(stockTake) => {
          bump()
          setCountingTake(stockTake)
        }}
      />
      <StockTakeCountSheet
        stockTake={countingTake}
        canManage={canManage}
        open={!!countingTake}
        onOpenChange={(open) => !open && setCountingTake(null)}
        onChanged={bump}
      />
      <InventoryCategoriesDialog
        categories={categories}
        open={categoriesOpen}
        onOpenChange={setCategoriesOpen}
        onChanged={bump}
      />

      {/* ⌘K asset-tag deep link lands straight on the unit. */}
      <AssetUnitSheet
        unit={deepLinkUnit}
        open={!!deepLinkUnit}
        onOpenChange={(open) => !open && setDeepLinkUnit(null)}
        onChanged={bump}
      />

      {/* Lost-book confirmation: the family is notified, so ask first. */}
      <Dialog open={!!losingLoan} onOpenChange={(open) => !open && setLosingLoan(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("textbooks.lostTitle")}</DialogTitle>
            <DialogDescription>{t("textbooks.lostBody")}</DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="lost-note">{t("textbooks.lostNoteLabel")}</Label>
            <Input id="lost-note" value={lostNote} onChange={(e) => setLostNote(e.target.value)} />
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setLosingLoan(null)}
              disabled={loanBusy}
            >
              {tc("actions.cancel")}
            </Button>
            <Button type="button" variant="destructive" loading={loanBusy} onClick={markLoanLost}>
              {t("textbooks.markLost")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {confirmDialog}
      {bulkDialog}
    </div>
  )
}
