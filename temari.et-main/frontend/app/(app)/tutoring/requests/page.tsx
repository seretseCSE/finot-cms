"use client"

import { Check, Inbox, X } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { PersonAvatar } from "@/components/ui/person-avatar"
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
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

interface InboxRequest {
  id: number
  status: string
  requester_name: string | null
  requester_avatar_url: string | null
  student_name: string | null
  subjects: string[]
  grade_label: string | null
  mode: string
  sessions_per_week: number
  hours_per_session: string
  message: string | null
  created_at: string
}

const STATUS_TONE: Record<string, string> = {
  pending: "border-warning/30 bg-warning/10 text-warning",
  accepted: "border-success/30 bg-success/10 text-success",
  declined: "border-destructive/30 bg-destructive/10 text-destructive",
  withdrawn: "border-border bg-muted text-muted-foreground",
}

export default function TutorRequestsPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")

  const [loading, setLoading] = useState(true)
  const [requests, setRequests] = useState<InboxRequest[]>([])
  const [accepting, setAccepting] = useState<number | null>(null)
  const [declineTarget, setDeclineTarget] = useState<InboxRequest | null>(null)
  const [declineNote, setDeclineNote] = useState("")
  const [declining, setDeclining] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await apiFetch<{ data: InboxRequest[] }>("/tutoring/requests")
      setRequests(res.data)
    } catch {
      // empty state handles it
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

  async function accept(request: InboxRequest) {
    setAccepting(request.id)
    try {
      await apiFetch(`/tutoring/requests/${request.id}/accept`, { method: "POST" })
      toast.success(t("request.accepted"))
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setAccepting(null)
    }
  }

  async function decline() {
    if (!declineTarget) return
    setDeclining(true)
    try {
      await apiFetch(`/tutoring/requests/${declineTarget.id}/decline`, {
        method: "POST",
        body: JSON.stringify({ note: declineNote || null }),
      })
      setDeclineTarget(null)
      setDeclineNote("")
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setDeclining(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("request.inbox")} description={t("request.inboxDesc")} backHref="/tutoring" />

      <div className="page-gutter space-y-3">
        {loading ? (
          <>
            <Skeleton className="h-32 rounded-2xl" />
            <Skeleton className="h-32 rounded-2xl" />
          </>
        ) : requests.length === 0 ? (
          <EmptyState icon={Inbox} title={t("request.emptyInbox")} description={t("request.emptyInboxDesc")} />
        ) : (
          requests.map((request) => (
            <div key={request.id} className="space-y-3 rounded-2xl border bg-card p-4 shadow-xs">
              <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                  <PersonAvatar className="size-10" photoUrl={request.requester_avatar_url} name={request.requester_name ?? "?"} />
                  <div className="min-w-0">
                    <p className="truncate font-medium">{request.requester_name}</p>
                    <p className="truncate text-sm text-muted-foreground">
                      {request.student_name ?? t("request.myself")}
                      {request.grade_label ? ` · ${request.grade_label}` : ""}
                    </p>
                  </div>
                </div>
                <Badge variant="outline" className={cn("shrink-0", STATUS_TONE[request.status])}>
                  {t(`status.${request.status}`)}
                </Badge>
              </div>

              <div className="flex flex-wrap gap-1.5">
                {request.subjects.map((subject) => (
                  <Badge key={subject} variant="secondary">
                    {subject}
                  </Badge>
                ))}
                <Badge variant="outline">{t(`mode.${request.mode}`)}</Badge>
                <Badge variant="outline">
                  {t("workspace.monthlyPlan", {
                    sessions: request.sessions_per_week,
                    hours: request.hours_per_session,
                  })}
                </Badge>
              </div>

              {request.message && (
                <p className="rounded-xl bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                  {request.message}
                </p>
              )}

              {request.status === "pending" && (
                <div className="flex gap-2">
                  <Button
                    className="flex-1"
                    loading={accepting === request.id}
                    disabled={accepting !== null}
                    onClick={() => accept(request)}
                  >
                    <Check data-slot="icon" />
                    {t("request.accept")}
                  </Button>
                  <Button
                    variant="outline"
                    className="flex-1"
                    disabled={accepting !== null}
                    onClick={() => setDeclineTarget(request)}
                  >
                    <X data-slot="icon" />
                    {t("request.declineAction")}
                  </Button>
                </div>
              )}
            </div>
          ))
        )}
      </div>

      <ResponsiveSheet
        open={declineTarget !== null}
        onOpenChange={(open) => !open && !declining && setDeclineTarget(null)}
      >
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("request.declineAction")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-2">
            <Label htmlFor="decline-note">{t("request.declineNote")}</Label>
            <textarea
              id="decline-note"
              rows={3}
              className={TEXTAREA_CLASS}
              value={declineNote}
              onChange={(event) => setDeclineNote(event.target.value)}
            />
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              disabled={declining}
              onClick={() => setDeclineTarget(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" loading={declining} onClick={decline}>
              {t("request.declineAction")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
