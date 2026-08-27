"use client"

import { Badge } from "@/components/ui/badge"
import { useTranslation } from "@/lib/i18n"
import type { LessonCoverage, LessonPlanStatus } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDate as sharedFmtDate, fmtTime as sharedFmtTime, fmtWeekday as sharedFmtWeekday } from "@/lib/dates"
import type { Locale } from "@/lib/types"

/** Status tints — same tokens as the marklist register. */
export const PLAN_STATUS_BADGE: Record<LessonPlanStatus, string> = {
  draft: "bg-muted text-muted-foreground",
  submitted: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
  declined: "border-destructive/30 bg-destructive/10 text-destructive",
}

export const PLAN_STATUS_ROW: Partial<Record<LessonPlanStatus, string>> = {
  submitted: "bg-warning/[0.07] hover:bg-warning/[0.12]",
  declined: "bg-destructive/[0.06] hover:bg-destructive/[0.1]",
}

export const COVERAGE_BADGE: Record<LessonCoverage, string> = {
  pending: "bg-muted text-muted-foreground",
  covered: "border-success/30 bg-success/10 text-success",
  partial: "border-warning/30 bg-warning/10 text-warning",
  missed: "border-destructive/30 bg-destructive/10 text-destructive",
}

export function PlanStatusBadge({ status }: { status: LessonPlanStatus }) {
  const { t } = useTranslation("lessonPlans")
  return (
    <Badge
      variant="outline"
      className={cn("border", PLAN_STATUS_BADGE[status])}
    >
      {t(`statuses.${status}`)}
    </Badge>
  )
}

export function CoverageBadge({ coverage }: { coverage: LessonCoverage }) {
  const { t } = useTranslation("lessonPlans")
  return (
    <Badge variant="outline" className={cn("border", COVERAGE_BADGE[coverage])}>
      {t(`coverage.${coverage}`)}
    </Badge>
  )
}

/** Short date on the workspace calendar, e.g. "Hamle 15". */
export function fmtDay(
  date: string | null | undefined,
  locale: string
): string {
  if (!date) return "—"
  return sharedFmtDate(date, { noYear: true, locale: locale as Locale })
}

/** Full date on the workspace calendar, e.g. "Hamle 15, 2018". */
export function fmtDate(
  date: string | null | undefined,
  locale: string
): string {
  if (!date) return "—"
  return sharedFmtDate(date, { locale: locale as Locale })
}

/** The Monday of the week containing `date` (local), as YYYY-MM-DD. */
export function mondayOf(date: Date): string {
  const d = new Date(date)
  const day = d.getDay()
  d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day))
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, "0")
  const dd = String(d.getDate()).padStart(2, "0")
  return `${y}-${m}-${dd}`
}

export function addWeeks(weekStart: string, weeks: number): string {
  const d = new Date(`${weekStart}T00:00:00`)
  d.setDate(d.getDate() + weeks * 7)
  return mondayOf(d)
}

/** Shift a YYYY-MM-DD date by whole days. */
export function addDays(date: string, days: number): string {
  const d = new Date(`${date}T00:00:00`)
  d.setDate(d.getDate() + days)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, "0")
  const dd = String(d.getDate()).padStart(2, "0")
  return `${y}-${m}-${dd}`
}

/** Weekday name, e.g. "Monday" / "ሰኞ". */
export function fmtWeekday(date: string, locale: string): string {
  return sharedFmtWeekday(date, false, locale as Locale)
}

/** Period-schedule time (H:i:s) on the school's clock — never raw 24h. */
export function fmtTime(time: string | null | undefined): string {
  if (!time) return ""
  return sharedFmtTime(time)
}

/** Syllabus progress bar — planned vs covered, with a "behind" tint. */
export function ProgressBar({
  percent,
  behind = false,
  className,
}: {
  percent: number
  behind?: boolean
  className?: string
}) {
  return (
    <div
      className={cn(
        "h-1.5 w-full overflow-hidden rounded-full bg-muted",
        className
      )}
    >
      <div
        className={cn(
          "h-full rounded-full transition-[width] duration-300",
          behind ? "bg-warning" : "bg-primary"
        )}
        style={{ width: `${Math.min(100, Math.max(0, percent))}%` }}
      />
    </div>
  )
}
