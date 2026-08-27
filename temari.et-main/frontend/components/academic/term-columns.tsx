"use client"

import { useMemo } from "react"

import { Badge } from "@/components/ui/badge"
import { TermStatusSelect } from "@/components/academic/term-status-select"
import type { DataTableColumn, DataTableFilter } from "@/components/ui/data-table"
import { useTranslation } from "@/lib/i18n"
import { PROGRAM_TYPES } from "@/lib/data/programs"
import type { Term } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtTime } from "@/lib/dates"

const DAY_MS = 24 * 60 * 60 * 1000

/** Whole weeks between the term's calendar dates (inclusive-ish). */
function durationWeeks(term: Term): number | null {
  if (!term.starts_on || !term.ends_on) return null
  const start = new Date(`${term.starts_on}T00:00:00`).getTime()
  const end = new Date(`${term.ends_on}T00:00:00`).getTime()
  if (Number.isNaN(start) || Number.isNaN(end) || end < start) return null
  return Math.max(1, Math.round((end - start) / (7 * DAY_MS)))
}

/** Days until the term ends — only meaningful while it's in progress. */
function daysLeft(term: Term): number | null {
  if (!term.in_progress || !term.ends_on) return null
  const end = new Date(`${term.ends_on}T00:00:00`).getTime()
  return Math.max(0, Math.ceil((end - Date.now()) / DAY_MS))
}

/**
 * Shared semester table definition — one source of truth for the standalone
 * Semesters page and the table inside an academic year's detail page.
 */
export function useTermColumns({
  showBranch = false,
  showYear = true,
  allTerms = [],
  canUpdateStatus = false,
  onStatusChanged,
}: {
  showBranch?: boolean
  showYear?: boolean
  /** All rows of the table — names what activation will auto-close. */
  allTerms?: Term[]
  canUpdateStatus?: boolean
  onStatusChanged?: (term: Term, closedNames: string[]) => void
} = {}): DataTableColumn<Term>[] {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")

  return useMemo(
    () => [
      ...(showBranch
        ? [
            {
              key: "branch_name",
              label: tc("columns.branch"),
              render: (row: Term) => (
                <span className="text-xs text-muted-foreground">
                  {row.school_name} · {row.branch_name}
                </span>
              ),
            } as DataTableColumn<Term>,
          ]
        : []),
      {
        key: "name",
        label: t("terms.name"),
        sortable: true,
        primary: true,
        render: (row) => (
          <div className="leading-tight">
            <div className="flex flex-wrap items-center gap-1.5">
              <span
                className={cn(
                  "size-1.5 shrink-0 rounded-full",
                  row.in_progress ? "bg-success" : "bg-border",
                )}
                title={row.in_progress ? t("terms.inProgress") : undefined}
              />
              <span className="font-medium">{row.name}</span>
              {row.is_quarter && (
                <Badge variant="outline" className="px-1.5 py-0 text-[11px]">
                  {t("terms.quarter")}
                </Badge>
              )}
              {row.is_current && (
                <Badge className="px-1.5 py-0 text-[11px]">{t("terms.current")}</Badge>
              )}
            </div>
            <span className="text-xs text-muted-foreground">
              {t("terms.sequenceLabel", { sequence: row.sequence })}
              {row.in_progress && ` · ${t("terms.inProgress")}`}
            </span>
          </div>
        ),
        exportValue: (row) => row.name,
      },
      ...(showYear
        ? [
            {
              key: "academic_year",
              label: t("terms.academicYear"),
              render: (row: Term) => row.academic_year_name ?? "—",
              exportValue: (row: Term) => row.academic_year_name ?? "",
            } as DataTableColumn<Term>,
          ]
        : []),
      {
        key: "program",
        label: t("terms.program"),
        render: (row) =>
          row.program ? (
            <Badge variant="secondary" className="px-1.5 py-0 text-[11px]">
              {tc(`programs.${row.program.type}`)}
            </Badge>
          ) : (
            "—"
          ),
        exportValue: (row) => row.program?.name ?? "",
      },
      {
        key: "calendar",
        label: t("terms.sections.calendar"),
        mobileHidden: true,
        render: (row) => {
          const weeks = durationWeeks(row)
          const left = daysLeft(row)
          return row.starts_on || row.ends_on ? (
            <div className="leading-tight">
              <span className="block text-sm tabular-nums">
                {row.starts_on ?? "…"} → {row.ends_on ?? "…"}
              </span>
              <span className="block text-xs text-muted-foreground">
                {weeks !== null && t("terms.durationWeeks", { count: weeks })}
                {left !== null && ` · ${t("terms.daysLeft", { count: left })}`}
              </span>
            </div>
          ) : (
            <span className="text-muted-foreground">—</span>
          )
        },
        exportValue: (row) => [row.starts_on, row.ends_on].filter(Boolean).join(" → "),
      },
      {
        key: "schedule",
        label: t("terms.sections.schedule"),
        mobileHidden: true,
        render: (row) => (
          <div className="leading-tight">
            <span className="block text-sm tabular-nums">
              {row.class_starts_at && row.class_ends_at
                ? `${fmtTime(row.class_starts_at)}–${fmtTime(row.class_ends_at)}`
                : "—"}
            </span>
          </div>
        ),
        exportValue: (row) =>
          row.class_starts_at && row.class_ends_at
            ? `${fmtTime(row.class_starts_at)}-${fmtTime(row.class_ends_at)} (${row.period_minutes}min)`
            : "",
      },
      {
        key: "status",
        label: tc("columns.status"),
        // In-place lifecycle dropdown (activate/close/reopen) with a
        // confirmation that lists the semesters activation will auto-close.
        render: (row) =>
          onStatusChanged ? (
            <TermStatusSelect
              term={row}
              siblings={allTerms}
              canUpdate={canUpdateStatus}
              onChanged={onStatusChanged}
            />
          ) : (
            <Badge variant={row.status === "active" ? "default" : "secondary"}>
              {t(`terms.statuses.${row.status}`)}
            </Badge>
          ),
        exportValue: (row) => row.status,
      },
    ],
    [t, tc, showBranch, showYear, allTerms, canUpdateStatus, onStatusChanged],
  )
}

/**
 * Client-side filters for a semester table. Year options come from the rows
 * actually shown; program/quarter/status are fixed sets.
 */
export function useTermFilters({
  terms,
  showYearFilter = true,
}: {
  terms: Term[]
  showYearFilter?: boolean
}): DataTableFilter[] {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")

  return useMemo(() => {
    const filters: DataTableFilter[] = []

    if (showYearFilter) {
      const years = new Map<number, string>()
      for (const term of terms) {
        if (term.academic_year_name) years.set(term.academic_year_id, term.academic_year_name)
      }
      if (years.size > 1) {
        filters.push({
          key: "academic_year_id",
          label: t("terms.academicYear"),
          options: [...years.entries()].map(([id, name]) => ({
            label: name,
            value: String(id),
          })),
        })
      }
    }

    filters.push(
      {
        key: "program_type",
        label: t("terms.program"),
        options: PROGRAM_TYPES.map((type) => ({
          label: tc(`programs.${type}`),
          value: type,
        })),
      },
      {
        key: "is_quarter",
        label: t("terms.quarter"),
        options: [
          { label: t("terms.quarter"), value: "true" },
          { label: t("terms.fullSemester"), value: "false" },
        ],
      },
      {
        key: "status",
        label: tc("filters.status"),
        options: (["planned", "active", "closed"] as const).map((s) => ({
          label: t(`terms.statuses.${s}`),
          value: s,
        })),
      },
    )

    return filters
  }, [terms, showYearFilter, t, tc])
}
