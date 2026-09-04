"use client"

import { CalendarCheck2, CircleAlert, CheckCircle2, Clock3, FilePlus2 } from "lucide-react"
import { useCallback, useEffect, useState } from "react"

import { AttendanceCalendar } from "@/components/me/attendance-calendar"
import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { ExcuseSheet } from "@/components/me/excuse-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"

interface ExcuseRow {
  id: number
  starts_on: string
  ends_on: string
  reason: string
  status: "pending" | "approved" | "rejected"
  decision_note: string | null
  created_at: string | null
}

const EXCUSE_BADGE = {
  pending: { icon: Clock3, className: "bg-warning/10 text-warning" },
  approved: { icon: CheckCircle2, className: "bg-success/10 text-success" },
  rejected: { icon: CircleAlert, className: "bg-destructive/10 text-destructive" },
} as const

/**
 * The family attendance page (relationship lane): a student follows their own
 * register on a full-width week/month calendar; a parent flips between
 * children with the shared child switcher — gated per link by
 * can_view_attendance. One page, both hats.
 */
export default function MyAttendancePage() {
  const { t } = useTranslation("me")
  const { user } = useAuth()

  const isStudent = user?.is_student === true
  const isParent = user?.is_parent === true && !isStudent

  const { children, child, activeChild, setActiveChild } = useChildren(isParent)
  const childId = child?.student_id ?? null
  const childAllowed = child?.permissions.can_view_attendance === true

  const empty = !isStudent && !isParent

  // ── Absence excuses (parent hat only) ──
  const [excuses, setExcuses] = useState<ExcuseRow[] | null>(null)
  const [excuseOpen, setExcuseOpen] = useState(false)

  const loadExcuses = useCallback(() => {
    if (childId === null || !childAllowed) return
    apiFetch<{ data: ExcuseRow[] }>(`/me/children/${childId}/absence-excuses`)
      .then((res) => setExcuses(res.data))
      .catch(() => setExcuses([]))
  }, [childId, childAllowed])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on child switch
    setExcuses(null)
    if (!isParent) return
    loadExcuses()
  }, [isParent, loadExcuses])

  return (
    <div className="space-y-6">
      <PageHeader title={t("attendance.title")} description={t("attendance.subtitle")}>
        {isParent && children && children.length > 1 ? (
          <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
        ) : null}
      </PageHeader>

      <div className="page-gutter">
        {empty ? (
          <EmptyState icon={CalendarCheck2} title={t("attendance.empty")} />
        ) : isStudent ? (
          <AttendanceCalendar endpoint="/me/student/attendance" />
        ) : children === null ? (
          <Skeleton className="h-72 w-full rounded-2xl" />
        ) : children.length === 0 ? (
          <EmptyState icon={CalendarCheck2} title={t("parent.empty")} />
        ) : childId !== null && childAllowed ? (
          <div className="space-y-6">
            <AttendanceCalendar endpoint={`/me/children/${childId}/attendance`} />

            {/* ── Absence excuses: file one + track past requests ── */}
            <section className="space-y-2">
              <div className="flex items-center justify-between">
                <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("excuses.title")}
                </h2>
                <Button variant="outline" size="sm" onClick={() => setExcuseOpen(true)}>
                  <FilePlus2 className="size-4" />
                  {t("excuses.request")}
                </Button>
              </div>
              {excuses === null ? (
                <Skeleton className="h-16 w-full rounded-2xl" />
              ) : excuses.length === 0 ? (
                <p className="rounded-2xl border border-dashed px-4 py-4 text-sm text-muted-foreground">
                  {t("excuses.empty")}
                </p>
              ) : (
                <div className="space-y-2">
                  {excuses.map((excuse) => {
                    const badge = EXCUSE_BADGE[excuse.status]
                    const BadgeIcon = badge.icon
                    return (
                      <div
                        key={excuse.id}
                        className="rounded-2xl border bg-card px-4 py-3 shadow-xs"
                      >
                        <div className="flex items-center justify-between gap-2">
                          <p className="text-sm font-medium tabular-nums">
                            {excuse.starts_on === excuse.ends_on
                              ? excuse.starts_on
                              : `${excuse.starts_on} → ${excuse.ends_on}`}
                          </p>
                          <Badge
                            variant="outline"
                            className={`gap-1 border-transparent ${badge.className}`}
                          >
                            <BadgeIcon className="size-3" />
                            {t(`excuses.statuses.${excuse.status}`)}
                          </Badge>
                        </div>
                        <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                          {excuse.reason}
                        </p>
                        {excuse.status === "rejected" && excuse.decision_note && (
                          <p className="mt-1 text-xs text-destructive">{excuse.decision_note}</p>
                        )}
                      </div>
                    )
                  })}
                </div>
              )}
            </section>
          </div>
        ) : (
          <div className="rounded-2xl border bg-card shadow-xs">
            <EmptyState icon={CalendarCheck2} title={t("attendance.noAccess")} compact />
          </div>
        )}
      </div>

      <ExcuseSheet
        studentId={childId}
        open={excuseOpen}
        onOpenChange={setExcuseOpen}
        onFiled={loadExcuses}
      />
    </div>
  )
}
