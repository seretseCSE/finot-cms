"use client"

import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { InventoryItemPicker } from "@/components/inventory/item-picker"
import { AsyncCombobox, type AsyncComboboxOption } from "@/components/ui/async-combobox"
import { Badge } from "@/components/ui/badge"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
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
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type {
  AssetCondition,
  AssetHolderOption,
  AssetHolderType,
  AssetStatus,
  AssetUnit,
  InventoryItem,
} from "@/lib/types"
import { cn } from "@/lib/utils"

export const ASSET_STATUS_TONE: Record<AssetStatus, string> = {
  in_store: "border-success/30 bg-success/10 text-success",
  assigned: "border-info/30 bg-info/10 text-info",
  under_repair: "border-warning/30 bg-warning/10 text-warning",
  lost: "border-destructive/30 bg-destructive/10 text-destructive",
  disposed: "border-border bg-muted text-muted-foreground",
}

const CONDITIONS: AssetCondition[] = ["new", "good", "fair", "poor", "damaged"]
const HOLDER_TYPES: AssetHolderType[] = ["employee", "student", "room", "section"]

/** Bulk-register N tagged units of an asset item. */
export function RegisterAssetsSheet({
  open,
  onOpenChange,
  onSaved,
  presetItem = null,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
  /** Locks the item when opened from that item's own detail view. */
  presetItem?: InventoryItem | null
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()

  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [item, setItem] = useState<InventoryItem | null>(null)
  const [quantity, setQuantity] = useState("1")
  const [serials, setSerials] = useState<string[]>([])
  const [condition, setCondition] = useState<AssetCondition>("good")
  const [unitCost, setUnitCost] = useState("")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setItem(presetItem)
    setQuantity("1")
    setSerials([])
    setCondition("good")
    setUnitCost("")
    setBranchId(null)
    setBranchError(null)
  }, [open, presetItem])

  const count = Math.min(Math.max(Number(quantity) || 0, 0), 100)

  async function submit() {
    if (needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }
    setBusy(true)
    try {
      await apiFetch("/inventory/assets", {
        method: "POST",
        body: {
          ...(branchId != null ? { branch_id: branchId } : {}),
          inventory_item_id: item?.id ?? null,
          quantity: count,
          serial_numbers: serials.slice(0, count).map((s) => s || null),
          condition,
          unit_cost: unitCost === "" ? null : Number(unitCost),
        },
      })
      toast.success(t("assets.registered"))
      onSaved()
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("assets.registerTitle")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <p className="rounded-xl bg-muted p-3 text-xs text-muted-foreground">
            {t("assets.registerHelp")}
          </p>

          {needsBranch && (
            <BranchField
              value={branchId}
              onChange={(id) => {
                setBranchId(id)
                setBranchError(null)
              }}
              error={branchError}
            />
          )}

          <div className="space-y-1.5">
            <Label>{t("assets.itemLabel")}</Label>
            {presetItem ? (
              <p className="rounded-xl border bg-background px-3 py-2.5 text-sm font-medium">
                {presetItem.name}
              </p>
            ) : (
              <InventoryItemPicker value={item} onChange={setItem} branchId={branchId} assetsOnly />
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="au-qty">{t("assets.quantityLabel")}</Label>
              <Input
                id="au-qty"
                type="number"
                inputMode="numeric"
                min={1}
                max={100}
                className="no-spinner"
                value={quantity}
                onChange={(e) => setQuantity(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label>{t("assets.condition")}</Label>
              <Select value={condition} onValueChange={(v) => setCondition(v as AssetCondition)}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CONDITIONS.map((value) => (
                    <SelectItem key={value} value={value}>
                      {t(`assets.conditions.${value}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="au-cost">{t("assets.unitCost")}</Label>
            <Input
              id="au-cost"
              type="number"
              inputMode="decimal"
              min={0}
              className="no-spinner"
              value={unitCost}
              onChange={(e) => setUnitCost(e.target.value)}
            />
          </div>

          {count > 0 && count <= 20 && (
            <div className="space-y-1.5">
              <Label>{t("assets.serialsLabel")}</Label>
              {Array.from({ length: count }).map((_, i) => (
                <Input
                  key={i}
                  placeholder={t("assets.serialPlaceholder").replace("{n}", String(i + 1))}
                  value={serials[i] ?? ""}
                  onChange={(e) =>
                    setSerials((s) => {
                      const next = [...s]
                      next[i] = e.target.value
                      return next
                    })
                  }
                />
              ))}
            </div>
          )}
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
            disabled={busy}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            type="button"
            className="h-11 flex-1"
            loading={busy}
            disabled={!item || count < 1}
            onClick={submit}
          >
            {t("assets.register")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

/** Scoped holder search over /inventory/holders (never the HR registers). */
export function HolderPicker({
  type,
  branchId,
  value,
  onChange,
}: {
  type: AssetHolderType
  branchId: number | null
  value: AssetHolderOption | null
  onChange: (option: AssetHolderOption | null) => void
}) {
  const { t } = useTranslation("inventory")

  const fetcher = useCallback(
    async (query: string, signal: AbortSignal): Promise<AsyncComboboxOption[]> => {
      const params = new URLSearchParams({ type, search: query })
      if (branchId != null) params.set("branch_id", String(branchId))
      const res = await apiFetch<{ data: AssetHolderOption[] }>(`/inventory/holders?${params}`, {
        signal,
      })
      return res.data.map((row) => ({
        value: String(row.id),
        label: row.label,
        description: row.sublabel ?? undefined,
        meta: row,
      }))
    },
    [type, branchId]
  )

  return (
    <AsyncCombobox
      value={value ? { value: String(value.id), label: value.label } : null}
      onChange={(option) => onChange((option?.meta as AssetHolderOption | undefined) ?? null)}
      fetcher={fetcher}
      minChars={0}
      placeholder={t("assets.holderPlaceholder")}
      searchPlaceholder={t("assets.holderPlaceholder")}
    />
  )
}

/**
 * One asset unit, full lifecycle: assign to a holder, take it back with a
 * condition, send to repair, mark lost/found, dispose — plus quick edits.
 */
export function AssetUnitSheet({
  unit,
  open,
  onOpenChange,
  onChanged,
}: {
  unit: AssetUnit | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")

  const [detail, setDetail] = useState<AssetUnit | null>(null)
  const [holderType, setHolderType] = useState<AssetHolderType>("employee")
  const [holder, setHolder] = useState<AssetHolderOption | null>(null)
  const [assignNote, setAssignNote] = useState("")
  const [returnCondition, setReturnCondition] = useState<AssetCondition | "">("")
  const [serial, setSerial] = useState("")
  const [condition, setCondition] = useState<AssetCondition>("good")
  const [busy, setBusy] = useState(false)
  const [confirm, setConfirm] = useState<"lost" | "disposed" | null>(null)

  useEffect(() => {
    if (!open || !unit) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setDetail(unit)
    setHolderType("employee")
    setHolder(null)
    setAssignNote("")
    setReturnCondition("")
    setSerial(unit.serial_number ?? "")
    setCondition(unit.condition)
    setConfirm(null)
  }, [open, unit])

  if (!detail) return null

  async function act(method: "POST" | "PUT", path: string, body: Record<string, unknown>, success: string) {
    setBusy(true)
    try {
      const res = await apiFetch<{ data: AssetUnit }>(path, { method, body })
      toast.success(success)
      setDetail(res.data)
      onChanged()
      setConfirm(null)
      return true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      return false
    } finally {
      setBusy(false)
    }
  }

  const base = `/inventory/assets/${detail.id}`
  const isInStore = detail.status === "in_store"
  const isAssigned = detail.status === "assigned"
  const isRepair = detail.status === "under_repair"
  const isLost = detail.status === "lost"

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle className="flex items-center gap-2">
            <span className="font-mono">{detail.tag}</span>
            <Badge variant="outline" className={cn("rounded-full", ASSET_STATUS_TONE[detail.status])}>
              {t(`assets.statuses.${detail.status}`)}
            </Badge>
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <div className="rounded-xl border p-3 text-sm">
            <p className="font-medium">{detail.item_name}</p>
            <p className="text-xs text-muted-foreground">
              {[
                detail.serial_number,
                t(`assets.conditions.${detail.condition}`),
                detail.holder?.label
                  ? `${t("assets.holder")}: ${detail.holder.label}`
                  : null,
              ]
                .filter(Boolean)
                .join(" · ")}
            </p>
            {detail.note && <p className="mt-1 text-xs text-muted-foreground">{detail.note}</p>}
          </div>

          {/* Quick edit: serial + condition */}
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="au-serial">{t("assets.serial")}</Label>
              <Input id="au-serial" value={serial} onChange={(e) => setSerial(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>{t("assets.condition")}</Label>
              <Select value={condition} onValueChange={(v) => setCondition(v as AssetCondition)}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CONDITIONS.map((value) => (
                    <SelectItem key={value} value={value}>
                      {t(`assets.conditions.${value}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <Button
            type="button"
            variant="outline"
            className="w-full"
            loading={busy}
            onClick={() =>
              act("PUT", base, { serial_number: serial || null, condition }, t("assets.saved"))
            }
          >
            {tc("actions.save")}
          </Button>

          {/* Assign */}
          {isInStore && (
            <div className="space-y-3 rounded-xl border p-3">
              <p className="text-sm font-medium">{t("assets.assignTitle")}</p>
              <div className="space-y-1.5">
                <Label>{t("assets.holderTypeLabel")}</Label>
                <div className="grid grid-cols-4 gap-1.5">
                  {HOLDER_TYPES.map((value) => (
                    <button
                      key={value}
                      type="button"
                      onClick={() => {
                        setHolderType(value)
                        setHolder(null)
                      }}
                      className={cn(
                        "pressable min-h-10 rounded-xl border px-1 text-xs font-medium transition-colors",
                        holderType === value
                          ? "border-primary bg-primary/5 text-primary"
                          : "border-border bg-background text-muted-foreground hover:bg-muted"
                      )}
                      aria-pressed={holderType === value}
                    >
                      {t(`assets.holderTypes.${value}`)}
                    </button>
                  ))}
                </div>
              </div>
              <div className="space-y-1.5">
                <Label>{t("assets.holderLabel")}</Label>
                <HolderPicker
                  type={holderType}
                  branchId={detail.branch_id}
                  value={holder}
                  onChange={setHolder}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="au-note">{t("movement.noteLabel")}</Label>
                <Input
                  id="au-note"
                  value={assignNote}
                  onChange={(e) => setAssignNote(e.target.value)}
                />
              </div>
              <Button
                type="button"
                className="w-full"
                loading={busy}
                disabled={!holder}
                onClick={() =>
                  act(
                    "POST",
                    `${base}/assign`,
                    { holder_type: holderType, holder_id: holder!.id, note: assignNote || null },
                    t("assets.assigned")
                  )
                }
              >
                {t("assets.assign")}
              </Button>
            </div>
          )}

          {/* Return */}
          {isAssigned && (
            <div className="space-y-3 rounded-xl border p-3">
              <p className="text-sm font-medium">{t("assets.returnTitle")}</p>
              <div className="space-y-1.5">
                <Label>{t("assets.returnConditionLabel")}</Label>
                <Select
                  value={returnCondition}
                  onValueChange={(v) => setReturnCondition(v as AssetCondition)}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t(`assets.conditions.${detail.condition}`)} />
                  </SelectTrigger>
                  <SelectContent>
                    {CONDITIONS.map((value) => (
                      <SelectItem key={value} value={value}>
                        {t(`assets.conditions.${value}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <Button
                type="button"
                className="w-full"
                loading={busy}
                onClick={() =>
                  act(
                    "POST",
                    `${base}/return`,
                    returnCondition ? { condition: returnCondition } : {},
                    t("assets.returned")
                  )
                }
              >
                {t("assets.return")}
              </Button>
            </div>
          )}

          {/* Lifecycle actions */}
          {detail.status !== "disposed" && (
            <div className="flex flex-wrap gap-2">
              {isInStore && (
                <Button
                  type="button"
                  variant="outline"
                  disabled={busy}
                  onClick={() =>
                    act("POST", `${base}/status`, { status: "under_repair" }, t("assets.statusChanged"))
                  }
                >
                  {t("assets.markRepair")}
                </Button>
              )}
              {isRepair && (
                <Button
                  type="button"
                  variant="outline"
                  disabled={busy}
                  onClick={() =>
                    act("POST", `${base}/status`, { status: "in_store" }, t("assets.statusChanged"))
                  }
                >
                  {t("assets.backFromRepair")}
                </Button>
              )}
              {isLost ? (
                <Button
                  type="button"
                  variant="outline"
                  disabled={busy}
                  onClick={() =>
                    act("POST", `${base}/status`, { status: "in_store" }, t("assets.statusChanged"))
                  }
                >
                  {t("assets.found")}
                </Button>
              ) : (
                <Button
                  type="button"
                  variant="outline"
                  className="text-destructive"
                  disabled={busy}
                  onClick={() => setConfirm("lost")}
                >
                  {t("assets.markLost")}
                </Button>
              )}
              {(isInStore || isRepair || isLost) && (
                <Button
                  type="button"
                  variant="outline"
                  className="text-destructive"
                  disabled={busy}
                  onClick={() => setConfirm("disposed")}
                >
                  {t("assets.dispose")}
                </Button>
              )}
            </div>
          )}
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
        </ResponsiveSheetFooter>

        <Dialog open={confirm !== null} onOpenChange={(open) => !open && setConfirm(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>
                {confirm === "lost" ? t("assets.markLostTitle") : t("assets.disposeTitle")}
              </DialogTitle>
              <DialogDescription>
                {confirm === "lost" ? t("assets.markLostBody") : t("assets.disposeBody")}
              </DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setConfirm(null)}
                disabled={busy}
              >
                {tc("actions.cancel")}
              </Button>
              <Button
                type="button"
                variant="destructive"
                loading={busy}
                onClick={() =>
                  act("POST", `${base}/status`, { status: confirm }, t("assets.statusChanged"))
                }
              >
                {confirm === "lost" ? t("assets.markLost") : t("assets.dispose")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
