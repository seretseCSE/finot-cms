"use client"

import { Wallet } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

interface GatewayRow {
  code: string
  label: string
  enabled: boolean
  configured: boolean
  purposes: string[]
}

interface Payload {
  gateways: GatewayRow[]
  purposes: { value: string; label: string }[]
  marketplace: {
    commission_percent: number
    boost_weekly_price: number
    boost_monthly_price: number
    auto_release_days: number | null
  }
}

/**
 * The operator's gateway matrix (enable per gateway + what each may
 * collect) and the marketplace money knobs. Credentials are env-only —
 * this page shows configured/missing, never a secret.
 */
export default function PaymentGatewaysPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")

  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [gateways, setGateways] = useState<GatewayRow[]>([])
  const [purposes, setPurposes] = useState<{ value: string; label: string }[]>([])
  const [commission, setCommission] = useState("")
  const [boostWeekly, setBoostWeekly] = useState("")
  const [boostMonthly, setBoostMonthly] = useState("")
  const [autoRelease, setAutoRelease] = useState("")

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiFetch<{ data: Payload }>("/payment-gateways")
      setGateways(res.data.gateways)
      setPurposes(res.data.purposes)
      setCommission(String(res.data.marketplace.commission_percent))
      setBoostWeekly(String(res.data.marketplace.boost_weekly_price))
      setBoostMonthly(String(res.data.marketplace.boost_monthly_price))
      setAutoRelease(
        res.data.marketplace.auto_release_days !== null ? String(res.data.marketplace.auto_release_days) : "",
      )
    } catch {
      // skeleton stays
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

  async function save() {
    setSaving(true)
    try {
      await apiFetch("/payment-gateways", {
        method: "PUT",
        body: JSON.stringify({
          gateways: Object.fromEntries(
            gateways.map((gateway) => [
              gateway.code,
              { enabled: gateway.enabled, purposes: gateway.purposes },
            ]),
          ),
          marketplace: {
            commission_percent: Number(commission),
            boost_weekly_price: Number(boostWeekly),
            boost_monthly_price: Number(boostMonthly),
            auto_release_days: autoRelease === "" ? null : Number(autoRelease),
          },
        }),
      })
      toast.success(t("admin.saved"))
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("admin.gatewaysTitle")}
        description={t("admin.gatewaysDesc")}
        actions={
          <Button loading={saving} onClick={save} disabled={loading}>
            {tc("actions.save")}
          </Button>
        }
      />

      {loading ? (
        <div className="page-gutter space-y-3">
          <Skeleton className="h-64 rounded-2xl" />
          <Skeleton className="h-40 rounded-2xl" />
        </div>
      ) : (
        <>
          <section className="page-gutter space-y-3">
            {gateways.map((gateway) => (
              <div key={gateway.code} className="space-y-3 rounded-2xl border bg-card p-5 shadow-xs">
                <div className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-xl bg-accent">
                      <Wallet className="size-5" strokeWidth={1.75} />
                    </div>
                    <div>
                      <p className="font-medium">{gateway.label}</p>
                      <Badge
                        variant="outline"
                        className={cn(
                          gateway.configured
                            ? "border-success/30 bg-success/10 text-success"
                            : "border-warning/30 bg-warning/10 text-warning",
                        )}
                      >
                        {t("admin.configured")}: {gateway.configured ? "✓" : t("admin.notConfigured")}
                      </Badge>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    <Label htmlFor={`enabled-${gateway.code}`} className="text-sm text-muted-foreground">
                      {t("admin.enabled")}
                    </Label>
                    <Switch
                      id={`enabled-${gateway.code}`}
                      checked={gateway.enabled}
                      onCheckedChange={(checked) =>
                        setGateways((prev) =>
                          prev.map((g) => (g.code === gateway.code ? { ...g, enabled: checked } : g)),
                        )
                      }
                    />
                  </div>
                </div>

                <div className="space-y-1.5">
                  <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("admin.purposes")}
                  </p>
                  <div className="flex flex-wrap gap-x-5 gap-y-2">
                    {purposes.map((purpose) => {
                      const checked = gateway.purposes.includes(purpose.value)
                      return (
                        <label key={purpose.value} className="flex cursor-pointer items-center gap-2 text-sm">
                          <Checkbox
                            checked={checked}
                            onCheckedChange={(value) =>
                              setGateways((prev) =>
                                prev.map((g) =>
                                  g.code === gateway.code
                                    ? {
                                        ...g,
                                        purposes: value
                                          ? [...g.purposes, purpose.value]
                                          : g.purposes.filter((p) => p !== purpose.value),
                                      }
                                    : g,
                                ),
                              )
                            }
                          />
                          {t(`admin.purpose.${purpose.value}`)}
                        </label>
                      )
                    })}
                  </div>
                </div>
              </div>
            ))}
          </section>

          <section className="page-gutter pb-8">
            <div className="space-y-4 rounded-2xl border bg-card p-5 shadow-xs">
              <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t("admin.marketplaceSettings")}
              </h2>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="space-y-2">
                  <Label htmlFor="commission">{t("admin.commissionPercent")}</Label>
                  <Input
                    id="commission"
                    type="number"
                    min={0}
                    max={50}
                    step={0.5}
                    className="no-spinner"
                    value={commission}
                    onChange={(e) => setCommission(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="boost-weekly">{t("admin.boostWeeklyPrice")}</Label>
                  <Input
                    id="boost-weekly"
                    type="number"
                    min={0}
                    className="no-spinner"
                    value={boostWeekly}
                    onChange={(e) => setBoostWeekly(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="boost-monthly">{t("admin.boostMonthlyPrice")}</Label>
                  <Input
                    id="boost-monthly"
                    type="number"
                    min={0}
                    className="no-spinner"
                    value={boostMonthly}
                    onChange={(e) => setBoostMonthly(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="auto-release">{t("admin.autoRelease")}</Label>
                  <Input
                    id="auto-release"
                    type="number"
                    min={0}
                    max={30}
                    className="no-spinner"
                    value={autoRelease}
                    onChange={(e) => setAutoRelease(e.target.value)}
                  />
                  <p className="text-xs text-muted-foreground">{t("admin.autoReleaseHint")}</p>
                </div>
              </div>
            </div>
          </section>
        </>
      )}
    </div>
  )
}
