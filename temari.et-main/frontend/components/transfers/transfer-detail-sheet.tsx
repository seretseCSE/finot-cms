"use client"

import {
  AlertTriangle,
  ArrowRight,
  ExternalLink,
  FileText,
  HandCoins,
  Landmark,
} from "lucide-react"
import { useEffect, useState } from "react"

import { RecordPaymentSheet } from "@/components/fees/record-payment-sheet"
import { ScholarshipInvoiceDialog } from "@/components/fees/scholarship-invoice-dialog"
import { AttachmentTile } from "@/components/ui/attachment"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { CopyableId } from "@/components/ui/copyable-id"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import type { MediaFile } from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import type {
  Invoice,
  Paginated,
  TransferRequest,
  TransferRequestStatus,
} from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

const STATUS_TONE: Record<TransferRequestStatus, string> = {
  requested: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
  rejected: "border-destructive/30 bg-destructive/10 text-destructive",
  cancelled: "border-border bg-muted text-muted-foreground",
}

interface Props {
  transfer: TransferRequest | null
  onOpenChange: (open: boolean) => void
  /** Which side of the request the ACTIVE workspace is. */
  isSender: boolean
  isReceiver: boolean
  /** Fee-clearance panel visibility/actions (sending side only). */
  canSeeFees: boolean
  canManageFees: boolean
  canRecordPayments: boolean
  openPreview: (files: MediaFile[], index: number) => void
  onApprove: (transfer: TransferRequest) => void
  onReject: (transfer: TransferRequest) => void
  onCancel: (transfer: TransferRequest) => void
  onOpenLetter: (transfer: TransferRequest) => void
}

/**
 * The transfer request's full story in one place: who is moving from where
 * to where, why, the supporting documents (tap → media preview), and — for
 * the SENDING side, whose approval IS the fee clearance — the student's open
 * invoices with inline settle actions. Bottom actions match the side.
 */
export function TransferDetailSheet({
  transfer,
  onOpenChange,
  isSender,
  isReceiver,
  canSeeFees,
  canManageFees,
  canRecordPayments,
  openPreview,
  onApprove,
  onReject,
  onCancel,
  onOpenLetter,
}: Props) {
  const { t } = useTranslation("transfers")
  const { t: tf } = useTranslation("fees")

  const [invoices, setInvoices] = useState<Invoice[] | null>(null)
  const [paying, setPaying] = useState<Invoice | null>(null)
  const [scholarship, setScholarship] = useState<Invoice | null>(null)
  const [feesVersion, setFeesVersion] = useState(0)

  const showFees = transfer !== null && isSender && canSeeFees
  const studentId = transfer?.student?.id

  // The sending side reviews the open balance before handing over.
  useEffect(() => {
    if (!showFees || !studentId) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale rows on close
      setInvoices(null)
      return
    }
    let cancelled = false
    setInvoices(null)
    apiFetch<Paginated<Invoice>>(
      `/invoices?student_id=${studentId}&status=unpaid,partial&per_page=100`,
    )
      .then((res) => !cancelled && setInvoices(res.data))
      .catch(() => !cancelled && setInvoices([]))
    return () => {
      cancelled = true
    }
  }, [showFees, studentId, feesVersion])

  if (transfer === null) return null

  const outstanding = (invoices ?? []).reduce((sum, invoice) => sum + Number(invoice.balance), 0)
  const requested = transfer.status === "requested"

  // Jump to the student's record: the SENDING school can always open it (the
  // record lived at their school), the RECEIVING school only once the handover
  // is approved — before that they hold directory-level facts only.
  const canOpenProfile =
    transfer.student?.id != null && (isSender || (isReceiver && transfer.status === "approved"))
  const profileHref = `/students/${transfer.student?.id}`

  return (
    <>
      <ResponsiveSheet open onOpenChange={onOpenChange}>
        <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle className="flex items-center gap-2.5">
              {t("detail.title")}
              <Badge variant="outline" className={cn("rounded-full", STATUS_TONE[transfer.status])}>
                {t(`statuses.${transfer.status}`)}
              </Badge>
            </ResponsiveSheetTitle>
          </ResponsiveSheetHeader>

          <ResponsiveSheetBody>
            <div className="space-y-5">
              {/* Student — name links to the record for participant schools. */}
              <div className="flex flex-wrap items-center gap-3 rounded-2xl border bg-muted/20 p-4">
                <PersonAvatar
                  name={transfer.student?.full_name ?? "?"}
                  photoUrl={transfer.student?.photo_url}
                  className="size-11 text-sm"
                />
                <div className="min-w-0 flex-1">
                  {canOpenProfile ? (
                    <a
                      href={profileHref}
                      target="_blank"
                      rel="noreferrer"
                      className="block truncate font-medium underline-offset-4 hover:underline"
                    >
                      {transfer.student?.full_name}
                    </a>
                  ) : (
                    <p className="truncate font-medium">{transfer.student?.full_name}</p>
                  )}
                  <p className="text-xs text-muted-foreground">
                    {fmtDate(transfer.created_at)}
                    {transfer.requested_by_name ? ` · ${transfer.requested_by_name}` : ""}
                  </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  <CopyableId value={transfer.student?.public_id} />
                  {canOpenProfile && (
                    <Button variant="outline" size="sm" asChild>
                      <a href={profileHref} target="_blank" rel="noreferrer">
                        <ExternalLink className="size-3.5" />
                        {t("detail.viewProfile")}
                      </a>
                    </Button>
                  )}
                </div>
              </div>

              {/* From → To */}
              <div className="grid grid-cols-[1fr_auto_1fr] items-stretch gap-2">
                <div className="rounded-2xl border p-3">
                  <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("detail.from")}
                  </p>
                  <p className="mt-1 text-sm font-medium">{transfer.from_school_name}</p>
                  <p className="text-xs text-muted-foreground">
                    {transfer.from_branch_name}
                    {transfer.from_enrollment?.grade_level_name
                      ? ` · ${transfer.from_enrollment.grade_level_name}`
                      : ""}
                    {transfer.from_enrollment?.section_name
                      ? ` ${transfer.from_enrollment.section_name}`
                      : ""}
                  </p>
                </div>
                <div className="flex items-center">
                  <ArrowRight className="size-4 text-muted-foreground" />
                </div>
                <div className="rounded-2xl border border-primary/30 bg-primary/5 p-3">
                  <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("detail.to")}
                  </p>
                  <p className="mt-1 text-sm font-medium">{transfer.to_school_name}</p>
                  <p className="text-xs text-muted-foreground">
                    {transfer.to_branch_name} · {transfer.to_grade_level_name} (
                    {transfer.to_academic_year_name})
                  </p>
                </div>
              </div>

              {/* Reason — visible to BOTH sides. */}
              <div className="space-y-1.5">
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("form.reason")}
                </p>
                <p className="rounded-xl border bg-muted/20 p-3 text-sm">
                  {transfer.reason || "—"}
                </p>
              </div>

              {transfer.decision_note && (
                <div className="space-y-1.5">
                  <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("detail.decisionNote")}
                  </p>
                  <p className="rounded-xl border bg-muted/20 p-3 text-sm">
                    {transfer.decision_note}
                  </p>
                </div>
              )}

              {/* Documents — tap opens the in-app media preview. */}
              {(transfer.attachments?.length ?? 0) > 0 && (
                <div className="space-y-1.5">
                  <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("documents")} ({transfer.attachments!.length})
                  </p>
                  <div className="space-y-1.5">
                    {transfer.attachments!.map((file, index) => (
                      <AttachmentTile
                        key={file.id}
                        file={file}
                        onPreview={() => openPreview(transfer.attachments!, index)}
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Fee clearance — the sending side's approval IS the clearance,
                  so the open balance sits right next to the decision. */}
              {showFees && (
                <div className="space-y-2">
                  <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("detail.feeClearance")}
                  </p>

                  {invoices === null ? (
                    <Skeleton className="h-16 w-full rounded-2xl" />
                  ) : invoices.length === 0 ? (
                    <p className="rounded-xl border border-success/30 bg-success/10 p-3 text-sm text-success">
                      {t("detail.noOpenInvoices")}
                    </p>
                  ) : (
                    <>
                      <p className="flex items-start gap-2 rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
                        {t("detail.outstandingWarning", {
                          count: invoices.length,
                          amount: outstanding.toLocaleString(),
                        })}
                      </p>
                      <ul className="space-y-1.5">
                        {invoices.map((invoice) => (
                          <li
                            key={invoice.id}
                            className="flex flex-wrap items-center gap-2 rounded-xl border px-3 py-2.5"
                          >
                            <div className="min-w-0 flex-1">
                              <p className="truncate text-sm font-medium">{invoice.title}</p>
                              <p className="flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                                <CopyableId value={invoice.number} />
                                {tf("invoices.columns.balance")}:{" "}
                                {Number(invoice.balance).toLocaleString()} ETB
                              </p>
                            </div>
                            {canRecordPayments && (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPaying(invoice)}
                              >
                                <HandCoins className="size-3.5" />
                                {tf("invoices.recordPayment")}
                              </Button>
                            )}
                            {canManageFees && (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setScholarship(invoice)}
                              >
                                <Landmark className="size-3.5" />
                                {tf("scholarship.action")}
                              </Button>
                            )}
                          </li>
                        ))}
                      </ul>
                    </>
                  )}
                </div>
              )}
            </div>
          </ResponsiveSheetBody>

          {/* Side-appropriate actions. */}
          {(requested && (isSender || isReceiver)) || transfer.status === "approved" ? (
            <ResponsiveSheetFooter>
              {requested && isSender && (
                <>
                  <Button
                    variant="outline"
                    className="h-11 flex-1 text-destructive hover:text-destructive"
                    onClick={() => onReject(transfer)}
                  >
                    {t("actions.reject")}
                  </Button>
                  <Button className="h-11 flex-1" onClick={() => onApprove(transfer)}>
                    {t("detail.approveHandOver")}
                  </Button>
                </>
              )}
              {requested && isReceiver && !isSender && (
                <Button
                  variant="outline"
                  className="h-11 flex-1 text-destructive hover:text-destructive"
                  onClick={() => onCancel(transfer)}
                >
                  {t("actions.cancel")}
                </Button>
              )}
              {transfer.status === "approved" && (
                <Button
                  variant="outline"
                  className="h-11 flex-1"
                  onClick={() => onOpenLetter(transfer)}
                >
                  <FileText className="size-4" />
                  {t("actions.letter")}
                </Button>
              )}
            </ResponsiveSheetFooter>
          ) : null}
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      <RecordPaymentSheet
        invoice={paying}
        open={paying !== null}
        onOpenChange={(open) => !open && setPaying(null)}
        onRecorded={() => {
          setPaying(null)
          setFeesVersion((v) => v + 1)
        }}
      />
      <ScholarshipInvoiceDialog
        invoice={scholarship}
        open={scholarship !== null}
        onOpenChange={(open) => !open && setScholarship(null)}
        onApplied={() => {
          setScholarship(null)
          setFeesVersion((v) => v + 1)
        }}
      />
    </>
  )
}
