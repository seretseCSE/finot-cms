"use client"

import {
  ArrowRight,
  BadgeCheck,
  Clock,
  ExternalLink,
  GraduationCap,
  Inbox,
  Rocket,
  Star,
  Wallet,
} from "lucide-react"
import Link from "next/link"
import { useEffect, useState } from "react"

import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { formatETB } from "@/lib/utils"
import { fmtDate } from "@/lib/dates"

interface TutorDashboard {
  status: string
  wallet_balance: string
  rating_avg: string | null
  rating_count: number
  hours_taught: string
  boosted_until: string | null
  pending_requests: number
  active_engagements: { id: number; learner: string; subjects: { name: string }[] }[]
  sessions_awaiting_confirmation: number
  open_payout: boolean
}

interface ProfileMeta {
  profile: { status: string; slug: string | null; decline_reason: string | null; suspend_reason: string | null } | null
}

/**
 * The tutor workspace home. Adapts to the profile lifecycle: no profile →
 * apply CTA; pending → review banner; approved → the working dashboard.
 */
export default function TutorHomePage() {
  const { t } = useTranslation("tutoring")
  const { user } = useAuth()

  const [loading, setLoading] = useState(true)
  const [meta, setMeta] = useState<ProfileMeta | null>(null)
  const [dashboard, setDashboard] = useState<TutorDashboard | null>(null)

  useEffect(() => {
    let cancelled = false
    void (async () => {
      try {
        const profileRes = await apiFetch<{ data: ProfileMeta }>("/tutoring/profile")
        if (cancelled) return
        setMeta(profileRes.data)
        if (profileRes.data.profile !== null) {
          const dash = await apiFetch<{ data: TutorDashboard }>("/tutoring/dashboard")
          if (!cancelled) setDashboard(dash.data)
        }
      } catch {
        // handled by empty states below
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [user?.id])

  if (loading) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("workspace.title")} />
        <div className="page-gutter grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-28 rounded-2xl" />
          ))}
        </div>
      </div>
    )
  }

  const profile = meta?.profile

  // No application yet → the pitch.
  if (!profile) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("apply.title")} description={t("apply.subtitle")} />
        <div className="page-gutter">
          <EmptyState
            icon={GraduationCap}
            title={t("dir.becomeTutor")}
            description={t("dir.becomeTutorDesc")}
            action={
              <Button asChild>
                <Link href="/tutoring/apply">
                  {t("dir.applyNow")}
                  <ArrowRight data-slot="icon" />
                </Link>
              </Button>
            }
          />
        </div>
      </div>
    )
  }

  const banner =
    profile.status === "pending" ? (
      <div className="rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning">
        {t("apply.pendingBanner")}
      </div>
    ) : profile.status === "declined" ? (
      <div className="space-y-2 rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
        <p>{t("apply.declinedBanner", { reason: profile.decline_reason ?? "—" })}</p>
        <Button asChild size="sm" variant="outline">
          <Link href="/tutoring/apply">{t("apply.editProfile")}</Link>
        </Button>
      </div>
    ) : profile.status === "suspended" ? (
      <div className="rounded-2xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
        {t("apply.suspendedBanner", { reason: profile.suspend_reason ?? "—" })}
      </div>
    ) : profile.status === "draft" ? (
      <div className="flex items-center justify-between gap-3 rounded-2xl border border-info/30 bg-info/10 p-4 text-sm text-info">
        <span>{t("apply.subtitle")}</span>
        <Button asChild size="sm">
          <Link href="/tutoring/apply">{t("apply.editProfile")}</Link>
        </Button>
      </div>
    ) : null

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("workspace.title")}
        description={
          dashboard?.boosted_until
            ? t("workspace.boostedUntil", {
                date: fmtDate(dashboard.boosted_until),
              })
            : undefined
        }
        actions={
          <div className="flex gap-2">
            {profile.slug && (
              <Button asChild variant="outline">
                <a href={`/tutors/${profile.slug}`} target="_blank" rel="noreferrer">
                  <ExternalLink data-slot="icon" />
                  {t("workspace.viewPublicProfile")}
                </a>
              </Button>
            )}
            <Button asChild variant="outline">
              <Link href="/tutoring/apply">{t("apply.editProfile")}</Link>
            </Button>
          </div>
        }
      />

      {banner && <div className="page-gutter">{banner}</div>}

      {dashboard && profile.status === "approved" && (
        <>
          <section className="page-gutter grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
              icon={Wallet}
              label={t("workspace.wallet")}
              value={formatETB(dashboard.wallet_balance)}
            />
            <StatCard
              icon={Star}
              label={t("workspace.rating")}
              value={
                dashboard.rating_avg
                  ? `${Number(dashboard.rating_avg).toFixed(1)} (${dashboard.rating_count})`
                  : t("dir.noReviews")
              }
            />
            <StatCard icon={BadgeCheck} label={t("workspace.hoursTaught")} value={dashboard.hours_taught} />
            <StatCard
              icon={Clock}
              label={t("workspace.awaitingConfirmation")}
              value={String(dashboard.sessions_awaiting_confirmation)}
            />
          </section>

          <section className="page-gutter grid gap-3 md:grid-cols-2">
            <Link
              href="/tutoring/requests"
              className="pressable flex items-center justify-between rounded-2xl border bg-card p-5 shadow-xs transition-colors hover:bg-accent/50"
            >
              <div className="flex items-center gap-3">
                <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <Inbox className="size-5" strokeWidth={1.75} />
                </div>
                <div>
                  <p className="font-medium">{t("request.inbox")}</p>
                  <p className="text-sm text-muted-foreground">
                    {dashboard.pending_requests} {t("workspace.pendingRequests").toLowerCase()}
                  </p>
                </div>
              </div>
              <ArrowRight className="size-4 text-muted-foreground" strokeWidth={2} />
            </Link>

            <Link
              href="/tutoring/earnings"
              className="pressable flex items-center justify-between rounded-2xl border bg-card p-5 shadow-xs transition-colors hover:bg-accent/50"
            >
              <div className="flex items-center gap-3">
                <div className="flex size-10 items-center justify-center rounded-xl bg-accent">
                  <Rocket className="size-5" strokeWidth={1.75} />
                </div>
                <div>
                  <p className="font-medium">{t("workspace.boostTitle")}</p>
                  <p className="text-sm text-muted-foreground">
                    {dashboard.boosted_until
                      ? t("workspace.boostedUntil", {
                          date: fmtDate(dashboard.boosted_until),
                        })
                      : t("workspace.notBoosted")}
                  </p>
                </div>
              </div>
              <ArrowRight className="size-4 text-muted-foreground" strokeWidth={2} />
            </Link>
          </section>

          <section className="page-gutter space-y-3">
            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("workspace.activeEngagements")}
            </h2>
            {dashboard.active_engagements.length === 0 ? (
              <EmptyState
                icon={GraduationCap}
                title={t("workspace.emptyEngagements")}
                description={t("workspace.emptyEngagementsDesc")}
              />
            ) : (
              <div className="grid gap-3 sm:grid-cols-2">
                {dashboard.active_engagements.map((engagement) => (
                  <Link
                    key={engagement.id}
                    href={`/tutoring/engagements/${engagement.id}`}
                    className="pressable flex items-center justify-between rounded-2xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent/50"
                  >
                    <div className="min-w-0">
                      <p className="truncate font-medium">{engagement.learner}</p>
                      <p className="truncate text-sm text-muted-foreground">
                        {(engagement.subjects ?? []).map((s) => s.name).join(", ")}
                      </p>
                    </div>
                    <ArrowRight className="size-4 shrink-0 text-muted-foreground" strokeWidth={2} />
                  </Link>
                ))}
              </div>
            )}
          </section>
        </>
      )}
    </div>
  )
}
