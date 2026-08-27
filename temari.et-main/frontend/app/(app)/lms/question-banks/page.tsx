"use client"

import {
  Eye,
  Landmark,
  Loader2,
  Pencil,
  Plus,
  Sparkles,
  SquarePlus,
  Trash2,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { BankSheet } from "@/components/lms/bank-sheet"
import { bankPreviewQuiz, ExamPreview } from "@/components/lms/exam-preview"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type {
  Paginated,
  Question,
  QuestionBank,
  QuizDetail,
  School,
} from "@/lib/types"

type BankRow = QuestionBank & {
  grade_name: string
  subject_label: string
  branch_label: string
  status: "active" | "inactive"
}

export default function QuestionBanksPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const router = useRouter()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  // Temari.et staff curate the national banks; school staff their own.
  const platform = isPlatform && permissions.includes("exam_prep.manage")
  const hasContext = platform || active.schoolId !== null

  const [banks, setBanks] = useState<BankRow[] | null>(null)
  const [editing, setEditing] = useState<QuestionBank | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  // Platform staff can flip between the national pool and any school's banks.
  const [schools, setSchools] = useState<School[]>([])
  const [schoolFilter, setSchoolFilter] = useState<string>("platform")

  const load = useCallback(() => {
    if (!hasContext) return
    const scope = platform
      ? schoolFilter === "platform"
        ? "&platform=1"
        : `&school_id=${schoolFilter}`
      : ""
    apiFetch<Paginated<QuestionBank>>(
      `/question-banks?per_page=100&all=1${scope}`
    )
      .then((res) =>
        // Client-mode filters/search read FLAT row keys — flatten everything.
        setBanks(
          res.data.map((bank) => ({
            ...bank,
            grade_name: bank.grade_level_name ?? "—",
            subject_label: bank.subject_name ?? t("banks.anySubject"),
            branch_label: bank.branch_name ?? t("banks.schoolWide"),
            status: bank.is_active ? "active" : "inactive",
          }))
        )
      )
      .catch((error) => {
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
        setBanks([])
      })
  }, [hasContext, platform, schoolFilter, t, tc])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setBanks(null)
    load()
  }, [load, active.schoolId, active.branchId])

  useEffect(() => {
    if (!platform) return
    let cancelled = false
    // Super admins get the full picker; other platform staff fail silently.
    apiFetch<Paginated<School>>("/schools?per_page=100&sort=name&dir=asc")
      .then((res) => !cancelled && setSchools(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [platform])

  // Bank dry run straight from the register: fetch the questions, page
  // through them exactly as a student would.
  const [preview, setPreview] = useState<QuizDetail | null>(null)
  const [previewLoadingId, setPreviewLoadingId] = useState<number | null>(null)

  async function previewBank(bank: BankRow) {
    setPreviewLoadingId(bank.id)
    try {
      const res = await apiFetch<Paginated<Question>>(
        `/question-banks/${bank.id}/questions?per_page=100`
      )
      setPreview(
        bankPreviewQuiz(bank.name, res.data, {
          grade: bank.grade_level_name,
          subject: bank.subject_name,
        })
      )
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setPreviewLoadingId(null)
    }
  }

  async function handleDelete(bank: QuestionBank) {
    try {
      const res = await apiFetch<{ message: string }>(
        `/question-banks/${bank.id}`,
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

  const browsingSchool = platform && schoolFilter !== "platform"
  const canManageRows = !browsingSchool

  const columns: DataTableColumn<BankRow>[] = [
    {
      key: "name",
      label: t("banks.name"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="flex items-center gap-2">
          <span className="font-medium">{row.name}</span>
          {!row.is_active && (
            <Badge variant="secondary">{t("banks.inactive")}</Badge>
          )}
        </div>
      ),
    },
    {
      key: "subject_label",
      label: t("banks.subject"),
      sortable: true,
    },
    {
      key: "grade_name",
      label: t("banks.grade"),
      sortable: true,
    },
    {
      key: "topics",
      label: t("banks.topics"),
      mobileHidden: true,
      exportValue: (row) => (row.topics ?? []).join(", "),
      render: (row) =>
        row.topics.length > 0 ? (
          <div className="flex max-w-56 flex-wrap gap-1">
            {row.topics.slice(0, 3).map((topic) => (
              <Badge
                key={topic}
                variant="outline"
                className="font-normal text-muted-foreground"
              >
                {topic}
              </Badge>
            ))}
            {row.topics.length > 3 && (
              <Badge
                variant="outline"
                className="font-normal text-muted-foreground"
              >
                +{row.topics.length - 3}
              </Badge>
            )}
          </div>
        ) : (
          "—"
        ),
    },
    {
      key: "questions_count",
      label: t("questions.title"),
      sortable: true,
      render: (row) => (
        <span className="tabular-nums">{row.questions_count ?? 0}</span>
      ),
    },
    {
      key: "created_by_name",
      label: t("common.createdBy"),
      render: (row) => row.created_by_name ?? "—",
      mobileHidden: true,
    },
    {
      key: "row_actions",
      label: "",
      className: "w-32",
      exportValue: () => "",
      render: (row: BankRow) => (
        <div
          className="flex justify-end gap-1"
          onClick={(e) => e.stopPropagation()}
        >
          {(row.questions_count ?? 0) > 0 && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground"
                  aria-label={t("exams.preview")}
                  disabled={previewLoadingId !== null}
                  onClick={() => void previewBank(row)}
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
          )}
          {canManageRows && row.can_edit && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground"
                  aria-label={t("questions.add")}
                  onClick={() =>
                    router.push(`/lms/question-banks/${row.id}?add=1`)
                  }
                >
                  <SquarePlus className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{t("questions.add")}</TooltipContent>
            </Tooltip>
          )}
          {canManageRows && row.can_edit && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground"
                  aria-label={tc("actions.edit")}
                  onClick={() => {
                    setEditing(row)
                    setSheetOpen(true)
                  }}
                >
                  <Pencil className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{tc("actions.edit")}</TooltipContent>
            </Tooltip>
          )}
          {canManageRows && row.can_delete && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8 text-muted-foreground hover:text-destructive"
                  aria-label={tc("actions.delete")}
                  onClick={() =>
                    confirmDelete(
                      () => handleDelete(row),
                      tc("confirmDelete.named", { name: row.name })
                    )
                  }
                >
                  <Trash2 className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>{tc("actions.delete")}</TooltipContent>
            </Tooltip>
          )}
        </div>
      ),
    } satisfies DataTableColumn<BankRow>,
  ]

  // Branch column only when the data spans branches (school-wide workspace).
  if (!platform && banks !== null && banks.some((bank) => bank.branch_name)) {
    columns.splice(4, 0, {
      key: "branch_label",
      label: tc("columns.branch"),
      mobileHidden: true,
    })
  }

  // Role-shaped filters, built from the loaded rows (client mode).
  const distinct = (values: (string | null | undefined)[]) =>
    [...new Set(values.filter((v): v is string => Boolean(v)))].sort()

  const filters: DataTableFilter[] = []
  if (banks) {
    if (!platform && banks.some((bank) => bank.branch_name)) {
      filters.push({
        key: "branch_label",
        label: tc("columns.branch"),
        options: distinct(banks.map((b) => b.branch_label)).map((v) => ({
          value: v,
          label: v,
        })),
      })
    }
    filters.push(
      {
        key: "grade_name",
        label: t("banks.grade"),
        options: distinct(banks.map((b) => b.grade_name)).map((v) => ({
          value: v,
          label: v,
        })),
      },
      {
        key: "subject_label",
        label: t("banks.subject"),
        options: distinct(banks.map((b) => b.subject_label)).map((v) => ({
          value: v,
          label: v,
        })),
      },
      {
        key: "status",
        label: t("questions.status"),
        options: [
          { value: "active", label: t("banks.active") },
          { value: "inactive", label: t("banks.inactive") },
        ],
      }
    )
  }

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={platform ? t("banks.platformTitle") : t("banks.title")}
        description={
          platform ? t("banks.platformSubtitle") : t("banks.subtitle")
        }
        actions={
          hasContext ? (
            <div className="flex items-center gap-2">
              {platform && schools.length > 0 && (
                <Select
                  value={schoolFilter}
                  onValueChange={(v) => {
                    setSchoolFilter(v)
                    setBanks(null)
                  }}
                >
                  <SelectTrigger className="h-11 w-44 md:w-56">
                    <Landmark className="size-4 shrink-0 text-muted-foreground" />
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="platform">
                      {t("banks.nationalScope")}
                    </SelectItem>
                    {schools.map((school) => (
                      <SelectItem key={school.id} value={String(school.id)}>
                        {school.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              {!platform && permissions.includes("lms.manage_own") && (
                <Button
                  variant="outline"
                  className="h-11"
                  onClick={() => router.push(`/ai?lane=teacher&q=${encodeURIComponent(t("banks.aiDraftPrompt"))}`)}
                >
                  <Sparkles className="size-4" /> {t("banks.aiDraft")}
                </Button>
              )}
              {canManageRows && (
                <Button
                  className="h-11"
                  onClick={() => {
                    setEditing(null)
                    setSheetOpen(true)
                  }}
                >
                  <Plus className="size-4" /> {t("banks.add")}
                </Button>
              )}
            </div>
          ) : undefined
        }
      />

      <BankSheet
        bank={editing}
        platform={platform}
        open={sheetOpen}
        onOpenChange={(open) => {
          setSheetOpen(open)
          if (!open) setEditing(null)
        }}
        onSaved={load}
      />

      {preview && (
        <ExamPreview
          quiz={preview}
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
          data={banks ?? []}
          loading={banks === null}
          searchKeys={["name", "subject_label", "grade_name"]}
          searchPlaceholder={tc("actions.search")}
          filters={filters}
          onRowClick={(row) => router.push(`/lms/question-banks/${row.id}`)}
          emptyMessage={t("banks.empty")}
          exportFilename="question-banks"
        />
      )}
    </div>
  )
}
