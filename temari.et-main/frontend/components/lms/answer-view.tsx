"use client"

import { Check, X } from "lucide-react"

import { QuestionStem, stemText } from "@/components/lms/question-content"
import { useTranslation } from "@/lib/i18n"
import type { QuestionAnswerKey, QuestionBody, QuestionType } from "@/lib/types"

/**
 * Read-only rendering of a question with a taker's answer — shared by the
 * teacher grading screen and the student result review. When `answerKey` is
 * present, correct choices are marked; it is simply absent for takers whose
 * quiz doesn't reveal answers.
 */
export function AnswerView({
  type,
  body,
  answer,
  answerKey,
}: {
  type: QuestionType
  body: QuestionBody
  answer: unknown
  answerKey?: QuestionAnswerKey | null
}) {
  const { t } = useTranslation("lms")

  if (type === "mcq_single" || type === "mcq_multi") {
    const pickedIds = new Set(
      (Array.isArray(answer) ? answer : answer !== null && answer !== undefined ? [answer] : []).map(
        String,
      ),
    )
    const correctIds = new Set(
      answerKey
        ? (Array.isArray(answerKey.correct) ? answerKey.correct : [answerKey.correct]).map(String)
        : [],
    )

    return (
      <ul className="space-y-1">
        {(body.options ?? []).map((option) => {
          const picked = pickedIds.has(option.id)
          const correct = correctIds.has(option.id)
          return (
            <li
              key={option.id}
              className={`flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm ${
                picked && answerKey
                  ? correct
                    ? "bg-success/10 text-success"
                    : "bg-destructive/10 text-destructive"
                  : picked
                    ? "bg-primary/10"
                    : correct
                      ? "bg-success/5 text-success"
                      : ""
              }`}
            >
              <span className="w-4 text-xs font-medium uppercase opacity-70">{option.id}</span>
              <QuestionStem html={option.text} className="min-w-0 flex-1" />
              {picked && answerKey ? (
                correct ? (
                  <Check className="size-3.5 shrink-0" />
                ) : (
                  <X className="size-3.5 shrink-0" />
                )
              ) : correct && answerKey ? (
                <Check className="size-3.5 shrink-0 opacity-60" />
              ) : null}
            </li>
          )
        })}
      </ul>
    )
  }

  if (type === "true_false") {
    const given = answer === true || answer === "true" || answer === 1
    const hasAnswer = answer !== null && answer !== undefined && answer !== ""
    return (
      <div className="space-y-1 text-sm">
        <p>
          <span className="text-muted-foreground">{t("attempts.studentAnswer")}: </span>
          {hasAnswer ? (given ? t("questions.true") : t("questions.false")) : t("attempts.noAnswer")}
        </p>
        {answerKey && typeof answerKey.correct === "boolean" && (
          <p className="text-success">
            <span className="text-muted-foreground">{t("attempts.correctAnswer")}: </span>
            {answerKey.correct ? t("questions.true") : t("questions.false")}
          </p>
        )}
      </div>
    )
  }

  if (type === "matching") {
    const given = (answer ?? {}) as Record<string, string>
    const pairs = (answerKey?.pairs ?? {}) as Record<string, string>
    // Matching feedback is inline prose — plain text keeps it readable.
    const rightById = new Map((body.right ?? []).map((o) => [o.id, stemText(o.text)]))
    return (
      <ul className="space-y-1 text-sm">
        {(body.left ?? []).map((item) => {
          const pick = given[item.id]
          const correct = answerKey ? pairs[item.id] : undefined
          const isRight = correct !== undefined && String(pick) === String(correct)
          return (
            <li key={item.id} className="flex flex-wrap items-center gap-1.5">
              <span className="font-medium">{stemText(item.text)}</span>
              <span className="text-muted-foreground">→</span>
              <span className={answerKey ? (isRight ? "text-success" : "text-destructive") : ""}>
                {pick ? (rightById.get(pick) ?? pick) : t("attempts.noAnswer")}
              </span>
              {answerKey && !isRight && correct !== undefined && (
                <span className="text-xs text-success">({rightById.get(correct) ?? correct})</span>
              )}
            </li>
          )
        })}
      </ul>
    )
  }

  if (type === "fill_blank") {
    const given = Array.isArray(answer) ? answer : []
    const blanks = answerKey?.blanks ?? []
    const count = Math.max(given.length, blanks.length)
    return (
      <ul className="space-y-1 text-sm">
        {Array.from({ length: count }).map((_, index) => (
          <li key={index} className="flex flex-wrap items-center gap-1.5">
            <span className="text-xs text-muted-foreground">{index + 1}.</span>
            <span>{given[index] ? String(given[index]) : t("attempts.noAnswer")}</span>
            {blanks[index] && (
              <span className="text-xs text-success">({blanks[index].join(" / ")})</span>
            )}
          </li>
        ))}
      </ul>
    )
  }

  // short_answer / numeric / essay — free text. The canonical shape is a raw
  // string/number, but tolerate legacy wrapped rows ({text} / {value}).
  const unwrapped =
    answer !== null && typeof answer === "object"
      ? ((answer as { text?: unknown; value?: unknown }).text ??
        (answer as { value?: unknown }).value ??
        null)
      : answer
  const text =
    unwrapped === null || unwrapped === undefined || unwrapped === ""
      ? null
      : typeof unwrapped === "object"
        ? JSON.stringify(unwrapped)
        : String(unwrapped)

  return (
    <div className="space-y-1.5 text-sm">
      {text === null ? (
        <p className="text-muted-foreground">{t("attempts.noAnswer")}</p>
      ) : (
        <p className="whitespace-pre-wrap rounded-lg bg-muted/50 px-3 py-2">{text}</p>
      )}
      {answerKey?.value !== undefined && (
        <p className="text-success">
          <span className="text-muted-foreground">{t("attempts.correctAnswer")}: </span>
          {answerKey.value}
          {answerKey.tolerance ? ` (± ${answerKey.tolerance})` : ""}
        </p>
      )}
      {answerKey?.accepted && answerKey.accepted.length > 0 && (
        <p className="text-success">
          <span className="text-muted-foreground">{t("attempts.correctAnswer")}: </span>
          {answerKey.accepted.join(" / ")}
        </p>
      )}
      {answerKey?.rubric && (
        <p className="text-xs text-muted-foreground">{answerKey.rubric}</p>
      )}
    </div>
  )
}
