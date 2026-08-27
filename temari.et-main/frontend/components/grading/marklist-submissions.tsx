"use client"

import { BellRing, CalendarClock, ExternalLink } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
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
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { fmtDate } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import type { MarklistStatusMeta, MarklistStatusRow } from "@/lib/types"
import { cn } from "@/lib/utils"

type MonitorStatus = MarklistStatusRow["status"]

// Same tokens as the marklist register — plus the red "nothing yet" state
// that only exists on the monitor.
const STATUS_BADGE: Record<MonitorStatus, string> = {
  not_started: "border-destructive/30 bg-destructive/10 text-destructive",
  draft: "bg-muted text-muted-foreground",
  submitted: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
}
const STATUS_ROW: Partial<Record<MonitorStatus, string>> = {
  not_started: "bg-destructive/[0.05] hover:bg-destructive/[0.09]",
  approved: "bg-success/[0.05] hover:bg-success/[0.09]",
}
const PIPELINE: { status: MonitorStatus; bar: string }[] = [
  { status: "approved", bar: "bg-success" },
  { status: "submitted", bar: "bg-warning" },
  { status: "draft", bar: "bg-muted-foreground/40" },
  { status: "not_started", bar: "bg-destructive/70" },
]

/** Monitor row + the flat keys DataTable search/filters read. */
type Row = MarklistStatusRow & {
  teacher_key: string
  subject_key: string
  section_key: string
  entry_percent: number | null
}

/**
 * The submission monitor: who has (not) turned in their marklists, how full
 * each marks grid actually is, and a one-tap nudge. Sorted worst-first by the
 * server — the director works the list top-down.
 */
export function MarklistSubmissionsPanel({
  termId,
  gradeId,
  sectionId,
}: {
  termId: string
  gradeId: string
  sectionId: string
}) {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const [rows, setRows] = useState<Row[] | null>(null)
  const [meta, setMeta] = useState<MarklistStatusMeta | null>(null)
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})
  const [remindingId, setRemindingId] = useState<number | null>(null)
  const [bulkOpen, setBulkOpen] = useState(false)
  const [bulkBusy, setBulkBusy] = useState(false)

  const canRemind = permissions.includes("grades.approve")

  useEffect(() => {
    if (!termId) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new query
    setRows(null)
    const params = new URLSearchParams()
    if (sectionId !== "all") params.set("section_id", sectionId)
    else if (gradeId && gradeId !== "all") params.set("grade_level_id", gradeId)
    const qs = params.size > 0 ? `?${params}` : ""
    apiFetch<{ data: MarklistStatusRow[]; meta: MarklistStatusMeta }>(
      `/terms/${termId}/marklist-status${qs}`,
    )
      .then((res) => {
        if (cancelled) return
        setRows(
          res.data.map((r) => ({
            ...r,
            id: r.subject_assignment_id,
            teacher_key: r.teacher.name ?? "",
            subject_key: r.subject.name ?? "",
            section_key: r.section.name,
            entry_percent: r.entry.percent,
          })),
        )
        setMeta(res.meta)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setRows([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [termId, gradeId, sectionId])

  const pendingRows = useMemo(
    () => (rows ?? []).filter((r) => r.status !== "approved" && r.teacher.has_account),
    [rows],
  )

  async function remind(ids: number[]) {
    try {
      // apiFetch JSON-encodes the body itself — pass the plain object.
      const res = await apiFetch<{ data: { teachers: number } }>(
        `/terms/${termId}/marklist-reminders`,
        { method: "POST", body: { subject_assignment_ids: ids } },
      )
      toast.success(t("reports.submissions.reminded", { count: res.data.teachers }))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  // Snapshotted once per mount — a ticking "days left" would be pointless churn.
  const [now] = useState(() => Date.now())
  const daysLeft = useMemo(() => {
    if (!meta?.term.ends_on || meta.term.status !== "active") return null
    const diff = Math.ceil((new Date(meta.term.ends_on).getTime() - now) / (24 * 60 * 60 * 1000))
    return diff >= 0 ? diff : null
  }, [meta, now])

  const columns: DataTableColumn<Row>[] = useMemo(
    () => [
      {
        key: "teacher_key",
        label: t("marklists.teacher"),
        primary: true,
        render: (row) => (
          <div className="flex min-w-0 items-center gap-2.5">
            <PersonAvatar
              name={row.teacher.name ?? "?"}
              photoUrl={row.teacher.photo_url}
              className="size-7 shrink-0"
            />
            <span className="truncate font-medium">{row.teacher.name ?? "—"}</span>
          </div>
        ),
        exportValue: (row) => row.teacher.name ?? "",
      },
      {
        key: "subject_key",
        label: t("reports.submissions.class"),
        render: (row) => (
          <p className="min-w-0 truncate">
            {row.subject.name}
            <span className="text-muted-foreground"> · {row.section.name}</span>
          </p>
        ),
        exportValue: (row) => `${row.subject.name ?? ""} — ${row.section.name}`,
      },
      {
        key: "entry_percent",
        label: t("reports.submissions.entry"),
        render: (row) =>
          row.entry.percent === null ? (
            <span className="text-muted-foreground text-xs">
              {t("reports.submissions.noColumns")}
            </span>
          ) : (
            <div className="flex items-center gap-2">
              <div className="bg-muted h-1.5 w-16 overflow-hidden rounded-full">
                <div
                  className={cn(
                    "h-full rounded-full transition-all",
                    row.entry.percent >= 100
                      ? "bg-success"
                      : row.entry.percent >= 50
                        ? "bg-warning"
                        : "bg-destructive/70",
                  )}
                  style={{ width: `${Math.min(row.entry.percent, 100)}%` }}
                />
              </div>
              <span className="text-xs font-medium tabular-nums">{row.entry.percent}%</span>
            </div>
          ),
        exportValue: (row) => (row.entry.percent === null ? "" : `${row.entry.percent}%`),
      },
      {
        key: "status",
        label: t("marklists.status"),
        render: (row) => (
          <Badge variant="outline" className={cn("border", STATUS_BADGE[row.status])}>
            {t(`reports.submissions.statuses.${row.status}`)}
          </Badge>
        ),
        exportValue: (row) => row.status,
      },
      {
        key: "submitted_at",
        label: t("reports.submissions.submittedOn"),
        mobileHidden: true,
        render: (row) => (
          <span className="text-muted-foreground text-xs">
            {row.submitted_at ? fmtDate(row.submitted_at) : "—"}
          </span>
        ),
        exportValue: (row) => row.submitted_at ?? "",
      },
    ],
    [t],
  )

  return (
    <div className="space-y-4">
      {/* The pipeline: one glance = how close to done this term's grading is. */}
      {meta !== null && meta.total > 0 && (
        <section className="bg-card space-y-3 rounded-2xl border p-4 shadow-xs">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 className="font-display text-base font-semibold">
                {t("reports.submissions.pipelineTitle")}
              </h2>
              <p className="text-muted-foreground mt-0.5 text-xs">
                {t("reports.submissions.pipelineHint", {
                  approved: meta.approved,
                  total: meta.total,
                })}
                {daysLeft !== null && (
                  <span className="text-warning ml-1.5 inline-flex items-center gap-1 font-medium">
                    <CalendarClock className="size-3" />
                    {t("reports.submissions.daysLeft", { count: daysLeft })}
                  </span>
                )}
              </p>
            </div>
            {canRemind && (
              <Button
                size="sm"
                variant="outline"
                disabled={pendingRows.length === 0}
                onClick={() => setBulkOpen(true)}
              >
                <BellRing className="size-4" />
                {t("reports.submissions.remindAll", { count: pendingRows.length })}
              </Button>
            )}
          </div>

          <div className="bg-muted flex h-2.5 overflow-hidden rounded-full">
            {PIPELINE.map(({ status, bar }) => {
              const count = meta[status]
              if (count === 0) return null
              return (
                <div
                  key={status}
                  className={cn(bar, "h-full transition-all")}
                  style={{ width: `${(count / meta.total) * 100}%` }}
                />
              )
            })}
          </div>

          {/* Legend chips double as one-tap status filters. */}
          <div className="flex flex-wrap gap-1.5">
            {PIPELINE.map(({ status, bar }) => {
              const activeFilter = filterValues.status === status
              return (
                <button
                  key={status}
                  type="button"
                  onClick={() =>
                    setFilterValues((prev) => ({
                      ...prev,
                      status: activeFilter ? "" : status,
                    }))
                  }
                  className={cn(
                    "flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors",
                    activeFilter
                      ? "border-primary/40 bg-primary/10"
                      : "hover:bg-accent/50 border-transparent",
                  )}
                >
                  <span className={cn("size-2 rounded-full", bar)} />
                  {t(`reports.submissions.statuses.${status}`)}
                  <span className="text-muted-foreground tabular-nums">{meta[status]}</span>
                </button>
              )
            })}
          </div>
        </section>
      )}

      <DataTable
        columns={columns}
        data={rows ?? []}
        loading={rows === null}
        dense
        searchKeys={["teacher_key", "subject_key", "section_key"]}
        searchPlaceholder={tc("actions.search")}
        filters={[
          {
            key: "status",
            label: t("marklists.status"),
            options: PIPELINE.map(({ status }) => ({
              label: t(`reports.submissions.statuses.${status}`),
              value: status,
            })),
          },
        ]}
        filterValues={filterValues}
        onFilterChange={(key, value) => setFilterValues((prev) => ({ ...prev, [key]: value }))}
        rowClassName={(row) => STATUS_ROW[row.status]}
        bulkActions={
          // Between "one teacher" and "everyone": nudge exactly the classes you
          // picked. Approved sheets and account-less teachers drop out first —
          // there is nothing to chase and nowhere to send it.
          canRemind
            ? [
                {
                  label: t("reports.submissions.remind"),
                  icon: BellRing,
                  onClick: async (rows: Row[]) => {
                    const targets = rows.filter(
                      (r) => r.status !== "approved" && r.teacher.has_account,
                    )
                    if (targets.length === 0) {
                      toast.info(t("reports.submissions.remindNone"))
                      return
                    }
                    await remind(targets.map((r) => r.subject_assignment_id))
                  },
                },
              ]
            : undefined
        }
        actions={[
          {
            label: t("marklists.open"),
            icon: ExternalLink,
            primary: true,
            onClick: (row) => router.push(`/marklists/${row.subject_assignment_id}`),
          },
          ...(canRemind
            ? [
                {
                  label: t("reports.submissions.remind"),
                  icon: BellRing,
                  hidden: (row: Row) => row.status === "approved" || !row.teacher.has_account,
                  disabled: (row: Row) => remindingId === row.subject_assignment_id,
                  onClick: async (row: Row) => {
                    setRemindingId(row.subject_assignment_id)
                    await remind([row.subject_assignment_id])
                    setRemindingId(null)
                  },
                },
              ]
            : []),
        ]}
        emptyMessage={t("reports.submissions.empty")}
        exportFilename="marklist-submissions"
      />

      {/* Mass-nudging every pending teacher deserves a pause. */}
      <AlertDialog open={bulkOpen} onOpenChange={setBulkOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("reports.submissions.remindAllTitle")}</AlertDialogTitle>
            <AlertDialogDescription>
              {t("reports.submissions.remindAllBody", { count: pendingRows.length })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={bulkBusy}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={bulkBusy}
              onClick={async (e) => {
                e.preventDefault()
                setBulkBusy(true)
                await remind(pendingRows.map((r) => r.subject_assignment_id))
                setBulkBusy(false)
                setBulkOpen(false)
              }}
            >
              {t("reports.submissions.remindAllConfirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
