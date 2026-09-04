"use client"

import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useTranslation } from "@/lib/i18n"
import type { Paginated, Section, StudentEnrollment } from "@/lib/types"

interface Props {
  enrollment: StudentEnrollment | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

/**
 * Fix a mistaken grade (or section) on a LIVE enrollment — edits the row in
 * place via PATCH /enrollments/{id}, so the year, fee trail and IDs survive.
 * Changing the grade clears a now-mismatched section until one is picked.
 */
export function EditEnrollmentSheet({ enrollment, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { grades } = useGradeLevels({ enabled: open })

  const [gradeId, setGradeId] = useState("")
  const [sectionId, setSectionId] = useState("")
  const [sections, setSections] = useState<Section[]>([])
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Seed from the enrollment each time the sheet opens.
  useEffect(() => {
    if (!open || !enrollment) return
    /* eslint-disable react-hooks/set-state-in-effect -- reset the form on open */
    setGradeId(String(enrollment.grade_level_id))
    setSectionId(enrollment.section_id ? String(enrollment.section_id) : "")
    setError(null)
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [open, enrollment])

  // Sections for the enrollment's own year + branch — refetched per grade.
  useEffect(() => {
    if (!open || !enrollment || !gradeId) return
    let cancelled = false
    const params = new URLSearchParams({
      academic_year_id: String(enrollment.academic_year_id),
      grade_level_id: gradeId,
      per_page: "100",
    })
    if (enrollment.branch_id) params.set("branch_id", String(enrollment.branch_id))
    apiFetch<Paginated<Section>>(`/sections?${params}`)
      .then((res) => {
        if (!cancelled) setSections(res.data)
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [open, enrollment, gradeId])

  const gradeChanged = enrollment ? gradeId !== String(enrollment.grade_level_id) : false
  const gradeSections = useMemo(
    () =>
      sections.filter(
        (section) => String(section.grade_level_id ?? section.grade_level?.id ?? "") === gradeId,
      ),
    [sections, gradeId],
  )

  async function save() {
    if (!enrollment || !gradeId) return
    setSaving(true)
    setError(null)
    try {
      await apiFetch(`/enrollments/${enrollment.id}`, {
        method: "PATCH",
        body: {
          grade_level_id: Number(gradeId),
          section_id: sectionId ? Number(sectionId) : null,
        },
      })
      toast.success(t("editEnrollment.saved"))
      onSaved()
      onOpenChange(false)
    } catch (err) {
      if (err instanceof ApiError) {
        const first = Object.values(err.errors)[0]?.[0]
        setError(first ?? err.message)
      } else {
        setError(tc("errors.generic"))
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("editEnrollment.title")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          {/* The year is the anchor — never editable here. */}
          <div className="space-y-1.5">
            <Label>{t("enroll.academicYear")}</Label>
            <p className="rounded-lg border bg-muted/40 px-3 py-2.5 text-sm font-medium">
              {enrollment?.academic_year_name ?? "—"}
            </p>
          </div>

          <div className="space-y-1.5">
            <Label>{t("wizard.gradeLevel")}</Label>
            <Select
              value={gradeId}
              onValueChange={(value) => {
                setGradeId(value)
                setSectionId("")
              }}
            >
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t("wizard.selectGrade")} />
              </SelectTrigger>
              <SelectContent emptyNotice={tc("emptySelect.grades")}>
                {grades.map((grade) => (
                  <SelectItem key={grade.id} value={String(grade.id)}>
                    {grade.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label>{t("wizard.sectionOptional")}</Label>
            <Select value={sectionId} onValueChange={setSectionId} disabled={!gradeId}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t("wizard.assignLater")} />
              </SelectTrigger>
              <SelectContent emptyNotice={tc("emptySelect.sections")}>
                {gradeSections.map((section) => (
                  <SelectItem key={section.id} value={String(section.id)}>
                    {section.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {gradeChanged && !sectionId ? (
              <p className="text-xs text-muted-foreground">{t("editEnrollment.sectionCleared")}</p>
            ) : null}
          </div>

          {error ? <p className="text-sm font-medium text-destructive">{error}</p> : null}
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.cancel")}
          </Button>
          <Button type="button" className="h-11 flex-1" loading={saving} disabled={!gradeId} onClick={save}>
            {t("editEnrollment.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
