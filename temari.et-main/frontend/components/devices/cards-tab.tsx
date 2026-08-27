"use client"

import Link from "next/link"
import { Layers, Nfc, Plus } from "lucide-react"
import { useCallback, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { AsyncCombobox, type AsyncComboboxOption } from "@/components/ui/async-combobox"
import { Button } from "@/components/ui/button"
import { CopyableId } from "@/components/ui/copyable-id"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
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
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { IdCardRow, IdCardStatus } from "@/lib/types"
import { useScopeFilters } from "@/lib/use-scope-filters"
import { useServerTable } from "@/lib/use-server-table"
import { cn } from "@/lib/utils"

import { ScopeSelects, type ScopeValue } from "./scope-selects"
import { addisToday } from "@/lib/dates"

const STATUS_TONE: Record<IdCardStatus, string> = {
  active: "border-transparent bg-success/10 text-success",
  lost: "border-transparent bg-warning/10 text-warning",
  revoked: "border-transparent bg-destructive/10 text-destructive",
  replaced: "border-transparent bg-muted text-muted-foreground",
}

interface PersonRow {
  id: number
  full_name?: string
  name?: string
  public_id?: string | null
}

function todayAddis(): string {
  return addisToday()
}

/**
 * The card register. Physical cards come from Temari.et: platform staff issue
 * (individually, in bulk, or as replacements); school staff view their
 * register and REPORT a lost/damaged card — which opens a fulfilment request.
 */
export function CardsTab() {
  const { t } = useTranslation("devices")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const canManage = permissions.includes("cards.manage")
  const canReport = permissions.includes("cards.report")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const table = useServerTable<IdCardRow>({
    endpoint: "/cards",
    defaultSort: { key: "created_at", dir: "desc" },
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("cards.loadFailed"),
  })

  // ── Individual issue (platform) ──
  const [issueOpen, setIssueOpen] = useState(false)
  const [issueScope, setIssueScope] = useState<ScopeValue>({ schoolId: null, branchId: null })
  const [holderType, setHolderType] = useState<"student" | "employee">("student")
  const [holder, setHolder] = useState<AsyncComboboxOption | null>(null)
  const [uid, setUid] = useState("")
  const [issuedOn, setIssuedOn] = useState(todayAddis())
  const [note, setNote] = useState("")
  const [saving, setSaving] = useState(false)

  // ── Replace (platform) / report lost (school) ──
  const [replacing, setReplacing] = useState<IdCardRow | null>(null)
  const [replacementUid, setReplacementUid] = useState("")
  const [reporting, setReporting] = useState<IdCardRow | null>(null)
  const [reportReason, setReportReason] = useState<"lost" | "damaged">("lost")
  const [reportNote, setReportNote] = useState("")

  const personFetcher = useCallback(
    async (query: string, signal: AbortSignal): Promise<AsyncComboboxOption[]> => {
      const endpoint = holderType === "student" ? "/students" : "/employees"
      const params = new URLSearchParams({ search: query, per_page: "10" })
      if (issueScope.branchId != null) params.set("branch_id", String(issueScope.branchId))
      const res = await apiFetch<{ data: PersonRow[] }>(`${endpoint}?${params}`, { signal })
      return res.data.map((p) => ({
        value: String(p.id),
        label: p.full_name ?? p.name ?? `#${p.id}`,
        description: p.public_id ?? undefined,
      }))
    },
    [holderType, issueScope.branchId],
  )

  function resetIssueForm() {
    setHolder(null)
    setUid("")
    setIssuedOn(todayAddis())
    setNote("")
  }

  async function issue() {
    if (!holder || !uid.trim()) return
    setSaving(true)
    try {
      await apiFetch("/cards", {
        method: "POST",
        body: {
          holder_type: holderType,
          holder_id: Number(holder.value),
          card_uid: uid.trim(),
          issued_on: issuedOn || undefined,
          note: note.trim() || undefined,
        },
      })
      toast.success(t("cards.issued"))
      setIssueOpen(false)
      resetIssueForm()
      table.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  async function replace() {
    if (!replacing || !replacementUid.trim()) return
    setSaving(true)
    try {
      await apiFetch(`/cards/${replacing.id}/replace`, {
        method: "POST",
        body: { card_uid: replacementUid.trim() },
      })
      toast.success(t("cards.replaced"))
      setReplacing(null)
      setReplacementUid("")
      table.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  async function submitReport() {
    if (!reporting) return
    setSaving(true)
    try {
      await apiFetch(`/cards/${reporting.id}/report-lost`, {
        method: "POST",
        body: { reason: reportReason, note: reportNote.trim() || undefined },
      })
      toast.success(t("cards.reported"))
      setReporting(null)
      setReportNote("")
      setReportReason("lost")
      table.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  function revoke(card: IdCardRow) {
    confirmDelete(async () => {
      await apiFetch(`/cards/${card.id}/deactivate`, {
        method: "POST",
        body: { status: "revoked" },
      })
      toast.success(t("cards.revokedDone"))
      table.refetch()
    }, t("cards.revokeWarning", { name: card.holder_name ?? card.card_uid }))
  }

  const showScope = isPlatform || active.branchId == null

  const columns: DataTableColumn<IdCardRow>[] = [
    {
      key: "holder_name",
      label: t("cards.columns.holder"),
      primary: true,
      render: (row) => (
        <div className="leading-tight">
          <span className="block text-sm font-medium">{row.holder_name ?? "—"}</span>
          <span className="block text-xs text-muted-foreground">
            {t(`cards.holderTypes.${row.holder_type}`)}
          </span>
        </div>
      ),
      exportValue: (row) => row.holder_name ?? "",
    },
    ...(showScope
      ? [
          {
            key: "scope_name",
            label: isPlatform ? tc("filters.school") : tc("filters.branch"),
            mobileHidden: true,
            render: (row: IdCardRow) => (
              <div className="text-xs leading-tight">
                {isPlatform && <span className="block">{row.school_name ?? "—"}</span>}
                <span className={cn("block", isPlatform && "text-muted-foreground")}>
                  {row.branch_name ?? "—"}
                </span>
              </div>
            ),
            exportValue: (row: IdCardRow) =>
              [row.school_name, row.branch_name].filter(Boolean).join(" · "),
          } satisfies DataTableColumn<IdCardRow>,
        ]
      : []),
    {
      key: "card_uid",
      label: t("cards.columns.uid"),
      render: (row) => <CopyableId value={row.card_uid} />,
      exportValue: (row) => row.card_uid,
    },
    {
      key: "status",
      label: t("cards.columns.status"),
      sortable: true,
      render: (row) => (
        <Badge className={cn("text-[11px] whitespace-nowrap", STATUS_TONE[row.status])}>
          {t(`cards.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => t(`cards.statuses.${row.status}`),
    },
    {
      key: "issued_on",
      label: t("cards.columns.issued"),
      sortable: true,
      mobileHidden: true,
      render: (row) => (
        <div className="text-xs leading-tight">
          <span className="block tabular-nums">{row.issued_on ?? "—"}</span>
          {row.issued_by_name && (
            <span className="block text-muted-foreground">{row.issued_by_name}</span>
          )}
        </div>
      ),
      exportValue: (row) => row.issued_on ?? "",
    },
    ...(canManage || canReport
      ? [
          {
            key: "actions",
            label: "",
            render: (row: IdCardRow) => {
              if (row.status === "active") {
                return (
                  <div className="flex justify-end gap-1.5 whitespace-nowrap">
                    {canReport && !canManage && (
                      <Button
                        variant="outline"
                        size="sm"
                        className="h-8 text-xs"
                        onClick={(e) => {
                          e.stopPropagation()
                          setReporting(row)
                        }}
                      >
                        {t("cards.reportLost")}
                      </Button>
                    )}
                    {canManage && (
                      <>
                        <Button
                          variant="outline"
                          size="sm"
                          className="h-8 text-xs"
                          onClick={(e) => {
                            e.stopPropagation()
                            setReplacing(row)
                          }}
                        >
                          {t("cards.replace")}
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          className="h-8 text-xs text-destructive"
                          onClick={(e) => {
                            e.stopPropagation()
                            revoke(row)
                          }}
                        >
                          {t("cards.revoke")}
                        </Button>
                      </>
                    )}
                  </div>
                )
              }
              if (canManage && row.status !== "replaced") {
                return (
                  <div className="flex justify-end">
                    <Button
                      variant="outline"
                      size="sm"
                      className="h-8 text-xs whitespace-nowrap"
                      onClick={(e) => {
                        e.stopPropagation()
                        setReplacing(row)
                      }}
                    >
                      {t("cards.issueReplacement")}
                    </Button>
                  </div>
                )
              }
              return null
            },
          } satisfies DataTableColumn<IdCardRow>,
        ]
      : []),
  ]

  const scopeFilters = useScopeFilters(table.filters)

  const filterDefs: DataTableFilter[] = [
    ...scopeFilters,
    {
      key: "status",
      label: tc("filters.status"),
      options: (["active", "lost", "revoked", "replaced"] as const).map((s) => ({
        label: t(`cards.statuses.${s}`),
        value: s,
      })),
    },
    {
      key: "holder_type",
      label: t("cards.columns.holder"),
      options: [
        { label: t("cards.holderTypes.student"), value: "student" },
        { label: t("cards.holderTypes.employee"), value: "employee" },
      ],
    },
  ]

  return (
    <>
      {canManage && (
        <div className="page-gutter flex flex-wrap justify-end gap-2">
          <Button size="sm" variant="outline" asChild>
            <Link href="/devices/issue">
              <Layers className="size-4" /> {t("cards.bulkIssue")}
            </Link>
          </Button>
          <Button size="sm" onClick={() => setIssueOpen(true)}>
            <Plus className="size-4" /> {t("cards.issue")}
          </Button>
        </div>
      )}

      <DataTable
        columns={columns}
        data={table.rows}
        loading={table.loading}
        serverMode
        searchable
        searchValue={table.searchInput}
        onSearchChange={table.setSearchInput}
        searchPlaceholder={t("cards.searchPlaceholder")}
        filters={filterDefs}
        filterValues={table.filters}
        onFilterChange={table.setFilter}
        onSortChange={table.onSortChange}
        emptyMessage={t("cards.empty")}
        exportFilename="id-cards"
        pagination={table.pagination}
      />

      {/* Individual issue — platform staff */}
      <ResponsiveSheet
        open={issueOpen}
        onOpenChange={(open) => {
          setIssueOpen(open)
          if (!open) resetIssueForm()
        }}
      >
        <ResponsiveSheetContent className="data-[side=right]:sm:max-w-md">
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("cards.issue")}</ResponsiveSheetTitle>
            <ResponsiveSheetDescription>{t("cards.issueHint")}</ResponsiveSheetDescription>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <ScopeSelects
              value={issueScope}
              onChange={(scope) => {
                setIssueScope(scope)
                setHolder(null)
              }}
              layout="stack"
            />
            <div className="space-y-2">
              <Label>{t("cards.fields.holderType")}</Label>
              <div className="grid grid-cols-2 gap-2">
                {(["student", "employee"] as const).map((type) => (
                  <button
                    key={type}
                    type="button"
                    onClick={() => {
                      setHolderType(type)
                      setHolder(null)
                    }}
                    className={cn(
                      "pressable min-h-10 rounded-xl border text-sm font-medium transition-colors",
                      holderType === type
                        ? "border-primary bg-primary/5 text-primary"
                        : "text-muted-foreground hover:bg-muted",
                    )}
                    aria-pressed={holderType === type}
                  >
                    {t(`cards.holderTypes.${type}`)}
                  </button>
                ))}
              </div>
            </div>
            <div className="space-y-2">
              <Label>
                {t(`cards.holderTypes.${holderType}`)} <span className="text-destructive">*</span>
              </Label>
              <AsyncCombobox
                value={holder}
                onChange={setHolder}
                fetcher={personFetcher}
                placeholder={t("cards.fields.personPlaceholder")}
                searchPlaceholder={tc("actions.search")}
                emptyText={t("cards.fields.noMatches")}
              />
            </div>
            {/* The chip — keyboard-wedge readers type the UID + Enter */}
            <div className="space-y-2">
              <Label>
                {t("cards.fields.uid")} <span className="text-destructive">*</span>
              </Label>
              <div className="relative">
                <Nfc className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={uid}
                  onChange={(e) => setUid(e.target.value.toUpperCase())}
                  onKeyDown={(e) => e.key === "Enter" && issue()}
                  placeholder={t("cards.fields.uidPlaceholder")}
                  className="pl-10 font-mono uppercase"
                  maxLength={32}
                />
              </div>
              <p className="text-xs text-muted-foreground">{t("cards.fields.uidHelp")}</p>
            </div>
            <div className="space-y-2">
              <Label>{t("cards.fields.issuedOn")}</Label>
              <DatePicker value={issuedOn} onChange={setIssuedOn} clearable={false} />
            </div>
            <div className="space-y-2">
              <Label>{t("cards.fields.note")}</Label>
              <Input
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder={t("cards.fields.notePlaceholder")}
              />
            </div>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setIssueOpen(false)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              onClick={issue}
              loading={saving} disabled={!holder || !uid.trim()}
            >
              {t("cards.issueAction")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Report lost/damaged — school staff */}
      <Dialog
        open={reporting !== null}
        onOpenChange={(open) => {
          if (!open) {
            setReporting(null)
            setReportNote("")
            setReportReason("lost")
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {t("cards.reportTitle", { name: reporting?.holder_name ?? "" })}
            </DialogTitle>
            <DialogDescription>{t("cards.reportHint")}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{t("cards.fields.reason")}</Label>
              <Select
                value={reportReason}
                onValueChange={(v) => setReportReason(v as "lost" | "damaged")}
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="lost">{t("requests.reasons.lost")}</SelectItem>
                  <SelectItem value="damaged">{t("requests.reasons.damaged")}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t("cards.fields.note")}</Label>
              <textarea
                value={reportNote}
                onChange={(e) => setReportNote(e.target.value)}
                rows={3}
                placeholder={t("cards.fields.reportNotePlaceholder")}
                className="w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              />
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setReporting(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" onClick={submitReport} loading={saving}>
              {t("cards.reportAction")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Replace — platform staff */}
      <Dialog
        open={replacing !== null}
        onOpenChange={(open) => {
          if (!open) {
            setReplacing(null)
            setReplacementUid("")
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {t("cards.replaceTitle", { name: replacing?.holder_name ?? "" })}
            </DialogTitle>
            <DialogDescription>{t("cards.replaceHint")}</DialogDescription>
          </DialogHeader>
          <div className="relative">
            <Nfc className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={replacementUid}
              autoFocus
              onChange={(e) => setReplacementUid(e.target.value.toUpperCase())}
              onKeyDown={(e) => e.key === "Enter" && replace()}
              placeholder={t("cards.fields.uidPlaceholder")}
              className="pl-10 font-mono uppercase"
              maxLength={32}
            />
          </div>
          <DialogFooter className="gap-2">
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setReplacing(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              onClick={replace}
              loading={saving} disabled={!replacementUid.trim()}
            >
              {t("cards.issueAction")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {confirmDialog}
    </>
  )
}
