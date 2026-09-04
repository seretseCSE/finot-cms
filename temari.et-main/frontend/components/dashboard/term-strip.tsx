"use client"

import { CalendarDays } from "lucide-react"
import Link from "next/link"

import { useTranslation } from "@/lib/i18n"
import type { DashboardData } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The "where are we in the year" strip: today's Ethiopian date and — in a
 * branch workspace — the current term with a progress bar, week number and
 * days-left. Quiet by design; it orients, the KPIs below carry the weight.
 */
export function TermStrip({ context }: { context: DashboardData["context"] }) {
  const { t } = useTranslation("common")
  const { t: tf } = useTranslation("fees")
  const term = context.term

  const ecDate = `${tf(`months.${context.ethiopian.month}`)} ${context.ethiopian.day}, ${context.ethiopian.year}`

  return (
    <div className="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-2xl border bg-card px-3.5 py-3 shadow-xs sm:px-4">
      <span className="flex min-w-0 items-center gap-2 text-sm">
        <CalendarDays
          className="size-4 shrink-0 text-primary"
          strokeWidth={1.75}
        />
        <span className="truncate font-medium">{ecDate}</span>
        <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
          {context.today}
        </span>
      </span>

      {term && (
        <Link
          href="/semesters"
          className="group flex min-w-0 flex-1 items-center gap-3 max-sm:basis-full sm:justify-end"
        >
          <span className="min-w-0 truncate text-sm">
            <span className="font-medium transition-colors group-hover:text-primary">
              {term.name}
            </span>
            {term.year_name && (
              <span className="ml-1.5 text-xs text-muted-foreground">
                {term.year_name}
              </span>
            )}
          </span>
          {term.week !== null && (
            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
              {t("dashboard.termWeek", { week: term.week })}
            </span>
          )}
          {term.progress !== null && (
            <span className="flex shrink-0 items-center gap-2">
              <span className="h-1.5 w-14 overflow-hidden rounded-full bg-muted sm:w-20">
                <span
                  className={cn("block h-full rounded-full bg-primary")}
                  style={{ width: `${term.progress}%` }}
                />
              </span>
              {term.days_left !== null && (
                <span className="text-xs text-muted-foreground tabular-nums">
                  {t("dashboard.termDaysLeft", { days: term.days_left })}
                </span>
              )}
            </span>
          )}
        </Link>
      )}
    </div>
  )
}
