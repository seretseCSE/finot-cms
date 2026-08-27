"use client"

import {
  AlarmClockOff,
  BadgeCent,
  CircleDollarSign,
  HandCoins,
  Hourglass,
  Landmark,
  Users,
  Wallet,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"

import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from "@/components/ui/chart"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { toEthiopian, ethiopianMonthRange } from "@/lib/ethiopian-date"
import { useTranslation } from "@/lib/i18n"
import type {
  FeeDailyCollections,
  FeeDefaulterRow,
  FeeReportOverview,
  PaymentMethod,
} from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { cn } from "@/lib/utils"
import { addisToday } from "@/lib/dates"

function etb(value: string | number): string {
  return `${Number(value).toLocaleString()} ETB`
}

/** "Today" on the Addis wall clock. */
function today(): string {
  return addisToday()
}

function daysAgo(days: number): string {
  const [y, m, d] = today().split("-").map(Number)
  const dt = new Date(y, m - 1, d)
  dt.setDate(dt.getDate() - days)
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, "0")}-${String(dt.getDate()).padStart(2, "0")}`
}

/** Aging buckets wear escalating status tones — never chart slots. */
const AGING_TONE: Record<FeeReportOverview["aging"][number]["bucket"], string> =
  {
    current: "bg-muted-foreground/30",
    "1-30": "bg-info",
    "31-60": "bg-warning",
    "61-90": "bg-warning/70",
    "90+": "bg-destructive",
  }

const METHOD_ICON: Record<PaymentMethod, typeof Wallet> = {
  wallet: Wallet,
  bank_transfer: Landmark,
  cash: HandCoins,
  other: CircleDollarSign,
}

/**
 * The receivables aging ladder: one proportional distribution bar over every
 * open birr, then a legend row per bucket. The redder the segment, the older
 * the debt.
 */
function AgingCard({
  overview,
  className,
}: {
  overview: FeeReportOverview
  className?: string
}) {
  const { t } = useTranslation("fees")
  const total = overview.aging.reduce((sum, b) => sum + Number(b.amount), 0)
  const open = overview.aging.filter((b) => Number(b.amount) > 0)

  return (
    <section
      className={cn(
        "rounded-2xl border bg-card p-4 shadow-xs md:p-5",
        className
      )}
    >
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="font-display text-base font-semibold">
          {t("reports.agingTitle")}
        </h2>
        {Number(overview.penalties_accrued) > 0 && (
          <span className="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-medium text-destructive">
            <BadgeCent className="size-3.5" strokeWidth={1.75} />
            {t("reports.penaltiesChip", {
              amount: etb(overview.penalties_accrued),
            })}
          </span>
        )}
      </div>
      <p className="mt-0.5 text-xs text-muted-foreground">
        {t("reports.agingHint")}
      </p>

      {total === 0 ? (
        <p className="flex h-40 items-center justify-center text-sm text-muted-foreground">
          {t("reports.noDefaultersTitle")}
        </p>
      ) : (
        <>
          <div className="mt-4 flex h-3 w-full gap-0.5 overflow-hidden rounded-full">
            {open.map((bucket) => (
              <div
                key={bucket.bucket}
                className={cn("h-full rounded-full", AGING_TONE[bucket.bucket])}
                style={{
                  width: `${Math.max((Number(bucket.amount) / total) * 100, 1.5)}%`,
                }}
                title={`${t(`reports.aging.${bucket.bucket}`)} · ${etb(bucket.amount)}`}
              />
            ))}
          </div>
          <ul className="mt-4 space-y-1.5">
            {overview.aging.map((bucket) => {
              const share =
                total > 0
                  ? Math.round((Number(bucket.amount) / total) * 100)
                  : 0
              return (
                <li
                  key={bucket.bucket}
                  className="flex items-center gap-2.5 text-sm"
                >
                  <span
                    className={cn(
                      "size-2.5 shrink-0 rounded-full",
                      AGING_TONE[bucket.bucket]
                    )}
                  />
                  <span className="w-20 shrink-0 text-muted-foreground">
                    {t(`reports.aging.${bucket.bucket}`)}
                  </span>
                  <span className="hidden text-xs text-muted-foreground sm:inline">
                    {t("reports.invoiceCount", { count: bucket.count })}
                  </span>
                  <span className="ml-auto font-medium tabular-nums">
                    {etb(bucket.amount)}
                  </span>
                  <span className="w-10 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                    {share}%
                  </span>
                </li>
              )
            })}
          </ul>
        </>
      )}
    </section>
  )
}

/** How money arrives: one row per method with its share of the total. */
function MethodMixCard({
  methods,
  className,
}: {
  methods: FeeReportOverview["methods"]
  className?: string
}) {
  const { t } = useTranslation("fees")
  const total = methods.reduce((sum, m) => sum + Number(m.amount), 0)

  return (
    <section
      className={cn(
        "rounded-2xl border bg-card p-4 shadow-xs md:p-5",
        className
      )}
    >
      <h2 className="font-display text-base font-semibold">
        {t("reports.methodMixTitle")}
      </h2>
      <p className="mt-0.5 text-xs text-muted-foreground">
        {t("reports.methodMixHint")}
      </p>
      {methods.length === 0 ? (
        <p className="flex h-40 items-center justify-center text-sm text-muted-foreground">
          {t("reports.noPayments")}
        </p>
      ) : (
        <ul className="mt-4 space-y-3">
          {methods.map((m) => {
            const Icon = METHOD_ICON[m.method] ?? CircleDollarSign
            const share =
              total > 0 ? Math.round((Number(m.amount) / total) * 100) : 0
            return (
              <li key={m.method}>
                <div className="flex items-center gap-2.5">
                  <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent">
                    <Icon
                      className="size-4 text-accent-foreground"
                      strokeWidth={1.75}
                    />
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-baseline justify-between gap-2">
                      <p className="truncate text-sm font-medium">
                        {t(`methods.${m.method}`)}
                      </p>
                      <p className="shrink-0 text-sm font-medium tabular-nums">
                        {etb(m.amount)}
                      </p>
                    </div>
                    <div className="mt-1.5 flex items-center gap-2">
                      <div className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                        <div
                          className="h-full rounded-full bg-primary"
                          style={{ width: `${share}%` }}
                        />
                      </div>
                      <span className="w-9 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                        {share}%
                      </span>
                    </div>
                  </div>
                </div>
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}

type DefaulterTableRow = FeeDefaulterRow & { id?: number }

const TAB_KEYS = ["overview", "defaulters", "collections"] as const
const PRESETS = ["thisEthMonth", "last7", "last30"] as const
type Preset = (typeof PRESETS)[number]

function presetRange(preset: Preset): { from: string; to: string } {
  if (preset === "thisEthMonth") {
    const ec = toEthiopian(today())
    const range = ethiopianMonthRange(ec.year, ec.month)
    return { from: range.from, to: today() < range.to ? today() : range.to }
  }
  return { from: daysAgo(preset === "last7" ? 6 : 29), to: today() }
}

export default function FeeReportsPage() {
  const { t } = useTranslation("fees")
  const router = useRouter()
  const { active } = useSchoolContext()
  const workspace = `${active.schoolId ?? ""}-${active.branchId ?? ""}`

  const [tab, setTab] = useProfileTabs(TAB_KEYS, "overview")
  const tabs = useMemo(
    () => TAB_KEYS.map((key) => ({ key, label: t(`reports.tabs.${key}`) })),
    [t]
  )

  // School-wide workspace: one branch picker narrows EVERY tab (overview
  // stats, defaulters register, daily collections) so the numbers always
  // describe the same slice. Renders nothing inside a branch workspace.
  const { needsBranch } = useBranchScope()
  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchParam =
    needsBranch && pickedBranchId != null ? `branch_id=${pickedBranchId}` : ""

  // ── Overview ──────────────────────────────────────────────────────
  const [overview, setOverview] = useState<FeeReportOverview | null>(null)
  useEffect(() => {
    if (tab !== "overview") return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale scope data
    setOverview(null)
    apiFetch<{ data: FeeReportOverview }>(
      `/fee-reports/overview${branchParam ? `?${branchParam}` : ""}`
    )
      .then((res) => !cancelled && setOverview(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [tab, workspace, branchParam])

  // ── Defaulters ────────────────────────────────────────────────────
  const table = useServerTable<DefaulterTableRow>({
    endpoint: "/fee-reports/defaulters",
    defaultSort: { key: "balance", dir: "desc" },
    enabled: tab === "defaulters",
    refreshKey: workspace,
    extraParams: pickedBranchId != null ? { branch_id: String(pickedBranchId) } : undefined,
  })

  const defaulterColumns: DataTableColumn<DefaulterTableRow>[] = [
    {
      key: "student_name",
      label: t("reports.columns.student"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.student_name ?? "—"}</p>
          {row.student_public_id ? (
            <CopyableId value={row.student_public_id} />
          ) : null}
        </div>
      ),
      exportValue: (row) => row.student_name ?? "",
    },
    {
      key: "open_invoices",
      label: t("reports.columns.openInvoices"),
      sortable: true,
      className: "text-right tabular-nums",
      mobileHidden: true,
    },
    {
      key: "balance",
      label: t("reports.columns.balance"),
      sortable: true,
      className: "text-right",
      render: (row) => (
        <span className="font-medium text-destructive tabular-nums">
          {etb(row.balance)}
        </span>
      ),
      exportValue: (row) => row.balance,
    },
    {
      key: "overdue_amount",
      label: t("reports.columns.overdue"),
      sortable: true,
      className: "text-right tabular-nums",
      render: (row) =>
        Number(row.overdue_amount) > 0 ? etb(row.overdue_amount) : "—",
      exportValue: (row) => row.overdue_amount,
    },
    {
      key: "oldest_due",
      label: t("reports.columns.oldestDue"),
      sortable: true,
      mobileHidden: true,
      render: (row) => row.oldest_due ?? "—",
      exportValue: (row) => row.oldest_due ?? "",
    },
    {
      key: "guardians",
      label: t("reports.columns.guardian"),
      mobileHidden: true,
      render: (row) =>
        row.guardians.length === 0 ? (
          "—"
        ) : (
          <ContactActionCell
            value={row.guardians[0].phone}
            name={row.guardians[0].name}
          >
            <span className="truncate text-sm">{row.guardians[0].name}</span>
          </ContactActionCell>
        ),
      exportValue: (row) =>
        row.guardians.map((g) => `${g.name} ${g.phone ?? ""}`).join("; "),
    },
  ]

  // ── Daily collections ─────────────────────────────────────────────
  const [range, setRange] = useState(() => presetRange("thisEthMonth"))
  const activePreset = PRESETS.find(
    (p) => presetRange(p).from === range.from && presetRange(p).to === range.to
  )
  const [collections, setCollections] = useState<FeeDailyCollections | null>(
    null
  )
  useEffect(() => {
    if (tab !== "collections") return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale range data
    setCollections(null)
    apiFetch<{ data: FeeDailyCollections }>(
      `/fee-reports/daily-collections?from=${range.from}&to=${range.to}${branchParam ? `&${branchParam}` : ""}`
    )
      .then((res) => !cancelled && setCollections(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [tab, range, workspace, branchParam])

  const chartData = useMemo(
    () =>
      (collections?.days ?? []).map((d) => ({
        date: d.date,
        total: Number(d.total),
      })),
    [collections]
  )
  const bestDay = useMemo(
    () =>
      (collections?.days ?? []).reduce<
        FeeDailyCollections["days"][number] | null
      >(
        (best, day) =>
          best === null || Number(day.total) > Number(best.total) ? day : best,
        null
      ),
    [collections]
  )
  const ecMonthLabel = useMemo(() => {
    const ec = toEthiopian(today())
    return t(`months.${ec.month}`)
  }, [t])

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("reports.title")}
        description={t("reports.subtitle")}
      />

      <div className="page-gutter flex flex-wrap items-center gap-3">
        <ProfileTabBar tabs={tabs} value={tab} onChange={setTab} />
        <BranchScopePicker
          value={pickedBranchId}
          onChange={setPickedBranchId}
          allOption
          className="h-9 w-full md:ml-auto md:w-56"
        />
      </div>

      {tab === "overview" && (
        <>
          <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
              label={t("reports.stats.invoiced")}
              value={overview ? etb(overview.invoiced) : null}
              icon={CircleDollarSign}
              hint={
                overview
                  ? t("reports.invoiceCount", { count: overview.invoices })
                  : undefined
              }
            />
            <StatCard
              label={t("reports.stats.collected")}
              value={overview ? etb(overview.collected) : null}
              icon={HandCoins}
              hint={
                overview?.collection_rate != null
                  ? t("reports.stats.collectionRate", {
                      rate: overview.collection_rate,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("reports.stats.outstanding")}
              value={overview ? etb(overview.outstanding) : null}
              icon={Hourglass}
              hint={
                overview
                  ? t("reports.stats.studentsOwing", {
                      count: overview.students_owing,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("reports.stats.overdue")}
              value={overview ? etb(overview.overdue_amount) : null}
              icon={AlarmClockOff}
              hint={
                overview
                  ? t("reports.stats.overdueCount", {
                      count: overview.overdue_count,
                    })
                  : undefined
              }
            />
          </div>

          <div className="page-gutter">
            {overview === null ? (
              <div className="grid gap-4 lg:grid-cols-5">
                <Skeleton className="h-72 rounded-2xl lg:col-span-3" />
                <Skeleton className="h-72 rounded-2xl lg:col-span-2" />
              </div>
            ) : (
              <div className="grid gap-4 lg:grid-cols-5">
                <AgingCard overview={overview} className="lg:col-span-3" />
                <MethodMixCard
                  methods={overview.methods}
                  className="lg:col-span-2"
                />
              </div>
            )}
          </div>
        </>
      )}

      {tab === "defaulters" && (
        // DataTable is full-bleed and brings its own gutters — never wrap
        // it in page-gutter (double padding = a narrower table than every
        // other list page).
        <DataTable
          columns={defaulterColumns}
          data={table.rows}
          loading={table.loading}
          total={table.total}
          serverMode
          searchable
          searchValue={table.searchInput}
          onSearchChange={table.setSearchInput}
          searchPlaceholder={t("reports.searchDefaulters")}
          filters={[
            {
              key: "overdue_only",
              label: t("reports.filters.overdueOnly"),
              options: [
                { value: "1", label: t("reports.filters.overdueOnly") },
              ],
            },
          ]}
          filterValues={table.filters}
          onFilterChange={table.setFilter}
          onSortChange={table.onSortChange}
          onRowClick={(row) =>
            router.push(`/students/${row.student_id}?tab=fees`)
          }
          emptyMessage={t("reports.noDefaultersHint")}
          exportFilename="fee-defaulters"
          pagination={table.pagination}
        />
      )}

      {tab === "collections" && (
        <>
          {/* Reporting window: preset pills + a compact custom range. */}
          <div className="page-gutter flex flex-wrap items-center gap-2">
            <div className="no-scrollbar inline-flex max-w-full items-center gap-0.5 overflow-x-auto rounded-full border bg-card p-1 shadow-xs">
              {PRESETS.map((preset) => (
                <button
                  key={preset}
                  type="button"
                  onClick={() => setRange(presetRange(preset))}
                  aria-pressed={activePreset === preset}
                  className={cn(
                    "pressable rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors",
                    activePreset === preset
                      ? "bg-primary text-primary-foreground"
                      : "text-muted-foreground hover:text-foreground"
                  )}
                >
                  {preset === "thisEthMonth"
                    ? t("reports.presets.thisEthMonth", { month: ecMonthLabel })
                    : t(`reports.presets.${preset}`)}
                </button>
              ))}
            </div>
            <div className="flex items-center gap-2 sm:ml-auto">
              <DatePicker
                value={range.from}
                onChange={(v) => v && setRange((r) => ({ ...r, from: v }))}
                max={range.to}
                clearable={false}
                aria-label={t("reports.from")}
                className="w-36"
              />
              <span className="text-xs text-muted-foreground">–</span>
              <DatePicker
                value={range.to}
                onChange={(v) => v && setRange((r) => ({ ...r, to: v }))}
                min={range.from}
                max={today()}
                clearable={false}
                aria-label={t("reports.to")}
                className="w-36"
              />
            </div>
          </div>

          {collections === null ? (
            <div className="page-gutter space-y-4">
              <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {[0, 1, 2].map((i) => (
                  <Skeleton key={i} className="h-24 rounded-2xl" />
                ))}
              </div>
              <Skeleton className="h-72 rounded-2xl" />
            </div>
          ) : collections.count === 0 ? (
            <div className="page-gutter">
              <EmptyState
                icon={HandCoins}
                title={t("reports.noCollectionsTitle")}
                description={t("reports.noCollectionsHint")}
              />
            </div>
          ) : (
            <>
              <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard
                  label={t("reports.stats.collectedRange")}
                  value={etb(collections.total)}
                  icon={HandCoins}
                />
                <StatCard
                  label={t("reports.stats.payments")}
                  value={collections.count}
                  icon={CircleDollarSign}
                />
                <StatCard
                  label={t("reports.stats.bestDay")}
                  value={bestDay ? etb(bestDay.total) : "—"}
                  icon={Users}
                  hint={bestDay?.date}
                  className="col-span-2 lg:col-span-1"
                />
              </div>

              <div className="page-gutter">
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <h2 className="font-display text-base font-semibold">
                    {t("reports.byDayTitle")}
                  </h2>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {t("reports.byDayHint")}
                  </p>
                  <div className="mt-4">
                    <ChartContainer
                      config={{
                        total: {
                          label: t("reports.stats.collected"),
                          color: "var(--chart-1)",
                        },
                      }}
                      className="aspect-auto h-56 w-full"
                    >
                      <BarChart
                        data={chartData}
                        margin={{ left: 12, right: 4 }}
                      >
                        <CartesianGrid vertical={false} strokeOpacity={0.5} />
                        <XAxis
                          dataKey="date"
                          tickLine={false}
                          axisLine={false}
                          tickMargin={8}
                          minTickGap={16}
                          tickFormatter={(value: string) => value.slice(5)}
                        />
                        <YAxis
                          tickLine={false}
                          axisLine={false}
                          width={56}
                          tickFormatter={(value: number) =>
                            value.toLocaleString()
                          }
                        />
                        <ChartTooltip content={<ChartTooltipContent />} />
                        <Bar
                          dataKey="total"
                          fill="var(--color-total)"
                          maxBarSize={28}
                          radius={[4, 4, 0, 0]}
                        />
                      </BarChart>
                    </ChartContainer>
                  </div>
                </section>
              </div>

              <div className="page-gutter grid gap-4 lg:grid-cols-2">
                <MethodMixCard methods={collections.methods} />
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <h2 className="font-display text-base font-semibold">
                    {t("reports.byCashierTitle")}
                  </h2>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {t("reports.byCashierHint")}
                  </p>
                  <ul className="mt-3 divide-y">
                    {collections.cashiers.map((cashier) => (
                      <li
                        key={cashier.user_id ?? "family"}
                        className="flex items-center justify-between gap-3 py-2.5"
                      >
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium">
                            {cashier.name}
                          </p>
                          <p className="text-xs text-muted-foreground">
                            {t("reports.stats.paymentsCount", {
                              count: cashier.count,
                            })}
                          </p>
                        </div>
                        <p className="shrink-0 text-sm font-semibold tabular-nums">
                          {etb(cashier.amount)}
                        </p>
                      </li>
                    ))}
                  </ul>
                </section>
              </div>
            </>
          )}
        </>
      )}
    </div>
  )
}
