"use client"

import { BadgeCheck, Ban, Eye, FileText, RotateCcw, X } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { PersonAvatar } from "@/components/ui/person-avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
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
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn, formatETB } from "@/lib/utils"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

interface TutorRow {
  id: number
  name: string | null
  phone: string | null
  headline: string | null
  status: string
  city: string | null
  mode: string
  hourly_rate: string | null
  rating_avg: string | null
  rating_count: number
  hours_taught: string
  wallet_balance: string
  subjects: string[]
  submitted_at: string | null
  [key: string]: unknown
}

interface TutorDetail extends TutorRow {
  email: string | null
  avatar_url: string | null
  bio: string | null
  fayda_id: string | null
  region: string | null
  sub_city: string | null
  languages: string[]
  education_level: string | null
  experience_years: number | null
  decline_reason: string | null
  suspend_reason: string | null
  attachments: { id: number; name: string; url: string | null; imported: boolean }[]
  subjects: { subject_id: number; name: string | null }[] & string[]
}

const STATUS_TONE: Record<string, string> = {
  draft: "border-border bg-muted text-muted-foreground",
  pending: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
  declined: "border-destructive/30 bg-destructive/10 text-destructive",
  suspended: "border-destructive/30 bg-destructive/10 text-destructive",
}

export default function MarketplaceTutorsPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")

  const [loading, setLoading] = useState(true)
  const [rows, setRows] = useState<TutorRow[]>([])
  const [detail, setDetail] = useState<TutorDetail | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [busy, setBusy] = useState<string | null>(null)
  const [reason, setReason] = useState("")
  const [reasonAction, setReasonAction] = useState<"decline" | "suspend" | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiFetch<{ data: TutorRow[] }>("/marketplace/tutors?per_page=100")
      setRows(res.data)
    } catch {
      // table shows empty
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!cancelled) await load()
    })()
    return () => {
      cancelled = true
    }
  }, [load])

  async function openDetail(row: TutorRow) {
    setDetailLoading(true)
    try {
      const res = await apiFetch<{ data: TutorDetail }>(`/marketplace/tutors/${row.id}`)
      setDetail(res.data)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setDetailLoading(false)
    }
  }

  async function decide(action: string, body?: Record<string, unknown>) {
    if (!detail) return
    setBusy(action)
    try {
      await apiFetch(`/marketplace/tutors/${detail.id}/${action}`, {
        method: "POST",
        body: body ? JSON.stringify(body) : undefined,
      })
      setReasonAction(null)
      setReason("")
      setDetail(null)
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  const columns: DataTableColumn<TutorRow>[] = [
    {
      key: "name",
      label: t("admin.register"),
      primary: true,
      render: (row) => (
        <div className="flex items-center gap-2.5">
          <PersonAvatar className="size-8" name={row.name ?? "?"} />
          <div className="min-w-0">
            <p className="truncate font-medium">{row.name}</p>
            <p className="truncate text-xs text-muted-foreground">{row.headline}</p>
          </div>
        </div>
      ),
      sortValue: (row) => row.name ?? "",
    },
    {
      key: "phone",
      label: tc("fields.phone"),
      sortable: false,
      render: (row) => <ContactActionCell value={row.phone} kind="phone" />,
    },
    {
      key: "status",
      label: tc("columns.status"),
      render: (row) => (
        <Badge variant="outline" className={STATUS_TONE[row.status]}>
          {t(`status.${row.status}`)}
        </Badge>
      ),
      sortValue: (row) => row.status,
    },
    {
      key: "hourly_rate",
      label: t("profile.hourlyRate"),
      render: (row) => (row.hourly_rate ? formatETB(row.hourly_rate) : "—"),
      sortValue: (row) => Number(row.hourly_rate ?? 0),
    },
    {
      key: "rating_avg",
      label: t("workspace.rating"),
      render: (row) =>
        row.rating_avg ? `${Number(row.rating_avg).toFixed(1)} (${row.rating_count})` : "—",
      sortValue: (row) => Number(row.rating_avg ?? 0),
    },
    {
      key: "wallet_balance",
      label: t("workspace.wallet"),
      render: (row) => formatETB(row.wallet_balance),
      sortValue: (row) => Number(row.wallet_balance),
    },
    { key: "city", label: t("dir.city"), sortValue: (row) => row.city ?? "" },
  ]

  return (
    <div className="space-y-6">
      <PageHeader title={t("admin.tutorsTitle")} description={t("admin.tutorsDesc")} />

      {loading ? (
        <div className="page-gutter">
          <Skeleton className="h-96 rounded-2xl" />
        </div>
      ) : (
        <DataTable
          data={rows}
          columns={columns}
          searchKeys={["name", "phone", "headline", "city"]}
          filters={[
            {
              key: "status",
              label: tc("columns.status"),
              options: ["pending", "approved", "declined", "suspended", "draft"].map((status) => ({
                value: status,
                label: t(`status.${status}`),
              })),
            },
          ]}
          actions={[
            {
              label: t("admin.application"),
              icon: Eye,
              primary: true,
              onClick: (row) => void openDetail(row),
            },
          ]}
        />
      )}

      {/* Application detail sheet */}
      <ResponsiveSheet
        open={detail !== null || detailLoading}
        onOpenChange={(open) => {
          if (!open && busy === null) {
            setDetail(null)
            setReasonAction(null)
            setReason("")
          }
        }}
      >
        <ResponsiveSheetContent className="sm:max-w-xl data-[side=right]:sm:max-w-xl">
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("admin.application")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            {detailLoading || !detail ? (
              <Skeleton className="h-72 rounded-2xl" />
            ) : (
              <>
                <div className="flex items-center gap-3">
                  <PersonAvatar className="size-14" photoUrl={detail.avatar_url} name={detail.name ?? "?"} />
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="font-medium">{detail.name}</p>
                      <Badge variant="outline" className={STATUS_TONE[detail.status]}>
                        {t(`status.${detail.status}`)}
                      </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">{detail.headline}</p>
                    <p className="text-xs text-muted-foreground">
                      {[detail.city, detail.sub_city].filter(Boolean).join(", ")} · {t(`mode.${detail.mode}`)} ·{" "}
                      {detail.hourly_rate ? `${formatETB(detail.hourly_rate)}/hr` : "—"}
                    </p>
                  </div>
                </div>

                {detail.bio && (
                  <p className="rounded-xl bg-muted/40 p-3 text-sm whitespace-pre-line">{detail.bio}</p>
                )}

                <div className="flex flex-wrap gap-1.5">
                  {(detail.subjects as { subject_id: number; name: string | null }[]).map((subject) => (
                    <Badge key={subject.subject_id} variant="secondary">
                      {subject.name}
                    </Badge>
                  ))}
                </div>

                <div className="space-y-1 rounded-xl border border-warning/30 bg-warning/5 p-3">
                  <p className="text-xs font-semibold tracking-wide text-warning uppercase">
                    {t("admin.fayda")}
                  </p>
                  <p className="font-mono text-lg tabular-nums">{detail.fayda_id ?? "—"}</p>
                  <p className="text-xs text-muted-foreground">{t("admin.faydaWarning")}</p>
                </div>

                <div className="space-y-2">
                  <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("admin.documents")}
                  </p>
                  {detail.attachments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">—</p>
                  ) : (
                    detail.attachments.map((attachment) => (
                      <a
                        key={attachment.id}
                        href={attachment.url ?? "#"}
                        target="_blank"
                        rel="noreferrer"
                        className="flex items-center gap-2 rounded-xl border bg-muted/20 px-3 py-2.5 text-sm font-medium hover:bg-accent/50"
                      >
                        <FileText className="size-4 shrink-0 text-muted-foreground" strokeWidth={1.75} />
                        <span className="truncate">{attachment.name}</span>
                        {attachment.imported && (
                          <Badge variant="outline" className="ml-auto shrink-0 text-xs">
                            {t("apply.importFromEmployee")}
                          </Badge>
                        )}
                      </a>
                    ))
                  )}
                </div>

                {reasonAction !== null && (
                  <div className="space-y-2 rounded-xl border border-destructive/30 bg-destructive/5 p-3">
                    <Label htmlFor="decision-reason">
                      {reasonAction === "decline" ? t("admin.declineReason") : t("admin.suspendReason")}
                    </Label>
                    <textarea
                      id="decision-reason"
                      rows={3}
                      className={TEXTAREA_CLASS}
                      value={reason}
                      onChange={(event) => setReason(event.target.value)}
                    />
                  </div>
                )}
              </>
            )}
          </ResponsiveSheetBody>
          {detail && (
            <ResponsiveSheetFooter>
              {reasonAction !== null ? (
                <>
                  <Button
                    variant="outline"
                    className="h-11 flex-1"
                    disabled={busy !== null}
                    onClick={() => setReasonAction(null)}
                  >
                    {tc("actions.cancel")}
                  </Button>
                  <Button
                    variant="destructive"
                    className="h-11 flex-1"
                    loading={busy === reasonAction}
                    disabled={reason.trim().length < 5}
                    onClick={() => void decide(reasonAction, { reason })}
                  >
                    {reasonAction === "decline" ? t("admin.decline") : t("admin.suspend")}
                  </Button>
                </>
              ) : detail.status === "pending" ? (
                <>
                  <Button
                    variant="outline"
                    className="h-11 flex-1"
                    disabled={busy !== null}
                    onClick={() => setReasonAction("decline")}
                  >
                    <X data-slot="icon" />
                    {t("admin.decline")}
                  </Button>
                  <Button className="h-11 flex-1" loading={busy === "approve"} onClick={() => void decide("approve")}>
                    <BadgeCheck data-slot="icon" />
                    {t("admin.approve")}
                  </Button>
                </>
              ) : detail.status === "approved" ? (
                <Button
                  variant="destructive"
                  className="h-11 flex-1"
                  disabled={busy !== null}
                  onClick={() => setReasonAction("suspend")}
                >
                  <Ban data-slot="icon" />
                  {t("admin.suspend")}
                </Button>
              ) : detail.status === "suspended" ? (
                <Button
                  className="h-11 flex-1"
                  loading={busy === "reinstate"}
                  onClick={() => void decide("reinstate")}
                >
                  <RotateCcw data-slot="icon" />
                  {t("admin.reinstate")}
                </Button>
              ) : null}
            </ResponsiveSheetFooter>
          )}
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
