"use client"

import { Check, Paperclip, X } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { CopyableId } from "@/components/ui/copyable-id"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { runBulk } from "@/components/ui/bulk-actions"
import { BulkDecisionDialog } from "@/components/ui/bulk-decision-dialog"
import { useTranslation } from "@/lib/i18n"
import { useScopeQuery } from "@/lib/use-scope-filters"

interface ExcuseRow {
  id: number
  student_id: number
  student_name: string | null
  student_public_id: string | null
  student_photo_url: string | null
  grade_level: string | null
  section: string | null
  branch_name: string | null
  requester_name: string | null
  requester_phone: string | null
  starts_on: string
  ends_on: string
  reason: string
  attachment_url: string | null
  status: "pending" | "approved" | "rejected"
  decided_by: string | null
  decided_at: string | null
  decision_note: string | null
}

const STATUS_TINT: Record<ExcuseRow["status"], string> = {
  pending: "bg-warning/10 text-warning",
  approved: "bg-success/10 text-success",
  rejected: "bg-destructive/10 text-destructive",
}

/**
 * The branch's absence-excuse review queue: parent-filed explanations for
 * absences, pending first. Approving retro-marks the range's absent days as
 * excused; both outcomes notify the family.
 */
export default function AbsenceExcusesPage() {
  const { t } = useTranslation("attendance")
  const { t: tc } = useTranslation("common")

  const scope = useScopeQuery()
  const [rows, setRows] = useState<ExcuseRow[] | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  // Decision dialog state — one flow for approve and reject.
  const [deciding, setDeciding] = useState<{ row: ExcuseRow; decision: "approved" | "rejected" } | null>(null)
  const [note, setNote] = useState("")
  const [saving, setSaving] = useState(false)
  // Monday morning: a screenful of parent notes, one decision.
  const [bulkDeciding, setBulkDeciding] = useState<{
    rows: ExcuseRow[]
    decision: "approved" | "rejected"
  } | null>(null)

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on scope switch
    setRows(null)
    apiFetch<{ data: ExcuseRow[] }>(`/absence-excuses?per_page=100${scope.params}`)
      .then((res) => !cancelled && setRows(res.data))
      .catch(() => !cancelled && setRows([]))
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- scope.key IS scope.params
  }, [scope.key, reloadKey])

  async function decide() {
    if (!deciding) return
    setSaving(true)
    try {
      const res = await apiFetch<{ data: ExcuseRow & { excused_days: number }; message: string }>(
        `/absence-excuses/${deciding.row.id}/decide`,
        {
          method: "POST",
          body: JSON.stringify({ decision: deciding.decision, note: note.trim() || null }),
        },
      )
      toast.success(
        deciding.decision === "approved"
          ? t("excuses.approvedToast", { days: res.data.excused_days })
          : t("excuses.rejectedToast"),
      )
      setDeciding(null)
      setNote("")
      setReloadKey((k) => k + 1)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  const columns: DataTableColumn<ExcuseRow>[] = [
    {
      key: "student_name",
      label: t("excuses.columns.student"),
      primary: true,
      sortable: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <PersonAvatar
            name={row.student_name ?? "?"}
            photoUrl={row.student_photo_url}
            className="size-8 text-[9px]"
          />
          <div className="min-w-0">
            <p className="truncate text-sm font-medium">{row.student_name}</p>
            <div className="flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
              <CopyableId value={row.student_public_id} />
              {[row.grade_level, row.section].filter(Boolean).join(" · ")}
            </div>
          </div>
        </div>
      ),
      exportValue: (row) => `${row.student_public_id ?? ""} ${row.student_name ?? ""}`.trim(),
    },
    {
      key: "starts_on",
      label: t("excuses.columns.dates"),
      sortable: true,
      render: (row) => (
        <span className="text-sm tabular-nums">
          {row.starts_on === row.ends_on ? row.starts_on : `${row.starts_on} → ${row.ends_on}`}
        </span>
      ),
      exportValue: (row) => `${row.starts_on} → ${row.ends_on}`,
    },
    {
      key: "reason",
      label: t("excuses.columns.reason"),
      render: (row) => (
        <p className="max-w-72 truncate text-sm text-muted-foreground" title={row.reason}>
          {row.reason}
        </p>
      ),
      exportValue: (row) => row.reason,
    },
    {
      key: "requester_name",
      label: t("excuses.columns.requester"),
      mobileHidden: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate text-sm">{row.requester_name}</p>
          <ContactActionCell value={row.requester_phone} />
        </div>
      ),
      exportValue: (row) => `${row.requester_name ?? ""} ${row.requester_phone ?? ""}`.trim(),
    },
    {
      key: "status",
      label: t("excuses.columns.status"),
      render: (row) => (
        <div className="min-w-0">
          <Badge variant="outline" className={`border-transparent ${STATUS_TINT[row.status]}`}>
            {t(`excuses.statuses.${row.status}`)}
          </Badge>
          {row.decided_by && (
            <p className="mt-0.5 truncate text-xs text-muted-foreground">{row.decided_by}</p>
          )}
        </div>
      ),
      exportValue: (row) => row.status,
    },
  ]

  // Only pending excuses can be decided; a selection of settled rows says so
  // rather than opening a dialog that would skip everything.
  function openBulk(rows: ExcuseRow[], decision: "approved" | "rejected") {
    const pending = rows.filter((r) => r.status === "pending")
    if (pending.length === 0) {
      toast.info(t("excuses.bulk.nonePending"))
      return
    }
    setBulkDeciding({ rows: pending, decision })
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("excuses.title")} description={t("excuses.subtitle")} />

      <DataTable
        columns={columns}
        data={rows ?? []}
        loading={rows === null}
        searchKeys={["student_name", "student_public_id", "requester_name", "reason"]}
        searchPlaceholder={tc("actions.search")}
        filters={[
          ...scope.filters,
          {
            key: "status",
            label: t("excuses.columns.status"),
            options: (["pending", "approved", "rejected"] as const).map((status) => ({
              label: t(`excuses.statuses.${status}`),
              value: status,
            })),
          },
        ]}
        filterValues={scope.values}
        onFilterChange={scope.setFilter}
        bulkActions={[
          {
            label: t("excuses.approve"),
            icon: Check,
            onClick: (rows: ExcuseRow[]) => openBulk(rows, "approved"),
          },
          {
            label: t("excuses.reject"),
            icon: X,
            destructive: true,
            onClick: (rows: ExcuseRow[]) => openBulk(rows, "rejected"),
          },
        ]}
        actions={[
          {
            label: t("excuses.attachment"),
            icon: Paperclip,
            onClick: (row: ExcuseRow) => window.open(row.attachment_url ?? "", "_blank"),
            hidden: (row: ExcuseRow) => row.attachment_url === null,
          },
          {
            label: t("excuses.approve"),
            icon: Check,
            onClick: (row: ExcuseRow) => {
              setNote("")
              setDeciding({ row, decision: "approved" })
            },
            hidden: (row: ExcuseRow) => row.status !== "pending",
          },
          {
            label: t("excuses.reject"),
            icon: X,
            onClick: (row: ExcuseRow) => {
              setNote("")
              setDeciding({ row, decision: "rejected" })
            },
            hidden: (row: ExcuseRow) => row.status !== "pending",
          },
        ]}
        emptyMessage={t("excuses.empty")}
        exportFilename="absence-excuses"
      />

      {/* ── Decision dialog: approve/reject + optional note ── */}
      <Dialog open={deciding !== null} onOpenChange={(open) => !open && setDeciding(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {deciding?.decision === "approved"
                ? t("excuses.approveTitle")
                : t("excuses.rejectTitle")}
            </DialogTitle>
            <DialogDescription>
              {deciding
                ? t("excuses.decideDesc", {
                    student: deciding.row.student_name ?? "",
                    from: deciding.row.starts_on,
                    to: deciding.row.ends_on,
                  })
                : ""}
            </DialogDescription>
          </DialogHeader>
          {deciding && (
            <p className="rounded-xl border bg-muted/30 px-3 py-2.5 text-sm text-muted-foreground">
              {deciding.row.reason}
            </p>
          )}
          <Textarea
            value={note}
            onChange={(e) => setNote(e.target.value)}
            rows={2}
            maxLength={500}
            placeholder={t("excuses.notePlaceholder")}
          />
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              className="h-11 flex-1"
              onClick={() => setDeciding(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              type="button"
              className="h-11 flex-1"
              variant={deciding?.decision === "rejected" ? "destructive" : "default"}
              onClick={decide}
              loading={saving}
            >
              {saving
                ? tc("actions.saving")
                : deciding?.decision === "approved"
                  ? t("excuses.approve")
                  : t("excuses.reject")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Clear the queue: one decision for every selected excuse. */}
      <BulkDecisionDialog
        open={bulkDeciding !== null}
        onOpenChange={(v) => {
          if (!v) setBulkDeciding(null)
        }}
        mode={bulkDeciding?.decision === "rejected" ? "reject" : "approve"}
        title={
          bulkDeciding?.decision === "rejected"
            ? t("excuses.bulk.rejectTitle", { count: bulkDeciding?.rows.length ?? 0 })
            : t("excuses.bulk.approveTitle", { count: bulkDeciding?.rows.length ?? 0 })
        }
        description={
          bulkDeciding?.decision === "rejected"
            ? t("excuses.bulk.rejectDesc")
            : t("excuses.bulk.approveDesc")
        }
        noteLabel={t("excuses.note")}
        notePlaceholder={t("excuses.notePlaceholder")}
        confirmLabel={
          bulkDeciding?.decision === "rejected" ? t("excuses.reject") : t("excuses.approve")
        }
        onConfirm={async (bulkNote) => {
          if (!bulkDeciding) return
          await runBulk({
            url: "/absence-excuses/bulk/decide",
            ids: bulkDeciding.rows.map((r) => r.id),
            body: { decision: bulkDeciding.decision, note: bulkNote || undefined },
            countKey: "decided",
            success: (count) =>
              bulkDeciding.decision === "approved"
                ? t("excuses.bulk.approved", { count })
                : t("excuses.bulk.rejected", { count }),
            tc,
          })
          setBulkDeciding(null)
          setReloadKey((k) => k + 1)
        }}
      />
    </div>
  )
}
