"use client"

import { Check, Plus, Trash2 } from "lucide-react"
import { useMemo } from "react"

import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useTranslation } from "@/lib/i18n"
import type { LmsClassOption } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The shared grade → sections audience model (materials AND courses): one
 * default "everyone" row, plus as many grade/section-scoped rows as needed.
 * A section already claimed by one row is hidden from the others so the
 * same class can never be targeted twice. Rows resolve client-side into the
 * flat subject_assignment_ids the *_targets pivots expect.
 */
export interface AudienceRow {
  key: string
  /** "all" = every offered class; otherwise a grade name from `classes`. */
  grade: string
  sectionIds: number[]
}

let rowSeq = 0
export function makeAudienceRow(grade = "all"): AudienceRow {
  rowSeq += 1
  return { key: `row-${rowSeq}`, grade, sectionIds: [] }
}

/** The flat subject_assignment_ids the current rows select over `classes`. */
export function audienceTargetIds(rows: AudienceRow[], classes: LmsClassOption[]): number[] {
  const ids = new Set<number>()
  for (const row of rows) {
    if (row.grade === "all") {
      classes.forEach((c) => ids.add(c.subject_assignment_id))
    } else if (row.sectionIds.length > 0) {
      classes
        .filter((c) => c.section.grade_level === row.grade && row.sectionIds.includes(c.section.id ?? -1))
        .forEach((c) => ids.add(c.subject_assignment_id))
    }
  }
  return [...ids]
}

/** Rebuild rows (one per grade) from saved target ids — for edit hydration. */
export function audienceRowsFromTargets(
  targetIds: number[],
  classes: LmsClassOption[],
): AudienceRow[] | null {
  const byGrade = new Map<string, Set<number>>()
  for (const targetId of targetIds) {
    const match = classes.find((c) => c.subject_assignment_id === targetId)
    const grade = match?.section.grade_level
    const sectionId = match?.section.id
    if (!grade || sectionId == null) continue
    if (!byGrade.has(grade)) byGrade.set(grade, new Set())
    byGrade.get(grade)!.add(sectionId)
  }
  if (byGrade.size === 0) return null
  return [...byGrade.entries()].map(([grade, ids]) => ({ ...makeAudienceRow(grade), sectionIds: [...ids] }))
}

interface Props {
  classes: LmsClassOption[]
  rows: AudienceRow[]
  onChange: (rows: AudienceRow[]) => void
  /** Fired on any interaction — callers clear their audience error here. */
  onInteract?: () => void
}

export function AudienceRows({ classes, rows, onChange, onInteract }: Props) {
  const { t } = useTranslation("lms")

  const gradeNames = useMemo(
    () => [...new Set(classes.map((c) => c.section.grade_level).filter((g): g is string => Boolean(g)))],
    [classes],
  )
  const sectionOptionsByGrade = useMemo(() => {
    const map = new Map<string, { id: number; name: string }[]>()
    for (const option of classes) {
      const grade = option.section.grade_level
      if (!grade || option.section.id == null) continue
      const list = map.get(grade) ?? []
      if (!list.some((s) => s.id === option.section.id)) {
        list.push({ id: option.section.id, name: option.section.name ?? "" })
      }
      map.set(grade, list)
    }
    return map
  }, [classes])

  // Sections already claimed per grade, so a later row never offers a duplicate.
  const usedSectionsByGrade = useMemo(() => {
    const map = new Map<string, Set<number>>()
    for (const row of rows) {
      if (row.grade === "all") continue
      const set = map.get(row.grade) ?? new Set<number>()
      row.sectionIds.forEach((id) => set.add(id))
      map.set(row.grade, set)
    }
    return map
  }, [rows])

  function update(next: AudienceRow[]) {
    onChange(next)
    onInteract?.()
  }

  return (
    <>
      <div className="divide-y overflow-hidden rounded-xl border">
        {rows.map((row) => {
          const usedByOthers = new Set(
            [...(usedSectionsByGrade.get(row.grade) ?? [])].filter((id) => !row.sectionIds.includes(id)),
          )
          const sectionOptions =
            row.grade !== "all"
              ? (sectionOptionsByGrade.get(row.grade) ?? []).filter((opt) => !usedByOthers.has(opt.id))
              : []
          const allSelected = sectionOptions.length > 0 && row.sectionIds.length === sectionOptions.length
          const availableGrades = gradeNames.filter((grade) => {
            if (grade === row.grade) return true
            const all = sectionOptionsByGrade.get(grade) ?? []
            const used = usedSectionsByGrade.get(grade) ?? new Set<number>()
            return all.length === 0 || all.some((opt) => !used.has(opt.id))
          })

          return (
            <div key={row.key} className="space-y-2.5 p-3">
              <div className="flex items-end gap-2">
                <div className="min-w-0 flex-1 space-y-1.5">
                  <Label className="text-xs text-muted-foreground">{t("materials.grade")}</Label>
                  <Select
                    value={row.grade}
                    onValueChange={(v) =>
                      update(rows.map((r) => (r.key === row.key ? { ...r, grade: v, sectionIds: [] } : r)))
                    }
                  >
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{t("materials.allGrades")}</SelectItem>
                      {availableGrades.map((grade) => (
                        <SelectItem key={grade} value={grade}>
                          {grade}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                {rows.length > 1 && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="shrink-0 text-muted-foreground"
                    onClick={() => update(rows.filter((r) => r.key !== row.key))}
                    aria-label={t("materials.removeAudience")}
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>

              {row.grade !== "all" && (
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <Label className="text-xs text-muted-foreground">{t("materials.sections")}</Label>
                    {sectionOptions.length > 0 && (
                      <button
                        type="button"
                        className="text-xs font-medium text-primary hover:underline"
                        onClick={() =>
                          update(
                            rows.map((r) =>
                              r.key === row.key
                                ? { ...r, sectionIds: allSelected ? [] : sectionOptions.map((s) => s.id) }
                                : r,
                            ),
                          )
                        }
                      >
                        {allSelected ? t("materials.deselectAllSections") : t("materials.selectAllSections")}
                      </button>
                    )}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {sectionOptions.length === 0 ? (
                      <p className="text-sm text-muted-foreground">{t("materials.noSectionsForGrade")}</p>
                    ) : (
                      sectionOptions.map((section) => {
                        const selected = row.sectionIds.includes(section.id)
                        return (
                          <button
                            key={section.id}
                            type="button"
                            onClick={() =>
                              update(
                                rows.map((r) =>
                                  r.key === row.key
                                    ? {
                                        ...r,
                                        sectionIds: selected
                                          ? r.sectionIds.filter((id) => id !== section.id)
                                          : [...r.sectionIds, section.id],
                                      }
                                    : r,
                                ),
                              )
                            }
                            className={cn(
                              "inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-sm font-medium transition-colors",
                              selected ? "border-primary/40 bg-primary/10 text-primary" : "hover:bg-muted/60",
                            )}
                          >
                            {selected && <Check className="size-3.5" />}
                            {section.name}
                          </button>
                        )
                      })
                    )}
                  </div>
                </div>
              )}
            </div>
          )
        })}
      </div>

      <Button
        type="button"
        variant="outline"
        size="sm"
        className="mt-3 w-full"
        onClick={() => update([...rows, makeAudienceRow("all")])}
      >
        <Plus className="size-3.5" /> {t("materials.addAudience")}
      </Button>
    </>
  )
}
