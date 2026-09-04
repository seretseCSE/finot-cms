"use client"

import { useMemo } from "react"
import { Bar, BarChart, CartesianGrid, Label, LabelList, Pie, PieChart, XAxis, YAxis } from "recharts"

import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { useTranslation } from "@/lib/i18n"
import type { OrgStats } from "@/lib/types"

/**
 * The profile breakdown charts for a school/branch, one form per data job
 * (dataviz heuristic): boys/girls is a 2-part composition → donut; workforce
 * is a ranking over many job titles → horizontal bars; students per grade is
 * an ordered sequence → columns. Recharts-heavy — always load via dynamic().
 */
export function OrgStatsCharts({ stats }: { stats: OrgStats }) {
  const { t } = useTranslation("schools")
  const { t: te } = useTranslation("employees")

  const workforceRows = useMemo(
    () =>
      stats.employees.by_job_title.map((row) => ({
        name: te(`jobTitles.${row.job_title}`),
        total: row.total,
      })),
    [stats, te],
  )

  const gradeRows = useMemo(
    () =>
      stats.grades.map((grade) => ({
        code: grade.code ?? grade.name,
        name: grade.name,
        students: grade.students,
        sections: grade.sections,
      })),
    [stats],
  )

  return (
    <div className="space-y-3">
      <div className="grid items-stretch gap-3 md:grid-cols-5">
        <ChartCard title={t("stats.genderTitle")} className="md:col-span-2">
          <GenderDonut stats={stats} />
        </ChartCard>
        <ChartCard title={t("stats.staffByRole")} className="md:col-span-3">
          {workforceRows.length === 0 ? (
            <ChartEmpty message={t("stats.noEmployees")} />
          ) : (
            <WorkforceBars rows={workforceRows} label={t("stats.employees")} />
          )}
        </ChartCard>
      </div>

      <ChartCard title={t("stats.studentsByGrade")}>
        {gradeRows.length === 0 ? (
          <ChartEmpty message={t("stats.noGrades")} />
        ) : (
          <GradeColumns rows={gradeRows} />
        )}
      </ChartCard>
    </div>
  )
}

function ChartCard({
  title,
  className,
  children,
}: {
  title: string
  className?: string
  children: React.ReactNode
}) {
  return (
    <section className={`bg-card rounded-2xl border p-4 shadow-xs md:p-5 ${className ?? ""}`}>
      <h2 className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
        {title}
      </h2>
      <div className="mt-3">{children}</div>
    </section>
  )
}

function ChartEmpty({ message }: { message: string }) {
  return (
    <p className="text-muted-foreground flex h-40 items-center justify-center text-sm">
      {message}
    </p>
  )
}

/** 2-slice donut with the attending total in the hole and a counted legend. */
function GenderDonut({ stats }: { stats: OrgStats }) {
  const { t } = useTranslation("schools")

  const { male, female } = stats.students
  const total = male + female

  const config: ChartConfig = {
    male: { label: t("stats.male"), color: "var(--chart-1)" },
    female: { label: t("stats.female"), color: "var(--chart-2)" },
  }
  const data = [
    { key: "male", value: male, fill: "var(--color-male)" },
    { key: "female", value: female, fill: "var(--color-female)" },
  ].filter((slice) => slice.value > 0)

  if (total === 0) return <ChartEmpty message={t("stats.noStudents")} />

  return (
    <div>
      <ChartContainer config={config} className="mx-auto aspect-square h-44 w-full max-w-52">
        <PieChart>
          <ChartTooltip content={<ChartTooltipContent hideLabel nameKey="key" />} />
          <Pie
            data={data}
            dataKey="value"
            nameKey="key"
            innerRadius={52}
            outerRadius={72}
            stroke="var(--card)"
            strokeWidth={2}
          >
            <Label
              content={({ viewBox }) => {
                if (!viewBox || !("cx" in viewBox) || !("cy" in viewBox)) return null
                return (
                  <text x={viewBox.cx} y={viewBox.cy} textAnchor="middle" dominantBaseline="middle">
                    <tspan
                      x={viewBox.cx}
                      y={viewBox.cy}
                      className="fill-foreground font-display text-2xl font-semibold tabular-nums"
                    >
                      {total}
                    </tspan>
                    <tspan
                      x={viewBox.cx}
                      y={(viewBox.cy ?? 0) + 18}
                      className="fill-muted-foreground text-[11px]"
                    >
                      {t("stats.students")}
                    </tspan>
                  </text>
                )
              }}
            />
          </Pie>
        </PieChart>
      </ChartContainer>

      {/* Counted legend — identity never rides on color alone. */}
      <div className="mt-1 flex items-center justify-center gap-5 text-sm">
        {(["male", "female"] as const).map((key) => {
          const value = stats.students[key]
          return (
            <span key={key} className="flex items-center gap-1.5">
              <span
                className="size-2 shrink-0 rounded-full"
                style={{ background: `var(--chart-${key === "male" ? 1 : 2})` }}
              />
              <span className="text-muted-foreground">{t(`stats.${key}`)}</span>
              <span className="font-semibold tabular-nums">{value}</span>
              <span className="text-muted-foreground text-xs tabular-nums">
                {Math.round((value / total) * 100)}%
              </span>
            </span>
          )
        })}
      </div>
    </div>
  )
}

/** Ranked horizontal bars — one row per job title, tallest first. */
function WorkforceBars({ rows, label }: { rows: { name: string; total: number }[]; label: string }) {
  const config: ChartConfig = {
    total: { label, color: "var(--chart-1)" },
  }

  return (
    <ChartContainer
      config={config}
      className="aspect-auto w-full"
      style={{ height: Math.max(160, rows.length * 32) }}
    >
      <BarChart data={rows} layout="vertical" margin={{ left: 8, right: 28 }}>
        <CartesianGrid horizontal={false} strokeDasharray="3 3" />
        <XAxis type="number" tickLine={false} axisLine={false} allowDecimals={false} fontSize={11} />
        <YAxis
          type="category"
          dataKey="name"
          width={110}
          tickLine={false}
          axisLine={false}
          fontSize={11}
        />
        <ChartTooltip content={<ChartTooltipContent />} />
        <Bar dataKey="total" fill="var(--color-total)" radius={[0, 6, 6, 0]} maxBarSize={20}>
          <LabelList dataKey="total" position="right" fontSize={11} className="fill-muted-foreground" />
        </Bar>
      </BarChart>
    </ChartContainer>
  )
}

/** Columns in national grade order; sections ride in the tooltip. */
function GradeColumns({
  rows,
}: {
  rows: { code: string; name: string; students: number; sections: number }[]
}) {
  const { t } = useTranslation("schools")

  const config: ChartConfig = {
    students: { label: t("stats.students"), color: "var(--chart-1)" },
  }

  return (
    <ChartContainer config={config} className="aspect-auto h-56 w-full">
      <BarChart data={rows} margin={{ top: 18, left: -8, right: 4 }}>
        <CartesianGrid vertical={false} strokeDasharray="3 3" />
        <XAxis dataKey="code" tickLine={false} axisLine={false} tickMargin={8} fontSize={11} />
        <YAxis tickLine={false} axisLine={false} allowDecimals={false} width={36} fontSize={11} />
        <ChartTooltip
          content={
            <ChartTooltipContent
              labelFormatter={(_, payload) =>
                (payload?.[0]?.payload as { name?: string })?.name ?? ""
              }
              formatter={(value, name, item) => (
                <div className="flex w-full flex-col gap-1">
                  <span className="flex items-center justify-between gap-3">
                    <span className="text-muted-foreground flex items-center gap-1.5">
                      <span className="size-2 rounded-[2px]" style={{ background: item?.color }} />
                      {config[name as string]?.label ?? name}
                    </span>
                    <span className="font-mono tabular-nums">{Number(value)}</span>
                  </span>
                  <span className="text-muted-foreground flex items-center justify-between gap-3 text-xs">
                    <span>{t("stats.sections")}</span>
                    <span className="font-mono tabular-nums">
                      {(item?.payload as { sections?: number })?.sections ?? 0}
                    </span>
                  </span>
                </div>
              )}
            />
          }
        />
        <Bar dataKey="students" fill="var(--color-students)" maxBarSize={24} radius={[4, 4, 0, 0]}>
          <LabelList
            dataKey="students"
            position="top"
            fontSize={11}
            className="fill-muted-foreground"
          />
        </Bar>
      </BarChart>
    </ChartContainer>
  )
}
