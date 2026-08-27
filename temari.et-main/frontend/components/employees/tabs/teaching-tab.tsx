"use client"

import { Pencil } from "lucide-react"
import { useMemo } from "react"

import { TeachingCard } from "@/components/employees/teaching-card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useTranslation } from "@/lib/i18n"
import type { Employee } from "@/lib/types"

/** Teaching capability (subject × grade window) + the live semester workload. */
export function EmployeeTeachingTab({
  employee,
  canManageTimetable,
  onEdit,
}: {
  employee: Employee
  canManageTimetable: boolean
  /** Opens the edit sheet on the Teaching tab; omitted when read-only. */
  onEdit?: () => void
}) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")

  /** Fold teaching capability to "Mathematics · Grade 1–4". */
  const teaching = useMemo(() => {
    const bySubject = new Map<string, { name: string; sorts: number[]; grades: string[] }>()
    for (const ts of employee.teacher_subjects ?? []) {
      const key = ts.subject_name ?? String(ts.subject_id)
      const entry = bySubject.get(key) ?? { name: key, sorts: [], grades: [] }
      if (ts.grade_level_sort != null) entry.sorts.push(ts.grade_level_sort)
      if (ts.grade_level_name) entry.grades.push(ts.grade_level_name)
      bySubject.set(key, entry)
    }
    return [...bySubject.values()].map((entry) => {
      const ordered = entry.grades
        .map((name, i) => ({ name, sort: entry.sorts[i] ?? 0 }))
        .sort((a, b) => a.sort - b.sort)
      const label =
        ordered.length > 2
          ? `${ordered[0].name} – ${ordered[ordered.length - 1].name}`
          : ordered.map((g) => g.name).join(", ")
      return { name: entry.name, grades: label }
    })
  }, [employee])

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
          <CardTitle className="text-base">{t("profile.teaching")}</CardTitle>
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
        <CardContent>
          {teaching.length === 0 ? (
            <p className="text-sm text-muted-foreground">—</p>
          ) : (
            <div className="flex flex-wrap gap-1.5">
              {teaching.map((row) => (
                <Badge key={row.name} variant="outline" className="rounded-full text-xs">
                  {row.name}
                  {row.grades ? (
                    <span className="text-muted-foreground"> · {row.grades}</span>
                  ) : null}
                </Badge>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* What they actually teach this semester — live from the teaching grid. */}
      <TeachingCard employeeId={employee.id} canManageTimetable={canManageTimetable} />
    </div>
  )
}
