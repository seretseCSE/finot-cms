"use client"

import {
  ArrowLeftRight,
  BookOpenCheck,
  CalendarCheck2,
  CalendarClock,
  CalendarDays,
  ChevronRight,
  CircleAlert,
  FileBadge,
  GraduationCap,
  Receipt,
  type LucideIcon,
} from "lucide-react"
import Link from "next/link"
import { useEffect, useState } from "react"

import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { Badge } from "@/components/ui/badge"
import { CopyableId } from "@/components/ui/copyable-id"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/** /me/children/{id}/home — the aggregated tile payload (one request). */
interface ChildHome {
  attendance: {
    rate: number | null
    streak: number
    absent: number
    total: number
  } | null
  results: {
    latest: {
      term_id: number
      term_name: string | null
      average: number | null
      rank: number | null
      rank_of: number | null
    } | null
  } | null
  fees: {
    open_count: number
    open_balance: string
    next_due_date: string | null
    overdue_count: number
  } | null
  classwork: { due_count: number }
}

/**
 * THE PARENT HOME (ADR-012): one child at a time, one aggregated request —
 * a status tile per concern (attendance, results, fees, classwork), each
 * deep-linking to its full page. Tiles the guardian link doesn't allow
 * simply don't render.
 */
export default function MyChildrenPage() {
  const { t } = useTranslation("me")
  const { user } = useAuth()

  const { children, child, activeChild, setActiveChild } = useChildren(user?.is_parent === true)
  const childId = child?.student_id ?? null

  const [home, setHome] = useState<ChildHome | null>(null)

  useEffect(() => {
    if (childId === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on child switch
    setHome(null)
    apiFetch<{ data: ChildHome }>(`/me/children/${childId}/home`)
      .then((res) => !cancelled && setHome(res.data))
      .catch(() => !cancelled && setHome(null))
    return () => {
      cancelled = true
    }
  }, [childId])

  if (!user?.is_parent) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("parent.title")} description={t("parent.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={GraduationCap} title={t("parent.empty")} />
        </div>
      </div>
    )
  }

  const enrollment = child?.current_enrollment ?? null
  const overdue = (home?.fees?.overdue_count ?? 0) > 0

  return (
    <div className="space-y-6">
      <PageHeader title={t("parent.title")} description={t("parent.subtitle")}>
        {children && children.length > 1 ? (
          <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
        ) : null}
      </PageHeader>

      <div className="page-gutter">
        <div className="mx-auto space-y-4">
          {children === null ? (
            <>
              <Skeleton className="h-24 w-full rounded-2xl" />
              <div className="grid grid-cols-2 gap-3">
                <Skeleton className="h-28 rounded-2xl" />
                <Skeleton className="h-28 rounded-2xl" />
                <Skeleton className="h-28 rounded-2xl" />
                <Skeleton className="h-28 rounded-2xl" />
              </div>
            </>
          ) : children.length === 0 ? (
            <EmptyState icon={GraduationCap} title={t("parent.empty")} />
          ) : child === null ? null : (
            <>
              {/* ── Child hero ── */}
              <div className="flex items-center gap-4 rounded-2xl border bg-card p-4 shadow-xs">
                <PersonAvatar
                  name={child.full_name}
                  photoUrl={child.photo_url}
                  className="size-14 rounded-2xl text-base"
                />
                <div className="min-w-0 flex-1">
                  <p className="flex flex-wrap items-center gap-2 text-base font-semibold">
                    {child.full_name}
                    <CopyableId value={child.public_id} />
                  </p>
                  <p className="truncate text-sm text-muted-foreground">
                    {enrollment
                      ? [enrollment.grade_level, enrollment.section, enrollment.school]
                          .filter(Boolean)
                          .join(" · ")
                      : t("parent.notEnrolled")}
                  </p>
                </div>
                {enrollment && (
                  <Badge variant={enrollment.status === "active" ? "default" : "secondary"}>
                    {enrollment.status}
                  </Badge>
                )}
              </div>

              {/* ── Overdue alert ── */}
              {overdue && home?.fees && (
                <Link
                  href="/me/payments"
                  className="pressable flex items-center gap-3 rounded-2xl border border-destructive/30 bg-destructive/5 px-4 py-3"
                >
                  <CircleAlert className="size-5 shrink-0 text-destructive" />
                  <span className="min-w-0 flex-1 text-sm">
                    {t("parent.overdueAlert", {
                      count: home.fees.overdue_count,
                      amount: home.fees.open_balance,
                    })}
                  </span>
                  <ChevronRight className="size-4 shrink-0 text-destructive" />
                </Link>
              )}

              {/* ── Status tiles ── */}
              {home === null ? (
                <div className="grid grid-cols-2 gap-3">
                  <Skeleton className="h-28 rounded-2xl" />
                  <Skeleton className="h-28 rounded-2xl" />
                  <Skeleton className="h-28 rounded-2xl" />
                  <Skeleton className="h-28 rounded-2xl" />
                </div>
              ) : (
                <div className="grid grid-cols-2 gap-3">
                  {home.attendance && (
                    <StatusTile
                      href="/me/attendance"
                      icon={CalendarCheck2}
                      tint="text-success bg-success/10"
                      value={home.attendance.rate !== null ? `${home.attendance.rate}%` : "—"}
                      label={t("parent.tiles.attendance")}
                      hint={
                        home.attendance.streak > 0
                          ? t("parent.tiles.streak", { days: home.attendance.streak })
                          : undefined
                      }
                    />
                  )}
                  {home.results && (
                    <StatusTile
                      href="/me/results"
                      icon={FileBadge}
                      tint="text-primary bg-primary/10"
                      value={
                        home.results.latest?.average != null
                          ? String(home.results.latest.average)
                          : "—"
                      }
                      label={t("parent.tiles.results")}
                      hint={
                        home.results.latest?.rank != null && home.results.latest.rank_of != null
                          ? t("parent.tiles.rank", {
                              rank: home.results.latest.rank,
                              of: home.results.latest.rank_of,
                            })
                          : home.results.latest?.term_name ?? undefined
                      }
                    />
                  )}
                  {home.fees && (
                    <StatusTile
                      href="/me/payments"
                      icon={Receipt}
                      tint={overdue ? "text-destructive bg-destructive/10" : "text-warning bg-warning/10"}
                      value={`${home.fees.open_balance} ETB`}
                      label={t("parent.tiles.fees")}
                      hint={
                        home.fees.next_due_date
                          ? t("parent.tiles.nextDue", { date: home.fees.next_due_date })
                          : t("parent.feesSettled")
                      }
                    />
                  )}
                  <StatusTile
                    href="/me/children/learning"
                    icon={BookOpenCheck}
                    tint="text-info bg-info/10"
                    value={String(home.classwork.due_count)}
                    label={t("parent.tiles.classwork")}
                    hint={t("parent.tiles.workDue")}
                  />
                </div>
              )}

              {/* ── Quick links ── */}
              <section className="grid grid-cols-3 gap-2">
                {[
                  { href: "/me/timetable", icon: CalendarClock, label: t("timetable.title") },
                  { href: "/me/calendar", icon: CalendarDays, label: t("calendar.title") },
                  { href: "/me/transfers", icon: ArrowLeftRight, label: t("parent.linkTransfers") },
                ].map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    className="pressable flex flex-col items-center gap-1.5 rounded-2xl border bg-card px-3 py-3.5 text-center shadow-xs hover:bg-accent/50"
                  >
                    <link.icon className="size-5 text-primary" strokeWidth={1.75} />
                    <span className="text-xs font-medium">{link.label}</span>
                  </Link>
                ))}
              </section>
            </>
          )}
        </div>
      </div>
    </div>
  )
}

function StatusTile({
  href,
  icon: Icon,
  tint,
  value,
  label,
  hint,
}: {
  href: string
  icon: LucideIcon
  tint: string
  value: string
  label: string
  hint?: string
}) {
  return (
    <Link
      href={href}
      className="pressable group flex flex-col gap-2 rounded-2xl border bg-card p-4 shadow-xs transition-colors hover:bg-accent/50"
    >
      <div className="flex items-center justify-between">
        <span className={cn("flex size-9 items-center justify-center rounded-xl", tint)}>
          <Icon className="size-4" />
        </span>
        <ChevronRight className="size-4 text-muted-foreground/50 transition-transform group-hover:translate-x-0.5" />
      </div>
      <div className="min-w-0">
        <p className="truncate text-lg font-semibold tabular-nums leading-tight">{value}</p>
        <p className="truncate text-xs text-muted-foreground">{label}</p>
        {hint && <p className="truncate text-[11px] text-muted-foreground/80">{hint}</p>}
      </div>
    </Link>
  )
}
