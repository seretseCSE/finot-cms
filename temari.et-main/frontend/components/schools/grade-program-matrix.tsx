"use client"

import { Check, Lock } from "lucide-react"
import { useMemo } from "react"

import { Checkbox } from "@/components/ui/checkbox"
import { PROGRAM_TYPES } from "@/lib/data/programs"
import { useTranslation } from "@/lib/i18n"
import type { BranchGradeUsage, Cycle, GradeLevel, ProgramRef } from "@/lib/types"
import { cn } from "@/lib/utils"

/** One enabled program column with the grades it is offered in. */
export interface ProgramGrades {
  type: string
  grade_level_ids: number[]
}

/**
 * The branch's grade × program offering matrix: rows are the national grade
 * ladder grouped by cycle, columns are the branch's enabled education
 * programs, each cell one checkbox. Program chips add/remove columns; column
 * and cycle checkboxes bulk-toggle. In edit mode, cells the backend would
 * refuse to uncheck (live enrollments in the cell, or the grade's last program
 * while sections exist) render locked with the usage counts.
 */
export function GradeProgramMatrix({
  grades,
  value,
  onChange,
  usage,
  existingPrograms,
}: {
  /** Full national ladder (`/grade-levels?all=1`). */
  grades: GradeLevel[]
  value: ProgramGrades[]
  onChange: (next: ProgramGrades[]) => void
  /** Edit mode: live usage per grade (branch show `meta.grade_usage`). */
  usage?: BranchGradeUsage
  /** Edit mode: the branch's saved programs (program removal is additive-only). */
  existingPrograms?: ProgramRef[]
}) {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const { t: tcat } = useTranslation("catalogs")

  const enabledTypes = value.map((entry) => entry.type)
  const programIdByType = useMemo(
    () => new Map((existingPrograms ?? []).map((program) => [program.type, program.id])),
    [existingPrograms],
  )

  const cycles = useMemo(() => {
    const groups: { cycle: Cycle; rows: GradeLevel[] }[] = []
    for (const grade of grades) {
      const last = groups[groups.length - 1]
      if (last && last.cycle === grade.cycle) last.rows.push(grade)
      else groups.push({ cycle: grade.cycle, rows: [grade] })
    }
    return groups
  }, [grades])

  function entryFor(type: string) {
    return value.find((entry) => entry.type === type)
  }

  function setEntry(type: string, gradeIds: number[]) {
    onChange(value.map((entry) => (entry.type === type ? { ...entry, grade_level_ids: gradeIds } : entry)))
  }

  /** Why this cell can't be unchecked, or null when it can. */
  function lockReason(type: string, grade: GradeLevel): string | null {
    const entry = entryFor(type)
    if (!entry || !entry.grade_level_ids.includes(grade.id)) return null
    const gradeUsage = usage?.[String(grade.id)]
    if (!gradeUsage) return null

    const programId = programIdByType.get(type)
    const enrolled = programId != null ? (gradeUsage.enrollments?.[String(programId)] ?? 0) : 0
    if (enrolled > 0) return t("branches.matrix.lockedEnrollments", { count: String(enrolled) })

    // Sections carry a grade, not a program — they lock the grade's LAST cell.
    const checkedCount = value.filter((e) => e.grade_level_ids.includes(grade.id)).length
    if ((gradeUsage.sections ?? 0) > 0 && checkedCount === 1)
      return t("branches.matrix.lockedSections", { count: String(gradeUsage.sections) })

    return null
  }

  function toggleProgram(type: string) {
    if (enabledTypes.includes(type)) {
      // Saved programs are additive-only server-side; the last column stays too.
      if (programIdByType.has(type) || value.length === 1) return
      onChange(value.filter((entry) => entry.type !== type))
      return
    }
    // The first program starts with every grade; extra ones start empty so the
    // principal deliberately picks where the new program runs.
    onChange([
      ...value,
      { type, grade_level_ids: value.length === 0 ? grades.map((g) => g.id) : [] },
    ])
  }

  function toggleCell(type: string, grade: GradeLevel) {
    const entry = entryFor(type)
    if (!entry) return
    if (entry.grade_level_ids.includes(grade.id)) {
      if (lockReason(type, grade)) return
      setEntry(type, entry.grade_level_ids.filter((id) => id !== grade.id))
    } else {
      setEntry(type, [...entry.grade_level_ids, grade.id])
    }
  }

  /** Bulk-toggle a set of rows for one program, honouring locked cells. */
  function toggleRows(type: string, rows: GradeLevel[]) {
    const entry = entryFor(type)
    if (!entry) return
    const allOn = rows.every((grade) => entry.grade_level_ids.includes(grade.id))
    if (allOn) {
      const keep = rows.filter((grade) => lockReason(type, grade) !== null).map((g) => g.id)
      setEntry(
        type,
        entry.grade_level_ids.filter((id) => !rows.some((g) => g.id === id) || keep.includes(id)),
      )
    } else {
      const merged = new Set([...entry.grade_level_ids, ...rows.map((g) => g.id)])
      setEntry(type, [...merged])
    }
  }

  function columnState(type: string, rows: GradeLevel[]): boolean | "indeterminate" {
    const entry = entryFor(type)
    if (!entry) return false
    const checked = rows.filter((grade) => entry.grade_level_ids.includes(grade.id)).length
    if (checked === 0) return false
    return checked === rows.length ? true : "indeterminate"
  }

  const gridTemplate = `minmax(7.5rem, 1fr) repeat(${enabledTypes.length}, minmax(3.25rem, 5.5rem))`

  return (
    <div className="space-y-3">
      {/* Program columns — chip toggles add/remove matrix columns. */}
      <div className="flex flex-wrap gap-1.5">
        {PROGRAM_TYPES.map((type) => {
          const selected = enabledTypes.includes(type)
          const locked = selected && (programIdByType.has(type) || value.length === 1)
          return (
            <button
              key={type}
              type="button"
              onClick={() => toggleProgram(type)}
              title={locked ? t("branches.matrix.programLocked") : undefined}
              aria-label={tc(`programs.${type}`)}
              className={cn(
                "pressable flex min-h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors",
                selected
                  ? "border-primary bg-primary/10 text-primary"
                  : "border-border bg-background text-muted-foreground hover:bg-muted",
              )}
              aria-pressed={selected}
            >
              {selected && (locked ? <Lock className="size-3" /> : <Check className="size-3" />)}
              {tc(`programs.${type}`)}
            </button>
          )
        })}
      </div>

      <div className="overflow-x-auto rounded-xl border">
        <div className="min-w-fit">
          {/* Header: grade column + one column per enabled program. */}
          <div
            className="grid items-center border-b bg-muted/40 dark:bg-muted/15"
            style={{ gridTemplateColumns: gridTemplate }}
          >
            <div className="px-3 py-2.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
              {t("branches.matrix.gradeColumn")}
            </div>
            {enabledTypes.map((type) => (
              <div key={type} className="flex flex-col items-center gap-1.5 px-1 py-2.5">
                <span className="max-w-full truncate text-xs font-medium">{tc(`programs.${type}`)}</span>
                <Checkbox
                  checked={columnState(type, grades)}
                  onCheckedChange={() => toggleRows(type, grades)}
                  aria-label={t("branches.matrix.toggleColumn", { program: tc(`programs.${type}`) })}
                />
              </div>
            ))}
          </div>

          {cycles.map(({ cycle, rows }) => (
            <div key={cycle}>
              {/* Cycle band: bulk-toggle its grades per program. */}
              <div
                className="grid items-center border-b bg-primary/5 dark:bg-primary/10"
                style={{ gridTemplateColumns: gridTemplate }}
              >
                <div className="flex items-center gap-2 px-3 py-2">
                  <span className="h-3.5 w-1 shrink-0 rounded-full bg-primary/70" aria-hidden />
                  <span className="text-[11px] font-semibold tracking-wider text-primary uppercase">
                    {tcat(`gradeLevels.cycles.${cycle}`)}
                  </span>
                </div>
                {enabledTypes.map((type) => (
                  <div key={type} className="flex justify-center py-1.5">
                    <Checkbox
                      className="size-3.5"
                      checked={columnState(type, rows)}
                      onCheckedChange={() => toggleRows(type, rows)}
                      aria-label={t("branches.matrix.toggleCycle", {
                        cycle: tcat(`gradeLevels.cycles.${cycle}`),
                        program: tc(`programs.${type}`),
                      })}
                    />
                  </div>
                ))}
              </div>

              {rows.map((grade) => {
                const supported = value.some((entry) => entry.grade_level_ids.includes(grade.id))
                return (
                  <div
                    key={grade.id}
                    className="grid min-h-11 items-center border-b last:border-b-0"
                    style={{ gridTemplateColumns: gridTemplate }}
                  >
                    <div className="flex items-center gap-2 px-3">
                      <span className={cn("text-sm", !supported && "text-muted-foreground")}>{grade.name}</span>
                      {grade.has_national_exam && (
                        <span
                          className="size-1.5 rounded-full bg-primary/60"
                          title={t("branches.matrix.nationalExam")}
                        />
                      )}
                    </div>
                    {enabledTypes.map((type) => {
                      const entry = entryFor(type)
                      const checked = entry?.grade_level_ids.includes(grade.id) ?? false
                      const reason = lockReason(type, grade)
                      return (
                        <div key={type} className="flex justify-center py-1.5" title={reason ?? undefined}>
                          <Checkbox
                            checked={checked}
                            disabled={reason !== null}
                            onCheckedChange={() => toggleCell(type, grade)}
                            aria-label={`${grade.name} · ${tc(`programs.${type}`)}`}
                          />
                        </div>
                      )
                    })}
                  </div>
                )
              })}
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
