"use client"

import { X } from "lucide-react"
import type { UseFormReturn } from "react-hook-form"

import type { EmployeeFormValues } from "@/components/employees/wizard/schema"
import { Button } from "@/components/ui/button"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useTranslation } from "@/lib/i18n"
import { subjectAppliesToSort } from "@/lib/subjects"
import type { GradeLevel, Subject } from "@/lib/types"
import { cn } from "@/lib/utils"

interface Props {
  active: boolean
  form: UseFormReturn<EmployeeFormValues>
  subjects: Subject[]
  grades: GradeLevel[]
  /** Grades opened on the picker — a grade with no subject ticked yet still
   * needs to stay on screen, so this is state rather than derived. */
  teachingGradeIds: number[]
  setTeachingGradeIds: React.Dispatch<React.SetStateAction<number[]>>
  teacherSubjects: ({ subject_id?: number; grade_level_id?: number } | undefined)[]
  onToggleSubject: (subjectId: number, gradeLevelId: number) => void
}

/**
 * What a teacher is qualified to teach, as subject × grade pairs. Only
 * rendered while a current teacher position exists.
 */
export function TeachingStep({
  active,
  form,
  subjects,
  grades,
  teachingGradeIds,
  setTeachingGradeIds,
  teacherSubjects,
  onToggleSubject,
}: Props) {
  const { t } = useTranslation("employees")

  return (
    <div className={cn("space-y-4", !active && "hidden")}>
      <p className="text-xs text-muted-foreground">{t("teaching.hint")}</p>
      {teachingGradeIds.map((gradeId) => {
        const grade = grades.find((g) => g.id === gradeId)
        if (!grade) return null
        const applicable = subjects.filter((s) => subjectAppliesToSort(s, grade.sort_order))
        return (
          <div key={gradeId} className="space-y-2 rounded-xl border p-3">
            <div className="flex items-center justify-between">
              <p className="text-sm font-medium">{grade.name}</p>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-8 text-muted-foreground hover:text-destructive"
                onClick={() => {
                  setTeachingGradeIds((prev) => prev.filter((id) => id !== gradeId))
                  form.setValue(
                    "teacher_subjects",
                    (form.getValues("teacher_subjects") ?? []).filter(
                      (ts) => ts.grade_level_id !== gradeId
                    )
                  )
                }}
                aria-label={t("teaching.removeGrade")}
              >
                <X className="size-4" />
              </Button>
            </div>
            <div className="flex flex-wrap gap-1.5">
              {applicable.map((subject) => {
                const selected = teacherSubjects.some(
                  (ts) => ts?.subject_id === subject.id && ts?.grade_level_id === gradeId
                )
                return (
                  <button
                    key={subject.id}
                    type="button"
                    onClick={() => onToggleSubject(subject.id, gradeId)}
                    className={cn(
                      "pressable inline-flex min-h-8 items-center rounded-full border px-3 text-xs font-medium transition-colors",
                      selected
                        ? "border-primary/40 bg-primary/10 text-primary"
                        : "text-muted-foreground hover:bg-muted"
                    )}
                    aria-pressed={selected}
                  >
                    {subject.name}
                  </button>
                )
              })}
            </div>
          </div>
        )
      })}
      <Select
        value=""
        onValueChange={(v) => {
          const id = Number(v)
          setTeachingGradeIds((prev) => (prev.includes(id) ? prev : [...prev, id]))
        }}
      >
        <SelectTrigger className="w-full">
          <SelectValue placeholder={t("teaching.addGrade")} />
        </SelectTrigger>
        <SelectContent>
          {grades
            .filter((g) => !teachingGradeIds.includes(g.id))
            .map((grade) => (
              <SelectItem key={grade.id} value={String(grade.id)}>
                {grade.name}
              </SelectItem>
            ))}
        </SelectContent>
      </Select>
    </div>
  )
}
