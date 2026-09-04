"use client"

import { BookOpen, ExternalLink, GraduationCap, Receipt, Search } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { PersonAvatar } from "@/components/ui/person-avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
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

interface FamilyCycle {
  id: number
  engagement_id: number
  label: string
  status: string
  amount_due: string
  gross_amount: string
  credit_applied: string
  planned_hours: string
  starts_on: string
  ends_on: string
  funded_at: string | null
  tutor_name: string | null
  tutor_avatar_url: string | null
  learner_name: string | null
}

interface GatewayOption {
  code: string
  label: string
}

interface FamilyEngagement {
  id: number
  status: string
  tutor: { slug: string | null; name: string | null; avatar_url: string | null; headline: string | null }
  learner_name: string | null
  subjects: { id: number; name: string }[]
  sessions_per_week: number
  hours_per_session: string
  hourly_rate: string
  mode: string
}

interface FamilyRequest {
  id: number
  status: string
  tutor: { slug: string | null; name: string | null; avatar_url: string | null; headline: string | null }
  student_name: string | null
  response_note: string | null
  created_at: string
}

const STATUS_TONE: Record<string, string> = {
  awaiting_payment: "border-warning/30 bg-warning/10 text-warning",
  funded: "border-success/30 bg-success/10 text-success",
  released: "border-primary/30 bg-primary/10 text-primary",
  refunded: "border-border bg-muted text-muted-foreground",
  canceled: "border-border bg-muted text-muted-foreground",
  active: "border-success/30 bg-success/10 text-success",
  paused: "border-warning/30 bg-warning/10 text-warning",
  ended: "border-border bg-muted text-muted-foreground",
  pending: "border-warning/30 bg-warning/10 text-warning",
  accepted: "border-success/30 bg-success/10 text-success",
  declined: "border-destructive/30 bg-destructive/10 text-destructive",
  withdrawn: "border-border bg-muted text-muted-foreground",
}

export default function MyTutoringPage() {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")
  const router = useRouter()

  const [loading, setLoading] = useState(true)
  const [cycles, setCycles] = useState<FamilyCycle[]>([])
  const [gateways, setGateways] = useState<GatewayOption[]>([])
  const [engagements, setEngagements] = useState<FamilyEngagement[]>([])
  const [requests, setRequests] = useState<FamilyRequest[]>([])

  // Pay sheet state.
  const [payCycle, setPayCycle] = useState<FamilyCycle | null>(null)
  const [gateway, setGateway] = useState<string>("")
  const [paying, setPaying] = useState(false)
  const [withdrawing, setWithdrawing] = useState<number | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const [cyclesRes, engagementsRes, requestsRes] = await Promise.all([
        apiFetch<{ data: { cycles: FamilyCycle[]; gateways: GatewayOption[] } }>("/me/tutoring/cycles"),
        apiFetch<{ data: FamilyEngagement[] }>("/me/tutoring/engagements"),
        apiFetch<{ data: FamilyRequest[] }>("/me/tutoring/requests"),
      ])
      setCycles(cyclesRes.data.cycles)
      setGateways(cyclesRes.data.gateways)
      setEngagements(engagementsRes.data)
      setRequests(requestsRes.data)
    } catch {
      toast.error(t("family.title"))
    } finally {
      setLoading(false)
    }
  }, [t])

  useEffect(() => {
    let cancelled = false
    void (async () => {
      if (!cancelled) await load()
    })()
    return () => {
      cancelled = true
    }
  }, [load])

  async function startPayment() {
    if (!payCycle || !gateway) return
    setPaying(true)
    try {
      const res = await apiFetch<{ data: { checkout_url: string } }>(
        `/me/tutoring/cycles/${payCycle.id}/pay`,
        { method: "POST", body: JSON.stringify({ gateway }) },
      )
      window.location.href = res.data.checkout_url
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("pay.failed"))
      setPaying(false)
    }
  }

  async function withdrawRequest(id: number) {
    setWithdrawing(id)
    try {
      await apiFetch(`/me/tutoring/requests/${id}/withdraw`, { method: "POST" })
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setWithdrawing(null)
    }
  }

  const due = cycles.filter((c) => c.status === "awaiting_payment")
  const history = cycles.filter((c) => c.status !== "awaiting_payment")
  const pendingRequests = requests.filter((r) => r.status === "pending")

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("family.title")}
        description={t("family.subtitle")}
        actions={
          <Button asChild>
            <a href="/tutors" target="_blank" rel="noreferrer">
              <Search data-slot="icon" />
              {t("family.findTutor")}
            </a>
          </Button>
        }
      />

      {loading ? (
        <div className="page-gutter space-y-3">
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-28 rounded-2xl" />
          <Skeleton className="h-40 rounded-2xl" />
        </div>
      ) : cycles.length === 0 && engagements.length === 0 && requests.length === 0 ? (
        <div className="page-gutter">
          <EmptyState
            icon={BookOpen}
            title={t("family.empty")}
            description={t("family.emptyDesc")}
            action={
              <Button asChild>
                <a href="/tutors" target="_blank" rel="noreferrer">
                  {t("family.findTutor")}
                </a>
              </Button>
            }
          />
        </div>
      ) : (
        <>
          {due.length > 0 && (
            <section className="page-gutter space-y-3">
              <div>
                <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("family.due")}
                </h2>
                <p className="text-sm text-muted-foreground">{t("family.dueDesc")}</p>
              </div>
              {due.map((cycle) => (
                <div
                  key={cycle.id}
                  className="flex flex-col gap-3 rounded-2xl border bg-card p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="flex min-w-0 items-center gap-3">
                    <PersonAvatar className="size-10" photoUrl={cycle.tutor_avatar_url} name={cycle.tutor_name ?? "?"} />
                    <div className="min-w-0">
                      <p className="truncate font-medium">{cycle.label}</p>
                      <p className="truncate text-sm text-muted-foreground">
                        {cycle.tutor_name}
                        {cycle.learner_name ? ` · ${cycle.learner_name}` : ""}
                      </p>
                      {Number(cycle.credit_applied) > 0 && (
                        <p className="text-xs text-success">
                          {t("family.credit")}: {formatETB(cycle.credit_applied)}
                        </p>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center justify-between gap-3 sm:justify-end">
                    <p className="font-mono text-lg font-semibold tabular-nums">
                      {formatETB(cycle.amount_due)}
                    </p>
                    <Button
                      onClick={() => {
                        setPayCycle(cycle)
                        setGateway(gateways[0]?.code ?? "")
                      }}
                    >
                      <Receipt data-slot="icon" />
                      {t("family.payMonth", { label: cycle.label })}
                    </Button>
                  </div>
                </div>
              ))}
            </section>
          )}

          {engagements.length > 0 && (
            <section className="page-gutter space-y-3">
              <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t("family.myTutors")}
              </h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {engagements.map((engagement) => (
                  <button
                    key={engagement.id}
                    type="button"
                    onClick={() => router.push(`/me/tutoring/${engagement.id}`)}
                    className="pressable flex items-center gap-3 rounded-2xl border bg-card p-4 text-left shadow-xs transition-colors hover:bg-accent/50"
                  >
                    <PersonAvatar className="size-11" photoUrl={engagement.tutor.avatar_url} name={engagement.tutor.name ?? "?"} />
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <p className="truncate font-medium">{engagement.tutor.name}</p>
                        <Badge variant="outline" className={cn("shrink-0", STATUS_TONE[engagement.status])}>
                          {t(`status.${engagement.status}`)}
                        </Badge>
                      </div>
                      <p className="truncate text-sm text-muted-foreground">
                        {engagement.subjects.map((s) => s.name).join(", ") || engagement.tutor.headline}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {engagement.learner_name} ·{" "}
                        {t("workspace.monthlyPlan", {
                          sessions: engagement.sessions_per_week,
                          hours: engagement.hours_per_session,
                        })}
                      </p>
                    </div>
                    <GraduationCap className="size-4 shrink-0 text-muted-foreground" strokeWidth={2} />
                  </button>
                ))}
              </div>
            </section>
          )}

          {pendingRequests.length > 0 && (
            <section className="page-gutter space-y-3">
              <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t("request.myRequests")}
              </h2>
              {pendingRequests.map((request) => (
                <div
                  key={request.id}
                  className="flex items-center justify-between gap-3 rounded-2xl border bg-card p-4 shadow-xs"
                >
                  <div className="flex min-w-0 items-center gap-3">
                    <PersonAvatar className="size-10" photoUrl={request.tutor.avatar_url} name={request.tutor.name ?? "?"} />
                    <div className="min-w-0">
                      <p className="truncate font-medium">{request.tutor.name}</p>
                      <p className="truncate text-sm text-muted-foreground">
                        {request.student_name ?? t("request.myself")}
                      </p>
                    </div>
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <Badge variant="outline" className={STATUS_TONE[request.status]}>
                      {t(`status.${request.status}`)}
                    </Badge>
                    <Button
                      variant="ghost"
                      size="sm"
                      loading={withdrawing === request.id}
                      onClick={() => withdrawRequest(request.id)}
                    >
                      {t("request.withdraw")}
                    </Button>
                  </div>
                </div>
              ))}
            </section>
          )}

          {history.length > 0 && (
            <section className="page-gutter space-y-3">
              <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t("family.history")}
              </h2>
              <div className="overflow-hidden rounded-2xl border bg-card shadow-xs">
                {history.map((cycle, index) => (
                  <Link
                    key={cycle.id}
                    href={`/me/tutoring/${cycle.engagement_id}`}
                    className={cn(
                      "flex items-center justify-between gap-3 p-4 transition-colors hover:bg-accent/50",
                      index > 0 && "border-t",
                    )}
                  >
                    <div className="min-w-0">
                      <p className="truncate font-medium">{cycle.label}</p>
                      <p className="truncate text-sm text-muted-foreground">
                        {cycle.tutor_name}
                        {cycle.learner_name ? ` · ${cycle.learner_name}` : ""}
                      </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-3">
                      <span className="font-mono text-sm tabular-nums">{formatETB(cycle.amount_due)}</span>
                      <Badge variant="outline" className={STATUS_TONE[cycle.status]}>
                        {t(`status.${cycle.status}`)}
                      </Badge>
                    </div>
                  </Link>
                ))}
              </div>
            </section>
          )}
        </>
      )}

      {/* Gateway picker */}
      <ResponsiveSheet open={payCycle !== null} onOpenChange={(open) => !open && !paying && setPayCycle(null)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>
              {payCycle ? t("family.payMonth", { label: payCycle.label }) : ""}
            </ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            {payCycle && (
              <div className="rounded-2xl border bg-muted/30 p-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">{payCycle.tutor_name}</span>
                  <span className="font-mono text-xl font-semibold tabular-nums">
                    {formatETB(payCycle.amount_due)}
                  </span>
                </div>
                <p className="mt-2 text-xs text-muted-foreground">{t("family.escrowNote")}</p>
              </div>
            )}
            <div className="space-y-2">
              <p className="text-sm font-medium">{t("family.payWith")}</p>
              <div className="grid gap-2">
                {gateways.map((option) => (
                  <button
                    key={option.code}
                    type="button"
                    onClick={() => setGateway(option.code)}
                    className={cn(
                      "touch-target flex items-center justify-between rounded-xl border px-4 py-3 text-left text-sm font-medium transition-colors",
                      gateway === option.code
                        ? "border-primary bg-primary/5 text-primary"
                        : "bg-muted/30 hover:bg-accent/50",
                    )}
                  >
                    {option.label}
                    <ExternalLink className="size-4 text-muted-foreground" strokeWidth={2} />
                  </button>
                ))}
              </div>
            </div>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              disabled={paying}
              onClick={() => setPayCycle(null)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button className="h-11 flex-1" loading={paying} disabled={!gateway} onClick={startPayment}>
              {payCycle ? t("family.payMonth", { label: payCycle.label }) : ""}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
