"use client"

import {
  CalendarClock,
  CheckCircle2,
  Loader2,
  Plus,
  Sparkles,
  Trash2,
  Upload,
} from "lucide-react"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { PeriodScheduleTab } from "@/components/timetable/period-schedule-tab"
import { GridEditor } from "@/components/timetable/grid-editor"
import { RoomsTab } from "@/components/timetable/rooms-tab"
import { SetupWizard } from "@/components/timetable/setup-wizard"
import { WeeklyGridReadOnly } from "@/components/timetable/weekly-grid"
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
import { BranchScopePicker, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { TermSelect } from "@/components/academic/term-select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  Paginated,
  StudentTimetable,
  Term,
  TimetableVersion,
  TimetableVersionStatus,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const STATUS_TONE: Record<TimetableVersionStatus, string> = {
  draft: "border-info/30 bg-info/10 text-info",
  generating: "border-warning/30 bg-warning/10 text-warning",
  published: "border-success/30 bg-success/10 text-success",
  archived: "border-border bg-muted text-muted-foreground",
}

type Tab = "grid" | "periods" | "rooms"

/** First-time setup state, piggybacked on the versions index response. */
type SetupMeta = { has_periods: boolean; rooms_count: number; has_loads?: boolean }

export default function TimetablePage() {
  const { t } = useTranslation("timetable")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const canManage = permissions.includes("timetable.manage")
  const hasBranch = active.branchId != null

  // School-wide workspace: timetabling is one-branch-at-a-time, so school
  // managers pick the working branch here instead of switching workspaces.
  // Terms load for that branch; everything deeper hangs off the term/version.
  const { needsBranch } = useBranchScope()
  const [pickedBranchId, setPickedBranchId] = useState<number | null>(null)
  const branchReady = hasBranch || (needsBranch && pickedBranchId != null)
  const workingBranchId = hasBranch ? null : pickedBranchId
  const branchParam = workingBranchId != null ? `&branch_id=${workingBranchId}` : ""

  const [terms, setTerms] = useState<Term[]>([])
  const [termId, setTermId] = useState<number | null>(null)
  const [versions, setVersions] = useState<TimetableVersion[] | null>(null)
  const [versionId, setVersionId] = useState<number | null>(null)
  const [setupMeta, setSetupMeta] = useState<SetupMeta | null>(null)
  const [skipWizard, setSkipWizard] = useState(false)
  const [tab, setTab] = useState<Tab>("grid")
  const [refreshKey, setRefreshKey] = useState(0)
  const [newOpen, setNewOpen] = useState(false)
  const [newName, setNewName] = useState("")
  const [newSaturday, setNewSaturday] = useState(false)
  // Creating a draft and leaving it empty is almost never what anyone wants —
  // the solver runs by default, and the operator opts out to hand-build.
  const [newAutoGenerate, setNewAutoGenerate] = useState(true)
  const [copyFromId, setCopyFromId] = useState<number | null>(null)
  const [publishing, setPublishing] = useState<TimetableVersion | null>(null)
  const [working, setWorking] = useState(false)
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null)

  useEffect(() => {
    if (!branchReady) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on context switch
      setTerms([])
      setTermId(null)
      setVersions(null)
      return
    }
    let cancelled = false
    apiFetch<Paginated<Term>>(`/terms?per_page=100${branchParam}`)
      .then((res) => {
        if (cancelled) return
        setTerms(res.data)
        setTermId((prev) =>
          prev && res.data.some((term) => term.id === prev)
            ? prev
            : (res.data.find((term) => term.is_current)?.id ?? res.data[0]?.id ?? null),
        )
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [branchReady, branchParam, active.branchId])

  const loadVersions = useCallback(
    (keepSelection = true, selectId: number | null = null) => {
      if (!termId) return
      apiFetch<{ data: TimetableVersion[]; meta?: SetupMeta }>(
        `/terms/${termId}/timetable-versions`,
      )
        .then((res) => {
          setVersions(res.data)
          setSetupMeta(res.meta ?? null)
          setVersionId((prev) => {
            // A just-created draft always wins the selection.
            if (selectId !== null && res.data.some((v) => v.id === selectId)) return selectId
            if (keepSelection && prev && res.data.some((v) => v.id === prev)) return prev
            return (
              res.data.find((v) => v.status === "published")?.id ?? res.data[0]?.id ?? null
            )
          })
        })
        .catch((error) => {
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
          setVersions([])
        })
    },
    [termId, tc],
  )

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on term change
    setVersions(null)
    setVersionId(null)
    setSetupMeta(null)
    setSkipWizard(false)
    loadVersions(false)
  }, [loadVersions])

  const selected = useMemo(
    () => versions?.find((v) => v.id === versionId) ?? null,
    [versions, versionId],
  )

  // Poll while the solver runs; bump the grid when it finishes.
  useEffect(() => {
    if (selected?.status !== "generating") return
    pollRef.current = setInterval(async () => {
      try {
        const res = await apiFetch<{ data: TimetableVersion[] }>(
          `/terms/${termId}/timetable-versions`,
        )
        setVersions(res.data)
        const fresh = res.data.find((v) => v.id === selected.id)
        if (fresh && fresh.status !== "generating") {
          toast.success(t("versions.generated"))
          setRefreshKey((k) => k + 1)
        }
      } catch {
        // transient — keep polling
      }
    }, 2500)
    return () => {
      if (pollRef.current) clearInterval(pollRef.current)
    }
  }, [selected?.status, selected?.id, termId, t])

  async function createDraft() {
    if (!termId || !newName.trim()) return
    setWorking(true)
    try {
      const res = await apiFetch<{ data: TimetableVersion }>(
        `/terms/${termId}/timetable-versions`,
        {
          method: "POST",
          body: {
            name: newName.trim(),
            days: newSaturday ? [1, 2, 3, 4, 5, 6] : [1, 2, 3, 4, 5],
            copy_from_id: copyFromId,
          },
        },
      )
      const versionId = res.data.id

      if (newAutoGenerate) {
        try {
          await apiFetch(`/timetable-versions/${versionId}/generate`, { method: "POST", body: {} })
          toast.success(t("versions.createdGenerating"))
        } catch {
          // The draft exists — they can still generate by hand from the grid.
          toast.success(t("versions.created"))
        }
      } else {
        toast.success(t("versions.created"))
      }

      setNewOpen(false)
      setNewName("")
      setCopyFromId(null)
      setNewAutoGenerate(true)
      loadVersions(false, versionId)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  async function generate(version: TimetableVersion) {
    setWorking(true)
    try {
      await apiFetch(`/timetable-versions/${version.id}/generate`, { method: "POST", body: {} })
      loadVersions()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
    }
  }

  async function publish() {
    if (!publishing) return
    setWorking(true)
    try {
      await apiFetch(`/timetable-versions/${publishing.id}/publish`, { method: "POST" })
      toast.success(t("versions.published"))
      loadVersions()
      setRefreshKey((k) => k + 1)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
      setPublishing(null)
    }
  }

  async function removeDraft(version: TimetableVersion) {
    try {
      await apiFetch(`/timetable-versions/${version.id}`, { method: "DELETE" })
      loadVersions(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const conflictsCount = Array.isArray(selected?.conflicts) ? selected.conflicts.length : 0

  // First setup of this semester's schedule: no versions yet → the guided
  // wizard (period schedule → rooms → first draft) replaces the free tabs.
  const showWizard =
    canManage &&
    termId !== null &&
    versions !== null &&
    versions.length === 0 &&
    setupMeta !== null &&
    !skipWizard

  const termSelect = (
    <TermSelect
      terms={terms}
      value={termId ? String(termId) : ""}
      onValueChange={(v) => setTermId(Number(v))}
      placeholder={t("semester")}
      aria-label={t("semester")}
      className="h-9 w-auto min-w-40 rounded-full bg-muted/30 text-xs font-medium"
      emptyNotice={tc("emptySelect.terms")}
    />
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          branchReady && canManage && termId && showWizard ? (
            <>
              {termSelect}
              <button
                type="button"
                onClick={() => setSkipWizard(true)}
                className="pressable min-h-9 text-xs font-medium text-muted-foreground underline-offset-4 hover:underline"
              >
                {t("setup.manual")}
              </button>
            </>
          ) : branchReady && canManage && termId && !showWizard ? (
            <Button onClick={() => setNewOpen(true)}>
              <Plus className="size-4" />
              {t("versions.new")}
            </Button>
          ) : undefined
        }
      />

      {needsBranch && (
        <div className="page-gutter">
          <BranchScopePicker value={pickedBranchId} onChange={setPickedBranchId} />
        </div>
      )}

      {!branchReady ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("noBranch")}
          </div>
        </div>
      ) : !canManage ? (
        <MyTimetable termId={termId} terms={terms} onTermChange={setTermId} />
      ) : showWizard && termId ? (
        <>
          <SetupWizard
            key={`${active.branchId ?? workingBranchId}-${termId}`}
            termId={termId}
            termName={terms.find((term) => term.id === termId)?.name ?? ""}
            hasPeriods={setupMeta?.has_periods ?? false}
            hasLoads={setupMeta?.has_loads ?? true}
            onDone={(id) => loadVersions(false, id)}
            branchId={workingBranchId}
          />
        </>
      ) : (
        <>
          {/* Term + tabs + version pickers — one toolbar line. */}
          <div className="page-gutter flex flex-wrap items-center gap-2">
            {termSelect}

            <div className="flex gap-1.5">
              {(["grid", "periods", "rooms"] as Tab[]).map((key) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => setTab(key)}
                  className={cn(
                    "pressable inline-flex min-h-9 items-center rounded-full border px-3.5 text-xs font-medium transition-colors",
                    tab === key
                      ? "border-primary/40 bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-muted",
                  )}
                  aria-pressed={tab === key}
                >
                  {t(`tabs.${key}`)}
                </button>
              ))}
            </div>

            {versions !== null && versions.length > 0 && (
              <Select
                value={versionId ? String(versionId) : ""}
                onValueChange={(v) => setVersionId(Number(v))}
              >
                <SelectTrigger className="h-9 w-auto min-w-44 rounded-full bg-muted/30 text-xs font-medium" aria-label={t("versions.title")}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {versions.map((version) => (
                    <SelectItem key={version.id} value={String(version.id)}>
                      {version.name} — {t(`versions.statuses.${version.status}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}

            {selected && (
              <>
                <Badge variant="outline" className={cn("rounded-full", STATUS_TONE[selected.status])}>
                  {selected.status === "generating" && (
                    <Loader2 className="size-3 animate-spin" />
                  )}
                  {t(`versions.statuses.${selected.status}`)}
                </Badge>
                {selected.score !== null && selected.status === "draft" && (
                  <span className="text-xs text-muted-foreground tabular-nums">
                    {t("versions.score", { score: selected.score })}
                  </span>
                )}
                {conflictsCount > 0 && (
                  <Badge variant="outline" className="rounded-full border-destructive/30 bg-destructive/10 text-destructive">
                    {t("versions.conflicts", { count: conflictsCount })}
                  </Badge>
                )}
              </>
            )}

            {selected && (
              <div className="ml-auto flex flex-wrap gap-1.5">
                {selected.status === "draft" && (
                  <>
                    <Button size="sm" variant="outline" onClick={() => generate(selected)} loading={working}>
                      <Sparkles className="size-4" />
                      {selected.slots_count > 0 ? t("versions.regenerate") : t("versions.generate")}
                    </Button>
                    <Button size="sm" onClick={() => setPublishing(selected)} loading={working}>
                      <Upload className="size-4" />
                      {t("versions.publish")}
                    </Button>
                    <Button
                      size="sm"
                      variant="ghost"
                      className="text-destructive"
                      onClick={() =>
                        confirmDelete(
                          () => removeDraft(selected),
                          tc("confirmDelete.named", { name: selected.name }),
                        )
                      }
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </>
                )}
              </div>
            )}
          </div>

          <div className="page-gutter">
            {tab === "periods" && termId && <PeriodScheduleTab termId={termId} canManage={canManage} />}
            {tab === "rooms" && <RoomsTab canManage={canManage} branchId={workingBranchId} />}
            {tab === "grid" &&
              (versions === null ? (
                <Skeleton className="h-96 rounded-2xl" />
              ) : versions.length === 0 ? (
                <div className="rounded-2xl border bg-card shadow-xs">
                  <EmptyState
                    icon={CalendarClock}
                    title={t("versions.empty")}
                    description={t("versions.emptyDesc")}
                    action={
                      canManage ? (
                        <Button onClick={() => setNewOpen(true)}>
                          <Plus className="size-4" />
                          {t("versions.new")}
                        </Button>
                      ) : undefined
                    }
                  />
                </div>
              ) : selected?.status === "generating" ? (
                <div className="flex flex-col items-center gap-3 rounded-2xl border bg-card py-16 shadow-xs">
                  <Loader2 className="size-6 animate-spin text-primary" />
                  <p className="text-sm text-muted-foreground">{t("versions.generating")}</p>
                </div>
              ) : (
                versionId && (
                  <GridEditor
                    versionId={versionId}
                    canManage={canManage}
                    editable={selected?.status === "draft" || selected?.status === "published"}
                    refreshKey={refreshKey}
                  />
                )
              ))}
          </div>
        </>
      )}

      {/* New draft dialog. */}
      <Dialog open={newOpen} onOpenChange={setNewOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>{t("versions.new")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{t("versions.name")}</Label>
              <Input
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                placeholder={t("versions.namePlaceholder")}
                autoFocus
              />
            </div>
            {(versions?.length ?? 0) > 0 && (
              <div className="space-y-2">
                <Label>{t("versions.copyFrom")}</Label>
                <Select
                  value={copyFromId ? String(copyFromId) : "blank"}
                  onValueChange={(v) => setCopyFromId(v === "blank" ? null : Number(v))}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="blank">{t("versions.blank")}</SelectItem>
                    {(versions ?? []).map((version) => (
                      <SelectItem key={version.id} value={String(version.id)}>
                        {version.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
            <label className="flex min-h-11 items-center gap-2 text-sm">
              <Checkbox
                checked={newSaturday}
                onCheckedChange={(v) => setNewSaturday(v === true)}
              />
              {t("versions.saturday")}
            </label>
            <label className="flex items-start gap-2.5 rounded-xl border border-primary/30 bg-primary/5 p-3 text-sm">
              <Checkbox
                checked={newAutoGenerate}
                onCheckedChange={(v) => setNewAutoGenerate(v === true)}
                className="mt-0.5"
              />
              <span className="min-w-0">
                <span className="font-medium">{t("versions.autoGenerate")}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">
                  {copyFromId
                    ? t("versions.autoGenerateCopyHint")
                    : t("versions.autoGenerateHint")}
                </span>
              </span>
            </label>
            <Button className="w-full" onClick={createDraft} loading={working} disabled={!newName.trim()}>
              {newAutoGenerate ? <Sparkles className="size-4" /> : null}
              {newAutoGenerate ? t("versions.createGenerate") : tc("actions.create")}
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Publish confirmation. */}
      <AlertDialog open={publishing !== null} onOpenChange={(open) => !open && setPublishing(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("versions.publishTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("versions.publishDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working}
              onClick={(e) => {
                e.preventDefault()
                publish()
              }}
            >
              <CheckCircle2 className="size-4" />
              {t("versions.publish")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

/** Read-only weekly view for teachers (their own published lessons). */
function MyTimetable({
  termId,
  terms,
  onTermChange,
}: {
  termId: number | null
  terms: Term[]
  onTermChange: (id: number) => void
}) {
  const { t } = useTranslation("timetable")
  const { t: tc } = useTranslation("common")

  const [data, setData] = useState<StudentTimetable | null | undefined>(undefined)

  useEffect(() => {
    if (!termId) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on term change
    setData(undefined)
    apiFetch<{ data: (StudentTimetable & { version_id?: number }) | null }>(
      `/my-timetable?term_id=${termId}`,
    )
      .then((res) => !cancelled && setData(res.data))
      .catch(() => !cancelled && setData(null))
    return () => {
      cancelled = true
    }
  }, [termId])

  return (
    <div className="page-gutter space-y-4">
      <TermSelect
        terms={terms}
        value={termId ? String(termId) : ""}
        onValueChange={(v) => onTermChange(Number(v))}
        placeholder={t("semester")}
        aria-label={t("semester")}
        className="h-9 w-auto min-w-40 rounded-full bg-muted/30 text-xs font-medium"
        emptyNotice={tc("emptySelect.terms")}
      />

      {data === undefined ? (
        <Skeleton className="h-72 rounded-2xl" />
      ) : data === null ? (
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState icon={CalendarClock} title={t("mine.empty")} compact />
        </div>
      ) : (
        <WeeklyGridReadOnly data={data} />
      )}
    </div>
  )
}
