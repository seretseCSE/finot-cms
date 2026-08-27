"use client"

import { BookOpenText, ChevronDown, Map } from "lucide-react"
import { useEffect, useState } from "react"

import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { CoverageBadge, fmtDate, ProgressBar } from "@/components/lesson-plans/shared"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useLocale, useTranslation } from "@/lib/i18n"
import type { FamilyLessonPlans } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The family class-plan page (relationship lane): per subject — the approved
 * syllabus roadmap with progress, and this week's approved topics. One page,
 * both hats; parents flip children with the shared switcher. Approved-only:
 * drafts and the staff review trail never reach here.
 */
export default function MyClassPlanPage() {
  const { t } = useTranslation("lessonPlans")
  const { user } = useAuth()
  const { locale } = useLocale()

  const isStudent = user?.is_student === true
  const isParent = user?.is_parent === true && !isStudent

  const { children, child, activeChild, setActiveChild } = useChildren(isParent)
  const childId = child?.student_id ?? null

  const [data, setData] = useState<FamilyLessonPlans | null | undefined>(undefined)
  const [openSubject, setOpenSubject] = useState<number | null>(null)

  useEffect(() => {
    if (!isStudent && childId === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on child switch
    setData(undefined)
    const url = isStudent ? "/me/student/lesson-plans" : `/me/children/${childId}/lesson-plans`
    apiFetch<{ data: FamilyLessonPlans }>(url)
      .then((res) => !cancelled && setData(res.data))
      .catch(() => !cancelled && setData(null))
    return () => {
      cancelled = true
    }
  }, [isStudent, childId])

  const empty = !isStudent && !isParent
  const subjects = data?.subjects ?? []

  return (
    <div className="space-y-6">
      <PageHeader title={t("family.title")} description={t("family.description")}>
        {isParent && children && children.length > 1 ? (
          <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
        ) : null}
      </PageHeader>

      <div className="page-gutter space-y-4">
        {empty ? (
          <EmptyState icon={Map} title={t("family.empty")} />
        ) : data === undefined ? (
          <>
            <Skeleton className="h-32 w-full rounded-2xl" />
            <Skeleton className="h-32 w-full rounded-2xl" />
            <Skeleton className="h-32 w-full rounded-2xl" />
          </>
        ) : data === null || subjects.length === 0 ? (
          <div className="rounded-2xl border bg-card shadow-xs">
            <EmptyState
              icon={Map}
              title={t("family.empty")}
              description={t("family.emptyHint")}
              compact
            />
          </div>
        ) : (
          subjects.map((subject, index) => {
            const expanded = openSubject === index
            return (
              <div key={index} className="bg-card overflow-hidden rounded-2xl border shadow-xs">
                {/* ── Subject header: teacher + progress ── */}
                <button
                  type="button"
                  className="touch-target hover:bg-accent/40 flex w-full items-center gap-3 px-4 py-3.5 text-left transition-colors"
                  onClick={() => setOpenSubject(expanded ? null : index)}
                  aria-expanded={expanded}
                >
                  <div className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                    <BookOpenText className="size-5" strokeWidth={1.75} />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{subject.subject.name}</p>
                    <p className="text-muted-foreground truncate text-xs">
                      {subject.teacher_name ?? "—"}
                    </p>
                    {subject.has_plan && subject.progress_percent !== null && (
                      <div className="mt-1.5 flex items-center gap-2">
                        <ProgressBar percent={subject.progress_percent} className="max-w-40" />
                        <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                          {t("family.progress", { percent: String(subject.progress_percent) })}
                        </span>
                      </div>
                    )}
                  </div>
                  <ChevronDown
                    className={cn(
                      "text-muted-foreground size-4 shrink-0 transition-transform duration-200",
                      expanded && "rotate-180",
                    )}
                  />
                </button>

                {expanded && (
                  <div className="space-y-4 border-t px-4 py-4">
                    {!subject.has_plan ? (
                      <p className="text-muted-foreground text-sm">{t("family.noPlan")}</p>
                    ) : (
                      <>
                        {/* ── This week ── */}
                        <div className="space-y-2">
                          <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            {t("family.thisWeek")}
                          </h3>
                          {subject.current_week === null ? (
                            <p className="text-muted-foreground text-sm">{t("family.noWeek")}</p>
                          ) : (
                            <ul className="space-y-2">
                              {subject.current_week.lessons.map((lesson, i) => (
                                <li
                                  key={i}
                                  className="bg-muted/40 flex items-start justify-between gap-2 rounded-xl px-3 py-2.5"
                                >
                                  <div className="min-w-0">
                                    <p className="text-muted-foreground text-[11px] font-semibold uppercase tracking-wide">
                                      {t(`days.${lesson.day_of_week}`)}
                                    </p>
                                    <p className="text-sm font-medium">{lesson.topic}</p>
                                    {lesson.homework && (
                                      <p className="text-muted-foreground mt-0.5 text-xs">
                                        {t("family.homework")}: {lesson.homework}
                                      </p>
                                    )}
                                  </div>
                                  <CoverageBadge coverage={lesson.coverage} />
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>

                        {/* ── Syllabus roadmap ── */}
                        {subject.units !== null && subject.units.length > 0 && (
                          <div className="space-y-2">
                            <div className="flex items-center justify-between">
                              <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {t("family.syllabus")}
                              </h3>
                              {subject.units_total !== null && subject.units_done !== null && (
                                <span className="text-muted-foreground text-xs tabular-nums">
                                  {t("family.unitsDone", {
                                    done: String(subject.units_done),
                                    total: String(subject.units_total),
                                  })}
                                </span>
                              )}
                            </div>
                            <ol className="space-y-1.5">
                              {subject.units.map((unit) => (
                                <li key={unit.sequence} className="flex items-center gap-2.5">
                                  <span
                                    className={cn(
                                      "flex size-6 shrink-0 items-center justify-center rounded-full border text-[11px] font-semibold tabular-nums",
                                      unit.is_past
                                        ? "border-success/30 bg-success/10 text-success"
                                        : unit.is_current
                                          ? "border-primary bg-primary text-primary-foreground"
                                          : "text-muted-foreground",
                                    )}
                                  >
                                    {unit.sequence}
                                  </span>
                                  <div className="min-w-0 flex-1">
                                    <p
                                      className={cn(
                                        "truncate text-sm",
                                        unit.is_current ? "font-medium" : undefined,
                                      )}
                                    >
                                      {unit.title}
                                      {unit.is_current && (
                                        <span className="bg-primary/10 text-primary ml-2 rounded-full px-1.5 py-0.5 text-[10px] font-medium">
                                          {t("family.currentUnit")}
                                        </span>
                                      )}
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">
                                      {fmtDate(unit.starts_on, locale)} – {fmtDate(unit.ends_on, locale)}
                                    </p>
                                  </div>
                                </li>
                              ))}
                            </ol>
                          </div>
                        )}
                      </>
                    )}
                  </div>
                )}
              </div>
            )
          })
        )}
      </div>
    </div>
  )
}
