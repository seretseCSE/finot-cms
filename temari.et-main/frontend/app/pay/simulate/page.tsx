"use client"

import { FlaskConical } from "lucide-react"
import { useRouter, useSearchParams } from "next/navigation"
import { Suspense, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Logo } from "@/components/ui/logo"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"

/**
 * The fake-gateway checkout (staging/local only — the backend refuses it in
 * production): pick the outcome, then land on the same return page a real
 * gateway would redirect to.
 */
function PaySimulateInner() {
  const { t } = useTranslation("tutoring")
  const search = useSearchParams()
  const router = useRouter()
  const txRef = search.get("tx_ref")
  const returnUrl = search.get("return")
  const [busy, setBusy] = useState<string | null>(null)

  async function decide(outcome: "paid" | "failed") {
    if (!txRef) return
    setBusy(outcome)
    try {
      await apiFetch(`/payments/simulate/${txRef}`, {
        method: "POST",
        body: JSON.stringify({ outcome }),
      })
      router.replace(returnUrl ?? `/pay/return?tx_ref=${txRef}`)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
      setBusy(null)
    }
  }

  return (
    <main className="flex min-h-dvh items-center justify-center bg-background p-4">
      <div className="w-full max-w-sm space-y-6 rounded-2xl border bg-card p-6 text-center shadow-xs">
        <div className="flex justify-center">
          <Logo />
        </div>
        <div className="space-y-2">
          <FlaskConical className="mx-auto size-10 text-info" strokeWidth={1.75} />
          <h1 className="font-display text-xl font-semibold">{t("pay.simulator")}</h1>
          <p className="text-sm text-muted-foreground">{t("pay.simulatorDesc")}</p>
        </div>
        <div className="space-y-2">
          <Button
            className="h-11 w-full"
            loading={busy === "paid"}
            disabled={busy === "failed"}
            onClick={() => decide("paid")}
          >
            {t("pay.simulatePaid")}
          </Button>
          <Button
            variant="outline"
            className="h-11 w-full"
            loading={busy === "failed"}
            disabled={busy === "paid"}
            onClick={() => decide("failed")}
          >
            {t("pay.simulateFailed")}
          </Button>
        </div>
      </div>
    </main>
  )
}

export default function PaySimulatePage() {
  return (
    <Suspense fallback={null}>
      <PaySimulateInner />
    </Suspense>
  )
}
