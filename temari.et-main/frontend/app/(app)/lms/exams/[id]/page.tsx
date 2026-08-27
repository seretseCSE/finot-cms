"use client"

import {
  CheckCircle2,
  Clock,
  Eye,
  FileKey2,
  FileText,
  KeyRound,
  ListChecks,
  Loader2,
  Megaphone,
  Pencil,
  Printer,
  RefreshCw,
  Send,
  Users,
  XCircle,
} from "lucide-react"
import { useParams } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { ExamEditor } from "@/components/lms/exam-editor"
import { ExamPreview } from "@/components/lms/exam-preview"
import { QuizAttemptsPanel } from "@/components/lms/quiz-attempts-panel"
import { formatDateTime, QuizStatusBadge } from "@/components/lms/shared"
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useDocumentDownload } from "@/lib/use-document"
import { useTranslation } from "@/lib/i18n"
import type { QuizDetail } from "@/lib/types"

export default function ExamDetailPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()
  const quizId = Number(params.id)

  const [quiz, setQuiz] = useState<QuizDetail | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [previewOpen, setPreviewOpen] = useState(false)
  // Random-draw exams: a freshly drawn sample paper from GET …/preview.
  const [previewSample, setPreviewSample] = useState<QuizDetail | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [closeConfirm, setCloseConfirm] = useState(false)
  // Bumped on page-level actions (publish/close/sync) to reload the attempts panel.
  const [attemptsKey, setAttemptsKey] = useState(0)
  const [working, setWorking] = useState(false)
  const { print: printDocument, generating } = useDocumentDownload()

  const load = useCallback(() => {
    apiFetch<{ data: QuizDetail }>(`/quizzes/${quizId}`)
      .then((res) => setQuiz(res.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic")),
      )
  }, [quizId, tc])

  useEffect(() => {
    load()
  }, [load])

  async function action(path: string, success: string) {
    setWorking(true)
    try {
      const res = await apiFetch<{ message?: string; meta?: { count?: number } }>(path, {
        method: "POST",
        body: {},
      })
      toast.success(res.message ?? success)
      load()
      setAttemptsKey((key) => key + 1)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  /** `manual` results policy: flip the release switch (settings are replaced whole). */
  async function releaseResults() {
    if (!quiz) return
    setWorking(true)
    try {
      await apiFetch(`/quizzes/${quiz.id}`, {
        method: "PUT",
        body: { settings: { ...quiz.settings, results_released: true } },
      })
      toast.success(t("exams.resultsReleased"))
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  /** Open the student-view dry run — drawing a sample paper if needed. */
  async function openPreview() {
    if (!quiz) return
    if (!quiz.draw?.length) {
      setPreviewOpen(true)
      return
    }
    setPreviewLoading(true)
    try {
      const res = await apiFetch<{
        data: { questions: QuizDetail["questions"]; groups: QuizDetail["groups"] }
      }>(`/quizzes/${quiz.id}/preview`)
      setPreviewSample({ ...quiz, questions: res.data.questions, groups: res.data.groups })
      setPreviewOpen(true)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setPreviewLoading(false)
    }
  }

  if (quiz === null) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("exams.title")} backHref="/lms/exams" />
        <div className="page-gutter space-y-4">
          <Skeleton className="h-28 w-full rounded-2xl" />
          <Skeleton className="h-64 w-full rounded-2xl" />
        </div>
      </div>
    )
  }

  const isDraft = quiz.status === "draft"
  const isPublished = quiz.status === "published"
  const manualPending = quiz.settings.results_policy === "manual" && !quiz.settings.results_released
  const expected = quiz.expected_takers ?? 0
  const taken = quiz.takers_count ?? 0
  const completion = expected > 0 ? Math.round((taken / expected) * 100) : null

  return (
    <div className="space-y-6">
      <PageHeader
        title={quiz.title}
        description={
          quiz.is_platform
            ? [quiz.grade_level_name, quiz.subject_name].filter(Boolean).join(" · ")
            : [
                quiz.grade_level_name,
                quiz.subject_name,
                (quiz.section_names ?? []).map((name) => name).join(", "),
              ]
                .filter(Boolean)
                .join(" · ")
        }
        backHref="/lms/exams"
        actions={
          <div className="flex flex-wrap gap-2">
            {(quiz.questions.length > 0 || Boolean(quiz.draw?.length)) && (
              <Button variant="outline" className="h-11" disabled={previewLoading} onClick={() => void openPreview()}>
                {previewLoading ? <Loader2 className="size-4 animate-spin" /> : <Eye className="size-4" />}
                {t("exams.preview")}
              </Button>
            )}
            {quiz.questions.length > 0 && !quiz.draw?.length && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" className="h-11" disabled={generating}>
                    {generating ? <Loader2 className="size-4 animate-spin" /> : <Printer className="size-4" />}
                    {t("exams.print")}
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64">
                  <DropdownMenuItem
                    onClick={() => printDocument("exam_paper", quiz.id, { variant: "questions" })}
                  >
                    <FileText className="size-4" /> {t("exams.printPaper")}
                  </DropdownMenuItem>
                  {quiz.can_edit && (
                    <DropdownMenuItem
                      onClick={() => printDocument("exam_paper", quiz.id, { variant: "answer_key" })}
                    >
                      <FileKey2 className="size-4" /> {t("exams.printKey")}
                    </DropdownMenuItem>
                  )}
                </DropdownMenuContent>
              </DropdownMenu>
            )}
            {quiz.can_edit && (
              <Button variant="outline" className="h-11" onClick={() => setEditOpen(true)}>
                <Pencil className="size-4" /> {tc("actions.edit")}
              </Button>
            )}
            {isDraft && (
              <Button className="h-11" onClick={() => action(`/quizzes/${quiz.id}/publish`, t("exams.published"))} loading={working}>
                <Send className="size-4" /> {t("exams.publish")}
              </Button>
            )}
            {isPublished && (
              <Button variant="outline" className="h-11" onClick={() => setCloseConfirm(true)} loading={working}>
                <XCircle className="size-4" /> {t("exams.close")}
              </Button>
            )}
            {manualPending && !isDraft && (
              <Button variant="outline" className="h-11" onClick={releaseResults} loading={working}>
                <Megaphone className="size-4" /> {t("exams.releaseResults")}
              </Button>
            )}
            {quiz.assessment_id !== null && (
              <Button
                variant="outline"
                className="h-11"
                onClick={() => action(`/quizzes/${quiz.id}/sync`, t("exams.synced", { count: "" }))}
                loading={working}
              >
                <RefreshCw className="size-4" /> {t("exams.syncGrades")}
              </Button>
            )}
          </div>
        }
      />

      <div className="page-gutter space-y-4">
        {/* Participation: expected vs actual */}
        {!quiz.is_platform && expected > 0 && (
          <div className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <Users className="size-4.5" />
                </div>
                <div>
                  <p className="text-sm font-medium">{t("exams.participation")}</p>
                  <p className="text-xs text-muted-foreground">
                    {t("exams.participationDetail", { taken, expected })}
                  </p>
                </div>
              </div>
              <p className="text-2xl font-semibold tabular-nums">
                {completion}
                <span className="text-sm font-normal text-muted-foreground">%</span>
              </p>
            </div>
            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
              <div
                className={`h-2 rounded-full transition-all ${
                  (completion ?? 0) >= 80 ? "bg-success" : (completion ?? 0) >= 40 ? "bg-warning" : "bg-primary"
                }`}
                style={{ width: `${Math.min(100, completion ?? 0)}%` }}
              />
            </div>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2">
          <QuizStatusBadge status={quiz.status} />
          <Badge variant="secondary">{t(`exams.kinds.${quiz.kind}`)}</Badge>
          <Badge variant="outline" className="gap-1">
            <ListChecks className="size-3" />
            {quiz.draw?.length
              ? t("exams.pickedCount", {
                  count: quiz.draw.reduce((sum, rule) => sum + rule.count, 0),
                  points: "~",
                })
              : t("exams.pickedCount", {
                  count: quiz.questions.length,
                  points: Number(quiz.total_points) || quiz.questions.reduce((sum, question) => sum + question.quiz_points, 0),
                })}
          </Badge>
          {(quiz.section_names ?? []).map((name) => (
            <Badge key={name} variant="outline" className="gap-1 border-transparent bg-info/10 text-info">
              {name}
            </Badge>
          ))}
          {quiz.settings.duration_minutes ? (
            <Badge variant="outline" className="gap-1">
              <Clock className="size-3" /> {t("learn.minutes", { count: quiz.settings.duration_minutes })}
            </Badge>
          ) : null}
          {quiz.has_access_code && (
            <Badge variant="outline" className="gap-1">
              <KeyRound className="size-3" /> {t("exams.accessCode")}
            </Badge>
          )}
          {quiz.assessment_name && (
            <Badge variant="outline" className="gap-1 border-transparent bg-primary/10 text-primary">
              <CheckCircle2 className="size-3" /> {quiz.assessment_name}
            </Badge>
          )}
          {quiz.settings.closes_at && (
            <span className="text-xs text-muted-foreground">
              {t("learn.closesAt", { date: formatDateTime(quiz.settings.closes_at) })}
            </span>
          )}
        </div>
      </div>

      <QuizAttemptsPanel quizId={quiz.id} reloadKey={attemptsKey} onChanged={load} />

      <ExamEditor
        quiz={quiz}
        platform={quiz.is_platform}
        open={editOpen}
        onOpenChange={setEditOpen}
        onSaved={load}
      />

      <ExamPreview
        quiz={quiz.draw?.length ? (previewSample ?? quiz) : quiz}
        sample={Boolean(quiz.draw?.length)}
        open={previewOpen}
        onOpenChange={(open) => {
          setPreviewOpen(open)
          if (!open) setPreviewSample(null)
        }}
      />

      <AlertDialog open={closeConfirm} onOpenChange={setCloseConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("exams.closeConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("exams.closeConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                setCloseConfirm(false)
                action(`/quizzes/${quiz.id}/close`, t("exams.closed"))
              }}
            >
              {t("exams.close")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
