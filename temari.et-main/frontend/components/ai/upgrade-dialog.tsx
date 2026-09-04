"use client"

import { Check, Sparkles } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AiSubscriptionPlans } from "@/lib/types"
import { cn } from "@/lib/utils"

const GATEWAY_LABELS: Record<string, string> = {
  chapa: "Chapa",
  telebirr: "Telebirr",
  cbebirr: "CBE Birr",
  fake: "Test gateway",
}

/**
 * The B2C Temari AI upgrade: price + features from the backend, gateway
 * choice (Chapa / Telebirr / CBE Birr), then a redirect to the hosted
 * checkout. Pricing values come from the API — never hardcoded here.
 */
export function UpgradeDialog({
  open,
  onOpenChange,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation("ai")
  const [plans, setPlans] = useState<AiSubscriptionPlans | null>(null)
  const [gateway, setGateway] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    apiFetch<{ data: AiSubscriptionPlans }>("/me/ai/plans")
      .then((res) => {
        if (cancelled) return
        setPlans(res.data)
        setGateway(res.data.gateways[0] ?? null)
      })
      .catch(() => undefined)
    return () => {
      cancelled = true
    }
  }, [open])

  const subscribe = async () => {
    if (!gateway) return
    setBusy(true)
    try {
      const res = await apiFetch<{ data: { checkout_url: string } }>("/me/ai/subscribe", {
        method: "POST",
        body: { gateway },
      })
      window.location.href = res.data.checkout_url
    } catch (error) {
      toast.error(error instanceof Error ? error.message : t("thread.error"))
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="size-5 text-primary" />
            {t("upgrade.title")}
          </DialogTitle>
          <DialogDescription>{t("upgrade.subtitle")}</DialogDescription>
        </DialogHeader>

        {plans && (
          <div className="space-y-4">
            <p className="font-display text-2xl font-semibold tracking-tight">
              {t("upgrade.price", { price: plans.price_etb, days: plans.days })}
            </p>

            <ul className="space-y-2">
              {[0, 1, 2, 3].map((i) => (
                <li key={i} className="flex items-start gap-2 text-sm">
                  <Check className="mt-0.5 size-4 shrink-0 text-primary" />
                  {t(`upgrade.features.${i}`)}
                </li>
              ))}
            </ul>

            <div>
              <p className="mb-2 text-sm font-medium">{t("upgrade.payWith")}</p>
              <div className="flex flex-wrap gap-2">
                {plans.gateways.map((code) => (
                  <button
                    key={code}
                    type="button"
                    onClick={() => setGateway(code)}
                    className={cn(
                      "rounded-lg border px-3 py-2 text-sm transition-colors",
                      gateway === code
                        ? "border-primary bg-primary/10 font-medium"
                        : "hover:bg-accent",
                    )}
                  >
                    {GATEWAY_LABELS[code] ?? code}
                  </button>
                ))}
              </div>
            </div>

            <Button className="w-full" onClick={() => void subscribe()} loading={busy} disabled={!gateway}>
              {t("upgrade.subscribe")}
            </Button>
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
