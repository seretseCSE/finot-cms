"use client"

import { Award, Percent, Plus, TrendingDown, Users, X } from "lucide-react"
import Link from "next/link"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from "recharts"

import { RankChip } from "@/components/grading/roster-matrix"
import { Button } from "@/components/ui/button"
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from "@/components/ui/chart"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { PersonAvatar } from "@/components/ui/person-avatar"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { MarklistAnalysis, MarklistAnalysisStudent, ScoreRange } from "@/lib/types"
import { cn } from "@/lib/utils"

const OVERALL = "overall"

/** MoE-style fallback when no grading scale pins the cohort's grade. */
const FALLBACK_RANGES: ScoreRange[] = [
  { min: 90, max: 100, label: "90 – 100" },
  { min: 80, max: 89.99, label: "80 – 89" },
  { min: 60, max: 79.99, label: "60 – 79" },
  { min: 50, max: 59.99, label: "50 – 59" },
  { min: 0, max: 49.99, label: "< 50", is_passing: false },
]

interface RangeBin extends ScoreRange {
  male: number
  female: number
  total: number
  pct: number
}

/**
 * Marklist analysis: per-subject (or overall) scores for the chosen cohort,
 * binned into editable performance ranges with a gender split, plus top
 * performers, low scorers and the full ranked student table. Binning is pure
 * client-side — tweaking a range never refetches (3G-friendly).
 */
export function MarklistAnalysisPanel({
  termId,
  gradeId,
  sectionId,
  subjects,
}: {
  termId: string
  gradeId: string
  sectionId: string
  subjects: { subject_id: number; code: string | null; name: string }[]
}) {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")

  const [subjectId, setSubjectId] = useState<string>(OVERALL)
  const [data, setData] = useState<MarklistAnalysis | null>(null)
  const [ranges, setRanges] = useState<ScoreRange[]>(FALLBACK_RANGES)
  const [rangesTouched, setRangesTouched] = useState(false)

  useEffect(() => {
    if (!termId) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new query
    setData(null)
    const params = new URLSearchParams()
    if (sectionId !== "all") params.set("section_id", sectionId)
    else if (gradeId && gradeId !== "all") params.set("grade_level_id", gradeId)
    if (subjectId !== OVERALL) params.set("subject_id", subjectId)
    const qs = params.size > 0 ? `?${params}` : ""
    apiFetch<{ data: MarklistAnalysis }>(`/terms/${termId}/marklist-analysis${qs}`)
      .then((res) => {
        if (cancelled) return
        setData(res.data)
        // Seed the editor from the cohort's grading scale until hand-edited.
        if (!rangesTouched) {
          setRanges(
            res.data.default_ranges && res.data.default_ranges.length > 0
              ? res.data.default_ranges
              : FALLBACK_RANGES,
          )
        }
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setData({
          term: { id: 0, name: "", status: "planned" },
          subject: null,
          students: [],
          summary: { count: 0, male: 0, female: 0, average: null, min: null, max: null },
          default_ranges: null,
        })
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc stable; rangesTouched read-only here
  }, [termId, gradeId, sectionId, subjectId])

  // ── Client-side binning: instant re-bin on any range edit ────────────────
  const bins: RangeBin[] = useMemo(() => {
    const students = data?.students ?? []
    return ranges.map((range) => {
      const inRange = students.filter((s) => s.score >= range.min && s.score <= range.max)
      const male = inRange.filter((s) => s.gender === "male").length
      const female = inRange.filter((s) => s.gender === "female").length
      return {
        ...range,
        male,
        female,
        total: inRange.length,
        pct: students.length > 0 ? Math.round((inRange.length / students.length) * 1000) / 10 : 0,
      }
    })
  }, [data, ranges])

  // DataTable keys rows by `id`.
  const tableRows = useMemo(
    () => (data?.students ?? []).map((s) => ({ ...s, id: s.student_id })),
    [data],
  )

  const top = useMemo(() => (data?.students ?? []).slice(0, 10), [data])
  const low = useMemo(() => {
    const students = data?.students ?? []
    // Lowest first, and never duplicate the top list on tiny cohorts.
    return students.slice(Math.max(10, students.length - 10)).reverse()
  }, [data])

  const chartConfig: ChartConfig = useMemo(
    () => ({
      male: { label: t("reports.analysis.male"), color: "var(--chart-1)" },
      female: { label: t("reports.analysis.female"), color: "var(--chart-2)" },
    }),
    [t],
  )

  function editRange(index: number, patch: Partial<ScoreRange>) {
    setRangesTouched(true)
    setRanges((prev) =>
      prev.map((range, i) => {
        if (i !== index) return range
        const next = { ...range, ...patch }
        return { ...next, label: `${next.min} – ${next.max}` }
      }),
    )
  }

  function addRange() {
    setRangesTouched(true)
    setRanges((prev) => [...prev, { min: 0, max: 100, label: "0 – 100" }])
  }

  function removeRange(index: number) {
    setRangesTouched(true)
    setRanges((prev) => prev.filter((_, i) => i !== index))
  }

  function resetRanges() {
    setRangesTouched(false)
    setRanges(
      data?.default_ranges && data.default_ranges.length > 0
        ? data.default_ranges
        : FALLBACK_RANGES,
    )
  }

  const columns: DataTableColumn<MarklistAnalysisStudent & { id: number }>[] = useMemo(
    () => [
      {
        key: "rank",
        label: t("rosters.rank"),
        sortable: true,
        render: (row) => <RankChip rank={row.rank} />,
        exportValue: (row) => String(row.rank ?? ""),
      },
      {
        key: "full_name",
        label: t("rosters.student"),
        primary: true,
        render: (row) => (
          <p className="min-w-0 truncate font-medium">
            {row.full_name ?? "—"}
            {row.section_name ? (
              <span className="text-muted-foreground font-normal"> · {row.section_name}</span>
            ) : null}
          </p>
        ),
        exportValue: (row) =>
          `${row.full_name ?? ""}${row.section_name ? ` — ${row.section_name}` : ""}`,
      },
      {
        key: "gender",
        label: t("reports.analysis.gender"),
        mobileHidden: true,
        render: (row) =>
          row.gender ? (
            <span className="text-muted-foreground text-xs capitalize">
              {t(`reports.analysis.${row.gender}` as "reports.analysis.male")}
            </span>
          ) : (
            "—"
          ),
        exportValue: (row) => row.gender ?? "",
      },
      {
        key: "score",
        label: t("reports.analysis.score"),
        sortable: true,
        render: (row) => (
          <span
            className={cn(
              "font-semibold tabular-nums",
              row.is_passing === false && "text-destructive",
            )}
          >
            {row.score}
            {row.letter ? (
              <span className="text-muted-foreground ml-1 text-[10px] font-normal">
                {row.letter}
              </span>
            ) : null}
          </span>
        ),
        exportValue: (row) => String(row.score),
      },
    ],
    [t],
  )

  return (
    <div className="space-y-4">
      {/* Subject picker — the analysis pivots on one subject or the average. */}
      <div className="flex flex-wrap items-center gap-2">
        <Select value={subjectId} onValueChange={setSubjectId}>
          <SelectTrigger className="h-9 w-full md:w-64" aria-label={t("reports.analysis.subject")}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={OVERALL}>{t("reports.analysis.overall")}</SelectItem>
            {subjects.map((subject) => (
              <SelectItem key={subject.subject_id} value={String(subject.subject_id)}>
                {subject.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Cohort summary. */}
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard
          label={t("reports.analysis.students")}
          value={data === null ? null : data.summary.count}
          icon={Users}
          hint={
            data !== null
              ? `${t("reports.analysis.male")} ${data.summary.male} · ${t("reports.analysis.female")} ${data.summary.female}`
              : undefined
          }
        />
        <StatCard
          label={t("reportCards.average")}
          value={data === null ? null : (data.summary.average ?? "—")}
          icon={Percent}
        />
        <StatCard
          label={t("reports.analysis.highest")}
          value={data === null ? null : (data.summary.max ?? "—")}
          icon={Award}
        />
        <StatCard
          label={t("reports.analysis.lowest")}
          value={data === null ? null : (data.summary.min ?? "—")}
          icon={TrendingDown}
        />
      </div>

      {/* Range editor + distribution: the editor doubles as the legend. */}
      <div className="grid gap-4 lg:grid-cols-2">
        <section className="bg-card rounded-2xl border p-4 shadow-xs">
          <div className="flex items-start justify-between gap-2">
            <div>
              <h2 className="font-display text-base font-semibold">
                {t("reports.analysis.ranges")}
              </h2>
              <p className="text-muted-foreground mt-0.5 text-xs">
                {t("reports.analysis.rangesHint")}
              </p>
            </div>
            {rangesTouched && (
              <Button variant="ghost" size="sm" onClick={resetRanges}>
                {t("reports.analysis.resetRanges")}
              </Button>
            )}
          </div>
          <div className="mt-3 space-y-2">
            {bins.map((bin, i) => (
              <div key={i} className="flex items-center gap-2">
                <Input
                  type="number"
                  inputMode="decimal"
                  min={0}
                  max={100}
                  value={bin.min}
                  aria-label={`${t("reports.analysis.range")} ${i + 1} min`}
                  className="no-spinner h-9 w-16 px-1.5 text-center tabular-nums"
                  onChange={(e) => editRange(i, { min: Number(e.target.value) })}
                />
                <span className="text-muted-foreground text-xs">–</span>
                <Input
                  type="number"
                  inputMode="decimal"
                  min={0}
                  max={100}
                  value={bin.max}
                  aria-label={`${t("reports.analysis.range")} ${i + 1} max`}
                  className="no-spinner h-9 w-16 px-1.5 text-center tabular-nums"
                  onChange={(e) => editRange(i, { max: Number(e.target.value) })}
                />
                <div className="min-w-0 flex-1 text-right">
                  <span className="text-sm font-semibold tabular-nums">{bin.total}</span>
                  <span className="text-muted-foreground ml-1.5 text-xs tabular-nums">
                    {bin.pct}% · {t("reports.analysis.maleShort")} {bin.male} ·{" "}
                    {t("reports.analysis.femaleShort")} {bin.female}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={() => removeRange(i)}
                  aria-label={tc("actions.delete")}
                  className="text-muted-foreground hover:text-destructive flex size-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                >
                  <X className="size-3.5" />
                </button>
              </div>
            ))}
          </div>
          <Button variant="outline" size="sm" className="mt-3" onClick={addRange}>
            <Plus className="size-4" />
            {t("reports.analysis.addRange")}
          </Button>
        </section>

        <section className="bg-card rounded-2xl border p-4 shadow-xs">
          <h2 className="font-display text-base font-semibold">
            {t("reports.analysis.distribution")}
          </h2>
          <p className="text-muted-foreground mt-0.5 text-xs">
            {t("reports.analysis.distributionHint")}
          </p>
          <div className="mt-4">
            {data === null ? (
              <Skeleton className="h-56 rounded-xl" />
            ) : data.students.length === 0 ? (
              <p className="text-muted-foreground flex h-56 items-center justify-center text-sm">
                {t("reports.noData")}
              </p>
            ) : (
              <ChartContainer config={chartConfig} className="h-56 w-full">
                <BarChart data={bins} margin={{ left: -20 }}>
                  <CartesianGrid vertical={false} />
                  <XAxis dataKey="label" tickLine={false} axisLine={false} fontSize={11} />
                  <YAxis tickLine={false} axisLine={false} allowDecimals={false} />
                  <ChartTooltip content={<ChartTooltipContent />} />
                  <ChartLegend content={<ChartLegendContent />} />
                  <Bar dataKey="male" stackId="g" fill="var(--color-male)" radius={[0, 0, 0, 0]} />
                  <Bar
                    dataKey="female"
                    stackId="g"
                    fill="var(--color-female)"
                    radius={[6, 6, 0, 0]}
                  />
                </BarChart>
              </ChartContainer>
            )}
          </div>
        </section>
      </div>

      {/* Top performers / low scorers. */}
      <div className="grid gap-4 lg:grid-cols-2">
        <PerformerList
          title={t("reports.analysis.topPerformers")}
          students={data === null ? null : top}
          tone="top"
        />
        <PerformerList
          title={t("reports.analysis.lowScorers")}
          students={data === null ? null : low}
          tone="low"
        />
      </div>

      {/* The full ranked list — searchable, filterable, exportable. */}
      <DataTable
        columns={columns}
        data={tableRows}
        loading={data === null}
        searchKeys={["full_name", "public_id"]}
        searchPlaceholder={tc("actions.search")}
        filters={[
          {
            key: "gender",
            label: t("reports.analysis.gender"),
            options: [
              { label: t("reports.analysis.male"), value: "male" },
              { label: t("reports.analysis.female"), value: "female" },
            ],
          },
        ]}
        emptyMessage={t("reports.noData")}
        exportFilename="marklist-analysis"
      />
    </div>
  )
}

/** Compact ranked list shared by the top-performers and low-scorers cards. */
function PerformerList({
  title,
  students,
  tone,
}: {
  title: string
  students: MarklistAnalysisStudent[] | null
  tone: "top" | "low"
}) {
  const { t } = useTranslation("grading")

  return (
    <section className="bg-card rounded-2xl border p-4 shadow-xs">
      <h2 className="font-display text-base font-semibold">{title}</h2>
      {students === null ? (
        <Skeleton className="mt-4 h-40 rounded-xl" />
      ) : students.length === 0 ? (
        <p className="text-muted-foreground flex h-32 items-center justify-center text-sm">
          {t("reports.noData")}
        </p>
      ) : (
        <ol className="mt-3 divide-y">
          {students.map((student) => (
            <li key={student.student_id}>
              <Link
                href={`/students/${student.student_id}`}
                target="_blank"
                className="hover:bg-accent/40 -mx-2 flex items-center gap-3 rounded-xl px-2 py-2.5 transition-colors"
              >
                <RankChip rank={student.rank} />
                <PersonAvatar
                  name={student.full_name ?? "?"}
                  photoUrl={student.photo_url}
                  className="size-8"
                />
                <p className="min-w-0 flex-1 truncate text-sm font-medium">
                  {student.full_name}
                  {student.section_name ? (
                    <span className="text-muted-foreground font-normal">
                      {" · "}
                      {student.section_name}
                    </span>
                  ) : null}
                </p>
                <span
                  className={cn(
                    "text-sm font-semibold tabular-nums",
                    tone === "low" && student.is_passing === false && "text-destructive",
                  )}
                >
                  {student.score}
                </span>
              </Link>
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}
