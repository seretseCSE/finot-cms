"use client"

import { CheckCircle2, Clock, XCircle } from "lucide-react"
import Link from "next/link"
import { useRouter, useSearchParams } from "next/navigation"
import { Suspense, useCallback, useEffect, useRef, useState } from "react"

import { Button } from "@/components/ui/button"
import { Logo } from "@/components/ui/logo"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { formatETB } from "@/lib/utils"

interface TxStatus {
  tx_ref: string
  gateway_label: string
  purpose: string
  amount: string
  status: string
  failure_reason: string | null
}

/**
 * The gateway return page: polls the transaction (each poll triggers a
 * server-side verify) until it settles. No app shell — the payer may arrive
 * from an external checkout tab.
 */
function PayReturnInner() {
  const { t } = useTranslation("tutoring")
  const search = useSearchParams()
  const router = useRouter()
  const txRef = search.get("tx_ref")

  const [tx, setTx] = useState<TxStatus | null>(null)
  const [checking, setChecking] = useState(true)
  const [tick, setTick] = useState(0)
  const attempts = useRef(0)

  const check = useCallback(async () => {
    if (!txRef) return
    try {
      const res = await apiFetch<{ data: TxStatus }>(`/payments/transactions/${txRef}`)
      setTx(res.data)
      // Keep polling while pending (gateway settlement can lag a few seconds).
      if ((res.data.status === "pending" || res.data.status === "initiated") && attempts.current < 6) {
        attempts.current += 1
        setTimeout(() => setTick((value) => value + 1), 2500)
      }
    } catch {
      // Unauthenticated or unknown ref — send them to sign in.
      router.replace("/login")
    } finally {
      setChecking(false)
    }
  }, [txRef, router])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!cancelled) await check()
    })()
    return () => {
      cancelled = true
    }
  }, [check, tick])

  const destination = tx?.purpose === "profile_boost" ? "/tutoring/earnings" : "/me/tutoring"

  return (
    <main className="flex min-h-dvh items-center justify-center bg-background p-4">
      <div className="w-full max-w-sm space-y-6 rounded-2xl border bg-card p-6 text-center shadow-xs">
        <div className="flex justify-center">
          <Logo />
        </div>

        {!tx ? (
          <div className="space-y-3">
            <Skeleton className="mx-auto h-10 w-10 rounded-full" />
            <p className="text-sm text-muted-foreground">{t("pay.checking")}</p>
          </div>
        ) : tx.status === "paid" ? (
          <div className="space-y-2">
            <CheckCircle2 className="mx-auto size-12 text-success" strokeWidth={1.75} />
            <h1 className="font-display text-xl font-semibold">{t("pay.success")}</h1>
            <p className="font-mono text-2xl font-semibold tabular-nums">{formatETB(tx.amount)}</p>
            <p className="text-sm text-muted-foreground">
              {tx.gateway_label} · {tx.tx_ref}
            </p>
            <p className="text-sm text-muted-foreground">{t("pay.successDesc")}</p>
          </div>
        ) : tx.status === "failed" || tx.status === "canceled" ? (
          <div className="space-y-2">
            <XCircle className="mx-auto size-12 text-destructive" strokeWidth={1.75} />
            <h1 className="font-display text-xl font-semibold">{t("pay.failed")}</h1>
            {tx.failure_reason && <p className="text-sm text-muted-foreground">{tx.failure_reason}</p>}
            <p className="text-sm text-muted-foreground">{t("pay.failedDesc")}</p>
          </div>
        ) : (
          <div className="space-y-2">
            <Clock className="mx-auto size-12 text-warning" strokeWidth={1.75} />
            <h1 className="font-display text-xl font-semibold">{t("pay.pending")}</h1>
            <p className="text-sm text-muted-foreground">{t("pay.pendingDesc")}</p>
            <Button
              variant="outline"
              className="mt-2"
              loading={checking}
              onClick={() => {
                setChecking(true)
                void check()
              }}
            >
              {t("pay.checkAgain")}
            </Button>
          </div>
        )}

        <Button asChild className="h-11 w-full">
          <Link href={destination}>{t("pay.backToApp")}</Link>
        </Button>
      </div>
    </main>
  )
}

export default function PayReturnPage() {
  return (
    <Suspense fallback={null}>
      <PayReturnInner />
    </Suspense>
  )
}
