"use client"

import { BookOpenCheck, EyeOff } from "lucide-react"
import { useEffect, useState } from "react"

import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { formatDateTime } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"

interface ChildLms {
  can_view_grades: boolean
  assignments: {
    id: number
    title: string
    subject_name: string | null
    due_at: string | null
    status: string
    submission_status: string | null
    is_late: boolean
    score: number | null
    max_score: number | null
  }[]
  exams: {
    quiz_title: string | null
    kind: string
    subject_name: string | null
    submitted_at: string | null
    score: number | null
    max_score: number
  }[]
}

/**
 * The parent's window into a child's classwork (ADR-012): homework status
 * and exam results, score visibility gated per guardian link.
 */
export default function ChildLearningPage() {
  const { t } = useTranslation("lms")
  const { user } = useAuth()
  const { children, activeChild, setActiveChild } = useChildren(user?.is_parent === true)

  const [data, setData] = useState<ChildLms | null>(null)

  useEffect(() => {
    if (activeChild === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setData(null)
    apiFetch<{ data: ChildLms }>(`/me/children/${activeChild}/lms`)
      .then((res) => !cancelled && setData(res.data))
      .catch(() => !cancelled && setData({ can_view_grades: false, assignments: [], exams: [] }))
    return () => {
      cancelled = true
    }
  }, [activeChild])

  if (!user?.is_parent) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("child.title")} />
        <div className="page-gutter">
          <EmptyState icon={BookOpenCheck} title={t("child.empty")} />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("child.title")} description={t("learn.subtitle")} />

      <div className="page-gutter">
        <div className="mx-auto space-y-5">
          {children !== null && (
            <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
          )}

          {data === null ? (
            <div className="space-y-4">
              <Skeleton className="h-56 w-full rounded-2xl" />
              <Skeleton className="h-56 w-full rounded-2xl" />
            </div>
          ) : data.assignments.length === 0 && data.exams.length === 0 ? (
            <EmptyState icon={BookOpenCheck} title={t("child.empty")} description={t("child.emptyDesc")} />
          ) : (
            <>
              {!data.can_view_grades && (
                <p className="flex items-center gap-2 rounded-xl bg-muted/60 px-3.5 py-2.5 text-xs text-muted-foreground">
                  <EyeOff className="size-3.5" /> {t("child.noGrades")}
                </p>
              )}

              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("child.assignments")}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                  {data.assignments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t("learn.emptyAssignments")}</p>
                  ) : (
                    data.assignments.map((assignment) => (
                      <div
                        key={assignment.id}
                        className="flex items-center gap-3 rounded-xl border px-3.5 py-2.5"
                      >
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">{assignment.title}</p>
                          <p className="text-xs text-muted-foreground">
                            {[
                              assignment.subject_name,
                              assignment.due_at
                                ? t("learn.due", { date: formatDateTime(assignment.due_at) })
                                : null,
                            ]
                              .filter(Boolean)
                              .join(" · ")}
                          </p>
                        </div>
                        {assignment.submission_status === null ? (
                          <Badge variant="outline" className="border-transparent bg-warning/10 text-warning">
                            {t("assignments.notSubmitted")}
                          </Badge>
                        ) : assignment.score !== null ? (
                          <Badge variant="outline" className="border-transparent bg-success/10 text-success tabular-nums">
                            {Number(assignment.score)}
                            {assignment.max_score !== null ? `/${Number(assignment.max_score)}` : ""}
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="border-transparent bg-info/10 text-info">
                            {t("attempts.statuses.submitted")}
                          </Badge>
                        )}
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="text-base">{t("child.exams")}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                  {data.exams.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{t("learn.emptyExams")}</p>
                  ) : (
                    data.exams.map((exam, index) => (
                      <div key={index} className="flex items-center gap-3 rounded-xl border px-3.5 py-2.5">
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium">{exam.quiz_title}</p>
                          <p className="text-xs text-muted-foreground">
                            {[exam.subject_name, formatDateTime(exam.submitted_at)]
                              .filter(Boolean)
                              .join(" · ")}
                          </p>
                        </div>
                        {exam.score !== null ? (
                          <Badge variant="outline" className="border-transparent bg-success/10 text-success tabular-nums">
                            {Number(exam.score)} / {Number(exam.max_score)}
                          </Badge>
                        ) : (
                          <Badge variant="secondary">{t(`exams.kinds.${exam.kind}`)}</Badge>
                        )}
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
