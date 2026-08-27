"use client"

import { ChevronDown } from "lucide-react"
import { useMemo, useState } from "react"

import { MarkCell, RankChip, useLoadMore } from "@/components/grading/roster-matrix"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { useTranslation } from "@/lib/i18n"
import type {
  RosterColumn,
  RosterScoreCell,
  YearRosterMeta,
  YearRosterStudent,
} from "@/lib/types"
import { cn } from "@/lib/utils"

/** One display line of a student's yearly block. */
interface YearLine {
  key: string
  kind: "term" | "semester" | "year"
  label: string
  scores: Record<string, RosterScoreCell>
  total: number | null
  average: number | null
  rank: number | null
  rank_of: number | null
}

/** Mean of the available subject totals across a set of lines, per subject. */
export function meanScores(
  columns: RosterColumn[],
  lines: { scores: Record<string, RosterScoreCell> }[],
): Record<string, RosterScoreCell> {
  const out: Record<string, RosterScoreCell> = {}
  for (const col of columns) {
    const key = String(col.subject_id)
    const totals = lines
      .map((line) => line.scores[key]?.total)
      .filter((v): v is number => v !== null && v !== undefined)
    out[key] =
      totals.length === 0
        ? { total: null, letter: null, is_passing: null }
        : {
            total: Math.round((totals.reduce((a, b) => a + b, 0) / totals.length) * 100) / 100,
            letter: null,
            // Derived mean keeps the tint honest without re-resolving bands.
            is_passing: lines.every(
              (line) => line.scores[key]?.is_passing !== false,
            ),
          }
  }
  return out
}

/**
 * The traditional printed yearly block: one line per term, a semester-average
 * line after each quarter group (per-subject means computed client-side from
 * the frozen term scores) and the year line with average + section rank.
 */
function buildLines(
  student: YearRosterStudent,
  terms: YearRosterMeta["terms"],
  hasSemesterGroups: boolean,
  columns: RosterColumn[],
  labels: { semesterAvg: (n: number) => string; yearAvg: string },
): YearLine[] {
  const byTerm = new Map(student.terms.map((line) => [line.term_id, line]))
  const lines: YearLine[] = []

  const pushSemester = (semester: number, group: YearRosterMeta["terms"]) => {
    const present = group
      .map((t) => byTerm.get(t.id))
      .filter((line): line is NonNullable<typeof line> => Boolean(line))
    if (present.length === 0) return
    lines.push({
      key: `sem-${semester}`,
      kind: "semester",
      label: labels.semesterAvg(semester),
      scores: meanScores(columns, present),
      total: null,
      average: student.semesters.find((s) => s.semester === semester)?.average ?? null,
      rank: null,
      rank_of: null,
    })
  }

  if (hasSemesterGroups) {
    const groups = new Map<number, YearRosterMeta["terms"]>()
    for (const term of terms) {
      if (term.is_quarter && term.semester !== null) {
        groups.set(term.semester, [...(groups.get(term.semester) ?? []), term])
      }
    }
    for (const term of terms) {
      const line = byTerm.get(term.id)
      if (line) {
        lines.push({
          key: `term-${term.id}`,
          kind: "term",
          label: term.name,
          scores: line.scores,
          total: line.total,
          average: line.average,
          rank: line.rank,
          rank_of: line.rank_of,
        })
      }
      // Close the semester after its last quarter.
      if (term.is_quarter && term.semester !== null) {
        const group = groups.get(term.semester) ?? []
        if (group.length > 0 && group[group.length - 1]?.id === term.id) {
          pushSemester(term.semester, group)
        }
      }
    }
  } else {
    for (const term of terms) {
      const line = byTerm.get(term.id)
      if (line) {
        lines.push({
          key: `term-${term.id}`,
          kind: "term",
          label: term.name,
          scores: line.scores,
          total: line.total,
          average: line.average,
          rank: line.rank,
          rank_of: line.rank_of,
        })
      }
    }
  }

  lines.push({
    key: "year",
    kind: "year",
    label: labels.yearAvg,
    scores: meanScores(columns, student.terms),
    total: null,
    average: student.year.average,
    rank: student.year.rank,
    rank_of: student.year.rank_of,
  })

  return lines
}

/**
 * Yearly roster: per student, all terms + semester sub-averages + year line.
 * Desktop = sticky-student-column sheet with rowSpan blocks; mobile = cards
 * with a term timeline and an expandable per-subject year summary.
 */
export function YearlyRoster({
  columns,
  students,
  terms,
  hasSemesterGroups,
  showSection,
}: {
  columns: RosterColumn[]
  students: YearRosterStudent[]
  terms: YearRosterMeta["terms"]
  hasSemesterGroups: boolean
  showSection: boolean
}) {
  const { t } = useTranslation("grading")
  const { visible, sentinelRef, done } = useLoadMore(students.length)
  const shown = students.slice(0, visible)

  const labels = useMemo(
    () => ({
      semesterAvg: (n: number) => t("rosters.semesterAvg", { semester: n }),
      yearAvg: t("rosters.yearAvg"),
    }),
    [t],
  )

  return (
    <>
      {/* ── Mobile: per-student cards with the term timeline ── */}
      <div className="space-y-2 md:hidden">
        {shown.map((student) => (
          <MobileYearCard
            key={student.student_id}
            student={student}
            terms={terms}
            hasSemesterGroups={hasSemesterGroups}
            columns={columns}
            labels={labels}
            showSection={showSection}
          />
        ))}
      </div>

      {/* ── Desktop: the printed-roster block layout ── */}
      <div className="bg-card hidden overflow-x-auto rounded-2xl border md:block">
        <table className="w-full min-w-[52rem] border-collapse text-sm">
          <thead>
            <tr className="bg-card sticky top-0 z-20 border-b">
              <th className="bg-card sticky left-0 z-30 px-4 py-3 text-left font-medium">
                {t("rosters.student")}
              </th>
              <th className="min-w-28 px-3 py-3 text-left text-xs font-medium">
                {t("rosters.period")}
              </th>
              {columns.map((col) => (
                <th
                  key={col.subject_id}
                  className="min-w-16 px-2 py-3 text-center text-xs font-semibold"
                  title={col.name}
                >
                  {col.code ?? col.name}
                </th>
              ))}
              <th className="min-w-16 px-3 py-3 text-right text-xs font-medium">
                {t("rosters.total")}
              </th>
              <th className="min-w-16 px-3 py-3 text-right text-xs font-medium">
                {t("rosters.average")}
              </th>
              <th className="min-w-14 px-3 py-3 text-center text-xs font-medium">
                {t("rosters.rank")}
              </th>
            </tr>
          </thead>
          <tbody>
            {shown.map((student, studentIndex) => {
              const lines = buildLines(student, terms, hasSemesterGroups, columns, labels)
              return lines.map((line, lineIndex) => (
                <tr
                  key={`${student.student_id}-${line.key}`}
                  className={cn(
                    "border-b",
                    line.kind === "semester" && "bg-muted/40",
                    line.kind === "year" && "bg-muted/70",
                    line.kind === "term" && studentIndex % 2 === 1 && "bg-muted/10",
                  )}
                >
                  {lineIndex === 0 && (
                    <td
                      rowSpan={lines.length}
                      className="bg-card sticky left-0 z-10 max-w-56 border-b px-4 py-2 align-top"
                    >
                      <p className="truncate font-medium">{student.full_name ?? "—"}</p>
                      <p className="text-muted-foreground truncate text-[11px]">
                        {[showSection ? student.section_name : null, student.public_id]
                          .filter(Boolean)
                          .join(" · ")}
                      </p>
                    </td>
                  )}
                  <td
                    className={cn(
                      "whitespace-nowrap px-3 py-1.5 text-xs",
                      line.kind === "term" ? "text-muted-foreground" : "font-semibold",
                    )}
                  >
                    {line.label}
                  </td>
                  {columns.map((col) => (
                    <td
                      key={col.subject_id}
                      className={cn(
                        "px-2 py-1.5 text-center",
                        line.kind !== "term" && "font-medium",
                      )}
                    >
                      <MarkCell cell={line.scores[String(col.subject_id)]} />
                    </td>
                  ))}
                  <td className="px-3 py-1.5 text-right tabular-nums">{line.total ?? "—"}</td>
                  <td
                    className={cn(
                      "px-3 py-1.5 text-right tabular-nums",
                      line.kind === "term" ? "font-medium" : "font-semibold",
                    )}
                  >
                    {line.average ?? "—"}
                  </td>
                  <td className="px-3 py-1.5 text-center">
                    {line.kind === "year" ? (
                      <RankChip rank={line.rank} rankOf={line.rank_of} />
                    ) : line.rank !== null ? (
                      <span className="text-muted-foreground text-xs tabular-nums">
                        {line.rank}
                      </span>
                    ) : (
                      <span className="text-muted-foreground">—</span>
                    )}
                  </td>
                </tr>
              ))
            })}
          </tbody>
        </table>
      </div>

      {!done && <div ref={sentinelRef} className="h-8" />}
    </>
  )
}

/** Mobile card: year rank + average up top, term timeline, subject summary. */
function MobileYearCard({
  student,
  terms,
  hasSemesterGroups,
  columns,
  labels,
  showSection,
}: {
  student: YearRosterStudent
  terms: YearRosterMeta["terms"]
  hasSemesterGroups: boolean
  columns: RosterColumn[]
  labels: { semesterAvg: (n: number) => string; yearAvg: string }
  showSection: boolean
}) {
  const { t } = useTranslation("grading")
  const [open, setOpen] = useState(false)
  const lines = useMemo(
    () => buildLines(student, terms, hasSemesterGroups, columns, labels),
    [student, terms, hasSemesterGroups, columns, labels],
  )
  const yearLine = lines[lines.length - 1]

  return (
    <div className="bg-card rounded-2xl border">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex min-h-14 w-full items-center gap-3 px-3 py-2.5 text-left"
      >
        <RankChip rank={student.year.rank} rankOf={student.year.rank_of} />
        <PersonAvatar
          name={student.full_name ?? "?"}
          photoUrl={student.photo_url}
          className="size-9"
        />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">{student.full_name ?? "—"}</p>
          <p className="text-muted-foreground truncate text-xs">
            {[showSection ? student.section_name : null, student.public_id]
              .filter(Boolean)
              .join(" · ") || "—"}
          </p>
        </div>
        <div className="text-right">
          <p className="text-base font-semibold tabular-nums">{student.year.average ?? "—"}</p>
          <p className="text-muted-foreground text-[10px]">{t("rosters.yearAvg")}</p>
        </div>
        <ChevronDown
          className={cn(
            "text-muted-foreground size-4 shrink-0 transition-transform",
            open && "rotate-180",
          )}
        />
      </button>

      {/* The term timeline is always visible — the roster's story at a glance. */}
      <div className="border-t px-4 py-2">
        {lines.map((line) => (
          <div
            key={line.key}
            className={cn(
              "flex items-center justify-between gap-2 py-1 text-sm",
              line.kind !== "term" && "font-semibold",
            )}
          >
            <span
              className={cn(
                "min-w-0 truncate text-xs",
                line.kind === "term" ? "text-muted-foreground" : "",
              )}
            >
              {line.label}
            </span>
            <span className="flex items-center gap-2 tabular-nums">
              {line.kind === "term" && line.rank !== null && (
                <span className="text-muted-foreground text-[10px]">#{line.rank}</span>
              )}
              {line.average ?? "—"}
            </span>
          </div>
        ))}
      </div>

      {/* Expanded: per-subject year means. */}
      {open && (
        <div className="grid grid-cols-2 gap-x-4 border-t px-4 py-3">
          {columns.map((col) => (
            <div
              key={col.subject_id}
              className="flex items-center justify-between gap-2 py-1 text-sm"
            >
              <span className="text-muted-foreground min-w-0 truncate text-xs">{col.name}</span>
              <MarkCell cell={yearLine.scores[String(col.subject_id)]} />
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
