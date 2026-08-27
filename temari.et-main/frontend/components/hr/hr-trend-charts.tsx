"use client"

import { useMemo } from "react"
import { Bar, BarChart, CartesianGrid, LabelList, XAxis, YAxis } from "recharts"

import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { Skeleton } from "@/components/ui/skeleton"
import { fmtMonthName, weekdayName } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type { HrTenureBucket, HrTrendsReport, EmployeeAttendanceStatus, Locale } from "@/lib/types"
import { formatETB } from "@/lib/utils"

/** Status series wear the status tokens; half-day is a softened warning step. */
const STATUS_SERIES: { key: EmployeeAttendanceStatus; color: string }[] = [
  { key: "present", color: "var(--success)" },
  { key: "late", color: "var(--warning)" },
  { key: "half_day", color: "color-mix(in oklab, var(--warning) 55%, var(--card))" },
  { key: "absent", color: "var(--destructive)" },
  { key: "excused", color: "var(--info)" },
]

const TENURE_ORDER: HrTenureBucket[] = ["lt1", "1to3", "3to5", "5to10", "gte10", "unknown"]

/** Compact ETB axis ticks: 1.2M / 340K / 900. */
function compactAmount(value: number): string {
  if (Math.abs(value) >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`
  if (Math.abs(value) >= 1_000) return `${Math.round(value / 1_000)}K`
  return String(value)
}

function monthLabel(month: string, locale: Locale): string {
  // The 15th is safely inside every Gregorian month, so the Ethiopian month it
  // maps to is the one this bucket is actually about.
  return fmtMonthName(`${month}-15`, locale) || month
}

function ChartCard({
  title,
  hint,
  children,
}: {
  title: string
  hint?: string
  children: React.ReactNode
}) {
  return (
    <section className="rounded-2xl border bg-card p-4 shadow-xs">
      <h2 className="font-display text-base font-semibold">{title}</h2>
      {hint && <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p>}
      <div className="mt-4">{children}</div>
    </section>
  )
}

function ChartEmpty({ message }: { message: string }) {
  return (
    <p className="flex h-48 items-center justify-center text-sm text-muted-foreground">
      {message}
    </p>
  )
}

/**
 * The four HR trend charts (daily register, weekday pattern, leave by month,
 * payroll history) + tenure. `trends === null` renders matching skeletons.
 */
export function HrTrendCharts({ trends }: { trends: HrTrendsReport | null }) {
  const { t, locale } = useTranslation("hr")

  const statusConfig: ChartConfig = useMemo(
    () =>
      Object.fromEntries(
        STATUS_SERIES.map(({ key, color }) => [
          key,
          { label: t(`attendance.statuses.${key}`), color },
        ]),
      ),
    [t],
  )

  // Late + absent marks summed per weekday, register days only.
  const weekdayData = useMemo(() => {
    if (!trends) return []
    const byDay = new Map<number, { late: number; absent: number }>()
    for (const row of trends.daily) {
      const day = new Date(`${row.date}T12:00:00`).getDay()
      const entry = byDay.get(day) ?? { late: 0, absent: 0 }
      entry.late += row.late
      entry.absent += row.absent
      byDay.set(day, entry)
    }
    // Monday-first week; only weekdays that had a register.
    return [1, 2, 3, 4, 5, 6, 0]
      .filter((day) => byDay.has(day))
      .map((day) => ({ day: weekdayName(day, locale, true), ...byDay.get(day)! }))
  }, [trends, locale])

  const leaveConfig: ChartConfig = {
    paid: { label: t("leave.paid"), color: "var(--chart-1)" },
    unpaid: { label: t("leave.unpaid"), color: "var(--chart-2)" },
  }

  const payrollConfig: ChartConfig = {
    net: { label: t("reports.charts.payroll.net"), color: "var(--chart-1)" },
    deductions: { label: t("reports.charts.payroll.deductions"), color: "var(--chart-2)" },
    employer_pension: {
      label: t("reports.charts.payroll.employerPension"),
      color: "var(--chart-3)",
    },
  }

  const tenureConfig: ChartConfig = {
    employees: { label: t("reports.charts.tenure.employees"), color: "var(--chart-1)" },
  }

  const tenureData = useMemo(() => {
    if (!trends) return []
    return TENURE_ORDER.filter(
      (bucket) => bucket !== "unknown" || trends.tenure[bucket] > 0,
    ).map((bucket) => ({
      bucket: t(`reports.charts.tenure.buckets.${bucket}`),
      employees: trends.tenure[bucket],
    }))
  }, [trends, t])

  const hasLeave = trends?.leave_monthly.some((m) => m.paid > 0 || m.unpaid > 0) ?? false

  if (trends === null) {
    return (
      <>
        <Skeleton className="h-72 rounded-2xl" />
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <Skeleton className="h-64 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <Skeleton className="h-64 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
      </>
    )
  }

  return (
    <>
      {/* ── Daily register — full width ── */}
      <ChartCard
        title={t("reports.charts.daily.title")}
        hint={t("reports.charts.daily.hint")}
      >
        {trends.daily.length === 0 ? (
          <ChartEmpty message={t("reports.attendanceMix.empty")} />
        ) : (
          <ChartContainer config={statusConfig} className="aspect-auto h-56 w-full">
            <BarChart data={trends.daily} margin={{ left: -20, right: 4 }}>
              <CartesianGrid vertical={false} strokeOpacity={0.5} />
              <XAxis
                dataKey="date"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                minTickGap={16}
                tickFormatter={(value: string) => value.slice(5)}
              />
              <YAxis tickLine={false} axisLine={false} allowDecimals={false} width={36} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <ChartLegend content={<ChartLegendContent />} />
              {STATUS_SERIES.map(({ key }) => (
                <Bar
                  key={key}
                  dataKey={key}
                  stackId="day"
                  fill={`var(--color-${key})`}
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                />
              ))}
            </BarChart>
          </ChartContainer>
        )}
      </ChartCard>

      {/* ── Weekday pattern + leave by month ── */}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <ChartCard
          title={t("reports.charts.weekday.title")}
          hint={t("reports.charts.weekday.hint")}
        >
          {weekdayData.length === 0 ? (
            <ChartEmpty message={t("reports.attendanceMix.empty")} />
          ) : (
            <ChartContainer config={statusConfig} className="aspect-auto h-48 w-full">
              <BarChart data={weekdayData} margin={{ left: -20, right: 4 }}>
                <CartesianGrid vertical={false} strokeOpacity={0.5} />
                <XAxis dataKey="day" tickLine={false} axisLine={false} tickMargin={8} />
                <YAxis
                  tickLine={false}
                  axisLine={false}
                  allowDecimals={false}
                  width={36}
                />
                <ChartTooltip content={<ChartTooltipContent />} />
                <ChartLegend content={<ChartLegendContent />} />
                <Bar
                  dataKey="late"
                  stackId="wd"
                  fill="var(--color-late)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                />
                <Bar
                  dataKey="absent"
                  stackId="wd"
                  fill="var(--color-absent)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                  radius={[4, 4, 0, 0]}
                />
              </BarChart>
            </ChartContainer>
          )}
        </ChartCard>

        <ChartCard
          title={t("reports.charts.leaveMonthly.title")}
          hint={t("reports.charts.leaveMonthly.hint")}
        >
          {!hasLeave ? (
            <ChartEmpty message={t("reports.charts.leaveMonthly.empty")} />
          ) : (
            <ChartContainer config={leaveConfig} className="aspect-auto h-48 w-full">
              <BarChart data={trends.leave_monthly} margin={{ left: -20, right: 4 }}>
                <CartesianGrid vertical={false} strokeOpacity={0.5} />
                <XAxis
                  dataKey="month"
                  tickLine={false}
                  axisLine={false}
                  tickMargin={8}
                  tickFormatter={(value: string) => monthLabel(value, locale)}
                />
                <YAxis
                  tickLine={false}
                  axisLine={false}
                  allowDecimals={false}
                  width={36}
                />
                <ChartTooltip
                  content={
                    <ChartTooltipContent
                      labelFormatter={(value) => monthLabel(String(value), locale)}
                    />
                  }
                />
                <ChartLegend content={<ChartLegendContent />} />
                <Bar
                  dataKey="paid"
                  stackId="leave"
                  fill="var(--color-paid)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                />
                <Bar
                  dataKey="unpaid"
                  stackId="leave"
                  fill="var(--color-unpaid)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                  radius={[4, 4, 0, 0]}
                />
              </BarChart>
            </ChartContainer>
          )}
        </ChartCard>
      </div>

      {/* ── Payroll history + tenure ── */}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <ChartCard
          title={t("reports.charts.payroll.title")}
          hint={t("reports.charts.payroll.hint")}
        >
          {trends.payroll_runs.length === 0 ? (
            <ChartEmpty message={t("reports.charts.payroll.empty")} />
          ) : (
            <ChartContainer config={payrollConfig} className="aspect-auto h-48 w-full">
              <BarChart data={trends.payroll_runs} margin={{ left: -8, right: 4 }}>
                <CartesianGrid vertical={false} strokeOpacity={0.5} />
                <XAxis
                  dataKey="period_end"
                  tickLine={false}
                  axisLine={false}
                  tickMargin={8}
                  tickFormatter={(value: string) => monthLabel(value.slice(0, 7), locale)}
                />
                <YAxis
                  tickLine={false}
                  axisLine={false}
                  width={44}
                  tickFormatter={(value: number) => compactAmount(value)}
                />
                <ChartTooltip
                  content={
                    <ChartTooltipContent
                      labelFormatter={(_, payload) =>
                        (payload?.[0]?.payload as { name?: string })?.name ?? ""
                      }
                      formatter={(value, name, item) => (
                        <span className="flex w-full items-center justify-between gap-3">
                          <span className="flex items-center gap-1.5 text-muted-foreground">
                            <span
                              className="size-2 rounded-[2px]"
                              style={{ background: item?.color }}
                            />
                            {payrollConfig[name as string]?.label ?? name}
                          </span>
                          <span className="font-mono tabular-nums">
                            {formatETB(Number(value))}
                          </span>
                        </span>
                      )}
                    />
                  }
                />
                <ChartLegend content={<ChartLegendContent />} />
                <Bar
                  dataKey="net"
                  stackId="run"
                  fill="var(--color-net)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                />
                <Bar
                  dataKey="deductions"
                  stackId="run"
                  fill="var(--color-deductions)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                />
                <Bar
                  dataKey="employer_pension"
                  stackId="run"
                  fill="var(--color-employer_pension)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                  radius={[4, 4, 0, 0]}
                />
              </BarChart>
            </ChartContainer>
          )}
        </ChartCard>

        <ChartCard
          title={t("reports.charts.tenure.title")}
          hint={t("reports.charts.tenure.hint")}
        >
          {tenureData.every((row) => row.employees === 0) ? (
            <ChartEmpty message={t("reports.charts.tenure.empty")} />
          ) : (
            <ChartContainer config={tenureConfig} className="aspect-auto h-48 w-full">
              <BarChart data={tenureData} margin={{ top: 16, left: -20, right: 4 }}>
                <CartesianGrid vertical={false} strokeOpacity={0.5} />
                <XAxis dataKey="bucket" tickLine={false} axisLine={false} tickMargin={8} />
                <YAxis
                  tickLine={false}
                  axisLine={false}
                  allowDecimals={false}
                  width={36}
                />
                <ChartTooltip content={<ChartTooltipContent hideLabel />} />
                <Bar
                  dataKey="employees"
                  fill="var(--color-employees)"
                  maxBarSize={24}
                  radius={[4, 4, 0, 0]}
                >
                  <LabelList
                    dataKey="employees"
                    position="top"
                    className="fill-muted-foreground"
                    fontSize={11}
                  />
                </Bar>
              </BarChart>
            </ChartContainer>
          )}
        </ChartCard>
      </div>
    </>
  )
}
