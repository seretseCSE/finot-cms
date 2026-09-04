"use client"

import { X } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { useClassOptions } from "@/components/lms/shared"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
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
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { subjectAppliesToSort } from "@/lib/subjects"
import type { GradeLevel, QuestionBank, Subject } from "@/lib/types"

interface Props {
  bank: QuestionBank | null
  platform?: boolean
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

/**
 * Create/edit a question bank (school, branch or platform scope). Banks are
 * organized grade → subject → topics: pick the grade first, which narrows the
 * subject list to what applies to it (or, for teachers, what they teach at
 * that grade); topics (chapters) are typed in and become the filing system
 * for questions.
 */
export function BankSheet({ bank, platform = false, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const permissions = useEffectivePermissions()
  const { classes } = useClassOptions()

  // Teachers (lms.manage_own without lms.manage) create banks only for the
  // subjects they actively teach this semester — mirrors the backend rule.
  const teacherOnly = !platform && !permissions.includes("lms.manage")

  const [name, setName] = useState("")
  const [description, setDescription] = useState("")
  const [subjectId, setSubjectId] = useState<string>("")
  const [gradeLevelId, setGradeLevelId] = useState<string>("")
  const [topics, setTopics] = useState<string[]>([])
  const [topicDraft, setTopicDraft] = useState("")
  const [schoolWide, setSchoolWide] = useState(false)
  const [subjects, setSubjects] = useState<Subject[]>([])
  const [grades, setGrades] = useState<GradeLevel[]>([])
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  // Grades a teacher actually teaches (from their class list), platform/staff pick from the full seed list.
  const teacherGradeIds = useMemo(() => {
    const names = new Set(classes.map((c) => c.section.grade_level).filter(Boolean))
    return new Set(grades.filter((g) => names.has(g.name)).map((g) => g.id))
  }, [classes, grades])

  const gradeChoices = teacherOnly ? grades.filter((g) => teacherGradeIds.has(g.id)) : grades

  // Subjects available for a given grade id — teachers see only what they teach at that grade,
  // everyone else sees subjects whose grade set (grade_sorts; empty = all) covers it.
  const subjectChoicesFor = useMemo(() => {
    return (gradeId: string): { id: number; name: string }[] => {
      const grade = grades.find((g) => String(g.id) === gradeId) ?? null
      if (grade === null) return []
      if (teacherOnly) {
        const seen = new Map<number, { id: number; name: string }>()
        for (const option of classes) {
          if (option.section.grade_level !== grade.name) continue
          if (option.subject?.id && !seen.has(option.subject.id)) {
            seen.set(option.subject.id, { id: option.subject.id, name: option.subject.name ?? "" })
          }
        }
        return [...seen.values()]
      }
      return subjects.filter((subject) => subjectAppliesToSort(subject, grade.sort_order))
    }
  }, [teacherOnly, grades, classes, subjects])

  const subjectChoices = subjectChoicesFor(gradeLevelId)

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync sheet with the edited row */
    setName(bank?.name ?? "")
    setDescription(bank?.description ?? "")
    setSubjectId(bank?.subject_id ? String(bank.subject_id) : "")
    setGradeLevelId(bank?.grade_level_id ? String(bank.grade_level_id) : "")
    setTopics(bank?.topics ? [...bank.topics] : [])
    setTopicDraft("")
    setSchoolWide(bank !== null && bank.branch_id === null && !platform)
    setErrors({})
    /* eslint-enable react-hooks/set-state-in-effect */

    let cancelled = false
    apiFetch<{ data: Subject[] }>(platform ? "/catalogs/subjects?per_page=100" : "/subjects?per_page=100")
      .then((res) => !cancelled && setSubjects(res.data))
      .catch(() => !cancelled && setSubjects([]))
    // Platform banks may target any grade; branch banks follow the offering.
    apiFetch<{ data: GradeLevel[] }>(platform ? "/grade-levels?all=1" : "/grade-levels")
      .then((res) => !cancelled && setGrades(res.data))
      .catch(() => !cancelled && setGrades([]))
    return () => {
      cancelled = true
    }
  }, [open, bank, platform])

  // Picking a new grade may invalidate the already-picked subject — drop it rather than save a mismatch.
  function selectGrade(value: string) {
    setGradeLevelId(value)
    if (subjectId !== "" && !subjectChoicesFor(value).some((subject) => String(subject.id) === subjectId)) {
      setSubjectId("")
    }
  }

  function addTopic() {
    const value = topicDraft.trim()
    if (value === "") return
    if (!topics.some((topic) => topic.toLowerCase() === value.toLowerCase())) {
      setTopics((prev) => [...prev, value])
    }
    setTopicDraft("")
  }

  async function save() {
    setSaving(true)
    setErrors({})
    try {
      await apiFetch(bank ? `/question-banks/${bank.id}` : "/question-banks", {
        method: bank ? "PUT" : "POST",
        body: {
          name,
          description: description || null,
          subject_id: subjectId ? Number(subjectId) : null,
          grade_level_id: gradeLevelId ? Number(gradeLevelId) : null,
          topics,
          ...(bank === null ? { platform, school_wide: schoolWide } : {}),
        },
      })
      toast.success(t("banks.saved"))
      onOpenChange(false)
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors)
        toast.error(error.message)
      } else {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{bank ? t("banks.edit") : t("banks.add")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          <div className="space-y-2">
            <Label>
              {t("banks.name")} <span className="text-destructive">*</span>
            </Label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
            {errors.name && <p className="text-destructive text-xs">{errors.name[0]}</p>}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>
                {t("banks.grade")} <span className="text-destructive">*</span>
              </Label>
              <Select value={gradeLevelId || undefined} onValueChange={selectGrade}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={tc("actions.select")} />
                </SelectTrigger>
                <SelectContent>
                  {gradeChoices.map((grade) => (
                    <SelectItem key={grade.id} value={String(grade.id)}>
                      {grade.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.grade_level_id && (
                <p className="text-destructive text-xs">{errors.grade_level_id[0]}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label>
                {t("banks.subject")} <span className="text-destructive">*</span>
              </Label>
              <Select
                value={subjectId || undefined}
                onValueChange={setSubjectId}
                disabled={gradeLevelId === ""}
              >
                <SelectTrigger className="w-full">
                  <SelectValue
                    placeholder={
                      gradeLevelId === ""
                        ? t("banks.pickGradeFirst")
                        : teacherOnly
                          ? t("banks.taughtSubjectPlaceholder")
                          : tc("actions.select")
                    }
                  />
                </SelectTrigger>
                <SelectContent>
                  {subjectChoices.map((subject) => (
                    <SelectItem key={subject.id} value={String(subject.id)}>
                      {subject.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.subject_id && <p className="text-destructive text-xs">{errors.subject_id[0]}</p>}
            </div>
          </div>
          {teacherOnly && (
            <p className="-mt-3 text-xs text-muted-foreground">{t("banks.taughtSubjectHint")}</p>
          )}

          <div className="space-y-2">
            <Label>{t("banks.topics")}</Label>
            {topics.length > 0 && (
              <div className="flex flex-wrap gap-1.5">
                {topics.map((topic) => (
                  <span
                    key={topic}
                    className="inline-flex items-center gap-1 rounded-full bg-primary/10 py-1 pl-3 pr-1.5 text-xs font-medium text-primary"
                  >
                    {topic}
                    <button
                      type="button"
                      aria-label={tc("actions.delete")}
                      className="flex size-4.5 items-center justify-center rounded-full hover:bg-primary/15"
                      onClick={() => setTopics((prev) => prev.filter((x) => x !== topic))}
                    >
                      <X className="size-3" />
                    </button>
                  </span>
                ))}
              </div>
            )}
            <Input
              value={topicDraft}
              onChange={(e) => setTopicDraft(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === ",") {
                  e.preventDefault()
                  addTopic()
                }
              }}
              onBlur={addTopic}
              placeholder={t("banks.topicsPlaceholder")}
            />
            <p className="text-xs text-muted-foreground">{t("banks.topicsHint")}</p>
          </div>

          <div className="space-y-2">
            <Label>{t("banks.description")}</Label>
            <Textarea rows={3} value={description} onChange={(e) => setDescription(e.target.value)} />
          </div>

          {!platform && !teacherOnly && bank === null && (
            <div className="flex items-center justify-between rounded-xl border px-3.5 py-3">
              <div className="min-w-0">
                <p className="text-sm font-medium">{t("banks.schoolWide")}</p>
                <p className="text-xs text-muted-foreground">{t("banks.schoolWideHint")}</p>
              </div>
              <Switch checked={schoolWide} onCheckedChange={setSchoolWide} />
            </div>
          )}
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
            disabled={saving}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            className="h-11 flex-1"
            onClick={save}
            loading={saving} disabled={name.trim() === "" || subjectId === "" || gradeLevelId === ""}
          >
            {tc("actions.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
