"use client"

import { GraduationCap, Pencil } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useTranslation } from "@/lib/i18n"
import type { Employee } from "@/lib/types"

/** Academic credentials — one card, one row per degree. */
export function EmployeeQualificationsTab({
  employee,
  onEdit,
}: {
  employee: Employee
  /** Opens the edit sheet on the Qualifications tab; omitted when read-only. */
  onEdit?: () => void
}) {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const qualifications = employee.qualifications ?? []

  return (
    <Card>
      <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
        <CardTitle className="flex items-center gap-2 text-base">
          <span className="flex size-8 items-center justify-center rounded-lg bg-accent text-muted-foreground">
            <GraduationCap className="size-4" />
          </span>
          {t("profile.qualifications")}
        </CardTitle>
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
        {qualifications.length === 0 ? (
          <p className="text-sm text-muted-foreground">—</p>
        ) : (
          <ul className="space-y-2 text-sm">
            {qualifications.map((q) => (
              <li key={q.id} className="rounded-xl border px-3 py-2.5">
                <p className="font-medium">
                  {q.education_level}
                  {q.field_of_study ? ` — ${q.field_of_study}` : ""}
                </p>
                {q.institution || q.graduation_year ? (
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {[q.institution, q.graduation_year].filter(Boolean).join(" · ")}
                  </p>
                ) : null}
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}
