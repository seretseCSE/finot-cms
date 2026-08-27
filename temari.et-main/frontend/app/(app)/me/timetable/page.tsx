"use client"

import { CalendarClock, Users } from "lucide-react"
import { useEffect, useState } from "react"

import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { WeeklyGridReadOnly } from "@/components/timetable/weekly-grid"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { StudentTimetable } from "@/lib/types"

interface TeacherRow {
  subject: string | null
  subject_code: string | null
  teacher: string | null
  photo_url: string | null
}

/**
 * The family timetable page (relationship lane): a student sees their own
 * published week; a parent flips between children with the shared child
 * switcher. One page, both hats.
 */
export default function MyTimetablePage() {
  const { t } = useTranslation("me")
  const { t: tt } = useTranslation("timetable")
  const { user } = useAuth()

  const isStudent = user?.is_student === true
  const isParent = user?.is_parent === true && !isStudent

  const { children, child, activeChild, setActiveChild } = useChildren(isParent)
  const childId = child?.student_id ?? null

  const [data, setData] = useState<StudentTimetable | null | undefined>(undefined)
  const [teachers, setTeachers] = useState<TeacherRow[] | null>(null)

  useEffect(() => {
    if (!isStudent && childId === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on child switch
    setData(undefined)
    const url = isStudent ? "/me/student/timetable" : `/me/children/${childId}/timetable`
    apiFetch<{ data: StudentTimetable | null }>(url)
      .then((res) => !cancelled && setData(res.data))
      .catch(() => !cancelled && setData(null))
    const teachersUrl = isStudent ? "/me/student/teachers" : `/me/children/${childId}/teachers`
    apiFetch<{ data: TeacherRow[] }>(teachersUrl)
      .then((res) => !cancelled && setTeachers(res.data))
      .catch(() => !cancelled && setTeachers([]))
    return () => {
      cancelled = true
    }
  }, [isStudent, childId])

  const empty = !isStudent && !isParent

  return (
    <div className="space-y-6">
      <PageHeader title={t("timetable.title")} description={data?.term_name ?? undefined}>
        {isParent && children && children.length > 1 ? (
          <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
        ) : null}
      </PageHeader>

      <div className="page-gutter space-y-6">
        {empty ? (
          <EmptyState icon={CalendarClock} title={t("timetable.empty")} />
        ) : data === undefined ? (
          <Skeleton className="h-96 rounded-2xl" />
        ) : data === null ? (
          <div className="rounded-2xl border bg-card shadow-xs">
            <EmptyState icon={CalendarClock} title={tt("mine.empty")} compact />
          </div>
        ) : (
          <WeeklyGridReadOnly data={data} />
        )}

        {/* ── Subject teachers — who teaches this class ── */}
        {!empty && teachers !== null && teachers.length > 0 && (
          <section className="space-y-2">
            <h2 className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              <Users className="size-3.5" />
              {t("timetable.teachers")}
            </h2>
            <div className="grid gap-2 sm:grid-cols-2">
              {teachers.map((row, index) => (
                <div
                  key={`${row.subject}-${index}`}
                  className="flex items-center gap-3 rounded-2xl border bg-card px-3.5 py-2.5 shadow-xs"
                >
                  <PersonAvatar
                    name={row.teacher ?? "?"}
                    photoUrl={row.photo_url}
                    className="size-9 text-[10px]"
                  />
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium">
                      {row.teacher ?? t("timetable.unassigned")}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">{row.subject}</p>
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  )
}
