"use client"

import { NotebookText, Pencil, Plus, Trash2 } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useBranchScope } from "@/components/ui/branch-select"
import { useTranslation } from "@/lib/i18n"
import type { ChatTemplate } from "@/lib/types"
import { cn } from "@/lib/utils"

const CATEGORIES = ["general", "attendance", "homework", "behavior", "praise", "fees", "meeting"] as const
const PLACEHOLDERS = ["{student_name}", "{teacher_name}", "{school_name}", "{date}"] as const
const LANGS = ["en", "am", "om"] as const

interface Draft {
  id: number | null
  name: string
  category: string
  body: { en: string; am: string; om: string }
  is_active: boolean
  branch_id: number | null
}

const emptyDraft: Draft = {
  id: null,
  name: "",
  category: "general",
  body: { en: "", am: "", om: "" },
  is_active: true,
  branch_id: null,
}

/** Studio row + flat keys for search/filters. */
type Row = ChatTemplate & { scope_key: string }

/**
 * The preset-message studio (chat.moderate): the library of school/branch
 * message templates the composer's picker offers teachers — with the
 * tri-language body that makes a preset land in the family's own language.
 */
export default function ChatTemplatesPage() {
  const { t } = useTranslation("chat")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { needsBranch, branches } = useBranchScope()

  const [rows, setRows] = useState<Row[] | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [draft, setDraft] = useState<Draft>(emptyDraft)
  const [saving, setSaving] = useState(false)

  const canManage = permissions.includes("chat.moderate")
  const hasWorkspace = !isPlatform && active.schoolId !== null

  const load = useCallback(() => {
    apiFetch<{ data: ChatTemplate[] }>("/chat/templates")
      .then((res) =>
        setRows(
          res.data.map((template) => ({
            ...template,
            scope_key: template.branch_name ?? t("templates.schoolWide"),
          })),
        ),
      )
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps -- t/tc stable enough
  }, [])

  useEffect(() => {
    if (!hasWorkspace || !canManage) return
    load()
  }, [hasWorkspace, canManage, active.schoolId, active.branchId, load])

  function openCreate() {
    setDraft({ ...emptyDraft, branch_id: active.branchId })
    setSheetOpen(true)
  }

  function openEdit(row: Row) {
    setDraft({
      id: row.id,
      name: row.name,
      category: row.category,
      body: { en: row.body.en ?? "", am: row.body.am ?? "", om: row.body.om ?? "" },
      is_active: row.is_active,
      branch_id: row.branch_id,
    })
    setSheetOpen(true)
  }

  async function save() {
    if (!draft.name.trim()) {
      toast.error(t("templates.nameRequired"))
      return
    }
    if (!LANGS.some((lang) => draft.body[lang].trim())) {
      toast.error(t("templates.bodyRequired"))
      return
    }

    setSaving(true)
    try {
      const payload = {
        name: draft.name.trim(),
        category: draft.category,
        body: draft.body,
        is_active: draft.is_active,
        ...(draft.id === null ? { branch_id: draft.branch_id } : {}),
      }
      if (draft.id === null) {
        await apiFetch("/chat/templates", { method: "POST", body: payload })
      } else {
        await apiFetch(`/chat/templates/${draft.id}`, { method: "PUT", body: payload })
      }
      toast.success(tc("actions.saved"))
      setSheetOpen(false)
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  async function toggleActive(row: Row, value: boolean) {
    // Reversible toggle — no confirmation by design.
    setRows((current) =>
      (current ?? []).map((r) => (r.id === row.id ? { ...r, is_active: value } : r)),
    )
    try {
      await apiFetch(`/chat/templates/${row.id}`, { method: "PUT", body: { is_active: value } })
    } catch (error) {
      setRows((current) =>
        (current ?? []).map((r) => (r.id === row.id ? { ...r, is_active: !value } : r)),
      )
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const columns: DataTableColumn<Row>[] = [
    {
      key: "name",
      label: t("templates.name"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate text-sm font-medium">{row.name}</p>
          <p className="text-muted-foreground line-clamp-1 text-xs">
            {row.body.en || row.body.am || row.body.om}
          </p>
        </div>
      ),
      exportValue: (row) => row.name,
    },
    {
      key: "category",
      label: t("templates.category"),
      render: (row) => (
        <Badge variant="secondary">{t(`templates.categories.${row.category}`)}</Badge>
      ),
      exportValue: (row) => row.category,
    },
    {
      key: "scope_key",
      label: t("templates.scope"),
      mobileHidden: true,
      render: (row) => (
        <span className={cn("text-xs", row.branch_id === null && "text-muted-foreground")}>
          {row.scope_key}
        </span>
      ),
    },
    {
      key: "languages",
      label: t("templates.languages"),
      sortable: false,
      mobileHidden: true,
      render: (row) => (
        <span className="text-muted-foreground text-xs uppercase">
          {LANGS.filter((lang) => (row.body[lang] ?? "").trim()).join(" · ")}
        </span>
      ),
      exportValue: (row) => LANGS.filter((lang) => (row.body[lang] ?? "").trim()).join(","),
    },
    {
      key: "is_active",
      label: t("templates.active"),
      render: (row) => (
        <span onClick={(event) => event.stopPropagation()}>
          <Switch
            checked={row.is_active}
            onCheckedChange={(value) => void toggleActive(row, value)}
            aria-label={t("templates.active")}
          />
        </span>
      ),
      exportValue: (row) => (row.is_active ? "yes" : "no"),
    },
  ]

  if (!hasWorkspace || !canManage) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("templates.title")} backHref="/messages" backLabel={t("title")} />
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {tc("errors.forbidden")}
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("templates.title")}
        description={t("templates.subtitle")}
        backHref="/messages"
        backLabel={t("title")}
        actions={
          <Button onClick={openCreate}>
            <Plus className="size-4" />
            {t("templates.add")}
          </Button>
        }
      />

      {rows !== null && rows.length === 0 ? (
        <div className="page-gutter">
          <EmptyState
            icon={NotebookText}
            title={t("templates.emptyTitle")}
            description={t("templates.emptyBody")}
          />
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={rows ?? []}
          loading={rows === null}
          searchKeys={["name"]}
          searchPlaceholder={tc("actions.search")}
          filters={[
            {
              key: "category",
              label: t("templates.category"),
              options: CATEGORIES.map((category) => ({
                label: t(`templates.categories.${category}`),
                value: category,
              })),
            },
          ]}
          actions={[
            {
              label: tc("actions.edit"),
              icon: Pencil,
              primary: true,
              onClick: openEdit,
            },
            {
              label: tc("actions.delete"),
              icon: Trash2,
              destructive: true,
              onClick: (row) =>
                confirmDelete(async () => {
                  await apiFetch(`/chat/templates/${row.id}`, { method: "DELETE" })
                  toast.success(tc("actions.deleted"))
                  load()
                }),
            },
          ]}
          emptyMessage={t("templates.emptyTitle")}
          exportFilename="chat-templates"
        />
      )}

      <ResponsiveSheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>
              {draft.id === null ? t("templates.add") : t("templates.edit")}
            </ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="template-name">{t("templates.name")}</Label>
                <Input
                  id="template-name"
                  value={draft.name}
                  onChange={(event) => setDraft((d) => ({ ...d, name: event.target.value }))}
                  placeholder={t("templates.namePlaceholder")}
                />
              </div>
              <div className="space-y-2">
                <Label>{t("templates.category")}</Label>
                <Select
                  value={draft.category}
                  onValueChange={(value) => setDraft((d) => ({ ...d, category: value }))}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {CATEGORIES.map((category) => (
                      <SelectItem key={category} value={category}>
                        {t(`templates.categories.${category}`)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            {/* New templates in a school-wide workspace may target one branch
                or serve every branch (school-wide, the default). */}
            {draft.id === null && needsBranch && (
              <div className="space-y-2">
                <Label>{t("templates.scope")}</Label>
                <Select
                  value={draft.branch_id != null ? String(draft.branch_id) : "school"}
                  onValueChange={(value) =>
                    setDraft((d) => ({ ...d, branch_id: value === "school" ? null : Number(value) }))
                  }
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="school">{t("templates.schoolWide")}</SelectItem>
                    {branches.map((branch) => (
                      <SelectItem key={branch.id} value={String(branch.id)}>
                        {branch.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-muted-foreground text-xs">{t("templates.scopeHint")}</p>
              </div>
            )}

            <div className="space-y-1.5">
              <p className="text-muted-foreground text-xs">{t("templates.placeholdersHint")}</p>
              <div className="flex flex-wrap gap-1.5">
                {PLACEHOLDERS.map((token) => (
                  <code key={token} className="bg-muted rounded-md px-1.5 py-0.5 text-[11px]">
                    {token}
                  </code>
                ))}
              </div>
            </div>

            {LANGS.map((lang) => (
              <div key={lang} className="space-y-2">
                <Label htmlFor={`template-body-${lang}`}>
                  {t(`templates.body.${lang}`)}
                </Label>
                <Textarea
                  id={`template-body-${lang}`}
                  value={draft.body[lang]}
                  onChange={(event) =>
                    setDraft((d) => ({ ...d, body: { ...d.body, [lang]: event.target.value } }))
                  }
                  rows={3}
                  className={lang !== "en" ? "font-ethiopic" : undefined}
                  placeholder={t("templates.bodyPlaceholder")}
                />
              </div>
            ))}

            <label className="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5">
              <span className="text-sm font-medium">{t("templates.active")}</span>
              <Switch
                checked={draft.is_active}
                onCheckedChange={(value) => setDraft((d) => ({ ...d, is_active: value }))}
              />
            </label>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setSheetOpen(false)}
              disabled={saving}
            >
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" onClick={() => void save()} loading={saving}>
              {tc("actions.save")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
