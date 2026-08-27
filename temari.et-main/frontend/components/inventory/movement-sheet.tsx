"use client"

import { useEffect, useState } from "react"
import { toast } from "sonner"

import { InventoryItemPicker } from "@/components/inventory/item-picker"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { InventoryItem } from "@/lib/types"

export type MovementKind = "receive" | "issue" | "adjust" | "write_off"

const ENDPOINT: Record<MovementKind, string> = {
  receive: "/inventory/movements/receive",
  issue: "/inventory/movements/issue",
  adjust: "/inventory/movements/adjust",
  write_off: "/inventory/movements/write-off",
}

/**
 * The storekeeper's direct ledger actions in one sheet: receive (no PO
 * needed), issue to a named person, signed adjustment, write-off. Fields
 * follow the kind; the ledger enforces overdraw/reason rules server-side.
 */
export function MovementSheet({
  kind,
  item: presetItem,
  open,
  onOpenChange,
  onSaved,
}: {
  kind: MovementKind
  /** Preselects the item when opened from a row action. */
  item?: InventoryItem | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()

  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [item, setItem] = useState<InventoryItem | null>(null)
  const [quantity, setQuantity] = useState("")
  const [unitCost, setUnitCost] = useState("")
  const [supplier, setSupplier] = useState("")
  const [reference, setReference] = useState("")
  const [recipient, setRecipient] = useState("")
  const [note, setNote] = useState("")
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setItem(presetItem ?? null)
    setQuantity("")
    setUnitCost("")
    setSupplier("")
    setReference("")
    setRecipient("")
    setNote("")
    setErrors({})
    setBranchId(null)
    setBranchError(null)
  }, [open, presetItem])

  const title = {
    receive: t("movement.receiveTitle"),
    issue: t("movement.issueTitle"),
    adjust: t("movement.adjustTitle"),
    write_off: t("movement.writeOffTitle"),
  }[kind]

  const needsReason = kind === "adjust" || kind === "write_off"

  async function submit() {
    if (needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }
    setBusy(true)
    setErrors({})
    try {
      await apiFetch(ENDPOINT[kind], {
        method: "POST",
        body: {
          ...(branchId != null ? { branch_id: branchId } : {}),
          inventory_item_id: item?.id ?? null,
          quantity: quantity === "" ? null : Number(quantity),
          ...(kind === "receive"
            ? {
                unit_cost: unitCost === "" ? null : Number(unitCost),
                supplier_name: supplier || null,
                reference: reference || null,
              }
            : {}),
          ...(kind === "issue" ? { recipient: recipient || null } : {}),
          note: note || null,
        },
      })
      toast.success(
        {
          receive: t("movement.received"),
          issue: t("movement.issued"),
          adjust: t("movement.adjusted"),
          write_off: t("movement.writtenOff"),
        }[kind]
      )
      onSaved()
      onOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        const map: Record<string, string> = {}
        for (const [field, messages] of Object.entries(error.errors)) map[field] = messages[0]
        setErrors(map)
        if (Object.keys(map).length === 0) toast.error(error.message)
      } else {
        toast.error(tc("errors.generic"))
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{title}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
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
            <Label>{t("movement.itemLabel")}</Label>
            <InventoryItemPicker value={item} onChange={setItem} branchId={branchId} />
            {errors.inventory_item_id && (
              <p className="text-xs text-destructive">{errors.inventory_item_id}</p>
            )}
            {item && (
              <p className="text-xs text-muted-foreground">
                {t("items.onHand")}: {Number(item.quantity_on_hand ?? 0)} {t(`units.${item.unit}`)}
              </p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="mv-qty">{t("movement.quantityLabel")}</Label>
            <Input
              id="mv-qty"
              type="number"
              inputMode="decimal"
              className="no-spinner"
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
              min={kind === "adjust" ? undefined : 0}
            />
            {kind === "adjust" && (
              <p className="text-xs text-muted-foreground">{t("movement.adjustHelp")}</p>
            )}
            {errors.quantity && <p className="text-xs text-destructive">{errors.quantity}</p>}
          </div>

          {kind === "receive" && (
            <>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <Label htmlFor="mv-cost">{t("movement.unitCostLabel")}</Label>
                  <Input
                    id="mv-cost"
                    type="number"
                    inputMode="decimal"
                    min={0}
                    className="no-spinner"
                    value={unitCost}
                    onChange={(e) => setUnitCost(e.target.value)}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="mv-ref">{t("movement.referenceLabel")}</Label>
                  <Input
                    id="mv-ref"
                    placeholder={t("movement.referencePlaceholder")}
                    value={reference}
                    onChange={(e) => setReference(e.target.value)}
                  />
                </div>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="mv-supplier">{t("movement.supplierLabel")}</Label>
                <Input
                  id="mv-supplier"
                  placeholder={t("movement.supplierPlaceholder")}
                  value={supplier}
                  onChange={(e) => setSupplier(e.target.value)}
                />
              </div>
            </>
          )}

          {kind === "issue" && (
            <div className="space-y-1.5">
              <Label htmlFor="mv-recipient">{t("movement.recipientLabel")}</Label>
              <Input
                id="mv-recipient"
                placeholder={t("movement.recipientPlaceholder")}
                value={recipient}
                onChange={(e) => setRecipient(e.target.value)}
              />
              {errors.recipient && <p className="text-xs text-destructive">{errors.recipient}</p>}
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="mv-note">
              {needsReason ? t("movement.reasonLabel") : t("movement.noteLabel")}
            </Label>
            <Input
              id="mv-note"
              placeholder={needsReason ? t("movement.reasonPlaceholder") : undefined}
              value={note}
              onChange={(e) => setNote(e.target.value)}
            />
            {errors.note && <p className="text-xs text-destructive">{errors.note}</p>}
          </div>
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
          <Button type="button" className="h-11 flex-1" loading={busy} onClick={submit}>
            {tc("actions.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
