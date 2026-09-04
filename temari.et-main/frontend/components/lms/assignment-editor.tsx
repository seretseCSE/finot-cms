"use client"

import {
  Camera,
  Check,
  ChevronDown,
  ClipboardList,
  FileText,
  Link2,
  Loader2,
  Mic,
  Paperclip,
  Plus,
  Trash2,
  Users,
  X,
} from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import {
  PendingFileList,
  renamedFile,
  toPendingFiles,
  type PendingFile,
} from "@/components/lms/pending-files"
import { DateTimeField, useClassOptions } from "@/components/lms/shared"
import { AttachmentTile } from "@/components/ui/attachment"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useMediaPreview } from "@/components/ui/media-preview"
import { RichTextEditor } from "@/components/ui/rich-text"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { addisToday } from "@/lib/dates"
import { COURSEWORK_ACCEPT, COURSEWORK_MAX_BYTES } from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import type { AssignmentKind, LmsAssignment, Quiz } from "@/lib/types"
import { cn } from "@/lib/utils"

interface GradebookSlot {
  id: number
  name: string
  max_score?: number | string
}

interface ClassStudent {
  id: number
  full_name: string
  public_id?: string | null
}

interface RubricRow {
  criterion: string
  max_points: string
}

interface Props {
  assignment: LmsAssignment | null
  defaultSubjectAssignmentId?: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

const KINDS: AssignmentKind[] = ["standard", "quiz"]

type Status = "draft" | "published" | "closed"

/**
 * The full-screen assignment studio (ADR-016), matching the question/exam
 * editors. Center: who gets the work (grade → subject → section, then whole
 * class or picked students), the work itself (title + rich WYSIWYG
 * instructions + reference files), and how it's turned in and marked. Right
 * rail: status, schedule, late/resubmission policy, gradebook link. Saving
 * is a split button: publish, or keep as a draft. On mobile it becomes a
 * single app-like scroll.
 */
export function AssignmentEditor({
  assignment,
  defaultSubjectAssignmentId = null,
  open,
  onOpenChange,
  onSaved,
}: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { classes } = useClassOptions()
  const fileInput = useRef<HTMLInputElement>(null)
  const { openPreview, previewDialog } = useMediaPreview()

  // Picked or dropped, reference files keep their rename row before upload.
  const fileDrop = useFileDrop({
    multiple: true,
    accept: COURSEWORK_ACCEPT,
    maxSize: COURSEWORK_MAX_BYTES,
    onFiles: (picked) => setFiles((prev) => [...prev, ...toPendingFiles(picked)]),
  })

  const [subjectAssignmentId, setSubjectAssignmentId] = useState<number | null>(null)
  const [gradeName, setGradeName] = useState("")
  const [subjectKey, setSubjectKey] = useState("")
  const [kind, setKind] = useState<AssignmentKind>("standard")
  const [quizId, setQuizId] = useState<string>("")
  const [title, setTitle] = useState("")
  const [instructions, setInstructions] = useState("")
  const [types, setTypes] = useState<string[]>(["text"])
  const [useRubric, setUseRubric] = useState(false)
  const [rubric, setRubric] = useState<RubricRow[]>([{ criterion: "", max_points: "5" }])
  const [maxScore, setMaxScore] = useState("10")
  const [availableFrom, setAvailableFrom] = useState<string | null>(null)
  const [dueAt, setDueAt] = useState<string | null>(null)
  const [latePolicy, setLatePolicy] = useState("accept")
  const [latePenalty, setLatePenalty] = useState("")
  const [resubmission, setResubmission] = useState("until_graded")
  const [targetAll, setTargetAll] = useState(true)
  const [targetIds, setTargetIds] = useState<number[]>([])
  const [files, setFiles] = useState<PendingFile[]>([])
  const [removedPaths, setRemovedPaths] = useState<string[]>([])
  const [slots, setSlots] = useState<GradebookSlot[]>([])
  const [classQuizzes, setClassQuizzes] = useState<Quiz[]>([])
  const [students, setStudents] = useState<ClassStudent[]>([])
  const [assessmentId, setAssessmentId] = useState<string>("")
  const [status, setStatus] = useState<Status>("published")
  const [imgUploading, setImgUploading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  // ── grade → subject → section cascade over the caller's classes ──
  const gradeNames = useMemo(
    () => [...new Set(classes.map((c) => c.section.grade_level).filter((g): g is string => Boolean(g)))],
    [classes],
  )
  const subjectChoices = useMemo(() => {
    const seen = new Map<string, string>()
    for (const option of classes) {
      if (option.section.grade_level === gradeName && option.subject.id) {
        seen.set(String(option.subject.id), option.subject.name ?? "")
      }
    }
    return [...seen.entries()].map(([id, name]) => ({ id, name }))
  }, [classes, gradeName])
  const sectionChoices = useMemo(
    () =>
      classes.filter(
        (option) =>
          option.section.grade_level === gradeName && String(option.subject.id) === subjectKey,
      ),
    [classes, gradeName, subjectKey],
  )

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync editor with the edited row */
    setErrors({})
    setSubjectAssignmentId(assignment?.subject_assignment_id ?? defaultSubjectAssignmentId)
    setGradeName("")
    setSubjectKey("")
    setKind(assignment?.kind ?? "standard")
    setQuizId(assignment?.quiz_id ? String(assignment.quiz_id) : "")
    setTitle(assignment?.title ?? "")
    setInstructions(assignment?.instructions ?? "")
    setTypes(assignment?.submission_types?.length ? assignment.submission_types : ["text"])
    setUseRubric((assignment?.rubric?.length ?? 0) > 0)
    setRubric(
      assignment?.rubric?.length
        ? assignment.rubric.map((row) => ({
            criterion: row.criterion,
            max_points: String(row.max_points),
          }))
        : [{ criterion: "", max_points: "5" }],
    )
    setMaxScore(assignment !== null && assignment.max_score !== null ? String(assignment.max_score) : "10")
    setAvailableFrom(assignment?.available_from ?? null)
    setDueAt(assignment?.due_at ?? null)
    setLatePolicy(assignment?.late_policy ?? "accept")
    setLatePenalty(
      assignment?.late_penalty_percent != null ? String(assignment.late_penalty_percent) : "",
    )
    setResubmission(assignment?.resubmission_policy ?? "until_graded")
    setTargetAll(!assignment?.target_student_ids?.length)
    setTargetIds(assignment?.target_student_ids ?? [])
    setFiles([])
    setRemovedPaths([])
    setAssessmentId(assignment?.assessment_id ? String(assignment.assessment_id) : "")
    setStatus(assignment?.status ?? "published")
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [open, assignment, defaultSubjectAssignmentId])

  // When a default class is preset (create from a class page), resolve the
  // grade/subject pickers so the cascade shows the right selection.
  useEffect(() => {
    if (!open || assignment !== null || subjectAssignmentId === null || classes.length === 0 || gradeName !== "") return
    const anchor = classes.find((option) => option.subject_assignment_id === subjectAssignmentId)
    if (anchor) {
      /* eslint-disable react-hooks/set-state-in-effect -- one-shot resolve from loaded data */
      setGradeName(anchor.section.grade_level ?? "")
      setSubjectKey(String(anchor.subject.id ?? ""))
      /* eslint-enable react-hooks/set-state-in-effect */
    }
  }, [open, assignment, subjectAssignmentId, classes, gradeName])

  // Per-class lookups: gradebook slots, published quizzes, the roster.
  useEffect(() => {
    if (!open || !subjectAssignmentId) {
      /* eslint-disable react-hooks/set-state-in-effect -- reset when the class clears */
      setSlots([])
      setClassQuizzes([])
      setStudents([])
      /* eslint-enable react-hooks/set-state-in-effect */
      return
    }
    let cancelled = false
    apiFetch<{ data: GradebookSlot[] }>(`/subject-assignments/${subjectAssignmentId}/assessments`)
      .then((res) => !cancelled && setSlots(res.data))
      .catch(() => !cancelled && setSlots([]))
    apiFetch<{ data: Quiz[] }>(`/quizzes?subject_assignment_id=${subjectAssignmentId}&per_page=100`)
      .then((res) => !cancelled && setClassQuizzes(res.data))
      .catch(() => !cancelled && setClassQuizzes([]))
    apiFetch<{ data: ClassStudent[] }>(`/subject-assignments/${subjectAssignmentId}/students`)
      .then((res) => !cancelled && setStudents(res.data))
      .catch(() => !cancelled && setStudents([]))
    return () => {
      cancelled = true
    }
  }, [open, subjectAssignmentId])

  async function uploadImage(file: File) {
    const form = new FormData()
    form.append("file", file)
    const res = await apiFetch<{ data: { url: string; path: string } }>("/lms/uploads", {
      method: "POST",
      body: form,
    })
    return res.data
  }

  function toggleType(type: string, checked: boolean) {
    setTypes((prev) => (checked ? [...prev, type] : prev.filter((entry) => entry !== type)))
  }

  const rubricTotal = rubric.reduce((sum, row) => sum + Number(row.max_points || 0), 0)

  // The deadline has to land strictly after the start. The due picker's `min`
  // only guards the DAY, so a same-day earlier time is caught here — both
  // strings are naive local times, so `Date` compares them like for like.
  const dueBeforeStart =
    availableFrom !== null && dueAt !== null && new Date(dueAt) <= new Date(availableFrom)

  function validate(): boolean {
    const found: Record<string, string[]> = {}
    if (title.trim() === "") found.title = [tc("validation.required")]
    if (subjectAssignmentId === null) found.subject_assignment_id = [t("exams.pickSections")]
    if (kind === "standard" && types.length === 0) found.submission_types = [t("assignments.pickModes")]
    if (kind === "quiz" && quizId === "") found.quiz_id = [tc("validation.required")]
    if (!targetAll && targetIds.length === 0) found.target_student_ids = [t("assignments.pickStudents")]
    if (dueBeforeStart) found.due_at = [t("assignments.dueAfterStart")]
    setErrors(found)
    return Object.keys(found).length === 0
  }

  async function save(saveStatus?: Status) {
    const finalStatus = saveStatus ?? status
    if (!validate()) {
      toast.error(t("questions.errors.fix"))
      return
    }
    setSaving(true)
    try {
      const form = new FormData()
      if (assignment === null) form.append("subject_assignment_id", String(subjectAssignmentId))
      form.append("kind", kind)
      if (kind === "quiz" && quizId) form.append("quiz_id", quizId)
      form.append("title", title)
      if (instructions) form.append("instructions", instructions)
      if (kind === "standard") {
        types.forEach((type, index) => form.append(`submission_types[${index}]`, type))
      }
      if (useRubric && kind === "standard") {
        rubric
          .filter((row) => row.criterion.trim() !== "")
          .forEach((row, index) => {
            form.append(`rubric[${index}][criterion]`, row.criterion)
            form.append(`rubric[${index}][max_points]`, row.max_points || "0")
          })
      } else if (kind === "standard" && maxScore) {
        form.append("max_score", maxScore)
      }
      // Always posted: an empty string clears the list back to "whole class".
      if (targetAll || targetIds.length === 0) {
        form.append("target_student_ids", "")
      } else {
        targetIds.forEach((id, index) => form.append(`target_student_ids[${index}]`, String(id)))
      }
      if (availableFrom) form.append("available_from", availableFrom)
      if (dueAt) form.append("due_at", dueAt)
      form.append("late_policy", latePolicy)
      if (latePenalty) form.append("late_penalty_percent", latePenalty)
      form.append("resubmission_policy", resubmission)
      if (assessmentId) form.append("assessment_id", assessmentId)
      files.forEach((entry, index) => form.append(`attachments[${index}]`, renamedFile(entry)))
      removedPaths.forEach((path, index) => form.append(`removed_paths[${index}]`, path))
      form.append("status", finalStatus)
      if (assignment !== null) form.append("_method", "PUT")

      await apiFetch(assignment ? `/assignments/${assignment.id}` : "/assignments", {
        method: "POST",
        body: form,
      })
      toast.success(finalStatus === "draft" ? t("assignments.savedDraft") : t("assignments.saved"))
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

  const existingFiles = (assignment?.attachments ?? []).filter(
    (file) => !removedPaths.includes(String(file.path ?? "")),
  )

  const editingClassLabel = assignment
    ? [assignment.subject_name, assignment.grade_level_name, assignment.section_name]
        .filter(Boolean)
        .join(" · ")
    : null

  const saveLabel =
    status === "draft"
      ? t("questions.saveDraft")
      : status === "closed"
        ? tc("actions.save")
        : t("questions.saveAndPublish")

  // Status + schedule are the assignment's defining settings — on mobile
  // (where the settings rail stacks BELOW the canvas) they surface as a
  // setup card first. One controlled tree, mounted twice: above the canvas
  // (mobile) and at the top of the settings rail (desktop).
  const scheduleSetup = (
    <>
      <div className="space-y-2">
        <Label>{t("questions.status")}</Label>
        <Select value={status} onValueChange={(v) => setStatus(v as Status)}>
          <SelectTrigger className="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="published">{t("assignments.statuses.published")}</SelectItem>
            <SelectItem value="draft">{t("assignments.statuses.draft")}</SelectItem>
            {assignment !== null && (
              <SelectItem value="closed">{t("assignments.statuses.closed")}</SelectItem>
            )}
          </SelectContent>
        </Select>
        <p className="text-xs text-muted-foreground">{t("assignments.statusHint")}</p>
      </div>

      <div className="space-y-4 border-t pt-4">
        <DateTimeField
          label={t("assignments.availableFrom")}
          value={availableFrom}
          onChange={setAvailableFrom}
          min={addisToday()}
        />
        <DateTimeField
          label={t("assignments.dueAt")}
          value={dueAt}
          onChange={setDueAt}
          min={availableFrom?.slice(0, 10) || addisToday()}
        />
        {(dueBeforeStart || errors.due_at) && (
          <p className="text-xs text-destructive">
            {dueBeforeStart ? t("assignments.dueAfterStart") : errors.due_at?.[0]}
          </p>
        )}
      </div>
    </>
  )

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/20 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-open:animate-in data-open:fade-in-0 data-open:zoom-in-[0.99] data-closed:animate-out data-closed:fade-out-0"
          onEscapeKeyDown={(e) => e.preventDefault()}
          onPointerDownOutside={(e) => e.preventDefault()}
          onInteractOutside={(e) => e.preventDefault()}
        >
          {/* ── Top bar ─────────────────────────────────────────────── */}
          <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-3 md:px-5">
            <Button
              variant="ghost"
              size="icon"
              className="text-muted-foreground"
              onClick={() => onOpenChange(false)}
              aria-label={tc("actions.close")}
            >
              <X className="size-5" />
            </Button>
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="hidden size-8 items-center justify-center rounded-lg bg-primary/10 text-primary sm:flex">
                <ClipboardList className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {assignment ? t("assignments.edit") : t("assignments.add")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {(editingClassLabel ?? title) ?? t("assignments.title")}
                </p>
              </div>
            </div>

            <div className="ml-auto flex items-center">
              <Button className="h-10 rounded-r-none px-4 md:px-5" disabled={saving || imgUploading} onClick={() => save()}>
                {saving && <Loader2 className="size-4 animate-spin" />}
                {saveLabel}
              </Button>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    className="h-10 rounded-l-none border-l border-primary-foreground/25 px-2"
                    loading={saving || imgUploading}
                    aria-label={t("questions.saveOptions")}
                  >
                    <ChevronDown className="size-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-52">
                  <DropdownMenuItem onClick={() => save("published")}>
                    <Check className="size-4" /> {t("questions.saveAndPublish")}
                  </DropdownMenuItem>
                  <DropdownMenuItem onClick={() => save("draft")}>
                    {t("questions.saveDraft")}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </header>

          {/* ── Body: canvas + settings rail ─────────────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
                {/* Mobile setup card — the desktop rail carries the same
                    controls on md+. */}
                <section className="space-y-5 rounded-2xl border bg-card p-4 shadow-xs md:hidden">
                  {scheduleSetup}
                </section>

                {/* Who gets this */}
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <div className="mb-3 flex items-center gap-2">
                    <Users className="size-4 text-muted-foreground" />
                    <Label>{t("exams.audience")}</Label>
                  </div>

                  {assignment !== null ? (
                    <div className="flex flex-wrap items-center gap-2">
                      <Badge variant="secondary" className="h-8 rounded-full px-3 text-sm">
                        {editingClassLabel}
                      </Badge>
                      <p className="text-xs text-muted-foreground">{t("assignments.classLocked")}</p>
                    </div>
                  ) : (
                    <>
                      <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                          <Label className="text-muted-foreground">{t("banks.grade")}</Label>
                          <Select
                            value={gradeName || undefined}
                            onValueChange={(v) => {
                              setGradeName(v)
                              setSubjectKey("")
                              setSubjectAssignmentId(null)
                            }}
                          >
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder={tc("actions.select")} />
                            </SelectTrigger>
                            <SelectContent>
                              {gradeNames.map((grade) => (
                                <SelectItem key={grade} value={grade}>
                                  {grade}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                        <div className="space-y-2">
                          <Label className="text-muted-foreground">{t("exams.subject")}</Label>
                          <Select
                            value={subjectKey || undefined}
                            onValueChange={(v) => {
                              setSubjectKey(v)
                              setSubjectAssignmentId(null)
                            }}
                            disabled={gradeName === ""}
                          >
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder={tc("actions.select")} />
                            </SelectTrigger>
                            <SelectContent>
                              {subjectChoices.map((subject) => (
                                <SelectItem key={subject.id} value={subject.id}>
                                  {subject.name}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                      </div>
                      {subjectKey !== "" && (
                        <div className="mt-4 space-y-2">
                          <Label className="text-muted-foreground">{t("assignments.section")}</Label>
                          <div className="flex flex-wrap gap-2">
                            {sectionChoices.map((option) => {
                              const selected = subjectAssignmentId === option.subject_assignment_id
                              return (
                                <button
                                  key={option.subject_assignment_id}
                                  type="button"
                                  onClick={() => setSubjectAssignmentId(option.subject_assignment_id)}
                                  className={cn(
                                    "inline-flex h-10 items-center gap-1.5 rounded-full border px-4 text-sm font-medium transition-colors",
                                    selected
                                      ? "border-primary/40 bg-primary/10 text-primary"
                                      : "hover:bg-muted/60",
                                  )}
                                >
                                  {selected && <Check className="size-3.5" />}
                                  {option.section.name}
                                </button>
                              )
                            })}
                            {sectionChoices.length === 0 && (
                              <p className="text-sm text-muted-foreground">{t("exams.noSections")}</p>
                            )}
                          </div>
                        </div>
                      )}
                      {errors.subject_assignment_id && (
                        <p className="mt-2 text-xs text-destructive">{errors.subject_assignment_id[0]}</p>
                      )}
                    </>
                  )}

                  {/* whole class vs picked students */}
                  {subjectAssignmentId !== null && (
                    <div className="mt-4 space-y-2 border-t pt-4">
                      <label className="flex cursor-pointer items-center justify-between gap-3">
                        <span className="min-w-0">
                          <span className="block text-sm font-medium">{t("assignments.wholeClass")}</span>
                          <span className="block text-xs text-muted-foreground">
                            {t("assignments.wholeClassHint")}
                          </span>
                        </span>
                        <Checkbox
                          checked={targetAll}
                          onCheckedChange={(checked) => setTargetAll(checked === true)}
                        />
                      </label>
                      {!targetAll && (
                        <div className="max-h-56 space-y-0.5 overflow-y-auto rounded-xl border p-2">
                          {students.length === 0 ? (
                            <p className="px-2 py-3 text-sm text-muted-foreground">{tc("states.empty")}</p>
                          ) : (
                            students.map((student) => (
                              <label
                                key={student.id}
                                className="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-accent"
                              >
                                <Checkbox
                                  checked={targetIds.includes(student.id)}
                                  onCheckedChange={(checked) =>
                                    setTargetIds((prev) =>
                                      checked === true
                                        ? [...prev, student.id]
                                        : prev.filter((id) => id !== student.id),
                                    )
                                  }
                                />
                                <span className="min-w-0 flex-1 truncate text-sm">{student.full_name}</span>
                                {student.public_id && (
                                  <span className="text-xs text-muted-foreground">{student.public_id}</span>
                                )}
                              </label>
                            ))
                          )}
                        </div>
                      )}
                      {!targetAll && targetIds.length > 0 && (
                        <p className="text-xs text-muted-foreground">
                          {t("assignments.studentsPicked", { count: targetIds.length })}
                        </p>
                      )}
                      {errors.target_student_ids && (
                        <p className="text-xs text-destructive">{errors.target_student_ids[0]}</p>
                      )}
                    </div>
                  )}
                </section>

                {/* The work */}
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <div className="mb-3 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                      <FileText className="size-4 text-muted-foreground" />
                      <Label>{t("assignments.work")}</Label>
                    </div>
                    <div className="grid grid-cols-2 gap-1 rounded-full border p-0.5">
                      {KINDS.map((option) => (
                        <button
                          key={option}
                          type="button"
                          onClick={() => setKind(option)}
                          className={cn(
                            "rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors",
                            kind === option
                              ? "bg-primary text-primary-foreground"
                              : "text-muted-foreground hover:text-foreground",
                          )}
                        >
                          {t(`assignments.kinds.${option}`)}
                        </button>
                      ))}
                    </div>
                  </div>
                  <p className="mb-4 text-xs text-muted-foreground">{t(`assignments.kindHints.${kind}`)}</p>

                  <div className="space-y-4">
                    {kind === "quiz" && (
                      <div className="space-y-2">
                        <Label>
                          {t("assignments.linkedQuiz")} <span className="text-destructive">*</span>
                        </Label>
                        <Select value={quizId || undefined} onValueChange={setQuizId}>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("assignments.linkedQuizPlaceholder")} />
                          </SelectTrigger>
                          <SelectContent>
                            {classQuizzes.map((quiz) => (
                              <SelectItem key={quiz.id} value={String(quiz.id)}>
                                {quiz.title}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        {errors.quiz_id && <p className="text-xs text-destructive">{errors.quiz_id[0]}</p>}
                      </div>
                    )}

                    <div className="space-y-2">
                      <Label>
                        {t("assignments.assignmentTitle")} <span className="text-destructive">*</span>
                      </Label>
                      <Input
                        value={title}
                        onChange={(e) => setTitle(e.target.value)}
                        placeholder={t("assignments.titlePlaceholder")}
                      />
                      {errors.title && <p className="text-xs text-destructive">{errors.title[0]}</p>}
                    </div>

                    <div className="space-y-2">
                      <Label>{t("assignments.instructions")}</Label>
                      <RichTextEditor
                        value={instructions}
                        onChange={setInstructions}
                        placeholder={t("assignments.instructionsPlaceholder")}
                        onUploadingChange={setImgUploading}
                        onUploadImage={async (file) => {
                          const stored = await uploadImage(file)
                          return { url: stored.url, path: stored.path }
                        }}
                      />
                    </div>

                    {/* reference files */}
                    <div
                      {...fileDrop.dropProps}
                      className={cn("space-y-2 rounded-2xl", fileDrop.dragOver && DROP_ACTIVE)}
                    >
                      <Label>{t("assignments.attachments")}</Label>
                      <div className="space-y-1.5">
                        {existingFiles.map((file) => (
                          <AttachmentTile
                            key={String(file.path ?? file.name)}
                            file={file}
                            onPreview={() => openPreview(existingFiles, existingFiles.indexOf(file))}
                            onDelete={() => setRemovedPaths((prev) => [...prev, String(file.path ?? "")])}
                          />
                        ))}
                        <PendingFileList
                          items={files}
                          onRename={(index, name) =>
                            setFiles((prev) => prev.map((f, i) => (i === index ? { ...f, name } : f)))
                          }
                          onRemove={(index) => setFiles((prev) => prev.filter((_, i) => i !== index))}
                        />
                      </div>
                      <input
                        ref={fileInput}
                        type="file"
                        multiple
                        accept={COURSEWORK_ACCEPT}
                        className="hidden"
                        onChange={(e) => {
                          fileDrop.takeFiles(e.target.files)
                          e.target.value = ""
                        }}
                      />
                      <div className="flex flex-wrap items-center gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="h-9 rounded-full"
                          onClick={() => fileInput.current?.click()}
                        >
                          <Paperclip className="size-3.5" /> {t("assignments.attach")}
                        </Button>
                        <DropHint />
                      </div>
                    </div>
                  </div>
                </section>

                {/* Turn-in + marking (standard work only) */}
                {kind === "standard" && (
                  <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                    <Label className="mb-3 block">{t("assignments.submissionTypes")}</Label>
                    <div className="grid gap-2 sm:grid-cols-3">
                      {[
                        { value: "text", label: t("assignments.typeText"), icon: FileText },
                        { value: "file", label: t("assignments.typeFile"), icon: Paperclip },
                        { value: "photo", label: t("assignments.typePhoto"), icon: Camera },
                        { value: "audio", label: t("assignments.typeAudio"), icon: Mic },
                        { value: "link", label: t("assignments.typeLink"), icon: Link2 },
                      ].map((option) => (
                        <label
                          key={option.value}
                          className={cn(
                            "flex cursor-pointer items-center gap-2.5 rounded-xl border px-3.5 py-3 transition-colors",
                            types.includes(option.value) && "border-primary/40 bg-primary/5",
                          )}
                        >
                          <Checkbox
                            checked={types.includes(option.value)}
                            onCheckedChange={(checked) => toggleType(option.value, checked === true)}
                          />
                          <option.icon className="size-3.5 text-muted-foreground" />
                          <span className="text-sm">{option.label}</span>
                        </label>
                      ))}
                    </div>
                    {errors.submission_types && (
                      <p className="mt-2 text-xs text-destructive">{errors.submission_types[0]}</p>
                    )}

                    <div className="mt-5 space-y-3 border-t pt-4">
                      <label className="flex cursor-pointer items-center justify-between gap-3">
                        <span className="min-w-0">
                          <span className="block text-sm font-medium">{t("assignments.useRubric")}</span>
                          <span className="block text-xs text-muted-foreground">
                            {t("assignments.useRubricHint")}
                          </span>
                        </span>
                        <Checkbox
                          checked={useRubric}
                          onCheckedChange={(checked) => setUseRubric(checked === true)}
                        />
                      </label>

                      {useRubric ? (
                        <div className="space-y-2">
                          {rubric.map((row, index) => (
                            <div key={index} className="flex items-center gap-2">
                              <Input
                                value={row.criterion}
                                placeholder={t("assignments.rubricCriterion")}
                                onChange={(e) =>
                                  setRubric((prev) =>
                                    prev.map((r, i) => (i === index ? { ...r, criterion: e.target.value } : r)),
                                  )
                                }
                                className="flex-1"
                              />
                              <Input
                                type="number"
                                min="0.5"
                                step="0.5"
                                value={row.max_points}
                                onChange={(e) =>
                                  setRubric((prev) =>
                                    prev.map((r, i) => (i === index ? { ...r, max_points: e.target.value } : r)),
                                  )
                                }
                                className="no-spinner h-9 w-20 text-center"
                                aria-label={t("questions.points")}
                              />
                              {rubric.length > 1 && (
                                <Button
                                  type="button"
                                  variant="ghost"
                                  size="icon"
                                  className="size-8 text-muted-foreground"
                                  aria-label={tc("actions.delete")}
                                  onClick={() => setRubric((prev) => prev.filter((_, i) => i !== index))}
                                >
                                  <Trash2 className="size-3.5" />
                                </Button>
                              )}
                            </div>
                          ))}
                          <div className="flex items-center justify-between">
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => setRubric((prev) => [...prev, { criterion: "", max_points: "5" }])}
                            >
                              <Plus className="size-3.5" /> {t("assignments.rubricAdd")}
                            </Button>
                            <p className="text-xs text-muted-foreground">
                              {t("assignments.rubricTotal", { total: rubricTotal })}
                            </p>
                          </div>
                          {errors.rubric && <p className="text-xs text-destructive">{errors.rubric[0]}</p>}
                        </div>
                      ) : (
                        <div className="grid grid-cols-2 gap-3 sm:max-w-xs">
                          <div className="space-y-2">
                            <Label className="text-muted-foreground">{t("assignments.maxScore")}</Label>
                            <Input
                              type="number"
                              min="0"
                              className="no-spinner"
                              value={maxScore}
                              onChange={(e) => setMaxScore(e.target.value)}
                            />
                          </div>
                        </div>
                      )}
                    </div>
                  </section>
                )}
              </div>
            </main>

            {/* Settings rail */}
            <aside className="border-t bg-background md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-t-0">
              <div className="space-y-5 p-4 md:p-5">
                <div className="hidden space-y-5 md:block">{scheduleSetup}</div>

                <div className="space-y-4 border-t pt-4">
                  <div className="space-y-2">
                    <Label>{t("assignments.latePolicy")}</Label>
                    <Select value={latePolicy} onValueChange={setLatePolicy}>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="accept">{t("assignments.lateAccept")}</SelectItem>
                        <SelectItem value="reject">{t("assignments.lateReject")}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  {latePolicy === "accept" && (
                    <div className="space-y-2">
                      <Label>{t("assignments.latePenalty")}</Label>
                      <Input
                        type="number"
                        min="0"
                        max="100"
                        className="no-spinner"
                        value={latePenalty}
                        onChange={(e) => setLatePenalty(e.target.value)}
                        placeholder="0"
                      />
                    </div>
                  )}
                  {kind === "standard" && (
                    <div className="space-y-2">
                      <Label>{t("assignments.resubmission")}</Label>
                      <Select value={resubmission} onValueChange={setResubmission}>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="until_graded">{t("assignments.resubmitUntilGraded")}</SelectItem>
                          <SelectItem value="once">{t("assignments.resubmitOnce")}</SelectItem>
                          <SelectItem value="never">{t("assignments.resubmitNever")}</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  )}
                </div>

                {kind === "standard" && (
                  <div className="space-y-2 border-t pt-4">
                    <Label>{t("assignments.gradebookSlot")}</Label>
                    <Select
                      value={assessmentId || "none"}
                      onValueChange={(v) => setAssessmentId(v === "none" ? "" : v)}
                    >
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">{t("exams.gradebookNone")}</SelectItem>
                        {slots.map((slot) => (
                          <SelectItem key={slot.id} value={String(slot.id)}>
                            {slot.name}
                            {slot.max_score !== undefined ? ` (/${Number(slot.max_score)})` : ""}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {errors.assessment_id && (
                      <p className="text-xs text-destructive">{errors.assessment_id[0]}</p>
                    )}
                    <p className="text-xs text-muted-foreground">{t("assignments.gradebookHint")}</p>
                  </div>
                )}
              </div>
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
      {previewDialog}
    </DialogPrimitive.Root>
  )
}
