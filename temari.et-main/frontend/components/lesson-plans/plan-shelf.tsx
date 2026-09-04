"use client"

import { CalendarRange, Map } from "lucide-react"
import { useRouter } from "next/navigation"
import { useMemo, useState } from "react"

import {
  PlanStatusBadge,
  ProgressBar,
} from "@/components/lesson-plans/shared"
import { EmptyState } from "@/components/ui/empty-state"
import { Skeleton } from "@/components/ui/skeleton"
import { useTranslation } from "@/lib/i18n"
import type { AnnualLessonPlanRow } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The teacher's own plans as an app-like shelf: grade pills on top, one card
 * per plan below — status, progress and counts at a glance, tap to open.
 * (Supervisors keep the full register table with teacher search + export.)
 */
export function PlanShelf({
  rows,
  loading,
}: {
  rows: AnnualLessonPlanRow[]
  loading: boolean
}) {
  const { t } = useTranslation("lessonPlans")
  const router = useRouter()
  const [grade, setGrade] = useState<string | null>(null)

  const grades = useMemo(
    () =>
      [
        ...new Set(rows.map((r) => r.grade_level.name ?? "").filter(Boolean)),
      ].sort((a, b) => a.localeCompare(b, undefined, { numeric: true })),
    [rows]
  )

  const visible =
    grade === null ? rows : rows.filter((r) => r.grade_level.name === grade)

  if (loading) {
    return (
      <div className="page-gutter grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: 3 }).map((_, i) => (
          <Skeleton key={i} className="h-36 rounded-2xl" />
        ))}
      </div>
    )
  }

  if (rows.length === 0) {
    return (
      <div className="page-gutter">
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState icon={Map} title={t("register.empty")} />
        </div>
      </div>
    )
  }

  return (
    <div className="page-gutter space-y-4">
      {grades.length > 1 && (
        <div className="-mx-4 flex scrollbar-none gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
          <GradePill
            label={t("shelf.allGrades")}
            active={grade === null}
            onClick={() => setGrade(null)}
          />
          {grades.map((g) => (
            <GradePill
              key={g}
              label={g}
              active={grade === g}
              onClick={() => setGrade(grade === g ? null : g)}
            />
          ))}
        </div>
      )}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {visible.map((plan) => (
          <button
            key={plan.id}
            type="button"
            onClick={() => router.push(`/lesson-plans/${plan.id}`)}
            className="pressable min-w-0 rounded-2xl border bg-card p-4 text-left shadow-xs transition-colors hover:bg-accent/50"
          >
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="truncate font-medium">
                  {plan.subject.name}
                  <span className="font-normal text-muted-foreground">
                    {" · "}
                    {plan.grade_level.name}
                  </span>
                </p>
                <p className="text-xs text-muted-foreground">
                  {plan.academic_year.name}
                </p>
              </div>
              <PlanStatusBadge status={plan.status} />
            </div>

            {plan.pacing && plan.pacing.planned_periods > 0 ? (
              <div className="mt-3 space-y-1">
                <ProgressBar
                  percent={plan.pacing.progress_percent}
                  behind={plan.pacing.lag_periods > 0}
                />
                <p
                  className={cn(
                    "text-xs tabular-nums",
                    plan.pacing.lag_periods > 0
                      ? "text-warning"
                      : "text-muted-foreground"
                  )}
                >
                  {plan.pacing.progress_percent}%
                  {plan.pacing.lag_periods > 0 &&
                    ` · ${t("plan.lagPeriods")} ${plan.pacing.lag_periods}`}
                </p>
              </div>
            ) : (
              <div className="mt-3" />
            )}

            <div className="mt-2 flex items-center gap-3 text-xs text-muted-foreground">
              <span className="inline-flex items-center gap-1">
                <Map className="size-3.5" />
                {plan.units_count ?? 0} {t("register.units").toLowerCase()}
              </span>
              <span className="inline-flex items-center gap-1">
                <CalendarRange className="size-3.5" />
                {plan.weekly_plans_count ?? 0}{" "}
                {t("plan.stats.weeksPlanned").toLowerCase()}
              </span>
            </div>
          </button>
        ))}
      </div>
    </div>
  )
}

export function GradePill({
  label,
  active,
  onClick,
}: {
  label: string
  active: boolean
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "touch-target shrink-0 rounded-full border px-4 py-2 text-sm font-medium transition-colors",
        active
          ? "border-primary bg-primary text-primary-foreground"
          : "bg-card hover:bg-accent"
      )}
    >
      {label}
    </button>
  )
}
