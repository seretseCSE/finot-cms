"use client"

import { ExternalLink, Eye, FileQuestion, Loader2 } from "lucide-react"
import dynamic from "next/dynamic"
import * as React from "react"

import { romanNumeral } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { QuizDetail } from "@/lib/types"
import { cn } from "@/lib/utils"

const ExamPreview = dynamic(
  () => import("@/components/lms/exam-preview").then((m) => m.ExamPreview),
  { ssr: false },
)

/**
 * The `exam_preview` block: after the AI creates or edits an exam it drops
 * `{"quiz_id": N}` and the chat renders THIS live card instead of a wall of
 * question text — title, class, parts and counts, plus the exact same
 * full-screen ExamPreview the studio uses (fetched fresh on every open, so
 * later edits show up). Authority is the API's: the card only shows what
 * GET /quizzes/{id} lets this user see.
 */

export interface AiExamBlock {
  quiz_id: number
}

export function parseExamBlock(json: string): AiExamBlock | null {
  try {
    const raw = JSON.parse(json) as { quiz_id?: unknown }
    return typeof raw.quiz_id === "number" && Number.isInteger(raw.quiz_id) && raw.quiz_id > 0
      ? { quiz_id: raw.quiz_id }
      : null
  } catch {
    return null
  }
}

const STATUS_TINT: Record<string, string> = {
  draft: "border-warning/40 bg-warning/10 text-warning",
  published: "border-success/40 bg-success/10 text-success",
  closed: "border-muted-foreground/30 bg-muted text-muted-foreground",
  archived: "border-muted-foreground/30 bg-muted text-muted-foreground",
}

export function AiExamPreviewCard({ block }: { block: AiExamBlock }) {
  const { t } = useTranslation("ai")
  const { t: tl } = useTranslation("lms")

  const [quiz, setQuiz] = React.useState<QuizDetail | null>(null)
  const [error, setError] = React.useState<string | null>(null)
  const [open, setOpen] = React.useState(false)
  const [opening, setOpening] = React.useState(false)

  const load = React.useCallback(async (): Promise<QuizDetail | null> => {
    try {
      const res = await apiFetch<{ data: QuizDetail }>(`/quizzes/${block.quiz_id}`)
      setQuiz(res.data)
      setError(null)
      return res.data
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("examCard.failed"))
      return null
    }
  }, [block.quiz_id, t])

  React.useEffect(() => {
    // load() is async: every setState inside it runs after the fetch resolves, never in
    // the same render pass.
    // eslint-disable-next-line react-hooks/set-state-in-effect -- see above
    void load()
  }, [load])

  // Preview always re-fetches first — the AI may have edited the paper since
  // this card was rendered.
  const openPreview = async () => {
    if (opening) return
    setOpening(true)
    const fresh = await load()
    setOpening(false)
    if (fresh) setOpen(true)
  }

  if (error !== null) {
    return (
      <div className="my-3 rounded-2xl border bg-card px-3.5 py-3 text-sm text-muted-foreground shadow-xs">
        {error}
      </div>
    )
  }

  if (quiz === null) {
    return (
      <div className="my-3 flex items-center gap-2.5 rounded-2xl border bg-card px-3.5 py-3 shadow-xs">
        <Loader2 className="size-4 animate-spin text-muted-foreground" aria-hidden />
        <span className="text-sm text-muted-foreground">{t("examCard.loading")}</span>
      </div>
    )
  }

  const meta = [quiz.grade_level_name, quiz.subject_name, (quiz.section_names ?? []).join(", ")]
    .filter(Boolean)
    .join(" · ")
  const totalPoints =
    Number(quiz.total_points) || quiz.questions.reduce((sum, q) => sum + q.quiz_points, 0)
  const duration = quiz.settings?.duration_minutes
  const parts = quiz.parts ?? []

  return (
    <div className="my-3 overflow-hidden rounded-2xl border bg-card shadow-xs">
      <div className="flex items-center gap-2.5 border-b bg-muted/40 px-3.5 py-2.5">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
          <FileQuestion className="size-4" aria-hidden />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-medium">{quiz.title}</span>
          {meta !== "" && (
            <span className="block truncate text-xs text-muted-foreground">{meta}</span>
          )}
        </span>
        <Badge variant="outline" className={cn("shrink-0 gap-1", STATUS_TINT[quiz.status])}>
          {tl(`exams.statuses.${quiz.status}`)}
        </Badge>
      </div>

      <div className="space-y-2 px-3.5 py-3">
        <p className="text-xs text-muted-foreground">
          {tl("exams.pickedCount", { count: quiz.questions.length, points: totalPoints })}
          {duration ? ` · ${t("examCard.minutes", { count: duration })}` : ""}
        </p>
        {parts.length > 0 && (
          <ul className="space-y-1">
            {parts.map((part, index) => {
              const count = quiz.questions.filter((q) => q.part_index === index).length
              return (
                <li key={index} className="flex items-baseline gap-2 text-sm">
                  <span className="font-medium">
                    {tl("exams.partLabel", { numeral: romanNumeral(index + 1) })}
                    {part.title ? ` — ${part.title}` : ""}
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {t("examCard.partQuestions", { count })}
                  </span>
                </li>
              )
            })}
          </ul>
        )}
      </div>

      <div className="flex items-center gap-2 border-t px-3.5 py-2.5">
        <Button
          className="h-10 flex-1 rounded-full"
          onClick={() => void openPreview()}
          loading={opening}
        >
          {!opening && <Eye className="size-4" aria-hidden />}
          {tl("exams.preview")}
        </Button>
        <a
          href={`/lms/exams/${quiz.id}`}
          className="inline-flex min-h-10 items-center gap-1.5 px-2 text-sm font-medium text-primary"
        >
          <ExternalLink className="size-4" aria-hidden />
          {t("examCard.open")}
        </a>
      </div>

      {open && <ExamPreview quiz={quiz} open={open} onOpenChange={setOpen} />}
    </div>
  )
}
