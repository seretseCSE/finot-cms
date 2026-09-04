"use client"

import { HeartPulse, Pencil, Plus } from "lucide-react"
import { useState } from "react"

import { HealthSheet } from "@/components/students/health-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { useTranslation } from "@/lib/i18n"
import type { Student } from "@/lib/types"

const SEVERITY_CLASS: Record<string, string> = {
  mild: "bg-success/10 text-success",
  moderate: "bg-warning/10 text-warning",
  severe: "bg-destructive/10 text-destructive",
}

export function HealthTab({
  student,
  canUpdate,
  onChanged,
}: {
  student: Student
  canUpdate: boolean
  onChanged: () => void
}) {
  const { t } = useTranslation("students")

  const [sheetOpen, setSheetOpen] = useState(false)

  const conditions = student.health_conditions ?? []
  const hasAny = conditions.length > 0 || student.blood_type || student.health_notes

  return (
    <div className="space-y-4">
      {canUpdate && hasAny ? (
        <div className="flex justify-end">
          <Button variant="outline" className="h-10 rounded-full" onClick={() => setSheetOpen(true)}>
            <Pencil className="size-4" />
            {t("health.edit")}
          </Button>
        </div>
      ) : null}

      {!hasAny ? (
        <div className="space-y-3 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
          <HeartPulse className="mx-auto size-6 opacity-50" />
          <p>{t("health.empty")}</p>
          {canUpdate ? (
            <Button
              variant="outline"
              className="h-10 rounded-full"
              onClick={() => setSheetOpen(true)}
            >
              <Plus className="size-4" />
              {t("health.add")}
            </Button>
          ) : null}
        </div>
      ) : (
        <>
          {student.blood_type ? (
            <Card>
              <CardContent className="flex items-center justify-between py-4 text-sm">
                <span className="text-muted-foreground">{t("fields.bloodType")}</span>
                <Badge variant="outline" className="font-mono">
                  {student.blood_type}
                </Badge>
              </CardContent>
            </Card>
          ) : null}

          {conditions.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t("wizard.conditions")}</CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="space-y-3">
                  {conditions.map((condition) => (
                    <li
                      key={condition.health_condition_id}
                      className="flex flex-col gap-1 border-b pb-3 text-sm last:border-b-0 last:pb-0"
                    >
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{condition.name}</span>
                        {condition.severity ? (
                          <span
                            className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${SEVERITY_CLASS[condition.severity] ?? ""}`}
                          >
                            {t(`wizard.severities.${condition.severity}`)}
                          </span>
                        ) : null}
                      </div>
                      {condition.medication ? (
                        <span className="text-xs text-muted-foreground">
                          {t("health.medication")}: {condition.medication}
                        </span>
                      ) : null}
                      {condition.notes ? (
                        <span className="text-xs text-muted-foreground">{condition.notes}</span>
                      ) : null}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ) : null}

          {student.health_notes ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">{t("fields.healthNotes")}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-muted-foreground">{student.health_notes}</CardContent>
            </Card>
          ) : null}
        </>
      )}

      {canUpdate ? (
        <HealthSheet
          student={student}
          open={sheetOpen}
          onOpenChange={setSheetOpen}
          onSaved={onChanged}
        />
      ) : null}
    </div>
  )
}
