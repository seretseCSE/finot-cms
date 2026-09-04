"use client"

import { CalendarRange, ClipboardCheck, Map } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"

import { fmtDate } from "@/components/lesson-plans/shared"
import { Badge } from "@/components/ui/badge"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { EmptyState } from "@/components/ui/empty-state"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useLocale, useTranslation } from "@/lib/i18n"

interface AnnualRow {
  id: number
  subject: { name: string | null }
  grade_level: { name: string | null }
  teacher_name: string | null
  submitted_at: string | null
  units_count: number
}

interface WeeklyRow {
  id: number
  annual_lesson_plan_id: number
  week_starts_on: string
  submitted_at: string | null
  lag_justified: boolean
  lag_justification: string | null
  lessons_count: number
  subject: { name: string | null }
  grade_level: { name: string | null }
  teacher_name: string | null
}

interface InboxData {
  annual: AnnualRow[]
  weekly: WeeklyRow[]
}

/** Flat keys DataTable's client-mode search/filters read. */
type FlatAnnual = AnnualRow & {
  subject_key: string
  grade_key: string
  teacher_key: string
  submitted_key: string
}
type FlatWeekly = WeeklyRow & {
  subject_key: string
  grade_key: string
  teacher_key: string
}

/**
 * The reviewer's inbox as two proper registers: every submitted annual and
 * weekly plan in scope, searchable and filterable by grade/teacher. Rows
 * deep-link into the plan workspace where the decision buttons live — the
 * inbox is for triage, not blind approval.
 */
export function ReviewInbox() {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const { locale } = useLocale()
  const router = useRouter()

  const [data, setData] = useState<InboxData | null>(null)
  const [annualFilters, setAnnualFilters] = useState<Record<string, string>>({})
  const [weeklyFilters, setWeeklyFilters] = useState<Record<string, string>>({})

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on scope change
    setData(null)
    apiFetch<{ data: InboxData }>("/lesson-plans/review")
      .then((res) => !cancelled && setData(res.data))
      .catch(() => !cancelled && setData({ annual: [], weekly: [] }))
    return () => {
      cancelled = true
    }
  }, [active.schoolId, active.branchId])

  const annual: FlatAnnual[] = useMemo(
    () =>
      (data?.annual ?? []).map((r) => ({
        ...r,
        subject_key: r.subject.name ?? "",
        grade_key: r.grade_level.name ?? "",
        teacher_key: r.teacher_name ?? "",
        submitted_key: r.submitted_at?.slice(0, 10) ?? "",
      })),
    [data]
  )

  const weekly: FlatWeekly[] = useMemo(
    () =>
      (data?.weekly ?? []).map((r) => ({
        ...r,
        subject_key: r.subject.name ?? "",
        grade_key: r.grade_level.name ?? "",
        teacher_key: r.teacher_name ?? "",
      })),
    [data]
  )

  const optionsFor = <T,>(rows: T[], key: keyof T) =>
    [...new Set(rows.map((r) => String(r[key] ?? "")).filter(Boolean))]
      .sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
      .map((v) => ({ label: v, value: v }))

  const annualColumns: DataTableColumn<FlatAnnual>[] = useMemo(
    () => [
      {
        key: "subject_key",
        label: t("register.subject"),
        primary: true,
        render: (row) => (
          <p className="truncate font-medium">
            {row.subject.name}
            <span className="font-normal text-muted-foreground">
              {" · "}
              {row.grade_level.name}
            </span>
          </p>
        ),
        exportValue: (row) =>
          `${row.subject.name ?? ""} — ${row.grade_level.name ?? ""}`,
      },
      {
        key: "teacher_key",
        label: t("register.teacher"),
        render: (row) => row.teacher_name ?? "—",
        exportValue: (row) => row.teacher_name ?? "",
      },
      {
        key: "submitted_key",
        label: t("review.submittedOn"),
        mobileHidden: true,
        render: (row) => (
          <span className="tabular-nums">
            {fmtDate(row.submitted_at?.slice(0, 10) ?? null, locale)}
          </span>
        ),
        exportValue: (row) => row.submitted_key,
      },
      {
        key: "units_count",
        label: t("register.units"),
        mobileHidden: true,
        render: (row) => <span className="tabular-nums">{row.units_count}</span>,
        exportValue: (row) => String(row.units_count),
      },
    ],
    [t, locale]
  )

  const weeklyColumns: DataTableColumn<FlatWeekly>[] = useMemo(
    () => [
      {
        key: "subject_key",
        label: t("register.subject"),
        primary: true,
        render: (row) => (
          <p className="truncate font-medium">
            {row.subject.name}
            <span className="font-normal text-muted-foreground">
              {" · "}
              {row.grade_level.name}
            </span>
          </p>
        ),
        exportValue: (row) =>
          `${row.subject.name ?? ""} — ${row.grade_level.name ?? ""}`,
      },
      {
        key: "week_starts_on",
        label: t("review.week"),
        render: (row) => (
          <span className="tabular-nums">
            {fmtDate(row.week_starts_on, locale)}
          </span>
        ),
        exportValue: (row) => row.week_starts_on,
      },
      {
        key: "teacher_key",
        label: t("register.teacher"),
        mobileHidden: true,
        render: (row) => row.teacher_name ?? "—",
        exportValue: (row) => row.teacher_name ?? "",
      },
      {
        key: "lessons_count",
        label: t("review.lessons"),
        mobileHidden: true,
        render: (row) => (
          <span className="tabular-nums">{row.lessons_count}</span>
        ),
        exportValue: (row) => String(row.lessons_count),
      },
      {
        key: "lag_justified",
        label: t("review.justified"),
        sortable: false,
        render: (row) =>
          row.lag_justified ? (
            <Badge
              variant="outline"
              className="border border-warning/30 bg-warning/10 text-warning"
              title={row.lag_justification ?? undefined}
            >
              {t("review.justified")}
            </Badge>
          ) : (
            <span className="text-muted-foreground">—</span>
          ),
        exportValue: (row) => (row.lag_justified ? "yes" : ""),
      },
    ],
    [t, locale]
  )

  if (data === null) {
    return (
      <div className="page-gutter space-y-3">
        <Skeleton className="h-24 w-full rounded-2xl" />
        <Skeleton className="h-24 w-full rounded-2xl" />
      </div>
    )
  }

  if (data.annual.length === 0 && data.weekly.length === 0) {
    return (
      <div className="page-gutter">
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState
            icon={ClipboardCheck}
            title={t("review.empty")}
            description={t("review.emptyHint")}
          />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {annual.length > 0 && (
        <section className="space-y-2">
          <h2 className="page-gutter flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            <Map className="size-3.5" />
            {t("review.annual")}
          </h2>
          <DataTable
            columns={annualColumns}
            data={annual}
            searchKeys={["subject_key", "teacher_key", "grade_key"]}
            searchPlaceholder={tc("actions.search")}
            filters={[
              {
                key: "grade_key",
                label: t("register.grade"),
                options: optionsFor(annual, "grade_key"),
              },
              {
                key: "teacher_key",
                label: t("register.teacher"),
                options: optionsFor(annual, "teacher_key"),
              },
            ]}
            filterValues={annualFilters}
            onFilterChange={(key, value) =>
              setAnnualFilters((prev) => ({ ...prev, [key]: value }))
            }
            onRowClick={(row) => router.push(`/lesson-plans/${row.id}`)}
            emptyMessage={t("review.empty")}
            exportFilename="lesson-plan-review-annual"
          />
        </section>
      )}

      {weekly.length > 0 && (
        <section className="space-y-2">
          <h2 className="page-gutter flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            <CalendarRange className="size-3.5" />
            {t("review.weekly")}
          </h2>
          <DataTable
            columns={weeklyColumns}
            data={weekly}
            searchKeys={["subject_key", "teacher_key", "grade_key"]}
            searchPlaceholder={tc("actions.search")}
            filters={[
              {
                key: "grade_key",
                label: t("register.grade"),
                options: optionsFor(weekly, "grade_key"),
              },
              {
                key: "teacher_key",
                label: t("register.teacher"),
                options: optionsFor(weekly, "teacher_key"),
              },
            ]}
            filterValues={weeklyFilters}
            onFilterChange={(key, value) =>
              setWeeklyFilters((prev) => ({ ...prev, [key]: value }))
            }
            onRowClick={(row) =>
              router.push(
                `/lesson-plans/${row.annual_lesson_plan_id}?week=${row.week_starts_on}`
              )
            }
            emptyMessage={t("review.empty")}
            exportFilename="lesson-plan-review-weekly"
          />
        </section>
      )}
    </div>
  )
}
