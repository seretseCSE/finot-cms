"use client"

import {
  AlertTriangle,
  ArrowRight,
  CheckCircle2,
  ClipboardCheck,
  ClipboardList,
  PackageMinus,
  PackagePlus,
  Plus,
  ShoppingCart,
  UserSearch,
} from "lucide-react"
import { useEffect, useState } from "react"

import { ASSET_STATUS_TONE, HolderPicker } from "@/components/inventory/asset-sheets"
import { REQUISITION_TONE } from "@/components/inventory/requisition-sheets"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { apiFetch } from "@/lib/api"
import { fmtDateTime } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type {
  AssetHolderOption,
  AssetHolderType,
  AssetUnit,
  InventoryItem,
  InventoryStats,
  Paginated,
  Requisition,
  StockMovement,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const HOLDER_TYPES: AssetHolderType[] = ["employee", "student", "room", "section"]

/** One big tappable verb — the storekeeper's home screen is these cards. */
function ActionCard({
  icon: Icon,
  label,
  hint,
  onClick,
}: {
  icon: typeof PackagePlus
  label: string
  hint: string
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="pressable flex min-h-24 flex-col items-start justify-between gap-2 rounded-2xl border bg-card p-4 text-left shadow-xs transition-colors hover:border-primary/40 hover:bg-primary/5"
    >
      <span className="flex size-9 items-center justify-center rounded-xl bg-primary/10">
        <Icon className="size-5 text-primary" strokeWidth={1.75} />
      </span>
      <span>
        <span className="block text-sm font-semibold">{label}</span>
        <span className="block text-xs text-muted-foreground">{hint}</span>
      </span>
    </button>
  )
}

/**
 * The guided home of the store: the four verbs as big cards, what needs
 * attention (low stock, pending requests), recent ledger activity, and a
 * first-run checklist when the store is still empty.
 */
export function InventoryOverview({
  stats,
  canManage,
  canApprove,
  canRequest,
  refreshKey,
  onAddToStore,
  onReceive,
  onIssue,
  onStartCount,
  onRequestItems,
  onOpenItem,
  onOpenRequisition,
  onGoToTab,
}: {
  stats: InventoryStats | null
  canManage: boolean
  canApprove: boolean
  canRequest: boolean
  refreshKey: string
  onAddToStore: () => void
  onReceive: () => void
  onIssue: () => void
  onStartCount: () => void
  onRequestItems: () => void
  onOpenItem: (item: InventoryItem) => void
  onOpenRequisition: (requisition: Requisition) => void
  onGoToTab: (tab: string) => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")

  const [lowStock, setLowStock] = useState<InventoryItem[] | null>(null)
  const [pending, setPending] = useState<Requisition[] | null>(null)
  const [activity, setActivity] = useState<StockMovement[] | null>(null)
  const [holderOpen, setHolderOpen] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<Paginated<InventoryItem>>("/inventory/items?low_stock=1&per_page=5")
      .then((res) => !cancelled && setLowStock(res.data))
      .catch(() => !cancelled && setLowStock([]))
    apiFetch<Paginated<Requisition>>("/inventory/requisitions?status=pending&per_page=5")
      .then((res) => !cancelled && setPending(res.data))
      .catch(() => !cancelled && setPending([]))
    apiFetch<Paginated<StockMovement>>("/inventory/movements?per_page=8")
      .then((res) => !cancelled && setActivity(res.data))
      .catch(() => !cancelled && setActivity([]))
    return () => {
      cancelled = true
    }
  }, [refreshKey])

  const empty = stats !== null && stats.item_count === 0

  return (
    <div className="page-gutter space-y-6">
      {/* ── First-run checklist ── */}
      {empty && canManage && (
        <div className="rounded-2xl border border-primary/20 bg-primary/5 p-4">
          <p className="font-semibold">{t("overview.setupTitle")}</p>
          <p className="mt-1 text-sm text-muted-foreground">{t("overview.setupBody")}</p>
          <ol className="mt-3 space-y-2 text-sm">
            {[1, 2, 3].map((n) => (
              <li key={n} className="flex items-start gap-2">
                <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary">
                  {n}
                </span>
                {t(`overview.setupStep${n}`)}
              </li>
            ))}
          </ol>
          <Button className="mt-4 h-11" onClick={onAddToStore}>
            <Plus className="size-4" />
            {t("overview.setupCta")}
          </Button>
        </div>
      )}

      {/* ── The verbs ── */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {canManage ? (
          <>
            <ActionCard icon={Plus} label={t("items.addToStore")} hint={t("overview.addHint")} onClick={onAddToStore} />
            <ActionCard icon={PackagePlus} label={t("quick.receive")} hint={t("overview.receiveHint")} onClick={onReceive} />
            <ActionCard icon={PackageMinus} label={t("quick.issue")} hint={t("overview.issueHint")} onClick={onIssue} />
            <ActionCard icon={ClipboardCheck} label={t("quick.count")} hint={t("overview.countHint")} onClick={onStartCount} />
          </>
        ) : canRequest ? (
          <ActionCard
            icon={ShoppingCart}
            label={t("requisitions.new")}
            hint={t("overview.requestHint")}
            onClick={onRequestItems}
          />
        ) : null}
      </div>

      {/* ── Needs attention ── */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section className="rounded-2xl border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <p className="flex items-center gap-1.5 text-sm font-semibold">
              <AlertTriangle className="size-4 text-warning" />
              {t("stats.lowStock")}
              {stats != null && stats.low_stock_count > 0 && (
                <Badge variant="outline" className="rounded-full border-warning/30 bg-warning/10 text-warning">
                  {stats.low_stock_count}
                </Badge>
              )}
            </p>
            <Button variant="ghost" size="sm" onClick={() => onGoToTab("items")}>
              {t("overview.viewAll")}
              <ArrowRight className="size-3.5" />
            </Button>
          </div>
          <div className="mt-2 space-y-1.5">
            {lowStock === null ? (
              <p className="p-2 text-sm text-muted-foreground">{tc("states.loading")}</p>
            ) : lowStock.length === 0 ? (
              <p className="flex items-center gap-1.5 p-2 text-sm text-muted-foreground">
                <CheckCircle2 className="size-4 text-success" />
                {t("overview.stockHealthy")}
              </p>
            ) : (
              lowStock.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => onOpenItem(item)}
                  className="pressable flex w-full items-center justify-between gap-2 rounded-xl px-2 py-1.5 text-left hover:bg-muted"
                >
                  <span className="min-w-0 truncate text-sm">{item.name}</span>
                  <span className="shrink-0 text-sm font-medium text-warning tabular-nums">
                    {Number(item.quantity_on_hand ?? 0)} {t(`units.${item.unit}`)}
                  </span>
                </button>
              ))
            )}
          </div>
        </section>

        <section className="rounded-2xl border bg-card p-4 shadow-xs">
          <div className="flex items-center justify-between">
            <p className="flex items-center gap-1.5 text-sm font-semibold">
              <ClipboardList className="size-4 text-info" />
              {t("stats.pendingRequisitions")}
              {stats != null && stats.pending_requisitions > 0 && (
                <Badge variant="outline" className="rounded-full border-info/30 bg-info/10 text-info">
                  {stats.pending_requisitions}
                </Badge>
              )}
            </p>
            <Button variant="ghost" size="sm" onClick={() => onGoToTab("requisitions")}>
              {t("overview.viewAll")}
              <ArrowRight className="size-3.5" />
            </Button>
          </div>
          <div className="mt-2 space-y-1.5">
            {pending === null ? (
              <p className="p-2 text-sm text-muted-foreground">{tc("states.loading")}</p>
            ) : pending.length === 0 ? (
              <p className="flex items-center gap-1.5 p-2 text-sm text-muted-foreground">
                <CheckCircle2 className="size-4 text-success" />
                {t("overview.noPending")}
              </p>
            ) : (
              pending.map((requisition) => (
                <button
                  key={requisition.id}
                  type="button"
                  onClick={() => onOpenRequisition(requisition)}
                  className="pressable flex w-full items-center justify-between gap-2 rounded-xl px-2 py-1.5 text-left hover:bg-muted"
                >
                  <span className="min-w-0">
                    <span className="block truncate text-sm">{requisition.requested_by_name}</span>
                    <span className="block truncate text-xs text-muted-foreground">
                      {requisition.purpose ?? "—"}
                    </span>
                  </span>
                  <Badge
                    variant="outline"
                    className={cn("shrink-0 rounded-full", REQUISITION_TONE.pending)}
                  >
                    {t("requisitions.statuses.pending")}
                  </Badge>
                </button>
              ))
            )}
          </div>
        </section>
      </div>

      {/* ── Recent activity + clearance ── */}
      <section className="rounded-2xl border bg-card p-4 shadow-xs">
        <div className="flex items-center justify-between">
          <p className="text-sm font-semibold">{t("overview.recentActivity")}</p>
          {canManage && (
            <Button variant="ghost" size="sm" onClick={() => setHolderOpen(true)}>
              <UserSearch className="size-4" />
              {t("overview.whoHolds")}
            </Button>
          )}
        </div>
        <div className="mt-2 space-y-1.5">
          {activity === null ? (
            <p className="p-2 text-sm text-muted-foreground">{tc("states.loading")}</p>
          ) : activity.length === 0 ? (
            <p className="p-2 text-sm text-muted-foreground">{t("movement.emptyBody")}</p>
          ) : (
            activity.map((movement) => {
              const change = Number(movement.quantity_change)
              return (
                <div
                  key={movement.id}
                  className="flex items-center justify-between gap-2 rounded-xl px-2 py-1.5"
                >
                  <span className="min-w-0">
                    <span className="block truncate text-sm">
                      {movement.item_name}
                      <span className="text-xs text-muted-foreground">
                        {" · "}
                        {t(`movement.types.${movement.type}`)}
                        {movement.recipient ? ` · ${movement.recipient}` : ""}
                        {movement.supplier_name ? ` · ${movement.supplier_name}` : ""}
                      </span>
                    </span>
                    <span className="block text-xs text-muted-foreground">
                      {fmtDateTime(movement.created_at)}
                    </span>
                  </span>
                  <span
                    className={cn(
                      "shrink-0 text-sm font-medium tabular-nums",
                      change > 0 ? "text-success" : "text-destructive"
                    )}
                  >
                    {change > 0 ? `+${change}` : change}
                  </span>
                </div>
              )
            })
          )}
        </div>
      </section>

      {canManage && (
        <WhoHoldsWhatDialog open={holderOpen} onOpenChange={setHolderOpen} canApprove={canApprove} />
      )}
    </div>
  )
}

/**
 * The clearance question as a dialog: pick a person (or room/section) and
 * see every asset unit still in their hands — the offboarding checklist.
 */
function WhoHoldsWhatDialog({
  open,
  onOpenChange,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  canApprove: boolean
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()

  const [branchId, setBranchId] = useState<number | null>(null)
  const [holderType, setHolderType] = useState<AssetHolderType>("employee")
  const [holder, setHolder] = useState<AssetHolderOption | null>(null)
  const [held, setHeld] = useState<AssetUnit[] | null>(null)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setHolder(null)
    setHeld(null)
    setBranchId(null)
  }, [open])

  useEffect(() => {
    if (!holder) {
      return
    }
    let cancelled = false
    apiFetch<Paginated<AssetUnit>>(
      `/inventory/assets?holder_type=${holderType}&holder_id=${holder.id}&per_page=100`
    )
      .then((res) => !cancelled && setHeld(res.data))
      .catch(() => !cancelled && setHeld([]))
    return () => {
      cancelled = true
    }
  }, [holder, holderType])

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t("overview.whoHolds")}</DialogTitle>
          <DialogDescription>{t("overview.whoHoldsBody")}</DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          {needsBranch && <BranchField value={branchId} onChange={setBranchId} error={null} />}
          <div className="space-y-1.5">
            <Label>{t("assets.holderTypeLabel")}</Label>
            <Select
              value={holderType}
              onValueChange={(v) => {
                setHolderType(v as AssetHolderType)
                setHolder(null)
                setHeld(null)
              }}
            >
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {HOLDER_TYPES.map((type) => (
                  <SelectItem key={type} value={type}>
                    {t(`assets.holderTypes.${type}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>{t("assets.holderLabel")}</Label>
            <HolderPicker type={holderType} branchId={branchId} value={holder} onChange={setHolder} />
          </div>

          {holder && (
            <div className="space-y-1.5">
              {held === null ? (
                <p className="p-2 text-sm text-muted-foreground">{tc("states.loading")}</p>
              ) : held.length === 0 ? (
                <p className="flex items-center gap-1.5 rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                  <CheckCircle2 className="size-4 text-success" />
                  {t("overview.holdsNothing")}
                </p>
              ) : (
                held.map((unit) => (
                  <div
                    key={unit.id}
                    className="flex items-center justify-between gap-2 rounded-xl border px-3 py-2"
                  >
                    <span className="min-w-0">
                      <span className="block font-mono text-sm font-medium">{unit.tag}</span>
                      <span className="block truncate text-xs text-muted-foreground">
                        {unit.item_name}
                      </span>
                    </span>
                    <Badge
                      variant="outline"
                      className={cn("shrink-0 rounded-full", ASSET_STATUS_TONE[unit.status])}
                    >
                      {t(`assets.statuses.${unit.status}`)}
                    </Badge>
                  </div>
                ))
              )}
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  )
}
