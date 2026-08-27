"use client"

import { Camera, CheckCircle2, ClipboardList, KeyRound, Link2, Loader2, Mic, Paperclip, PlayCircle, X } from "lucide-react"
import { useRouter } from "next/navigation"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import {
  PendingFileList,
  renamedFile,
  toPendingFiles,
  type PendingFile,
} from "@/components/lms/pending-files"
import { QuestionStem } from "@/components/lms/question-content"
import { formatDateTime } from "@/components/lms/shared"
import { AssignmentChat } from "@/components/lms/assignment-chat"
import { AttachmentTile } from "@/components/ui/attachment"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { useMediaPreview } from "@/components/ui/media-preview"
import { RichTextEditor } from "@/components/ui/rich-text"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { COURSEWORK_ACCEPT, COURSEWORK_MAX_BYTES } from "@/lib/files"
import { useTranslation } from "@/lib/i18n"
import { stripHtml } from "@/lib/sanitize-html"
import { cn } from "@/lib/utils"
import type { MeAssignment } from "@/lib/types"

interface Props {
  assignmentId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
}

/**
 * The student's full-screen assignment view (matching the studio layout):
 * left ~2/3 is the work — instructions + teacher files + rubric, then the
 * turn-in flow (rich written answer, files, a photo of paper work, audio,
 * or a link); right ~1/3 is the private chat with the teacher. Quiz-kind
 * work deep-links into the exam player (asking for the access code when the
 * quiz has one). On mobile it collapses into one app-like scroll.
 */
export function StudentAssignmentSheet({ assignmentId, open, onOpenChange, onChanged }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const router = useRouter()
  const fileInput = useRef<HTMLInputElement>(null)
  const cameraInput = useRef<HTMLInputElement>(null)
  const audioInput = useRef<HTMLInputElement>(null)

  const [assignment, setAssignment] = useState<MeAssignment | null>(null)
  const [body, setBody] = useState("")
  const [linkUrl, setLinkUrl] = useState("")
  const [files, setFiles] = useState<PendingFile[]>([])
  const [saving, setSaving] = useState(false)
  const [startingQuiz, setStartingQuiz] = useState(false)
  const [accessCode, setAccessCode] = useState("")
  const { openPreview, previewDialog } = useMediaPreview()
  const [imgUploading, setImgUploading] = useState(false)

  useEffect(() => {
    if (!open || assignmentId === null) return
    let cancelled = false
    /* eslint-disable react-hooks/set-state-in-effect -- reset while (re)loading */
    setAssignment(null)
    setFiles([])
    setAccessCode("")
    /* eslint-enable react-hooks/set-state-in-effect */
    apiFetch<{ data: MeAssignment }>(`/me/lms/assignments/${assignmentId}`)
      .then((res) => {
        if (cancelled) return
        setAssignment(res.data)
        setBody(res.data.submission?.body ?? "")
        setLinkUrl(res.data.submission?.link_url ?? "")
      })
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : t("player.loadFailed"))
        onOpenChange(false)
      })
    return () => {
      cancelled = true
    }
  }, [open, assignmentId, onOpenChange, t])

  async function submit() {
    if (!assignment) return
    setSaving(true)
    try {
      const form = new FormData()
      if (stripHtml(body).trim() !== "" && assignment.submission_types.includes("text")) {
        form.append("body", body)
      }
      if (linkUrl.trim() !== "" && assignment.submission_types.includes("link")) {
        form.append("link_url", linkUrl.trim())
      }
      files.forEach((entry, index) => form.append(`files[${index}]`, renamedFile(entry)))

      const res = await apiFetch<{ message: string }>(`/me/lms/assignments/${assignment.id}/submit`, {
        method: "POST",
        body: form,
      })
      toast.success(res.message)
      onOpenChange(false)
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("player.loadFailed"))
    } finally {
      setSaving(false)
    }
  }

  async function removeFile(path: string | null | undefined) {
    if (!assignment || !path) return
    try {
      await apiFetch(`/me/lms/assignments/${assignment.id}/remove-file`, {
        method: "POST",
        body: { path },
      })
      setAssignment((prev) =>
        prev && prev.submission
          ? {
              ...prev,
              submission: {
                ...prev.submission,
                files: prev.submission.files.filter((file) => file.path !== path),
              },
            }
          : prev,
      )
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("player.loadFailed"))
    }
  }

  async function openQuiz() {
    if (!assignment?.quiz) return
    if (assignment.quiz.live_attempt_id) {
      router.push(`/me/exam/${assignment.quiz.live_attempt_id}`)
      return
    }
    setStartingQuiz(true)
    try {
      const res = await apiFetch<{ data: { attempt_id: number } }>(
        `/me/exams/${assignment.quiz.id}/start`,
        {
          method: "POST",
          body: assignment.quiz.requires_access_code ? { access_code: accessCode.trim() } : {},
        },
      )
      router.push(`/me/exam/${res.data.attempt_id}`)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("player.loadFailed"))
    } finally {
      setStartingQuiz(false)
    }
  }

  const submission = assignment?.submission ?? null
  const types = assignment?.submission_types ?? []
  const acceptsText = types.includes("text")
  const acceptsFile = types.includes("file")
  const acceptsPhoto = types.includes("photo")
  const acceptsAudio = types.includes("audio")
  const acceptsLink = types.includes("link")
  const acceptsUploads = acceptsFile || acceptsPhoto || acceptsAudio
  const isQuizKind = assignment?.kind === "quiz"
  const quizDone =
    isQuizKind &&
    assignment?.quiz_progress != null &&
    assignment.quiz_progress.status !== "in_progress"

  const editable =
    assignment !== null &&
    !isQuizKind &&
    assignment.status === "published" &&
    (submission === null || submission.status === "submitted") &&
    (acceptsText || acceptsUploads || acceptsLink)

  // Work dropped anywhere on the submission panel joins the same rename list.
  const fileDrop = useFileDrop({
    multiple: true,
    disabled: !editable,
    accept: COURSEWORK_ACCEPT,
    maxSize: COURSEWORK_MAX_BYTES,
    onFiles: (picked) => setFiles((prev) => [...prev, ...toPendingFiles(picked)]),
  })

  const canSubmit =
    editable &&
    !saving &&
    !imgUploading &&
    (stripHtml(body).trim() !== "" ||
      linkUrl.trim() !== "" ||
      files.length > 0 ||
      (submission?.files.length ?? 0) > 0)

  const submitLabel = submission !== null ? t("learn.resubmit") : t("learn.submit")

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
                  {assignment?.title ?? t("assignments.title")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {[
                    assignment?.subject_name,
                    assignment?.due_at
                      ? t("learn.due", { date: formatDateTime(assignment.due_at) })
                      : null,
                  ]
                    .filter(Boolean)
                    .join(" · ")}
                </p>
              </div>
            </div>

            {editable && (
              <Button className="ml-auto h-10 px-4 md:px-5" onClick={submit} disabled={!canSubmit}>
                {saving && <Loader2 className="size-4 animate-spin" />}
                {submitLabel}
              </Button>
            )}
          </header>

          {/* ── Body: the work + chat rail ───────────────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-3xl space-y-4 p-4 pb-8 md:p-6">
                {assignment === null ? (
                  <>
                    <Skeleton className="h-24 w-full rounded-2xl" />
                    <Skeleton className="h-40 w-full rounded-2xl" />
                  </>
                ) : (
                  <>
                    {/* the work */}
                    <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                      <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {assignment.subject_name && (
                          <Badge variant="secondary">{assignment.subject_name}</Badge>
                        )}
                        {isQuizKind && <Badge variant="outline">{t("assignments.kinds.quiz")}</Badge>}
                        {assignment.max_score !== null && (
                          <Badge variant="outline">/{Number(assignment.max_score)}</Badge>
                        )}
                        {assignment.due_at && (
                          <span>{t("learn.due", { date: formatDateTime(assignment.due_at) })}</span>
                        )}
                      </div>

                      {assignment.instructions && (
                        <QuestionStem html={assignment.instructions} className="text-sm" />
                      )}

                      {(assignment.attachments ?? []).length > 0 && (
                        <div className="space-y-1.5">
                          {(assignment.attachments ?? []).map((file, index) => (
                            <AttachmentTile
                              key={index}
                              file={file}
                              onPreview={() => openPreview(assignment.attachments ?? [], index)}
                            />
                          ))}
                        </div>
                      )}

                      {/* how the work is marked */}
                      {(assignment.rubric?.length ?? 0) > 0 && (
                        <div className="space-y-1 rounded-xl border p-3.5">
                          <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {t("assignments.rubricTitle")}
                          </p>
                          {(assignment.rubric ?? []).map((row, index) => (
                            <div key={index} className="flex items-center justify-between gap-3 text-sm">
                              <span className="min-w-0 flex-1">{row.criterion}</span>
                              <span className="tabular-nums text-muted-foreground">
                                {submission?.rubric_scores != null
                                  ? `${Number(submission.rubric_scores[index] ?? 0)}/`
                                  : ""}
                                {Number(row.max_points)}
                              </span>
                            </div>
                          ))}
                        </div>
                      )}
                    </section>

                    {/* quiz-kind: the quiz IS the work — no separate turn-in */}
                    {isQuizKind && assignment.quiz && (
                      <section className="space-y-3 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                        {quizDone ? (
                          <div className="space-y-1 rounded-xl border border-success/30 bg-success/5 p-3.5">
                            <p className="flex items-center gap-2 text-sm font-semibold text-success">
                              <CheckCircle2 className="size-4" />
                              {t("learn.quizDoneTitle")}
                            </p>
                            <p className="text-sm text-muted-foreground">
                              {assignment.quiz_progress?.score != null
                                ? t("learn.gradedScore", {
                                    score: Number(assignment.quiz_progress.score),
                                    max: Number(assignment.quiz_progress.max_score),
                                  })
                                : t("learn.quizAwaitingResults")}
                            </p>
                          </div>
                        ) : (
                          <p className="text-sm">{t("assignments.quizKindHint")}</p>
                        )}
                        <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                          {assignment.quiz.duration_minutes ? (
                            <Badge variant="outline">
                              {t("learn.minutes", { count: assignment.quiz.duration_minutes })}
                            </Badge>
                          ) : null}
                          <Badge variant="outline">
                            {t("learn.attemptsOf", {
                              used: assignment.quiz.attempts_used,
                              total: assignment.quiz.attempts_allowed || "∞",
                            })}
                          </Badge>
                        </div>
                        {assignment.quiz.requires_access_code &&
                          assignment.quiz.can_start &&
                          !assignment.quiz.live_attempt_id && (
                            <div className="relative">
                              <KeyRound className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                              <Input
                                value={accessCode}
                                onChange={(e) => setAccessCode(e.target.value)}
                                placeholder={t("learn.accessCodePlaceholder")}
                                className="pl-9"
                                autoComplete="off"
                              />
                            </div>
                          )}
                        {/* Only offer the player when there's actually a sitting to
                            enter (a live attempt to resume, or an attempt left) —
                            never a dead disabled button under a "completed" card. */}
                        {(assignment.quiz.can_start || assignment.quiz.live_attempt_id) && (
                          <Button
                            className="h-12 w-full"
                            onClick={() => void openQuiz()}
                            loading={startingQuiz} disabled={(assignment.quiz.requires_access_code &&
                                !assignment.quiz.live_attempt_id &&
                                accessCode.trim() === "")}
                          >
                            <PlayCircle className="size-4" />
                            {assignment.quiz.live_attempt_id
                              ? t("learn.resume")
                              : quizDone
                                ? t("learn.retake")
                                : t("learn.start")}
                          </Button>
                        )}
                      </section>
                    )}

                    {/* graded feedback */}
                    {submission !== null && submission.status !== "submitted" && (
                      <section className="space-y-2 rounded-2xl border bg-accent/40 p-4 md:p-5">
                        <p className="text-sm font-semibold">
                          {submission.score !== null
                            ? t("learn.gradedScore", {
                                score: Number(submission.score),
                                max: assignment.max_score !== null ? Number(assignment.max_score) : "—",
                              })
                            : t("learn.pendingGrading")}
                        </p>
                        {submission.feedback && (
                          <QuestionStem html={submission.feedback} className="text-sm" />
                        )}
                      </section>
                    )}

                    {/* the turn-in */}
                    {!isQuizKind && (acceptsText || acceptsUploads || acceptsLink) && (
                      <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                        {submission !== null && (
                          <p className="text-xs text-muted-foreground">
                            {t("learn.submittedOn", { date: formatDateTime(submission.submitted_at) })}
                            {submission.is_late ? ` · ${t("assignments.late")}` : ""}
                          </p>
                        )}

                        {acceptsText && (
                          <div className="space-y-2">
                            <p className="text-sm font-medium">{t("learn.yourAnswer")}</p>
                            {editable ? (
                              <RichTextEditor
                                value={body}
                                onChange={setBody}
                                placeholder={t("learn.answerPlaceholder")}
                                onUploadingChange={setImgUploading}
                                onUploadImage={async (file) => {
                                  const form = new FormData()
                                  form.append("file", file)
                                  const res = await apiFetch<{ data: { url: string; path: string } }>(
                                    "/me/lms/uploads",
                                    { method: "POST", body: form },
                                  )
                                  return { url: res.data.url, path: res.data.path }
                                }}
                              />
                            ) : stripHtml(body).trim() !== "" ? (
                              <QuestionStem html={body} className="rounded-xl bg-muted/40 px-3.5 py-3 text-sm" />
                            ) : null}
                          </div>
                        )}

                        {acceptsLink && (
                          <div className="space-y-2">
                            <p className="text-sm font-medium">{t("learn.yourLink")}</p>
                            <div className="relative">
                              <Link2 className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                              <Input
                                type="url"
                                value={linkUrl}
                                onChange={(e) => setLinkUrl(e.target.value)}
                                placeholder="https://…"
                                className="pl-9"
                                disabled={!editable}
                              />
                            </div>
                          </div>
                        )}

                        {acceptsUploads && (
                          <div
                            {...fileDrop.dropProps}
                            className={cn(
                              "space-y-2 rounded-2xl",
                              fileDrop.dragOver && DROP_ACTIVE,
                            )}
                          >
                            {(submission?.files ?? []).map((file, index) => (
                              <AttachmentTile
                                key={index}
                                file={file}
                                onPreview={() => openPreview(submission?.files ?? [], index)}
                                onDelete={editable ? () => void removeFile(file.path) : undefined}
                              />
                            ))}
                            <PendingFileList
                              items={files}
                              onRename={(index, name) =>
                                setFiles((prev) => prev.map((f, i) => (i === index ? { ...f, name } : f)))
                              }
                              onRemove={(index) => setFiles((prev) => prev.filter((_, i) => i !== index))}
                            />
                            {editable && (
                              <>
                                {/* hidden pickers: generic file / camera capture / audio */}
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
                                <input
                                  ref={cameraInput}
                                  type="file"
                                  accept="image/*"
                                  capture="environment"
                                  multiple
                                  className="hidden"
                                  onChange={(e) => {
                                    fileDrop.takeFiles(e.target.files)
                                    e.target.value = ""
                                  }}
                                />
                                <input
                                  ref={audioInput}
                                  type="file"
                                  accept="audio/*"
                                  className="hidden"
                                  onChange={(e) => {
                                    fileDrop.takeFiles(e.target.files)
                                    e.target.value = ""
                                  }}
                                />
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                  {acceptsPhoto && (
                                    <Button
                                      type="button"
                                      variant="outline"
                                      className="h-11"
                                      onClick={() => cameraInput.current?.click()}
                                    >
                                      <Camera className="size-4" /> {t("learn.snapPhoto")}
                                    </Button>
                                  )}
                                  {acceptsFile && (
                                    <Button
                                      type="button"
                                      variant="outline"
                                      className="h-11"
                                      onClick={() => fileInput.current?.click()}
                                    >
                                      <Paperclip className="size-4" /> {t("learn.attachFiles")}
                                    </Button>
                                  )}
                                  {acceptsAudio && (
                                    <Button
                                      type="button"
                                      variant="outline"
                                      className="h-11"
                                      onClick={() => audioInput.current?.click()}
                                    >
                                      <Mic className="size-4" /> {t("learn.addAudio")}
                                    </Button>
                                  )}
                                </div>
                                <DropHint className="block" />
                              </>
                            )}
                          </div>
                        )}

                        {/* app-like submit on mobile (the header button is sm+) */}
                        {editable && (
                          <Button className="h-12 w-full md:hidden" onClick={submit} disabled={!canSubmit}>
                            {submitLabel}
                          </Button>
                        )}
                      </section>
                    )}
                  </>
                )}
              </div>
            </main>

            {/* Chat rail */}
            <aside className="flex flex-col border-t bg-background md:min-h-0 md:w-1/3 md:min-w-[320px] md:max-w-[440px] md:shrink-0 md:border-l md:border-t-0">
              <p className="flex shrink-0 items-center gap-1.5 border-b px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("assignments.thread")}
              </p>
              {assignment && (
                <AssignmentChat key={assignment.id} assignmentId={assignment.id} lane="me" />
              )}
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
      {previewDialog}
    </DialogPrimitive.Root>
  )
}
