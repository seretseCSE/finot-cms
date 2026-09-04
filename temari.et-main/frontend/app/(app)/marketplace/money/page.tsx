"use client"

import { Banknote, CheckCircle2, HandCoins, Landmark, RotateCcw, Undo2 } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { EmptyState } from "@/components/ui/empty-state"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
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
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn, formatETB } from "@/lib/utils"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

interface Stats {
  awaiting_payment: number
  held_in_escrow: number
  releasable_count: number
  commission_collected: number
  released_total: number
}

interface CycleRow {
  id: number
  label: string
  status: string
  tutor_name: string | null
  payer_name: string | null
  gross_amount: string
  amount_due: string
  commission_percent: string
  commission_amount: string | null
  released_amount: string | null
  credit_carried: string | null
  ends_on: string
  sessions_logged: number
  sessions_disputed: number
}

interface PayoutRow {
  id: number
  amount: string
  method: string
  status: string
  tutor_name: string | null
  tutor_phone: string | null
  wallet_balance: string
  bank_name: string | null
  account_number: string | null
  account_name: string | null
  failure_reason: string | null
  created_at: string
}

const STATUS_TONE: Record<string, string> = {
  awaiting_payment: "border-warning/30 bg-warning/10 text-warning",
  funded: "border-success/30 bg-success/10 text-success",
  released: "border-primary/30 bg-primary/10 text-primary",
  refunded: "border-border bg-muted text-muted-foreground",
  canceled: "border-border bg-muted text-muted-foreground",
  pending: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-info/30 bg-info/10 text-info",
  paid: "border-success/30 bg-success/10 text-success",
  failed: "border-destructive/30 bg-destructive/10 text-destructive",
}

export default function MarketplaceMoneyPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")

  const [tab, setTab] = useState<"cycles" | "payouts">("cycles")
  const [stats, setStats] = useState<Stats | null>(null)
  const [cycles, setCycles] = useState<CycleRow[]>([])
  const [payouts, setPayouts] = useState<PayoutRow[]>([])
  const [cycleFilter, setCycleFilter] = useState("releasable")
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState<string | null>(null)

  // Refund sheet.
  const [refundCycle, setRefundCycle] = useState<CycleRow | null>(null)
  const [refundNote, setRefundNote] = useState("")

  // Payout pay sheet.
  const [payTarget, setPayTarget] = useState<PayoutRow | null>(null)
  const [payMethod, setPayMethod] = useState<"chapa" | "manual">("chapa")

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const cycleQuery =
        cycleFilter === "releasable" ? "releasable=1" : cycleFilter === "all" ? "" : `status=${cycleFilter}`
      const [statsRes, cyclesRes, payoutsRes] = await Promise.all([
        apiFetch<{ data: Stats }>("/marketplace/cycles/stats"),
        apiFetch<{ data: CycleRow[] }>(`/marketplace/cycles?per_page=50&${cycleQuery}`),
        apiFetch<{ data: PayoutRow[] }>("/marketplace/payouts?per_page=50"),
      ])
      setStats(statsRes.data)
      setCycles(cyclesRes.data)
      setPayouts(payoutsRes.data)
    } catch {
      // empty states
    } finally {
      setLoading(false)
    }
  }, [cycleFilter])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!cancelled) await load()
    })()
    return () => {
      cancelled = true
    }
  }, [load])

  async function release(cycle: CycleRow) {
    setBusy(`release-${cycle.id}`)
    try {
      await apiFetch(`/marketplace/cycles/${cycle.id}/release`, { method: "POST" })
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function refund() {
    if (!refundCycle) return
    setBusy("refund")
    try {
      await apiFetch(`/marketplace/cycles/${refundCycle.id}/refund`, {
        method: "POST",
        body: JSON.stringify({ note: refundNote }),
      })
      setRefundCycle(null)
      setRefundNote("")
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function approvePayout(payout: PayoutRow) {
    setBusy(`approve-${payout.id}`)
    try {
      await apiFetch(`/marketplace/payouts/${payout.id}/approve`, { method: "POST" })
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function payPayout() {
    if (!payTarget) return
    setBusy("pay")
    try {
      await apiFetch(`/marketplace/payouts/${payTarget.id}/pay`, {
        method: "POST",
        body: JSON.stringify({ method: payMethod }),
      })
      setPayTarget(null)
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function reversePayout(payout: PayoutRow) {
    setBusy(`reverse-${payout.id}`)
    try {
      await apiFetch(`/marketplace/payouts/${payout.id}/reverse`, {
        method: "POST",
        body: JSON.stringify({ status: "canceled" }),
      })
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("admin.moneyTitle")} description={t("admin.moneyDesc")} />

      <section className="page-gutter grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          icon={Landmark}
          label={t("admin.heldInEscrow")}
          value={stats ? formatETB(stats.held_in_escrow) : null}
        />
        <StatCard
          icon={HandCoins}
          label={t("admin.releasable")}
          value={stats ? String(stats.releasable_count) : null}
        />
        <StatCard
          icon={Banknote}
          label={t("admin.commissionCollected")}
          value={stats ? formatETB(stats.commission_collected) : null}
        />
        <StatCard
          icon={CheckCircle2}
          label={t("admin.releasedTotal")}
          value={stats ? formatETB(stats.released_total) : null}
        />
      </section>

      <div className="page-gutter flex gap-2">
        {(["cycles", "payouts"] as const).map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={cn(
              "touch-target rounded-full border px-4 py-2 text-sm font-medium transition-colors",
              tab === key
                ? "border-primary bg-primary/10 text-primary"
                : "bg-muted/30 text-muted-foreground hover:bg-accent/50",
            )}
          >
            {key === "cycles" ? t("admin.cycles") : t("admin.payoutDesk")}
          </button>
        ))}
      </div>

      {tab === "cycles" && (
        <section className="page-gutter space-y-3">
          <Select value={cycleFilter} onValueChange={setCycleFilter}>
            <SelectTrigger className="w-56 rounded-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="releasable">{t("admin.releasable")}</SelectItem>
              <SelectItem value="funded">{t("status.funded")}</SelectItem>
              <SelectItem value="awaiting_payment">{t("status.awaiting_payment")}</SelectItem>
              <SelectItem value="released">{t("status.released")}</SelectItem>
              <SelectItem value="refunded">{t("status.refunded")}</SelectItem>
              <SelectItem value="all">{tc("filters.status")}</SelectItem>
            </SelectContent>
          </Select>

          {loading ? (
            <Skeleton className="h-64 rounded-2xl" />
          ) : cycles.length === 0 ? (
            <EmptyState icon={Landmark} title={t("dir.empty")} description="" />
          ) : (
            <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
              {cycles.map((cycle, index) => (
                <div
                  key={cycle.id}
                  className={cn(
                    "flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between",
                    index > 0 && "border-t",
                  )}
                >
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="font-medium">{cycle.label}</p>
                      <Badge variant="outline" className={STATUS_TONE[cycle.status]}>
                        {t(`status.${cycle.status}`)}
                      </Badge>
                      {cycle.sessions_disputed > 0 && (
                        <Badge variant="outline" className={STATUS_TONE.failed}>
                          {t("admin.disputes")}: {cycle.sessions_disputed}
                        </Badge>
                      )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {cycle.tutor_name} ← {cycle.payer_name} · {formatETB(cycle.amount_due)} ·{" "}
                      {cycle.commission_percent}%
                    </p>
                    {cycle.status === "released" && (
                      <p className="text-xs text-muted-foreground">
                        {t("admin.releasedTotal")}: {formatETB(cycle.released_amount ?? 0)} ·{" "}
                        {t("admin.commissionCollected")}: {formatETB(cycle.commission_amount ?? 0)} ·{" "}
                        {t("family.credit")}: {formatETB(cycle.credit_carried ?? 0)}
                      </p>
                    )}
                  </div>
                  {cycle.status === "funded" && (
                    <div className="flex shrink-0 gap-2">
                      <Button
                        size="sm"
                        loading={busy === `release-${cycle.id}`}
                        disabled={busy !== null}
                        onClick={() => release(cycle)}
                      >
                        <HandCoins data-slot="icon" />
                        {t("admin.releaseAction")}
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={busy !== null}
                        onClick={() => setRefundCycle(cycle)}
                      >
                        <Undo2 data-slot="icon" />
                        {t("admin.refundAction")}
                      </Button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {tab === "payouts" && (
        <section className="page-gutter space-y-3">
          {loading ? (
            <Skeleton className="h-64 rounded-2xl" />
          ) : payouts.length === 0 ? (
            <EmptyState icon={Banknote} title={t("dir.empty")} description="" />
          ) : (
            <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
              {payouts.map((payout, index) => (
                <div
                  key={payout.id}
                  className={cn(
                    "flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between",
                    index > 0 && "border-t",
                  )}
                >
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <p className="font-mono font-semibold tabular-nums">{formatETB(payout.amount)}</p>
                      <Badge variant="outline" className={STATUS_TONE[payout.status]}>
                        {t(`status.${payout.status}`)}
                      </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {payout.tutor_name} · {payout.bank_name}
                    </p>
                    <div className="text-xs text-muted-foreground">
                      <ContactActionCell value={payout.account_number} kind="value" />
                    </div>
                  </div>
                  <div className="flex shrink-0 gap-2">
                    {payout.status === "pending" && (
                      <>
                        <Button
                          size="sm"
                          loading={busy === `approve-${payout.id}`}
                          disabled={busy !== null}
                          onClick={() => approvePayout(payout)}
                        >
                          {t("admin.approvePayout")}
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          disabled={busy !== null}
                          loading={busy === `reverse-${payout.id}`}
                          onClick={() => reversePayout(payout)}
                        >
                          <RotateCcw data-slot="icon" />
                          {t("admin.reversePayout")}
                        </Button>
                      </>
                    )}
                    {payout.status === "approved" && (
                      <>
                        <Button size="sm" disabled={busy !== null} onClick={() => setPayTarget(payout)}>
                          <Banknote data-slot="icon" />
                          {t("status.paid")}
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          disabled={busy !== null}
                          loading={busy === `reverse-${payout.id}`}
                          onClick={() => reversePayout(payout)}
                        >
                          <RotateCcw data-slot="icon" />
                          {t("admin.reversePayout")}
                        </Button>
                      </>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* Refund sheet */}
      <ResponsiveSheet open={refundCycle !== null} onOpenChange={(open) => !open && busy !== "refund" && setRefundCycle(null)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("admin.refundAction")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-2">
            <Label htmlFor="refund-note">{t("admin.refundNote")}</Label>
            <textarea
              id="refund-note"
              rows={3}
              className={TEXTAREA_CLASS}
              value={refundNote}
              onChange={(event) => setRefundNote(event.target.value)}
            />
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              disabled={busy === "refund"}
              onClick={() => setRefundCycle(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              variant="destructive"
              className="h-11 flex-1"
              loading={busy === "refund"}
              disabled={refundNote.trim().length < 5}
              onClick={refund}
            >
              {t("admin.refundAction")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Pay payout sheet */}
      <ResponsiveSheet open={payTarget !== null} onOpenChange={(open) => !open && busy !== "pay" && setPayTarget(null)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>
              {payTarget ? `${formatETB(payTarget.amount)} → ${payTarget.tutor_name}` : ""}
            </ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-3">
            <button
              type="button"
              onClick={() => setPayMethod("chapa")}
              className={cn(
                "touch-target w-full rounded-xl border px-4 py-3 text-left text-sm font-medium transition-colors",
                payMethod === "chapa" ? "border-primary bg-primary/5 text-primary" : "bg-muted/30",
              )}
            >
              {t("admin.payViaChapa")}
            </button>
            <button
              type="button"
              onClick={() => setPayMethod("manual")}
              className={cn(
                "touch-target w-full rounded-xl border px-4 py-3 text-left text-sm font-medium transition-colors",
                payMethod === "manual" ? "border-primary bg-primary/5 text-primary" : "bg-muted/30",
              )}
            >
              {t("admin.payManual")}
            </button>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button variant="outline" className="h-11 flex-1" disabled={busy === "pay"} onClick={() => setPayTarget(null)}>
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" loading={busy === "pay"} onClick={payPayout}>
              {t("status.paid")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
