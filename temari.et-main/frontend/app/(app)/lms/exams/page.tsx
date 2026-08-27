"use client"

import { Eye, Loader2, Plus, Sparkles } from "lucide-react"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { ExamEditor } from "@/components/lms/exam-editor"
import { ExamPreview } from "@/components/lms/exam-preview"
import { QuizStatusBadge } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { Paginated, Quiz, QuizDetail } from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"

type ExamRow = Quiz & {
  grade_label: string
  subject_label: string
  sections_label: string
  completion: number | null
}

export default function ExamsPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const router = useRouter()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const platform = isPlatform && permissions.includes("exam_prep.manage")
  const hasContext = platform || active.schoolId !== null

  const [quizzes, setQuizzes] = useState<ExamRow[] | null>(null)
  const [editorOpen, setEditorOpen] = useState(false)
  // The student-view dry run, straight from the register (works on
  // published/closed exams too — the paper never becomes invisible).
  const [preview, setPreview] = useState<{ quiz: QuizDetail; sample: boolean } | null>(null)
  const [previewLoadingId, setPreviewLoadingId] = useState<number | null>(null)
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})
  // Branch narrowing for the school-wide workspace — applied server-side
  // (refetch); grade/section/kind/status filters stay client-side. The
  // platform exam-prep library has no branches, so scope stays inert there.
  const scope = useScopeQuery({
    values: filterValues,
    setFilter: (key, value) => setFilterValues((prev) => ({ ...prev, [key]: value })),
  })

  const load = useCallback(() => {
    if (!hasContext) return
    apiFetch<Paginated<Quiz>>(`/quizzes?per_page=100${platform ? "&platform=1" : scope.params}`)
      .then((res) =>
        // Client-mode filters/search read FLAT row keys — flatten everything.
        setQuizzes(
          res.data.map((quiz) => {
            const expected = quiz.expected_takers ?? 0
            const taken = quiz.takers_count ?? 0
            return {
              ...quiz,
              grade_label: quiz.grade_level_name ?? "—",
              subject_label: quiz.subject_name ?? "—",
              sections_label: (quiz.section_names ?? []).join(", ") || (quiz.section_name ?? "—"),
              completion: expected > 0 ? Math.round((taken / expected) * 100) : null,
            }
          }),
        ),
      )
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setQuizzes([])
      })
  }, [hasContext, platform, scope.params, tc])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setQuizzes(null)
    load()
  }, [load, active.schoolId, active.branchId])

  /** Fetch the full paper (sample-drawing if needed) and open the dry run. */
  const previewRow = useCallback(
    async (row: ExamRow) => {
      setPreviewLoadingId(row.id)
      try {
        const detail = await apiFetch<{ data: QuizDetail }>(`/quizzes/${row.id}`)
        let quiz = detail.data
        const sample = Boolean(quiz.draw?.length)
        if (sample) {
          const res = await apiFetch<{
            data: { questions: QuizDetail["questions"]; groups: QuizDetail["groups"] }
          }>(`/quizzes/${row.id}/preview`)
          quiz = { ...quiz, questions: res.data.questions, groups: res.data.groups }
        }
        setPreview({ quiz, sample })
      } catch (error) {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      } finally {
        setPreviewLoadingId(null)
      }
    },
    [tc],
  )

  const columns: DataTableColumn<ExamRow>[] = [
    {
      key: "title",
      label: t("exams.examTitle"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.title}</p>
          <p className="text-xs text-muted-foreground">{t(`exams.kinds.${row.kind}`)}</p>
        </div>
      ),
    },
    {
      key: "grade_label",
      label: t("banks.grade"),
      sortable: true,
    },
    {
      key: "subject_label",
      label: t("exams.subject"),
      sortable: true,
    },
    ...(!platform
      ? [
          {
            key: "sections_label",
            label: t("exams.sections"),
            render: (row: ExamRow) =>
              (row.section_names ?? []).length > 0 ? (
                <div className="flex max-w-44 flex-wrap gap-1">
                  {(row.section_names ?? []).map((name) => (
                    <Badge key={name} variant="outline" className="font-normal text-muted-foreground">
                      {name}
                    </Badge>
                  ))}
                </div>
              ) : (
                "—"
              ),
          } satisfies DataTableColumn<ExamRow>,
        ]
      : []),
    {
      key: "question_count",
      label: t("exams.questionCount"),
      render: (row) => (
        <span className="tabular-nums">
          {row.draw?.length ? row.draw.reduce((sum, rule) => sum + rule.count, 0) : (row.question_count ?? 0)}
        </span>
      ),
      mobileHidden: true,
    },
    ...(!platform
      ? [
          {
            key: "completion",
            label: t("exams.completion"),
            sortable: true,
            exportValue: (row: ExamRow) => (row.completion !== null ? `${row.completion}%` : ""),
            render: (row: ExamRow) =>
              row.completion === null ? (
                <span className="text-muted-foreground">—</span>
              ) : (
                <div className="flex min-w-24 items-center gap-2">
                  <div className="h-1.5 w-14 overflow-hidden rounded-full bg-muted">
                    <div
                      className={`h-1.5 rounded-full ${
                        row.completion >= 80 ? "bg-success" : row.completion >= 40 ? "bg-warning" : "bg-primary"
                      }`}
                      style={{ width: `${Math.min(100, row.completion)}%` }}
                    />
                  </div>
                  <span className="text-xs tabular-nums text-muted-foreground">
                    {row.takers_count}/{row.expected_takers} · {row.completion}%
                  </span>
                </div>
              ),
          } satisfies DataTableColumn<ExamRow>,
        ]
      : [
          {
            key: "attempts_count",
            label: t("exams.attemptCount"),
            sortable: true,
            render: (row: ExamRow) => <span className="tabular-nums">{row.attempts_count ?? 0}</span>,
          } satisfies DataTableColumn<ExamRow>,
        ]),
    {
      key: "status",
      label: tc("columns.status"),
      render: (row) => <QuizStatusBadge status={row.status} />,
    },
    {
      key: "row_actions",
      label: "",
      className: "w-12",
      exportValue: () => "",
      render: (row) => (
        <div className="flex justify-end" onClick={(e) => e.stopPropagation()}>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                variant="ghost"
                size="icon"
                className="size-8 text-muted-foreground"
                aria-label={t("exams.preview")}
                disabled={previewLoadingId !== null}
                onClick={() => void previewRow(row)}
              >
                {previewLoadingId === row.id ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : (
                  <Eye className="size-4" />
                )}
              </Button>
            </TooltipTrigger>
            <TooltipContent>{t("exams.preview")}</TooltipContent>
          </Tooltip>
        </div>
      ),
    },
  ]

  const distinct = (values: (string | null | undefined)[]) =>
    [...new Set(values.filter((v): v is string => Boolean(v) && v !== "—"))].sort()

  const filters: DataTableFilter[] = [...(platform ? [] : scope.filters)]
  if (quizzes) {
    filters.push(
      {
        key: "grade_label",
        label: t("banks.grade"),
        options: distinct(quizzes.map((q) => q.grade_label)).map((v) => ({ value: v, label: v })),
      },
      {
        key: "subject_label",
        label: t("exams.subject"),
        options: distinct(quizzes.map((q) => q.subject_label)).map((v) => ({ value: v, label: v })),
      },
    )
    if (!platform) {
      // `section_names` is an array — DataTable matches ANY element. Cascades
      // from grade: hidden until a grade is picked, options narrowed to it.
      filters.push({
        key: "section_names",
        label: t("exams.sections"),
        dependsOn: "grade_label",
        options: (gradeValue: string) => {
          const grades = gradeValue.split(",").filter(Boolean)
          const inGrade = quizzes.filter((q) => grades.includes(q.grade_label ?? ""))
          return distinct(inGrade.flatMap((q) => q.section_names ?? [])).map((v) => ({
            value: v,
            label: v,
          }))
        },
      })
    }
    filters.push(
      {
        key: "kind",
        label: t("exams.kind"),
        options: ["quiz", "exam", "mock"].map((kind) => ({
          value: kind,
          label: t(`exams.kinds.${kind}`),
        })),
      },
      {
        key: "status",
        label: tc("columns.status"),
        options: ["draft", "published", "closed", "archived"].map((status) => ({
          value: status,
          label: t(`exams.statuses.${status}`),
        })),
      },
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={platform ? t("exams.platformTitle") : t("exams.title")}
        description={platform ? t("exams.platformSubtitle") : t("exams.subtitle")}
        actions={
          hasContext ? (
            <div className="flex flex-wrap gap-2">
              {!platform && permissions.includes("lms.manage_own") && (
                <Button
                  variant="outline"
                  className="h-11"
                  onClick={() => router.push(`/ai?lane=teacher&q=${encodeURIComponent(t("exams.aiCreatePrompt"))}`)}
                >
                  <Sparkles className="size-4" /> {t("exams.aiCreate")}
                </Button>
              )}
              <Button className="h-11" onClick={() => setEditorOpen(true)}>
                <Plus className="size-4" /> {t("exams.add")}
              </Button>
            </div>
          ) : undefined
        }
      />

      <ExamEditor
        quiz={null}
        platform={platform}
        open={editorOpen}
        onOpenChange={setEditorOpen}
        onSaved={(quiz) => router.push(`/lms/exams/${quiz.id}`)}
      />

      {preview && (
        <ExamPreview
          quiz={preview.quiz}
          sample={preview.sample}
          open
          onOpenChange={(open) => !open && setPreview(null)}
        />
      )}

      {!hasContext ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("common.noContext")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={quizzes ?? []}
          loading={quizzes === null}
          searchKeys={["title", "subject_label", "sections_label", "grade_label"]}
          searchPlaceholder={t("exams.search")}
          filters={filters}
          filterValues={filterValues}
          onFilterChange={(key, value) =>
            setFilterValues((prev) => {
              const next = { ...prev, [key]: value }
              // Grade change invalidates a section picked under another grade.
              if (key === "grade_label") delete next.section_names
              return next
            })
          }
          onRowClick={(row) => router.push(`/lms/exams/${row.id}`)}
          emptyMessage={t("exams.empty")}
          exportFilename="exams"
        />
      )}
    </div>
  )
}
