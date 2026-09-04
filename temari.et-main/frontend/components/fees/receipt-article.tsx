"use client"

import { generate } from "lean-qr"
import { toSvgDataURL } from "lean-qr/extras/svg"
import { useEffect, useState } from "react"

import { Logo } from "@/components/ui/logo"
import { useTranslation } from "@/lib/i18n"
import type { PaymentReceipt } from "@/lib/types"

/** Public verification URL the receipt's QR code points at. */
export function receiptPublicUrl(token: string): string {
  return `${window.location.origin}/receipts/${token}`
}

function etb(value: string | number | null): string {
  if (value === null) return "—"
  return `${Number(value).toLocaleString()} ETB`
}

/**
 * The printable official payment receipt — shared by the staff print page
 * and the PUBLIC verification page its QR code opens. Anyone scanning the
 * QR sees this exact copy straight from the platform, so a doctored paper
 * receipt can always be checked against the record.
 */
export function ReceiptArticle({ receipt }: { receipt: PaymentReceipt }) {
  const { t } = useTranslation("fees")

  // QR encodes the public receipt URL — window is only known client-side.
  const [qr, setQr] = useState<string | null>(null)
  useEffect(() => {
    if (!receipt.public_token) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- QR needs window.origin (client only)
    setQr(
      toSvgDataURL(generate(receiptPublicUrl(receipt.public_token)), {
        on: "#000000",
        off: "#ffffff",
        pad: 2,
      }),
    )
  }, [receipt.public_token])

  return (
    <>
      <style>{`@media print {
        body * { visibility: hidden; }
        #payment-receipt, #payment-receipt * { visibility: visible; }
        #payment-receipt { position: absolute; inset: 0; margin: 0; border: none; box-shadow: none; }
      }`}</style>

      <article
        id="payment-receipt"
        className="mx-auto max-w-2xl rounded-2xl border bg-card p-8 shadow-xs md:p-10"
      >
        <header className="flex items-start justify-between gap-4 border-b pb-5">
          <div>
            <h2 className="font-display text-xl font-semibold">{receipt.school}</h2>
            <p className="text-sm text-muted-foreground">{receipt.branch}</p>
          </div>
          <div className="text-right">
            <Logo className="ml-auto" />
            <p className="mt-1.5 font-mono text-sm font-semibold">{receipt.receipt_number}</p>
          </div>
        </header>

        <h3 className="mt-5 text-center text-sm font-semibold tracking-widest uppercase">
          {t("receipt.title")}
        </h3>

        <dl className="mt-5 space-y-2.5 text-sm">
          {[
            [t("receipt.student"), receipt.student.full_name],
            [t("receipt.studentId"), receipt.student.public_id],
            [t("receipt.for"), `${receipt.invoice_title ?? "—"} (${receipt.invoice_number})`],
            [t("receipt.method"), receipt.method_label],
            [t("payments.reference"), receipt.reference],
            [
              t("payments.receivedInto"),
              receipt.bank_account
                ? `${receipt.bank_account.bank_name ?? ""} ${receipt.bank_account.account_number}`.trim()
                : null,
            ],
            [t("receipt.paidOn"), receipt.paid_at],
            [t("receipt.recordedBy"), receipt.recorded_by],
          ]
            .filter(([, value]) => value)
            .map(([label, value]) => (
              <div key={String(label)} className="flex items-baseline justify-between gap-4">
                <dt className="shrink-0 text-muted-foreground">{label}</dt>
                <dd className="min-w-0 text-right font-medium">{value}</dd>
              </div>
            ))}
        </dl>

        <div className="mt-5 rounded-xl bg-muted/40 px-4 py-3 text-center">
          <p className="text-xs text-muted-foreground uppercase tracking-wide">
            {t("receipt.amountPaid")}
          </p>
          <p className="mt-0.5 font-display text-2xl font-bold tabular-nums">
            {etb(receipt.amount)}
          </p>
          {receipt.invoice_total_due !== null && (
            <p className="mt-1 text-xs text-muted-foreground">
              {t("receipt.invoiceProgress", {
                paid: etb(receipt.invoice_amount_paid),
                total: etb(receipt.invoice_total_due),
              })}
            </p>
          )}
        </div>

        <footer className="mt-6 flex items-end justify-between gap-4 border-t pt-5">
          <p className="max-w-[24rem] text-[11px] leading-relaxed text-muted-foreground">
            {t("receipt.verifyHint")}
          </p>
          {qr && (
            // eslint-disable-next-line @next/next/no-img-element -- data URL QR
            <img src={qr} alt={t("receipt.qrAlt")} className="size-24 shrink-0" />
          )}
        </footer>
      </article>
    </>
  )
}
