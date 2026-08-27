"use client"

import { useMemo } from "react"

import { useTranslation } from "@/lib/i18n"
import type { Cycle, GradeLevel } from "@/lib/types"
import { cn } from "@/lib/utils"

interface GradeMultiSelectProps {
  gradeLevels: GradeLevel[]
  /** Selected grade_level ids. Empty = "every grade" (open). */
  value: number[]
  onChange: (ids: number[]) => void
  disabled?: boolean
}

/**
 * Grade picker as a cycle-grouped chip grid — the exact set is first-class
 * (real curricula have gaps), one tap per grade, tap a cycle name to toggle
 * the whole cycle. An EMPTY selection means "taught in every grade".
 */
export function GradeMultiSelect({ gradeLevels, value, onChange, disabled }: GradeMultiSelectProps) {
  const { t } = useTranslation("catalogs")
  const selected = useMemo(() => new Set(value), [value])

  // Cycles in ladder order (kindergarten → preparatory), grades within too.
  const cycles = useMemo(() => {
    const ordered = [...gradeLevels].sort((a, b) => a.sort_order - b.sort_order)
    const groups = new Map<Cycle, GradeLevel[]>()
    for (const grade of ordered) {
      const list = groups.get(grade.cycle) ?? []
      list.push(grade)
      groups.set(grade.cycle, list)
    }
    return [...groups.entries()]
  }, [gradeLevels])

  function toggle(id: number) {
    const next = new Set(selected)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    onChange([...next])
  }

  function toggleCycle(grades: GradeLevel[]) {
    const next = new Set(selected)
    const allOn = grades.every((g) => next.has(g.id))
    for (const grade of grades) {
      if (allOn) next.delete(grade.id)
      else next.add(grade.id)
    }
    onChange([...next])
  }

  return (
    <div className="space-y-2.5">
      {cycles.map(([cycle, grades]) => {
        const allOn = grades.every((g) => selected.has(g.id))
        return (
          <div key={cycle} className="space-y-1.5">
            <button
              type="button"
              disabled={disabled}
              onClick={() => toggleCycle(grades)}
              className={cn(
                "text-xs font-medium transition-colors",
                allOn ? "text-primary" : "text-muted-foreground hover:text-foreground",
              )}
            >
              {t(`gradeLevels.cycles.${cycle}`)}
            </button>
            <div className="flex flex-wrap gap-1.5">
              {grades.map((grade) => {
                const on = selected.has(grade.id)
                return (
                  <button
                    key={grade.id}
                    type="button"
                    disabled={disabled}
                    onClick={() => toggle(grade.id)}
                    aria-pressed={on}
                    className={cn(
                      "min-h-9 min-w-12 rounded-lg border px-2.5 text-sm font-medium tabular-nums transition-colors",
                      on
                        ? "border-primary bg-primary text-primary-foreground"
                        : "bg-card text-muted-foreground hover:border-foreground/30",
                      disabled && "opacity-50",
                    )}
                  >
                    {grade.code}
                  </button>
                )
              })}
            </div>
          </div>
        )
      })}
    </div>
  )
}
