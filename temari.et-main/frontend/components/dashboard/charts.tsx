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
import { useTranslation } from "@/lib/i18n"
import type { DashboardAttendanceDay, DashboardFinanceMonth } from "@/lib/types"
import { cn, formatETB } from "@/lib/utils"

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
    <section
      className={cn(
        "rounded-2xl border bg-card p-4 shadow-xs md:p-5",
        className
      )}
    >
      <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {title}
      </h2>
      {hint && (
        <p className="mt-0.5 text-xs text-muted-foreground/80">{hint}</p>
      )}
      <div className="mt-3">{children}</div>
    </section>
  )
}

/** Compact bar label: 1.2M / 340K / 900 — blank for zero-value bars. */
function compactLabel(value: unknown): string {
  const n = Number(value)
  if (!n || n <= 0) return ""
  if (n >= 1_000_000)
    return `${(n / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`
  if (n >= 1_000) return `${Math.round(n / 1_000)}K`
  return String(Math.round(n))
}

function ChartEmpty({ message }: { message: string }) {
  return (
    <p className="flex h-44 items-center justify-center text-center text-sm text-muted-foreground">
      {message}
    </p>
  )
}

/**
 * The last 7 marked school days, one stacked bar per day split by status.
 * Status series wear the status tokens (DESIGN.md §3) — never chart slots.
 */
export function AttendanceWeekChart({
  week,
}: {
  week: DashboardAttendanceDay[]
}) {
  const { t } = useTranslation("common")
  const { t: ta } = useTranslation("attendance")

  const config: ChartConfig = useMemo(
    () => ({
      present: { label: ta("statuses.present"), color: "var(--success)" },
      late: { label: ta("statuses.late"), color: "var(--warning)" },
      absent: { label: ta("statuses.absent"), color: "var(--destructive)" },
      excused: { label: ta("statuses.excused"), color: "var(--info)" },
    }),
    [ta]
  )

  return (
    <ChartCard
      title={t("dashboard.attendanceWeek")}
      hint={t("dashboard.attendanceWeekHint")}
    >
      {week.length === 0 ? (
        <ChartEmpty message={t("dashboard.noAttendanceYet")} />
      ) : (
        <ChartContainer config={config} className="aspect-auto h-52 w-full">
          <BarChart data={week} margin={{ left: -8, right: 4 }}>
            <CartesianGrid vertical={false} strokeOpacity={0.5} />
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              fontSize={11}
              tickFormatter={(value: string) => value.slice(5)}
            />
            <YAxis
              tickLine={false}
              axisLine={false}
              allowDecimals={false}
              width={40}
              fontSize={11}
            />
            <ChartTooltip content={<ChartTooltipContent />} />
            <ChartLegend content={<ChartLegendContent />} />
            {(["present", "late", "absent", "excused"] as const).map(
              (key, index, all) => (
                <Bar
                  key={key}
                  dataKey={key}
                  stackId="day"
                  fill={`var(--color-${key})`}
                  stroke="var(--card)"
                  strokeWidth={1}
                  maxBarSize={28}
                  radius={index === all.length - 1 ? [4, 4, 0, 0] : undefined}
                />
              )
            )}
          </BarChart>
        </ChartContainer>
      )}
    </ChartCard>
  )
}

/**
 * Fee collections over the last six Ethiopian months — the money pulse a
 * principal reads before any meeting. Month names localize via fees:months.
 */
export function CollectionsTrendChart({
  trend,
}: {
  trend: DashboardFinanceMonth[]
}) {
  const { t } = useTranslation("common")
  const { t: tf } = useTranslation("fees")

  const rows = useMemo(
    () =>
      trend.map((row) => ({
        month: `${tf(`months.${row.ec_month}`)}`,
        full: `${tf(`months.${row.ec_month}`)} ${row.ec_year}`,
        collected: Number(row.collected) || 0,
        payments: row.payments,
      })),
    [trend, tf]
  )

  const config: ChartConfig = {
    collected: { label: t("dashboard.collected"), color: "var(--chart-1)" },
  }

  const hasMoney = rows.some((row) => row.collected > 0)

  return (
    <ChartCard
      title={t("dashboard.collectionsTrend")}
      hint={t("dashboard.collectionsTrendHint")}
    >
      {!hasMoney ? (
        <ChartEmpty message={t("dashboard.noCollectionsYet")} />
      ) : (
        <ChartContainer config={config} className="aspect-auto h-52 w-full">
          <BarChart data={rows} margin={{ top: 18, left: 4, right: 4 }}>
            <CartesianGrid vertical={false} strokeOpacity={0.5} />
            <XAxis
              dataKey="month"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              fontSize={11}
            />
            <YAxis
              tickLine={false}
              axisLine={false}
              width={44}
              fontSize={11}
              tickFormatter={(value: number) =>
                value >= 1_000_000
                  ? `${(value / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`
                  : value >= 1_000
                    ? `${Math.round(value / 1_000)}K`
                    : String(value)
              }
            />
            <ChartTooltip
              content={
                <ChartTooltipContent
                  labelFormatter={(_, payload) =>
                    (payload?.[0]?.payload as { full?: string })?.full ?? ""
                  }
                  formatter={(value, name, item) => (
                    <div className="flex w-full flex-col gap-1">
                      <span className="flex items-center justify-between gap-3">
                        <span className="flex items-center gap-1.5 text-muted-foreground">
                          <span
                            className="size-2 rounded-[2px]"
                            style={{ background: item?.color }}
                          />
                          {config[name as string]?.label ?? name}
                        </span>
                        <span className="font-mono tabular-nums">
                          {formatETB(Number(value))}
                        </span>
                      </span>
                      <span className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                        <span>{t("dashboard.payments")}</span>
                        <span className="font-mono tabular-nums">
                          {(item?.payload as { payments?: number })?.payments ??
                            0}
                        </span>
                      </span>
                    </div>
                  )}
                />
              }
            />
            <Bar
              dataKey="collected"
              fill="var(--color-collected)"
              maxBarSize={32}
              radius={[4, 4, 0, 0]}
            >
              <LabelList
                dataKey="collected"
                position="top"
                fontSize={10}
                className="fill-muted-foreground"
                formatter={compactLabel}
              />
            </Bar>
          </BarChart>
        </ChartContainer>
      )}
    </ChartCard>
  )
}
