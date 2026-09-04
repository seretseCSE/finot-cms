"use client"

import { ArrowDownToLine, Rocket, Wallet } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
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
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn, formatETB } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

interface LedgerEntry {
  id: number
  entry_type: string
  amount: string
  balance_after: string
  memo: string | null
  created_at: string
}

interface Payout {
  id: number
  amount: string
  method: string
  status: string
  bank_name: string | null
  account_number: string | null
  failure_reason: string | null
  created_at: string
}

interface EarningsPayload {
  wallet_balance: string
  payout_account: {
    bank_code: string | null
    bank_name: string | null
    account_number: string | null
    account_name: string | null
  }
  ledger: LedgerEntry[]
  payouts: Payout[]
}

interface BoostPlans {
  plans: { plan: string; days: number; price: number }[]
  boosted_until: string | null
  gateways: { code: string; label: string }[]
}

const PAYOUT_TONE: Record<string, string> = {
  pending: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-info/30 bg-info/10 text-info",
  paid: "border-success/30 bg-success/10 text-success",
  failed: "border-destructive/30 bg-destructive/10 text-destructive",
  canceled: "border-border bg-muted text-muted-foreground",
}

export default function TutorEarningsPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")

  const [loading, setLoading] = useState(true)
  const [data, setData] = useState<EarningsPayload | null>(null)
  const [boost, setBoost] = useState<BoostPlans | null>(null)

  // Payout account form.
  const [bankName, setBankName] = useState("")
  const [bankCode, setBankCode] = useState("")
  const [accountNumber, setAccountNumber] = useState("")
  const [accountName, setAccountName] = useState("")
  const [savingAccount, setSavingAccount] = useState(false)

  // Payout request.
  const [payoutOpen, setPayoutOpen] = useState(false)
  const [payoutAmount, setPayoutAmount] = useState("")
  const [requesting, setRequesting] = useState(false)

  // Boost purchase.
  const [boostOpen, setBoostOpen] = useState(false)
  const [boostPlan, setBoostPlan] = useState("weekly")
  const [boostGateway, setBoostGateway] = useState("")
  const [buying, setBuying] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [earningsRes, boostRes] = await Promise.all([
        apiFetch<{ data: EarningsPayload }>("/tutoring/earnings"),
        apiFetch<{ data: BoostPlans }>("/tutoring/boosts/plans"),
      ])
      setData(earningsRes.data)
      setBoost(boostRes.data)
      setBankName(earningsRes.data.payout_account.bank_name ?? "")
      setBankCode(earningsRes.data.payout_account.bank_code ?? "")
      setAccountNumber(earningsRes.data.payout_account.account_number ?? "")
      setAccountName(earningsRes.data.payout_account.account_name ?? "")
      setBoostGateway(boostRes.data.gateways[0]?.code ?? "")
    } catch {
      // empty state
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!cancelled) await load()
    })()
    return () => {
      cancelled = true
    }
  }, [load])

  async function saveAccount() {
    setSavingAccount(true)
    try {
      await apiFetch("/tutoring/profile/payout-account", {
        method: "PUT",
        body: JSON.stringify({
          payout_bank_code: bankCode,
          payout_bank_name: bankName,
          payout_account_number: accountNumber,
          payout_account_name: accountName,
        }),
      })
      toast.success(tc("actions.saved"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setSavingAccount(false)
    }
  }

  async function requestPayout() {
    setRequesting(true)
    try {
      await apiFetch("/tutoring/payouts", {
        method: "POST",
        body: JSON.stringify({ amount: Number(payoutAmount) }),
      })
      toast.success(t("workspace.payoutRequested"))
      setPayoutOpen(false)
      setPayoutAmount("")
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setRequesting(false)
    }
  }

  async function buyBoost() {
    setBuying(true)
    try {
      const res = await apiFetch<{ data: { checkout_url: string } }>("/tutoring/boosts", {
        method: "POST",
        body: JSON.stringify({ plan: boostPlan, gateway: boostGateway }),
      })
      window.location.href = res.data.checkout_url
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
      setBuying(false)
    }
  }

  if (loading || !data) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("workspace.earnings")} backHref="/tutoring" />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("workspace.earnings")}
        description={t("workspace.earningsDesc")}
        backHref="/tutoring"
        actions={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setBoostOpen(true)}>
              <Rocket data-slot="icon" />
              {t("workspace.boostCta")}
            </Button>
            <Button onClick={() => setPayoutOpen(true)}>
              <ArrowDownToLine data-slot="icon" />
              {t("workspace.requestPayout")}
            </Button>
          </div>
        }
      />

      <section className="page-gutter grid gap-3 sm:grid-cols-2">
        <StatCard icon={Wallet} label={t("workspace.wallet")} value={formatETB(data.wallet_balance)} />
        <div className="space-y-3 rounded-2xl border bg-card p-4 shadow-xs">
          <div>
            <p className="text-sm font-medium">{t("workspace.payoutAccount")}</p>
            <p className="text-xs text-muted-foreground">{t("workspace.payoutAccountDesc")}</p>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <Input placeholder={t("workspace.bankName")} value={bankName} onChange={(e) => setBankName(e.target.value)} />
            <Input placeholder={t("workspace.bankCode")} value={bankCode} onChange={(e) => setBankCode(e.target.value)} />
            <Input
              placeholder={t("workspace.accountNumber")}
              value={accountNumber}
              onChange={(e) => setAccountNumber(e.target.value)}
            />
            <Input
              placeholder={t("workspace.accountName")}
              value={accountName}
              onChange={(e) => setAccountName(e.target.value)}
            />
          </div>
          <Button
            size="sm"
            variant="outline"
            loading={savingAccount}
            disabled={!bankName || !bankCode || !accountNumber || !accountName}
            onClick={saveAccount}
          >
            {t("workspace.savePayoutAccount")}
          </Button>
        </div>
      </section>

      {data.payouts.length > 0 && (
        <section className="page-gutter space-y-3">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {t("workspace.payouts")}
          </h2>
          <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            {data.payouts.map((payout, index) => (
              <div key={payout.id} className={cn("flex items-center justify-between gap-3 p-4", index > 0 && "border-t")}>
                <div className="min-w-0">
                  <p className="font-mono font-semibold tabular-nums">{formatETB(payout.amount)}</p>
                  <p className="text-sm text-muted-foreground">
                    {payout.bank_name} · {fmtDate(payout.created_at)}
                  </p>
                  {payout.failure_reason && (
                    <p className="text-xs text-destructive">{payout.failure_reason}</p>
                  )}
                </div>
                <Badge variant="outline" className={PAYOUT_TONE[payout.status]}>
                  {t(`status.${payout.status}`)}
                </Badge>
              </div>
            ))}
          </div>
        </section>
      )}

      <section className="page-gutter space-y-3 pb-8">
        <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("workspace.ledger")}
        </h2>
        {data.ledger.length === 0 ? (
          <EmptyState icon={Wallet} title={t("workspace.emptyEngagements")} description={t("workspace.emptyEngagementsDesc")} />
        ) : (
          <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            {data.ledger.map((entry, index) => (
              <div key={entry.id} className={cn("flex items-center justify-between gap-3 p-4", index > 0 && "border-t")}>
                <div className="min-w-0">
                  <p className="text-sm font-medium">{t(`workspace.entry.${entry.entry_type}`)}</p>
                  <p className="truncate text-xs text-muted-foreground">
                    {entry.memo} · {fmtDate(entry.created_at)}
                  </p>
                </div>
                <div className="shrink-0 text-right">
                  <p
                    className={cn(
                      "font-mono font-semibold tabular-nums",
                      Number(entry.amount) >= 0 ? "text-success" : "text-destructive",
                    )}
                  >
                    {Number(entry.amount) >= 0 ? "+" : ""}
                    {formatETB(entry.amount)}
                  </p>
                  <p className="font-mono text-xs text-muted-foreground tabular-nums">
                    {formatETB(entry.balance_after)}
                  </p>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Payout request sheet */}
      <ResponsiveSheet open={payoutOpen} onOpenChange={(open) => !open && !requesting && setPayoutOpen(false)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("workspace.requestPayout")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <div className="rounded-2xl border bg-muted/30 p-4 text-center">
              <p className="text-sm text-muted-foreground">{t("workspace.wallet")}</p>
              <p className="font-mono text-2xl font-semibold tabular-nums">{formatETB(data.wallet_balance)}</p>
            </div>
            <div className="space-y-2">
              <Label htmlFor="payout-amount">{t("workspace.payoutAmount")}</Label>
              <Input
                id="payout-amount"
                type="number"
                min={50}
                max={Number(data.wallet_balance)}
                className="no-spinner"
                value={payoutAmount}
                onChange={(event) => setPayoutAmount(event.target.value)}
              />
            </div>
            {!data.payout_account.account_number && (
              <p className="text-sm text-warning">{t("workspace.noPayoutAccount")}</p>
            )}
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button variant="outline" className="h-11 flex-1" disabled={requesting} onClick={() => setPayoutOpen(false)}>
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              loading={requesting}
              disabled={
                !payoutAmount ||
                Number(payoutAmount) < 50 ||
                Number(payoutAmount) > Number(data.wallet_balance) ||
                !data.payout_account.account_number
              }
              onClick={requestPayout}
            >
              {t("workspace.requestPayout")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Boost sheet */}
      <ResponsiveSheet open={boostOpen} onOpenChange={(open) => !open && !buying && setBoostOpen(false)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("workspace.boostTitle")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <p className="text-sm text-muted-foreground">{t("workspace.boostDesc")}</p>
            {boost?.boosted_until && (
              <p className="rounded-xl bg-success/10 px-3 py-2 text-sm text-success">
                {t("workspace.boostedUntil", { date: fmtDate(boost.boosted_until) })}
              </p>
            )}
            <div className="grid grid-cols-2 gap-2">
              {(boost?.plans ?? []).map((plan) => (
                <button
                  key={plan.plan}
                  type="button"
                  onClick={() => setBoostPlan(plan.plan)}
                  className={cn(
                    "rounded-xl border p-4 text-center transition-colors",
                    boostPlan === plan.plan ? "border-primary bg-primary/5" : "bg-muted/30 hover:bg-accent/50",
                  )}
                >
                  <p className="text-sm font-medium">
                    {plan.plan === "weekly" ? t("workspace.boostWeekly") : t("workspace.boostMonthly")}
                  </p>
                  <p className="font-mono text-lg font-semibold tabular-nums">{formatETB(plan.price)}</p>
                </button>
              ))}
            </div>
            <div className="space-y-2">
              <p className="text-sm font-medium">{t("family.payWith")}</p>
              <div className="grid gap-2">
                {(boost?.gateways ?? []).map((option) => (
                  <button
                    key={option.code}
                    type="button"
                    onClick={() => setBoostGateway(option.code)}
                    className={cn(
                      "touch-target rounded-xl border px-4 py-3 text-left text-sm font-medium transition-colors",
                      boostGateway === option.code
                        ? "border-primary bg-primary/5 text-primary"
                        : "bg-muted/30 hover:bg-accent/50",
                    )}
                  >
                    {option.label}
                  </button>
                ))}
              </div>
            </div>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button variant="outline" className="h-11 flex-1" disabled={buying} onClick={() => setBoostOpen(false)}>
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" loading={buying} disabled={!boostGateway} onClick={buyBoost}>
              {t("workspace.buyBoost")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
