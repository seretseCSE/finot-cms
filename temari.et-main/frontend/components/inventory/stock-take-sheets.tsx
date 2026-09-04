"use client"

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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { InventoryCategory, StockTake, StockTakeStatus } from "@/lib/types"
import { cn } from "@/lib/utils"

export const STOCK_TAKE_TONE: Record<StockTakeStatus, string> = {
  in_progress: "border-warning/30 bg-warning/10 text-warning",
  posted: "border-success/30 bg-success/10 text-success",
  cancelled: "border-border bg-muted text-muted-foreground",
}

/** Start a counting session — optionally scoped to one category. */
export function StartStockTakeDialog({
  categories,
  open,
  onOpenChange,
  onStarted,
}: {
  categories: InventoryCategory[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onStarted: (stockTake: StockTake) => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")
  const { needsBranch } = useBranchScope()

  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [categoryId, setCategoryId] = useState("")
  const [note, setNote] = useState("")
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setCategoryId("")
    setNote("")
    setBranchId(null)
    setBranchError(null)
  }, [open])

  async function start() {
    if (needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }
    setBusy(true)
    try {
      const res = await apiFetch<{ data: StockTake }>("/inventory/stock-takes", {
        method: "POST",
        body: {
          ...(branchId != null ? { branch_id: branchId } : {}),
          inventory_category_id: categoryId ? Number(categoryId) : null,
          note: note || null,
        },
      })
      toast.success(t("stockTakes.started"))
      onOpenChange(false)
      onStarted(res.data)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("stockTakes.newTitle")}</DialogTitle>
          <DialogDescription>{t("stockTakes.newHelp")}</DialogDescription>
        </DialogHeader>
        <div className="space-y-4">
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
            <Label>{t("stockTakes.categoryLabel")}</Label>
            <Select value={categoryId} onValueChange={setCategoryId}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t("stockTakes.categoryPlaceholder")} />
              </SelectTrigger>
              <SelectContent>
                {categories
                  .filter((c) => c.is_active)
                  .map((category) => (
                    <SelectItem key={category.id} value={String(category.id)}>
                      {category.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="st-note">{t("stockTakes.noteLabel")}</Label>
            <Input id="st-note" value={note} onChange={(e) => setNote(e.target.value)} />
          </div>
        </div>
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={busy}
          >
            {tc("actions.cancel")}
          </Button>
          <Button type="button" loading={busy} onClick={start}>
            {t("stockTakes.new")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/**
 * The tally screen: one row per item, expected vs counted, save as you go,
 * then post — differences land in the ledger as adjustments.
 */
export function StockTakeCountSheet({
  stockTake,
  canManage,
  open,
  onOpenChange,
  onChanged,
}: {
  stockTake: StockTake | null
  canManage: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
}) {
  const { t } = useTranslation("inventory")
  const { t: tc } = useTranslation("common")

  const [detail, setDetail] = useState<StockTake | null>(null)
  const [counts, setCounts] = useState<Record<number, string>>({})
  const [filter, setFilter] = useState("")
  const [busy, setBusy] = useState<"save" | "post" | "cancel" | null>(null)
  const [confirmPost, setConfirmPost] = useState(false)

  // Fetch the full session (list rows come without lines).
  useEffect(() => {
    if (!open || !stockTake) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale detail
    setDetail(null)
    setFilter("")
    apiFetch<{ data: StockTake }>(`/inventory/stock-takes/${stockTake.id}`)
      .then((res) => {
        if (cancelled) return
        setDetail(res.data)
        setCounts(
          Object.fromEntries(
            (res.data.lines ?? []).map((l) => [
              l.id,
              l.counted_quantity != null ? String(Number(l.counted_quantity)) : "",
            ])
          )
        )
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [open, stockTake])

  const lines = useMemo(() => {
    const all = detail?.lines ?? []
    const term = filter.trim().toLowerCase()
    return term === "" ? all : all.filter((l) => (l.item_name ?? "").toLowerCase().includes(term))
  }, [detail, filter])

  const editable = canManage && detail?.status === "in_progress"

  async function saveCounts(): Promise<boolean> {
    if (!detail) return false
    const payload = (detail.lines ?? []).map((l) => ({
      stock_take_line_id: l.id,
      counted_quantity: counts[l.id] === "" || counts[l.id] == null ? null : Number(counts[l.id]),
    }))
    setBusy("save")
    try {
      await apiFetch(`/inventory/stock-takes/${detail.id}/counts`, {
        method: "PUT",
        body: { lines: payload },
      })
      toast.success(t("stockTakes.countsSaved"))
      return true
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      return false
    } finally {
      setBusy(null)
    }
  }

  async function post() {
    if (!detail || !(await saveCounts())) return
    setBusy("post")
    try {
      const res = await apiFetch<{ data: StockTake; message?: string }>(
        `/inventory/stock-takes/${detail.id}/post`,
        { method: "POST" }
      )
      toast.success(res.message ?? t("stockTakes.posted"))
      setConfirmPost(false)
      onChanged()
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(null)
    }
  }

  async function cancel() {
    if (!detail) return
    setBusy("cancel")
    try {
      await apiFetch(`/inventory/stock-takes/${detail.id}/cancel`, { method: "POST" })
      toast.success(t("stockTakes.cancelledToast"))
      onChanged()
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setBusy(null)
    }
  }

  const countedTotal = (detail?.lines ?? []).filter(
    (l) => counts[l.id] !== "" && counts[l.id] != null
  ).length

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle className="flex items-center gap-2">
            {t("stockTakes.countTitle")}
            {detail && (
              <Badge
                variant="outline"
                className={cn("rounded-full", STOCK_TAKE_TONE[detail.status])}
              >
                {t(`stockTakes.statuses.${detail.status}`)}
              </Badge>
            )}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-3">
          {!detail ? (
            <p className="p-4 text-center text-sm text-muted-foreground">{tc("states.loading")}</p>
          ) : (
            <>
              <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                <span>
                  {detail.category_name ?? t("stockTakes.allCategories")}
                  {detail.note ? ` · ${detail.note}` : ""}
                </span>
                <span className="tabular-nums">
                  {t("stockTakes.progress")}: {countedTotal}/{(detail.lines ?? []).length}
                </span>
              </div>

              <Input
                placeholder={t("items.searchPlaceholder")}
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
              />

              <div className="space-y-1.5">
                {lines.map((line) => {
                  const counted = counts[line.id] ?? ""
                  const diff =
                    counted === "" ? null : Number(counted) - Number(line.expected_quantity)
                  return (
                    <div
                      key={line.id}
                      className="flex items-center gap-2 rounded-xl border px-3 py-2"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium">{line.item_name}</p>
                        <p className="text-xs text-muted-foreground tabular-nums">
                          {t("stockTakes.expected")}: {Number(line.expected_quantity)}{" "}
                          {line.item_unit ? t(`units.${line.item_unit}`) : ""}
                        </p>
                      </div>
                      {diff !== null && diff !== 0 && (
                        <span
                          className={cn(
                            "shrink-0 text-xs font-medium tabular-nums",
                            diff > 0 ? "text-success" : "text-destructive"
                          )}
                        >
                          {diff > 0 ? `+${diff}` : diff}
                        </span>
                      )}
                      <Input
                        type="number"
                        inputMode="decimal"
                        min={0}
                        aria-label={t("stockTakes.counted")}
                        placeholder="—"
                        disabled={!editable}
                        className="no-spinner h-10 w-20 text-center tabular-nums"
                        value={counted}
                        onChange={(e) =>
                          setCounts((c) => ({ ...c, [line.id]: e.target.value }))
                        }
                      />
                    </div>
                  )
                })}
              </div>

              {editable && (
                <p className="text-xs text-muted-foreground">{t("stockTakes.notCounted")}</p>
              )}
            </>
          )}
        </ResponsiveSheetBody>

        {editable && (
          <ResponsiveSheetFooter>
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1 text-destructive"
              loading={busy === "cancel"}
              disabled={busy !== null && busy !== "cancel"}
              onClick={cancel}
            >
              {t("stockTakes.cancel")}
            </Button>
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              loading={busy === "save"}
              disabled={busy !== null && busy !== "save"}
              onClick={saveCounts}
            >
              {t("stockTakes.saveCounts")}
            </Button>
            <Button
              type="button"
              className="h-11 flex-1"
              disabled={busy !== null}
              onClick={() => setConfirmPost(true)}
            >
              {t("stockTakes.post")}
            </Button>
          </ResponsiveSheetFooter>
        )}

        <Dialog open={confirmPost} onOpenChange={setConfirmPost}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{t("stockTakes.postTitle")}</DialogTitle>
              <DialogDescription>{t("stockTakes.postBody")}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setConfirmPost(false)}
                disabled={busy !== null}
              >
                {tc("actions.cancel")}
              </Button>
              <Button type="button" loading={busy === "post" || busy === "save"} onClick={post}>
                {t("stockTakes.post")}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
