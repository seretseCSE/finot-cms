"use client"

import {
  ArrowLeft,
  ArrowRight,
  Clock,
  Dices,
  Eye,
  FileQuestion,
  Play,
  X,
} from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useMemo, useState } from "react"

import {
  PartBanner,
  PassageCard,
  QuestionAttachments,
  QuestionStem,
} from "@/components/lms/question-content"
import { romanNumeral } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"
import type { Question, QuestionGroupStem, QuizDetail } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * Wrap a QUESTION BANK's rows as a pseudo-exam so ExamPreview can page
 * through them exactly like a student would — the bank-level "check your
 * formatting" dry run. Group containers become passages above their
 * sub-questions; retired questions stay out.
 */
export function bankPreviewQuiz(
  title: string,
  rows: Question[],
  meta: { grade?: string | null; subject?: string | null } = {}
): QuizDetail {
  const usable = rows.filter((question) => question.status !== "retired")

  const groups: Record<number, QuestionGroupStem> = {}
  for (const question of usable) {
    if (question.type === "group") {
      groups[question.id] = {
        id: question.id,
        stem: question.body.stem,
        attachments: question.body.attachments,
      }
    }
  }

  const childrenOf = new Map<number, Question[]>()
  for (const question of usable) {
    if (question.parent_id) {
      childrenOf.set(question.parent_id, [
        ...(childrenOf.get(question.parent_id) ?? []),
        question,
      ])
    }
  }
  childrenOf.forEach((list) =>
    list.sort((a, b) => (a.position ?? 0) - (b.position ?? 0) || a.id - b.id)
  )

  // Reading order: standalone questions as listed; a group's sub-questions
  // together at the group's place; orphans (parent not loaded) at the end.
  const ordered: Question[] = []
  const placed = new Set<number>()
  for (const question of usable) {
    if (question.parent_id && groups[question.parent_id]) continue
    if (question.type === "group") {
      for (const child of childrenOf.get(question.id) ?? []) {
        ordered.push(child)
        placed.add(child.id)
      }
      continue
    }
    ordered.push(question)
    placed.add(question.id)
  }
  for (const question of usable) {
    if (
      !placed.has(question.id) &&
      question.type !== "group" &&
      !(question.parent_id && groups[question.parent_id])
    ) {
      ordered.push(question)
    }
  }

  return {
    id: 0,
    kind: "quiz",
    title,
    instructions: null,
    is_platform: false,
    subject_assignment_id: null,
    subject_id: null,
    subject_name: meta.subject ?? null,
    grade_level_id: null,
    grade_level_name: meta.grade ?? null,
    language: "en",
    status: "draft",
    total_points: ordered.reduce(
      (sum, question) => sum + Number(question.points),
      0
    ),
    settings: {},
    draw: null,
    parts: null,
    has_access_code: false,
    assessment_id: null,
    published_at: null,
    closed_at: null,
    created_at: "",
    can_edit: false,
    can_delete: false,
    questions: ordered.map((question, index) => ({
      ...question,
      quiz_points: Number(question.points),
      sort_order: index,
      part_index: null,
    })),
    groups,
  }
}

/**
 * Staff-side dry run: the exam exactly as a student pages through it —
 * cover page (title + instructions) first, then one question at a time.
 * Pure preview: nothing is answered, graded or recorded.
 */
export function ExamPreview({
  quiz,
  open,
  onOpenChange,
  sample = false,
}: {
  quiz: QuizDetail
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Random-draw exams: this is ONE possible paper, freshly drawn. */
  sample?: boolean
}) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")

  // -1 = the cover page.
  const [index, setIndex] = useState(-1)

  useEffect(() => {
    if (open) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- restart on open
      setIndex(-1)
    }
  }, [open])

  // Reading order mirrors the real paper: declared parts first, then
  // anything unassigned — the same order the player serves.
  const { parts, questions } = useMemo(() => {
    const quizParts = quiz.parts ?? []
    if (quizParts.length === 0)
      return { parts: quizParts, questions: quiz.questions }
    const buckets: (typeof quiz.questions)[] = quizParts.map(() => [])
    const none: typeof quiz.questions = []
    for (const q of quiz.questions) {
      if (q.part_index !== null && q.part_index < quizParts.length)
        buckets[q.part_index].push(q)
      else none.push(q)
    }
    return { parts: quizParts, questions: [...buckets.flat(), ...none] }
  }, [quiz])

  const question = index >= 0 ? questions[index] : null
  const currentPart =
    question !== null && question.part_index !== null
      ? parts[question.part_index]
      : undefined
  const firstOfPart =
    question !== null &&
    questions.findIndex((q) => q.part_index === question.part_index) === index
  const totalPoints =
    Number(quiz.total_points) ||
    questions.reduce((sum, q) => sum + q.quiz_points, 0)

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/20 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0"
          onPointerDownOutside={(e) => e.preventDefault()}
          onInteractOutside={(e) => e.preventDefault()}
        >
          <header className="flex h-14 shrink-0 items-center gap-3 border-b px-3 md:px-5">
            <Button
              variant="ghost"
              size="icon"
              className="text-muted-foreground"
              onClick={() => onOpenChange(false)}
              aria-label={tc("actions.close")}
            >
              <X className="size-5" />
            </Button>
            <div className="min-w-0">
              <DialogPrimitive.Title className="truncate text-sm font-semibold">
                {quiz.title}
              </DialogPrimitive.Title>
              <p className="text-xs text-muted-foreground">
                {t("exams.previewMode")}
              </p>
            </div>
            <div className="ml-auto flex items-center gap-2">
              {sample && (
                <Badge
                  variant="outline"
                  className="gap-1 border-info/40 bg-info/10 text-info"
                >
                  <Dices className="size-3" /> {t("exams.previewSample")}
                </Badge>
              )}
              <Badge
                variant="outline"
                className="gap-1 border-warning/40 bg-warning/10 text-warning"
              >
                <Eye className="size-3" /> {t("exams.preview")}
              </Badge>
            </div>
          </header>

          {/* progress */}
          {index >= 0 && (
            <div className="h-1 w-full bg-muted">
              <div
                className="h-1 bg-primary transition-all"
                style={{
                  width: `${((index + 1) / Math.max(1, questions.length)) * 100}%`,
                }}
              />
            </div>
          )}

          <main className="min-h-0 flex-1 overflow-y-auto">
            <div className="mx-auto w-full max-w-2xl px-4 py-8 md:py-12">
              {index === -1 ? (
                /* ── Cover: title + instructions, like the student lobby ── */
                <div className="space-y-6 text-center">
                  <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <FileQuestion className="size-7" />
                  </div>
                  <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                      {quiz.title}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                      {[
                        quiz.grade_level_name,
                        quiz.subject_name,
                        (quiz.section_names ?? []).join(", "),
                      ]
                        .filter(Boolean)
                        .join(" · ")}
                    </p>
                  </div>
                  <div className="mx-auto flex max-w-md flex-wrap items-center justify-center gap-2">
                    <Badge variant="outline" className="gap-1">
                      <FileQuestion className="size-3" />
                      {t("exams.pickedCount", {
                        count: questions.length,
                        points: totalPoints,
                      })}
                    </Badge>
                    {quiz.settings.duration_minutes ? (
                      <Badge variant="outline" className="gap-1">
                        <Clock className="size-3" />
                        {t("learn.minutes", {
                          count: quiz.settings.duration_minutes,
                        })}
                      </Badge>
                    ) : null}
                  </div>
                  {quiz.instructions && (
                    <div className="rounded-2xl border bg-card p-5 text-left shadow-xs">
                      <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {t("exams.instructions")}
                      </p>
                      <QuestionStem
                        html={quiz.instructions}
                        className="text-sm leading-relaxed"
                      />
                    </div>
                  )}
                  {parts.length > 0 && (
                    <div className="rounded-2xl border bg-card p-5 text-left shadow-xs">
                      <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        {t("exams.paperParts")}
                      </p>
                      <ul className="space-y-1.5">
                        {parts.map((part, pi) => {
                          const bucket = questions.filter(
                            (q) => q.part_index === pi
                          )
                          return (
                            <li
                              key={pi}
                              className="flex items-baseline justify-between gap-3 text-sm"
                            >
                              <span className="min-w-0 truncate font-medium">
                                {t("exams.partLabel", {
                                  numeral: romanNumeral(pi + 1),
                                })}
                                {part.title ? ` — ${part.title}` : ""}
                              </span>
                              <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                {t("exams.partSummary", {
                                  count: bucket.length,
                                  points: bucket.reduce(
                                    (sum, q) => sum + q.quiz_points,
                                    0
                                  ),
                                })}
                              </span>
                            </li>
                          )
                        })}
                      </ul>
                    </div>
                  )}
                  {questions.length > 0 ? (
                    <Button className="h-12 px-8" onClick={() => setIndex(0)}>
                      <Play className="size-4" /> {t("exams.previewStart")}
                    </Button>
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      {t("exams.previewNoQuestions")}
                    </p>
                  )}
                </div>
              ) : question ? (
                /* ── One question, exactly like the player ── */
                <div className="space-y-6">
                  {currentPart !== undefined &&
                    question.part_index !== null && (
                      <PartBanner
                        numeral={romanNumeral(question.part_index + 1)}
                        title={currentPart.title}
                        instructions={currentPart.instructions}
                        compact={!firstOfPart}
                      />
                    )}
                  {question.parent_id != null &&
                    quiz.groups?.[question.parent_id] && (
                      <PassageCard
                        group={quiz.groups[question.parent_id]}
                        defaultOpen={
                          questions.findIndex(
                            (q) => q.parent_id === question.parent_id
                          ) === index
                        }
                      />
                    )}
                  <div className="flex items-start justify-between gap-3">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                      {t("exams.previewQuestionOf", {
                        current: index + 1,
                        total: questions.length,
                      })}
                    </p>
                    <span className="shrink-0 rounded-full bg-accent px-2.5 py-1 text-xs font-medium text-muted-foreground">
                      {t("player.points", { count: question.quiz_points })}
                    </span>
                  </div>

                  <QuestionStem
                    html={question.body.stem}
                    className="text-base leading-relaxed font-medium md:text-lg"
                  />

                  {question.body.attachments &&
                    question.body.attachments.length > 0 && (
                      <QuestionAttachments
                        attachments={question.body.attachments}
                      />
                    )}

                  {/* Answer area — inert, options page like the real player. */}
                  {(question.type === "mcq_single" ||
                    question.type === "mcq_multi") && (
                    <div className="space-y-2">
                      {(question.body.options ?? []).map((option) => (
                        <div
                          key={option.id}
                          className="flex items-center gap-3 rounded-xl border px-4 py-3"
                        >
                          <span
                            className={cn(
                              "flex size-6 shrink-0 items-center justify-center border text-xs font-semibold text-muted-foreground uppercase",
                              question.type === "mcq_single"
                                ? "rounded-full"
                                : "rounded-md"
                            )}
                          >
                            {option.id}
                          </span>
                          <QuestionStem
                            html={option.text}
                            className="min-w-0 flex-1 text-sm"
                          />
                        </div>
                      ))}
                    </div>
                  )}
                  {question.type === "true_false" && (
                    <div className="grid grid-cols-2 gap-2">
                      <div className="rounded-xl border px-4 py-3 text-center text-sm font-medium">
                        {t("questions.true")}
                      </div>
                      <div className="rounded-xl border px-4 py-3 text-center text-sm font-medium">
                        {t("questions.false")}
                      </div>
                    </div>
                  )}
                  {(question.type === "short_answer" ||
                    question.type === "numeric" ||
                    question.type === "essay") && (
                    <div className="rounded-xl border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                      {t(`exams.previewInput.${question.type}`)}
                    </div>
                  )}
                  {question.type === "fill_blank" && (
                    <div className="rounded-xl border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                      {t("exams.previewInput.fill_blank")}
                    </div>
                  )}
                  {question.type === "matching" && (
                    <div className="grid gap-2 sm:grid-cols-2">
                      {(question.body.left ?? []).map((item) => (
                        <div
                          key={item.id}
                          className="rounded-xl border px-4 py-2.5 text-sm"
                        >
                          <QuestionStem html={item.text} />
                        </div>
                      ))}
                      {(question.body.right ?? []).map((item) => (
                        <div
                          key={item.id}
                          className="rounded-xl border border-dashed px-4 py-2.5 text-sm text-muted-foreground"
                        >
                          <QuestionStem html={item.text} />
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : null}
            </div>
          </main>

          {/* footer nav */}
          {index >= 0 && (
            <footer className="flex shrink-0 items-center justify-between gap-3 border-t px-4 py-3 md:px-6">
              <Button
                variant="outline"
                className="h-11"
                onClick={() => setIndex((i) => i - 1)}
              >
                <ArrowLeft className="size-4" />{" "}
                {index === 0 ? t("exams.previewCover") : tc("actions.back")}
              </Button>
              <p className="text-xs text-muted-foreground tabular-nums">
                {index + 1} / {questions.length}
              </p>
              {index < questions.length - 1 ? (
                <Button className="h-11" onClick={() => setIndex((i) => i + 1)}>
                  {tc("actions.next")} <ArrowRight className="size-4" />
                </Button>
              ) : (
                <Button
                  variant="outline"
                  className="h-11"
                  onClick={() => onOpenChange(false)}
                >
                  {t("exams.previewDone")}
                </Button>
              )}
            </footer>
          )}
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
