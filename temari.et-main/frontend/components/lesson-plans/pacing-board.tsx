"use client"

import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"

import {
  fmtDay,
  PlanStatusBadge,
  ProgressBar,
} from "@/components/lesson-plans/shared"
import { Badge } from "@/components/ui/badge"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useLocale, useTranslation } from "@/lib/i18n"
import type { LessonPlanPacingRow } from "@/lib/types"

type Row = LessonPlanPacingRow & {
  subject_key: string
  grade_key: string
  teacher_key: string
  behind_key: string
}

/**
 * The accountability dashboard: syllabus progress per approved plan —
 * planned vs covered vs expected-by-today, the decline/justification trail,
 * and who is behind. Rows open the plan workspace.
 */
export function PacingBoard({ branchFilter }: { branchFilter: number | null }) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const { locale } = useLocale()
  const router = useRouter()

  const [rows, setRows] = useState<Row[] | null>(null)
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on scope change
    setRows(null)
    const branchParam =
      branchFilter !== null ? `?branch_id=${branchFilter}` : ""
    apiFetch<{ data: LessonPlanPacingRow[] }>(
      `/lesson-plans/pacing${branchParam}`
    )
      .then(
        (res) =>
          !cancelled &&
          setRows(
            res.data.map((r) => ({
              ...r,
              subject_key: r.subject.name ?? "",
              grade_key: r.grade_level.name ?? "",
              teacher_key: r.teacher_name ?? "",
              behind_key:
                (r.pacing?.lag_periods ?? 0) > 0 ? "behind" : "on_track",
            }))
          )
      )
      .catch(() => !cancelled && setRows([]))
    return () => {
      cancelled = true
    }
  }, [active.schoolId, active.branchId, branchFilter])

  const columns: DataTableColumn<Row>[] = useMemo(
    () => [
      {
        key: "teacher_key",
        label: t("pacingBoard.teacher"),
        primary: true,
        render: (row) => (
          <div className="min-w-0">
            <p className="truncate font-medium">{row.teacher_name ?? "—"}</p>
            <p className="truncate text-xs text-muted-foreground">
              {row.subject.name} · {row.grade_level.name}
            </p>
          </div>
        ),
        exportValue: (row) =>
          `${row.teacher_name ?? ""} — ${row.subject.name ?? ""} ${row.grade_level.name ?? ""}`,
      },
      {
        key: "progress",
        label: t("pacingBoard.progress"),
        render: (row) => {
          const p = row.pacing
          if (!p || p.planned_periods === 0)
            return <span className="text-muted-foreground">—</span>
          return (
            <div className="w-32 space-y-1">
              <ProgressBar
                percent={p.progress_percent}
                behind={p.lag_periods > 0}
              />
              <p className="text-xs text-muted-foreground tabular-nums">
                {p.covered_periods}/{p.planned_periods} · {p.progress_percent}%
              </p>
            </div>
          )
        },
        exportValue: (row) =>
          `${row.pacing?.covered_periods ?? 0}/${row.pacing?.planned_periods ?? 0}`,
      },
      {
        key: "behind_key",
        label: t("pacingBoard.behindBy"),
        render: (row) => {
          const lag = row.pacing?.lag_periods ?? 0
          return lag > 0 ? (
            <Badge
              variant="outline"
              className="border border-warning/30 bg-warning/10 text-warning"
            >
              {t("pacingBoard.periods", { count: String(lag) })}
            </Badge>
          ) : (
            <Badge
              variant="outline"
              className="border border-success/30 bg-success/10 text-success"
            >
              {t("pacingBoard.onTrack")}
            </Badge>
          )
        },
        exportValue: (row) => String(row.pacing?.lag_periods ?? 0),
      },
      {
        key: "weeks",
        label: t("pacingBoard.weeks"),
        mobileHidden: true,
        render: (row) => (
          <p className="text-sm tabular-nums">
            {row.weeks_approved}/{row.weeks_total}
            {(row.weeks_declined > 0 || row.weeks_justified > 0) && (
              <span className="text-xs text-muted-foreground">
                {row.weeks_declined > 0 &&
                  ` · ${row.weeks_declined} ${t("pacingBoard.declined")}`}
                {row.weeks_justified > 0 &&
                  ` · ${row.weeks_justified} ${t("pacingBoard.justified")}`}
              </span>
            )}
          </p>
        ),
        exportValue: (row) => `${row.weeks_approved}/${row.weeks_total}`,
      },
      {
        key: "last_week",
        label: t("pacingBoard.lastWeek"),
        mobileHidden: true,
        render: (row) =>
          row.last_week_status ? (
            <div className="flex items-center gap-2">
              <span className="text-xs text-muted-foreground tabular-nums">
                {fmtDay(row.last_week_starts_on, locale)}
              </span>
              <PlanStatusBadge status={row.last_week_status} />
            </div>
          ) : (
            <span className="text-muted-foreground">—</span>
          ),
        exportValue: (row) => row.last_week_starts_on ?? "",
      },
    ],
    [t, locale]
  )

  const optionsFor = (key: keyof Row) =>
    [
      ...new Set((rows ?? []).map((r) => String(r[key] ?? "")).filter(Boolean)),
    ].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))

  return (
    <DataTable
      columns={columns}
      data={rows ?? []}
      loading={rows === null}
      searchKeys={["teacher_key", "subject_key"]}
      searchPlaceholder={tc("actions.search")}
      filters={[
        ...(optionsFor("grade_key").length > 0
          ? [
              {
                key: "grade_key",
                label: t("register.grade"),
                options: optionsFor("grade_key").map((v) => ({
                  label: v,
                  value: v,
                })),
              },
            ]
          : []),
        {
          key: "behind_key",
          label: t("pacingBoard.behindBy"),
          options: [
            { label: t("pacingBoard.onTrack"), value: "on_track" },
            { label: t("plan.behind"), value: "behind" },
          ],
        },
      ]}
      filterValues={filterValues}
      onFilterChange={(key, value) =>
        setFilterValues((prev) => ({ ...prev, [key]: value }))
      }
      rowClassName={(row) =>
        (row.pacing?.lag_periods ?? 0) > 0
          ? "bg-warning/[0.07] hover:bg-warning/[0.12]"
          : undefined
      }
      onRowClick={(row) => router.push(`/lesson-plans/${row.id}`)}
      emptyMessage={t("pacingBoard.empty")}
      exportFilename="lesson-plan-pacing"
    />
  )
}
