"use client"

import { ArrowLeft, CalendarClock, HandCoins, Hash } from "lucide-react"
import Link from "next/link"
import { useParams } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"

import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { fmtMonthName } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { formatETB } from "@/lib/utils"
import type {
  BankAccount,
  BankAccountPayment,
  BankAccountStats,
  Locale,
  PaymentMethod,
} from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"

const METHODS: PaymentMethod[] = [
  "wallet",
  "bank_transfer",
  "cash",
  "other",
]

function monthLabel(month: string, locale: Locale): string {
  // The 15th is safely inside every Gregorian month, so the Ethiopian month it
  // maps to is the one this bucket is actually about.
  return fmtMonthName(`${month}-15`, locale) || month
}

export default function PaymentAccountDetailPage() {
  const { t, locale } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const params = useParams<{ id: string }>()
  const accountId = params.id

  const [account, setAccount] = useState<BankAccount | null>(null)
  const [stats, setStats] = useState<BankAccountStats | null>(null)

  const table = useServerTable<BankAccountPayment>({
    endpoint: `/bank-accounts/${accountId}/payments`,
    defaultSort: { key: "paid_at", dir: "desc" },
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("accounts.loadFailed"),
  })

  useEffect(() => {
    let cancelled = false
    // No dedicated show endpoint — the school's account list is tiny.
    apiFetch<{ data: BankAccount[] }>("/bank-accounts")
      .then((res) => {
        if (cancelled) return
        setAccount(res.data.find((a) => String(a.id) === accountId) ?? null)
      })
      .catch(() => {})
    apiFetch<{ data: BankAccountStats }>(`/bank-accounts/${accountId}/stats`)
      .then((res) => !cancelled && setStats(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [accountId, active.branchId, active.schoolId])

  const chartConfig: ChartConfig = {
    total: { label: t("accounts.report.collected"), color: "var(--chart-1)" },
  }

  const monthly = useMemo(
    () =>
      (stats?.monthly ?? []).map((row) => ({
        month: monthLabel(row.month, locale),
        total: Number(row.total),
      })),
    [stats, locale]
  )

  const columns: DataTableColumn<BankAccountPayment>[] = [
    {
      key: "invoice_number",
      label: t("invoices.columns.number"),
      render: (row) => <CopyableId value={row.invoice_number} />,
      exportValue: (row) => row.invoice_number,
    },
    {
      key: "student_name",
      label: t("invoices.columns.student"),
      primary: true,
      render: (row) => (
        <span className="flex flex-col">
          <span className="font-medium">{row.student_name ?? "—"}</span>
          {row.student_public_id && (
            <span className="text-xs text-muted-foreground">{row.student_public_id}</span>
          )}
        </span>
      ),
      exportValue: (row) => row.student_name ?? "",
    },
    {
      key: "invoice_title",
      label: t("invoices.columns.title"),
      mobileHidden: true,
      render: (row) => row.invoice_title ?? "—",
      exportValue: (row) => row.invoice_title ?? "",
    },
    {
      key: "amount",
      label: t("payments.amount"),
      render: (row) => <span className="tabular-nums">{row.amount} ETB</span>,
      exportValue: (row) => row.amount,
    },
    {
      key: "method",
      label: t("payments.method"),
      mobileHidden: true,
      render: (row) => t(`methods.${row.method}`),
      exportValue: (row) => row.method_label,
    },
    {
      key: "reference",
      label: t("payments.reference"),
      mobileHidden: true,
      render: (row) => row.reference ?? "—",
      exportValue: (row) => row.reference ?? "",
    },
    {
      key: "paid_at",
      label: t("payments.paidAt"),
      render: (row) => row.paid_at ?? "—",
      exportValue: (row) => row.paid_at ?? "",
    },
    {
      key: "recorded_by_name",
      label: t("payments.recordedBy"),
      mobileHidden: true,
      render: (row) => row.recorded_by_name ?? "—",
      exportValue: (row) => row.recorded_by_name ?? "",
    },
  ]

  return (
    <div className="space-y-6">
      <div className="space-y-3 px-4 md:px-8">
        <Button asChild variant="ghost" size="sm" className="-ml-2 h-9 text-muted-foreground">
          <Link href="/payment-accounts">
            <ArrowLeft className="size-4" />
            {t("accounts.pageTitle")}
          </Link>
        </Button>
        {account === null ? (
          <Skeleton className="h-12 w-72 rounded-xl" />
        ) : (
          <div className="flex items-center gap-3">
            <BankLogo bank={account.bank} size={44} />
            <div className="min-w-0">
              <h1 className="font-display flex items-center gap-2 text-2xl font-semibold tracking-tight md:text-[1.75rem]">
                <span className="truncate">{account.account_name}</span>
                {account.bank?.type === "wallet" && (
                  <Badge variant="outline">{t("accounts.wallet")}</Badge>
                )}
                {!account.is_active && (
                  <Badge variant="secondary">{tc("states.inactive")}</Badge>
                )}
              </h1>
              <p className="text-sm text-muted-foreground">
                {account.bank?.name} ·{" "}
                <ContactActionCell
                  kind="value"
                  value={account.account_number}
                  name={account.account_name}
                  triggerClassName="text-sm"
                />
              </p>
            </div>
          </div>
        )}
      </div>

      <div className="grid grid-cols-2 gap-3 px-4 md:px-8 xl:grid-cols-3">
        <StatCard
          label={t("accounts.report.collected")}
          value={stats ? formatETB(stats.collected) : null}
          icon={HandCoins}
        />
        <StatCard
          label={t("accounts.columns.transactions")}
          value={stats ? stats.transactions : null}
          icon={Hash}
        />
        <StatCard
          label={t("accounts.columns.lastPayment")}
          value={stats ? (stats.last_paid_at ?? "—") : null}
          icon={CalendarClock}
          className="col-span-2 xl:col-span-1"
        />
      </div>

      <div className="grid gap-4 px-4 md:px-8 lg:grid-cols-3">
        <section className="rounded-2xl border bg-card p-4 shadow-xs lg:col-span-2">
          <h2 className="font-display text-base font-semibold">
            {t("accounts.report.monthlyTitle")}
          </h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {t("accounts.report.monthlyHint")}
          </p>
          <div className="mt-4">
            {stats === null ? (
              <Skeleton className="h-48 rounded-xl" />
            ) : monthly.length === 0 ? (
              <p className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                {t("accounts.report.empty")}
              </p>
            ) : (
              <ChartContainer config={chartConfig} className="h-48 w-full">
                <BarChart data={monthly} margin={{ left: 4, right: 4 }}>
                  <CartesianGrid vertical={false} strokeDasharray="3 3" />
                  <XAxis dataKey="month" tickLine={false} axisLine={false} fontSize={11} />
                  <YAxis
                    tickLine={false}
                    axisLine={false}
                    fontSize={11}
                    width={44}
                    tickFormatter={(v: number) =>
                      Math.abs(v) >= 1000 ? `${Math.round(v / 1000)}K` : String(v)
                    }
                  />
                  <ChartTooltip content={<ChartTooltipContent />} />
                  <Bar dataKey="total" fill="var(--color-total)" radius={[6, 6, 0, 0]} />
                </BarChart>
              </ChartContainer>
            )}
          </div>
        </section>

        <section className="space-y-4">
          <div className="rounded-2xl border bg-card p-4 shadow-xs">
            <h2 className="font-display text-base font-semibold">
              {t("accounts.report.byMethodTitle")}
            </h2>
            <ul className="mt-3 space-y-2">
              {(stats?.by_method ?? []).map((row) => (
                <li key={row.method} className="flex items-center justify-between text-sm">
                  <span>{t(`methods.${row.method}`)}</span>
                  <span className="tabular-nums font-medium">
                    {formatETB(row.total)}{" "}
                    <span className="text-xs font-normal text-muted-foreground">
                      ×{row.count}
                    </span>
                  </span>
                </li>
              ))}
              {stats !== null && stats.by_method.length === 0 && (
                <li className="text-sm text-muted-foreground">{t("accounts.report.empty")}</li>
              )}
            </ul>
          </div>
          <div className="rounded-2xl border bg-card p-4 shadow-xs">
            <h2 className="font-display text-base font-semibold">
              {t("accounts.report.byFeeTitle")}
            </h2>
            <ul className="mt-3 space-y-2">
              {(stats?.by_fee ?? []).map((row) => (
                <li key={row.fee} className="flex items-center justify-between gap-3 text-sm">
                  <span className="truncate">{row.fee}</span>
                  <span className="shrink-0 tabular-nums font-medium">{formatETB(row.total)}</span>
                </li>
              ))}
              {stats !== null && stats.by_fee.length === 0 && (
                <li className="text-sm text-muted-foreground">{t("accounts.report.empty")}</li>
              )}
            </ul>
          </div>
        </section>
      </div>

      <DataTable
        columns={columns}
        data={table.rows}
        loading={table.loading}
        serverMode
        filters={[
          {
            key: "method",
            label: t("payments.method"),
            options: METHODS.map((m) => ({ label: t(`methods.${m}`), value: m })),
          },
        ]}
        filterValues={table.filters}
        onFilterChange={table.setFilter}
        emptyMessage={t("accounts.report.noPayments")}
        exportFilename="account-payments"
        pagination={table.pagination}
      />
    </div>
  )
}
