"use client"

import { CalendarPlus, CheckCheck, Flag, MessageCircle, Video, X } from "lucide-react"
import { useParams, useRouter } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import { TimePicker } from "@/components/ui/time-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
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

interface CycleRow {
  id: number
  label: string
  status: string
  starts_on: string
  ends_on: string
  planned_hours: string
  gross_amount: string
  amount_due: string
  confirmed_hours: string | null
  released_amount: string | null
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

interface Detail {
  id: number
  status: string
  payer_name: string | null
  learner_name: string | null
  subjects: { id: number; name: string }[]
  mode: string
  sessions_per_week: number
  hours_per_session: string
  hourly_rate: string
  commission_percent: string | null
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

export default function TutorEngagementDetailPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()
  const router = useRouter()

  const [loading, setLoading] = useState(true)
  const [detail, setDetail] = useState<Detail | null>(null)
  const [sessions, setSessions] = useState<SessionRow[]>([])
  const [busy, setBusy] = useState<string | null>(null)

  // Schedule sheet.
  const [scheduleOpen, setScheduleOpen] = useState(false)
  const [whenDate, setWhenDate] = useState("")
  const [whenTime, setWhenTime] = useState("16:00")
  const [duration, setDuration] = useState("")
  const [topic, setTopic] = useState("")

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [detailRes, sessionsRes] = await Promise.all([
        apiFetch<{ data: Detail }>(`/tutoring/engagements/${params.id}`),
        apiFetch<{ data: SessionRow[] }>(`/tutoring/engagements/${params.id}/sessions`),
      ])
      setDetail(detailRes.data)
      setSessions(sessionsRes.data)
      setDuration(detailRes.data.hours_per_session)
    } catch {
      setDetail(null)
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

  async function scheduleSession() {
    setBusy("schedule")
    try {
      await apiFetch(`/tutoring/engagements/${params.id}/sessions`, {
        method: "POST",
        body: JSON.stringify({
          scheduled_at: `${whenDate} ${whenTime}`,
          duration_hours: Number(duration),
          topic: topic || null,
        }),
      })
      setScheduleOpen(false)
      setTopic("")
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function act(path: string, key: string) {
    setBusy(key)
    try {
      await apiFetch(path, { method: "POST" })
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setBusy(null)
    }
  }

  async function openChat() {
    try {
      const res = await apiFetch<{ data: { conversation_id: number } }>(
        `/tutoring/engagements/${params.id}/thread`,
      )
      router.push(`/messages?conversation=${res.data.conversation_id}`)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    }
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("workspace.engagements")} backHref="/tutoring/engagements" />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-64 rounded-2xl" />
        </div>
      </div>
    )
  }

  if (!detail) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("workspace.engagements")} backHref="/tutoring/engagements" />
        <div className="page-gutter">
          <EmptyState icon={Flag} title={t("profile.notFound")} description="" />
        </div>
      </div>
    )
  }

  const fundedCycle = detail.cycles.find((c) => c.status === "funded")

  return (
    <div className="space-y-6">
      <PageHeader
        title={detail.learner_name ?? ""}
        description={`${detail.subjects.map((s) => s.name).join(", ")} · ${detail.payer_name ?? ""}`}
        backHref="/tutoring/engagements"
        actions={
          <div className="flex gap-2">
            <Button variant="outline" onClick={openChat}>
              <MessageCircle data-slot="icon" />
              {t("family.chatWithTutor")}
            </Button>
            <Button onClick={() => setScheduleOpen(true)} disabled={!fundedCycle}>
              <CalendarPlus data-slot="icon" />
              {t("workspace.scheduleSession")}
            </Button>
          </div>
        }
      />

      {!fundedCycle && detail.status === "active" && (
        <div className="page-gutter">
          <div className="rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
            {t("workspace.monthLocked")}
          </div>
        </div>
      )}

      <section className="page-gutter grid gap-3 sm:grid-cols-3">
        <div className="rounded-2xl border bg-card p-4 shadow-xs">
          <p className="text-[13px] text-muted-foreground">{t("workspace.rate")}</p>
          <p className="font-mono text-lg font-semibold tabular-nums">{formatETB(detail.hourly_rate)}/hr</p>
        </div>
        <div className="rounded-2xl border bg-card p-4 shadow-xs">
          <p className="text-[13px] text-muted-foreground">{t("workspace.schedule")}</p>
          <p className="text-lg font-semibold">
            {t("workspace.monthlyPlan", {
              sessions: detail.sessions_per_week,
              hours: detail.hours_per_session,
            })}
          </p>
        </div>
        <div className="rounded-2xl border bg-card p-4 shadow-xs">
          <p className="text-[13px] text-muted-foreground">{t("workspace.commission")}</p>
          <p className="font-mono text-lg font-semibold tabular-nums">{detail.commission_percent ?? "—"}%</p>
        </div>
      </section>

      <section className="page-gutter space-y-3">
        <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("workspace.sessions")}
        </h2>
        {sessions.length === 0 ? (
          <EmptyState
            icon={CalendarPlus}
            title={t("workspace.emptySessions")}
            description={t("workspace.emptySessionsDesc")}
          />
        ) : (
          <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            {sessions.map((session, index) => (
              <div
                key={session.id}
                className={cn("flex flex-wrap items-center justify-between gap-3 p-4", index > 0 && "border-t")}
              >
                <div className="min-w-0">
                  <p className="text-sm font-medium">
                    {fmtDateTime(session.scheduled_at)}{" "}
                    · {session.duration_hours}h
                  </p>
                  {session.topic && <p className="truncate text-sm text-muted-foreground">{session.topic}</p>}
                  {session.dispute_reason && (
                    <p className="text-xs text-destructive">{session.dispute_reason}</p>
                  )}
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
                  {session.status === "scheduled" && (
                    <>
                      <Button
                        size="sm"
                        loading={busy === `log-${session.id}`}
                        onClick={() => act(`/tutoring/sessions/${session.id}/log`, `log-${session.id}`)}
                      >
                        <CheckCheck data-slot="icon" />
                        {t("workspace.logSession")}
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        aria-label={t("workspace.cancelSession")}
                        title={t("workspace.cancelSession")}
                        disabled={busy !== null}
                        onClick={() => act(`/tutoring/sessions/${session.id}/cancel`, `cancel-${session.id}`)}
                      >
                        <X className="size-4" />
                      </Button>
                    </>
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

      <section className="page-gutter space-y-3 pb-8">
        <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("workspace.months")}
        </h2>
        <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
          {detail.cycles.map((cycle, index) => (
            <div key={cycle.id} className={cn("flex items-center justify-between gap-3 p-4", index > 0 && "border-t")}>
              <div>
                <p className="font-medium">{cycle.label}</p>
                <p className="text-sm text-muted-foreground">
                  {cycle.status === "released" && cycle.released_amount !== null
                    ? `${cycle.confirmed_hours}h → ${formatETB(cycle.released_amount)}`
                    : `${cycle.planned_hours}h · ${formatETB(cycle.gross_amount)}`}
                </p>
              </div>
              <Badge variant="outline" className={STATUS_TONE[cycle.status]}>
                {t(`status.${cycle.status}`)}
              </Badge>
            </div>
          ))}
        </div>
      </section>

      {/* Schedule sheet */}
      <ResponsiveSheet open={scheduleOpen} onOpenChange={(open) => !open && busy !== "schedule" && setScheduleOpen(false)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("workspace.scheduleSession")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <div className="space-y-2">
              <Label>{t("workspace.sessionDate")}</Label>
              <div className="grid grid-cols-2 gap-3">
                <DatePicker value={whenDate} onChange={setWhenDate} />
                <TimePicker value={whenTime} onChange={setWhenTime} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label htmlFor="session-duration">{t("workspace.duration")}</Label>
                <Input
                  id="session-duration"
                  type="number"
                  min={0.5}
                  max={4}
                  step={0.5}
                  className="no-spinner"
                  value={duration}
                  onChange={(event) => setDuration(event.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="session-topic">{t("workspace.topic")}</Label>
                <Input id="session-topic" value={topic} onChange={(event) => setTopic(event.target.value)} />
              </div>
            </div>
            <p className="text-xs text-muted-foreground">{t("workspace.logHint")}</p>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              disabled={busy === "schedule"}
              onClick={() => setScheduleOpen(false)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              loading={busy === "schedule"}
              disabled={!whenDate || !whenTime || !duration}
              onClick={scheduleSession}
            >
              {t("workspace.scheduleSession")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
