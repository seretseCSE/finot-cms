"use client"

import { BadgeCheck, ChevronDown, RefreshCcw, Wallet } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DocumentDownloadButton } from "@/components/ui/document-download-button"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { PayrollItem, PayrollRun, PayrollStatus } from "@/lib/types"
import { cn, formatETB } from "@/lib/utils"

const STATUS_VARIANT: Record<PayrollStatus, "default" | "secondary" | "outline"> = {
  draft: "secondary",
  approved: "outline",
  paid: "default",
}

/** One payslip row, expandable to its snapshot breakdown. */
function PayslipRow({ item, frozen }: { item: PayrollItem; frozen: boolean }) {
  const { t } = useTranslation("payroll")
  const { t: ts } = useTranslation("employees")
  const [open, setOpen] = useState(false)

  return (
    <li className="px-4 py-2.5">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="pressable flex w-full items-center gap-3 text-left"
        aria-expanded={open}
      >
        <div className="min-w-0 flex-1 leading-tight">
          <p className="truncate text-sm font-medium">{item.employee_name}</p>
          <p className="text-xs text-muted-foreground tabular-nums">
            {t("item.gross")} {formatETB(item.gross_pay)} · {t("item.tax")}{" "}
            {formatETB(item.income_tax)}
          </p>
        </div>
        <span className="text-sm font-semibold tabular-nums">{formatETB(item.net_pay)}</span>
        <ChevronDown
          className={cn("size-4 shrink-0 text-muted-foreground transition-transform", open && "rotate-180")}
        />
      </button>
      {open && (
        <dl className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 rounded-xl bg-muted/30 p-3 text-xs tabular-nums sm:grid-cols-3">
          {(item.breakdown?.positions ?? []).map((position) => (
            <div key={position.job_title} className="contents">
              <dt className="text-muted-foreground">{ts(`jobTitles.${position.job_title}`)}</dt>
              <dd className="text-right sm:col-span-2">{formatETB(position.salary)}</dd>
            </div>
          ))}
          {(item.breakdown?.allowances ?? []).map((allowance) => (
            <div key={allowance.name} className="contents">
              <dt className="text-muted-foreground">{allowance.name}</dt>
              <dd className="text-right sm:col-span-2">+{formatETB(allowance.amount)}</dd>
            </div>
          ))}
          <div className="contents">
            <dt className="text-muted-foreground">{t("item.tax")}</dt>
            <dd className="text-right sm:col-span-2">−{formatETB(item.income_tax)}</dd>
          </div>
          <div className="contents">
            <dt className="text-muted-foreground">{t("item.pension")}</dt>
            <dd className="text-right sm:col-span-2">−{formatETB(item.pension_employee)}</dd>
          </div>
          {(item.breakdown?.deductions ?? []).map((deduction) => (
            <div key={deduction.name} className="contents">
              <dt className="text-muted-foreground">{deduction.name}</dt>
              <dd className="text-right sm:col-span-2">−{formatETB(deduction.amount)}</dd>
            </div>
          ))}
          <div className="contents font-medium">
            <dt>{t("item.net")}</dt>
            <dd className="text-right sm:col-span-2">{formatETB(item.net_pay)}</dd>
          </div>
          {frozen && (
            <div className="col-span-2 mt-1 sm:col-span-3">
              <DocumentDownloadButton type="payslip" subjectId={item.id} />
            </div>
          )}
        </dl>
      )}
    </li>
  )
}

/**
 * A payroll run's payslips + lifecycle actions. Draft runs can be recomputed
 * (re-pull HR data); APPROVE freezes the numbers; MARK PAID closes the run.
 */
export function PayrollRunDetail({
  runId,
  canManage,
  canApprove,
  open,
  onOpenChange,
  onChanged,
}: {
  runId: number | null
  canManage: boolean
  canApprove: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: (run: PayrollRun) => void
}) {
  const { t } = useTranslation("payroll")
  const { t: tc } = useTranslation("common")

  const [run, setRun] = useState<PayrollRun | null>(null)
  const [working, setWorking] = useState(false)
  const [confirming, setConfirming] = useState<"approve" | "pay" | null>(null)

  useEffect(() => {
    if (!open || runId === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset before load
    setRun(null)
    apiFetch<{ data: PayrollRun }>(`/payroll-runs/${runId}`)
      .then((res) => !cancelled && setRun(res.data))
      .catch((error) => {
        if (!cancelled) toast.error(error instanceof ApiError ? error.message : t("loadFailed"))
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, runId])

  async function act(path: string, success: string) {
    if (!run) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: PayrollRun }>(`/payroll-runs/${run.id}/${path}`, {
        method: "POST",
      })
      setRun(res.data)
      onChanged(res.data)
      toast.success(success)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Something went wrong.")
    } finally {
      setWorking(false)
      setConfirming(null)
    }
  }

  const totals: { key: string; value: string }[] = run
    ? [
        { key: "gross", value: run.gross_total },
        { key: "tax", value: run.tax_total },
        { key: "pension", value: run.pension_employee_total },
        { key: "deductions", value: run.deduction_total },
        { key: "net", value: run.net_total },
      ]
    : []

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-2xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle className="flex flex-wrap items-center gap-2">
            {run ? run.name : t("detailTitle")}
            {run && (
              <Badge variant={STATUS_VARIANT[run.status]}>{t(`statuses.${run.status}`)}</Badge>
            )}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          {run === null ? (
            <>
              <Skeleton className="h-24 rounded-2xl" />
              <Skeleton className="h-64 rounded-2xl" />
            </>
          ) : (
            <>
              <p className="text-sm text-muted-foreground tabular-nums">
                {run.period_start} → {run.period_end} ·{" "}
                {t("employeeCount", { count: run.items?.length ?? run.employee_count ?? 0 })}
              </p>

              {/* Totals strip */}
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                {totals.map((total) => (
                  <div key={total.key} className="rounded-xl border bg-muted/30 px-3 py-2">
                    <p className="text-[11px] font-medium text-muted-foreground uppercase tracking-wide">
                      {t(`totals.${total.key}`)}
                    </p>
                    <p className="text-sm font-semibold tabular-nums">{formatETB(total.value)}</p>
                  </div>
                ))}
              </div>

              {/* Lifecycle actions */}
              {(canManage || canApprove) && (
                <div className="flex flex-wrap gap-2">
                  {canManage && run.status === "draft" && (
                    <Button
                      variant="outline"
                      size="sm"
                      className="h-9"
                      loading={working}
                      onClick={() => act("recompute", t("recomputed"))}
                    >
                      <RefreshCcw className="size-4" />
                      {t("actions.recompute")}
                    </Button>
                  )}
                  {canApprove && run.status === "draft" && (
                    <Button
                      size="sm"
                      className="h-9"
                      loading={working}
                      onClick={() => setConfirming("approve")}
                    >
                      <BadgeCheck className="size-4" />
                      {t("actions.approve")}
                    </Button>
                  )}
                  {canApprove && run.status === "approved" && (
                    <Button
                      size="sm"
                      className="h-9"
                      loading={working}
                      onClick={() => setConfirming("pay")}
                    >
                      <Wallet className="size-4" />
                      {t("actions.markPaid")}
                    </Button>
                  )}
                </div>
              )}

              {/* Payslips */}
              <div className="overflow-hidden rounded-2xl border bg-card">
                <ul className="divide-y">
                  {(run.items ?? []).map((item) => (
                    <PayslipRow
                      key={item.id}
                      item={item}
                      frozen={run.status === "approved" || run.status === "paid"}
                    />
                  ))}
                </ul>
              </div>
            </>
          )}
        </ResponsiveSheetBody>

        <AlertDialog open={confirming !== null} onOpenChange={(v) => !v && setConfirming(null)}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>
                {confirming === "approve" ? t("approveConfirmTitle") : t("payConfirmTitle")}
              </AlertDialogTitle>
              <AlertDialogDescription>
                {confirming === "approve"
                  ? t("approveConfirmDescription", { net: formatETB(run?.net_total ?? 0) })
                  : t("payConfirmDescription", { net: formatETB(run?.net_total ?? 0) })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
              <AlertDialogAction
                loading={working}
                onClick={(e) => {
                  e.preventDefault()
                  if (confirming === "approve") act("approve", t("approved"))
                  else act("mark-paid", t("paid"))
                }}
              >
                {tc("actions.confirm")}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
