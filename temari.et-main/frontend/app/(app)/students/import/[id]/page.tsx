"use client"

import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  Download,
  Loader2,
  Pencil,
  Play,
  Trash2,
  Users,
} from "lucide-react"
import Link from "next/link"
import { useParams, useRouter } from "next/navigation"
import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { RowEditSheet } from "@/components/students/import/row-edit-sheet"
import { downloadFailedRows, FAILED_EXPORT_HEADERS } from "@/components/students/import/sheet"
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
import { PageHeader } from "@/components/ui/page-header"
import { Switch } from "@/components/ui/switch"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type {
  GradeLevel,
  Paginated,
  Section,
  StudentImport,
  StudentImportRow,
  StudentImportRowStatus,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const PAGE_SIZE = 50
const POLL_MS = 2500

const STATUS_TINT: Record<StudentImportRowStatus, string> = {
  ready: "bg-success/10 text-success",
  duplicate: "bg-amber-500/10 text-amber-700 dark:text-amber-400",
  error: "bg-destructive/10 text-destructive",
  imported: "bg-success/10 text-success",
  skipped: "bg-muted text-muted-foreground",
  failed: "bg-destructive/10 text-destructive",
}

/** Chip filters offered per import phase. */
function chipStatuses(status: StudentImport["status"]): (StudentImportRowStatus | "all")[] {
  return status === "draft"
    ? ["all", "ready", "duplicate", "error"]
    : ["all", "imported", "skipped", "failed", "error"]
}

export default function StudentImportReviewPage() {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()
  const router = useRouter()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const importId = Number(params.id)
  const canCreate = permissions.includes("students.create")

  const [session, setSession] = useState<StudentImport | null>(null)
  const [sessionTick, setSessionTick] = useState(0)
  const [loadFailed, setLoadFailed] = useState(false)
  /** null = loading. */
  const [rows, setRows] = useState<StudentImportRow[] | null>(null)
  const [rowsTick, setRowsTick] = useState(0)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState<StudentImportRowStatus | "all">("all")
  const [editing, setEditing] = useState<StudentImportRow | null>(null)

  const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([])
  const [sections, setSections] = useState<Section[]>([])

  const [sendSms, setSendSms] = useState(false)
  const [createAccounts, setCreateAccounts] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const [committing, setCommitting] = useState(false)
  const [exporting, setExporting] = useState(false)

  const prevStatusRef = useRef<StudentImport["status"] | null>(null)

  // The import session — refetched whenever sessionTick bumps (poll, commit).
  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: StudentImport }>(`/student-imports/${importId}`)
      .then((res) => {
        if (cancelled) return
        const wasImporting = prevStatusRef.current === "importing"
        prevStatusRef.current = res.data.status
        setSession(res.data)
        // The job just landed: reset the grid onto the results.
        if (wasImporting && res.data.status !== "importing") {
          setStatusFilter("all")
          setPage(1)
          setRows(null)
          setRowsTick((tick) => tick + 1)
        }
      })
      .catch(() => !cancelled && setLoadFailed(true))
    return () => {
      cancelled = true
    }
  }, [importId, sessionTick])

  // Poll while the queued job runs.
  useEffect(() => {
    if (session?.status !== "importing") return
    const timer = setTimeout(() => setSessionTick((tick) => tick + 1), POLL_MS)
    return () => clearTimeout(timer)
  }, [session, sessionTick])

  // The visible page of rows.
  useEffect(() => {
    let cancelled = false
    const statusParam = statusFilter === "all" ? "" : `&status=${statusFilter}`
    apiFetch<Paginated<StudentImportRow>>(
      `/student-imports/${importId}/rows?page=${page}&per_page=${PAGE_SIZE}${statusParam}`,
    )
      .then((res) => {
        if (cancelled) return
        setRows(res.data)
        setLastPage(res.meta.last_page)
      })
      .catch(() => !cancelled && toast.error(t("loadFailed")))
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [importId, page, statusFilter, rowsTick])

  // Branch-scoped catalogs for the edit sheet — pinned to the import's branch
  // so the school-wide workspace edits against the right register.
  useEffect(() => {
    if (!session) return
    let cancelled = false
    apiFetch<{ data: GradeLevel[] }>(`/grade-levels?branch_id=${session.branch_id}`)
      .then((res) => !cancelled && setGradeLevels(res.data))
      .catch(() => {})
    apiFetch<Paginated<Section>>(`/sections?branch_id=${session.branch_id}&per_page=100`)
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [session?.branch_id])

  const stats = session?.row_stats ?? {}
  const importableCount = session?.importable_count ?? stats.ready ?? 0

  function statusCount(status: StudentImportRowStatus | "all"): number {
    if (status === "all") return session?.total_rows ?? 0
    return stats[status] ?? 0
  }

  function showStatus(status: StudentImportRowStatus | "all") {
    setStatusFilter(status)
    setPage(1)
    setRows(null)
  }

  async function commit() {
    setCommitting(true)
    try {
      await apiFetch(`/student-imports/${importId}/commit`, {
        method: "POST",
        body: { options: { send_sms: sendSms, create_student_accounts: createAccounts } },
      })
      setConfirmOpen(false)
      setSessionTick((tick) => tick + 1)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setCommitting(false)
    }
  }

  async function exportFailed() {
    setExporting(true)
    try {
      const res = await apiFetch<Paginated<StudentImportRow>>(
        `/student-imports/${importId}/rows?status=failed,error&per_page=100`,
      )
      if (res.data.length === 0) {
        toast.info(t("import.report.noFailed"))
        return
      }
      await downloadFailedRows(FAILED_EXPORT_HEADERS, res.data, "temari-import-fixes.xlsx")
    } finally {
      setExporting(false)
    }
  }

  function removeImport() {
    confirmDelete(async () => {
      await apiFetch(`/student-imports/${importId}`, { method: "DELETE" })
      router.push("/students")
    })
  }

  if (!canCreate || loadFailed) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("import.title")} backHref="/students" backLabel={t("title")} />
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {loadFailed ? t("loadFailed") : tc("errors.forbidden")}
          </div>
        </div>
      </div>
    )
  }

  if (!session) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center">
        <Loader2 className="size-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  const isDraft = session.status === "draft"
  const isImporting = session.status === "importing"
  const done = session.imported_count + session.skipped_count + session.failed_count

  return (
    <div className="space-y-6 pb-6">
      <PageHeader
        title={t("import.reviewTitle")}
        description={`${session.file_name} · ${session.academic_year?.name ?? ""}${
          session.branch ? ` · ${session.branch.name}` : ""
        }`}
        backHref="/students"
        backLabel={t("title")}
        actions={
          isDraft ? (
            <Button variant="outline" className="h-11" onClick={removeImport}>
              <Trash2 className="size-4" />
              {t("import.discard")}
            </Button>
          ) : undefined
        }
      />

      <div className="page-gutter space-y-6">
        {/* Importing progress */}
        {isImporting ? (
          <div className="space-y-3 rounded-2xl border p-6 text-center">
            <Loader2 className="mx-auto size-6 animate-spin text-primary" />
            <p className="text-sm font-medium">{t("import.progress.running")}</p>
            <div className="mx-auto h-2 w-full max-w-sm overflow-hidden rounded-full bg-muted">
              <div
                className="h-full rounded-full bg-primary transition-all"
                style={{
                  width: `${session.total_rows > 0 ? Math.round((done / session.total_rows) * 100) : 0}%`,
                }}
              />
            </div>
            <p className="text-xs text-muted-foreground">
              {t("import.progress.counts", { done, total: session.total_rows })}
            </p>
          </div>
        ) : null}

        {/* Completed report */}
        {session.status === "completed" || session.status === "failed" ? (
          <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3">
              {(
                [
                  ["imported", session.imported_count],
                  ["skipped", session.skipped_count],
                  ["failed", session.failed_count + (stats.error ?? 0)],
                ] as const
              ).map(([key, value]) => (
                <div key={key} className="rounded-2xl border p-4 text-center">
                  <p className="text-2xl font-semibold tabular-nums">{value}</p>
                  <p className="text-xs text-muted-foreground">{t(`import.report.${key}`)}</p>
                </div>
              ))}
            </div>
            <div className="flex flex-wrap gap-2">
              <Button asChild className="h-11">
                <Link href="/students">
                  <Users className="size-4" />
                  {t("import.report.goToStudents")}
                </Link>
              </Button>
              {session.failed_count + (stats.error ?? 0) > 0 ? (
                <Button
                  variant="outline"
                  className="h-11"
                  disabled={exporting}
                  onClick={() => void exportFailed()}
                >
                  {exporting ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                  {t("import.report.downloadFailed")}
                </Button>
              ) : null}
            </div>
            {session.status === "failed" ? (
              <p className="flex items-center gap-2 rounded-xl bg-destructive/10 px-3 py-2 text-sm text-destructive">
                <AlertTriangle className="size-4" />
                {t("import.report.jobFailed")}
              </p>
            ) : null}
          </div>
        ) : null}

        {/* Status chips */}
        <div className="flex flex-wrap gap-2">
          {chipStatuses(session.status).map((status) => (
            <button
              key={status}
              type="button"
              onClick={() => showStatus(status)}
              className={cn(
                "flex h-9 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors",
                statusFilter === status
                  ? "border-primary bg-primary/10 text-primary"
                  : "text-muted-foreground hover:bg-muted",
              )}
            >
              {t(`import.status.${status}`)}
              <span className="tabular-nums">{statusCount(status)}</span>
            </button>
          ))}
        </div>

        {/* The grid */}
        <div className="overflow-x-auto rounded-2xl border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-12">#</TableHead>
                <TableHead>{t("import.grid.student")}</TableHead>
                <TableHead className="hidden sm:table-cell">{t("import.grid.grade")}</TableHead>
                <TableHead className="hidden md:table-cell">{t("import.grid.guardian")}</TableHead>
                <TableHead>{t("import.grid.status")}</TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows === null ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center">
                    <Loader2 className="mx-auto size-5 animate-spin text-muted-foreground" />
                  </TableCell>
                </TableRow>
              ) : rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-sm text-muted-foreground">
                    {t("import.grid.empty")}
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((row) => {
                  const grade = gradeLevels.find((g) => g.id === row.payload.grade_level_id)
                  const section = sections.find((s) => s.id === row.payload.section_id)
                  const guardian = row.payload.guardians?.[0]
                  return (
                    <TableRow key={row.id} className="cursor-pointer" onClick={() => setEditing(row)}>
                      <TableCell className="text-xs tabular-nums text-muted-foreground">
                        {row.row_number}
                      </TableCell>
                      <TableCell>
                        <p className="text-sm font-medium">
                          {[row.payload.first_name, row.payload.father_name, row.payload.grandfather_name]
                            .filter(Boolean)
                            .join(" ") || "—"}
                        </p>
                        <p className="text-xs text-muted-foreground">
                          {row.payload.gender ? t(`import.row.${row.payload.gender}`) : "—"}
                          {row.payload.date_of_birth ? ` · ${row.payload.date_of_birth}` : ""}
                        </p>
                      </TableCell>
                      <TableCell className="hidden text-sm sm:table-cell">
                        {grade?.name ?? "—"}
                        {section ? ` · ${section.name}` : ""}
                      </TableCell>
                      <TableCell className="hidden md:table-cell">
                        <p className="text-sm">
                          {[guardian?.first_name, guardian?.father_name].filter(Boolean).join(" ") || "—"}
                        </p>
                        <p className="text-xs text-muted-foreground">{guardian?.phone ?? ""}</p>
                      </TableCell>
                      <TableCell>
                        <Badge className={cn("border-transparent", STATUS_TINT[row.status])}>
                          {row.status === "duplicate" && row.resolution
                            ? t(`import.status.duplicateAs.${row.resolution}`)
                            : t(`import.status.${row.status}`)}
                        </Badge>
                        {row.issues.length > 0 ? (
                          <span className="ml-1.5 align-middle text-xs text-muted-foreground">
                            {row.issues.length}
                          </span>
                        ) : null}
                      </TableCell>
                      <TableCell onClick={(event) => event.stopPropagation()}>
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Button
                              variant="ghost"
                              size="icon"
                              className="size-8"
                              aria-label={isDraft ? tc("actions.edit") : t("import.grid.view")}
                              onClick={() => setEditing(row)}
                            >
                              <Pencil className="size-4" />
                            </Button>
                          </TooltipTrigger>
                          <TooltipContent>
                            {isDraft ? tc("actions.edit") : t("import.grid.view")}
                          </TooltipContent>
                        </Tooltip>
                      </TableCell>
                    </TableRow>
                  )
                })
              )}
            </TableBody>
          </Table>
        </div>

        {/* Pager */}
        {lastPage > 1 ? (
          <div className="flex items-center justify-center gap-3">
            <Button
              variant="outline"
              size="icon"
              className="size-9"
              aria-label={tc("actions.back")}
              disabled={page <= 1}
              onClick={() => {
                setPage((p) => p - 1)
                setRows(null)
              }}
            >
              <ChevronLeft className="size-4" />
            </Button>
            <span className="text-sm tabular-nums text-muted-foreground">
              {page} / {lastPage}
            </span>
            <Button
              variant="outline"
              size="icon"
              className="size-9"
              aria-label={tc("actions.next")}
              disabled={page >= lastPage}
              onClick={() => {
                setPage((p) => p + 1)
                setRows(null)
              }}
            >
              <ChevronRight className="size-4" />
            </Button>
          </div>
        ) : null}

        {/* Commit panel — sticky so the start button stays in reach while
            scrolling a long row grid (above the mobile bottom nav). */}
        {isDraft ? (
          <div className="sticky max-w-2xl mx-auto bottom-20 z-30 space-y-4 rounded-2xl border bg-card p-4 shadow-lg md:bottom-4">
            <h2 className="text-sm font-semibold">{t("import.commit.heading")}</h2>

            <label className="flex items-start justify-between gap-4">
              <span>
                <span className="block text-sm font-medium">{t("import.commit.accounts")}</span>
                <span className="block text-xs text-muted-foreground">
                  {t("import.commit.accountsHint")}
                </span>
              </span>
              <Switch checked={createAccounts} onCheckedChange={setCreateAccounts} />
            </label>

            <label className="flex items-start justify-between gap-4">
              <span>
                <span className="block text-sm font-medium">{t("import.commit.sms")}</span>
                <span className="block text-xs text-muted-foreground">
                  {sendSms ? t("import.commit.smsOnHint") : t("import.commit.smsOffHint")}
                </span>
              </span>
              <Switch checked={sendSms} onCheckedChange={setSendSms} />
            </label>

            <Button
              className="h-11 w-full"
              loading={committing} disabled={importableCount === 0}
              onClick={() => setConfirmOpen(true)}
            >
              <Play className="size-4" />
              {t("import.commit.start", { count: importableCount })}
            </Button>
            {(stats.error ?? 0) > 0 ? (
              <p className="text-xs text-muted-foreground">
                {t("import.commit.errorsLeftBehind", { count: stats.error ?? 0 })}
              </p>
            ) : null}
          </div>
        ) : null}
      </div>

      {/* Commit confirmation — the SMS decision is stated out loud. */}
      <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("import.commit.confirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription className="space-y-2">
              <span className="block">{t("import.commit.confirmBody", { count: importableCount })}</span>
              <span
                className={cn(
                  "block rounded-lg px-3 py-2 text-sm",
                  sendSms
                    ? "bg-amber-500/10 text-amber-700 dark:text-amber-400"
                    : "bg-muted text-muted-foreground",
                )}
              >
                {sendSms
                  ? t("import.commit.confirmSmsOn", { count: importableCount })
                  : t("import.commit.confirmSmsOff")}
              </span>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="h-11 flex-1">{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction className="h-11 flex-1" loading={committing} onClick={() => void commit()}>
              {committing ? <Loader2 className="size-4 animate-spin" /> : null}
              {t("import.commit.confirmAction")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {confirmDialog}
      <RowEditSheet
        importId={importId}
        row={editing}
        gradeLevels={gradeLevels}
        sections={sections}
        editable={isDraft}
        onOpenChange={(open) => !open && setEditing(null)}
        onSaved={(saved) => {
          setRows((prev) => (prev ?? []).map((row) => (row.id === saved.id ? saved : row)))
          setSessionTick((tick) => tick + 1)
        }}
      />
    </div>
  )
}
