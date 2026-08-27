"use client"

import { ChevronRight, School } from "lucide-react"
import Link from "next/link"

import { useTranslation } from "@/lib/i18n"
import type { DashboardData } from "@/lib/types"

/**
 * Temari.et staff extras in the global workspace: the latest schools to come
 * onboard (the platform's own growth pulse). Counts live in the KPI row.
 */
export function PlatformOverview({
  platform,
}: {
  platform: NonNullable<DashboardData["platform"]>
}) {
  const { t } = useTranslation("common")

  if (platform.recent_schools.length === 0) return null

  return (
    <section>
      <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t("dashboard.recentSchools")}
      </h2>
      <div className="divide-y rounded-2xl border bg-card shadow-xs">
        {platform.recent_schools.map((school) => (
          <Link
            key={school.id}
            href={`/schools/${school.id}`}
            className="group flex items-center gap-3.5 px-4 py-3 transition-colors first:rounded-t-2xl last:rounded-b-2xl hover:bg-accent/40"
          >
            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <School className="size-4" strokeWidth={1.75} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-medium">
                {school.name}
              </span>
              <span className="text-xs text-muted-foreground">
                {t("dashboard.branchCount", { count: school.branches })}
                {school.created_at ? ` · ${school.created_at}` : ""}
              </span>
            </span>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground/60 transition-colors group-hover:text-primary" />
          </Link>
        ))}
      </div>
    </section>
  )
}
