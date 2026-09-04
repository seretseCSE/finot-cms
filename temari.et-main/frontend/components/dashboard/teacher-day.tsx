"use client"

import {
  BookOpen,
  CalendarOff,
  ClipboardCheck,
  DoorOpen,
  GraduationCap,
  ListChecks,
} from "lucide-react"
import Link from "next/link"
import { useEffect, useState } from "react"

import { useTranslation } from "@/lib/i18n"
import type { DashboardTeacher, DashboardTeacherPeriod } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtTime } from "@/lib/dates"

/** "HH:MM" local wall-clock, refreshed each minute so the now-marker moves. */
function useClock(): string {
  const [now, setNow] = useState(() => clock())
  useEffect(() => {
    const id = setInterval(() => setNow(clock()), 60_000)
    return () => clearInterval(id)
  }, [])
  return now
}

function clock(): string {
  const d = new Date()
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`
}

function periodState(
  period: DashboardTeacherPeriod,
  now: string
): "past" | "now" | "next" {
  if (period.ends_at && period.ends_at <= now) return "past"
  if (
    period.starts_at &&
    period.starts_at <= now &&
    (!period.ends_at || period.ends_at > now)
  )
    return "now"
  return "next"
}

/**
 * The teacher's personal command center — "my day": today's periods off the
 * published timetable with a live now-marker, homeroom registers still to
 * mark (the #1 morning job), the marklist pipeline and the LMS grading pile.
 * For a pure teacher this IS the dashboard.
 */
export function TeacherDay({ teacher }: { teacher: DashboardTeacher }) {
  const { t } = useTranslation("common")
  const now = useClock()

  const unmarked = teacher.homerooms.filter(
    (h) => h.students > 0 && h.marked_today === 0
  )
  const chips = [
    teacher.marklists.draft > 0 && {
      key: "marklists",
      href: "/marklists",
      icon: ListChecks,
      label: t("dashboard.teacherMarklistsDraft", {
        count: teacher.marklists.draft,
      }),
    },
    teacher.lms.to_grade > 0 && {
      key: "toGrade",
      href: "/lms/assignments",
      icon: BookOpen,
      label: t("dashboard.teacherToGrade", { count: teacher.lms.to_grade }),
    },
  ].filter(Boolean) as {
    key: string
    href: string
    icon: typeof ListChecks
    label: string
  }[]

  // With no homeroom and no work piles the schedule takes the full row.
  const hasSideColumn = teacher.homerooms.length > 0 || chips.length > 0

  return (
    <section>
      <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t("dashboard.myDay")}
      </h2>

      <div className="grid gap-3 lg:grid-cols-5">
        {/* ── Today's periods ── */}
        <div
          className={cn(
            "rounded-2xl border bg-card p-4 shadow-xs",
            hasSideColumn ? "lg:col-span-3" : "lg:col-span-5"
          )}
        >
          <h3 className="text-sm font-medium">
            {t("dashboard.todaysClasses")}
          </h3>
          {teacher.today.length === 0 ? (
            <p className="mt-6 mb-4 flex items-center justify-center gap-2 text-sm text-muted-foreground">
              <CalendarOff className="size-4" strokeWidth={1.75} />
              {t("dashboard.noClassesToday")}
            </p>
          ) : (
            <ol className="mt-3 space-y-1">
              {teacher.today.map((period) => {
                const state = periodState(period, now)
                return (
                  <li
                    key={`${period.period}-${period.section_id}`}
                    className={cn(
                      "flex items-center gap-3 rounded-xl px-2.5 py-2 transition-colors",
                      state === "now" && "bg-primary/8 ring-1 ring-primary/20",
                      state === "past" && "opacity-55"
                    )}
                  >
                    <span
                      className={cn(
                        "w-14 shrink-0 text-xs tabular-nums",
                        state === "now"
                          ? "font-semibold text-primary"
                          : "text-muted-foreground"
                      )}
                    >
                      {period.starts_at ? fmtTime(period.starts_at) : `#${period.period}`}
                    </span>
                    <span
                      className={cn(
                        "h-8 w-1 shrink-0 rounded-full",
                        state === "now" ? "bg-primary" : "bg-border"
                      )}
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium">
                        {period.subject}
                      </span>
                      <span className="flex items-center gap-2 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1">
                          <GraduationCap
                            className="size-3"
                            strokeWidth={1.75}
                          />
                          {period.section}
                        </span>
                        {period.room && (
                          <span className="flex items-center gap-1">
                            <DoorOpen className="size-3" strokeWidth={1.75} />
                            {period.room}
                          </span>
                        )}
                      </span>
                    </span>
                    {state === "now" && (
                      <span className="shrink-0 rounded-full bg-primary/15 px-2 py-0.5 text-[11px] font-semibold text-primary">
                        {t("dashboard.nowTeaching")}
                      </span>
                    )}
                  </li>
                )
              })}
            </ol>
          )}
        </div>

        {/* ── Homerooms + work piles ── */}
        {hasSideColumn && (
          <div className="space-y-3 lg:col-span-2">
            {teacher.homerooms.length > 0 && (
              <div className="rounded-2xl border bg-card p-4 shadow-xs">
                <h3 className="text-sm font-medium">
                  {t("dashboard.myHomerooms")}
                </h3>
                <ul className="mt-2.5 space-y-2">
                  {teacher.homerooms.map((homeroom) => {
                    const marked =
                      homeroom.students > 0 && homeroom.marked_today > 0
                    return (
                      <li
                        key={homeroom.section_id}
                        className="flex items-center gap-3"
                      >
                        <span className="min-w-0 flex-1">
                          <span className="block truncate text-sm font-medium">
                            {homeroom.section}
                          </span>
                          <span className="text-xs text-muted-foreground tabular-nums">
                            {t("dashboard.homeroomStudents", {
                              count: homeroom.students,
                            })}
                          </span>
                        </span>
                        {marked ? (
                          <span className="flex items-center gap-1 rounded-full bg-success/10 px-2.5 py-1 text-xs font-medium text-success">
                            <ClipboardCheck
                              className="size-3.5"
                              strokeWidth={2}
                            />
                            {t("dashboard.attendanceMarked", {
                              marked: homeroom.marked_today,
                              total: homeroom.students,
                            })}
                          </span>
                        ) : (
                          <Link
                            href="/attendance"
                            className="pressable touch-target flex items-center gap-1.5 rounded-full bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                          >
                            <ClipboardCheck
                              className="size-3.5"
                              strokeWidth={2}
                            />
                            {t("dashboard.takeAttendance")}
                          </Link>
                        )}
                      </li>
                    )
                  })}
                </ul>
                {unmarked.length > 0 && (
                  <p className="mt-2.5 text-xs text-warning">
                    {t("dashboard.homeroomUnmarked", {
                      count: unmarked.length,
                    })}
                  </p>
                )}
              </div>
            )}

            {chips.length > 0 && (
              <div className="rounded-2xl border bg-card p-4 shadow-xs">
                <h3 className="text-sm font-medium">{t("dashboard.myWork")}</h3>
                <div className="mt-2.5 flex flex-wrap gap-2">
                  {chips.map((chip) => {
                    const Icon = chip.icon
                    return (
                      <Link
                        key={chip.key}
                        href={chip.href}
                        className="pressable flex items-center gap-1.5 rounded-full bg-accent px-3 py-1.5 text-xs font-medium transition-colors hover:bg-accent/70"
                      >
                        <Icon className="size-3.5" strokeWidth={1.75} />
                        {chip.label}
                      </Link>
                    )
                  })}
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  )
}
