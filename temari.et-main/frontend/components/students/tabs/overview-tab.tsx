"use client"

import { ChartSpline, GraduationCap, Pencil } from "lucide-react"
import { useState } from "react"

import { EditEnrollmentSheet } from "@/components/students/edit-enrollment-sheet"
import { EnrollmentResultsDialog } from "@/components/students/enrollment-results-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { Student, StudentEnrollment } from "@/lib/types"
import { cn } from "@/lib/utils"

/** Enrollment history only — addresses live in their own tab (AddressTab). */
export function OverviewTab({
  student,
  canEditEnrollment = false,
  onChanged,
}: {
  student: Student
  /** enrollments.create in a live scope — allows fixing a wrong grade/section. */
  canEditEnrollment?: boolean
  onChanged?: () => void
}) {
  const { t } = useTranslation("students")
  const { active, isPlatform } = useSchoolContext()
  // The per-year results modal; data loads only when it opens (lazy).
  const [performanceOf, setPerformanceOf] = useState<StudentEnrollment | null>(null)
  const [editing, setEditing] = useState<StudentEnrollment | null>(null)

  // Only a LIVE enrollment at the viewer's own school can be corrected —
  // closed years and other schools' rows are history.
  const canEdit = (enrollment: StudentEnrollment): boolean =>
    canEditEnrollment &&
    (enrollment.status === "pending" || enrollment.status === "active") &&
    (enrollment.school_id == null ||
      active.schoolId == null ||
      enrollment.school_id === active.schoolId)

  // The results endpoint is scoped to the enrollment's OWN school — years at
  // a school the viewer doesn't manage (before/after a transfer) can't be
  // opened from here, so don't offer a button that would only error.
  const canOpenPerformance = (enrollment: StudentEnrollment): boolean =>
    isPlatform ||
    enrollment.school_id == null ||
    active.schoolId == null ||
    enrollment.school_id === active.schoolId

  const enrollments = (student.enrollments ?? [])
    .slice()
    .sort((a, b) => b.academic_year_id - a.academic_year_id)

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">{t("detail.enrollments")}</CardTitle>
        </CardHeader>
        <CardContent className="text-sm">
          {enrollments.length === 0 ? (
            <p className="text-muted-foreground">{t("detail.noEnrollments")}</p>
          ) : (
            <ol className="relative space-y-0 border-l border-border/70 pl-5">
              {enrollments.map((enrollment: StudentEnrollment, index) => (
                <li key={enrollment.id} className="relative pb-5 last:pb-1">
                  <span
                    className={cn(
                      "absolute -left-[26px] top-1 size-3 rounded-full border-2 border-card",
                      enrollment.status === "active" ? "bg-primary" : "bg-border",
                    )}
                    aria-hidden
                  />
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="min-w-0">
                      <p className="flex items-center gap-1.5 font-medium">
                        <GraduationCap className="size-4 shrink-0 text-muted-foreground" />
                        {enrollment.grade_level?.name}
                        {enrollment.section_name
                          ? ` — ${enrollment.section_name}`
                          : ` — ${t("wizard.assignLater")}`}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {enrollment.academic_year_name}
                        {/* History spans schools (transfers) — name each row's own. */}
                        {enrollment.school_name
                          ? ` · ${enrollment.school_name}${enrollment.branch_name ? ` — ${enrollment.branch_name}` : ""}`
                          : ""}
                        {enrollment.homeroom_teacher
                          ? ` · ${t("detail.homeroomTeacher")}: ${enrollment.homeroom_teacher.name}`
                          : ""}
                        {enrollment.previous_school_name
                          ? ` · ${t("wizard.previousSchool")}: ${enrollment.previous_school_name}`
                          : ""}
                      </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-1.5">
                      {canEdit(enrollment) && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button
                              variant="ghost"
                              size="icon"
                              className="size-7 rounded-full text-muted-foreground hover:text-foreground"
                              aria-label={t("editEnrollment.title")}
                              onClick={() => setEditing(enrollment)}
                            >
                              <Pencil className="size-3.5" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>{t("editEnrollment.title")}</TooltipContent>
                        </Tooltip>
                      )}
                      {canOpenPerformance(enrollment) && (
                        <Button
                          variant="outline"
                          size="sm"
                          className="h-7 gap-1.5 rounded-full text-xs"
                          onClick={() => setPerformanceOf(enrollment)}
                        >
                          <ChartSpline className="size-3.5" />
                          {t("detail.performance.open")}
                        </Button>
                      )}
                      <Badge
                        variant="secondary"
                        className={cn(
                          index === 0 && enrollment.status === "active" && "bg-success/10 text-success",
                        )}
                      >
                        {enrollment.status_label}
                      </Badge>
                    </div>
                  </div>
                </li>
              ))}
            </ol>
          )}
        </CardContent>
      </Card>

      <EnrollmentResultsDialog
        enrollment={performanceOf}
        open={performanceOf !== null}
        onOpenChange={(open) => !open && setPerformanceOf(null)}
      />

      <EditEnrollmentSheet
        enrollment={editing}
        open={editing !== null}
        onOpenChange={(open) => !open && setEditing(null)}
        onSaved={() => onChanged?.()}
      />
    </div>
  )
}
