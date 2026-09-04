"use client"

import { Ban, CheckCircle2, Flag, Loader2, UserRound, X } from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { Fragment, useEffect, useState } from "react"
import { toast } from "sonner"

import { AnswerView } from "@/components/lms/answer-view"
import { PartBanner, PassageCard, QuestionStem } from "@/components/lms/question-content"
import { formatDateTime, romanNumeral } from "@/components/lms/shared"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Skeleton } from "@/components/ui/skeleton"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AttemptGradingQuestion, QuestionGroupStem, QuizAttemptRow, QuizPart } from "@/lib/types"

interface Detail {
  attempt: QuizAttemptRow
  integrity_log: { type: string; at: string }[]
  parts?: QuizPart[] | null
  questions: AttemptGradingQuestion[]
  groups?: Record<number, QuestionGroupStem>
}

interface Props {
  quizId: number
  attemptId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onGraded: () => void
}

/**
 * Full-screen review of one sitting. Center: every question with the
 * student's answer and per-question manual scores/feedback (the essays and
 * short answers a human must mark). Right rail: who sat it, when, the
 * integrity flags and the invalidate action. "Save grades" persists the
 * manual marks and recomputes the total.
 */
export function AttemptReview({ quizId, attemptId, open, onOpenChange, onGraded }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")

  const [detail, setDetail] = useState<Detail | null>(null)
  const [scores, setScores] = useState<Record<number, string>>({})
  const [feedback, setFeedback] = useState<Record<number, string>>({})
  const [saving, setSaving] = useState(false)
  const [invalidating, setInvalidating] = useState(false)
  const [confirmInvalidate, setConfirmInvalidate] = useState(false)

  useEffect(() => {
    if (!open || attemptId === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setDetail(null)
    apiFetch<{ data: Detail }>(`/quizzes/${quizId}/attempts/${attemptId}`)
      .then((res) => {
        if (cancelled) return
        setDetail(res.data)
        const nextScores: Record<number, string> = {}
        const nextFeedback: Record<number, string> = {}
        for (const question of res.data.questions) {
          if (question.manual_score !== null) nextScores[question.question_id] = String(question.manual_score)
          if (question.feedback) nextFeedback[question.question_id] = question.feedback
        }
        setScores(nextScores)
        setFeedback(nextFeedback)
      })
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic")),
      )
    return () => {
      cancelled = true
    }
  }, [open, attemptId, quizId, tc])

  const hasManualWork = detail?.questions.some(
    (question) =>
      question.needs_manual ||
      question.manual_score !== null ||
      question.type === "essay" ||
      question.type === "short_answer",
  )

  async function save() {
    if (!detail || attemptId === null) return
    setSaving(true)
    try {
      const answers = detail.questions
        .filter(
          (question) =>
            scores[question.question_id] !== undefined || feedback[question.question_id] !== undefined,
        )
        .map((question) => ({
          question_id: question.question_id,
          manual_score:
            scores[question.question_id] !== undefined && scores[question.question_id] !== ""
              ? Number(scores[question.question_id])
              : null,
          feedback: feedback[question.question_id] || null,
        }))

      if (answers.length === 0) {
        onOpenChange(false)
        return
      }

      await apiFetch(`/quizzes/${quizId}/attempts/${attemptId}/grade`, {
        method: "POST",
        body: { answers },
      })
      toast.success(t("attempts.gradesSaved"))
      onOpenChange(false)
      onGraded()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  async function invalidate() {
    if (attemptId === null) return
    setInvalidating(true)
    try {
      const res = await apiFetch<{ message: string }>(
        `/quizzes/${quizId}/attempts/${attemptId}/invalidate`,
        { method: "POST", body: {} },
      )
      toast.success(res.message)
      onOpenChange(false)
      onGraded()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setInvalidating(false)
    }
  }

  const attempt = detail?.attempt

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/20 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0"
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
                <UserRound className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {attempt?.taker_name ?? t("attempts.review")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {t("attempts.reviewSubtitle")}
                </p>
              </div>
            </div>

            {hasManualWork && (
              <Button className="ml-auto h-10 px-5" disabled={saving || detail === null} onClick={save}>
                {saving && <Loader2 className="size-4 animate-spin" />}
                {t("attempts.saveGrades")}
              </Button>
            )}
          </header>

          {/* ── Body: answers + facts rail ───────────────────────────── */}
          <div className="min-h-0 flex-1 overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-3xl space-y-4 p-4 pb-8 md:p-8">
                {detail === null ? (
                  <>
                    <Skeleton className="h-40 w-full rounded-2xl" />
                    <Skeleton className="h-40 w-full rounded-2xl" />
                  </>
                ) : (
                  <>
                    {hasManualWork && (
                      <p className="rounded-xl bg-info/10 px-3.5 py-2.5 text-sm text-info">
                        {t("attempts.manualHint")}
                      </p>
                    )}
                    {detail.questions.map((question, qIndex) => {
                      const parts = detail.parts ?? []
                      const part =
                        question.part !== null && question.part !== undefined ? parts[question.part] : undefined
                      const startsPart =
                        part !== undefined &&
                        (qIndex === 0 || detail.questions[qIndex - 1].part !== question.part)
                      return (
                      <Fragment key={question.question_id}>
                      {startsPart && part !== undefined && question.part !== null && question.part !== undefined && (
                        <PartBanner
                          numeral={romanNumeral(question.part + 1)}
                          title={part.title}
                          compact
                        />
                      )}
                      {question.group_id != null &&
                        detail.groups?.[question.group_id] &&
                        (qIndex === 0 || detail.questions[qIndex - 1].group_id !== question.group_id) && (
                          <PassageCard group={detail.groups[question.group_id]} defaultOpen={false} />
                        )}
                      <div className="space-y-2.5 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex gap-1.5 text-sm font-medium">
                            <span>{question.number}.</span>
                            <QuestionStem html={question.body.stem} />
                          </div>
                          <Badge variant="outline" className="shrink-0">
                            {question.manual_score !== null
                              ? t("attempts.points", { earned: Number(question.manual_score), max: question.points })
                              : question.auto_score !== null
                                ? t("attempts.points", { earned: Number(question.auto_score), max: question.points })
                                : `— / ${question.points}`}
                          </Badge>
                        </div>

                        <AnswerView
                          type={question.type}
                          body={question.body}
                          answer={question.answer}
                          answerKey={question.answer_key}
                        />

                        {(question.needs_manual ||
                          question.manual_score !== null ||
                          question.type === "essay" ||
                          question.type === "short_answer") && (
                          <div className="grid gap-2 border-t pt-3 sm:grid-cols-[7rem_1fr]">
                            <div className="space-y-1">
                              <label className="text-xs font-medium text-muted-foreground">
                                {t("attempts.manualScore")} (/{question.points})
                              </label>
                              <Input
                                type="number"
                                min="0"
                                max={question.points}
                                step="0.25"
                                className="no-spinner h-9"
                                value={scores[question.question_id] ?? ""}
                                onChange={(e) =>
                                  setScores((prev) => ({ ...prev, [question.question_id]: e.target.value }))
                                }
                              />
                            </div>
                            <div className="space-y-1">
                              <label className="text-xs font-medium text-muted-foreground">
                                {t("attempts.feedback")}
                              </label>
                              <Textarea
                                rows={1}
                                value={feedback[question.question_id] ?? ""}
                                onChange={(e) =>
                                  setFeedback((prev) => ({ ...prev, [question.question_id]: e.target.value }))
                                }
                                placeholder={t("attempts.feedbackPlaceholder")}
                              />
                            </div>
                          </div>
                        )}
                      </div>
                      </Fragment>
                      )
                    })}
                  </>
                )}
              </div>
            </main>

            {/* Facts rail */}
            <aside className="border-t bg-background md:min-h-0 md:w-[320px] md:shrink-0 md:overflow-y-auto md:border-l md:border-t-0">
              <div className="space-y-5 p-4 md:p-5">
                {attempt && (
                  <>
                    <div className="rounded-2xl border p-4 text-center">
                      <p className="text-xs uppercase tracking-wide text-muted-foreground">
                        {t("attempts.score")}
                      </p>
                      <p className="mt-1 text-3xl font-semibold tabular-nums">
                        {attempt.score !== null ? Number(attempt.score) : "—"}
                        <span className="text-base font-normal text-muted-foreground">
                          {" "}/ {Number(attempt.max_score)}
                        </span>
                      </p>
                      <Badge
                        variant="outline"
                        className={`mt-2 border-transparent ${
                          attempt.status === "graded"
                            ? "bg-success/10 text-success"
                            : attempt.status === "invalidated"
                              ? "bg-destructive/10 text-destructive"
                              : "bg-warning/10 text-warning"
                        }`}
                      >
                        {t(`attempts.statuses.${attempt.status}`)}
                      </Badge>
                    </div>

                    <div className="space-y-2 text-sm">
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground">{t("attempts.attemptNo")}</span>
                        <span className="font-medium tabular-nums">#{attempt.attempt_number}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground">{t("attempts.started")}</span>
                        <span className="font-medium">{formatDateTime(attempt.started_at)}</span>
                      </div>
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground">{t("attempts.submitted")}</span>
                        <span className="font-medium">{formatDateTime(attempt.submitted_at)}</span>
                      </div>
                      {attempt.student_public_id && (
                        <div className="flex items-center justify-between gap-3">
                          <span className="text-muted-foreground">{t("attempts.studentId")}</span>
                          <span className="font-medium">{attempt.student_public_id}</span>
                        </div>
                      )}
                    </div>

                    {/* Integrity */}
                    {detail && detail.integrity_log.length > 0 ? (
                      <div className="rounded-xl border border-warning/30 bg-warning/5 p-3">
                        <p className="mb-1.5 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-warning">
                          <Flag className="size-3.5" /> {t("attempts.integrityLog")} ({attempt.flag_count})
                        </p>
                        <ul className="space-y-0.5 text-xs text-muted-foreground">
                          {detail.integrity_log.map((event, index) => (
                            <li key={index}>
                              {formatDateTime(event.at)} — {t(`attempts.events.${event.type}`)}
                            </li>
                          ))}
                        </ul>
                        <p className="mt-2 text-xs text-muted-foreground">{t("attempts.integrityNote")}</p>
                      </div>
                    ) : (
                      <div className="flex items-center gap-2 rounded-xl border border-success/30 bg-success/5 p-3 text-sm text-success">
                        <CheckCircle2 className="size-4 shrink-0" />
                        {t("attempts.integrityEmpty")}
                      </div>
                    )}

                    {attempt.status !== "invalidated" && (
                      <div className="space-y-1.5 border-t pt-4">
                        <Button
                          variant="outline"
                          className="h-11 w-full border-destructive/30 text-destructive hover:bg-destructive/5 hover:text-destructive"
                          disabled={invalidating}
                          onClick={() => setConfirmInvalidate(true)}
                        >
                          {invalidating ? <Loader2 className="size-4 animate-spin" /> : <Ban className="size-4" />}
                          {t("attempts.invalidate")}
                        </Button>
                        <p className="text-xs text-muted-foreground">{t("attempts.invalidateHint")}</p>
                      </div>
                    )}
                  </>
                )}
              </div>
            </aside>
          </div>

          <AlertDialog open={confirmInvalidate} onOpenChange={setConfirmInvalidate}>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>{t("attempts.invalidateConfirmTitle")}</AlertDialogTitle>
                <AlertDialogDescription>{t("attempts.invalidateConfirmDesc")}</AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>{tc("actions.cancel")}</AlertDialogCancel>
                <AlertDialogAction
                  className="bg-destructive text-white hover:bg-destructive/90"
                  onClick={() => {
                    setConfirmInvalidate(false)
                    void invalidate()
                  }}
                >
                  {t("attempts.invalidate")}
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
