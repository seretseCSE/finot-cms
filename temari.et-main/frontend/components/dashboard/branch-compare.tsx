"use client"

import { ChevronRight } from "lucide-react"
import Link from "next/link"

import { RATE_BAR, rateBand } from "@/components/attendance/report-charts"
import { useTranslation } from "@/lib/i18n"
import type { DashboardBranchRow } from "@/lib/types"
import { cn, formatETB } from "@/lib/utils"

/**
 * The school-wide workspace's branch pulse: one row per branch with today's
 * attendance rate (banded bar), enrollment and this month's collections —
 * the outlier jumps out before the principal has finished their coffee.
 */
export function BranchCompare({
  branches,
}: {
  branches: DashboardBranchRow[]
}) {
  const { t } = useTranslation("common")

  if (branches.length < 2) return null

  return (
    <section>
      <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t("dashboard.branchPulse")}
      </h2>
      <div className="divide-y rounded-2xl border bg-card shadow-xs">
        {branches.map((branch) => {
          const band = rateBand(branch.attendance_rate)
          return (
            <Link
              key={branch.id}
              href={`/branches/${branch.id}`}
              className="group flex items-center gap-3 px-4 py-3 transition-colors first:rounded-t-2xl last:rounded-b-2xl hover:bg-accent/40"
            >
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium">
                  {branch.name}
                </span>
                <span className="text-xs text-muted-foreground tabular-nums">
                  {t("dashboard.branchStudents", {
                    count: branch.students.toLocaleString(),
                  })}
                </span>
              </span>

              {/* Attendance today — banded bar + number. */}
              <span className="hidden w-36 shrink-0 items-center gap-2 sm:flex">
                <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                  <span
                    className={cn("block h-full rounded-full", RATE_BAR[band])}
                    style={{ width: `${branch.attendance_rate ?? 0}%` }}
                  />
                </span>
                <span
                  className={cn(
                    "w-11 text-right text-xs font-medium tabular-nums",
                    band === "low" && "text-destructive",
                    band === "mid" && "text-warning"
                  )}
                >
                  {branch.attendance_rate === null
                    ? "—"
                    : `${branch.attendance_rate}%`}
                </span>
              </span>

              <span className="w-28 shrink-0 text-right text-xs font-medium tabular-nums sm:w-32 sm:text-sm">
                {formatETB(branch.collected_month)}
              </span>
              <ChevronRight className="size-4 shrink-0 text-muted-foreground/60 transition-colors group-hover:text-primary" />
            </Link>
          )
        })}
      </div>
    </section>
  )
}
