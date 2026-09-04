"use client"

import { CheckCircle2, ClipboardList, Clock, MessageCircle, Pencil, RefreshCw, Send, XCircle } from "lucide-react"
import { useParams, useRouter } from "next/navigation"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { AssignmentEditor } from "@/components/lms/assignment-editor"
import { AssignmentThreadSheet, type ThreadStudent } from "@/components/lms/assignment-thread-sheet"
import { QuestionStem } from "@/components/lms/question-content"
import { QuizAttemptsPanel } from "@/components/lms/quiz-attempts-panel"
import { AssignmentStatusBadge, formatDateTime } from "@/components/lms/shared"
import { SubmissionGradingSheet } from "@/components/lms/submission-grading-sheet"
import { AttachmentTile } from "@/components/ui/attachment"
import { useMediaPreview } from "@/components/ui/media-preview"
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
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AssignmentThread, LmsAssignment, LmsSubmission, Paginated } from "@/lib/types"

export default function AssignmentDetailPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()
  const router = useRouter()
  const assignmentId = Number(params.id)

  const [assignment, setAssignment] = useState<LmsAssignment | null>(null)
  const [submissions, setSubmissions] = useState<LmsSubmission[] | null>(null)
  const [threads, setThreads] = useState<AssignmentThread[]>([])
  const [openThread, setOpenThread] = useState<ThreadStudent | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [grading, setGrading] = useState<LmsSubmission | null>(null)
  const [closeConfirm, setCloseConfirm] = useState(false)
  const [working, setWorking] = useState(false)
  const { openPreview, previewDialog } = useMediaPreview()

  const loadThreads = useCallback(() => {
    apiFetch<{ data: AssignmentThread[] }>(`/assignments/${assignmentId}/threads`)
      .then((res) => setThreads(res.data))
      .catch(() => setThreads([]))
  }, [assignmentId])

  const load = useCallback(() => {
    apiFetch<{ data: LmsAssignment }>(`/assignments/${assignmentId}`)
      .then((res) => setAssignment(res.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic")),
      )
    apiFetch<Paginated<LmsSubmission>>(`/assignments/${assignmentId}/submissions?per_page=100`)
      .then((res) => setSubmissions(res.data))
      .catch(() => setSubmissions([]))
    loadThreads()
  }, [assignmentId, tc, loadThreads])

  useEffect(() => {
    load()
  }, [load])

  async function action(path: string, success: string) {
    setWorking(true)
    try {
      const res = await apiFetch<{ message?: string }>(path, { method: "POST", body: {} })
      toast.success(res.message ?? success)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  // One row per student: their submission (if any) merged with their message
  // thread (if any) — so a question asked BEFORE submitting is just as
  // visible as graded work, in a single table.
  interface StudentRow {
    id: number
    student_id: number
    student_name: string
    student_public_id: string | null
    student_photo_url: string | null
    submission: LmsSubmission | null
    submitted_at: string | null
    is_late: boolean
    status: "submitted" | "graded" | "returned" | "none"
    score: number | null
    messages_count: number
    awaiting_reply: boolean
  }

  const rows = useMemo<StudentRow[]>(() => {
    const byStudent = new Map<number, StudentRow>()
    for (const submission of submissions ?? []) {
      byStudent.set(submission.student_id, {
        id: submission.student_id,
        student_id: submission.student_id,
        student_name: submission.student_name ?? "",
        student_public_id: submission.student_public_id ?? null,
        student_photo_url: submission.student_photo_url ?? null,
        submission,
        submitted_at: submission.submitted_at,
        is_late: submission.is_late,
        status: submission.status,
        score: submission.score,
        messages_count: 0,
        awaiting_reply: false,
      })
    }
    for (const thread of threads) {
      const existing = byStudent.get(thread.student_id)
      if (existing) {
        existing.messages_count = thread.messages_count
        existing.awaiting_reply = thread.awaiting_reply
      } else {
        byStudent.set(thread.student_id, {
          id: thread.student_id,
          student_id: thread.student_id,
          student_name: thread.student_name,
          student_public_id: thread.student_public_id,
          student_photo_url: thread.student_photo_url,
          submission: null,
          submitted_at: null,
          is_late: false,
          status: "none",
          score: null,
          messages_count: thread.messages_count,
          awaiting_reply: thread.awaiting_reply,
        })
      }
    }
    return [...byStudent.values()]
  }, [submissions, threads])

  const columns: DataTableColumn<StudentRow>[] = [
    {
      key: "student_name",
      label: t("attempts.taker"),
      primary: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <PersonAvatar name={row.student_name} photoUrl={row.student_photo_url} />
          <div className="min-w-0">
            <p className="truncate font-medium">{row.student_name}</p>
            {row.student_public_id && (
              <p className="text-xs text-muted-foreground">{row.student_public_id}</p>
            )}
          </div>
        </div>
      ),
    },
    {
      key: "submitted_at",
      label: t("attempts.submitted"),
      sortable: true,
      render: (row) =>
        row.submitted_at === null ? (
          <span className="text-muted-foreground">—</span>
        ) : (
          <div>
            <span>{formatDateTime(row.submitted_at)}</span>
            {row.is_late && (
              <Badge variant="outline" className="ml-2 border-transparent bg-warning/10 text-warning">
                {t("assignments.late")}
              </Badge>
            )}
          </div>
        ),
    },
    {
      key: "status",
      label: tc("columns.status"),
      render: (row) => (
        <Badge
          variant="outline"
          className={`border-transparent ${
            row.status === "none"
              ? "bg-muted text-muted-foreground"
              : row.status === "submitted"
                ? "bg-warning/10 text-warning"
                : "bg-success/10 text-success"
          }`}
        >
          {row.status === "none"
            ? t("assignments.notSubmitted")
            : row.status === "submitted"
              ? t("attempts.statuses.submitted")
              : t("assignments.graded")}
        </Badge>
      ),
    },
    {
      key: "score",
      label: t("assignments.score"),
      sortable: true,
      render: (row) => (
        <span className="tabular-nums">
          {row.score !== null
            ? `${Number(row.score)}${assignment?.max_score !== null && assignment ? ` / ${Number(assignment.max_score)}` : ""}`
            : "—"}
        </span>
      ),
    },
    {
      key: "messages_count",
      label: t("assignments.chat"),
      sortable: true,
      render: (row) => (
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation()
            setOpenThread(row)
          }}
          className="pressable inline-flex h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-medium hover:bg-accent"
          aria-label={t("assignments.chat")}
        >
          <MessageCircle className="size-3.5 text-muted-foreground" />
          {row.messages_count > 0 ? row.messages_count : ""}
          {row.awaiting_reply && <span className="size-2 rounded-full bg-warning" />}
        </button>
      ),
    },
  ]

  if (assignment === null) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("assignments.title")} backHref="/lms/assignments" />
        <div className="page-gutter space-y-4">
          <Skeleton className="h-28 w-full rounded-2xl" />
          <Skeleton className="h-64 w-full rounded-2xl" />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={assignment.title}
        description={[assignment.subject_name, assignment.grade_level_name, assignment.section_name]
          .filter(Boolean)
          .join(" · ")}
        backHref="/lms/assignments"
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" className="h-11" onClick={() => setEditOpen(true)}>
              <Pencil className="size-4" /> {tc("actions.edit")}
            </Button>
            {assignment.status === "draft" && (
              <Button
                className="h-11"
                onClick={() => action(`/assignments/${assignment.id}/publish`, t("assignments.published"))}
                loading={working}
              >
                <Send className="size-4" /> {t("assignments.publish")}
              </Button>
            )}
            {assignment.status === "published" && (
              <Button variant="outline" className="h-11" onClick={() => setCloseConfirm(true)} loading={working}>
                <XCircle className="size-4" /> {t("assignments.close")}
              </Button>
            )}
            {assignment.assessment_id !== null && (
              <Button
                variant="outline"
                className="h-11"
                onClick={() => action(`/assignments/${assignment.id}/sync`, t("assignments.synced", { count: "" }))}
                loading={working}
              >
                <RefreshCw className="size-4" /> {t("assignments.syncGrades")}
              </Button>
            )}
          </div>
        }
      />

      <div className="page-gutter space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          <AssignmentStatusBadge status={assignment.status} />
          {assignment.due_at && (
            <Badge variant="outline" className="gap-1">
              <Clock className="size-3" /> {t("learn.due", { date: formatDateTime(assignment.due_at) })}
            </Badge>
          )}
          {assignment.max_score !== null && (
            <Badge variant="outline">/{Number(assignment.max_score)}</Badge>
          )}
          {assignment.assessment_name && (
            <Badge variant="outline" className="gap-1 border-transparent bg-primary/10 text-primary">
              <CheckCircle2 className="size-3" /> {assignment.assessment_name}
            </Badge>
          )}
          <Badge variant="secondary">{t(`assignments.kinds.${assignment.kind}`)}</Badge>
          {assignment.kind === "standard" && assignment.submission_types.length > 0 && (
            <Badge variant="secondary">
              {assignment.submission_types
                .map((type) =>
                  t(
                    `assignments.type${
                      { text: "Text", file: "File", photo: "Photo", audio: "Audio", link: "Link" }[type]
                    }`,
                  ),
                )
                .join(" · ")}
            </Badge>
          )}
        </div>

        {assignment.instructions && (
          <QuestionStem html={assignment.instructions} className="max-w-2xl text-sm text-muted-foreground" />
        )}

        {assignment.attachments.length > 0 && (
          <div className="grid max-w-2xl gap-1.5 sm:grid-cols-2">
            {assignment.attachments.map((file, index) => (
              <AttachmentTile
                key={index}
                file={file}
                onPreview={() => openPreview(assignment.attachments, index)}
              />
            ))}
          </div>
        )}

      </div>

      {/* Quiz-kind work lives in the exam player — its attempts (not
          assignment_submissions) are the turn-ins. Bridge to the exam grading
          lane and show completion, instead of an empty/misleading queue. */}
      {assignment.kind === "quiz" ? (
        <>
          <div className="page-gutter space-y-4">
            <div className="rounded-2xl border bg-card p-5 shadow-xs">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                  <p className="text-sm font-semibold">{t("assignments.quizWorkTitle")}</p>
                  <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                    {t("assignments.quizWorkDesc")}
                  </p>
                </div>
                {assignment.quiz_id !== null && (
                  <Button
                    variant="outline"
                    className="h-11 shrink-0"
                    onClick={() => router.push(`/lms/exams/${assignment.quiz_id}`)}
                  >
                    <ClipboardList className="size-4" /> {t("assignments.manageQuiz")}
                  </Button>
                )}
              </div>

              {assignment.quiz_stats && assignment.quiz_stats.expected_takers > 0 && (
                <div className="mt-5 space-y-1.5">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">{t("exams.completion")}</span>
                    <span className="font-medium tabular-nums">
                      {assignment.quiz_stats.takers_count} / {assignment.quiz_stats.expected_takers}
                      {` · ${Math.round((assignment.quiz_stats.takers_count / assignment.quiz_stats.expected_takers) * 100)}%`}
                    </span>
                  </div>
                  <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-success transition-all"
                      style={{
                        width: `${Math.min(100, Math.round((assignment.quiz_stats.takers_count / assignment.quiz_stats.expected_takers) * 100))}%`,
                      }}
                    />
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* attempts ARE the turn-ins: review & grade each sitting inline */}
          {assignment.quiz_id !== null && (
            <QuizAttemptsPanel
              quizId={assignment.quiz_id}
              onChanged={load}
              exportFilename="quiz-attempts"
            />
          )}

          {/* students who messaged about the quiz still surface here */}
          {rows.some((row) => row.messages_count > 0) && (
            <div className="page-gutter space-y-2">
              <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("assignments.chat")}
              </h2>
              <DataTable
                columns={columns.filter((col) => col.key === "student_name" || col.key === "messages_count")}
                data={rows.filter((row) => row.messages_count > 0)}
                loading={submissions === null}
                searchKeys={["student_name", "student_public_id"]}
                searchPlaceholder={tc("actions.search")}
                onRowClick={(row) => setOpenThread(row)}
                emptyMessage={t("assignments.queueEmpty")}
                exportFilename="quiz-questions"
              />
            </div>
          )}
        </>
      ) : (
        /* one table: submissions ∪ chats — row opens grading, chat pill opens the thread */
        <DataTable
          columns={columns}
          data={rows}
          loading={submissions === null}
          searchKeys={["student_name", "student_public_id"]}
          searchPlaceholder={tc("actions.search")}
          filters={[
            {
              key: "status",
              label: tc("columns.status"),
              options: [
                { value: "submitted", label: t("attempts.statuses.submitted") },
                { value: "returned", label: t("assignments.graded") },
                { value: "graded", label: t("assignments.graded") },
                { value: "none", label: t("assignments.notSubmitted") },
              ],
            },
          ]}
          onRowClick={(row) => (row.submission ? setGrading(row.submission) : setOpenThread(row))}
          actions={[
            {
              label: t("assignments.gradeSubmission"),
              icon: CheckCircle2,
              onClick: (row: StudentRow) => row.submission && setGrading(row.submission),
            },
            {
              label: t("assignments.chat"),
              icon: MessageCircle,
              onClick: (row: StudentRow) => setOpenThread(row),
            },
          ]}
          emptyMessage={t("assignments.queueEmpty")}
          exportFilename="submissions"
        />
      )}

      <AssignmentEditor
        assignment={assignment}
        open={editOpen}
        onOpenChange={setEditOpen}
        onSaved={load}
      />

      <AssignmentThreadSheet
        assignmentId={assignment.id}
        thread={openThread}
        open={openThread !== null}
        onOpenChange={(open) => !open && setOpenThread(null)}
        onReplied={loadThreads}
      />

      {previewDialog}

      <SubmissionGradingSheet
        assignment={assignment}
        submission={grading}
        open={grading !== null}
        onOpenChange={(open) => !open && setGrading(null)}
        onGraded={load}
      />

      <AlertDialog open={closeConfirm} onOpenChange={setCloseConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("assignments.closeConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("assignments.closeConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                setCloseConfirm(false)
                action(`/assignments/${assignment.id}/close`, t("assignments.closed"))
              }}
            >
              {t("assignments.close")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
