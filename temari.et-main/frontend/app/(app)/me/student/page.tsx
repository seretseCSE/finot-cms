"use client"

import {
  BookOpen,
  BookOpenCheck,
  CalendarDays,
  ChevronRight,
  FileBadge,
  FileQuestion,
  GraduationCap,
  LibraryBig,
  Play,
  UserRound,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { StudentExamCard } from "@/components/lms/exam-card"
import { formatDateTime } from "@/components/lms/shared"
import { StudentAssignmentSheet } from "@/components/lms/student-assignment-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { CopyableId } from "@/components/ui/copyable-id"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { MeAssignment, MeCourse, MeExam, StudentTimetable } from "@/lib/types"
import { fmtTime } from "@/lib/dates"

const TABS = ["today", "profile"] as const

/** /me/student payload — self lane (ADR-012). */
interface OwnRecord {
  student_id: number
  full_name: string
  public_id: string | null
  photo_url: string | null
  gender: string
  date_of_birth: string | null
  enrollments: {
    id: number
    school: string | null
    branch: string | null
    grade_level: string | null
    section: string | null
    academic_year: string | null
    status: string
  }[]
}

interface Overview {
  assignments: MeAssignment[]
  exams: MeExam[]
}

function greetingKey(): "goodMorning" | "goodAfternoon" | "goodEvening" {
  const hour = new Date().getHours()
  if (hour < 12) return "goodMorning"
  if (hour < 18) return "goodAfternoon"
  return "goodEvening"
}

/**
 * THE STUDENT HOME — an app, not a record page. "Today" leads with what
 * matters right now: a live-exam resume banner, today's classes, work due
 * soon, open exams, and continue-learning course cards. The timetable and
 * the profile/enrollments/results live one tab away.
 */
export default function MyLearningPage() {
  const { t } = useTranslation("me")
  const { t: tl } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const { user } = useAuth()
  const router = useRouter()
  const [tab, setTab] = useProfileTabs(TABS, "today")

  const [record, setRecord] = useState<OwnRecord | null | "missing">(null)
  const [timetable, setTimetable] = useState<StudentTimetable | null | undefined>(undefined)
  const [overview, setOverview] = useState<Overview | null>(null)
  const [courses, setCourses] = useState<MeCourse[] | null>(null)
  const [openAssignment, setOpenAssignment] = useState<number | null>(null)

  useEffect(() => {
    if (!user?.is_student) return
    let cancelled = false
    apiFetch<{ data: OwnRecord }>("/me/student")
      .then((res) => !cancelled && setRecord(res.data))
      .catch((error) => {
        if (cancelled) return
        if (!(error instanceof ApiError && error.status === 404)) {
          toast.error(error instanceof ApiError ? error.message : t("student.loadFailed"))
        }
        setRecord("missing")
      })
    apiFetch<{ data: StudentTimetable | null }>("/me/student/timetable")
      .then((res) => !cancelled && setTimetable(res.data))
      .catch(() => !cancelled && setTimetable(null))
    apiFetch<{ data: Overview }>("/me/lms/overview")
      .then((res) => !cancelled && setOverview(res.data))
      .catch(() => !cancelled && setOverview({ assignments: [], exams: [] }))
    apiFetch<{ data: MeCourse[] }>("/me/courses?per_page=100")
      .then((res) => !cancelled && setCourses(res.data))
      .catch(() => !cancelled && setCourses([]))
    return () => {
      cancelled = true
    }
  }, [user?.is_student, t])

  // Coming back to the app (phone unlock, tab switch) refreshes the feed —
  // freshly published classwork appears without a manual reload.
  useEffect(() => {
    if (!user?.is_student) return
    function onVisible() {
      if (document.visibilityState !== "visible") return
      apiFetch<{ data: Overview }>("/me/lms/overview")
        .then((res) => setOverview(res.data))
        .catch(() => undefined)
    }
    document.addEventListener("visibilitychange", onVisible)
    return () => document.removeEventListener("visibilitychange", onVisible)
  }, [user?.is_student])

  if (!user?.is_student || record === "missing") {
    return (
      <div className="space-y-6">
        <PageHeader title={t("student.title")} description={t("student.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={BookOpen} title={t("student.empty")} />
        </div>
      </div>
    )
  }

  // Today's classes from the weekly grid (1=Monday … 6=Saturday).
  const jsDay = new Date().getDay()
  const todayDow = jsDay === 0 ? 7 : jsDay
  const todaySlots =
    timetable && timetable.days.includes(todayDow)
      ? timetable.periods
          .filter((period) => period.type === "class" && period.period_number !== null)
          .map((period) => ({
            period,
            slot: timetable.slots.find(
              (slot) => slot.day_of_week === todayDow && slot.period_number === period.period_number,
            ),
          }))
      : []

  const nowHm = new Date().toTimeString().slice(0, 5)
  const liveExam = overview?.exams.find((exam) => exam.live_attempt_id) ?? null
  const dueSoon = (overview?.assignments ?? [])
    .filter((assignment) => assignment.submission === null && assignment.status === "published")
    .slice(0, 4)
  const openExams = (overview?.exams ?? [])
    .filter((exam) => (exam.can_start || exam.live_attempt_id) && exam.status === "published")
    .slice(0, 4)
  const continueLearning = (courses ?? [])
    .filter((course) => course.progress_percent > 0 && course.progress_percent < 100)
    .slice(0, 3)

  const firstName = record !== null ? record.full_name.split(" ")[0] : ""
  const activeEnrollment =
    record !== null
      ? (record.enrollments.find((e) => e.status === "active") ?? record.enrollments[0])
      : undefined

  return (
    <div className="space-y-6">
      <PageHeader
        title={record === null ? t("student.title") : `${tc(`dashboard.${greetingKey()}`)}, ${firstName}`}
        description={
          activeEnrollment
            ? [activeEnrollment.grade_level, activeEnrollment.section, activeEnrollment.branch]
                .filter(Boolean)
                .join(" · ")
            : t("student.subtitle")
        }
      />

      <div className="page-gutter">
        <div className="mx-auto space-y-5">
          <ProfileTabBar
            tabs={[
              { key: "today" as const, label: t("student.tabs.today"), icon: CalendarDays },
              { key: "profile" as const, label: t("student.tabs.profile"), icon: UserRound },
            ]}
            value={tab}
            onChange={setTab}
          />

          {tab === "today" && (
            <>
              {/* live exam? one tap back in */}
              {liveExam && (
                <button
                  type="button"
                  onClick={() => router.push(`/me/exam/${liveExam.live_attempt_id}`)}
                  className="pressable flex w-full items-center gap-3 rounded-2xl border border-primary/40 bg-primary/5 px-4 py-3.5 text-left"
                >
                  <span className="relative flex size-3 shrink-0">
                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-60" />
                    <span className="relative inline-flex size-3 rounded-full bg-primary" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-semibold">{liveExam.title}</span>
                    <span className="block text-xs text-muted-foreground">
                      {t("student.liveExam")}
                    </span>
                  </span>
                  <Play className="size-4 shrink-0 text-primary" />
                </button>
              )}

              {/* today's classes strip */}
              <section className="space-y-2">
                <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("student.todayClasses")}
                </h3>
                {timetable === undefined ? (
                  <Skeleton className="h-20 w-full rounded-2xl" />
                ) : todaySlots.length === 0 ? (
                  <p className="rounded-2xl border border-dashed px-4 py-4 text-sm text-muted-foreground">
                    {t("student.noClassesToday")}
                  </p>
                ) : (
                  <div className="scrollbar-none -mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    {todaySlots.map(({ period, slot }) => {
                      const isNow =
                        period.starts_at.slice(0, 5) <= nowHm && nowHm < period.ends_at.slice(0, 5)
                      return (
                        <div
                          key={period.sequence}
                          className={`w-32 shrink-0 rounded-2xl border px-3 py-2.5 ${
                            isNow ? "border-primary/50 bg-primary/5" : ""
                          }`}
                        >
                          <p className="text-[10px] tabular-nums text-muted-foreground">
                            {fmtTime(period.starts_at)}–{fmtTime(period.ends_at)}
                          </p>
                          <p className="mt-0.5 truncate text-sm font-medium">
                            {slot?.subject ?? t("student.freePeriod")}
                          </p>
                          {slot?.teacher && (
                            <p className="truncate text-[10px] text-muted-foreground">{slot.teacher}</p>
                          )}
                          {isNow && (
                            <Badge className="mt-1 h-4 px-1.5 text-[9px]">{t("student.now")}</Badge>
                          )}
                        </div>
                      )
                    })}
                  </div>
                )}
              </section>

              {/* due soon */}
              <section className="space-y-2">
                <div className="flex items-center justify-between">
                  <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("student.dueSoon")}
                  </h3>
                  <Button variant="ghost" size="sm" className="h-8" onClick={() => router.push("/me/assignments")}>
                    {tc("dashboard.viewAll")} <ChevronRight className="size-3.5" />
                  </Button>
                </div>
                {overview === null ? (
                  <Skeleton className="h-24 w-full rounded-2xl" />
                ) : dueSoon.length === 0 ? (
                  <p className="rounded-2xl border border-dashed px-4 py-4 text-sm text-muted-foreground">
                    {t("student.nothingDue")}
                  </p>
                ) : (
                  <div className="space-y-2">
                    {dueSoon.map((assignment) => (
                      <button
                        key={assignment.id}
                        type="button"
                        onClick={() => setOpenAssignment(assignment.id)}
                        className="pressable flex w-full items-center gap-3 rounded-2xl border bg-card px-4 py-3 text-left shadow-xs hover:bg-accent/50"
                      >
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                          <BookOpenCheck className="size-4 text-primary" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">{assignment.title}</p>
                          <p className="text-xs text-muted-foreground">
                            {[
                              assignment.subject_name,
                              assignment.due_at
                                ? tl("learn.due", { date: formatDateTime(assignment.due_at) })
                                : null,
                            ]
                              .filter(Boolean)
                              .join(" · ")}
                          </p>
                        </div>
                        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                      </button>
                    ))}
                  </div>
                )}
              </section>

              {/* open exams */}
              {openExams.length > 0 && (
                <section className="space-y-2">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t("student.openExams")}
                    </h3>
                    <Button variant="ghost" size="sm" className="h-8" onClick={() => router.push("/me/exams")}>
                      {tc("dashboard.viewAll")} <ChevronRight className="size-3.5" />
                    </Button>
                  </div>
                  <div className="grid gap-3 md:grid-cols-2">
                    {openExams.map((exam) => (
                      <StudentExamCard key={exam.id} exam={exam} />
                    ))}
                  </div>
                </section>
              )}

              {/* continue learning */}
              {continueLearning.length > 0 && (
                <section className="space-y-2">
                  <div className="flex items-center justify-between">
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      {t("student.continueLearning")}
                    </h3>
                    <Button variant="ghost" size="sm" className="h-8" onClick={() => router.push("/me/courses")}>
                      {tc("dashboard.viewAll")} <ChevronRight className="size-3.5" />
                    </Button>
                  </div>
                  <div className="space-y-2">
                    {continueLearning.map((course) => (
                      <button
                        key={course.id}
                        type="button"
                        onClick={() => router.push(`/me/courses/${course.id}`)}
                        className="pressable flex w-full items-center gap-3 rounded-2xl border bg-card px-4 py-3 text-left shadow-xs hover:bg-accent/50"
                      >
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                          <LibraryBig className="size-4 text-primary" />
                        </div>
                        <div className="min-w-0 flex-1 space-y-1">
                          <p className="truncate text-sm font-medium">{course.title}</p>
                          <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                              className="h-full rounded-full bg-primary"
                              style={{ width: `${course.progress_percent}%` }}
                            />
                          </div>
                        </div>
                        <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                          {course.progress_percent}%
                        </span>
                      </button>
                    ))}
                  </div>
                </section>
              )}

              {/* quick links */}
              <section className="grid grid-cols-3 gap-2 sm:grid-cols-6">
                {[
                  { href: "/me/assignments", icon: BookOpenCheck, label: t("student.linkClasswork") },
                  { href: "/me/results", icon: FileBadge, label: t("student.linkResults") },
                  { href: "/me/courses", icon: LibraryBig, label: t("student.linkCourses") },
                  { href: "/me/exam-prep", icon: FileQuestion, label: t("student.linkPrep") },
                  { href: "/me/attendance", icon: GraduationCap, label: t("student.linkAttendance") },
                  { href: "/me/calendar", icon: CalendarDays, label: t("student.linkCalendar") },
                ].map((link) => (
                  <button
                    key={link.href}
                    type="button"
                    onClick={() => router.push(link.href)}
                    className="pressable flex flex-col items-center gap-1.5 rounded-2xl border bg-card px-3 py-3.5 text-center shadow-xs hover:bg-accent/50"
                  >
                    <link.icon className="size-5 text-primary" strokeWidth={1.75} />
                    <span className="text-xs font-medium">{link.label}</span>
                  </button>
                ))}
              </section>
            </>
          )}

          {tab === "profile" &&
            (record === null ? (
              <>
                <Skeleton className="h-32 w-full rounded-2xl" />
                <Skeleton className="h-40 w-full rounded-2xl" />
              </>
            ) : (
              <>
                <Card>
                  <CardHeader className="flex flex-row items-center gap-4">
                    {record.photo_url ? (
                      // eslint-disable-next-line @next/next/no-img-element -- signed URL
                      <img src={record.photo_url} alt="" className="size-14 rounded-2xl object-cover" />
                    ) : (
                      <div className="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-lg font-semibold text-primary">
                        {record.full_name.slice(0, 1)}
                      </div>
                    )}
                    <div className="min-w-0">
                      <CardTitle className="flex flex-wrap items-center gap-2 text-lg">
                        {record.full_name}
                        <CopyableId value={record.public_id} />
                      </CardTitle>
                      {record.date_of_birth ? (
                        <p className="text-sm text-muted-foreground">{record.date_of_birth}</p>
                      ) : null}
                    </div>
                  </CardHeader>
                </Card>

                <Card>
                  <CardHeader>
                    <CardTitle className="text-base">{t("student.enrollments")}</CardTitle>
                  </CardHeader>
                  <CardContent className="text-sm">
                    {record.enrollments.length === 0 ? (
                      <p className="text-muted-foreground">{t("student.noEnrollments")}</p>
                    ) : (
                      <ul className="space-y-2">
                        {record.enrollments.map((enrollment) => (
                          <li
                            key={enrollment.id}
                            className="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2.5"
                          >
                            <span>
                              <span className="font-medium">
                                {enrollment.school}
                                {enrollment.branch ? ` · ${enrollment.branch}` : ""}
                              </span>{" "}
                              <span className="text-muted-foreground">
                                {enrollment.grade_level}
                                {enrollment.section ? ` — ${enrollment.section}` : ""}
                                {enrollment.academic_year ? ` · ${enrollment.academic_year}` : ""}
                              </span>
                            </span>
                            <Badge variant={enrollment.status === "active" ? "default" : "secondary"}>
                              {enrollment.status}
                            </Badge>
                          </li>
                        ))}
                      </ul>
                    )}
                  </CardContent>
                </Card>

                <button
                  type="button"
                  onClick={() => router.push("/me/results")}
                  className="pressable flex w-full items-center gap-3 rounded-2xl border bg-card px-4 py-3.5 text-left shadow-xs hover:bg-accent/50"
                >
                  <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <FileBadge className="size-4 text-primary" />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block text-sm font-medium">{t("results.title")}</span>
                    <span className="block text-xs text-muted-foreground">
                      {t("results.subtitle")}
                    </span>
                  </span>
                  <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                </button>
              </>
            ))}
        </div>
      </div>

      <StudentAssignmentSheet
        assignmentId={openAssignment}
        open={openAssignment !== null}
        onOpenChange={(open) => {
          if (!open) setOpenAssignment(null)
        }}
        onChanged={() => setOpenAssignment(null)}
      />
    </div>
  )
}
