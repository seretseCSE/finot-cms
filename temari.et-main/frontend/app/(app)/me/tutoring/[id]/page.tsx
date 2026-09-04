"use client"

import { CalendarClock, Check, Flag, MessageCircle, Star, Video } from "lucide-react"
import { useParams, useRouter } from "next/navigation"
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
import { cn, formatETB } from "@/lib/utils"
import { fmtDateTime } from "@/lib/dates"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

interface CycleRow {
  id: number
  label: string
  status: string
  starts_on: string
  ends_on: string
  planned_hours: string
  gross_amount: string
  credit_applied: string
  amount_due: string
  confirmed_hours: string | null
  funded_at: string | null
  released_at: string | null
}

interface SessionRow {
  id: number
  cycle_id: number
  scheduled_at: string
  duration_hours: string
  topic: string | null
  status: string
  meeting_url: string | null
  dispute_reason: string | null
}

interface EngagementDetail {
  id: number
  status: string
  tutor: { slug: string | null; name: string | null; avatar_url: string | null; headline: string | null }
  learner_name: string | null
  subjects: { id: number; name: string }[]
  mode: string
  sessions_per_week: number
  hours_per_session: string
  hourly_rate: string
  conversation_id: number | null
  cycles: CycleRow[]
}

const STATUS_TONE: Record<string, string> = {
  awaiting_payment: "border-warning/30 bg-warning/10 text-warning",
  funded: "border-success/30 bg-success/10 text-success",
  released: "border-primary/30 bg-primary/10 text-primary",
  refunded: "border-border bg-muted text-muted-foreground",
  canceled: "border-border bg-muted text-muted-foreground",
  scheduled: "border-info/30 bg-info/10 text-info",
  logged: "border-warning/30 bg-warning/10 text-warning",
  confirmed: "border-success/30 bg-success/10 text-success",
  disputed: "border-destructive/30 bg-destructive/10 text-destructive",
  active: "border-success/30 bg-success/10 text-success",
  paused: "border-warning/30 bg-warning/10 text-warning",
  ended: "border-border bg-muted text-muted-foreground",
}

export default function FamilyEngagementPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()
  const router = useRouter()

  const [loading, setLoading] = useState(true)
  const [engagement, setEngagement] = useState<EngagementDetail | null>(null)
  const [sessions, setSessions] = useState<SessionRow[]>([])
  const [busy, setBusy] = useState<string | null>(null)

  // Dispute sheet.
  const [disputeSession, setDisputeSession] = useState<SessionRow | null>(null)
  const [disputeReason, setDisputeReason] = useState("")

  // Review sheet.
  const [reviewCycle, setReviewCycle] = useState<CycleRow | null>(null)
  const [rating, setRating] = useState(5)
  const [comment, setComment] = useState("")

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [detail, sessionsRes] = await Promise.all([
        apiFetch<{ data: EngagementDetail }>(`/me/tutoring/engagements/${params.id}`),
        apiFetch<{ data: SessionRow[] }>(`/me/tutoring/engagements/${params.id}/sessions`),
      ])
      setEngagement(detail.data)
      setSessions(sessionsRes.data)
    } catch {
      setEngagement(null)
    } finally {
      setLoading(false)
    }
  }, [params.id])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!cancelled) await load()
    })()
    return () => {
      cancelled = true
    }
  }, [load])

  async function confirmSession(session: SessionRow) {
    setBusy(`confirm-${session.id}`)
    try {
      await apiFetch(`/me/tutoring/sessions/${session.id}/confirm`, { method: "POST" })
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function fileDispute() {
    if (!disputeSession) return
    setBusy("dispute")
    try {
      await apiFetch(`/me/tutoring/sessions/${disputeSession.id}/dispute`, {
        method: "POST",
        body: JSON.stringify({ reason: disputeReason }),
      })
      toast.success(t("family.disputeSent"))
      setDisputeSession(null)
      setDisputeReason("")
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function submitReview() {
    if (!reviewCycle) return
    setBusy("review")
    try {
      await apiFetch(`/me/tutoring/cycles/${reviewCycle.id}/review`, {
        method: "POST",
        body: JSON.stringify({ rating, comment: comment || null }),
      })
      toast.success(t("family.reviewThanks"))
      setReviewCycle(null)
      setComment("")
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function openChat() {
    try {
      const res = await apiFetch<{ data: { conversation_id: number } }>(
        `/me/tutoring/engagements/${params.id}/thread`,
      )
      router.push(`/messages?conversation=${res.data.conversation_id}`)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("family.title")} backHref="/me/tutoring" />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
      </div>
    )
  }

  if (!engagement) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("family.title")} backHref="/me/tutoring" />
        <div className="page-gutter">
          <EmptyState icon={Flag} title={t("profile.notFound")} description="" />
        </div>
      </div>
    )
  }

  const loggedSessions = sessions.filter((s) => s.status === "logged")

  return (
    <div className="space-y-6">
      <PageHeader
        title={engagement.tutor.name ?? t("family.title")}
        description={engagement.subjects.map((s) => s.name).join(", ") || engagement.tutor.headline || ""}
        backHref="/me/tutoring"
        actions={
          <Button variant="outline" onClick={openChat}>
            <MessageCircle data-slot="icon" />
            {t("family.chatWithTutor")}
          </Button>
        }
      />

      <section className="page-gutter">
        <div className="flex items-center gap-4 rounded-2xl border bg-card p-4 shadow-xs">
          <PersonAvatar className="size-12" photoUrl={engagement.tutor.avatar_url} name={engagement.tutor.name ?? "?"} />
          <div className="min-w-0 flex-1">
            <div className="flex flex-wrap items-center gap-2">
              <p className="font-medium">{engagement.learner_name}</p>
              <Badge variant="outline" className={STATUS_TONE[engagement.status]}>
                {t(`status.${engagement.status}`)}
              </Badge>
            </div>
            <p className="text-sm text-muted-foreground">
              {t("workspace.monthlyPlan", {
                sessions: engagement.sessions_per_week,
                hours: engagement.hours_per_session,
              })}{" "}
              · {formatETB(engagement.hourly_rate)}/hr · {t(`mode.${engagement.mode}`)}
            </p>
          </div>
        </div>
      </section>

      {loggedSessions.length > 0 && (
        <section className="page-gutter space-y-3">
          <div>
            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("family.sessionsTitle")}
            </h2>
            <p className="text-sm text-muted-foreground">{t("family.confirmHint")}</p>
          </div>
          {loggedSessions.map((session) => (
            <div
              key={session.id}
              className="flex flex-col gap-3 rounded-2xl border border-warning/30 bg-warning/5 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <p className="font-medium">
                  {fmtDateTime(session.scheduled_at)}{" "}
                  · {session.duration_hours}h
                </p>
                {session.topic && <p className="text-sm text-muted-foreground">{session.topic}</p>}
              </div>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  loading={busy === `confirm-${session.id}`}
                  onClick={() => confirmSession(session)}
                >
                  <Check data-slot="icon" />
                  {t("family.confirm")}
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={busy !== null}
                  onClick={() => setDisputeSession(session)}
                >
                  <Flag data-slot="icon" />
                  {t("family.dispute")}
                </Button>
              </div>
            </div>
          ))}
        </section>
      )}

      <section className="page-gutter space-y-3">
        <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("workspace.months")}
        </h2>
        <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
          {engagement.cycles.map((cycle, index) => (
            <div key={cycle.id} className={cn("flex items-center justify-between gap-3 p-4", index > 0 && "border-t")}>
              <div className="min-w-0">
                <p className="font-medium">{cycle.label}</p>
                <p className="text-sm text-muted-foreground">
                  {cycle.confirmed_hours !== null
                    ? `${cycle.confirmed_hours}h · ${formatETB(cycle.gross_amount)}`
                    : `${cycle.planned_hours}h · ${formatETB(cycle.amount_due)}`}
                </p>
              </div>
              <div className="flex shrink-0 items-center gap-2">
                <Badge variant="outline" className={STATUS_TONE[cycle.status]}>
                  {t(`status.${cycle.status}`)}
                </Badge>
                {cycle.status === "released" && (
                  <Button size="sm" variant="outline" onClick={() => setReviewCycle(cycle)}>
                    <Star data-slot="icon" />
                    {t("family.rateTutor")}
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      </section>

      <section className="page-gutter space-y-3">
        <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("workspace.sessions")}
        </h2>
        {sessions.length === 0 ? (
          <EmptyState icon={CalendarClock} title={t("workspace.emptySessions")} description={t("family.dueDesc")} />
        ) : (
          <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            {sessions.map((session, index) => (
              <div
                key={session.id}
                className={cn("flex items-center justify-between gap-3 p-4", index > 0 && "border-t")}
              >
                <div className="min-w-0">
                  <p className="text-sm font-medium">
                    {fmtDateTime(session.scheduled_at)}{" "}
                    · {session.duration_hours}h
                  </p>
                  {session.topic && <p className="truncate text-sm text-muted-foreground">{session.topic}</p>}
                </div>
                <div className="flex shrink-0 items-center gap-2">
                  {session.meeting_url && session.status === "scheduled" && (
                    <Button asChild size="sm" variant="outline">
                      <a href={session.meeting_url} target="_blank" rel="noreferrer">
                        <Video data-slot="icon" />
                        {t("workspace.joinMeeting")}
                      </a>
                    </Button>
                  )}
                  <Badge variant="outline" className={STATUS_TONE[session.status]}>
                    {t(`status.${session.status}`)}
                  </Badge>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Dispute sheet */}
      <ResponsiveSheet
        open={disputeSession !== null}
        onOpenChange={(open) => !open && busy !== "dispute" && setDisputeSession(null)}
      >
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("family.dispute")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-3">
            <Label htmlFor="dispute-reason">{t("family.disputeReason")}</Label>
            <textarea
              id="dispute-reason"
              rows={4}
              className={TEXTAREA_CLASS}
              value={disputeReason}
              onChange={(event) => setDisputeReason(event.target.value)}
            />
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              disabled={busy === "dispute"}
              onClick={() => setDisputeSession(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              loading={busy === "dispute"}
              disabled={disputeReason.trim().length < 5}
              onClick={fileDispute}
            >
              {t("family.dispute")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>

      {/* Review sheet */}
      <ResponsiveSheet
        open={reviewCycle !== null}
        onOpenChange={(open) => !open && busy !== "review" && setReviewCycle(null)}
      >
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("family.rateTutor")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <div className="flex justify-center gap-2">
              {[1, 2, 3, 4, 5].map((value) => (
                <button
                  key={value}
                  type="button"
                  aria-label={`${value}/5`}
                  onClick={() => setRating(value)}
                  className="touch-target pressable"
                >
                  <Star
                    className={cn(
                      "size-8 transition-colors",
                      value <= rating ? "fill-warning text-warning" : "text-muted-foreground/40",
                    )}
                    strokeWidth={1.75}
                  />
                </button>
              ))}
            </div>
            <div className="space-y-2">
              <Label htmlFor="review-comment">{t("family.reviewComment")}</Label>
              <textarea
                id="review-comment"
                rows={3}
                className={TEXTAREA_CLASS}
                value={comment}
                onChange={(event) => setComment(event.target.value)}
              />
            </div>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              disabled={busy === "review"}
              onClick={() => setReviewCycle(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" loading={busy === "review"} onClick={submitReview}>
              {t("family.submitReview")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
