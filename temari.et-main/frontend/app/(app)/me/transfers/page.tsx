"use client"

import { AlertTriangle, ArrowRight, ArrowLeftRight, Plus } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { useChildren } from "@/components/me/child-tabs"
import { AsyncCombobox, type AsyncComboboxOption } from "@/components/ui/async-combobox"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
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
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

const STATUS_TONE: Record<string, string> = {
  submitted: "border-warning/30 bg-warning/10 text-warning",
  accepted: "border-primary/30 bg-primary/10 text-primary",
  declined: "border-destructive/30 bg-destructive/10 text-destructive",
  withdrawn: "border-border bg-muted text-muted-foreground",
  requested: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
  rejected: "border-destructive/30 bg-destructive/10 text-destructive",
  cancelled: "border-border bg-muted text-muted-foreground",
}

interface TrackedApplication {
  id: number
  student_id: number
  student_name: string | null
  from_school: string | null
  from_branch: string | null
  to_school: string | null
  to_branch: string | null
  status: string
  reason: string
  decline_note: string | null
  request_status: string | null
  created_at: string | null
  decided_at: string | null
  mine: boolean
}

interface TrackedRequest {
  id: number
  student_id: number
  student_name: string | null
  from_school: string | null
  from_branch: string | null
  to_school: string | null
  to_branch: string | null
  status: string
  created_at: string | null
  decided_at: string | null
}

interface Destination {
  id: number
  name: string
  branches: { id: number; name: string; city: string | null }[]
}

/**
 * The family's transfer hub (relationship lane): live tracking of every
 * movement of their children — school-initiated or their own — plus the
 * online transfer application flow (NEMIS order: family → destination school
 * → current school).
 */
export default function MyTransfersPage() {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")
  const { user } = useAuth()

  const { children } = useChildren(user?.is_parent === true)
  const [applications, setApplications] = useState<TrackedApplication[] | null>(null)
  const [requests, setRequests] = useState<TrackedRequest[] | null>(null)
  const [requestOpen, setRequestOpen] = useState(false)

  const load = useCallback(() => {
    let cancelled = false
    apiFetch<{ data: { applications: TrackedApplication[]; requests: TrackedRequest[] } }>(
      "/me/transfers",
    )
      .then((res) => {
        if (cancelled) return
        setApplications(res.data.applications)
        setRequests(res.data.requests)
      })
      .catch(() => {
        if (cancelled) return
        setApplications([])
        setRequests([])
      })
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => load(), [load])

  async function withdraw(application: TrackedApplication) {
    try {
      await apiFetch(`/me/transfer-applications/${application.id}/withdraw`, { method: "POST" })
      toast.success(t("family.withdrawn"))
      load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  const loading = applications === null || requests === null
  const empty = !loading && applications!.length === 0 && requests!.length === 0

  function statusChip(status: string) {
    return (
      <Badge variant="outline" className={cn("rounded-full", STATUS_TONE[status] ?? "")}>
        {t(`family.statuses.${status}`)}
      </Badge>
    )
  }

  function routeLine(row: { from_school: string | null; to_school: string | null; to_branch: string | null }) {
    return (
      <p className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
        <span>{row.from_school}</span>
        <ArrowRight className="size-3" />
        <span>
          {row.to_school}
          {row.to_branch ? ` · ${row.to_branch}` : ""}
        </span>
      </p>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("family.title")}
        description={t("family.subtitle")}
        actions={
          <Button onClick={() => setRequestOpen(true)}>
            <Plus className="size-4" />
            {t("family.request")}
          </Button>
        }
      />

      <div className="page-gutter space-y-4">
        {loading ? (
          <>
            <Skeleton className="h-24 w-full rounded-2xl" />
            <Skeleton className="h-24 w-full rounded-2xl" />
          </>
        ) : empty ? (
          <EmptyState
            icon={ArrowLeftRight}
            title={t("family.empty")}
            description={t("family.emptyDesc")}
          />
        ) : (
          <>
            {applications!.map((application) => (
              <div key={`app-${application.id}`} className="rounded-2xl border p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-medium">{application.student_name}</p>
                  {/* Once accepted, the materialized request's status is the truth. */}
                  {statusChip(
                    application.status === "accepted" && application.request_status
                      ? application.request_status
                      : application.status,
                  )}
                </div>
                <div className="mt-1.5 space-y-1.5">
                  {routeLine(application)}
                  {/* Simple progress narrative, not jargon. */}
                  <p className="text-xs text-muted-foreground">
                    {application.status === "submitted" && t("family.progress.submitted")}
                    {application.status === "accepted" &&
                      (application.request_status === "requested"
                        ? t("family.progress.awaitingCurrent")
                        : application.request_status === "approved"
                          ? t("family.progress.approved")
                          : application.request_status === "rejected"
                            ? t("family.progress.rejected")
                            : "")}
                    {application.status === "declined" &&
                      `${t("family.progress.declined")}${application.decline_note ? ` — ${application.decline_note}` : ""}`}
                    {application.status === "withdrawn" && t("family.progress.withdrawn")}
                  </p>
                </div>
                {application.status === "submitted" && application.mine && (
                  <Button
                    variant="outline"
                    size="sm"
                    className="mt-3 text-destructive hover:text-destructive"
                    onClick={() => withdraw(application)}
                  >
                    {t("family.withdraw")}
                  </Button>
                )}
              </div>
            ))}

            {requests!.map((request) => (
              <div key={`req-${request.id}`} className="rounded-2xl border p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <p className="font-medium">{request.student_name}</p>
                  {statusChip(request.status)}
                </div>
                <div className="mt-1.5 space-y-1.5">
                  {routeLine(request)}
                  <p className="text-xs text-muted-foreground">
                    {t("family.schoolInitiated")}
                  </p>
                </div>
              </div>
            ))}
          </>
        )}
      </div>

      <RequestTransferSheet
        open={requestOpen}
        onOpenChange={setRequestOpen}
        childrenLinks={children ?? []}
        onCreated={() => {
          setRequestOpen(false)
          load()
        }}
      />
    </div>
  )
}

/** The online application form: child → destination school/branch → reason. */
function RequestTransferSheet({
  open,
  onOpenChange,
  childrenLinks,
  onCreated,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  childrenLinks: {
    student_id: number
    full_name: string
    unpaid_invoices: number | null
  }[]
  onCreated: () => void
}) {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")
  const { user } = useAuth()

  const [studentId, setStudentId] = useState("")
  const [school, setSchool] = useState<AsyncComboboxOption | null>(null)
  const [branchId, setBranchId] = useState("")
  const [reason, setReason] = useState("")
  const [ownStudentId, setOwnStudentId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)

  // A student account may request their own transfer (adult students,
  // Grade 11–12) — resolve the own student id once.
  useEffect(() => {
    if (!open || user?.is_student !== true) return
    apiFetch<{ data: { student_id: number } }>("/me/student")
      .then((res) => setOwnStudentId(res.data.student_id))
      .catch(() => {})
  }, [open, user?.is_student])

  useEffect(() => {
    if (!open) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on close
      setStudentId("")
      setSchool(null)
      setBranchId("")
      setReason("")
      return
    }
  }, [open])

  // One type-to-search picker over ACTIVE Temari schools — the branches ride
  // along in each option's meta so picking a school needs no second fetch.
  const searchDestinations = useCallback(async (query: string): Promise<AsyncComboboxOption[]> => {
    const res = await apiFetch<{ data: Destination[] }>(
      `/me/transfer-applications/destinations?q=${encodeURIComponent(query.trim())}`,
    )
    return res.data.map((destination) => ({
      value: String(destination.id),
      label: destination.name,
      description:
        destination.branches.length === 1
          ? [destination.branches[0].name, destination.branches[0].city].filter(Boolean).join(" — ")
          : t("family.branchCount", { count: destination.branches.length }),
      meta: destination,
    }))
  }, [t])

  const subjects = [
    ...childrenLinks.map((child) => ({
      id: child.student_id,
      label: child.full_name,
      unpaid: child.unpaid_invoices ?? 0,
    })),
    ...(ownStudentId !== null
      ? [{ id: ownStudentId, label: t("family.myself"), unpaid: 0 }]
      : []),
  ]

  const selectedSchool = (school?.meta ?? null) as Destination | null
  const selectedSubject = subjects.find((subject) => String(subject.id) === studentId)

  async function submit() {
    if (!studentId || !branchId || !reason.trim()) return
    setSubmitting(true)
    try {
      await apiFetch("/me/transfer-applications", {
        method: "POST",
        body: {
          student_id: Number(studentId),
          to_branch_id: Number(branchId),
          reason: reason.trim(),
        },
      })
      toast.success(t("family.submitted"))
      onCreated()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("family.request")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody>
          <div className="space-y-5">
            <div className="space-y-2">
              <Label>{t("family.student")}</Label>
              <Select value={studentId} onValueChange={setStudentId}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t("family.selectStudent")} />
                </SelectTrigger>
                <SelectContent>
                  {subjects.map((subject) => (
                    <SelectItem key={subject.id} value={String(subject.id)}>
                      {subject.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Money talks early: the current school reviews unpaid fees
                before approving — no surprises later. */}
            {(selectedSubject?.unpaid ?? 0) > 0 && (
              <p className="flex items-start gap-2 rounded-xl border border-warning/40 bg-warning/10 p-3 text-sm">
                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
                {t("family.unpaidWarning", { count: selectedSubject!.unpaid })}
              </p>
            )}

            <div className="space-y-2">
              <Label>{t("family.destination")}</Label>
              <AsyncCombobox
                value={school}
                onChange={(option) => {
                  setSchool(option)
                  const destination = (option?.meta ?? null) as Destination | null
                  setBranchId(
                    destination && destination.branches.length === 1
                      ? String(destination.branches[0].id)
                      : "",
                  )
                }}
                fetcher={searchDestinations}
                minChars={0}
                placeholder={t("family.selectSchool")}
                searchPlaceholder={t("family.searchSchools")}
                emptyText={t("family.noSchoolsFound")}
              />
              {selectedSchool && selectedSchool.branches.length > 1 && (
                <Select value={branchId} onValueChange={setBranchId}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("family.selectBranch")} />
                  </SelectTrigger>
                  <SelectContent>
                    {selectedSchool.branches.map((branch) => (
                      <SelectItem key={branch.id} value={String(branch.id)}>
                        {branch.name}
                        {branch.city ? ` — ${branch.city}` : ""}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </div>

            <div className="space-y-2">
              <Label>{t("form.reason")}</Label>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t("family.reasonPlaceholder")}
                rows={3}
                className={TEXTAREA_CLASS}
              />
            </div>

            <p className="text-xs text-muted-foreground">{t("family.howItWorks")}</p>
          </div>
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            className="h-11 flex-1"
            onClick={submit}
            loading={submitting} disabled={!studentId || !branchId || !reason.trim()}
          >
            {t("family.submit")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
