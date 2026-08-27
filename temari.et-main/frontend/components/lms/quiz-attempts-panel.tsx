"use client"

import { Ban, Eye, Flag } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { AttemptReview } from "@/components/lms/attempt-review"
import { formatDateTime } from "@/components/lms/shared"
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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Paginated, QuizAttemptRow } from "@/lib/types"

interface Props {
  quizId: number
  /** Bumped by the parent to force a reload (e.g. after a grade sync). */
  reloadKey?: number
  /** Fired after an attempt is graded or invalidated, so the parent can
   *  refresh its own completion/stats. */
  onChanged?: () => void
  exportFilename?: string
}

/**
 * The shared teacher view of a quiz's sittings: the attempts table with
 * per-row Review (opens the full grading sheet) and Invalidate (behind a
 * confirm). Used both on the exam detail page and inline on a quiz-kind
 * assignment — one source of truth for grading a quiz's attempts.
 */
export function QuizAttemptsPanel({ quizId, reloadKey, onChanged, exportFilename = "exam-attempts" }: Props) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")

  const [attempts, setAttempts] = useState<QuizAttemptRow[] | null>(null)
  const [grading, setGrading] = useState<number | null>(null)
  const [invalidating, setInvalidating] = useState<QuizAttemptRow | null>(null)
  const [working, setWorking] = useState(false)

  const load = useCallback(() => {
    apiFetch<Paginated<QuizAttemptRow>>(`/quizzes/${quizId}/attempts?per_page=100`)
      .then((res) => setAttempts(res.data))
      .catch(() => setAttempts([]))
  }, [quizId])

  useEffect(() => {
    load()
  }, [load, reloadKey])

  async function invalidate(row: QuizAttemptRow) {
    setWorking(true)
    try {
      const res = await apiFetch<{ message?: string }>(
        `/quizzes/${quizId}/attempts/${row.id}/invalidate`,
        { method: "POST", body: {} },
      )
      toast.success(res.message ?? t("attempts.invalidated"))
      load()
      onChanged?.()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  const columns: DataTableColumn<QuizAttemptRow>[] = [
    {
      key: "taker_name",
      label: t("attempts.taker"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.taker_name ?? "—"}</p>
          {row.student_public_id && (
            <p className="text-xs text-muted-foreground">{row.student_public_id}</p>
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
            row.status === "graded"
              ? "bg-success/10 text-success"
              : row.status === "submitted"
                ? "bg-warning/10 text-warning"
                : row.status === "in_progress"
                  ? "bg-info/10 text-info"
                  : "bg-muted text-muted-foreground"
          }`}
        >
          {t(`attempts.statuses.${row.status}`)}
        </Badge>
      ),
    },
    {
      key: "score",
      label: t("attempts.score"),
      sortable: true,
      render: (row) => (
        <span className="tabular-nums">
          {row.score !== null ? `${Number(row.score)} / ${Number(row.max_score)}` : "—"}
        </span>
      ),
    },
    {
      key: "flag_count",
      label: t("attempts.flags"),
      sortable: true,
      render: (row) =>
        row.flag_count > 0 ? (
          <span className="inline-flex items-center gap-1 text-warning">
            <Flag className="size-3.5" /> {row.flag_count}
          </span>
        ) : (
          <span className="text-muted-foreground">0</span>
        ),
    },
    {
      key: "started_at",
      label: t("attempts.started"),
      sortable: true,
      render: (row) => formatDateTime(row.started_at),
      mobileHidden: true,
    },
    {
      key: "submitted_at",
      label: t("attempts.submitted"),
      render: (row) => formatDateTime(row.submitted_at),
      mobileHidden: true,
    },
    {
      key: "row_actions",
      label: "",
      className: "w-52",
      exportValue: () => "",
      render: (row) => (
        <div className="flex justify-end gap-1.5" onClick={(e) => e.stopPropagation()}>
          {row.status !== "in_progress" && (
            <Button variant="outline" size="sm" className="h-8" onClick={() => setGrading(row.id)}>
              <Eye className="size-3.5" /> {t("attempts.review")}
            </Button>
          )}
          {row.status !== "invalidated" && (
            <Button
              variant="outline"
              size="sm"
              className="h-8 border-destructive/30 text-destructive hover:bg-destructive/5 hover:text-destructive"
              onClick={() => setInvalidating(row)}
              loading={working}
            >
              <Ban className="size-3.5" /> {t("attempts.invalidate")}
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <>
      <DataTable
        columns={columns}
        data={attempts ?? []}
        loading={attempts === null}
        searchKeys={["taker_name", "student_public_id"]}
        searchPlaceholder={tc("actions.search")}
        filters={[
          {
            key: "status",
            label: tc("columns.status"),
            options: ["in_progress", "submitted", "graded", "invalidated"].map((status) => ({
              value: status,
              label: t(`attempts.statuses.${status}`),
            })),
          },
        ]}
        onRowClick={(row) => row.status !== "in_progress" && setGrading(row.id)}
        emptyMessage={t("attempts.empty")}
        exportFilename={exportFilename}
      />

      <AttemptReview
        quizId={quizId}
        attemptId={grading}
        open={grading !== null}
        onOpenChange={(open) => !open && setGrading(null)}
        onGraded={() => {
          load()
          onChanged?.()
        }}
      />

      {/* Invalidate from the table — always behind a confirm. */}
      <AlertDialog open={invalidating !== null} onOpenChange={(open) => !open && setInvalidating(null)}>
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
                const row = invalidating
                setInvalidating(null)
                if (row) void invalidate(row)
              }}
            >
              {t("attempts.invalidate")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
