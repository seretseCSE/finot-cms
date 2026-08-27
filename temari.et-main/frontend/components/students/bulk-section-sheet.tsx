"use client"

import { ArrowRightLeft, UserRoundMinus } from "lucide-react"
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, Section, Student } from "@/lib/types"
import { cn } from "@/lib/utils"

interface SkippedRow {
  student_id: number
  name: string | null
  reason: "not_enrolled" | "grade_mismatch"
}

interface BulkSectionSheetProps {
  students: Student[]
  academicYears: AcademicYear[]
  sections: Section[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onDone: () => void
}

/**
 * Bulk assign/move a hand-picked set of students to one section — or return
 * them to the unassigned pool. Mismatched students are skipped server-side
 * and reported, so one stray row never blocks the batch.
 */
export function BulkSectionSheet({
  students,
  academicYears,
  sections,
  open,
  onOpenChange,
  onDone,
}: BulkSectionSheetProps) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")

  const [mode, setMode] = useState<"assign" | "unassign">("assign")
  const [yearId, setYearId] = useState("")
  const [gradeId, setGradeId] = useState("")
  const [sectionId, setSectionId] = useState("")
  const [working, setWorking] = useState(false)

  // Default to the operating year each time the sheet opens.
  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset form on open
    setMode("assign")
    setGradeId("")
    setSectionId("")
    const active = academicYears.find((y) => y.is_current) ?? academicYears[0]
    setYearId(active ? String(active.id) : "")
  }, [open, academicYears])

  const year = academicYears.find((y) => String(y.id) === yearId) ?? null

  // The year names the branch — sections cascade from it (school-wide lists
  // carry every branch's sections; a branch workspace is already scoped).
  const branchSections = useMemo(
    () => (year ? sections.filter((s) => s.branch_id === year.branch_id) : []),
    [sections, year],
  )

  const gradeOptions = useMemo(() => {
    const byId = new Map<number, string>()
    for (const section of branchSections) {
      if (section.grade_level) byId.set(section.grade_level.id, section.grade_level.name)
    }
    return [...byId.entries()].map(([id, name]) => ({ id, name }))
  }, [branchSections])

  const gradeSections = branchSections.filter(
    (s) => gradeId !== "" && String(s.grade_level_id) === gradeId,
  )

  const canSubmit =
    yearId !== "" && (mode === "unassign" || sectionId !== "") && students.length > 0

  async function submit() {
    if (!canSubmit) return
    setWorking(true)
    try {
      const res = await apiFetch<{
        meta: { updated: number; skipped: SkippedRow[] }
      }>("/section-assignments/students", {
        method: "POST",
        body: {
          academic_year_id: Number(yearId),
          student_ids: students.map((s) => s.id),
          section_id: mode === "assign" ? Number(sectionId) : null,
        },
      })

      const { updated, skipped } = res.meta
      toast.success(
        mode === "assign"
          ? t("bulkSection.assigned", { count: updated })
          : t("bulkSection.unassigned", { count: updated }),
      )
      if (skipped.length > 0) {
        const names = skipped
          .map((row) => row.name)
          .filter((n): n is string => n !== null)
          .slice(0, 3)
          .join(", ")
        toast.warning(t("bulkSection.skipped", { count: skipped.length }), {
          description: names || undefined,
        })
      }
      onOpenChange(false)
      onDone()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("bulkSection.title")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          {/* Who is moving — first names as a compact roll call. */}
          <div className="rounded-2xl border px-4 py-3">
            <p className="text-sm font-medium">
              {t("bulkSection.selectedCount", { count: students.length })}
            </p>
            <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">
              {students
                .slice(0, 6)
                .map((s) => s.full_name)
                .join(" · ")}
              {students.length > 6 ? ` +${students.length - 6}` : ""}
            </p>
          </div>

          {/* Assign vs back-to-pool. */}
          <div className="grid grid-cols-2 gap-0 overflow-hidden rounded-xl border">
            {(
              [
                { key: "assign", icon: ArrowRightLeft, label: t("bulkSection.modeAssign") },
                { key: "unassign", icon: UserRoundMinus, label: t("bulkSection.modeUnassign") },
              ] as const
            ).map(({ key, icon: Icon, label }) => (
              <button
                key={key}
                type="button"
                onClick={() => setMode(key)}
                aria-pressed={mode === key}
                className={cn(
                  "flex min-h-11 items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors",
                  mode === key
                    ? "bg-primary text-primary-foreground"
                    : "bg-card text-muted-foreground",
                )}
              >
                <Icon className="size-4" />
                {label}
              </button>
            ))}
          </div>

          <div className="space-y-2">
            <Label>{t("enroll.academicYear")}</Label>
            <Select
              value={yearId}
              onValueChange={(v) => {
                setYearId(v)
                setGradeId("")
                setSectionId("")
              }}
            >
              <SelectTrigger className="w-full">
                <SelectValue placeholder={t("enroll.selectYear")} />
              </SelectTrigger>
              <SelectContent>
                {academicYears.map((y) => (
                  <SelectItem key={y.id} value={String(y.id)}>
                    {y.branch_name ? `${y.name} — ${y.branch_name}` : y.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {mode === "assign" && (
            <>
              <div className="space-y-2">
                <Label>{t("wizard.gradeLevel")}</Label>
                <Select
                  value={gradeId}
                  onValueChange={(v) => {
                    setGradeId(v)
                    setSectionId("")
                  }}
                  disabled={yearId === ""}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("wizard.selectGrade")} />
                  </SelectTrigger>
                  <SelectContent>
                    {gradeOptions.map((grade) => (
                      <SelectItem key={grade.id} value={String(grade.id)}>
                        {grade.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label>{t("columns.section")}</Label>
                <Select value={sectionId} onValueChange={setSectionId} disabled={gradeId === ""}>
                  <SelectTrigger className="w-full">
                    <SelectValue
                      placeholder={gradeId ? t("bulkSection.selectSection") : t("wizard.gradeFirst")}
                    />
                  </SelectTrigger>
                  <SelectContent>
                    {gradeSections.map((section) => (
                      <SelectItem key={section.id} value={String(section.id)}>
                        {section.name}
                        {section.capacity !== null
                          ? ` · ${t("bulkSection.capacity", { count: section.capacity })}`
                          : ""}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-muted-foreground text-xs">{t("bulkSection.mismatchHint")}</p>
              </div>
            </>
          )}
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
            disabled={working}
          >
            {tc("actions.cancel")}
          </Button>
          <Button type="button" className="h-11 flex-1" onClick={submit} loading={working} disabled={!canSubmit}>
            {mode === "assign"
                ? t("bulkSection.modeAssign")
                : t("bulkSection.modeUnassign")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
