"use client"

import {
  ArrowLeftRight,
  ArrowDownLeft,
  ArrowUpRight,
  CheckCircle2,
  FileText,
  Inbox,
  Paperclip,
  Search,
  X,
  XCircle,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

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
import { AttachmentIcon, AttachmentTile } from "@/components/ui/attachment"
import { Badge } from "@/components/ui/badge"
import { runBulk } from "@/components/ui/bulk-actions"
import { BulkDecisionDialog } from "@/components/ui/bulk-decision-dialog"
import { Button } from "@/components/ui/button"
import { CopyableId } from "@/components/ui/copyable-id"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { useMediaPreview } from "@/components/ui/media-preview"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
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
import { ApplicationsInbox } from "@/components/transfers/applications-inbox"
import { TransferDetailSheet } from "@/components/transfers/transfer-detail-sheet"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  GradeLevel,
  Paginated,
  TransferCandidate,
  TransferRequest,
  TransferRequestStatus,
} from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"
import { cn } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

const TRANSFER_FILE_ACCEPT = ".pdf,.doc,.docx,image/jpeg,image/png,image/webp"
const TRANSFER_MAX_FILE_BYTES = 10 * 1024 * 1024

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

const STATUS_TONE: Record<TransferRequestStatus, string> = {
  requested: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
  rejected: "border-destructive/30 bg-destructive/10 text-destructive",
  cancelled: "border-border bg-muted text-muted-foreground",
}

type Direction = "all" | "incoming" | "outgoing" | "applications"

export default function TransfersPage() {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()
  const router = useRouter()
  const { openPreview, previewDialog } = useMediaPreview()

  const permissions = useEffectivePermissions()

  const [direction, setDirection] = useState<Direction>("all")
  const [rows, setRows] = useState<TransferRequest[] | null>(null)
  // Branch narrowing for the school-wide workspace — applied server-side
  // (refetch); the status filter stays client-side.
  const scope = useScopeQuery()
  const [sheetOpen, setSheetOpen] = useState(false)
  const [detail, setDetail] = useState<TransferRequest | null>(null)
  const [approving, setApproving] = useState<TransferRequest | null>(null)
  const [rejecting, setRejecting] = useState<TransferRequest | null>(null)
  const [cancelling, setCancelling] = useState<TransferRequest | null>(null)
  const [rejectNote, setRejectNote] = useState("")
  // Year-end: a whole cohort of incoming requests, decided in one pass.
  const [bulkDecision, setBulkDecision] = useState<{
    rows: TransferRequest[]
    decision: "approved" | "rejected"
  } | null>(null)
  const [working, setWorking] = useState(false)

  const hasContext = active.schoolId != null || active.branchId != null

  const load = useCallback(() => {
    if (!hasContext || direction === "applications") return
    setRows(null)
    let cancelled = false
    apiFetch<Paginated<TransferRequest>>(
      `/transfer-requests?direction=${direction}&per_page=100${scope.params}`,
    )
      .then((res) => !cancelled && setRows(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
  }, [direction, hasContext, scope.params, tc])

  // eslint-disable-next-line react-hooks/set-state-in-effect -- load resets to the loading state
  useEffect(() => load(), [load, active.branchId, active.schoolId])

  /** Are we the SENDING side of this request (approve/reject authority)? */
  /**
   * Approval is the SENDING side's call (it doubles as fee clearance), so a
   * sweep only ever offers rows we send and that are still pending.
   */
  function openBulk(selected: TransferRequest[], decision: "approved" | "rejected") {
    const actionable = selected.filter((r) => isSender(r) && r.status === "requested")
    if (actionable.length === 0) {
      toast.info(t("bulk.noneActionable"))
      return
    }
    setBulkDecision({ rows: actionable, decision })
  }

  const isSender = useCallback(
    (row: TransferRequest) =>
      active.branchId != null
        ? row.from_branch_id === active.branchId
        : row.from_school_id === active.schoolId,
    [active.branchId, active.schoolId],
  )

  const isReceiver = useCallback(
    (row: TransferRequest) =>
      active.branchId != null
        ? row.to_branch_id === active.branchId
        : row.to_school_id === active.schoolId,
    [active.branchId, active.schoolId],
  )

  async function decide(
    row: TransferRequest,
    action: "approve" | "reject" | "cancel",
    body?: Record<string, unknown>,
  ) {
    setWorking(true)
    try {
      await apiFetch(`/transfer-requests/${row.id}/${action}`, { method: "POST", body })
      toast.success(
        action === "approve" ? t("approved") : action === "reject" ? t("rejected") : t("cancelled"),
      )
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setWorking(false)
      setApproving(null)
      setRejecting(null)
      setCancelling(null)
      setRejectNote("")
    }
  }

  const columns: DataTableColumn<TransferRequest>[] = useMemo(
    () => [
      {
        key: "student",
        label: t("columns.student"),
        primary: true,
        render: (row) => (
          <div className="flex min-w-0 items-center gap-2.5">
            <PersonAvatar name={row.student?.full_name ?? "?"} photoUrl={row.student?.photo_url} />
            <div className="min-w-0">
              <p className="truncate text-sm font-medium">{row.student?.full_name}</p>
              <div className="flex items-center gap-1.5">
                <CopyableId value={row.student?.public_id} />
                {(row.attachments?.length ?? 0) > 0 && (
                  <button
                    type="button"
                    className="pressable inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    onClick={(e) => {
                      // The details sheet lists each file — tap one to preview.
                      e.stopPropagation()
                      setDetail(row)
                    }}
                  >
                    <Paperclip className="size-3" />
                    {t("detail.viewDocuments", { count: row.attachments!.length })}
                  </button>
                )}
              </div>
            </div>
          </div>
        ),
        exportValue: (row) => row.student?.full_name ?? "",
      },
      {
        key: "from",
        label: t("columns.from"),
        render: (row) => (
          <div className="text-xs">
            <p className="font-medium">{row.from_school_name}</p>
            <p className="text-muted-foreground">
              {row.from_branch_name} · {row.from_enrollment?.grade_level_name}
              {row.from_enrollment?.section_name ? ` ${row.from_enrollment.section_name}` : ""}
            </p>
          </div>
        ),
        exportValue: (row) => `${row.from_school_name} ${row.from_branch_name}`,
      },
      {
        key: "to",
        label: t("columns.to"),
        render: (row) => (
          <div className="text-xs">
            <p className="font-medium">{row.to_school_name}</p>
            <p className="text-muted-foreground">
              {row.to_branch_name} · {row.to_grade_level_name} ({row.to_academic_year_name})
            </p>
          </div>
        ),
        exportValue: (row) => `${row.to_school_name} ${row.to_branch_name}`,
      },
      {
        key: "status",
        label: t("columns.status"),
        render: (row) => (
          <div>
            <Badge variant="outline" className={cn("rounded-full", STATUS_TONE[row.status])}>
              {t(`statuses.${row.status}`)}
            </Badge>
            {isSender(row) && row.status === "requested" && (
              <p className="mt-1 text-[11px] text-warning">{t("direction.in")}</p>
            )}
          </div>
        ),
        exportValue: (row) => t(`statuses.${row.status}`),
      },
      {
        key: "created_at",
        label: t("columns.requested"),
        mobileHidden: true,
        render: (row) => (
          <span className="text-xs text-muted-foreground">
            {fmtDate(row.created_at)}
          </span>
        ),
        exportValue: (row) => row.created_at,
      },
    ],
    [t, isSender],
  )

  /** Supporting documents inside the decision dialogs — the sending school
   *  reviews these before approving/rejecting. */
  const documentsBlock = (row: TransferRequest | null) =>
    (row?.attachments?.length ?? 0) > 0 ? (
      <div className="space-y-1.5">
        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("documents")}
        </p>
        <div className="max-h-48 space-y-1.5 overflow-y-auto">
          {row!.attachments!.map((file, index) => (
            <AttachmentTile
              key={file.id}
              file={file}
              onPreview={() => openPreview(row!.attachments!, index)}
            />
          ))}
        </div>
      </div>
    ) : null

  return (
    <div className="space-y-6">
      {previewDialog}
      <PageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          hasContext ? (
            <Button onClick={() => setSheetOpen(true)}>
              <ArrowLeftRight className="size-4" />
              {t("new")}
            </Button>
          ) : undefined
        }
      />

      {!hasContext ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("noBranch")}
          </div>
        </div>
      ) : (
        <>
          {/* Direction pills — scroll within their row on narrow screens
              instead of widening the page (no horizontal body scroll). */}
          <div className="page-gutter flex gap-2 overflow-x-auto [scrollbar-width:none]">
            {(
              [
                ["all", ArrowLeftRight],
                ["incoming", ArrowDownLeft],
                ["outgoing", ArrowUpRight],
                // Family-initiated applications addressed to this school.
                ["applications", Inbox],
              ] as [Direction, typeof ArrowLeftRight][]
            ).map(([key, Icon]) => (
              <button
                key={key}
                type="button"
                onClick={() => setDirection(key)}
                className={cn(
                  "pressable inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3.5 text-xs font-medium transition-colors",
                  direction === key
                    ? "border-primary/40 bg-primary/10 text-primary"
                    : "text-muted-foreground hover:bg-muted",
                )}
                aria-pressed={direction === key}
              >
                <Icon className="size-3.5" />
                {t(`tabs.${key}`)}
              </button>
            ))}
          </div>

          {direction === "applications" ? (
            <ApplicationsInbox />
          ) : (
          <DataTable
            columns={columns}
            data={rows ?? []}
            loading={rows === null}
            searchKeys={[]}
            filters={[
              ...scope.filters,
              {
                key: "status",
                label: tc("filters.status"),
                options: (
                  ["requested", "approved", "rejected", "cancelled"] as TransferRequestStatus[]
                ).map((s) => ({ label: t(`statuses.${s}`), value: s })),
              },
            ]}
            filterValues={scope.values}
            onFilterChange={scope.setFilter}
            emptyMessage={t("empty")}
            exportFilename="transfers"
            onRowClick={(row) => setDetail(row)}
            bulkActions={[
              {
                label: t("actions.approve"),
                icon: CheckCircle2,
                onClick: (selected: TransferRequest[]) => openBulk(selected, "approved"),
              },
              {
                label: t("actions.reject"),
                icon: XCircle,
                destructive: true,
                onClick: (selected: TransferRequest[]) => openBulk(selected, "rejected"),
              },
            ]}
            actions={[
              {
                label: t("actions.approve"),
                icon: CheckCircle2,
                onClick: (row: TransferRequest) => setApproving(row),
                hidden: (row: TransferRequest) => !(isSender(row) && row.status === "requested"),
              },
              {
                label: t("actions.reject"),
                icon: XCircle,
                destructive: true,
                onClick: (row: TransferRequest) => setRejecting(row),
                hidden: (row: TransferRequest) => !(isSender(row) && row.status === "requested"),
              },
              {
                label: t("actions.cancel"),
                icon: X,
                destructive: true,
                onClick: (row: TransferRequest) => setCancelling(row),
                hidden: (row: TransferRequest) => !(isReceiver(row) && row.status === "requested"),
              },
              {
                label: t("actions.letter"),
                icon: FileText,
                onClick: (row: TransferRequest) => router.push(`/transfers/${row.id}/letter`),
                hidden: (row: TransferRequest) => row.status !== "approved",
              },
            ]}
          />
          )}
        </>
      )}

      {/* Decide a batch of incoming requests. */}
      <BulkDecisionDialog
        open={bulkDecision !== null}
        onOpenChange={(v) => {
          if (!v) setBulkDecision(null)
        }}
        mode={bulkDecision?.decision === "rejected" ? "reject" : "approve"}
        title={
          bulkDecision?.decision === "rejected"
            ? t("bulk.rejectTitle", { count: bulkDecision?.rows.length ?? 0 })
            : t("bulk.approveTitle", { count: bulkDecision?.rows.length ?? 0 })
        }
        description={
          bulkDecision?.decision === "rejected" ? t("bulk.rejectDesc") : t("bulk.approveDesc")
        }
        noteLabel={t("bulk.noteLabel")}
        notePlaceholder={
          bulkDecision?.decision === "rejected" ? t("bulk.notePlaceholder") : t("bulk.noteOptional")
        }
        confirmLabel={
          bulkDecision?.decision === "rejected" ? t("actions.reject") : t("actions.approve")
        }
        onConfirm={async (note) => {
          if (!bulkDecision) return
          await runBulk({
            url: "/transfer-requests/bulk/decide",
            ids: bulkDecision.rows.map((r) => r.id),
            body: { decision: bulkDecision.decision, decision_note: note || undefined },
            countKey: "decided",
            success: (count) =>
              bulkDecision.decision === "approved"
                ? t("bulk.approved", { count })
                : t("bulk.rejected", { count }),
            tc,
          })
          setBulkDecision(null)
          load()
        }}
      />

      <NewTransferSheet
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        onCreated={() => {
          setSheetOpen(false)
          load()
        }}
      />

      {/* Row click → the request's full story + side-appropriate actions. */}
      <TransferDetailSheet
        transfer={detail}
        onOpenChange={(open) => !open && setDetail(null)}
        isSender={detail !== null && isSender(detail)}
        isReceiver={detail !== null && isReceiver(detail)}
        canSeeFees={permissions.includes("fees.view")}
        canManageFees={permissions.includes("fees.manage")}
        canRecordPayments={permissions.includes("payments.record")}
        openPreview={openPreview}
        onApprove={(row) => {
          setDetail(null)
          setApproving(row)
        }}
        onReject={(row) => {
          setDetail(null)
          setRejecting(row)
        }}
        onCancel={(row) => {
          setDetail(null)
          setCancelling(row)
        }}
        onOpenLetter={(row) => router.push(`/transfers/${row.id}/letter`)}
      />

      {/* Approve — the irreversible handover. */}
      <AlertDialog open={approving !== null} onOpenChange={(open) => !open && setApproving(null)}>
        <AlertDialogContent size="lg">
          <AlertDialogHeader>
            <AlertDialogTitle>{t("approveTitle")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("approveDesc", {
                student: approving?.student?.full_name ?? "",
                school: approving?.to_school_name ?? "",
              })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          {documentsBlock(approving)}
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working}
              onClick={(e) => {
                e.preventDefault()
                if (approving) decide(approving, "approve")
              }}
            >
              {t("actions.approve")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Reject with a note. */}
      <AlertDialog open={rejecting !== null} onOpenChange={(open) => !open && setRejecting(null)}>
        <AlertDialogContent size="lg">
          <AlertDialogHeader>
            <AlertDialogTitle>{t("rejectTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("rejectDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          {documentsBlock(rejecting)}
          <textarea
            value={rejectNote}
            onChange={(e) => setRejectNote(e.target.value)}
            placeholder={t("rejectPlaceholder")}
            rows={3}
            className={TEXTAREA_CLASS}
          />
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working} disabled={rejectNote.trim().length === 0}
              onClick={(e) => {
                e.preventDefault()
                if (rejecting) decide(rejecting, "reject", { decision_note: rejectNote.trim() })
              }}
            >
              {t("actions.reject")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Withdraw own request. */}
      <AlertDialog open={cancelling !== null} onOpenChange={(open) => !open && setCancelling(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("cancelTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("cancelDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working}
              onClick={(e) => {
                e.preventDefault()
                if (cancelling) decide(cancelling, "cancel")
              }}
            >
              {t("actions.cancel")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}

/** Candidate lookup + request form, in one sheet. */
function NewTransferSheet({
  open,
  onOpenChange,
  onCreated,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: () => void
}) {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")
  const { active } = useSchoolContext()

  const [query, setQuery] = useState("")
  const [searching, setSearching] = useState(false)
  const [candidate, setCandidate] = useState<TransferCandidate | null>(null)
  const [notFound, setNotFound] = useState(false)
  const [years, setYears] = useState<AcademicYear[]>([])
  const [grades, setGrades] = useState<GradeLevel[]>([])
  const [yearId, setYearId] = useState<number | null>(null)
  const [gradeId, setGradeId] = useState<number | null>(null)
  const [reason, setReason] = useState("")
  // Each pending file carries an editable display name (like the student
  // registration wizard) — the backend stores it as the document title.
  const [files, setFiles] = useState<{ file: File; name: string }[]>([])
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (!open) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on close
      setQuery("")
      setCandidate(null)
      setNotFound(false)
      setReason("")
      setFiles([])
      return
    }
    apiFetch<Paginated<GradeLevel>>("/grade-levels")
      .then((res) => setGrades(res.data))
      .catch(() => {})
    apiFetch<Paginated<AcademicYear>>("/academic-years?per_page=100")
      .then((res) => {
        setYears(res.data)
        setYearId(res.data.find((y) => y.status === "active")?.id ?? res.data[0]?.id ?? null)
      })
      .catch(() => {})
  }, [open])

  async function search() {
    if (!query.trim()) return
    setSearching(true)
    setNotFound(false)
    setCandidate(null)
    try {
      const res = await apiFetch<{ data: TransferCandidate | null }>(
        `/transfer-requests/candidate?query=${encodeURIComponent(query.trim())}`,
      )
      if (res.data === null) {
        setNotFound(true)
      } else {
        setCandidate(res.data)
      }
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSearching(false)
    }
  }

  const sameBranch = candidate !== null && candidate.branch_id === active.branchId

  async function submit() {
    if (!candidate || !yearId || !gradeId || !reason.trim()) return
    setSubmitting(true)
    try {
      // Multipart: the supporting documents travel with the request itself.
      const body = new FormData()
      body.append("student_id", String(candidate.student_id))
      body.append("to_academic_year_id", String(yearId))
      body.append("to_grade_level_id", String(gradeId))
      body.append("reason", reason.trim())
      files.forEach((entry, index) => {
        body.append(`documents[${index}][file]`, entry.file)
        body.append(`documents[${index}][name]`, entry.name.trim() || entry.file.name)
      })
      await apiFetch("/transfer-requests", { method: "POST", body })
      toast.success(t("form.sent"))
      onCreated()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSubmitting(false)
    }
  }

  // Picked or dropped, supporting files land in the same renameable list
  // (capped at five — the sending school reviews these by hand).
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: TRANSFER_FILE_ACCEPT,
    maxSize: TRANSFER_MAX_FILE_BYTES,
    multiple: true,
    onFiles: (picked) =>
      setFiles((prev) =>
        [
          ...prev,
          ...picked.map((file) => ({ file, name: file.name.replace(/\.[^.]+$/, "") })),
        ].slice(0, 5),
      ),
  })

  const canSubmit =
    candidate !== null && !sameBranch && yearId !== null && gradeId !== null && !!reason.trim()

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("new")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody>
          <div className="space-y-5">
        <div className="space-y-2">
          <Label>{t("search.title")}</Label>
          <p className="text-xs text-muted-foreground">{t("search.hint")}</p>
          <div className="flex gap-2">
            <Input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && search()}
              placeholder={t("search.placeholder")}
              className="h-11 font-mono uppercase"
              autoFocus
            />
            <Button onClick={search} loading={searching} disabled={!query.trim()} className="h-11">
              <Search className="size-4" />
            </Button>
          </div>
          {notFound && <p className="text-sm text-destructive">{t("search.notFound")}</p>}
        </div>

        {candidate && (
          <div className="rounded-2xl border bg-muted/20 p-4">
            <div className="flex items-center gap-3">
              <PersonAvatar
                name={candidate.full_name}
                photoUrl={candidate.photo_url}
                className="size-11 text-sm"
              />
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{candidate.full_name}</p>
                <p className="text-xs text-muted-foreground">
                  {t("search.currentSchool")}: {candidate.school_name} · {candidate.branch_name} ·{" "}
                  {candidate.grade_level_name}
                </p>
              </div>
              <CopyableId value={candidate.public_id} />
            </div>
            {sameBranch && (
              <p className="mt-2 rounded-lg bg-warning/10 px-3 py-2 text-xs text-warning">
                {t("search.sameBranch")}
              </p>
            )}
          </div>
        )}

        {candidate && !sameBranch && (
          <div className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label>{t("form.targetYear")}</Label>
                <Select
                  value={yearId ? String(yearId) : ""}
                  onValueChange={(v) => setYearId(Number(v))}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {years.map((year) => (
                      <SelectItem key={year.id} value={String(year.id)}>
                        {year.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t("form.targetGrade")}</Label>
                <Select
                  value={gradeId ? String(gradeId) : ""}
                  onValueChange={(v) => setGradeId(Number(v))}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {grades.map((grade) => (
                      <SelectItem key={grade.id} value={String(grade.id)}>
                        {grade.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-2">
              <Label>{t("form.reason")}</Label>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t("form.reasonPlaceholder")}
                rows={3}
                className={TEXTAREA_CLASS}
              />
            </div>

            {/* Supporting documents — travel with the request for the
                sending school to review before deciding. */}
            <div
              {...dropProps}
              className={cn("space-y-2 rounded-2xl", dragOver && DROP_ACTIVE)}
            >
              <Label>{t("form.documents")}</Label>
              <p className="text-xs text-muted-foreground">{t("form.documentsHint")}</p>
              {files.length > 0 && (
                <ul className="space-y-1.5">
                  {files.map((entry, index) => (
                    <li
                      key={`${entry.file.name}-${index}`}
                      className="flex items-center gap-2.5 rounded-xl border px-2.5 py-2"
                    >
                      <AttachmentIcon mimeType={entry.file.type} className="size-8 [&_svg]:size-4" />
                      <div className="min-w-0 flex-1 space-y-0.5">
                        {/* Editable display name — stored as the document title. */}
                        <Input
                          value={entry.name}
                          onChange={(e) =>
                            setFiles((prev) =>
                              prev.map((f, i) => (i === index ? { ...f, name: e.target.value } : f)),
                            )
                          }
                          placeholder={t("form.documentNamePlaceholder")}
                          className="h-9"
                        />
                        <p className="truncate text-xs text-muted-foreground">
                          {entry.file.name} ·{" "}
                          {entry.file.size < 1024 * 1024
                            ? `${Math.max(1, Math.round(entry.file.size / 1024))} KB`
                            : `${(entry.file.size / (1024 * 1024)).toFixed(1)} MB`}
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        className="text-muted-foreground hover:text-destructive"
                        aria-label={tc("actions.delete")}
                        onClick={() => setFiles((prev) => prev.filter((_, i) => i !== index))}
                      >
                        <X className="size-4" />
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
              {files.length < 5 && (
                <div className="flex flex-wrap items-center gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => fileInputRef.current?.click()}
                  >
                    <Paperclip className="size-4" />
                    {t("form.addDocument")}
                  </Button>
                  <DropHint />
                </div>
              )}
              <input
                ref={fileInputRef}
                type="file"
                multiple
                hidden
                accept={TRANSFER_FILE_ACCEPT}
                onChange={(e) => {
                  takeFiles(e.target.files)
                  e.target.value = ""
                }}
              />
            </div>

          </div>
        )}
          </div>
        </ResponsiveSheetBody>
        {/* Standard sheet actions — pinned to the bottom like every other
            right-side sheet (see academic-year-sheet). */}
        <ResponsiveSheetFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            type="button"
            className="h-11 flex-1"
            onClick={submit}
            loading={submitting} disabled={!canSubmit}
          >
            {t("form.submit")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
