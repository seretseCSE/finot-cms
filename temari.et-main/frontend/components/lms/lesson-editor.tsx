"use client"

import { BookOpen, CirclePlay, FileQuestion, FileUp, Loader2, Paperclip, X } from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { baseName, renamedFile } from "@/components/lms/pending-files"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { RichTextEditor } from "@/components/ui/rich-text"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { stripHtml } from "@/lib/sanitize-html"
import type { Course, CourseLessonRow, CourseLessonType, Quiz } from "@/lib/types"
import { cn } from "@/lib/utils"

const TYPE_ICONS: Record<CourseLessonType, typeof CirclePlay> = {
  video: CirclePlay,
  reading: BookOpen,
  file: Paperclip,
  quiz: FileQuestion,
}

interface Props {
  course: Pick<Course, "id" | "is_platform" | "subject_assignment_id">
  moduleId: number | null
  lesson: CourseLessonRow | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: (lesson: CourseLessonRow) => void
}

/**
 * The full-screen lesson studio, stacked above the course studio (same
 * layout language as the exam/question editors): pick what kind of lesson
 * this is, then fill its content — video URL, WYSIWYG reading, R2 file, or
 * a quiz checkpoint from the same scope.
 */
export function LessonEditor({ course, moduleId, lesson, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const fileInput = useRef<HTMLInputElement>(null)

  const [type, setType] = useState<CourseLessonType>("video")
  const [title, setTitle] = useState("")
  const [url, setUrl] = useState("")
  const [body, setBody] = useState("")
  const [file, setFile] = useState<File | null>(null)
  const [fileName, setFileName] = useState("")
  const [quizId, setQuizId] = useState("")
  const [duration, setDuration] = useState("")
  const [quizzes, setQuizzes] = useState<Quiz[]>([])
  const [imgUploading, setImgUploading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  // Picked or dropped, the lesson file keeps its editable display name.
  const fileDrop = useFileDrop({
    onFiles: ([picked]) => {
      setFile(picked)
      setFileName(baseName(picked.name))
    },
  })

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync editor with the edited row */
    setErrors({})
    setType(lesson?.type ?? "video")
    setTitle(lesson?.title ?? "")
    setUrl(lesson?.content?.url ?? "")
    setBody(lesson?.content?.body ?? "")
    setFile(null)
    setFileName("")
    setQuizId(lesson?.quiz_id ? String(lesson.quiz_id) : "")
    setDuration(lesson?.duration_minutes ? String(lesson.duration_minutes) : "")
    /* eslint-enable react-hooks/set-state-in-effect */

    let cancelled = false
    const query = course.is_platform
      ? "/quizzes?platform=1&per_page=100"
      : course.subject_assignment_id
        ? `/quizzes?subject_assignment_id=${course.subject_assignment_id}&per_page=100`
        : "/quizzes?per_page=100"
    apiFetch<{ data: Quiz[] }>(query)
      .then((res) => !cancelled && setQuizzes(res.data))
      .catch(() => !cancelled && setQuizzes([]))
    return () => {
      cancelled = true
    }
  }, [open, lesson, course])

  async function uploadImage(uploaded: File) {
    const form = new FormData()
    form.append("file", uploaded)
    const res = await apiFetch<{ data: { url: string; path: string } }>("/lms/uploads", {
      method: "POST",
      body: form,
    })
    return res.data
  }

  async function save() {
    setSaving(true)
    setErrors({})
    try {
      const form = new FormData()
      form.append("type", type)
      form.append("title", title)
      if (type === "video" && url) form.append("url", url)
      if (type === "reading" && body) form.append("body", body)
      if (type === "file" && file) form.append("file", renamedFile({ file, name: fileName }))
      if (type === "quiz" && quizId) form.append("quiz_id", quizId)
      if (duration) form.append("duration_minutes", duration)
      if (lesson !== null) form.append("_method", "PUT")

      const res = await apiFetch<{ data: CourseLessonRow }>(
        lesson ? `/course-lessons/${lesson.id}` : `/course-modules/${moduleId}/lessons`,
        { method: "POST", body: form },
      )
      toast.success(t("courses.lessonSaved"))
      onOpenChange(false)
      onSaved(res.data)
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

  const hasBody = stripHtml(body).trim() !== "" || /<img|<iframe/i.test(body)

  const contentReady =
    (type === "video" && url.trim() !== "") ||
    (type === "reading" && hasBody) ||
    (type === "file" && (file !== null || lesson?.content?.name !== undefined)) ||
    (type === "quiz" && quizId !== "")

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
                <BookOpen className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {lesson ? t("courses.editLesson") : t("courses.addLesson")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {title || t("courses.lessonTitle")}
                </p>
              </div>
            </div>

            <div className="ml-auto flex items-center">
              <Button
                className="h-10 px-5"
                disabled={saving || imgUploading || title.trim() === "" || !contentReady}
                onClick={save}
              >
                {saving && <Loader2 className="size-4 animate-spin" />}
                {tc("actions.save")}
              </Button>
            </div>
          </header>

          {/* ── Body ─────────────────────────────────────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15">
            <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
              <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                <div className="space-y-2">
                  <Label>{t("courses.lessonType")}</Label>
                  <div className="flex flex-wrap justify-center gap-6 py-1 sm:justify-start">
                    {(Object.keys(TYPE_ICONS) as CourseLessonType[]).map((option) => {
                      const Icon = TYPE_ICONS[option]
                      const selected = type === option
                      return (
                        <button
                          key={option}
                          type="button"
                          onClick={() => setType(option)}
                          className="pressable flex flex-col items-center gap-2"
                        >
                          <span
                            className={cn(
                              "flex size-16 items-center justify-center rounded-full border-2 transition-colors",
                              selected
                                ? "border-primary bg-primary/10 text-primary"
                                : "border-border text-muted-foreground hover:bg-accent",
                            )}
                          >
                            <Icon className="size-6" strokeWidth={1.75} />
                          </span>
                          <span
                            className={cn(
                              "text-xs font-medium",
                              selected ? "text-primary" : "text-muted-foreground",
                            )}
                          >
                            {t(`courses.lessonTypes.${option}`)}
                          </span>
                        </button>
                      )
                    })}
                  </div>
                </div>

                <div className="space-y-2">
                  <Label>
                    {t("courses.lessonTitle")} <span className="text-destructive">*</span>
                  </Label>
                  <Input value={title} onChange={(e) => setTitle(e.target.value)} />
                  {errors.title && <p className="text-destructive text-xs">{errors.title[0]}</p>}
                </div>

                {type === "video" && (
                  <div className="space-y-2">
                    <Label>
                      {t("courses.videoUrl")} <span className="text-destructive">*</span>
                    </Label>
                    <Input
                      type="url"
                      inputMode="url"
                      value={url}
                      onChange={(e) => setUrl(e.target.value)}
                      placeholder="https://www.youtube.com/watch?v=…"
                    />
                    <p className="text-xs text-muted-foreground">{t("courses.videoUrlHint")}</p>
                    {errors.url && <p className="text-destructive text-xs">{errors.url[0]}</p>}
                  </div>
                )}

                {type === "reading" && (
                  <div className="space-y-2">
                    <Label>
                      {t("courses.readingBody")} <span className="text-destructive">*</span>
                    </Label>
                    <RichTextEditor
                      value={body}
                      onChange={setBody}
                      onUploadingChange={setImgUploading}
                      onUploadImage={uploadImage}
                    />
                    <p className="text-xs text-muted-foreground">{t("courses.readingHint")}</p>
                    {errors.body && <p className="text-destructive text-xs">{errors.body[0]}</p>}
                  </div>
                )}

                {type === "file" && (
                  <div
                    {...fileDrop.dropProps}
                    className={cn("space-y-2 rounded-2xl", fileDrop.dragOver && DROP_ACTIVE)}
                  >
                    <Label>
                      {t("courses.lessonFile")} <span className="text-destructive">*</span>
                    </Label>
                    {lesson?.content?.name && file === null && (
                      <p className="rounded-xl border px-3 py-2 text-sm text-muted-foreground">
                        {lesson.content.name}
                      </p>
                    )}
                    <input
                      ref={fileInput}
                      type="file"
                      className="hidden"
                      onChange={(e) => {
                        fileDrop.takeFiles(e.target.files)
                        e.target.value = ""
                      }}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      className="w-full justify-start"
                      onClick={() => fileInput.current?.click()}
                    >
                      <FileUp className="size-4" />
                      <span className="truncate">{file ? file.name : t("courses.lessonFile")}</span>
                    </Button>
                    {file !== null && (
                      <Input
                        value={fileName}
                        onChange={(e) => setFileName(e.target.value)}
                        placeholder={t("assignments.fileName")}
                        aria-label={t("assignments.fileName")}
                      />
                    )}
                    {errors.file && <p className="text-destructive text-xs">{errors.file[0]}</p>}
                  </div>
                )}

                {type === "quiz" && (
                  <div className="space-y-2">
                    <Label>
                      {t("assignments.linkedQuiz")} <span className="text-destructive">*</span>
                    </Label>
                    <Select value={quizId || undefined} onValueChange={setQuizId}>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t("assignments.linkedQuizPlaceholder")} />
                      </SelectTrigger>
                      <SelectContent>
                        {quizzes.map((quiz) => (
                          <SelectItem key={quiz.id} value={String(quiz.id)}>
                            {quiz.title}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">{t("courses.quizLessonBuilderHint")}</p>
                    {errors.quiz_id && <p className="text-destructive text-xs">{errors.quiz_id[0]}</p>}
                  </div>
                )}

                <div className="space-y-2">
                  <Label>{t("courses.duration")}</Label>
                  <Input
                    type="number"
                    min="1"
                    max="600"
                    className="no-spinner w-32"
                    value={duration}
                    onChange={(e) => setDuration(e.target.value)}
                  />
                </div>
              </section>
            </div>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
