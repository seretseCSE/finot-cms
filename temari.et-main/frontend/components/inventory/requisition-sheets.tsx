"use client"

import { Minus, Plus, Search, Trash2 } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

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
import { ApiError, apiFetch } from "@/lib/api"
import { fmtDateTime } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type { InventoryItem, Paginated, Requisition, RequisitionStatus } from "@/lib/types"
import { cn } from "@/lib/utils"

export const REQUISITION_TONE: Record<RequisitionStatus, string> = {
  pending: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-info/30 bg-info/10 text-info",
  declined: "border-destructive/30 bg-destructive/10 text-destructive",
  issued: "border-success/30 bg-success/10 text-success",
  cancelled: "border-border bg-muted text-muted-foreground",
}

interface CartLine {
  item: InventoryItem
  quantity: string
}

/**
 * The staff "shopping cart": search the catalog, tap items in, set
 * quantities, submit. Everyone understands a cart — no training needed.
 */
export function RequestItemsSheet({
  requisition,
  open,
  onOpenChange,
  onSaved,
}: {
  /** Present = editing one's own pending request. */
  requisition?: Requisition | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()
  const isEdit = !!requisition

  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [purpose, setPurpose] = useState("")
  const [cart, setCart] = useState<CartLine[]>([])
  const [query, setQuery] = useState("")
  const [results, setResults] = useState<InventoryItem[]>([])
  const [searching, setSearching] = useState(false)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setPurpose(requisition?.purpose ?? "")
    setCart(
      (requisition?.items ?? []).map((line) => ({
        item: {
          id: line.inventory_item_id,
          name: line.item_name ?? "",
          unit: line.item_unit ?? "piece",
        } as InventoryItem,
        quantity: String(Number(line.quantity_requested)),
      }))
    )
    setQuery("")
    setResults([])
    setBranchId(null)
    setBranchError(null)
  }, [open, requisition])

  // Debounced catalog search — all state updates happen inside the timer so
  // the effect body itself never sets state synchronously.
  useEffect(() => {
    if (!open) return
    const controller = new AbortController()
    const timer = setTimeout(() => {
      const term = query.trim()
      if (term.length < 1) {
        setResults([])
        setSearching(false)
        return
      }
      setSearching(true)
      const params = new URLSearchParams({ search: term, per_page: "10" })
      if (branchId != null) params.set("branch_id", String(branchId))
      apiFetch<Paginated<InventoryItem>>(`/inventory/items?${params}`, {
        signal: controller.signal,
      })
        .then((res) => setResults(res.data))
        .catch(() => {})
        .finally(() => setSearching(false))
    }, 250)
    return () => {
      clearTimeout(timer)
      controller.abort()
    }
  }, [query, open, branchId])

  const cartIds = useMemo(() => new Set(cart.map((l) => l.item.id)), [cart])

  function addItem(item: InventoryItem) {
    setCart((lines) =>
      cartIds.has(item.id)
        ? lines.map((l) =>
            l.item.id === item.id
              ? { ...l, quantity: String(Number(l.quantity || 0) + 1) }
              : l
          )
        : [...lines, { item, quantity: "1" }]
    )
  }

  function setQty(itemId: number, quantity: string) {
    setCart((lines) => lines.map((l) => (l.item.id === itemId ? { ...l, quantity } : l)))
  }

  function step(itemId: number, delta: number) {
    setCart((lines) =>
      lines.map((l) =>
        l.item.id === itemId
          ? { ...l, quantity: String(Math.max(1, Number(l.quantity || 0) + delta)) }
          : l
      )
    )
  }

  async function submit() {
    if (!isEdit && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }
    setBusy(true)
    try {
      await apiFetch(isEdit ? `/inventory/requisitions/${requisition!.id}` : "/inventory/requisitions", {
        method: isEdit ? "PUT" : "POST",
        body: {
          ...(!isEdit && branchId != null ? { branch_id: branchId } : {}),
          purpose: purpose || null,
          items: cart.map((l) => ({
            inventory_item_id: l.item.id,
            quantity: Number(l.quantity),
          })),
        },
      })
      toast.success(t("requisitions.submitted"))
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
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("requisitions.newTitle")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
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

          <div className="space-y-1.5">
            <Label htmlFor="req-search">{t("requisitions.searchCatalog")}</Label>
            <div className="relative">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                id="req-search"
                className="pl-9"
                placeholder={t("items.searchPlaceholder")}
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
            </div>
            {query.trim() !== "" && (
              <div className="overflow-hidden rounded-xl border">
                {searching && results.length === 0 ? (
                  <p className="p-3 text-sm text-muted-foreground">{tc("states.loading")}</p>
                ) : results.length === 0 ? (
                  <p className="p-3 text-sm text-muted-foreground">{tc("dataTable.noMatches")}</p>
                ) : (
                  results.map((item) => (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => addItem(item)}
                      className="pressable flex w-full items-center justify-between gap-2 border-b px-3 py-2.5 text-left last:border-b-0 hover:bg-muted"
                    >
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-medium">{item.name}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                          {[item.category_name, item.code].filter(Boolean).join(" · ")}
                        </span>
                      </span>
                      <span className="flex shrink-0 items-center gap-2">
                        <span className="text-xs text-muted-foreground tabular-nums">
                          {Number(item.quantity_on_hand ?? 0)} {t(`units.${item.unit}`)}
                        </span>
                        <Plus className="size-4 text-primary" />
                      </span>
                    </button>
                  ))
                )}
              </div>
            )}
          </div>

          <div className="space-y-2">
            <Label>{t("requisitions.items")}</Label>
            {cart.length === 0 ? (
              <p className="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground">
                {t("requisitions.cartEmpty")}
              </p>
            ) : (
              <div className="space-y-2">
                {cart.map((line) => (
                  <div
                    key={line.item.id}
                    className="flex items-center gap-2 rounded-xl border p-2.5"
                  >
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{line.item.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {t(`units.${line.item.unit}`)}
                      </p>
                    </div>
                    <div className="flex items-center gap-1">
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-9"
                        onClick={() => step(line.item.id, -1)}
                        aria-label="-1"
                      >
                        <Minus className="size-4" />
                      </Button>
                      <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        aria-label={t("requisitions.quantityLabel")}
                        className="no-spinner h-9 w-16 text-center tabular-nums"
                        value={line.quantity}
                        onChange={(e) => setQty(line.item.id, e.target.value)}
                      />
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-9"
                        onClick={() => step(line.item.id, 1)}
                        aria-label="+1"
                      >
                        <Plus className="size-4" />
                      </Button>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="size-9 text-destructive"
                      onClick={() =>
                        setCart((lines) => lines.filter((l) => l.item.id !== line.item.id))
                      }
                      aria-label={tc("actions.delete")}
                      title={tc("actions.delete")}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="req-purpose">{t("requisitions.purposeLabel")}</Label>
            <Input
              id="req-purpose"
              placeholder={t("requisitions.purposePlaceholder")}
              value={purpose}
              onChange={(e) => setPurpose(e.target.value)}
            />
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
            disabled={cart.length === 0 || cart.some((l) => !(Number(l.quantity) > 0))}
            onClick={submit}
          >
            {t("requisitions.submit")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

/**
 * One requisition, role-adaptive: approvers trim + countersign (never their
 * own), the store issues against approved lines, requesters watch progress
 * and may cancel while pending.
 */
export function RequisitionDetailSheet({
  requisition,
  canApprove,
  canManage,
  currentUserId,
  open,
  onOpenChange,
  onChanged,
  onEdit,
}: {
  requisition: Requisition | null
  canApprove: boolean
  canManage: boolean
  currentUserId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
  onEdit: (requisition: Requisition) => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")

  const [detail, setDetail] = useState<Requisition | null>(null)
  const [approvedQty, setApprovedQty] = useState<Record<number, string>>({})
  const [issueQty, setIssueQty] = useState<Record<number, string>>({})
  const [declining, setDeclining] = useState(false)
  const [declineReason, setDeclineReason] = useState("")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open || !requisition) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setDetail(requisition)
    setApprovedQty(
      Object.fromEntries(
        (requisition.items ?? []).map((l) => [l.id, String(Number(l.quantity_requested))])
      )
    )
    setIssueQty(
      Object.fromEntries(
        (requisition.items ?? []).map((l) => [
          l.id,
          String(
            Math.max(
              0,
              Number(l.quantity_approved ?? l.quantity_requested) - Number(l.quantity_issued)
            )
          ),
        ])
      )
    )
    setDeclining(false)
    setDeclineReason("")
  }, [open, requisition])

  if (!detail) return null

  const isOwn = detail.requested_by === currentUserId
  const decidable = canApprove && detail.status === "pending" && !isOwn
  const issuable = canManage && detail.status === "approved"

  async function act(path: string, body: Record<string, unknown>, success: string) {
    setBusy(true)
    try {
      const res = await apiFetch<{ data: Requisition }>(
        `/inventory/requisitions/${detail!.id}/${path}`,
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
            {t("requisitions.detailTitle")}
            <Badge variant="outline" className={cn("rounded-full", REQUISITION_TONE[detail.status])}>
              {t(`requisitions.statuses.${detail.status}`)}
            </Badge>
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <div className="rounded-xl border p-3 text-sm">
            <p className="font-medium">{detail.requested_by_name}</p>
            <p className="text-xs text-muted-foreground">
              {fmtDateTime(detail.created_at)}
              {detail.purpose ? ` · ${detail.purpose}` : ""}
            </p>
            {detail.decline_reason && (
              <p className="mt-2 rounded-lg bg-destructive/10 p-2 text-xs text-destructive">
                {detail.decline_reason}
              </p>
            )}
            {decidable && (
              <p className="mt-2 text-xs text-muted-foreground">{t("requisitions.approveHelp")}</p>
            )}
            {canApprove && detail.status === "pending" && isOwn && (
              <p className="mt-2 text-xs text-warning">{t("requisitions.ownRowNote")}</p>
            )}
          </div>

          <div className="space-y-2">
            {lines.map((line) => {
              const approved = Number(line.quantity_approved ?? line.quantity_requested)
              const remaining = Math.max(0, approved - Number(line.quantity_issued))
              return (
                <div key={line.id} className="rounded-xl border p-3">
                  <div className="flex items-center justify-between gap-2">
                    <p className="min-w-0 truncate text-sm font-medium">{line.item_name}</p>
                    <p className="shrink-0 text-xs text-muted-foreground tabular-nums">
                      {t("requisitions.requestedQty")}: {Number(line.quantity_requested)}{" "}
                      {line.item_unit ? t(`units.${line.item_unit}`) : ""}
                    </p>
                  </div>
                  {detail.status !== "pending" && (
                    <p className="mt-1 text-xs text-muted-foreground tabular-nums">
                      {t("requisitions.approvedQty")}:{" "}
                      {line.quantity_approved != null ? Number(line.quantity_approved) : "—"} ·{" "}
                      {t("requisitions.issuedQty")}: {Number(line.quantity_issued)}
                    </p>
                  )}
                  {decidable && (
                    <div className="mt-2 flex items-center gap-2">
                      <Label htmlFor={`approve-${line.id}`} className="text-xs">
                        {t("requisitions.approvedQty")}
                      </Label>
                      <Input
                        id={`approve-${line.id}`}
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={Number(line.quantity_requested)}
                        className="no-spinner h-9 w-24 text-right tabular-nums"
                        value={approvedQty[line.id] ?? ""}
                        onChange={(e) =>
                          setApprovedQty((q) => ({ ...q, [line.id]: e.target.value }))
                        }
                      />
                    </div>
                  )}
                  {issuable && (
                    <div className="mt-2 flex items-center gap-2">
                      <Label htmlFor={`issue-${line.id}`} className="text-xs">
                        {t("requisitions.issue")}
                      </Label>
                      <Input
                        id={`issue-${line.id}`}
                        type="number"
                        inputMode="decimal"
                        min={0}
                        max={remaining}
                        className="no-spinner h-9 w-24 text-right tabular-nums"
                        value={issueQty[line.id] ?? ""}
                        onChange={(e) => setIssueQty((q) => ({ ...q, [line.id]: e.target.value }))}
                      />
                      <span className="text-xs text-muted-foreground tabular-nums">
                        {t("requisitions.remaining")}: {remaining}
                      </span>
                    </div>
                  )}
                </div>
              )
            })}
          </div>

          {issuable && <p className="text-xs text-muted-foreground">{t("requisitions.issueHelp")}</p>}
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
                {t("requisitions.decline")}
              </Button>
              <Button
                type="button"
                className="h-11 flex-1"
                loading={busy}
                onClick={() =>
                  act(
                    "approve",
                    {
                      lines: lines.map((line) => ({
                        requisition_item_id: line.id,
                        quantity_approved: Number(approvedQty[line.id] ?? line.quantity_requested),
                      })),
                    },
                    t("requisitions.approvedToast")
                  )
                }
              >
                {t("requisitions.approve")}
              </Button>
            </>
          ) : issuable ? (
            <Button
              type="button"
              className="h-11 flex-1"
              loading={busy}
              disabled={lines.every((l) => !(Number(issueQty[l.id]) > 0))}
              onClick={() =>
                act(
                  "issue",
                  {
                    lines: lines
                      .filter((l) => Number(issueQty[l.id]) > 0)
                      .map((l) => ({
                        requisition_item_id: l.id,
                        quantity: Number(issueQty[l.id]),
                      })),
                  },
                  t("requisitions.issueToast")
                )
              }
            >
              {t("requisitions.issue")}
            </Button>
          ) : isOwn && detail.status === "pending" ? (
            <>
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1 text-destructive"
                loading={busy}
                onClick={() => act("cancel", {}, t("requisitions.cancelled"))}
              >
                {t("requisitions.cancel")}
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
              <DialogTitle>{t("requisitions.declineTitle")}</DialogTitle>
              <DialogDescription>{t("requisitions.declineReasonPlaceholder")}</DialogDescription>
            </DialogHeader>
            <div className="space-y-1.5">
              <Label htmlFor="decline-reason">{t("requisitions.declineReasonLabel")}</Label>
              <Input
                id="decline-reason"
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
                  act("decline", { decline_reason: declineReason }, t("requisitions.declinedToast"))
                }
              >
                {t("requisitions.decline")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
