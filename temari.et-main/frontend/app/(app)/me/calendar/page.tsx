"use client"

import {
  BookOpenCheck,
  CalendarDays,
  FileQuestion,
  Flag,
  FlagOff,
  NotebookPen,
  Sun,
  type LucideIcon,
} from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"

import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { Badge } from "@/components/ui/badge"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { fmtDate } from "@/lib/dates"
import { useLocale, useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

interface AgendaEvent {
  type: "holiday" | "term_start" | "term_end" | "assessment" | "exam" | "assignment_due"
  date: string
  time: string | null
  title: string
  subject: string | null
}

const TYPE_META: Record<AgendaEvent["type"], { icon: LucideIcon; tint: string }> = {
  holiday: { icon: Sun, tint: "bg-success/10 text-success" },
  term_start: { icon: Flag, tint: "bg-info/10 text-info" },
  term_end: { icon: FlagOff, tint: "bg-info/10 text-info" },
  assessment: { icon: NotebookPen, tint: "bg-warning/10 text-warning" },
  exam: { icon: FileQuestion, tint: "bg-destructive/10 text-destructive" },
  assignment_due: { icon: BookOpenCheck, tint: "bg-primary/10 text-primary" },
}

/**
 * The family school calendar (relationship lane): the next three months of
 * holidays, term boundaries, planned assessments, exam windows and homework
 * deadlines as one agenda — grouped by day, today first.
 */
export default function MyCalendarPage() {
  const { t } = useTranslation("me")
  const { locale } = useLocale()
  const { user } = useAuth()

  const isStudent = user?.is_student === true
  const isParent = user?.is_parent === true && !isStudent

  const { children, child, activeChild, setActiveChild } = useChildren(isParent)
  const childId = child?.student_id ?? null

  const [events, setEvents] = useState<AgendaEvent[] | null>(null)

  useEffect(() => {
    if (!isStudent && childId === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on child switch
    setEvents(null)
    const url = isStudent ? "/me/student/calendar" : `/me/children/${childId}/calendar`
    apiFetch<{ data: AgendaEvent[] }>(url)
      .then((res) => !cancelled && setEvents(res.data))
      .catch(() => !cancelled && setEvents([]))
    return () => {
      cancelled = true
    }
  }, [isStudent, childId])

  // "Wed, Hamle 15" — day + month in the school's active calendar, no year
  // (every group here is inside the visible range).
  const dayHeading = useCallback(
    (date: string) => fmtDate(date, { noYear: true, weekday: true, locale }),
    [locale],
  )

  const groups = useMemo(() => {
    const byDate = new Map<string, AgendaEvent[]>()
    for (const event of events ?? []) {
      const list = byDate.get(event.date) ?? []
      list.push(event)
      byDate.set(event.date, list)
    }
    return [...byDate.entries()]
  }, [events])

  const today = new Date().toISOString().slice(0, 10)
  const empty = !isStudent && !isParent

  return (
    <div className="space-y-6">
      <PageHeader title={t("calendar.title")} description={t("calendar.subtitle")}>
        {isParent && children && children.length > 1 ? (
          <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
        ) : null}
      </PageHeader>

      <div className="page-gutter">
        <div className="mx-auto">
          {empty ? (
            <EmptyState icon={CalendarDays} title={t("calendar.emptyAccount")} />
          ) : events === null ? (
            <div className="space-y-3">
              <Skeleton className="h-20 w-full rounded-2xl" />
              <Skeleton className="h-20 w-full rounded-2xl" />
              <Skeleton className="h-20 w-full rounded-2xl" />
            </div>
          ) : groups.length === 0 ? (
            <EmptyState
              icon={CalendarDays}
              title={t("calendar.empty")}
              description={t("calendar.emptyDesc")}
            />
          ) : (
            <div className="space-y-5">
              {groups.map(([date, list]) => (
                <section key={date} className="space-y-2">
                  <div className="flex items-center gap-2">
                    <h2
                      className={cn(
                        "text-xs font-semibold uppercase tracking-wide",
                        date === today ? "text-primary" : "text-muted-foreground",
                      )}
                    >
                      {dayHeading(date)}
                    </h2>
                    {date === today && (
                      <Badge className="h-4 px-1.5 text-[9px]">{t("calendar.today")}</Badge>
                    )}
                  </div>
                  <div className="space-y-2">
                    {list.map((event, index) => {
                      const meta = TYPE_META[event.type]
                      const Icon = meta.icon
                      return (
                        <div
                          key={`${event.type}-${event.title}-${index}`}
                          className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3 shadow-xs"
                        >
                          <span
                            className={cn(
                              "flex size-9 shrink-0 items-center justify-center rounded-xl",
                              meta.tint,
                            )}
                          >
                            <Icon className="size-4" />
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">{event.title}</p>
                            <p className="truncate text-xs text-muted-foreground">
                              {[t(`calendar.types.${event.type}`), event.subject, event.time]
                                .filter(Boolean)
                                .join(" · ")}
                            </p>
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </section>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
