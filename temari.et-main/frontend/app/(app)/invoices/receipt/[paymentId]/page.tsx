"use client"

import { useParams } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { ReceiptArticle } from "@/components/fees/receipt-article"
import {
  DocumentDownloadButton,
  DocumentPrintButton,
} from "@/components/ui/document-download-button"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { PaymentReceipt } from "@/lib/types"

/**
 * Staff print page for one payment's official receipt. The QR code opens the
 * public verification copy.
 */
export default function PaymentReceiptPage() {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ paymentId: string }>()

  const [receipt, setReceipt] = useState<PaymentReceipt | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: PaymentReceipt }>(`/payments/${params.paymentId}/receipt`)
      .then((res) => !cancelled && setReceipt(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setFailed(true)
      })
    return () => {
      cancelled = true
    }
  }, [params.paymentId, tc])

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("receipt.title")}
        backHref="/invoices"
        actions={
          receipt ? (
            <div className="flex items-center gap-2">
              <DocumentDownloadButton
                type="payment_receipt"
                subjectId={Number(params.paymentId)}
              />
              <DocumentPrintButton
                type="payment_receipt"
                subjectId={Number(params.paymentId)}
                variant="default"
                label={t("receipt.print")}
              />
            </div>
          ) : null
        }
      />
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
  )
}
