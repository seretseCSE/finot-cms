"use client"

import { Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { InventoryItemPicker } from "@/components/inventory/item-picker"
import { REQUISITION_TONE } from "@/components/inventory/requisition-sheets"
import { Badge } from "@/components/ui/badge"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
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
import { ApiError, apiFetch } from "@/lib/api"
import { addisToday, fmtDate, fmtDateTime } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type { InventoryItem, PurchaseOrder, PurchaseOrderStatus } from "@/lib/types"
import { cn } from "@/lib/utils"

export const PO_TONE: Record<PurchaseOrderStatus, string> = {
  pending: REQUISITION_TONE.pending,
  approved: REQUISITION_TONE.approved,
  declined: REQUISITION_TONE.declined,
  received: REQUISITION_TONE.issued,
  cancelled: REQUISITION_TONE.cancelled,
}

interface DraftLine {
  item: InventoryItem
  quantity: string
  unit_cost: string
}

/** Raise or edit a purchase order — supplier, expected date, ordered lines. */
export function PurchaseOrderSheet({
  order,
  open,
  onOpenChange,
  onSaved,
}: {
  order: PurchaseOrder | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()
  const isEdit = !!order

  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [supplier, setSupplier] = useState("")
  const [supplierPhone, setSupplierPhone] = useState("")
  const [expectedOn, setExpectedOn] = useState("")
  const [note, setNote] = useState("")
  const [lines, setLines] = useState<DraftLine[]>([])
  const [picking, setPicking] = useState<InventoryItem | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setSupplier(order?.supplier_name ?? "")
    setSupplierPhone(order?.supplier_phone ?? "")
    setExpectedOn(order?.expected_on ?? "")
    setNote(order?.note ?? "")
    setLines(
      (order?.items ?? []).map((line) => ({
        item: {
          id: line.inventory_item_id,
          name: line.item_name ?? "",
          unit: line.item_unit ?? "piece",
        } as InventoryItem,
        quantity: String(Number(line.quantity)),
        unit_cost: line.unit_cost != null ? String(Number(line.unit_cost)) : "",
      }))
    )
    setPicking(null)
    setBranchId(null)
    setBranchError(null)
  }, [open, order])

  async function submit() {
    if (!isEdit && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }
    setBusy(true)
    try {
      await apiFetch(isEdit ? `/inventory/purchase-orders/${order!.id}` : "/inventory/purchase-orders", {
        method: isEdit ? "PUT" : "POST",
        body: {
          ...(!isEdit && branchId != null ? { branch_id: branchId } : {}),
          supplier_name: supplier,
          supplier_phone: supplierPhone || null,
          expected_on: expectedOn || null,
          note: note || null,
          items: lines.map((l) => ({
            inventory_item_id: l.item.id,
            quantity: Number(l.quantity),
            unit_cost: l.unit_cost === "" ? null : Number(l.unit_cost),
          })),
        },
      })
      toast.success(isEdit ? t("po.saved") : t("po.submitted"))
      onSaved()
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(false)
    }
  }

  const total = lines.reduce(
    (sum, l) => sum + Number(l.quantity || 0) * Number(l.unit_cost || 0),
    0
  )

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("po.editTitle") : t("po.newTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <p className="rounded-xl bg-muted p-3 text-xs text-muted-foreground">
            {t("po.optionalNote")}
          </p>

          {!isEdit && needsBranch && (
            <BranchField
              value={branchId}
              onChange={(id) => {
                setBranchId(id)
                setBranchError(null)
              }}
              error={branchError}
            />
          )}

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="po-supplier">{t("po.supplierLabel")}</Label>
              <Input
                id="po-supplier"
                placeholder={t("po.supplierPlaceholder")}
                value={supplier}
                onChange={(e) => setSupplier(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="po-phone">{t("po.supplierPhoneLabel")}</Label>
              <Input
                id="po-phone"
                inputMode="tel"
                value={supplierPhone}
                onChange={(e) => setSupplierPhone(e.target.value)}
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>{t("po.expectedOnLabel")}</Label>
              <DatePicker value={expectedOn} onChange={(v) => setExpectedOn(v ?? "")} min={addisToday()} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="po-note">{t("po.noteLabel")}</Label>
              <Input id="po-note" value={note} onChange={(e) => setNote(e.target.value)} />
            </div>
          </div>

          <div className="space-y-2">
            <Label>{t("po.linesLabel")}</Label>
            {lines.map((line, index) => (
              <div key={line.item.id} className="flex items-center gap-2 rounded-xl border p-2.5">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{line.item.name}</p>
                  <p className="text-xs text-muted-foreground">{t(`units.${line.item.unit}`)}</p>
                </div>
                <Input
                  type="number"
                  inputMode="decimal"
                  min={0}
                  aria-label={t("po.qty")}
                  placeholder={t("po.qty")}
                  className="no-spinner h-9 w-16 text-center tabular-nums"
                  value={line.quantity}
                  onChange={(e) =>
                    setLines((ls) =>
                      ls.map((l, i) => (i === index ? { ...l, quantity: e.target.value } : l))
                    )
                  }
                />
                <Input
                  type="number"
                  inputMode="decimal"
                  min={0}
                  aria-label={t("po.unitCost")}
                  placeholder={t("po.unitCost")}
                  className="no-spinner h-9 w-24 text-right tabular-nums"
                  value={line.unit_cost}
                  onChange={(e) =>
                    setLines((ls) =>
                      ls.map((l, i) => (i === index ? { ...l, unit_cost: e.target.value } : l))
                    )
                  }
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="size-9 text-destructive"
                  onClick={() => setLines((ls) => ls.filter((_, i) => i !== index))}
                  aria-label={tc("actions.delete")}
                  title={tc("actions.delete")}
                >
                  <Trash2 className="size-4" />
                </Button>
              </div>
            ))}
            <div className="flex items-center gap-2">
              <div className="flex-1">
                <InventoryItemPicker value={picking} onChange={setPicking} branchId={branchId} />
              </div>
              <Button
                type="button"
                variant="outline"
                className="h-10"
                disabled={!picking || lines.some((l) => l.item.id === picking.id)}
                onClick={() => {
                  if (!picking) return
                  setLines((ls) => [...ls, { item: picking, quantity: "1", unit_cost: "" }])
                  setPicking(null)
                }}
              >
                <Plus className="size-4" />
                {t("po.addLine")}
              </Button>
            </div>
            {total > 0 && (
              <p className="text-right text-sm font-medium tabular-nums">
                {t("po.total")}: {total.toLocaleString()}
              </p>
            )}
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
          <Button
            type="button"
            className="h-11 flex-1"
            loading={busy}
            disabled={
              supplier.trim() === "" ||
              lines.length === 0 ||
              lines.some((l) => !(Number(l.quantity) > 0))
            }
            onClick={submit}
          >
            {t("po.submit")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

/** One PO, role-adaptive: countersign, receive part deliveries, cancel. */
export function PurchaseOrderDetailSheet({
  order,
  canApprove,
  canManage,
  currentUserId,
  open,
  onOpenChange,
  onChanged,
  onEdit,
}: {
  order: PurchaseOrder | null
  canApprove: boolean
  canManage: boolean
  currentUserId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
  onEdit: (order: PurchaseOrder) => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")

  const [detail, setDetail] = useState<PurchaseOrder | null>(null)
  const [receiveQty, setReceiveQty] = useState<Record<number, string>>({})
  const [receiveRef, setReceiveRef] = useState("")
  const [declining, setDeclining] = useState(false)
  const [declineReason, setDeclineReason] = useState("")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open || !order) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setDetail(order)
    setReceiveQty(
      Object.fromEntries(
        (order.items ?? []).map((l) => [
          l.id,
          String(Math.max(0, Number(l.quantity) - Number(l.received_quantity))),
        ])
      )
    )
    setReceiveRef("")
    setDeclining(false)
    setDeclineReason("")
  }, [open, order])

  if (!detail) return null

  const isOwn = detail.ordered_by === currentUserId
  const decidable = canApprove && detail.status === "pending" && !isOwn
  const receivable = canManage && detail.status === "approved"
  const cancellable =
    canManage &&
    (detail.status === "pending" ||
      (detail.status === "approved" &&
        (detail.items ?? []).every((l) => Number(l.received_quantity) === 0)))

  async function act(path: string, body: Record<string, unknown>, success: string) {
    setBusy(true)
    try {
      const res = await apiFetch<{ data: PurchaseOrder }>(
        `/inventory/purchase-orders/${detail!.id}/${path}`,
        { method: "POST", body }
      )
      toast.success(success)
      setDetail(res.data)
      onChanged()
      if (path === "cancel" || path === "decline") onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(false)
    }
  }

  const lines = detail.items ?? []

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle className="flex items-center gap-2">
            {t("po.detailTitle")}
            <Badge variant="outline" className={cn("rounded-full", PO_TONE[detail.status])}>
              {t(`po.statuses.${detail.status}`)}
            </Badge>
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <div className="rounded-xl border p-3 text-sm">
            <p className="font-medium">{detail.supplier_name}</p>
            <p className="text-xs text-muted-foreground">
              {[
                detail.ordered_by_name,
                fmtDateTime(detail.created_at),
                detail.expected_on ? `${t("po.expectedOn")}: ${fmtDate(detail.expected_on)}` : null,
              ]
                .filter(Boolean)
                .join(" · ")}
            </p>
            {detail.note && <p className="mt-1 text-xs text-muted-foreground">{detail.note}</p>}
            {detail.decline_reason && (
              <p className="mt-2 rounded-lg bg-destructive/10 p-2 text-xs text-destructive">
                {detail.decline_reason}
              </p>
            )}
            {canApprove && detail.status === "pending" && isOwn && (
              <p className="mt-2 text-xs text-warning">{t("po.ownRowNote")}</p>
            )}
          </div>

          <div className="space-y-2">
            {lines.map((line) => {
              const remaining = Math.max(0, Number(line.quantity) - Number(line.received_quantity))
              return (
                <div key={line.id} className="rounded-xl border p-3">
                  <div className="flex items-center justify-between gap-2">
                    <p className="min-w-0 truncate text-sm font-medium">{line.item_name}</p>
                    <p className="shrink-0 text-xs text-muted-foreground tabular-nums">
                      {t("po.ordered")}: {Number(line.quantity)}{" "}
                      {line.item_unit ? t(`units.${line.item_unit}`) : ""}
                      {line.unit_cost != null ? ` × ${Number(line.unit_cost)}` : ""}
                    </p>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground tabular-nums">
                    {t("po.receivedQty")}: {Number(line.received_quantity)} · {t("po.remaining")}:{" "}
                    {remaining}
                  </p>
                  {receivable && remaining > 0 && (
                    <div className="mt-2 flex items-center gap-2">
                      <Label htmlFor={`recv-${line.id}`} className="text-xs">
                        {t("po.receive")}
                      </Label>
                      <Input
                        id={`recv-${line.id}`}
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={remaining}
                        className="no-spinner h-9 w-24 text-right tabular-nums"
                        value={receiveQty[line.id] ?? ""}
                        onChange={(e) =>
                          setReceiveQty((q) => ({ ...q, [line.id]: e.target.value }))
                        }
                      />
                    </div>
                  )}
                </div>
              )
            })}
          </div>

          {receivable && (
            <div className="space-y-1.5">
              <Label htmlFor="recv-ref">{t("po.receiveReferenceLabel")}</Label>
              <Input
                id="recv-ref"
                placeholder={t("movement.referencePlaceholder")}
                value={receiveRef}
                onChange={(e) => setReceiveRef(e.target.value)}
              />
              <p className="text-xs text-muted-foreground">{t("po.receiveHelp")}</p>
            </div>
          )}
        </ResponsiveSheetBody>

        <ResponsiveSheetFooter>
          {decidable ? (
            <>
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1 text-destructive"
                disabled={busy}
                onClick={() => setDeclining(true)}
              >
                {t("po.decline")}
              </Button>
              <Button
                type="button"
                className="h-11 flex-1"
                loading={busy}
                onClick={() => act("approve", {}, t("po.approvedToast"))}
              >
                {t("po.approve")}
              </Button>
            </>
          ) : receivable ? (
            <>
              {cancellable && (
                <Button
                  type="button"
                  variant="outline"
                  className="h-11 flex-1 text-destructive"
                  loading={busy}
                  onClick={() => act("cancel", {}, t("po.cancelledToast"))}
                >
                  {t("po.cancel")}
                </Button>
              )}
              <Button
                type="button"
                className="h-11 flex-1"
                loading={busy}
                disabled={lines.every((l) => !(Number(receiveQty[l.id]) > 0))}
                onClick={() =>
                  act(
                    "receive",
                    {
                      reference: receiveRef || null,
                      lines: lines
                        .filter((l) => Number(receiveQty[l.id]) > 0)
                        .map((l) => ({
                          purchase_order_item_id: l.id,
                          quantity: Number(receiveQty[l.id]),
                        })),
                    },
                    t("po.receivedToast")
                  )
                }
              >
                {t("po.receive")}
              </Button>
            </>
          ) : canManage && detail.status === "pending" ? (
            <>
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1 text-destructive"
                loading={busy}
                onClick={() => act("cancel", {}, t("po.cancelledToast"))}
              >
                {t("po.cancel")}
              </Button>
              <Button
                type="button"
                className="h-11 flex-1"
                disabled={busy}
                onClick={() => {
                  onOpenChange(false)
                  onEdit(detail)
                }}
              >
                {tc("actions.edit")}
              </Button>
            </>
          ) : (
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => onOpenChange(false)}
            >
              {tc("actions.close")}
            </Button>
          )}
        </ResponsiveSheetFooter>

        <Dialog open={declining} onOpenChange={setDeclining}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{t("po.declineTitle")}</DialogTitle>
              <DialogDescription>{t("requisitions.declineReasonPlaceholder")}</DialogDescription>
            </DialogHeader>
            <div className="space-y-1.5">
              <Label htmlFor="po-decline-reason">{t("po.declineReasonLabel")}</Label>
              <Input
                id="po-decline-reason"
                value={declineReason}
                onChange={(e) => setDeclineReason(e.target.value)}
              />
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setDeclining(false)}
                disabled={busy}
              >
                {tc("actions.cancel")}
              </Button>
              <Button
                type="button"
                variant="destructive"
                loading={busy}
                disabled={declineReason.trim() === ""}
                onClick={() =>
                  act("decline", { decline_reason: declineReason }, t("po.declinedToast"))
                }
              >
                {t("po.decline")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
