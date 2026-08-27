"use client"

import { FileQuestion } from "lucide-react"
import { useCallback, useEffect, useState } from "react"

import { StudentExamCard } from "@/components/lms/exam-card"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import type { MeExam } from "@/lib/types"

/**
 * The student's class exams & quizzes register (ADR-012 relationship lane):
 * open sittings first, finished papers below.
 */
export default function MyExamsPage() {
  const { t } = useTranslation("lms")
  const { user } = useAuth()

  const [exams, setExams] = useState<MeExam[] | null>(null)

  const load = useCallback(() => {
    apiFetch<{ data: MeExam[] }>("/me/lms/exams?per_page=100")
      .then((res) => setExams(res.data))
      .catch(() => setExams((prev) => prev ?? []))
  }, [])

  useEffect(() => {
    if (!user?.is_student) return
    load()
    function onVisible() {
      if (document.visibilityState === "visible") load()
    }
    document.addEventListener("visibilitychange", onVisible)
    return () => document.removeEventListener("visibilitychange", onVisible)
  }, [user?.is_student, load])

  if (!user?.is_student) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("learn.exams")} description={t("learn.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={FileQuestion} title={t("learn.notStudent")} />
        </div>
      </div>
    )
  }

  const open = (exams ?? []).filter((exam) => exam.can_start || exam.live_attempt_id)
  const rest = (exams ?? []).filter((exam) => !exam.can_start && !exam.live_attempt_id)

  return (
    <div className="space-y-6">
      <PageHeader title={t("learn.exams")} description={t("learn.examsSubtitle")} />

      <div className="page-gutter">
        <div className="mx-auto space-y-6">
          {exams === null ? (
            <div className="grid gap-3 md:grid-cols-2">
              <Skeleton className="h-36 w-full rounded-2xl" />
              <Skeleton className="h-36 w-full rounded-2xl" />
            </div>
          ) : exams.length === 0 ? (
            <EmptyState
              icon={FileQuestion}
              title={t("learn.emptyExams")}
              description={t("learn.emptyExamsDesc")}
            />
          ) : (
            <>
              {open.length > 0 && (
                <section className="space-y-2.5">
                  <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("learn.openExams")}
                  </h2>
                  <div className="grid gap-3 md:grid-cols-2">
                    {open.map((exam) => (
                      <StudentExamCard key={exam.id} exam={exam} />
                    ))}
                  </div>
                </section>
              )}
              {rest.length > 0 && (
                <section className="space-y-2.5">
                  <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {t("learn.pastExams")}
                  </h2>
                  <div className="grid gap-3 md:grid-cols-2">
                    {rest.map((exam) => (
                      <StudentExamCard key={exam.id} exam={exam} />
                    ))}
                  </div>
                </section>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
