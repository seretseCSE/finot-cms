"use client"

import { Briefcase, Pencil } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useTranslation } from "@/lib/i18n"
import type { Employee } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * Employment history — the staff analogue of the student's enrollment
 * timeline: every job the person has held, most recent first, current roles
 * highlighted. Pay lines only render when the viewer holds payroll.view.
 */
export function EmployeeOverviewTab({
  employee,
  canSeePay,
  stats,
  onEdit,
}: {
  employee: Employee
  canSeePay: boolean
  stats: { label: string; value: string }[]
  /** Opens the edit sheet on the Positions tab; omitted when read-only. */
  onEdit?: () => void
}) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")

  const positions = (employee.positions ?? [])
    .slice()
    .sort((a, b) => (b.hired_on ?? "").localeCompare(a.hired_on ?? ""))

  return (
    <div className="space-y-4">
      {/* Quick stats — the at-a-glance vitals. */}
      <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border bg-border/60 sm:grid-cols-4">
        {stats.map((s) => (
          <div key={s.label} className="bg-card px-4 py-3">
            <dt className="text-[11px] text-muted-foreground">{s.label}</dt>
            <dd className="mt-0.5 text-lg font-semibold tabular-nums">{s.value}</dd>
          </div>
        ))}
      </dl>

      <Card>
        <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-base">{t("detail.employmentHistory")}</CardTitle>
          {onEdit ? (
            <Button
              variant="outline"
              size="sm"
              className="h-8 rounded-full"
              onClick={onEdit}
            >
              <Pencil className="size-3.5" />
              {tc("actions.edit")}
            </Button>
          ) : null}
        </CardHeader>
        <CardContent className="text-sm">
          {positions.length === 0 ? (
            <p className="text-muted-foreground">{t("detail.noPositions")}</p>
          ) : (
            <ol className="relative space-y-0 border-l border-border/70 pl-5">
              {positions.map((p) => (
                <li key={p.id} className="relative pb-5 last:pb-1">
                  <span
                    className={cn(
                      "absolute -left-[26px] top-1 size-3 rounded-full border-2 border-card",
                      p.ended_on ? "bg-border" : "bg-primary",
                    )}
                    aria-hidden
                  />
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="min-w-0">
                      <p className="flex items-center gap-1.5 font-medium">
                        <Briefcase className="size-4 shrink-0 text-muted-foreground" />
                        {t(`jobTitles.${p.job_title}`)}
                        {p.is_primary ? (
                          <span className="text-[10px] font-semibold uppercase tracking-wide text-primary">
                            {t("profile.primary")}
                          </span>
                        ) : null}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                        {p.hired_on}
                        {p.ended_on ? ` → ${p.ended_on}` : ""}
                        {p.employment_type_label ? ` · ${p.employment_type_label}` : ""}
                        {canSeePay && p.salary != null
                          ? ` · ${Number(p.salary).toLocaleString()} ETB`
                          : ""}
                      </p>
                    </div>
                    <Badge
                      variant="secondary"
                      className={cn(
                        !p.ended_on && "bg-success/10 text-success",
                      )}
                    >
                      {p.ended_on ? t("positions.ended") : t("detail.current")}
                    </Badge>
                  </div>
                </li>
              ))}
            </ol>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
