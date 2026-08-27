"use client"

import {
  Award,
  CheckCircle2,
  ChevronDown,
  FileBadge,
  GraduationCap,
  ScrollText,
  TrendingUp,
} from "lucide-react"
import Link from "next/link"
import { useEffect, useMemo, useState } from "react"

import { ChildTabs, useChildren } from "@/components/me/child-tabs"
import { ResultsCard } from "@/components/me/results-card"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const TABS = ["marks", "reportCards", "transcript"] as const

interface TermOption {
  id: number
  name: string
  sequence: number
  is_current: boolean
  status: string
}

interface MarksAssessment {
  id: number
  type: string | null
  name: string
  max_score: number
  weight: number
  conducted_on: string | null
  score: number | null
  is_absent: boolean
}

interface MarksSubject {
  subject_assignment_id: number
  subject: { id: number; code: string | null; name: string }
  teacher: string | null
  marklist_status: "draft" | "submitted" | "approved"
  assessments: MarksAssessment[]
  assessed_weight: number
  weighted_total: number
}

interface ResultCardPayload {
  student_id: number
  term_id: number
  subjects: MarksSubject[]
  summary: {
    average: number | null
    rank: number | null
    rank_of: number | null
  } | null
}

interface TranscriptYear {
  academic_year_id: number
  academic_year: string | null
  grade_level: string | null
  school_name: string | null
  branch_name: string | null
  annual_average: number | null
  outcome: { decision: string; label: string; to_grade_level: string | null } | null
}

/**
 * THE RESULTS HUB (relationship lane, ADR-012): live continuous-assessment
 * marks as teachers record them, the frozen report cards, and the multi-year
 * transcript — one page, both hats. Parents flip children with the shared
 * switcher, gated per link by can_view_grades.
 */
export default function MyResultsPage() {
  const { t } = useTranslation("me")
  const { user } = useAuth()
  const [tab, setTab] = useProfileTabs(TABS, "marks")

  const isStudent = user?.is_student === true
  const isParent = user?.is_parent === true && !isStudent

  // ── Whose results? ──
  const { children, child, activeChild, setActiveChild } = useChildren(isParent)
  const [own, setOwn] = useState<{ student_id: number; terms: TermOption[] } | null>(null)

  useEffect(() => {
    if (!isStudent) return
    let cancelled = false
    apiFetch<{ data: { student_id: number; terms: TermOption[] | null } }>("/me/student")
      .then((res) => {
        if (!cancelled) setOwn({ student_id: res.data.student_id, terms: res.data.terms ?? [] })
      })
      .catch(() => !cancelled && setOwn(null))
    return () => {
      cancelled = true
    }
  }, [isStudent])

  const studentId = isStudent ? (own?.student_id ?? null) : (child?.student_id ?? null)
  const gradesAllowed = isStudent || child?.permissions.can_view_grades === true
  const terms: TermOption[] = useMemo(
    () => (isStudent ? (own?.terms ?? []) : (child?.current_enrollment?.terms ?? [])),
    [isStudent, own, child],
  )

  // ── Term selection (marks tab) ──
  const [termId, setTermId] = useState<number | null>(null)
  const defaultTerm = useMemo(
    () => terms.find((tm) => tm.is_current)?.id ?? terms[terms.length - 1]?.id ?? null,
    [terms],
  )
  const activeTermId = termId !== null && terms.some((tm) => tm.id === termId) ? termId : defaultTerm

  // ── Live marks ──
  const [marks, setMarks] = useState<ResultCardPayload | null>(null)
  const [marksLoading, setMarksLoading] = useState(false)
  const [openSubject, setOpenSubject] = useState<number | null>(null)

  useEffect(() => {
    if (studentId === null || activeTermId === null || !gradesAllowed || tab !== "marks") return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on term/child switch
    setMarksLoading(true)
    const base = isStudent ? "/me/student/result-card" : `/me/children/${studentId}/result-card`
    apiFetch<{ data: ResultCardPayload }>(`${base}?term_id=${activeTermId}`)
      .then((res) => {
        if (cancelled) return
        setMarks(res.data)
        setOpenSubject(null)
      })
      .catch(() => !cancelled && setMarks(null))
      .finally(() => !cancelled && setMarksLoading(false))
    return () => {
      cancelled = true
    }
  }, [studentId, activeTermId, gradesAllowed, tab, isStudent])

  // ── Transcript ──
  const [transcript, setTranscript] = useState<TranscriptYear[] | null>(null)

  useEffect(() => {
    if (studentId === null || !gradesAllowed || tab !== "transcript") return
    let cancelled = false
    const base = isStudent ? "/me/student/transcript" : `/me/children/${studentId}/transcript`
    apiFetch<{ data: { years: TranscriptYear[] } }>(base)
      .then((res) => !cancelled && setTranscript(res.data.years))
      .catch(() => !cancelled && setTranscript([]))
    return () => {
      cancelled = true
    }
  }, [studentId, gradesAllowed, tab, isStudent])

  if (!isStudent && !isParent) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("results.title")} description={t("results.subtitle")} />
        <div className="page-gutter">
          <EmptyState icon={FileBadge} title={t("results.emptyAccount")} />
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader title={t("results.title")} description={t("results.subtitle")}>
        {isParent && children && children.length > 1 ? (
          <ChildTabs items={children} activeChild={activeChild} onChange={setActiveChild} />
        ) : null}
      </PageHeader>

      <div className="page-gutter">
        <div className="mx-auto space-y-5">
          <ProfileTabBar
            tabs={TABS.map((key) => ({ key, label: t(`results.tabs.${key}`) }))}
            value={tab}
            onChange={setTab}
          />

          {!gradesAllowed && !isStudent && children !== null && children.length > 0 ? (
            <div className="rounded-2xl border bg-card shadow-xs">
              <EmptyState icon={FileBadge} title={t("results.noAccess")} compact />
            </div>
          ) : studentId === null ? (
            <div className="space-y-3">
              <Skeleton className="h-12 w-full rounded-2xl" />
              <Skeleton className="h-40 w-full rounded-2xl" />
            </div>
          ) : (
            <>
              {/* ── Live marks ── */}
              {tab === "marks" && (
                <>
                  {terms.length > 1 && (
                    <div className="scrollbar-none -mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                      {terms.map((term) => (
                        <button
                          key={term.id}
                          type="button"
                          onClick={() => setTermId(term.id)}
                          className={cn(
                            "h-9 shrink-0 rounded-full border px-4 text-sm font-medium transition-colors",
                            term.id === activeTermId
                              ? "border-primary bg-primary/10 text-primary"
                              : "bg-background text-muted-foreground hover:bg-muted",
                          )}
                        >
                          {term.name}
                        </button>
                      ))}
                    </div>
                  )}

                  {marksLoading || marks === null ? (
                    marksLoading ? (
                      <div className="space-y-3">
                        <Skeleton className="h-16 w-full rounded-2xl" />
                        <Skeleton className="h-16 w-full rounded-2xl" />
                        <Skeleton className="h-16 w-full rounded-2xl" />
                      </div>
                    ) : (
                      <EmptyState
                        icon={GraduationCap}
                        title={t("results.noMarks")}
                        description={t("results.noMarksDesc")}
                      />
                    )
                  ) : (
                    <>
                      {/* Summary strip — appears once the term is computed */}
                      {marks.summary !== null && marks.summary.average !== null && (
                        <div className="grid grid-cols-2 gap-3">
                          <div className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3 shadow-xs">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-primary/10">
                              <TrendingUp className="size-4 text-primary" />
                            </span>
                            <div className="min-w-0">
                              <p className="text-lg font-semibold tabular-nums leading-tight">
                                {marks.summary.average}
                              </p>
                              <p className="text-xs text-muted-foreground">
                                {t("results.average")}
                              </p>
                            </div>
                          </div>
                          <div className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3 shadow-xs">
                            <span className="flex size-9 items-center justify-center rounded-xl bg-primary/10">
                              <Award className="size-4 text-primary" />
                            </span>
                            <div className="min-w-0">
                              <p className="text-lg font-semibold tabular-nums leading-tight">
                                {marks.summary.rank ?? "—"}
                                {marks.summary.rank !== null && marks.summary.rank_of !== null && (
                                  <span className="text-xs font-normal text-muted-foreground">
                                    /{marks.summary.rank_of}
                                  </span>
                                )}
                              </p>
                              <p className="text-xs text-muted-foreground">{t("results.rank")}</p>
                            </div>
                          </div>
                        </div>
                      )}

                      {marks.subjects.length === 0 ? (
                        <EmptyState
                          icon={GraduationCap}
                          title={t("results.noMarks")}
                          description={t("results.noMarksDesc")}
                        />
                      ) : (
                        <div className="space-y-2.5">
                          {marks.subjects.map((subject) => {
                            const isOpen = openSubject === subject.subject_assignment_id
                            const marked = subject.assessments.filter(
                              (a) => a.score !== null || a.is_absent,
                            ).length
                            return (
                              <div
                                key={subject.subject_assignment_id}
                                className="overflow-hidden rounded-2xl border bg-card shadow-xs"
                              >
                                <button
                                  type="button"
                                  onClick={() =>
                                    setOpenSubject(isOpen ? null : subject.subject_assignment_id)
                                  }
                                  className="pressable flex w-full items-center gap-3 px-4 py-3.5 text-left"
                                  aria-expanded={isOpen}
                                >
                                  <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                      {subject.subject.name}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                      {[
                                        subject.teacher,
                                        t("results.assessedCount", {
                                          marked,
                                          total: subject.assessments.length,
                                        }),
                                      ]
                                        .filter(Boolean)
                                        .join(" · ")}
                                    </p>
                                  </div>
                                  {subject.marklist_status !== "draft" && (
                                    <Badge
                                      variant="outline"
                                      className="hidden gap-1 border-transparent bg-success/10 text-success sm:inline-flex"
                                    >
                                      <CheckCircle2 className="size-3" />
                                      {t("results.signedOff")}
                                    </Badge>
                                  )}
                                  <div className="shrink-0 text-right">
                                    <p className="text-base font-semibold tabular-nums">
                                      {marked > 0 ? subject.weighted_total : "—"}
                                      {marked > 0 && (
                                        <span className="text-xs font-normal text-muted-foreground">
                                          /{Math.round(subject.assessed_weight)}
                                        </span>
                                      )}
                                    </p>
                                  </div>
                                  <ChevronDown
                                    className={cn(
                                      "size-4 shrink-0 text-muted-foreground transition-transform",
                                      isOpen && "rotate-180",
                                    )}
                                  />
                                </button>

                                {isOpen && (
                                  <div className="border-t">
                                    {subject.assessments.length === 0 ? (
                                      <p className="px-4 py-3 text-sm text-muted-foreground">
                                        {t("results.noAssessments")}
                                      </p>
                                    ) : (
                                      <ul className="divide-y">
                                        {subject.assessments.map((assessment) => (
                                          <li
                                            key={assessment.id}
                                            className="flex items-center gap-3 px-4 py-2.5"
                                          >
                                            <div className="min-w-0 flex-1">
                                              <p className="truncate text-sm">{assessment.name}</p>
                                              <p className="text-xs text-muted-foreground">
                                                {[
                                                  assessment.type,
                                                  assessment.conducted_on,
                                                  t("results.weight", {
                                                    weight: assessment.weight,
                                                  }),
                                                ]
                                                  .filter(Boolean)
                                                  .join(" · ")}
                                              </p>
                                            </div>
                                            {assessment.is_absent ? (
                                              <Badge
                                                variant="outline"
                                                className="border-transparent bg-warning/10 text-warning"
                                              >
                                                {t("results.absent")}
                                              </Badge>
                                            ) : assessment.score === null ? (
                                              <span className="text-sm text-muted-foreground">
                                                —
                                              </span>
                                            ) : (
                                              <span className="text-sm font-semibold tabular-nums">
                                                {assessment.score}
                                                <span className="font-normal text-muted-foreground">
                                                  /{assessment.max_score}
                                                </span>
                                              </span>
                                            )}
                                          </li>
                                        ))}
                                      </ul>
                                    )}
                                  </div>
                                )}
                              </div>
                            )
                          })}
                        </div>
                      )}
                    </>
                  )}
                </>
              )}

              {/* ── Frozen report cards ── */}
              {tab === "reportCards" && (
                <ResultsCard
                  indexUrl={
                    isStudent ? "/me/student/report-cards" : `/me/children/${studentId}/report-cards`
                  }
                  studentId={studentId}
                />
              )}

              {/* ── Transcript ── */}
              {tab === "transcript" &&
                (transcript === null ? (
                  <Skeleton className="h-48 w-full rounded-2xl" />
                ) : transcript.length === 0 ? (
                  <EmptyState
                    icon={ScrollText}
                    title={t("results.noTranscript")}
                    description={t("results.noTranscriptDesc")}
                  />
                ) : (
                  <div className="space-y-3">
                    <div className="space-y-2.5">
                      {transcript.map((year) => (
                        <div
                          key={year.academic_year_id}
                          className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3.5 shadow-xs"
                        >
                          <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                            <ScrollText className="size-4 text-primary" />
                          </span>
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">
                              {year.academic_year}
                              {year.grade_level ? ` · ${year.grade_level}` : ""}
                            </p>
                            <p className="truncate text-xs text-muted-foreground">
                              {[year.school_name, year.outcome?.label].filter(Boolean).join(" · ")}
                            </p>
                          </div>
                          <div className="shrink-0 text-right">
                            <p className="text-base font-semibold tabular-nums">
                              {year.annual_average ?? "—"}
                            </p>
                            <p className="text-xs text-muted-foreground">
                              {t("results.yearAverage")}
                            </p>
                          </div>
                        </div>
                      ))}
                    </div>
                    <Button variant="outline" className="w-full" asChild>
                      <Link href={`/print/transcript/${studentId}`} target="_blank">
                        <ScrollText className="size-4" />
                        {t("results.openTranscript")}
                      </Link>
                    </Button>
                  </div>
                ))}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
