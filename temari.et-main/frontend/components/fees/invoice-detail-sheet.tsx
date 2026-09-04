"use client"

import {
  CheckCircle2,
  CircleAlert,
  Clock3,
  ExternalLink,
  FileText,
  HandCoins,
  Hash,
  Link2,
  Loader2,
  Receipt,
  ShieldAlert,
  ShieldCheck,
  ShieldX,
} from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Invoice, InvoiceStatus, InvoiceVerification } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDate, fmtDateTime } from "@/lib/dates"

const STATUS_VARIANT: Record<InvoiceStatus, "default" | "secondary" | "outline"> = {
  paid: "default",
  partial: "secondary",
  unpaid: "outline",
  scholarship: "secondary",
  void: "secondary",
}

const VERIFICATION_TONE: Record<InvoiceVerification["status"], string> = {
  verified: "border-success/30 bg-success/10 text-success",
  needs_review: "border-warning/30 bg-warning/10 text-warning",
  failed: "border-destructive/30 bg-destructive/10 text-destructive",
}

const VERIFICATION_ICON = {
  verified: CheckCircle2,
  needs_review: Clock3,
  failed: CircleAlert,
} as const

const METHOD_ICON = {
  reference: Hash,
  link: Link2,
  file: FileText,
} as const

function etb(value: string | number): string {
  return `${Number(value).toLocaleString()} ETB`
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-4 py-1">
      <span className="shrink-0 text-sm text-muted-foreground">{label}</span>
      <span className="min-w-0 text-right text-sm font-medium">{value ?? "—"}</span>
    </div>
  )
}

/** Compact evidence line inside the check.et findings block. */
function Fact({ label, value }: { label: string; value: React.ReactNode }) {
  if (value === null || value === undefined || value === "") return null
  return (
    <div className="flex items-baseline justify-between gap-3">
      <span className="shrink-0 text-[11px] tracking-wide text-muted-foreground uppercase">
        {label}
      </span>
      <span className="min-w-0 text-right text-xs font-medium">{value}</span>
    </div>
  )
}

interface Props {
  invoice: Invoice | null
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Offer the settle/review actions from inside the sheet (payments.record). */
  canRecordPayment?: boolean
  /** Offer penalty waiving (fees.manage). */
  canManageFees?: boolean
  onRecordPayment?: (invoice: Invoice) => void
  /** Fired after a review action changes the invoice (confirm/reject). */
  onChanged?: (invoice: Invoice) => void
}

/**
 * The invoice's full story in one sheet: number, who and what for, the money
 * math (gross → discount → payable → paid → balance), every recorded payment
 * with its transaction reference and collection account, and the finance
 * review lane for family payment submissions — what was submitted, what
 * check.et found in bank records, fraud signals (duplicate transaction
 * numbers), and confirm/reject resolution.
 */
export function InvoiceDetailSheet({
  invoice,
  open,
  onOpenChange,
  canRecordPayment = false,
  canManageFees = false,
  onRecordPayment,
  onChanged,
}: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")

  const [detail, setDetail] = useState<Invoice | null>(null)
  const [verifications, setVerifications] = useState<InvoiceVerification[] | null>(null)

  // Review dialogs.
  const [confirming, setConfirming] = useState<InvoiceVerification | null>(null)
  const [confirmAmount, setConfirmAmount] = useState("")
  const [confirmAccountId, setConfirmAccountId] = useState("")
  const [confirmNote, setConfirmNote] = useState("")
  const [rejecting, setRejecting] = useState<InvoiceVerification | null>(null)
  const [rejectReason, setRejectReason] = useState("")
  const [working, setWorking] = useState(false)

  const load = useCallback(() => {
    if (!invoice) return
    let cancelled = false
    apiFetch<{ data: Invoice }>(`/invoices/${invoice.id}`)
      .then((res) => !cancelled && setDetail(res.data))
      .catch(() => !cancelled && setDetail(invoice))
    apiFetch<{ data: InvoiceVerification[] }>(`/invoices/${invoice.id}/verifications`)
      .then((res) => !cancelled && setVerifications(res.data))
      .catch(() => !cancelled && setVerifications([]))
    return () => {
      cancelled = true
    }
  }, [invoice])

  useEffect(() => {
    if (!open || !invoice) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale detail on close
      setDetail(null)
      setVerifications(null)
      return
    }
    return load()
  }, [open, invoice, load])

  if (!invoice) return null

  const row = detail ?? invoice
  const discounted = row.net_amount != null && row.net_amount !== row.amount
  const openBill = row.status === "unpaid" || row.status === "partial"

  function openConfirm(verification: InvoiceVerification) {
    const balance = Number(row.balance)
    const evidenceAmount = Number(verification.evidence?.amount ?? NaN)
    const prefill = Number.isFinite(evidenceAmount) ? Math.min(evidenceAmount, balance) : balance
    setConfirmAmount(prefill > 0 ? String(prefill) : "")
    setConfirmAccountId("")
    setConfirmNote("")
    setConfirming(verification)
  }

  async function submitConfirm() {
    if (!invoice || !confirming) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: Invoice }>(
        `/invoices/${invoice.id}/verifications/${confirming.id}/confirm`,
        {
          method: "POST",
          body: {
            amount: confirmAmount ? Number(confirmAmount) : undefined,
            bank_account_id: confirmAccountId ? Number(confirmAccountId) : undefined,
            note: confirmNote || undefined,
          },
        },
      )
      toast.success(t("invoices.verifications.confirmed"))
      setConfirming(null)
      setDetail(res.data)
      onChanged?.(res.data)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  async function submitReject() {
    if (!invoice || !rejecting || !rejectReason.trim()) return
    setWorking(true)
    try {
      await apiFetch(`/invoices/${invoice.id}/verifications/${rejecting.id}/reject`, {
        method: "POST",
        body: { reason: rejectReason.trim() },
      })
      toast.success(t("invoices.verifications.rejected"))
      setRejecting(null)
      setRejectReason("")
      if (detail) onChanged?.(detail)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  async function waivePenalty() {
    if (!invoice) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: Invoice }>(`/invoices/${invoice.id}/waive-penalty`, {
        method: "POST",
        body: {},
      })
      toast.success(t("invoices.penaltyWaived"))
      setDetail(res.data)
      onChanged?.(res.data)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  function verificationCard(verification: InvoiceVerification) {
    const Icon = VERIFICATION_ICON[verification.status]
    const MethodIcon =
      METHOD_ICON[verification.method as keyof typeof METHOD_ICON] ?? Hash
    const receipt = verification.receipt_file_url ?? verification.receipt_url
    const evidence = verification.evidence
    const hasEvidence =
      evidence != null &&
      !evidence.unavailable &&
      (evidence.amount != null ||
        evidence.payer_name != null ||
        evidence.receiver_account != null)
    const fraud =
      (verification.duplicate_claims ?? 0) > 0 || verification.already_paid_with === true

    return (
      <li key={verification.id} className="overflow-hidden rounded-xl border">
        {/* Header: verdict + when + who */}
        <div className="flex flex-wrap items-center gap-2 border-b bg-muted/20 px-3 py-2">
          <Badge
            variant="outline"
            className={cn("rounded-full", VERIFICATION_TONE[verification.status])}
          >
            <Icon className="size-3" />
            {t(`invoices.verifications.statuses.${verification.status}`)}
          </Badge>
          <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <MethodIcon className="size-3" />
            {t(`invoices.verifications.methods.${verification.method ?? "reference"}`)}
          </span>
          <span className="ml-auto text-xs text-muted-foreground">
            {fmtDateTime(verification.created_at)}
          </span>
        </div>

        <div className="space-y-2.5 p-3">
          {/* What the family submitted */}
          <div className="space-y-1 text-xs text-muted-foreground">
            {verification.submitted_by ? (
              <p>
                {t("invoices.verifications.submittedBy")}:{" "}
                <span className="font-medium text-foreground">
                  {verification.submitted_by}
                </span>
                {verification.submitted_by_phone ? ` · ${verification.submitted_by_phone}` : ""}
              </p>
            ) : null}
            {verification.transaction_number ? (
              <p className="flex items-center justify-between gap-2">
                {t("payments.reference")}
                {verification.bank_code ? ` (${verification.bank_code.toUpperCase()})` : ""}
                <ContactActionCell
                  kind="value"
                  value={verification.transaction_number}
                  triggerClassName="text-xs"
                />
              </p>
            ) : null}
            {receipt ? (
              <a
                href={receipt}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
              >
                <ExternalLink className="size-3" />
                {t("invoices.verifications.viewReceipt")}
              </a>
            ) : null}
          </div>

          {/* What check.et found in bank records */}
          {evidence?.unavailable ? (
            <p className="rounded-lg bg-warning/10 px-2.5 py-2 text-xs text-warning">
              {t("invoices.verifications.providerUnavailable")}
              {evidence.provider_message ? ` — ${evidence.provider_message}` : ""}
            </p>
          ) : hasEvidence ? (
            <div className="space-y-1 rounded-lg bg-muted/40 p-2.5">
              <p className="mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                <ShieldCheck className="size-3.5" />
                {t("invoices.verifications.evidenceTitle")}
              </p>
              <Fact
                label={t("invoices.verifications.evidenceAmount")}
                value={
                  evidence!.amount != null ? (
                    <span
                      className={cn(
                        "tabular-nums",
                        Number(evidence!.amount) > Number(row.balance) &&
                          openBill &&
                          "text-warning",
                      )}
                    >
                      {etb(evidence!.amount)}
                      {evidence!.currency && evidence!.currency !== "ETB"
                        ? ` (${evidence!.currency})`
                        : ""}
                    </span>
                  ) : null
                }
              />
              <Fact
                label={t("invoices.verifications.evidenceBank")}
                value={evidence!.bank_name ?? evidence!.bank_code?.toUpperCase()}
              />
              <Fact
                label={t("invoices.verifications.evidenceDate")}
                value={
                  evidence!.transaction_date
                    ? fmtDateTime(evidence!.transaction_date)
                    : null
                }
              />
              <Fact
                label={t("invoices.verifications.evidencePayer")}
                value={evidence!.payer_name}
              />
              <Fact
                label={t("invoices.verifications.evidenceReceiver")}
                value={
                  evidence!.receiver_name || evidence!.receiver_account ? (
                    <span>
                      {evidence!.receiver_name}
                      {evidence!.receiver_account ? (
                        <span className="ml-1 font-mono text-muted-foreground">
                          {evidence!.receiver_account}
                        </span>
                      ) : null}
                    </span>
                  ) : null
                }
              />
              <Fact
                label={t("invoices.verifications.evidenceStatus")}
                value={evidence!.receipt_status}
              />
            </div>
          ) : verification.status === "needs_review" ? (
            <p className="rounded-lg bg-muted/40 px-2.5 py-2 text-xs text-muted-foreground">
              {t("invoices.verifications.noEvidence")}
            </p>
          ) : null}

          {/* Why it parked / failed */}
          {verification.failure_reason && (
            <p
              className={cn(
                "rounded-lg px-2.5 py-2 text-xs",
                verification.status === "failed"
                  ? "bg-destructive/10 text-destructive"
                  : "bg-warning/10 text-warning",
              )}
            >
              {verification.failure_reason}
            </p>
          )}

          {/* Fraud radar */}
          {fraud && (
            <div className="space-y-1 rounded-lg border border-destructive/30 bg-destructive/10 p-2.5 text-xs text-destructive">
              <p className="flex items-center gap-1.5 font-semibold">
                <ShieldAlert className="size-3.5" />
                {t("invoices.verifications.fraudTitle")}
              </p>
              {verification.already_paid_with && (
                <p>{t("invoices.verifications.fraudAlreadyPaid")}</p>
              )}
              {(verification.duplicate_claims ?? 0) > 0 && (
                <p>
                  {t("invoices.verifications.fraudDuplicateClaims", {
                    count: verification.duplicate_claims ?? 0,
                  })}
                  {(verification.duplicate_other_invoices?.length ?? 0) > 0
                    ? ` (${verification.duplicate_other_invoices!.join(", ")})`
                    : ""}
                </p>
              )}
            </div>
          )}

          {/* Resolution trail */}
          {verification.reviewed_by && (
            <p className="text-xs text-muted-foreground">
              {t("invoices.verifications.reviewedBy", {
                name: verification.reviewed_by,
                date: verification.reviewed_at
                  ? fmtDateTime(verification.reviewed_at)
                  : "",
              })}
            </p>
          )}

          {/* Review actions */}
          {verification.status === "needs_review" && canRecordPayment && (
            <div className="flex gap-2 pt-0.5">
              <Button
                size="sm"
                className="h-9 flex-1"
                disabled={verification.already_paid_with}
                onClick={() => openConfirm(verification)}
              >
                <ShieldCheck className="size-3.5" />
                {t("invoices.verifications.confirmAction")}
              </Button>
              <Button
                size="sm"
                variant="outline"
                className="h-9 flex-1 text-destructive hover:text-destructive"
                onClick={() => {
                  setRejectReason("")
                  setRejecting(verification)
                }}
              >
                <ShieldX className="size-3.5" />
                {t("invoices.verifications.rejectAction")}
              </Button>
            </div>
          )}
        </div>
      </li>
    )
  }

  return (
    <>
      <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
        <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle className="flex items-center gap-2.5">
              {t("invoices.detailTitle")}
              <Badge variant={STATUS_VARIANT[row.status]}>{t(`statuses.${row.status}`)}</Badge>
              {row.is_overdue && (
                <Badge
                  variant="outline"
                  className="border-destructive/30 bg-destructive/10 text-destructive"
                >
                  {t("invoices.overdue")}
                </Badge>
              )}
            </ResponsiveSheetTitle>
          </ResponsiveSheetHeader>

          <ResponsiveSheetBody>
            <div className="space-y-5">
              {/* Who + what */}
              <div className="rounded-2xl border bg-muted/20 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium">{row.title}</p>
                    <p className="text-xs text-muted-foreground">
                      {[row.academic_year_name, row.term_name].filter(Boolean).join(" · ") || "—"}
                    </p>
                  </div>
                  <CopyableId value={row.number} />
                </div>
                <div className="mt-3 border-t pt-2">
                  <Row label={t("invoices.columns.student")} value={row.student_name} />
                  {row.student_public_id ? (
                    <Row
                      label={t("invoices.detailStudentId")}
                      value={<CopyableId value={row.student_public_id} />}
                    />
                  ) : null}
                  {row.billing_month != null && row.billing_year != null ? (
                    <Row
                      label={t("invoices.billingPeriod")}
                      value={`${t(`months.${row.billing_month}`)} ${row.billing_year}`}
                    />
                  ) : null}
                  <Row label={t("invoices.dueDate")} value={row.due_date ? fmtDate(row.due_date) : "—"} />
                  <Row
                    label={t("invoices.detailIssued")}
                    value={row.created_at ? fmtDate(row.created_at) : "—"}
                  />
                </div>
              </div>

              {/* Money math */}
              <div className="rounded-2xl border p-4">
                <Row label={t("invoices.columns.amount")} value={etb(row.amount)} />
                {discounted ? (
                  <>
                    <Row
                      label={t("invoices.detailDiscount")}
                      value={
                        <span className="text-success">
                          −{etb(Number(row.amount) - Number(row.net_amount))}
                          {row.concession_category
                            ? ` · ${t(`concessions.categories.${row.concession_category}`)}`
                            : row.scholarship_reason
                              ? ` · ${row.scholarship_reason}`
                              : ""}
                        </span>
                      }
                    />
                    <Row label={t("invoices.detailNet")} value={etb(row.net_amount!)} />
                  </>
                ) : null}
                {Number(row.penalty_amount ?? 0) > 0 ? (
                  <Row
                    label={t("invoices.penalty")}
                    value={
                      <span className="flex items-center justify-end gap-2 text-destructive">
                        +{etb(row.penalty_amount!)}
                        {canManageFees && openBill ? (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            loading={working}
                            onClick={waivePenalty}
                          >
                            {t("invoices.waivePenalty")}
                          </Button>
                        ) : null}
                      </span>
                    }
                  />
                ) : row.penalty_waived ? (
                  <Row
                    label={t("invoices.penalty")}
                    value={
                      <span className="text-muted-foreground">{t("invoices.penaltyWaivedNote")}</span>
                    }
                  />
                ) : null}
                <Row label={t("invoices.columns.paid")} value={etb(row.amount_paid)} />
                <div className="mt-1 border-t pt-2">
                  <Row
                    label={t("invoices.columns.balance")}
                    value={
                      <span className={cn(openBill && Number(row.balance) > 0 && "text-destructive")}>
                        {etb(row.balance)}
                      </span>
                    }
                  />
                </div>
              </div>

              {/* Family payment submissions — the review lane sits ABOVE the
                  payment history so a pending claim is impossible to miss. */}
              {(verifications?.length ?? 0) > 0 && (
                <div className="space-y-2">
                  <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("invoices.verifications.title")}
                  </p>
                  <ul className="space-y-2">{verifications!.map(verificationCard)}</ul>
                </div>
              )}

              {/* Payments */}
              <div className="space-y-2">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("payments.history")}
                </p>
                {detail === null ? (
                  <Skeleton className="h-16 w-full rounded-2xl" />
                ) : (detail.payments?.length ?? 0) === 0 ? (
                  <p className="flex items-center gap-2 rounded-xl border border-dashed p-3 text-sm text-muted-foreground">
                    <Receipt className="size-4" />
                    {t("payments.noPayments")}
                  </p>
                ) : (
                  <ul className="space-y-1.5">
                    {detail.payments!.map((payment) => (
                      <li key={payment.id} className="rounded-xl border p-3">
                        <div className="flex items-center gap-2.5">
                          {payment.bank_account ? (
                            <BankLogo
                              bank={{
                                id: payment.bank_account.id,
                                code: payment.bank_account.bank_code ?? "",
                                name: payment.bank_account.bank_name ?? "",
                                type: payment.bank_account.bank_type ?? "bank",
                                logo: payment.bank_account.bank_logo,
                              }}
                              size={30}
                            />
                          ) : (
                            <span className="flex size-[30px] shrink-0 items-center justify-center rounded-lg bg-accent">
                              <HandCoins className="size-4 text-accent-foreground" />
                            </span>
                          )}
                          <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold tabular-nums">{etb(payment.amount)}</p>
                            <p className="truncate text-xs text-muted-foreground">
                              {payment.method_label}
                              {payment.bank_account
                                ? ` · ${[payment.bank_account.bank_name, payment.bank_account.account_number].filter(Boolean).join(" ")}`
                                : ""}
                            </p>
                          </div>
                          <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                            {payment.paid_at ?? ""}
                          </span>
                        </div>
                        {(payment.reference || payment.note || payment.recorded_by_name) && (
                          <div className="mt-2 space-y-1 border-t pt-2 text-xs text-muted-foreground">
                            {payment.reference ? (
                              <p className="flex items-center justify-between gap-2">
                                {t("payments.reference")}
                                <ContactActionCell
                                  kind="value"
                                  value={payment.reference}
                                  triggerClassName="text-xs"
                                />
                              </p>
                            ) : null}
                            {payment.note ? (
                              <p>
                                {t("payments.note")}: {payment.note}
                              </p>
                            ) : null}
                            {payment.recorded_by_name ? (
                              <p>
                                {t("payments.recordedBy")}: {payment.recorded_by_name}
                              </p>
                            ) : null}
                          </div>
                        )}
                        <div className="mt-2 flex items-center justify-between gap-2 border-t pt-2 text-xs">
                          {payment.receipt_number ? (
                            <ContactActionCell
                              kind="value"
                              value={payment.receipt_number}
                              triggerClassName="text-xs text-muted-foreground"
                            />
                          ) : (
                            <span />
                          )}
                          <a
                            href={`/invoices/receipt/${payment.id}`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
                          >
                            <Receipt className="size-3" />
                            {t("receipt.open")}
                          </a>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </ResponsiveSheetBody>

          {openBill && canRecordPayment && onRecordPayment ? (
            <ResponsiveSheetFooter>
              <Button
                className="h-11 flex-1"
                onClick={() => {
                  onOpenChange(false)
                  onRecordPayment(row)
                }}
              >
                <HandCoins className="size-4" />
                {t("invoices.recordPayment")}
              </Button>
            </ResponsiveSheetFooter>
          ) : null}
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Confirm a reviewed submission → records the payment. */}
      <Dialog open={confirming !== null} onOpenChange={(v) => !v && setConfirming(null)}>
        <DialogContent className="sm:max-w-xl">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <ShieldCheck className="size-4.5" />
              {t("invoices.verifications.confirmTitle")}
            </DialogTitle>
            <DialogDescription>
              {t("invoices.verifications.confirmHint", { balance: row.balance })}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <p className="mb-1.5 text-sm font-medium">{t("payments.amount")}</p>
              <Input
                type="number"
                inputMode="decimal"
                min={0}
                value={confirmAmount}
                onChange={(e) => setConfirmAmount(e.target.value)}
              />
            </div>
            {(row.collection_accounts?.length ?? 0) > 0 ? (
              <div>
                <p className="mb-1.5 text-sm font-medium">{t("payments.receivedInto")}</p>
                <Select value={confirmAccountId} onValueChange={setConfirmAccountId}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("payments.selectAccount")} />
                  </SelectTrigger>
                  <SelectContent>
                    {row.collection_accounts!.map((account) => (
                      <SelectItem key={account.id} value={String(account.id)}>
                        {[account.bank_name, account.account_name].filter(Boolean).join(" · ")} (
                        {account.account_number})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            ) : null}
            <div>
              <p className="mb-1.5 text-sm font-medium">{t("payments.note")}</p>
              <Input
                value={confirmNote}
                onChange={(e) => setConfirmNote(e.target.value)}
                placeholder={t("payments.notePlaceholder")}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirming(null)} disabled={working}>
              {tc("actions.cancel")}
            </Button>
            <Button onClick={submitConfirm} disabled={working || !(Number(confirmAmount) > 0)}>
              {working ? <Loader2 className="size-4 animate-spin" /> : <ShieldCheck className="size-4" />}
              {t("invoices.verifications.confirmAction")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Reject with a reason the family will see. */}
      <Dialog open={rejecting !== null} onOpenChange={(v) => !v && setRejecting(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <ShieldX className="size-4.5" />
              {t("invoices.verifications.rejectTitle")}
            </DialogTitle>
            <DialogDescription>{t("invoices.verifications.rejectHint")}</DialogDescription>
          </DialogHeader>
          <Input
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            placeholder={t("invoices.verifications.rejectPlaceholder")}
          />
          <DialogFooter>
            <Button variant="outline" onClick={() => setRejecting(null)} disabled={working}>
              {tc("actions.cancel")}
            </Button>
            <Button
              variant="destructive"
              onClick={submitReject}
              disabled={working || !rejectReason.trim()}
            >
              {working ? <Loader2 className="size-4 animate-spin" /> : <ShieldX className="size-4" />}
              {t("invoices.verifications.rejectAction")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
