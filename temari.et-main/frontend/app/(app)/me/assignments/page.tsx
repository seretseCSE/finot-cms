"use client"

import { BookOpenCheck } from "lucide-react"
import { useCallback, useEffect, useState } from "react"

import { AssignmentRow, isTurnedIn } from "@/components/me/assignment-row"
import { StudentAssignmentSheet } from "@/components/lms/student-assignment-sheet"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { MeAssignment } from "@/lib/types"

/**
 * The student's assignments register (ADR-012 relationship lane): open work
 * first, finished work below — reads /me/lms/* only, never staff endpoints.
 */
export default function MyAssignmentsPage() {
  const { t } = useTranslation("lms")
  const { user } = useAuth()

  const [assignments, setAssignments] = useState<MeAssignment[] | null>(null)
  const [openAssignment, setOpenAssignment] = useState<number | null>(null)

  const load = useCallback(() => {
    apiFetch<{ data: MeAssignment[] }>("/me/lms/assignments?per_page=100")
      .then((res) => setAssignments(res.data))
      .catch(() => setAssignments((prev) => prev ?? []))
  }, [])

  useEffect(() => {
    if (!user?.is_student) return
    load()
    // Coming back to the app (phone unlock, tab switch) refreshes the list —
    // freshly published work appears without a manual reload.
    function onVisible() {
      if (document.visibilityState === "visible") load()
    }
    document.addEventListener("visibilitychange", onVisible)
    return () => document.removeEventListener("visibilitychange", onVisible)
  }, [user?.is_student, load])

  if (!user?.is_student) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("learn.assignments")} description={t("learn.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={BookOpenCheck} title={t("learn.notStudent")} />
        </div>
      </div>
    )
  }

  const open = (assignments ?? []).filter((a) => a.status === "published" && !isTurnedIn(a))
  const done = (assignments ?? []).filter((a) => isTurnedIn(a))

  return (
    <div className="space-y-6">
      <PageHeader title={t("learn.assignments")} description={t("learn.assignmentsSubtitle")} />

      <div className="page-gutter">
        <div className="mx-auto space-y-6">
          {assignments === null ? (
            <div className="space-y-3">
              <Skeleton className="h-20 w-full rounded-2xl" />
              <Skeleton className="h-20 w-full rounded-2xl" />
              <Skeleton className="h-20 w-full rounded-2xl" />
            </div>
          ) : assignments.length === 0 ? (
            <EmptyState
              icon={BookOpenCheck}
              title={t("learn.emptyAssignments")}
              description={t("learn.emptyAssignmentsDesc")}
            />
          ) : (
            <>
              {open.length > 0 && (
                <section className="space-y-2.5">
                  <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("learn.toDo")}
                  </h2>
                  {open.map((assignment) => (
                    <AssignmentRow
                      key={assignment.id}
                      assignment={assignment}
                      onOpen={() => setOpenAssignment(assignment.id)}
                    />
                  ))}
                </section>
              )}
              {done.length > 0 && (
                <section className="space-y-2.5">
                  <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("learn.turnedIn")}
                  </h2>
                  {done.map((assignment) => (
                    <AssignmentRow
                      key={assignment.id}
                      assignment={assignment}
                      onOpen={() => setOpenAssignment(assignment.id)}
                    />
                  ))}
                </section>
              )}
            </>
          )}
        </div>
      </div>

      <StudentAssignmentSheet
        assignmentId={openAssignment}
        open={openAssignment !== null}
        onOpenChange={(isOpen) => !isOpen && setOpenAssignment(null)}
        onChanged={load}
      />
    </div>
  )
}
