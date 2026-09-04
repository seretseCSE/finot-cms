"use client"

import { CheckCircle2, Hourglass, Lightbulb, ListChecks, XCircle } from "lucide-react"
import { useRouter } from "next/navigation"
import { Fragment, useEffect, useMemo, useState } from "react"

import { AnswerView } from "@/components/lms/answer-view"
import { PartBanner, PassageCard, QuestionStem } from "@/components/lms/question-content"
import { formatDateTime, romanNumeral } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"
import type { AttemptResult, AttemptResultQuestion } from "@/lib/types"

type QuestionStatus = "correct" | "partial" | "incorrect" | "pending"
type ReviewFilter = "all" | "mistakes" | "pending"

function questionStatus(question: AttemptResultQuestion): QuestionStatus {
  if (question.pending || question.earned === null) return "pending"
  if (question.earned >= question.points) return "correct"
  return question.earned > 0 ? "partial" : "incorrect"
}

const prefersReducedMotion = () =>
  typeof matchMedia !== "undefined" && matchMedia("(prefers-reduced-motion: reduce)").matches

/** Eased count-up for the hero score number; snaps instantly under reduced motion. */
function useCountUp(target: number, duration = 900): number {
  const [value, setValue] = useState(() => (prefersReducedMotion() ? target : 0))

  useEffect(() => {
    if (prefersReducedMotion()) return
    let raf = 0
    const start = performance.now()
    const tick = (now: number) => {
      const progress = Math.min(1, (now - start) / duration)
      setValue(target * (1 - Math.pow(1 - progress, 3)))
      if (progress < 1) raf = requestAnimationFrame(tick)
    }
    raf = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf)
  }, [target, duration])

  return value
}

/**
 * The taker's result review: score hero, then question-by-question with the
 * given answer and — when the quiz reveals answers — the key + explanation.
 * Hidden results show the celebratory "exam is in" holding screen.
 */
export function ResultView({ attemptId }: { attemptId: number }) {
  const { t } = useTranslation("lms")
  const router = useRouter()

  const [result, setResult] = useState<AttemptResult | null>(null)
  const [filter, setFilter] = useState<ReviewFilter>("all")

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: AttemptResult }>(`/me/exam-attempts/${attemptId}/result`)
      .then((res) => !cancelled && setResult(res.data))
      .catch(() => !cancelled && setResult({ visible: false, status: "submitted", submitted_at: null }))
    return () => {
      cancelled = true
    }
  }, [attemptId])

  const questions = useMemo(() => result?.questions ?? [], [result])

  const counts = useMemo(() => {
    const tally = { correct: 0, partial: 0, incorrect: 0, pending: 0 }
    for (const question of questions) tally[questionStatus(question)] += 1
    return tally
  }, [questions])

  const displayed = useMemo(() => {
    if (filter === "all") return questions
    if (filter === "pending") return questions.filter((question) => questionStatus(question) === "pending")
    return questions.filter((question) => {
      const status = questionStatus(question)
      return status === "incorrect" || status === "partial"
    })
  }, [questions, filter])

  if (result === null) {
    return (
      <div className="fixed inset-0 z-50 overflow-y-auto bg-background p-6 pt-safe">
        <div className="mx-auto max-w-2xl space-y-4">
          <Skeleton className="h-56 w-full rounded-3xl" />
          <Skeleton className="h-64 w-full rounded-2xl" />
        </div>
      </div>
    )
  }

  if (!result.visible) {
    return <SubmittedView result={result} onDone={() => router.back()} />
  }

  const percent =
    result.score !== null && result.score !== undefined && result.max_score
      ? Math.round((result.score / result.max_score) * 100)
      : null
  const mistakes = counts.incorrect + counts.partial

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-background">
      <div className="min-h-0 flex-1 overflow-y-auto">
        <div className="mx-auto w-full max-w-2xl space-y-4 px-4 py-6 pt-safe md:py-10">
          {/* score hero */}
          <div className="result-pop rounded-3xl border bg-card p-6 text-center shadow-xs">
            <p className="text-sm text-muted-foreground">{result.quiz_title ?? t("result.title")}</p>

            <div className="mt-4 flex justify-center">
              <ScoreRing percent={percent} score={result.score ?? null} maxScore={result.max_score ?? 0} />
            </div>

            {/* at-a-glance breakdown */}
            {questions.length > 0 && (
              <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
                <SummaryChip tone="success" icon={<CheckCircle2 className="size-3.5" />} label={t("result.correct")} count={counts.correct} />
                {counts.partial > 0 && (
                  <SummaryChip tone="warning" label={t("result.partial")} count={counts.partial} />
                )}
                {counts.incorrect > 0 && (
                  <SummaryChip tone="destructive" icon={<XCircle className="size-3.5" />} label={t("result.incorrect")} count={counts.incorrect} />
                )}
                {counts.pending > 0 && (
                  <SummaryChip tone="muted" label={t("result.pendingBadge")} count={counts.pending} />
                )}
              </div>
            )}

            {result.pending_manual && (
              <p className="mt-4 rounded-xl bg-warning/10 px-3 py-2 text-xs text-warning">
                {t("result.pending")}
              </p>
            )}
          </div>

          {/* review filter — only when there is something to narrow to */}
          {questions.length > 0 && (mistakes > 0 || counts.pending > 0) && (
            <div className="flex items-center gap-2 overflow-x-auto pb-1">
              <FilterPill active={filter === "all"} onClick={() => setFilter("all")}>
                {t("result.filterAll", { count: questions.length })}
              </FilterPill>
              {mistakes > 0 && (
                <FilterPill active={filter === "mistakes"} onClick={() => setFilter("mistakes")}>
                  {t("result.filterMistakes", { count: mistakes })}
                </FilterPill>
              )}
              {counts.pending > 0 && (
                <FilterPill active={filter === "pending"} onClick={() => setFilter("pending")}>
                  {t("result.filterPending", { count: counts.pending })}
                </FilterPill>
              )}
            </div>
          )}

          {/* per-question review */}
          {displayed.map((question, qIndex, all) => {
            const parts = result.parts ?? []
            const part = question.part !== null && question.part !== undefined ? parts[question.part] : undefined
            const startsPart =
              part !== undefined && (qIndex === 0 || all[qIndex - 1].part !== question.part)
            const status = questionStatus(question)

            return (
              <Fragment key={question.question_id}>
                {startsPart && part !== undefined && question.part !== null && question.part !== undefined && (
                  <PartBanner
                    numeral={romanNumeral(question.part + 1)}
                    title={part.title}
                    instructions={part.instructions}
                  />
                )}
                {question.group_id != null &&
                  result.groups?.[question.group_id] &&
                  (qIndex === 0 || all[qIndex - 1].group_id !== question.group_id) && (
                    <PassageCard group={result.groups[question.group_id]} defaultOpen={false} />
                  )}
                <div
                  className="result-rise space-y-3 rounded-2xl border bg-card p-4 shadow-xs"
                  style={{ animationDelay: `${Math.min(qIndex, 8) * 45}ms` }}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex gap-1.5 text-sm font-medium">
                      <span>{question.number}.</span>
                      <QuestionStem html={question.body.stem} />
                    </div>
                    <Badge
                      variant="outline"
                      className={`shrink-0 gap-1 border-transparent ${
                        status === "correct"
                          ? "bg-success/10 text-success"
                          : status === "partial"
                            ? "bg-warning/10 text-warning"
                            : status === "pending"
                              ? "bg-muted text-muted-foreground"
                              : "bg-destructive/10 text-destructive"
                      }`}
                    >
                      {status === "correct" ? (
                        <CheckCircle2 className="size-3" />
                      ) : status === "incorrect" ? (
                        <XCircle className="size-3" />
                      ) : null}
                      {question.earned !== null ? Number(question.earned) : "—"} / {question.points}
                    </Badge>
                  </div>

                  <AnswerView
                    type={question.type}
                    body={question.body}
                    answer={question.answer}
                    answerKey={question.answer_key ?? undefined}
                  />

                  {question.feedback && (
                    <p className="rounded-xl bg-accent px-3 py-2 text-sm">
                      <span className="font-medium">{t("result.teacherFeedback")}: </span>
                      {question.feedback}
                    </p>
                  )}

                  {question.explanation && (
                    <p className="flex gap-2 rounded-xl bg-info/10 px-3 py-2 text-sm text-foreground">
                      <Lightbulb className="mt-0.5 size-4 shrink-0 text-info" />
                      <span>{question.explanation}</span>
                    </p>
                  )}
                </div>
              </Fragment>
            )
          })}
        </div>
      </div>

      {/* one clear way out — a full-width Done button pinned to the bottom */}
      <footer className="border-t bg-background/95 px-4 py-3 pb-safe backdrop-blur-xl">
        <div className="mx-auto w-full max-w-2xl">
          <Button className="h-12 w-full" onClick={() => router.back()}>
            {t("result.done")}
          </Button>
        </div>
      </footer>
    </div>
  )
}

/** Animated donut with the score in the middle; tone follows the percent. */
function ScoreRing({
  percent,
  score,
  maxScore,
}: {
  percent: number | null
  score: number | null
  maxScore: number
}) {
  const [progress, setProgress] = useState(0)
  const animatedScore = useCountUp(score ?? 0)

  useEffect(() => {
    const timer = setTimeout(() => setProgress(percent ?? 0), 80)
    return () => clearTimeout(timer)
  }, [percent])

  const radius = 52
  const circumference = 2 * Math.PI * radius
  const tone =
    percent === null
      ? "text-muted-foreground"
      : percent >= 75
        ? "text-success"
        : percent >= 50
          ? "text-warning"
          : "text-destructive"
  const displayScore =
    score === null ? "—" : Number.isInteger(score) ? Math.round(animatedScore) : animatedScore.toFixed(1)

  return (
    <div className="relative size-40">
      <svg viewBox="0 0 120 120" className={cn("size-full -rotate-90", tone)} aria-hidden>
        <circle cx="60" cy="60" r={radius} fill="none" strokeWidth="9" className="stroke-muted" />
        <circle
          cx="60"
          cy="60"
          r={radius}
          fill="none"
          strokeWidth="9"
          strokeLinecap="round"
          stroke="currentColor"
          strokeDasharray={circumference}
          strokeDashoffset={circumference * (1 - Math.min(100, Math.max(0, progress)) / 100)}
          className="result-ring"
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <p className="font-display text-4xl font-semibold tracking-tight tabular-nums">{displayScore}</p>
        <p className="text-sm text-muted-foreground tabular-nums">/ {Number(maxScore)}</p>
        {percent !== null && (
          <p className={cn("mt-0.5 text-xs font-semibold tabular-nums", tone)}>{percent}%</p>
        )}
      </div>
    </div>
  )
}

function SummaryChip({
  tone,
  icon,
  label,
  count,
}: {
  tone: "success" | "warning" | "destructive" | "muted"
  icon?: React.ReactNode
  label: string
  count: number
}) {
  const tones = {
    success: "bg-success/10 text-success",
    warning: "bg-warning/10 text-warning",
    destructive: "bg-destructive/10 text-destructive",
    muted: "bg-muted text-muted-foreground",
  }

  return (
    <span className={cn("inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium", tones[tone])}>
      {icon}
      {count} {label}
    </span>
  )
}

function FilterPill({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "pressable shrink-0 rounded-full border px-3.5 py-2 text-sm font-medium transition-colors",
        active ? "border-primary bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-accent",
      )}
    >
      {children}
    </button>
  )
}

/** The "exam is in" holding screen shown while results are not yet released. */
function SubmittedView({ result, onDone }: { result: AttemptResult; onDone: () => void }) {
  const { t } = useTranslation("lms")

  const hasCounts = result.answered_count !== undefined && result.question_count !== undefined
  const releaseCopy =
    result.results_policy === "after_close" && result.expected_release_at
      ? t("result.releaseAfterClose", { date: formatDateTime(result.expected_release_at) })
      : t("result.releaseByTeacher")

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-background">
      <div className="min-h-0 flex-1 overflow-y-auto">
        <div className="mx-auto flex min-h-full w-full max-w-md flex-col items-center justify-center gap-6 px-6 py-10 pt-safe text-center">
          {/* drawn check */}
          <div className="result-pop relative">
            <div className="absolute inset-2 rounded-full bg-success/15 blur-xl" aria-hidden />
            <svg viewBox="0 0 64 64" className="relative size-28 text-success" aria-hidden>
              <circle
                cx="32"
                cy="32"
                r="28"
                fill="none"
                stroke="currentColor"
                strokeWidth="3.5"
                strokeLinecap="round"
                className="result-check-circle"
                transform="rotate(-90 32 32)"
              />
              <path
                d="M20 33 L28.5 41.5 L44 25"
                fill="none"
                stroke="currentColor"
                strokeWidth="4"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="result-check-mark"
              />
            </svg>
          </div>

          <div className="space-y-1.5">
            <h1 className="font-display text-2xl font-semibold tracking-tight">
              {t("result.submittedTitle")}
            </h1>
            {result.quiz_title && <p className="text-sm text-muted-foreground">{result.quiz_title}</p>}
          </div>

          {hasCounts && (
            <div className="grid w-full grid-cols-2 gap-3">
              <div className="rounded-2xl border bg-card p-4 shadow-xs">
                <ListChecks className="mx-auto size-5 text-primary" aria-hidden />
                <p className="mt-2 text-lg font-semibold tabular-nums">
                  {result.answered_count}
                  <span className="text-sm font-normal text-muted-foreground"> / {result.question_count}</span>
                </p>
                <p className="text-xs text-muted-foreground">{t("result.answeredLabel")}</p>
              </div>
              <div className="rounded-2xl border bg-card p-4 shadow-xs">
                <CheckCircle2 className="mx-auto size-5 text-success" aria-hidden />
                <p className="mt-2.5 text-base font-semibold tabular-nums">{formatDateTime(result.submitted_at)}</p>
                <p className="text-xs text-muted-foreground">{t("result.submittedAtLabel")}</p>
              </div>
            </div>
          )}

          <div className="flex w-full items-start gap-3 rounded-2xl border bg-card p-4 text-left shadow-xs">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-info/10 text-info">
              <Hourglass className="size-4.5" aria-hidden />
            </span>
            <div className="min-w-0">
              <p className="text-sm font-medium">{t("result.awaitingTitle")}</p>
              <p className="mt-0.5 text-sm text-muted-foreground">{releaseCopy}</p>
            </div>
          </div>
        </div>
      </div>

      <footer className="border-t bg-background/95 px-4 py-3 pb-safe backdrop-blur-xl">
        <div className="mx-auto w-full max-w-md">
          <Button className="h-12 w-full" onClick={onDone}>
            {t("result.done")}
          </Button>
        </div>
      </footer>
    </div>
  )
}
