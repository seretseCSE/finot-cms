"use client"

import { Link2, Loader2, X } from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { QuestionStem } from "@/components/lms/question-content"
import { formatDateTime } from "@/components/lms/shared"
import { AssignmentChat } from "@/components/lms/assignment-chat"
import { AttachmentTile } from "@/components/ui/attachment"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { useMediaPreview } from "@/components/ui/media-preview"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { RichTextEditor } from "@/components/ui/rich-text"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { stripHtml } from "@/lib/sanitize-html"
import type { LmsAssignment, LmsSubmission } from "@/lib/types"

interface Props {
  assignment: LmsAssignment
  submission: LmsSubmission | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onGraded: () => void
}

/**
 * The full-screen grading studio: left ~2/3 is the student's work (rich
 * answer / files / link) and the marking (score pad or per-criterion rubric
 * lines — server-summed, late penalty auto-docked — plus WYSIWYG feedback);
 * right ~1/3 is the private chat with the student. On mobile it collapses
 * into one app-like scroll.
 */
export function SubmissionGradingSheet({ assignment, submission, open, onOpenChange, onGraded }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")

  const rubric = assignment.rubric ?? []
  const hasRubric = rubric.length > 0

  const [score, setScore] = useState("")
  const [rubricScores, setRubricScores] = useState<string[]>([])
  const [feedback, setFeedback] = useState("")
  const [saving, setSaving] = useState(false)
  const { openPreview, previewDialog } = useMediaPreview()
  const [imgUploading, setImgUploading] = useState(false)

  useEffect(() => {
    if (!open || !submission) return
    /* eslint-disable react-hooks/set-state-in-effect -- sync sheet with the graded row */
    setScore(submission.score !== null ? String(submission.score) : "")
    setRubricScores(
      rubric.map((_, index) => String(submission.rubric_scores?.[index] ?? "")),
    )
    setFeedback(submission.feedback ?? "")
    /* eslint-enable react-hooks/set-state-in-effect */
    // eslint-disable-next-line react-hooks/exhaustive-deps -- rubric derives from assignment
  }, [open, submission, assignment.id])

  const rubricTotal = rubricScores.reduce(
    (sum, value, index) => sum + Math.min(Number(value || 0), Number(rubric[index]?.max_points ?? 0)),
    0,
  )

  async function save() {
    if (!submission) return
    setSaving(true)
    try {
      const cleanFeedback = stripHtml(feedback).trim() !== "" ? feedback : null
      await apiFetch(`/assignments/${assignment.id}/submissions/${submission.id}/grade`, {
        method: "POST",
        body: hasRubric
          ? { rubric_scores: rubricScores.map((value) => Number(value || 0)), feedback: cleanFeedback }
          : { score: Number(score), feedback: cleanFeedback },
      })
      toast.success(t("assignments.submissionGraded"))
      onOpenChange(false)
      onGraded()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  if (!submission) return null

  const canSave =
    !saving && !imgUploading && (hasRubric ? rubricScores.some((v) => v !== "") : score !== "")

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
              <PersonAvatar
                name={submission.student_name ?? ""}
                photoUrl={submission.student_photo_url ?? null}
                className="hidden size-8 sm:flex"
              />
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {submission.student_name}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {assignment.title} ·{" "}
                  {t("learn.submittedOn", { date: formatDateTime(submission.submitted_at) })}
                  {submission.attempt_count > 1 ? ` · #${submission.attempt_count}` : ""}
                </p>
              </div>
              {submission.is_late && (
                <Badge variant="outline" className="border-transparent bg-warning/10 text-warning">
                  {t("assignments.late")}
                  {assignment.late_penalty_percent ? ` −${Number(assignment.late_penalty_percent)}%` : ""}
                </Badge>
              )}
            </div>

            <Button className="ml-auto h-10 px-4 md:px-5" onClick={save} disabled={!canSave}>
              {saving && <Loader2 className="size-4 animate-spin" />}
              {tc("actions.save")}
            </Button>
          </header>

          {/* ── Body: the work + marking, chat rail ──────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-3xl space-y-4 p-4 pb-8 md:p-6">
                {/* the student's work */}
                <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("assignments.answer")}
                  </p>

                  {submission.body ? (
                    <QuestionStem html={submission.body} className="text-sm" />
                  ) : (
                    !submission.link_url &&
                    submission.files.length === 0 && (
                      <p className="text-sm text-muted-foreground">{tc("states.empty")}</p>
                    )
                  )}

                  {submission.link_url && (
                    <a
                      href={submission.link_url}
                      target="_blank"
                      rel="noreferrer"
                      className="pressable flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm hover:bg-accent"
                    >
                      <Link2 className="size-3.5 shrink-0 text-muted-foreground" />
                      <span className="min-w-0 flex-1 truncate">{submission.link_url}</span>
                    </a>
                  )}

                  {submission.files.length > 0 && (
                    <div className="space-y-1.5">
                      {submission.files.map((file, index) => (
                        <AttachmentTile
                          key={index}
                          file={file}
                          onPreview={() => openPreview(submission.files, index)}
                        />
                      ))}
                    </div>
                  )}
                </section>

                {/* marking */}
                <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  {hasRubric ? (
                    <div className="space-y-2">
                      <Label>{t("assignments.rubricTitle")}</Label>
                      <div className="space-y-1.5">
                        {rubric.map((row, index) => (
                          <div key={index} className="flex items-center gap-2 rounded-xl border px-3 py-2">
                            <span className="min-w-0 flex-1 text-sm">{row.criterion}</span>
                            <Input
                              type="number"
                              min="0"
                              max={Number(row.max_points)}
                              step="0.25"
                              inputMode="decimal"
                              className="no-spinner h-9 w-16 text-center"
                              value={rubricScores[index] ?? ""}
                              onChange={(e) =>
                                setRubricScores((prev) =>
                                  prev.map((v, i) => (i === index ? e.target.value : v)),
                                )
                              }
                              aria-label={row.criterion}
                            />
                            <span className="w-8 text-xs text-muted-foreground">/{Number(row.max_points)}</span>
                          </div>
                        ))}
                      </div>
                      <p className="text-right text-sm font-medium tabular-nums">
                        {t("assignments.rubricTotal", { total: rubricTotal })}
                      </p>
                    </div>
                  ) : (
                    <div className="space-y-2 sm:max-w-[10rem]">
                      <Label>
                        {t("assignments.score")}
                        {assignment.max_score !== null ? ` (/${Number(assignment.max_score)})` : ""}
                      </Label>
                      <Input
                        type="number"
                        min="0"
                        max={assignment.max_score ?? undefined}
                        step="0.25"
                        inputMode="decimal"
                        className="no-spinner text-lg font-semibold"
                        value={score}
                        onChange={(e) => setScore(e.target.value)}
                        autoFocus
                      />
                    </div>
                  )}

                  <div className="space-y-2">
                    <Label>{t("assignments.feedback")}</Label>
                    <RichTextEditor
                      value={feedback}
                      onChange={setFeedback}
                      placeholder={t("assignments.feedbackPlaceholder")}
                      onUploadingChange={setImgUploading}
                      onUploadImage={async (file) => {
                        const form = new FormData()
                        form.append("file", file)
                        const res = await apiFetch<{ data: { url: string; path: string } }>(
                          "/lms/uploads",
                          { method: "POST", body: form },
                        )
                        return { url: res.data.url, path: res.data.path }
                      }}
                    />
                  </div>
                </section>
              </div>
            </main>

            {/* Chat rail */}
            <aside className="flex flex-col border-t bg-background md:min-h-0 md:w-1/3 md:min-w-[320px] md:max-w-[440px] md:shrink-0 md:border-l md:border-t-0">
              <p className="flex shrink-0 items-center gap-1.5 border-b px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("assignments.chat")}
              </p>
              <AssignmentChat
                key={submission.student_id}
                assignmentId={assignment.id}
                studentId={submission.student_id}
                lane="staff"
              />
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
      {previewDialog}
    </DialogPrimitive.Root>
  )
}
