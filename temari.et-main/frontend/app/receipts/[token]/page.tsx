"use client"

import { ShieldCheck } from "lucide-react"
import { useParams } from "next/navigation"
import { useEffect, useState } from "react"

import { ReceiptArticle } from "@/components/fees/receipt-article"
import { Logo } from "@/components/ui/logo"
import { PublicDocumentActions } from "@/components/ui/public-document-actions"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { PaymentReceipt } from "@/lib/types"

/**
 * PUBLIC payment-receipt verification page — what the QR code on a printed
 * receipt opens. No login: the unguessable token in the URL is the only key.
 */
export default function PublicReceiptPage() {
  const { t } = useTranslation("fees")
  const params = useParams<{ token: string }>()

  const [receipt, setReceipt] = useState<PaymentReceipt | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: PaymentReceipt }>(`/public/receipts/${params.token}`, { anonymous: true })
      .then((res) => !cancelled && setReceipt(res.data))
      .catch(() => !cancelled && setFailed(true))
    return () => {
      cancelled = true
    }
  }, [params.token])

  return (
    <div className="min-h-svh bg-muted/30 px-4 py-8 md:py-12">
      <div className="mx-auto max-w-2xl space-y-4">
        <div className="flex items-center justify-between gap-3 print:hidden">
          <Logo />
          {receipt && (
            <PublicDocumentActions
              downloadUrl={receipt.download_url}
              viewUrl={receipt.view_url}
              fallbackLabel={t("receipt.print")}
            />
          )}
        </div>

        {receipt !== null && (
          <p className="flex items-center gap-1.5 text-xs font-medium text-success print:hidden">
            <ShieldCheck className="size-4" />
            {t("receipt.verified")}
          </p>
        )}

        {receipt === null ? (
          failed ? (
            <div className="rounded-2xl border border-dashed bg-card px-6 py-16 text-center text-sm text-muted-foreground">
              {t("receipt.notFound")}
            </div>
          ) : (
            <Skeleton className="h-96 rounded-2xl" />
          )
        ) : (
          <ReceiptArticle receipt={receipt} />
        )}
      </div>
    </div>
  )
}
