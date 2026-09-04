"use client"

import { ChevronLeft, ChevronRight, Inbox, Nfc } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { CardRequestRow, CardRequestStatus } from "@/lib/types"
import { cn } from "@/lib/utils"

import { ScopeSelects, type ScopeValue } from "./scope-selects"

const STATUSES: CardRequestStatus[] = [
  "requested",
  "accepted",
  "preparing",
  "delivering",
  "delivered",
  "rejected",
]

const STATUS_TONE: Record<CardRequestStatus, string> = {
  requested: "border-transparent bg-warning/10 text-warning",
  accepted: "border-transparent bg-info/10 text-info",
  preparing: "border-transparent bg-info/10 text-info",
  delivering: "border-transparent bg-info/10 text-info",
  delivered: "border-transparent bg-success/10 text-success",
  rejected: "border-transparent bg-muted text-muted-foreground",
}

/** The pipeline steps in order — rejected sits outside the flow. */
const PIPELINE: CardRequestStatus[] = [
  "requested",
  "accepted",
  "preparing",
  "delivering",
  "delivered",
]

/**
 * The card fulfilment queue. Schools watch their requests move; Temari.et
 * staff drive them: accept → issue the replacement chip → delivering →
 * delivered (or reject).
 */
export function RequestsTab() {
  const { t } = useTranslation("devices")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const canManage = permissions.includes("cards.manage")

  const [scope, setScope] = useState<ScopeValue>({ schoolId: null, branchId: null })
  const [status, setStatus] = useState<string>("open")
  const [page, setPage] = useState(1)
  const [rows, setRows] = useState<CardRequestRow[] | null>(null)
  const [lastPage, setLastPage] = useState(1)
  const [openCount, setOpenCount] = useState(0)

  const [issuing, setIssuing] = useState<CardRequestRow | null>(null)
  const [issuingUid, setIssuingUid] = useState("")
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => {
    let cancelled = false
    setRows(null)
    const params = new URLSearchParams({ page: String(page), per_page: "25" })
    if (scope.schoolId != null) params.set("school_id", String(scope.schoolId))
    if (scope.branchId != null) params.set("branch_id", String(scope.branchId))
    if (status === "open") {
      params.set("status", "requested,accepted,preparing,delivering")
    } else if (status !== "all") {
      params.set("status", status)
    }
    apiFetch<{
      data: CardRequestRow[]
      meta: { last_page: number; open_count: number }
    }>(`/card-requests?${params}`)
      .then((res) => {
        if (cancelled) return
        setRows(res.data)
        setLastPage(res.meta.last_page)
        setOpenCount(res.meta.open_count)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
  }, [page, scope.schoolId, scope.branchId, status, tc])

  // eslint-disable-next-line react-hooks/set-state-in-effect -- load resets to the loading state
  useEffect(() => load(), [load, active.branchId, active.schoolId])

  async function moveTo(row: CardRequestRow, next: CardRequestStatus) {
    try {
      await apiFetch(`/card-requests/${row.id}`, { method: "PATCH", body: { status: next } })
      toast.success(t("requests.updated"))
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function issueReplacement() {
    if (!issuing || !issuingUid.trim()) return
    setSaving(true)
    try {
      await apiFetch(`/card-requests/${issuing.id}/issue`, {
        method: "POST",
        body: { card_uid: issuingUid.trim() },
      })
      toast.success(t("cards.replaced"))
      setIssuing(null)
      setIssuingUid("")
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="page-gutter space-y-4">
      {/* Filters + open counter */}
      <div className="flex flex-wrap items-end gap-2">
        <ScopeSelects value={scope} onChange={(v) => { setScope(v); setPage(1) }} allOption />
        <Select
          value={status}
          onValueChange={(v) => {
            setStatus(v)
            setPage(1)
          }}
        >
          <SelectTrigger className="h-9 w-44 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="open">{t("requests.openOnly")}</SelectItem>
            <SelectItem value="all">{t("requests.all")}</SelectItem>
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {t(`requests.statuses.${s}`)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {openCount > 0 && (
          <span className="ml-auto inline-flex items-center gap-1.5 rounded-full border border-warning/30 bg-warning/5 px-3 py-1.5 text-xs font-medium text-warning tabular-nums">
            <Inbox className="size-3.5" />
            {t("requests.openCount", { count: openCount })}
          </span>
        )}
      </div>

      {rows === null ? (
        <Skeleton className="h-72 w-full rounded-2xl" />
      ) : rows.length === 0 ? (
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState icon={Inbox} title={t("requests.empty")} compact />
        </div>
      ) : (
        <ul className="space-y-3">
          {rows.map((row) => {
            const stepIndex = PIPELINE.indexOf(row.status)
            return (
              <li key={row.id} className="rounded-2xl border bg-card p-4 shadow-xs">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  {/* Who + where */}
                  <div className="min-w-0 leading-tight">
                    <p className="flex flex-wrap items-center gap-2 text-sm font-medium">
                      {row.holder_name ?? "—"}
                      <Badge variant="secondary" className="text-[10px]">
                        {t(`cards.holderTypes.${row.holder_type}`)}
                      </Badge>
                      <Badge className={cn("text-[10px]", STATUS_TONE[row.status])}>
                        {t(`requests.statuses.${row.status}`)}
                      </Badge>
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                      {[row.school_name, row.branch_name].filter(Boolean).join(" · ")}
                      {" — "}
                      {t(`requests.reasons.${row.reason}`)}
                      {row.requested_by_name
                        ? ` · ${t("requests.by", { name: row.requested_by_name })}`
                        : ""}
                    </p>
                    {row.note && (
                      <p className="mt-1.5 rounded-lg bg-muted/40 px-2.5 py-1.5 text-xs">
                        “{row.note}”
                      </p>
                    )}
                    <p className="mt-1.5 font-mono text-[11px] text-muted-foreground">
                      {row.lost_card_uid ?? "—"}
                      {" → "}
                      <span className={cn(row.new_card_uid && "text-success")}>
                        {row.new_card_uid ?? t("requests.awaitingChip")}
                      </span>
                    </p>
                  </div>

                  {/* Platform actions */}
                  {canManage && row.status !== "rejected" && row.status !== "delivered" && (
                    <div className="flex flex-wrap items-center gap-1.5">
                      {row.status === "requested" && (
                        <Button variant="outline" size="sm" className="h-8 text-xs" onClick={() => moveTo(row, "accepted")}>
                          {t("requests.actions.accept")}
                        </Button>
                      )}
                      {!row.new_card_uid && (
                        <Button size="sm" className="h-8 text-xs" onClick={() => setIssuing(row)}>
                          <Nfc className="size-3.5" /> {t("requests.actions.issueChip")}
                        </Button>
                      )}
                      {row.new_card_uid && row.status !== "delivering" && (
                        <Button variant="outline" size="sm" className="h-8 text-xs" onClick={() => moveTo(row, "delivering")}>
                          {t("requests.actions.startDelivery")}
                        </Button>
                      )}
                      {row.new_card_uid && row.status === "delivering" && (
                        <Button size="sm" className="h-8 text-xs" onClick={() => moveTo(row, "delivered")}>
                          {t("requests.actions.markDelivered")}
                        </Button>
                      )}
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 text-xs text-destructive"
                        onClick={() => moveTo(row, "rejected")}
                      >
                        {t("requests.actions.reject")}
                      </Button>
                    </div>
                  )}
                </div>

                {/* Pipeline stepper */}
                {row.status !== "rejected" && (
                  <div className="mt-3 flex items-center gap-1">
                    {PIPELINE.map((step, i) => (
                      <div key={step} className="flex flex-1 items-center gap-1">
                        <div
                          className={cn(
                            "h-1 flex-1 rounded-full",
                            i <= stepIndex ? "bg-primary" : "bg-muted",
                          )}
                          title={t(`requests.statuses.${step}`)}
                        />
                      </div>
                    ))}
                  </div>
                )}
              </li>
            )
          })}
        </ul>
      )}

      {lastPage > 1 && (
        <div className="flex items-center justify-end gap-2">
          <Button
            variant="outline"
            size="icon"
            className="size-8"
            disabled={page <= 1}
            onClick={() => setPage((p) => p - 1)}
            aria-label={tc("attachment.previous")}
          >
            <ChevronLeft className="size-4" />
          </Button>
          <span className="text-xs text-muted-foreground tabular-nums">
            {page} / {lastPage}
          </span>
          <Button
            variant="outline"
            size="icon"
            className="size-8"
            disabled={page >= lastPage}
            onClick={() => setPage((p) => p + 1)}
            aria-label={tc("attachment.next")}
          >
            <ChevronRight className="size-4" />
          </Button>
        </div>
      )}

      {/* Issue the replacement chip into a request */}
      <Dialog
        open={issuing !== null}
        onOpenChange={(open) => {
          if (!open) {
            setIssuing(null)
            setIssuingUid("")
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {t("cards.replaceTitle", { name: issuing?.holder_name ?? "" })}
            </DialogTitle>
            <DialogDescription>{t("cards.replaceHint")}</DialogDescription>
          </DialogHeader>
          <div className="relative">
            <Nfc className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={issuingUid}
              autoFocus
              onChange={(e) => setIssuingUid(e.target.value.toUpperCase())}
              onKeyDown={(e) => e.key === "Enter" && issueReplacement()}
              placeholder={t("cards.fields.uidPlaceholder")}
              className="pl-10 font-mono uppercase"
              maxLength={32}
            />
          </div>
          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setIssuing(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              onClick={issueReplacement}
              loading={saving} disabled={!issuingUid.trim()}
            >
              {t("cards.issueAction")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
