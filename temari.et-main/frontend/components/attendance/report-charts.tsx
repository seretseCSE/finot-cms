"use client"

import { useMemo } from "react"
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"

import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { Skeleton } from "@/components/ui/skeleton"
import { weekdayName } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type {
  AttendanceReportTrends,
  AttendanceStatus,
} from "@/lib/types"
import { cn } from "@/lib/utils"

/** Status series wear the status tokens (DESIGN.md §3) — never chart slots. */
const STATUS_SERIES: { key: AttendanceStatus; color: string }[] = [
  { key: "present", color: "var(--success)" },
  { key: "late", color: "var(--warning)" },
  { key: "absent", color: "var(--destructive)" },
  { key: "excused", color: "var(--info)" },
]

/** ≥95% healthy, 80–95% needs a look, below 80% is a problem (same as HR). */
export function rateBand(rate: number | null): "high" | "mid" | "low" | "none" {
  if (rate == null) return "none"
  if (rate >= 95) return "high"
  if (rate >= 80) return "mid"
  return "low"
}

export const RATE_BAR: Record<ReturnType<typeof rateBand>, string> = {
  high: "bg-success",
  mid: "bg-warning",
  low: "bg-destructive",
  none: "bg-muted",
}

function ChartCard({
  title,
  hint,
  children,
  className,
}: {
  title: string
  hint?: string
  children: React.ReactNode
  className?: string
}) {
  return (
    <section className={cn("rounded-2xl border bg-card p-4 shadow-xs", className)}>
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

function useStatusConfig(): ChartConfig {
  const { t } = useTranslation("attendance")

  return useMemo(
    () =>
      Object.fromEntries(
        STATUS_SERIES.map(({ key, color }) => [
          key,
          { label: t(`statuses.${key}`), color },
        ]),
      ),
    [t],
  )
}

/**
 * The Overview tab's chart: the daily register, one stacked bar per school
 * day split by status. `trends === null` renders a matching skeleton.
 */
export function AttendanceDailyChart({ trends }: { trends: AttendanceReportTrends | null }) {
  const { t } = useTranslation("attendance")
  const statusConfig = useStatusConfig()

  if (trends === null) {
    return <Skeleton className="h-72 rounded-2xl" />
  }

  return (
    <ChartCard
      title={t("reports.charts.daily.title")}
      hint={t("reports.charts.daily.hint")}
    >
      {trends.daily.length === 0 ? (
        <ChartEmpty message={t("reports.noData")} />
      ) : (
        <ChartContainer config={statusConfig} className="aspect-auto h-56 w-full">
          <BarChart data={trends.daily} margin={{ left: -8, right: 4 }}>
            <CartesianGrid vertical={false} strokeOpacity={0.5} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              minTickGap={16}
              tickFormatter={(value: string) => value.slice(5)}
            />
            <YAxis tickLine={false} axisLine={false} allowDecimals={false} width={40} />
            <ChartTooltip content={<ChartTooltipContent />} />
            <ChartLegend content={<ChartLegendContent />} />
            {STATUS_SERIES.map(({ key }, index) => (
              <Bar
                key={key}
                dataKey={key}
                stackId="day"
                fill={`var(--color-${key})`}
                stroke="var(--card)"
                strokeWidth={1}
                maxBarSize={24}
                radius={index === STATUS_SERIES.length - 1 ? [4, 4, 0, 0] : undefined}
              />
            ))}
          </BarChart>
        </ChartContainer>
      )}
    </ChartCard>
  )
}

/**
 * The Patterns tab's charts: the weekday pattern of late + absent marks, how
 * marks were captured (RFID gate vs manual register) and the arrival-time
 * histogram from check-ins. `trends === null` renders matching skeletons.
 */
export function AttendancePatternCharts({ trends }: { trends: AttendanceReportTrends | null }) {
  const { t, locale } = useTranslation("attendance")
  const statusConfig = useStatusConfig()

  // Late + absent marks summed per weekday — reveals market-day / Friday
  // patterns. Register days only.
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
    return [1, 2, 3, 4, 5, 6, 0]
      .filter((day) => byDay.has(day))
      .map((day) => ({ day: weekdayName(day, locale, true), ...byDay.get(day)! }))
  }, [trends, locale])

  const sourceConfig: ChartConfig = {
    device: { label: t("source.device"), color: "var(--chart-1)" },
    manual: { label: t("source.manual"), color: "var(--chart-2)" },
  }

  const arrivalConfig: ChartConfig = {
    on_time: { label: t("reports.charts.arrivals.onTime"), color: "var(--success)" },
    late: { label: t("statuses.late"), color: "var(--warning)" },
  }

  const arrivalData = useMemo(
    () =>
      (trends?.arrivals ?? []).map((bucket) => ({
        time: bucket.time,
        on_time: bucket.total - bucket.late,
        late: bucket.late,
      })),
    [trends],
  )

  if (trends === null) {
    return (
      <>
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <Skeleton className="h-64 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
        <Skeleton className="h-64 rounded-2xl" />
      </>
    )
  }

  const hasDaily = trends.daily.length > 0

  return (
    <>
      {/* ── Weekday pattern + capture method ── */}
      <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <ChartCard
          title={t("reports.charts.weekday.title")}
          hint={t("reports.charts.weekday.hint")}
        >
          {weekdayData.length === 0 ? (
            <ChartEmpty message={t("reports.noData")} />
          ) : (
            <ChartContainer config={statusConfig} className="aspect-auto h-48 w-full">
              <BarChart data={weekdayData} margin={{ left: -8, right: 4 }}>
                <CartesianGrid vertical={false} strokeOpacity={0.5} />
                <XAxis dataKey="day" tickLine={false} axisLine={false} tickMargin={8} />
                <YAxis
                  tickLine={false}
                  axisLine={false}
                  allowDecimals={false}
                  width={40}
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
          title={t("reports.charts.sources.title")}
          hint={t("reports.charts.sources.hint")}
        >
          {!hasDaily ? (
            <ChartEmpty message={t("reports.noData")} />
          ) : (
            <ChartContainer config={sourceConfig} className="aspect-auto h-48 w-full">
              <BarChart data={trends.daily} margin={{ left: -8, right: 4 }}>
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
                  allowDecimals={false}
                  width={40}
                />
                <ChartTooltip content={<ChartTooltipContent />} />
                <ChartLegend content={<ChartLegendContent />} />
                <Bar
                  dataKey="device"
                  stackId="src"
                  fill="var(--color-device)"
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={24}
                />
                <Bar
                  dataKey="manual"
                  stackId="src"
                  fill="var(--color-manual)"
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

      {/* ── Arrival times (device + manual check-ins) ── */}
      <ChartCard
        title={t("reports.charts.arrivals.title")}
        hint={t("reports.charts.arrivals.hint")}
      >
        {arrivalData.length === 0 ? (
          <ChartEmpty message={t("reports.charts.arrivals.empty")} />
        ) : (
          <ChartContainer config={arrivalConfig} className="aspect-auto h-48 w-full">
            <BarChart data={arrivalData} margin={{ left: -8, right: 4 }}>
              <CartesianGrid vertical={false} strokeOpacity={0.5} />
              <XAxis dataKey="time" tickLine={false} axisLine={false} tickMargin={8} />
              <YAxis tickLine={false} axisLine={false} allowDecimals={false} width={40} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <ChartLegend content={<ChartLegendContent />} />
              <Bar
                dataKey="on_time"
                stackId="arr"
                fill="var(--color-on_time)"
                stroke="var(--card)"
                strokeWidth={1}
                maxBarSize={28}
              />
              <Bar
                dataKey="late"
                stackId="arr"
                fill="var(--color-late)"
                stroke="var(--card)"
                strokeWidth={1}
                maxBarSize={28}
                radius={[4, 4, 0, 0]}
              />
            </BarChart>
          </ChartContainer>
        )}
      </ChartCard>
    </>
  )
}
