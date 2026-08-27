"use client"

import { useMemo } from "react"
import { Bar, BarChart, CartesianGrid, Cell, LabelList, XAxis, YAxis } from "recharts"

import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { Skeleton } from "@/components/ui/skeleton"
import { useTranslation } from "@/lib/i18n"
import type { GradingReport } from "@/lib/types"

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
    <section className="bg-card rounded-2xl border p-4 shadow-xs">
      <h2 className="font-display text-base font-semibold">{title}</h2>
      {hint && <p className="text-muted-foreground mt-0.5 text-xs">{hint}</p>}
      <div className="mt-4">{children}</div>
    </section>
  )
}

function ChartEmpty({ message }: { message: string }) {
  return (
    <p className="text-muted-foreground flex h-48 items-center justify-center text-sm">
      {message}
    </p>
  )
}

/**
 * The grading dashboard charts: grade-band distribution, subject averages,
 * section comparison and marklist submission progress — all reading the one
 * frozen-rows aggregate payload. `report === null` renders skeletons.
 */
export function GradingReportCharts({ report }: { report: GradingReport | null }) {
  const { t } = useTranslation("grading")

  const bandConfig: ChartConfig = useMemo(
    () => ({ count: { label: t("reports.students"), color: "var(--chart-1)" } }),
    [t],
  )
  const subjectConfig: ChartConfig = useMemo(
    () => ({ average: { label: t("reportCards.average"), color: "var(--chart-2)" } }),
    [t],
  )
  const sectionConfig: ChartConfig = useMemo(
    () => ({
      average: { label: t("reportCards.average"), color: "var(--chart-3)" },
      pass_rate: { label: t("reports.passRate"), color: "var(--chart-4)" },
    }),
    [t],
  )
  const marklistConfig: ChartConfig = useMemo(
    () => ({
      draft: { label: t("marklists.statuses.draft"), color: "var(--muted-foreground)" },
      submitted: { label: t("marklists.statuses.submitted"), color: "var(--warning)" },
      approved: { label: t("marklists.statuses.approved"), color: "var(--success)" },
    }),
    [t],
  )

  if (report === null) {
    return (
      <div className="grid gap-4 lg:grid-cols-2">
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-72 rounded-2xl" />
        ))}
      </div>
    )
  }

  const bands = report.bands.map((band) => ({
    ...band,
    name: band.letter ? `${band.letter} · ${band.label}` : band.label,
  }))
  const marklistRow = [
    {
      name: t("reports.marklistProgress"),
      draft: report.marklists.draft,
      submitted: report.marklists.submitted,
      approved: report.marklists.approved,
    },
  ]

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <ChartCard title={t("reports.bandTitle")} hint={t("reports.bandHint")}>
        {bands.length === 0 ? (
          <ChartEmpty message={t("reports.noData")} />
        ) : (
          <ChartContainer config={bandConfig} className="h-56 w-full">
            <BarChart data={bands} margin={{ top: 18, left: -18 }}>
              <CartesianGrid vertical={false} strokeDasharray="3 3" />
              <XAxis dataKey="name" tickLine={false} axisLine={false} fontSize={11} />
              <YAxis tickLine={false} axisLine={false} allowDecimals={false} fontSize={11} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <Bar dataKey="count" radius={[6, 6, 0, 0]}>
                <LabelList position="top" fontSize={11} />
                {bands.map((band, i) => (
                  <Cell
                    key={i}
                    fill={band.is_passing ? "var(--chart-1)" : "var(--destructive)"}
                  />
                ))}
              </Bar>
            </BarChart>
          </ChartContainer>
        )}
      </ChartCard>

      <ChartCard title={t("reports.marklistTitle")} hint={t("reports.marklistHint")}>
        {report.marklists.total === 0 ? (
          <ChartEmpty message={t("reports.noData")} />
        ) : (
          <div className="space-y-4">
            <ChartContainer config={marklistConfig} className="h-24 w-full">
              <BarChart data={marklistRow} layout="vertical" margin={{ left: -60 }}>
                <XAxis type="number" hide domain={[0, report.marklists.total]} />
                <YAxis type="category" dataKey="name" hide />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Bar dataKey="approved" stackId="a" fill="var(--success)" radius={[6, 0, 0, 6]} />
                <Bar dataKey="submitted" stackId="a" fill="var(--warning)" />
                <Bar
                  dataKey="draft"
                  stackId="a"
                  fill="color-mix(in oklab, var(--muted-foreground) 35%, var(--card))"
                  radius={[0, 6, 6, 0]}
                />
              </BarChart>
            </ChartContainer>
            <dl className="grid grid-cols-3 gap-3 text-center">
              {(["approved", "submitted", "draft"] as const).map((key) => (
                <div key={key} className="rounded-xl border px-2 py-2">
                  <dt className="text-muted-foreground text-[11px]">
                    {t(`marklists.statuses.${key}`)}
                  </dt>
                  <dd className="text-lg font-semibold tabular-nums">
                    {report.marklists[key]}
                    <span className="text-muted-foreground text-xs font-normal">
                      {" "}
                      / {report.marklists.total}
                    </span>
                  </dd>
                </div>
              ))}
            </dl>
          </div>
        )}
      </ChartCard>

      <ChartCard title={t("reports.subjectTitle")} hint={t("reports.subjectHint")}>
        {report.subjects.length === 0 ? (
          <ChartEmpty message={t("reports.noData")} />
        ) : (
          <ChartContainer
            config={subjectConfig}
            className="w-full"
            style={{ height: Math.max(160, report.subjects.length * 34) }}
          >
            <BarChart data={report.subjects} layout="vertical" margin={{ left: 8, right: 28 }}>
              <CartesianGrid horizontal={false} strokeDasharray="3 3" />
              <XAxis type="number" domain={[0, 100]} tickLine={false} axisLine={false} fontSize={11} />
              <YAxis
                type="category"
                dataKey="name"
                width={110}
                tickLine={false}
                axisLine={false}
                fontSize={11}
              />
              <ChartTooltip content={<ChartTooltipContent />} />
              <Bar dataKey="average" fill="var(--chart-2)" radius={[0, 6, 6, 0]}>
                <LabelList position="right" fontSize={11} />
              </Bar>
            </BarChart>
          </ChartContainer>
        )}
      </ChartCard>

      <ChartCard title={t("reports.sectionTitle")} hint={t("reports.sectionHint")}>
        {report.sections.length === 0 ? (
          <ChartEmpty message={t("reports.noData")} />
        ) : (
          <ChartContainer config={sectionConfig} className="h-56 w-full">
            <BarChart data={report.sections} margin={{ top: 18, left: -18 }}>
              <CartesianGrid vertical={false} strokeDasharray="3 3" />
              <XAxis dataKey="name" tickLine={false} axisLine={false} fontSize={11} />
              <YAxis domain={[0, 100]} tickLine={false} axisLine={false} fontSize={11} />
              <ChartTooltip content={<ChartTooltipContent />} />
              <Bar dataKey="average" fill="var(--chart-3)" radius={[6, 6, 0, 0]} />
              <Bar dataKey="pass_rate" fill="var(--chart-4)" radius={[6, 6, 0, 0]} />
            </BarChart>
          </ChartContainer>
        )}
      </ChartCard>
    </div>
  )
}
