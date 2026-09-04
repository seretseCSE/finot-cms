"use client"

import { PackageMinus, PackagePlus, Pencil, Plus } from "lucide-react"
import { useEffect, useState } from "react"

import { ASSET_STATUS_TONE, AssetUnitSheet, RegisterAssetsSheet } from "@/components/inventory/asset-sheets"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { apiFetch } from "@/lib/api"
import { fmtDateTime } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type { AssetUnit, InventoryItem, Paginated, StockMovement, StockMovementType } from "@/lib/types"
import { cn } from "@/lib/utils"

const MOVEMENT_TONE: Record<StockMovementType, string> = {
  receive: "text-success",
  issue: "text-info",
  return: "text-primary",
  adjustment: "text-warning",
  write_off: "text-destructive",
}

/**
 * One item, everything about it in one place: stock on hand, quick
 * receive/issue, its bin card, and — for asset items — the tagged units
 * with the full custody flow. This drill-in replaced the separate Assets
 * and Ledger tabs: the item is the thing, not the bookkeeping.
 */
export function ItemDetailSheet({
  item,
  canManage,
  open,
  onOpenChange,
  onAction,
  onEdit,
  onChanged,
}: {
  item: InventoryItem | null
  canManage: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Open the receive/issue movement sheet preset to this item. */
  onAction: (kind: "receive" | "issue", item: InventoryItem) => void
  onEdit: (item: InventoryItem) => void
  onChanged: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")

  const [movements, setMovements] = useState<StockMovement[] | null>(null)
  const [movementPages, setMovementPages] = useState(1)
  const [movementsTotal, setMovementsTotal] = useState(0)
  const [units, setUnits] = useState<AssetUnit[] | null>(null)
  const [unitDetail, setUnitDetail] = useState<AssetUnit | null>(null)
  const [registerOpen, setRegisterOpen] = useState(false)
  const [refresh, setRefresh] = useState(0)

  useEffect(() => {
    if (!open || !item) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale detail
    setMovements(null)
    setUnits(null)
    setMovementPages(1)

    apiFetch<Paginated<StockMovement>>(
      `/inventory/movements?inventory_item_id=${item.id}&per_page=10`
    )
      .then((res) => {
        if (cancelled) return
        setMovements(res.data)
        setMovementsTotal(res.meta.total)
      })
      .catch(() => !cancelled && setMovements([]))

    if (item.is_asset) {
      apiFetch<Paginated<AssetUnit>>(`/inventory/assets?inventory_item_id=${item.id}&per_page=100`)
        .then((res) => !cancelled && setUnits(res.data))
        .catch(() => !cancelled && setUnits([]))
    }

    return () => {
      cancelled = true
    }
  }, [open, item, refresh])

  async function loadMoreMovements() {
    if (!item) return
    const next = movementPages + 1
    const res = await apiFetch<Paginated<StockMovement>>(
      `/inventory/movements?inventory_item_id=${item.id}&per_page=10&page=${next}`
    )
    setMovements((rows) => [...(rows ?? []), ...res.data])
    setMovementPages(next)
  }

  if (!item) return null

  const qty = Number(item.quantity_on_hand ?? 0)
  const reorder = item.reorder_level != null ? Number(item.reorder_level) : null
  const low = reorder != null && qty <= reorder

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle className="flex items-center gap-2">
            <span className="truncate">{item.name}</span>
            {item.is_asset && (
              <Badge variant="outline" className="shrink-0 rounded-full text-[10px]">
                {t("items.assetBadge")}
              </Badge>
            )}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          {/* Stock summary + the two verbs */}
          <div className="flex items-center justify-between gap-3 rounded-xl border p-3">
            <div>
              <p className={cn("text-2xl font-semibold tabular-nums", qty <= 0 && "text-destructive", low && qty > 0 && "text-warning")}>
                {qty} <span className="text-sm font-normal text-muted-foreground">{t(`units.${item.unit}`)}</span>
              </p>
              <p className="text-xs text-muted-foreground">
                {[
                  item.category_name,
                  item.code,
                  reorder != null ? `${t("items.reorderLevel")}: ${reorder}` : null,
                ]
                  .filter(Boolean)
                  .join(" · ")}
              </p>
            </div>
            {canManage && (
              <div className="flex shrink-0 gap-1.5">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => onAction("receive", item)}
                >
                  <PackagePlus className="size-4" />
                  {t("quick.receive")}
                </Button>
                <Button variant="outline" size="sm" onClick={() => onAction("issue", item)}>
                  <PackageMinus className="size-4" />
                  {t("quick.issue")}
                </Button>
              </div>
            )}
          </div>

          {/* Tagged units (asset items) */}
          {item.is_asset && (
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium">{t("assets.tab")}</p>
                {canManage && (
                  <Button variant="ghost" size="sm" onClick={() => setRegisterOpen(true)}>
                    <Plus className="size-4" />
                    {t("assets.register")}
                  </Button>
                )}
              </div>
              {units === null ? (
                <p className="p-2 text-sm text-muted-foreground">{tc("states.loading")}</p>
              ) : units.length === 0 ? (
                <p className="rounded-xl border border-dashed p-3 text-center text-sm text-muted-foreground">
                  {t("assets.emptyForItem")}
                </p>
              ) : (
                <div className="space-y-1.5">
                  {units.map((unit) => (
                    <button
                      key={unit.id}
                      type="button"
                      onClick={() => setUnitDetail(unit)}
                      className="pressable flex w-full items-center justify-between gap-2 rounded-xl border px-3 py-2 text-left hover:bg-muted"
                    >
                      <span className="min-w-0">
                        <span className="block font-mono text-sm font-medium">{unit.tag}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                          {[unit.serial_number, unit.holder?.label].filter(Boolean).join(" · ") ||
                            t("assets.inStoreNobody")}
                        </span>
                      </span>
                      <Badge
                        variant="outline"
                        className={cn("shrink-0 rounded-full", ASSET_STATUS_TONE[unit.status])}
                      >
                        {t(`assets.statuses.${unit.status}`)}
                      </Badge>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Bin card */}
          <div className="space-y-2">
            <p className="text-sm font-medium">{t("items.history")}</p>
            {movements === null ? (
              <p className="p-2 text-sm text-muted-foreground">{tc("states.loading")}</p>
            ) : movements.length === 0 ? (
              <p className="rounded-xl border border-dashed p-3 text-center text-sm text-muted-foreground">
                {t("movement.empty")}
              </p>
            ) : (
              <div className="space-y-1.5">
                {movements.map((movement) => {
                  const change = Number(movement.quantity_change)
                  return (
                    <div
                      key={movement.id}
                      className="flex items-center justify-between gap-2 rounded-xl border px-3 py-2"
                    >
                      <div className="min-w-0">
                        <p className="text-sm">
                          <span className={cn("font-medium", MOVEMENT_TONE[movement.type])}>
                            {t(`movement.types.${movement.type}`)}
                          </span>{" "}
                          <span className="text-xs text-muted-foreground">
                            {[movement.supplier_name, movement.recipient, movement.note]
                              .filter(Boolean)
                              .join(" · ")}
                          </span>
                        </p>
                        <p className="text-xs text-muted-foreground">
                          {fmtDateTime(movement.created_at)}
                          {movement.created_by_name ? ` · ${movement.created_by_name}` : ""}
                        </p>
                      </div>
                      <div className="shrink-0 text-right">
                        <p
                          className={cn(
                            "text-sm font-medium tabular-nums",
                            change > 0 ? "text-success" : "text-destructive"
                          )}
                        >
                          {change > 0 ? `+${change}` : change}
                        </p>
                        <p className="text-xs text-muted-foreground tabular-nums">
                          {t("movement.after")}: {Number(movement.quantity_after)}
                        </p>
                      </div>
                    </div>
                  )
                })}
                {movements.length < movementsTotal && (
                  <Button variant="ghost" className="w-full" onClick={loadMoreMovements}>
                    {t("items.moreHistory")}
                  </Button>
                )}
              </div>
            )}
          </div>
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.close")}
          </Button>
          {canManage && (
            <Button
              type="button"
              className="h-11 flex-1"
              onClick={() => {
                onOpenChange(false)
                onEdit(item)
              }}
            >
              <Pencil className="size-4" />
              {tc("actions.edit")}
            </Button>
          )}
        </ResponsiveSheetFooter>

        <AssetUnitSheet
          unit={unitDetail}
          open={!!unitDetail}
          onOpenChange={(o) => !o && setUnitDetail(null)}
          onChanged={() => {
            setRefresh((n) => n + 1)
            onChanged()
          }}
        />
        <RegisterAssetsSheet
          open={registerOpen}
          onOpenChange={setRegisterOpen}
          presetItem={item}
          onSaved={() => {
            setRefresh((n) => n + 1)
            onChanged()
          }}
        />
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
