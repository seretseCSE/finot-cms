"use client"

import {
  CalendarClock,
  CheckCircle2,
  CircleAlert,
  Clock3,
  ExternalLink,
  Receipt,
  Send,
  ShieldCheck,
  Wallet,
} from "lucide-react"
import Link from "next/link"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { VerifyPaymentSheet, type MyInvoice } from "@/components/me/verify-payment-sheet"
import { Badge } from "@/components/ui/badge"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { FeeBankAccount } from "@/lib/types"

const TABS = ["due", "upcoming", "history"] as const

interface MyPaymentRow {
  id: number
  invoice_title: string | null
  amount: string
  method: string | null
  reference: string | null
  receipt_number: string | null
  receipt_token: string | null
  paid_at: string | null
  bank_name: string | null
  bank_logo: string | null
  account_number: string | null
}

interface UpcomingFeeRow {
  fee_structure_id: number
  fee: string
  type: string
  period: string
  due_date: string
  amount: string
}

const STATUS_VARIANT: Record<string, "default" | "secondary" | "outline"> = {
  paid: "default",
  partial: "secondary",
  unpaid: "outline",
  scholarship: "secondary",
  void: "secondary",
}

const VERIFICATION_ICON = {
  verified: <CheckCircle2 className="size-3.5 text-success" />,
  needs_review: <Clock3 className="size-3.5 text-warning" />,
  failed: <CircleAlert className="size-3.5 text-destructive" />,
} as const

/** A collection-account logo revealing full details in a popover on tap. */
function PayToLogo({ account }: { account: FeeBankAccount }) {
  const bank = {
    id: account.id,
    code: account.bank_code ?? "",
    name: account.bank_name ?? "",
    type: account.bank_type ?? ("bank" as const),
    logo: account.bank_logo,
  }
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label={account.bank_name ?? account.account_name}
          className="rounded-full transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
          onClick={(e) => e.stopPropagation()}
        >
          <BankLogo bank={bank} size={24} />
        </button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        className="w-auto min-w-52 p-3"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center gap-2.5">
          <BankLogo bank={bank} size={32} />
          <div className="min-w-0 space-y-0.5">
            {account.bank_name && (
              <p className="text-sm font-medium leading-tight">{account.bank_name}</p>
            )}
            <p className="truncate text-xs text-muted-foreground">{account.account_name}</p>
            <CopyableId value={account.account_number} />
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}

/** Outstanding-total strip above the due register — balance is net + penalty − paid. */
function DueSummary({
  invoices,
  balanceLabel,
  overdueLabel,
}: {
  invoices: MyInvoice[]
  balanceLabel: string
  overdueLabel: string
}) {
  const open = invoices.filter((row) => row.status === "unpaid" || row.status === "partial")
  if (open.length === 0) return null

  const total = open.reduce((sum, row) => sum + Number(row.balance ?? 0), 0)
  const overdue = open.filter((row) => row.is_overdue).length

  return (
    <div className="page-gutter">
      <div className="mx-auto flex items-center gap-3 rounded-2xl border border-warning/30 bg-warning/5 px-4 py-3.5">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/15">
          <Wallet className="size-4 text-warning" />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-lg font-semibold tabular-nums leading-tight">
            {total.toFixed(2)} ETB
          </p>
          <p className="text-xs text-muted-foreground">{balanceLabel}</p>
        </div>
        {overdue > 0 && (
          <Badge
            variant="outline"
            className="shrink-0 border-destructive/30 bg-destructive/10 text-destructive"
          >
            {overdue} {overdueLabel}
          </Badge>
        )}
      </div>
    </div>
  )
}

export default function MyPaymentsPage() {
  const { t } = useTranslation("me")
  const { t: tf } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { user } = useAuth()

  const { children, child, activeChild, setActiveChild } = useChildren(user?.is_parent === true)
  const [tab, setTab] = useProfileTabs(TABS, "due")
  const [invoices, setInvoices] = useState<MyInvoice[] | null>(null)
  const [history, setHistory] = useState<MyPaymentRow[] | null>(null)
  const [upcoming, setUpcoming] = useState<UpcomingFeeRow[] | null>(null)
  const [verifying, setVerifying] = useState<MyInvoice | null>(null)
  const [verifyOpen, setVerifyOpen] = useState(false)
  const [reloadKey, setReloadKey] = useState(0)

  const childId = child?.student_id ?? null
  const canPayFees = child?.permissions.can_pay_fees === true

  useEffect(() => {
    if (childId === null || !canPayFees) return
    let cancelled = false
    const timer = setTimeout(() => {
      if (cancelled) return
      setInvoices(null)
      apiFetch<{ data: MyInvoice[] }>(`/me/children/${childId}/invoices`)
        .then((res) => !cancelled && setInvoices(res.data))
        .catch((error) => {
          if (cancelled) return
          toast.error(error instanceof ApiError ? error.message : t("payments.loadFailed"))
          setInvoices([])
        })
    }, 0)
    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [childId, canPayFees, t, reloadKey])

  // Per-tab fetch (profile-tabs pattern): history and upcoming load on first
  // visit and revalidate on every re-visit or child switch.
  useEffect(() => {
    if (childId === null || !canPayFees) return
    let cancelled = false
    if (tab === "history") {
      apiFetch<{ data: MyPaymentRow[] }>(`/me/children/${childId}/payments`)
        .then((res) => !cancelled && setHistory(res.data))
        .catch(() => !cancelled && setHistory([]))
    }
    if (tab === "upcoming") {
      apiFetch<{ data: UpcomingFeeRow[] }>(`/me/children/${childId}/upcoming-fees`)
        .then((res) => !cancelled && setUpcoming(res.data))
        .catch(() => !cancelled && setUpcoming([]))
    }
    return () => {
      cancelled = true
    }
  }, [childId, canPayFees, tab, reloadKey])

  // Child switches reset the loaded registers so stale rows never flash.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on child switch
    setHistory(null)
    setUpcoming(null)
  }, [childId])

  const columns: DataTableColumn<MyInvoice>[] = [
    {
      key: "title",
      label: tf("invoices.columns.title"),
      primary: true,
      sortable: true,
      render: (row) => {
        const latest = (row.verifications ?? [])[0]
        return (
          <div className="min-w-0">
            <p className="truncate text-sm font-medium">{row.title}</p>
            <div className="flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
              <CopyableId value={row.number} />
              {[row.academic_year_name, row.term_name].filter(Boolean).join(" · ")}
            </div>
            {latest && (
              <>
                <p className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                  {VERIFICATION_ICON[latest.status]}
                  {t(`payments.statuses.${latest.status}`)}
                  {latest.transaction_number ? ` · ${latest.transaction_number}` : ""}
                </p>
                {/* The school's reason when a submission was turned down. */}
                {latest.status === "failed" && latest.failure_reason ? (
                  <p className="mt-0.5 text-xs text-destructive">{latest.failure_reason}</p>
                ) : null}
              </>
            )}
          </div>
        )
      },
      exportValue: (row) => `${row.number} ${row.title}`,
    },
    {
      key: "due_date",
      label: tf("invoices.columns.due"),
      sortable: true,
      render: (row) => (
        <div className="text-sm tabular-nums">
          {row.due_date ?? <span className="text-muted-foreground">—</span>}
          {row.is_overdue && (
            <Badge
              variant="outline"
              className="ml-1.5 border-destructive/30 bg-destructive/10 text-destructive"
            >
              {tf("invoices.overdue")}
            </Badge>
          )}
        </div>
      ),
      exportValue: (row) => row.due_date ?? "",
    },
    {
      // Which accounts the school collects this fee into — tap a logo for
      // the full account details and a copyable number.
      key: "pay_to",
      label: t("payments.payTo"),
      mobileHidden: true,
      render: (row) =>
        (row.collection_accounts?.length ?? 0) > 0 ? (
          <span className="flex items-center gap-1.5">
            {row.collection_accounts!.map((account) => (
              <PayToLogo key={account.id} account={account} />
            ))}
          </span>
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
      exportValue: (row) =>
        (row.collection_accounts ?? [])
          .map((a) => `${a.bank_name ?? ""} ${a.account_number}`.trim())
          .join("; "),
    },
    {
      key: "net_amount",
      label: tf("invoices.columns.amount"),
      sortable: true,
      render: (row) => (
        <div className="tabular-nums">
          <p className="text-sm font-semibold">{row.total_due ?? row.net_amount} ETB</p>
          <p className="text-xs text-muted-foreground">
            {Number(row.penalty_amount ?? 0) > 0 &&
              `${tf("invoices.penalty")}: +${row.penalty_amount} · `}
            {tf("invoices.columns.paid")}: {row.amount_paid}
            {(row.status === "unpaid" || row.status === "partial") &&
              ` · ${t("payments.balanceDue", { amount: row.balance })}`}
          </p>
        </div>
      ),
      exportValue: (row) => String(row.net_amount),
    },
    {
      key: "status",
      label: tf("invoices.columns.status"),
      render: (row) => (
        <Badge variant={STATUS_VARIANT[row.status] ?? "secondary"}>
          {tf(`statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => tf(`statuses.${row.status}`),
    },
  ]

  // Client-mode filters read flat row keys — years built from the data.
  const yearNames = [
    ...new Set((invoices ?? []).map((row) => row.academic_year_name).filter(Boolean)),
  ] as string[]
  const filterDefs: DataTableFilter[] = [
    {
      key: "status",
      label: tf("invoices.columns.status"),
      options: ["unpaid", "partial", "paid", "scholarship"].map((status) => ({
        label: tf(`statuses.${status}`),
        value: status,
      })),
    },
    ...(yearNames.length > 1
      ? [
          {
            key: "academic_year_name",
            label: tf("invoices.academicYear"),
            options: yearNames.map((name) => ({ label: name, value: name })),
          },
        ]
      : []),
  ]

  if (!user?.is_parent) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("payments.title")} description={t("payments.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={Receipt} title={t("parent.empty")} />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("payments.title")} description={t("payments.subtitle")}>
        {children && children.length > 1 ? (
          <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
        ) : null}
      </PageHeader>

      {children === null ? (
        <div className="page-gutter">
          <Skeleton className="h-48 w-full rounded-2xl" />
        </div>
      ) : children.length === 0 ? (
        <div className="page-gutter">
          <EmptyState icon={Receipt} title={t("parent.empty")} />
        </div>
      ) : !canPayFees ? (
        <div className="page-gutter">
          <EmptyState icon={ShieldCheck} title={t("payments.notPermitted")} />
        </div>
      ) : (
        <>
          <div className="page-gutter">
            <ProfileTabBar
              tabs={TABS.map((key) => ({ key, label: t(`payments.tabs.${key}`) }))}
              value={tab}
              onChange={setTab}
            />
          </div>

          {/* ── Due now: the open-invoices register ── */}
          {tab === "due" && (
            <>
              {invoices !== null && (
                <DueSummary
                  invoices={invoices}
                  balanceLabel={t("payments.totalBalance")}
                  overdueLabel={t("payments.overdueCount")}
                />
              )}
              <DataTable
                columns={columns}
                data={invoices ?? []}
                loading={invoices === null}
                searchKeys={["title", "number", "academic_year_name", "term_name"]}
                searchPlaceholder={tc("actions.search")}
                filters={filterDefs}
                onRowClick={(row) => {
                  if (row.status === "unpaid" || row.status === "partial") {
                    setVerifying(row)
                    setVerifyOpen(true)
                  }
                }}
                actions={[
                  {
                    label: t("payments.submitAction"),
                    icon: Send,
                    onClick: (row: MyInvoice) => {
                      setVerifying(row)
                      setVerifyOpen(true)
                    },
                    hidden: (row: MyInvoice) => row.status !== "unpaid" && row.status !== "partial",
                  },
                ]}
                emptyMessage={t("parent.noInvoices")}
                exportFilename="my-payments"
              />
            </>
          )}

          {/* ── Upcoming: future recurring periods, before any invoice exists ── */}
          {tab === "upcoming" && (
            <div className="page-gutter">
              <div className="mx-auto space-y-3">
                {upcoming === null ? (
                  <>
                    <Skeleton className="h-16 w-full rounded-2xl" />
                    <Skeleton className="h-16 w-full rounded-2xl" />
                  </>
                ) : upcoming.length === 0 ? (
                  <EmptyState
                    icon={CalendarClock}
                    title={t("payments.noUpcoming")}
                    description={t("payments.noUpcomingDesc")}
                  />
                ) : (
                  <>
                    <div className="space-y-2.5">
                      {upcoming.map((row) => (
                        <div
                          key={`${row.fee_structure_id}-${row.period}`}
                          className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3.5 shadow-xs"
                        >
                          <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                            <CalendarClock className="size-4 text-primary" />
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">
                              {row.fee} — {row.period}
                            </p>
                            <p className="text-xs text-muted-foreground">
                              {t("payments.dueOn", { date: row.due_date })}
                            </p>
                          </div>
                          <p className="shrink-0 text-sm font-semibold tabular-nums">
                            {row.amount} ETB
                          </p>
                        </div>
                      ))}
                    </div>
                    <p className="text-xs text-muted-foreground">{t("payments.upcomingNote")}</p>
                  </>
                )}
              </div>
            </div>
          )}

          {/* ── History: every recorded payment + its QR receipt ── */}
          {tab === "history" && (
            <div className="page-gutter">
              <div className="mx-auto space-y-3">
                {history === null ? (
                  <>
                    <Skeleton className="h-16 w-full rounded-2xl" />
                    <Skeleton className="h-16 w-full rounded-2xl" />
                  </>
                ) : history.length === 0 ? (
                  <EmptyState
                    icon={Wallet}
                    title={t("payments.noHistory")}
                    description={t("payments.noHistoryDesc")}
                  />
                ) : (
                  <div className="space-y-2.5">
                    {history.map((payment) => (
                      <div
                        key={payment.id}
                        className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3.5 shadow-xs"
                      >
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-success/10">
                          <CheckCircle2 className="size-4 text-success" />
                        </span>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">
                            {payment.invoice_title ?? t("payments.title")}
                          </p>
                          <div className="flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
                            <span className="tabular-nums">{payment.paid_at}</span>
                            {payment.method && <span>· {tf(`methods.${payment.method}`)}</span>}
                            {payment.bank_name && <span>· {payment.bank_name}</span>}
                            {payment.receipt_number && (
                              <ContactActionCell kind="value" value={payment.receipt_number} />
                            )}
                          </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                          <p className="text-sm font-semibold tabular-nums">
                            {payment.amount} ETB
                          </p>
                          {payment.receipt_token && (
                            <Link
                              href={`/receipts/${payment.receipt_token}`}
                              target="_blank"
                              title={t("payments.openReceipt")}
                              aria-label={t("payments.openReceipt")}
                              className="flex size-9 items-center justify-center rounded-xl border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                              <ExternalLink className="size-4" />
                            </Link>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </>
      )}

      <VerifyPaymentSheet
        studentId={childId}
        invoice={verifying}
        open={verifyOpen}
        onOpenChange={(v) => {
          setVerifyOpen(v)
          if (!v) setVerifying(null)
        }}
        onResult={() => setReloadKey((k) => k + 1)}
      />
    </div>
  )
}
