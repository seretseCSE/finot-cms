"use client"

import { BadgePercent, Plus } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { ConcessionSheet } from "@/components/fees/concession-sheet"
import { InvoiceDetailSheet } from "@/components/fees/invoice-detail-sheet"
import { RecordPaymentSheet } from "@/components/fees/record-payment-sheet"
import { ScholarshipInvoiceDialog } from "@/components/fees/scholarship-invoice-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { CopyableId } from "@/components/ui/copyable-id"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { formatETB } from "@/lib/utils"
import type {
  FeeConcession,
  Invoice,
  InvoiceStatus,
  Paginated,
  Student,
} from "@/lib/types"

const STATUS_VARIANT: Record<InvoiceStatus, "default" | "secondary" | "outline"> = {
  paid: "default",
  partial: "secondary",
  unpaid: "outline",
  scholarship: "secondary",
  void: "secondary",
}

export function FeesTab({ student }: { student: Student }) {
  const { t } = useTranslation("fees")
  const permissions = useEffectivePermissions()

  const canRecord = permissions.includes("payments.record")
  const canManage = permissions.includes("fees.manage")

  const [invoices, setInvoices] = useState<Invoice[] | null>(null)
  const [concessions, setConcessions] = useState<FeeConcession[] | null>(null)
  const [paying, setPaying] = useState<Invoice | null>(null)
  const [payOpen, setPayOpen] = useState(false)
  const [granting, setGranting] = useState<Invoice | null>(null)
  const [scholarshipOpen, setScholarshipOpen] = useState(false)
  const [concessionOpen, setConcessionOpen] = useState(false)
  const [viewing, setViewing] = useState<Invoice | null>(null)

  const load = useCallback(() => {
    apiFetch<Paginated<Invoice>>(`/invoices?student_id=${student.id}&per_page=100`)
      .then((res) => setInvoices(res.data))
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : t("invoices.empty"))
        setInvoices([])
      })
    // Standing concessions covering this student (incl. guardian-level ones).
    apiFetch<Paginated<FeeConcession>>(
      `/fee-concessions?student_id=${student.id}&status=pending,active&per_page=50`,
    )
      .then((res) => setConcessions(res.data))
      .catch(() => setConcessions([]))
  }, [student.id, t])

  useEffect(() => {
    load()
  }, [load])

  function upsert(invoice: Invoice) {
    setInvoices((prev) =>
      (prev ?? []).map((i) => (i.id === invoice.id ? { ...i, ...invoice } : i)),
    )
  }

  function concessionValue(row: FeeConcession): string {
    if (row.discount_type === "full_scholarship") return t("statuses.scholarship")
    if (row.discount_type === "percentage") return `${Number(row.discount_value)}%`
    return formatETB(row.discount_value)
  }

  if (invoices === null) {
    return (
      <div className="space-y-2">
        <Skeleton className="h-16 w-full rounded-2xl" />
        <Skeleton className="h-16 w-full rounded-2xl" />
      </div>
    )
  }

  return (
    <div className="space-y-5">
      {/* Standing concessions — the policy layer above individual bills. */}
      <section className="rounded-2xl border p-4">
        <div className="flex items-center justify-between gap-3">
          <h3 className="flex items-center gap-2 text-sm font-semibold">
            <BadgePercent className="size-4 text-primary" />
            {t("concessions.title")}
          </h3>
          {canManage && (
            <Button
              size="sm"
              variant="outline"
              className="h-8 rounded-full"
              onClick={() => setConcessionOpen(true)}
            >
              <Plus className="size-3.5" />
              {t("concessions.create")}
            </Button>
          )}
        </div>
        {concessions === null ? (
          <Skeleton className="mt-3 h-10 w-full rounded-xl" />
        ) : concessions.length === 0 ? (
          <p className="mt-2 text-xs text-muted-foreground">{t("concessions.noneForStudent")}</p>
        ) : (
          <ul className="mt-3 space-y-2">
            {concessions.map((row) => (
              <li
                key={row.id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-muted/30 px-3 py-2 text-sm"
              >
                <span className="flex min-w-0 flex-col">
                  <span className="font-medium">
                    {t(`concessions.categories.${row.category}`)} · {concessionValue(row)}
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {row.parent_id
                      ? `${t("concessions.viaGuardian")}${row.parent_name ? ` (${row.parent_name})` : ""} · `
                      : ""}
                    {row.academic_year_name
                      ? [row.academic_year_name, row.term_name].filter(Boolean).join(" · ")
                      : t("concessions.lifetime")}
                  </span>
                </span>
                <Badge
                  variant="outline"
                  className={
                    row.status === "active"
                      ? "border-success/30 bg-success/10 text-success"
                      : "border-warning/30 bg-warning/10 text-warning"
                  }
                >
                  {t(`concessions.statuses.${row.status}`)}
                </Badge>
              </li>
            ))}
          </ul>
        )}
      </section>

      {invoices.length === 0 ? (
        <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
          {t("invoices.empty")}
        </div>
      ) : (
        invoices.map((invoice) => (
          <div
            key={invoice.id}
            className="cursor-pointer rounded-2xl border p-4 transition-colors hover:bg-muted/30"
            onClick={() => setViewing(invoice)}
          >
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="text-sm font-medium">{invoice.title}</p>
                <div className="flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
                  <span onClick={(e) => e.stopPropagation()}>
                    <CopyableId value={invoice.number} />
                  </span>
                  {invoice.due_date ? `${t("invoices.columns.due")}: ${invoice.due_date}` : null}
                  {invoice.scholarship_reason ? ` · ${invoice.scholarship_reason}` : null}
                </div>
              </div>
              <Badge variant={STATUS_VARIANT[invoice.status]}>{t(`statuses.${invoice.status}`)}</Badge>
            </div>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-baseline gap-2 text-sm tabular-nums">
                {invoice.net_amount != null && invoice.net_amount !== invoice.amount ? (
                  <>
                    <span className="text-muted-foreground line-through">{invoice.amount}</span>
                    <span className="font-semibold">{invoice.net_amount} ETB</span>
                  </>
                ) : (
                  <span className="font-semibold">{invoice.amount} ETB</span>
                )}
                <span className="text-xs text-muted-foreground">
                  {t("invoices.columns.paid")}: {invoice.amount_paid}
                </span>
              </div>
              <div className="flex gap-2">
                {canRecord &&
                invoice.status !== "paid" &&
                invoice.status !== "void" &&
                invoice.status !== "scholarship" ? (
                  <Button
                    size="sm"
                    className="h-9 rounded-full"
                    onClick={(e) => {
                      e.stopPropagation()
                      setPaying(invoice)
                      setPayOpen(true)
                    }}
                  >
                    {t("invoices.recordPayment")}
                  </Button>
                ) : null}
                {canManage &&
                invoice.status !== "void" &&
                (invoice.status !== "paid" ||
                  (invoice.discount_type && invoice.discount_type !== "none")) ? (
                  <Button
                    size="sm"
                    variant="outline"
                    className="h-9 rounded-full"
                    onClick={(e) => {
                      e.stopPropagation()
                      setGranting(invoice)
                      setScholarshipOpen(true)
                    }}
                  >
                    {invoice.status === "paid"
                      ? t("scholarship.adjustAction")
                      : t("scholarship.action")}
                  </Button>
                ) : null}
              </div>
            </div>
          </div>
        ))
      )}

      <RecordPaymentSheet
        invoice={paying}
        open={payOpen}
        onOpenChange={(v) => {
          setPayOpen(v)
          if (!v) setPaying(null)
        }}
        onRecorded={upsert}
      />
      <InvoiceDetailSheet
        invoice={viewing}
        open={viewing !== null}
        onOpenChange={(v) => !v && setViewing(null)}
        canRecordPayment={canRecord}
        canManageFees={canManage}
        onRecordPayment={(invoice) => {
          setPaying(invoice)
          setPayOpen(true)
        }}
        onChanged={upsert}
      />
      <ScholarshipInvoiceDialog
        invoice={granting}
        open={scholarshipOpen}
        onOpenChange={(v) => {
          setScholarshipOpen(v)
          if (!v) setGranting(null)
        }}
        onApplied={upsert}
      />
      <ConcessionSheet
        open={concessionOpen}
        onOpenChange={setConcessionOpen}
        onSaved={() => load()}
        student={student}
      />
    </div>
  )
}
