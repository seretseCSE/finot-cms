"use client"

import { Eye, Layers, Pencil, Plus, Trash2 } from "lucide-react"
import { useParams, useRouter, useSearchParams } from "next/navigation"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { bankPreviewQuiz, ExamPreview } from "@/components/lms/exam-preview"
import { AiAssistButton, type AiDraftQuestion } from "@/components/ai/ai-assist"
import { stemText } from "@/components/lms/question-content"
import { QuestionEditor } from "@/components/lms/question-editor"
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
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { Switch } from "@/components/ui/switch"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Paginated, Question, QuestionBank } from "@/lib/types"
import { cn } from "@/lib/utils"

export default function QuestionBankDetailPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()
  const bankId = Number(params.id)
  const router = useRouter()
  const searchParams = useSearchParams()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [bank, setBank] = useState<QuestionBank | null>(null)
  const [questions, setQuestions] = useState<
    (Question & { stem_text: string })[] | null
  >(null)
  const [editing, setEditing] = useState<Question | null>(null)
  const [addingTo, setAddingTo] = useState<Question | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  const [previewOpen, setPreviewOpen] = useState(false)
  const [statusPending, setStatusPending] = useState<{
    row: Question & { stem_text: string }
    next: "draft" | "published"
  } | null>(null)
  const [statusWorking, setStatusWorking] = useState(false)

  const load = useCallback(() => {
    apiFetch<{ data: QuestionBank }>(`/question-banks/${bankId}`)
      .then((res) => setBank(res.data))
      .catch(() => {})
    apiFetch<Paginated<Question>>(
      `/question-banks/${bankId}/questions?per_page=100`
    )
      .then((res) => {
        // Client-mode search/filters read FLAT row keys — flatten the stem.
        setQuestions(
          res.data.map((row) => ({
            ...row,
            stem_text: stemText(row.body.stem),
          }))
        )
      })
      .catch((error) => {
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
        setQuestions([])
      })
  }, [bankId, tc])

  useEffect(() => {
    load()
  }, [load])

  // AI-generated questions land PUBLISHED through the normal validated store
  // flow — a bank question is a reusable building block, so it is ready to
  // drop onto an exam (the exam itself is still reviewed before publishing).
  // A passage draft (type "group") saves its container first, then each
  // sub-question under it — exactly what the editor's "add to passage" does.
  const insertAiDrafts = useCallback(
    async (drafts: AiDraftQuestion[]) => {
      const payloadFor = (
        draft: AiDraftQuestion
      ): { body: Record<string, unknown>; answer_key: Record<string, unknown> | null } | null => {
        const body: Record<string, unknown> = { stem: `<p>${draft.stem}</p>` }
        if (draft.type === "mcq_single") {
          body.options = (draft.options ?? []).map((o) => ({
            id: o.id,
            text: o.text,
          }))
          return { body, answer_key: { correct: String(draft.correct ?? "") } }
        }
        if (draft.type === "true_false") {
          // `correct` may arrive as a boolean or the string "true"/"false".
          return {
            body,
            answer_key: {
              correct:
                draft.correct === true ||
                String(draft.correct).toLowerCase() === "true",
            },
          }
        }
        if (draft.type === "matching") {
          // The AI sends correctly matched texts; ids are minted here and the
          // right column is shuffled so takers never see the aligned order.
          const pairs = (draft.matching_pairs ?? []).filter(
            (p) => p.left.trim() !== "" && p.right.trim() !== ""
          )
          if (pairs.length < 2) return null
          body.left = pairs.map((p, i) => ({ id: `l${i + 1}`, text: p.left }))
          body.right = pairs
            .map((p, i) => ({ id: `r${i + 1}`, text: p.right }))
            .sort(() => Math.random() - 0.5)
          return {
            body,
            answer_key: {
              pairs: Object.fromEntries(pairs.map((_, i) => [`l${i + 1}`, `r${i + 1}`])),
            },
          }
        }
        return { body, answer_key: { accepted: [String(draft.correct ?? "")] } }
      }

      const post = (payload: Record<string, unknown>) =>
        apiFetch<{ data: Question }>(`/question-banks/${bankId}/questions`, {
          method: "POST",
          body: payload,
        })

      let saved = 0
      for (const draft of drafts) {
        try {
          if (draft.type === "group") {
            const subs = draft.sub_questions ?? []
            if (subs.length === 0) continue
            const group = await post({
              type: "group",
              body: { stem: `<p>${draft.stem}</p>` },
              answer_key: null,
              status: "published",
            })
            for (const sub of subs) {
              const payload = payloadFor(sub)
              if (!payload) continue
              try {
                await post({
                  type: sub.type,
                  ...payload,
                  parent_id: group.data.id,
                  explanation: sub.explanation ?? undefined,
                  status: "published",
                })
                saved++
              } catch {
                // Skip invalid sub-questions; the passage keeps the rest.
              }
            }
            continue
          }

          const payload = payloadFor(draft)
          if (!payload) continue
          await post({
            type: draft.type,
            ...payload,
            explanation: draft.explanation ?? undefined,
            status: "published",
          })
          saved++
        } catch {
          // Skip invalid drafts silently; the count below tells the story.
        }
      }
      if (saved > 0) {
        toast.success(tc("actions.saved"))
        load()
      }
    },
    [bankId, load, tc]
  )

  // "Add question" straight from the banks table (…?add=1).
  const wantsAdd = searchParams.get("add") === "1"
  useEffect(() => {
    if (!wantsAdd) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- deep-link intent
    setEditorOpen(true)
    router.replace(`/lms/question-banks/${bankId}`, { scroll: false })
  }, [wantsAdd, bankId, router])

  // Publish/un-publish a single question straight from the table, always
  // behind a confirmation — draft questions can't be added to exams.
  async function runStatusChange() {
    if (!statusPending) return
    setStatusWorking(true)
    try {
      const res = await apiFetch<{ data: Question }>(
        `/questions/${statusPending.row.id}/status`,
        { method: "PATCH", body: { status: statusPending.next } }
      )
      setQuestions((prev) =>
        (prev ?? []).map((q) =>
          q.id === statusPending.row.id ? { ...q, status: res.data.status } : q
        )
      )
      toast.success(
        statusPending.next === "published"
          ? t("questions.publishedToast")
          : t("questions.draftedToast")
      )
      setStatusPending(null)
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setStatusWorking(false)
    }
  }

  async function handleDelete(question: Question) {
    try {
      const res = await apiFetch<{ message: string }>(
        `/questions/${question.id}`,
        {
          method: "DELETE",
        }
      )
      toast.success(res.message)
      load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  function openEditor(
    question: Question | null,
    parent: Question | null = null
  ) {
    setEditing(question)
    setAddingTo(parent)
    setEditorOpen(true)
  }

  const topics = bank?.topics ?? []

  // The bank as a student-facing dry run (passages included).
  const previewQuiz = useMemo(
    () =>
      bankPreviewQuiz(bank?.name ?? t("questions.title"), questions ?? [], {
        grade: bank?.grade_level_name,
        subject: bank?.subject_name,
      }),
    [bank, questions, t]
  )

  // A passage's questions, authored order — the passage is the unit: its
  // questions never appear as their own rows in the bank table.
  const childrenOf = useMemo(() => {
    const map = new Map<number, (Question & { stem_text: string })[]>()
    for (const row of questions ?? []) {
      if (row.parent_id) {
        map.set(row.parent_id, [...(map.get(row.parent_id) ?? []), row])
      }
    }
    map.forEach((children) =>
      children.sort(
        (a, b) => (a.position ?? 0) - (b.position ?? 0) || a.id - b.id
      )
    )
    return map
  }, [questions])

  // Table rows = top-level only; a group row carries its questions' text in
  // sub_text so search still finds a passage by any of its questions.
  const tableRows = useMemo(
    () =>
      questions === null
        ? null
        : questions
            .filter((row) => !row.parent_id)
            .map((row) => ({
              ...row,
              sub_text: (childrenOf.get(row.id) ?? [])
                .map((sub) => sub.stem_text)
                .join(" "),
            })),
    [questions, childrenOf]
  )

  // One passage as a student-facing dry run (the row's eye action).
  const [previewGroup, setPreviewGroup] = useState<Question | null>(null)
  const groupPreviewQuiz = useMemo(() => {
    if (previewGroup === null) return null
    return bankPreviewQuiz(
      stemText(previewGroup.body.stem).slice(0, 80) || (bank?.name ?? ""),
      [previewGroup, ...(childrenOf.get(previewGroup.id) ?? [])],
      { grade: bank?.grade_level_name, subject: bank?.subject_name }
    )
  }, [previewGroup, childrenOf, bank])

  const columns: DataTableColumn<Question & { stem_text: string; sub_text: string }>[] = [
    {
      key: "stem_text",
      label: t("questions.stem"),
      primary: true,
      render: (row) => (
        <span className="flex max-w-md items-start gap-1.5">
          {row.type === "group" && (
            <Layers className="mt-0.5 size-3.5 shrink-0 text-primary" />
          )}
          <span className="line-clamp-2 min-w-0 whitespace-normal">
            {row.stem_text}
            {row.type === "group" && (
              <span className="ml-1.5 text-xs text-muted-foreground">
                {t("questions.subCount", {
                  count: childrenOf.get(row.id)?.length ?? row.children_count ?? 0,
                })}
              </span>
            )}
          </span>
        </span>
      ),
      exportValue: (row) => row.stem_text,
    },
    {
      key: "type",
      label: t("questions.type"),
      sortable: true,
      render: (row) => (
        <Badge variant="secondary">{t(`questions.types.${row.type}`)}</Badge>
      ),
    },
    {
      key: "topic",
      label: t("questions.topic"),
      render: (row) => row.topic ?? "—",
      mobileHidden: true,
    },
    {
      key: "points",
      label: t("questions.points"),
      sortable: true,
      // The passage carries no marks of its own — its questions do, so the
      // row shows their sum.
      sortValue: (row) =>
        row.type === "group"
          ? (childrenOf.get(row.id) ?? []).reduce((sum, sub) => sum + Number(sub.points), 0)
          : Number(row.points),
      render: (row) => (
        <span className="tabular-nums">
          {row.type === "group"
            ? (childrenOf.get(row.id) ?? []).reduce((sum, sub) => sum + Number(sub.points), 0)
            : Number(row.points)}
        </span>
      ),
    },
    {
      key: "difficulty",
      label: t("questions.difficulty"),
      render: (row) =>
        row.difficulty ? t(`questions.${row.difficulty}`) : "—",
      mobileHidden: true,
    },
    {
      key: "status",
      label: t("questions.status"),
      sortable: true,
      // Retired questions (still referenced by an exam) are read-only; draft
      // and published toggle in place, always behind a confirmation.
      render: (row) =>
        row.status === "retired" || !row.can_edit ? (
          <Badge
            variant="outline"
            className={`border-transparent ${
              row.status === "published"
                ? "bg-success/10 text-success"
                : row.status === "retired"
                  ? "bg-muted text-muted-foreground"
                  : "bg-warning/10 text-warning"
            }`}
          >
            {t(`questions.${row.status}`)}
          </Badge>
        ) : (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation()
              setStatusPending({
                row,
                next: row.status === "published" ? "draft" : "published",
              })
            }}
            className="inline-flex items-center gap-2"
            aria-label={
              row.status === "published"
                ? t("questions.unpublish")
                : t("questions.publish")
            }
          >
            <Switch
              checked={row.status === "published"}
              className="pointer-events-none"
              tabIndex={-1}
            />
            <span
              className={cn(
                "text-xs font-medium",
                row.status === "published" ? "text-success" : "text-warning"
              )}
            >
              {t(`questions.${row.status}`)}
            </span>
          </button>
        ),
    },
    {
      key: "row_actions",
      label: "",
      className: "w-24",
      exportValue: () => "",
      render: (row) => (
        <div
          className="flex justify-end gap-1"
          onClick={(e) => e.stopPropagation()}
        >
          {row.type === "group" && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground"
                  aria-label={t("exams.preview")}
                  onClick={() => setPreviewGroup(row)}
                >
                  <Eye className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("exams.preview")}</TooltipContent>
            </Tooltip>
          )}
          {row.can_edit && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground"
                  aria-label={tc("actions.edit")}
                  onClick={() => openEditor(row)}
                >
                  <Pencil className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{tc("actions.edit")}</TooltipContent>
            </Tooltip>
          )}
          {row.can_delete && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground hover:text-destructive"
                  aria-label={tc("actions.delete")}
                  onClick={() => confirmDelete(() => handleDelete(row))}
                >
                  <Trash2 className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{tc("actions.delete")}</TooltipContent>
            </Tooltip>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="space-y-6">
      {confirmDialog}
      <AlertDialog
        open={statusPending !== null}
        onOpenChange={(open) => !open && !statusWorking && setStatusPending(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {statusPending?.next === "published"
                ? t("questions.publishTitle")
                : t("questions.unpublishTitle")}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {statusPending?.next === "published"
                ? t("questions.publishBody")
                : t("questions.unpublishBody")}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={statusWorking}>
              {tc("actions.cancel")}
            </AlertDialogCancel>
            <AlertDialogAction
              loading={statusWorking}
              onClick={(e) => {
                e.preventDefault()
                void runStatusChange()
              }}
            >
              {statusPending?.next === "published"
                ? t("questions.publish")
                : t("questions.unpublish")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
      <PageHeader
        title={bank?.name ?? t("questions.title")}
        description={
          bank
            ? [bank.subject_name, bank.grade_level_name]
                .filter(Boolean)
                .join(" · ") ||
              (bank.description ?? undefined)
            : undefined
        }
        backHref="/lms/question-banks"
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {(questions?.length ?? 0) > 0 && (
              <Button
                variant="outline"
                className="h-11"
                onClick={() => setPreviewOpen(true)}
              >
                <Eye className="size-4" /> {t("exams.preview")}
              </Button>
            )}
            {bank?.can_edit && (
              <AiAssistButton
                action="quiz_questions"
                params={{
                  subject: bank.subject_name ?? "",
                  grade: bank.grade_level_name ?? "",
                  topics: bank.topics ?? [],
                }}
                onQuestions={(drafts) => void insertAiDrafts(drafts)}
              />
            )}
            {bank?.can_edit && (
              <Button className="h-11" onClick={() => openEditor(null)}>
                <Plus className="size-4" /> {t("questions.add")}
              </Button>
            )}
          </div>
        }
      />

      <ExamPreview
        quiz={previewQuiz}
        open={previewOpen}
        onOpenChange={setPreviewOpen}
      />

      {/* One passage as a student sees it (row eye action). */}
      {groupPreviewQuiz !== null && (
        <ExamPreview
          quiz={groupPreviewQuiz}
          open={previewGroup !== null}
          onOpenChange={(open) => !open && setPreviewGroup(null)}
        />
      )}

      <QuestionEditor
        bank={bank}
        question={editing}
        parent={addingTo}
        subQuestions={editing ? (childrenOf.get(editing.id) ?? []) : []}
        open={editorOpen}
        onOpenChange={(open) => {
          setEditorOpen(open)
          if (!open) {
            setEditing(null)
            setAddingTo(null)
          }
        }}
        onSaved={load}
        onChanged={load}
      />

      <DataTable
        columns={columns}
        data={tableRows ?? []}
        loading={tableRows === null}
        searchKeys={["stem_text", "sub_text", "source", "topic"]}
        searchPlaceholder={t("questions.search")}
        filters={[
          ...(topics.length > 0
            ? [
                {
                  key: "topic",
                  label: t("questions.topic"),
                  options: topics.map((topic) => ({
                    value: topic,
                    label: topic,
                  })),
                },
              ]
            : []),
          {
            key: "type",
            label: t("questions.type"),
            options: (
              [
                "mcq_single",
                "mcq_multi",
                "true_false",
                "short_answer",
                "numeric",
                "fill_blank",
                "matching",
                "essay",
                "group",
              ] as const
            ).map((type) => ({
              value: type,
              label: t(`questions.types.${type}`),
            })),
          },
          {
            key: "difficulty",
            label: t("questions.difficulty"),
            options: ["easy", "medium", "hard"].map((level) => ({
              value: level,
              label: t(`questions.${level}`),
            })),
          },
          {
            key: "status",
            label: t("questions.status"),
            options: ["draft", "published", "retired"].map((status) => ({
              value: status,
              label: t(`questions.${status}`),
            })),
          },
        ]}
        onRowClick={(row) => row.can_edit && openEditor(row)}
        emptyMessage={t("questions.empty")}
        exportFilename="questions"
      />
    </div>
  )
}
