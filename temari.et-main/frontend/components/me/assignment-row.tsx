"use client"

import { CheckCircle2 } from "lucide-react"
import { useState } from "react"

import { formatDateTime } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { useTranslation } from "@/lib/i18n"
import type { MeAssignment } from "@/lib/types"

/**
 * Quiz-kind work never produces an AssignmentSubmission — completion lives on
 * `quiz_progress` (a submitted/graded attempt). Treat that as "turned in" so a
 * finished quiz drops off the to-do feed and stops showing a "Submit" badge.
 */
export function isTurnedIn(assignment: MeAssignment): boolean {
  if (assignment.submission !== null) return true
  const progress = assignment.quiz_progress
  return assignment.kind === "quiz" && progress != null && progress.status !== "in_progress"
}

/** One tappable assignment line for the student's classwork registers. */
export function AssignmentRow({
  assignment,
  onOpen,
}: {
  assignment: MeAssignment
  onOpen: () => void
}) {
  const { t } = useTranslation("lms")
  const submission = assignment.submission
  const quizDone =
    assignment.kind === "quiz" &&
    assignment.quiz_progress != null &&
    assignment.quiz_progress.status !== "in_progress"
  const quizScore = assignment.quiz_progress?.score ?? null

  // Captured once — render must stay pure (no Date.now() mid-render).
  const [renderedAt] = useState(() => Date.now())
  const overdue =
    !quizDone &&
    submission === null &&
    assignment.due_at !== null &&
    new Date(assignment.due_at).getTime() < renderedAt

  return (
    <button
      type="button"
      onClick={onOpen}
      className="pressable flex w-full items-center gap-3 rounded-2xl border bg-card p-4 text-left shadow-xs transition-colors hover:bg-accent/50"
    >
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">{assignment.title}</p>
        <p className="text-xs text-muted-foreground">
          {[
            assignment.subject_name,
            assignment.due_at
              ? t("learn.due", { date: formatDateTime(assignment.due_at) })
              : t("learn.noDue"),
          ]
            .filter(Boolean)
            .join(" · ")}
        </p>
      </div>
      {quizDone ? (
        <Badge variant="outline" className="gap-1 border-transparent bg-success/10 text-success">
          <CheckCircle2 className="size-3" />
          {quizScore !== null
            ? `${Number(quizScore)}${
                assignment.quiz_progress?.max_score != null
                  ? `/${Number(assignment.quiz_progress.max_score)}`
                  : ""
              }`
            : t("learn.quizDone")}
        </Badge>
      ) : submission === null ? (
        overdue ? (
          <Badge variant="outline" className="border-transparent bg-destructive/10 text-destructive">
            {t("learn.overdue")}
          </Badge>
        ) : (
          <Badge variant="outline" className="border-transparent bg-info/10 text-info">
            {t("learn.submit")}
          </Badge>
        )
      ) : submission.score !== null ? (
        <Badge variant="outline" className="gap-1 border-transparent bg-success/10 text-success">
          <CheckCircle2 className="size-3" />
          {Number(submission.score)}
          {assignment.max_score !== null ? `/${Number(assignment.max_score)}` : ""}
        </Badge>
      ) : (
        <Badge variant="outline" className="border-transparent bg-warning/10 text-warning">
          {submission.is_late ? t("assignments.late") : t("attempts.statuses.submitted")}
        </Badge>
      )}
    </button>
  )
}
