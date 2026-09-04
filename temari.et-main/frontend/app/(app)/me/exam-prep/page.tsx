"use client"

import { GraduationCap, Library, LibraryBig, Target, Trophy } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"

import { CourseShelf } from "@/components/lms/course-shelf"
import { StudentExamCard } from "@/components/lms/exam-card"
import { MaterialCard } from "@/components/lms/material-card"
import { formatDateTime } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { MeExam, MeMaterial, MyAttemptRow } from "@/lib/types"

const TABS = ["mocks", "courses", "library", "history"] as const

interface Facets {
  grade_levels: { id: number; name: string }[]
  subjects: { id: number; name: string }[]
  years_ec?: number[]
  exam_kinds?: string[]
}

/**
 * THE OPEN EXAM-PREP LANE (ADR-016): national mock exams, past papers and
 * the study library — for every signed-in user, school or no school.
 * Grade 6 / 8 / 12 national exam practice is the B2C hook.
 */
export default function ExamPrepPage() {
  const { t } = useTranslation("lms")
  const router = useRouter()
  const [tab, setTab] = useProfileTabs(TABS, "mocks")

  const [facets, setFacets] = useState<Facets>({ grade_levels: [], subjects: [] })
  const [gradeId, setGradeId] = useState("")
  const [subjectId, setSubjectId] = useState("")
  const [yearEc, setYearEc] = useState("")
  const [stream, setStream] = useState("")
  const [examKind, setExamKind] = useState("")
  const [mocks, setMocks] = useState<MeExam[] | null>(null)
  const [library, setLibrary] = useState<MeMaterial[] | null>(null)
  const [history, setHistory] = useState<MyAttemptRow[] | null>(null)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: Facets }>("/me/exam-prep/facets")
      .then((res) => !cancelled && setFacets(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while (re)loading
    setMocks(null)
    const params = new URLSearchParams({ per_page: "100" })
    if (gradeId) params.set("grade_level_id", gradeId)
    if (subjectId) params.set("subject_id", subjectId)
    if (yearEc) params.set("exam_year_ec", yearEc)
    if (stream) params.set("stream", stream)
    if (examKind) params.set("exam_kind", examKind)
    apiFetch<{ data: MeExam[] }>(`/me/exam-prep?${params}`)
      .then((res) => !cancelled && setMocks(res.data))
      .catch(() => !cancelled && setMocks([]))
    return () => {
      cancelled = true
    }
  }, [gradeId, subjectId, yearEc, stream, examKind])

  useEffect(() => {
    if (tab === "library" && library === null) {
      apiFetch<{ data: MeMaterial[] }>("/me/exam-prep/materials?per_page=100")
        .then((res) => setLibrary(res.data))
        .catch(() => setLibrary([]))
    }
    if (tab === "history" && history === null) {
      apiFetch<{ data: MyAttemptRow[] }>("/me/lms/attempts?per_page=100")
        .then((res) => setHistory(res.data.filter((attempt) => attempt.is_platform)))
        .catch(() => setHistory([]))
    }
  }, [tab, library, history])

  return (
    <div className="space-y-6">
      <PageHeader title={t("prep.title")} description={t("prep.subtitle")} />

      <div className="page-gutter">
        <div className="mx-auto space-y-5">
          {/* hero note: the free national-exam practice promise */}
          <div className="flex items-center gap-3 rounded-2xl border bg-primary/5 px-4 py-3">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
              <GraduationCap className="size-5 text-primary" strokeWidth={1.75} />
            </div>
            <p className="text-sm text-foreground">{t("prep.openToAll")}</p>
          </div>

          <ProfileTabBar
            tabs={[
              { key: "mocks" as const, label: t("prep.mocks"), icon: Target },
              { key: "courses" as const, label: t("prep.courses"), icon: LibraryBig },
              { key: "library" as const, label: t("prep.library"), icon: Library },
              { key: "history" as const, label: t("prep.history"), icon: Trophy },
            ]}
            value={tab}
            onChange={setTab}
          />

          {tab === "mocks" && (
            <>
              <div className="flex flex-wrap gap-2">
                <Select value={gradeId || "all"} onValueChange={(v) => setGradeId(v === "all" ? "" : v)}>
                  <SelectTrigger className="h-10 w-36 rounded-full">
                    <SelectValue placeholder={t("prep.grade")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{t("prep.allGrades")}</SelectItem>
                    {facets.grade_levels.map((grade) => (
                      <SelectItem key={grade.id} value={String(grade.id)}>
                        {grade.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Select value={subjectId || "all"} onValueChange={(v) => setSubjectId(v === "all" ? "" : v)}>
                  <SelectTrigger className="h-10 w-44 rounded-full">
                    <SelectValue placeholder={t("prep.subject")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{t("prep.allSubjects")}</SelectItem>
                    {facets.subjects.map((subject) => (
                      <SelectItem key={subject.id} value={String(subject.id)}>
                        {subject.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {(facets.years_ec?.length ?? 0) > 0 && (
                  <Select value={yearEc || "all"} onValueChange={(v) => setYearEc(v === "all" ? "" : v)}>
                    <SelectTrigger className="h-10 w-32 rounded-full">
                      <SelectValue placeholder={t("prep.year")} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{t("prep.allYears")}</SelectItem>
                      {(facets.years_ec ?? []).map((year) => (
                        <SelectItem key={year} value={String(year)}>
                          {year} {t("prep.ec")}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
                <Select value={stream || "all"} onValueChange={(v) => setStream(v === "all" ? "" : v)}>
                  <SelectTrigger className="h-10 w-36 rounded-full">
                    <SelectValue placeholder={t("courses.stream")} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{t("courses.streamAll")}</SelectItem>
                    <SelectItem value="natural">{t("courses.streamNatural")}</SelectItem>
                    <SelectItem value="social">{t("courses.streamSocial")}</SelectItem>
                  </SelectContent>
                </Select>
                {(facets.exam_kinds?.length ?? 0) > 0 && (
                  <Select value={examKind || "all"} onValueChange={(v) => setExamKind(v === "all" ? "" : v)}>
                    <SelectTrigger className="h-10 w-40 rounded-full">
                      <SelectValue placeholder={t("exams.examKind")} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">{t("prep.allKinds")}</SelectItem>
                      {(facets.exam_kinds ?? []).map((entry) => (
                        <SelectItem key={entry} value={entry}>
                          {t(`exams.examKinds.${entry}`)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </div>

              {mocks === null ? (
                <div className="grid gap-3 md:grid-cols-2">
                  {Array.from({ length: 4 }).map((_, index) => (
                    <Skeleton key={index} className="h-36 w-full rounded-2xl" />
                  ))}
                </div>
              ) : mocks.length === 0 ? (
                <EmptyState icon={Target} title={t("prep.empty")} description={t("prep.emptyDesc")} />
              ) : (
                <div className="grid gap-3 md:grid-cols-2">
                  {mocks.map((exam) => (
                    <StudentExamCard key={exam.id} exam={exam} />
                  ))}
                </div>
              )}
            </>
          )}

          {tab === "courses" && <CourseShelf />}

          {tab === "library" &&
            (library === null ? (
              <Skeleton className="h-64 w-full rounded-2xl" />
            ) : library.length === 0 ? (
              <EmptyState icon={Library} title={t("prep.empty")} description={t("prep.emptyDesc")} />
            ) : (
              <div className="grid items-start gap-3 md:grid-cols-2">
                {library.map((material) => (
                  <MaterialCard key={material.id} material={material} />
                ))}
              </div>
            ))}

          {tab === "history" &&
            (history === null ? (
              <Skeleton className="h-64 w-full rounded-2xl" />
            ) : history.length === 0 ? (
              <EmptyState
                icon={Trophy}
                title={t("prep.historyEmpty")}
                description={t("prep.historyEmptyDesc")}
              />
            ) : (
              <div className="space-y-2.5">
                {history.map((attempt) => (
                  <button
                    key={attempt.id}
                    type="button"
                    onClick={() => router.push(`/me/exam/${attempt.id}`)}
                    className="pressable flex w-full items-center gap-3 rounded-2xl border bg-card p-4 text-left shadow-xs transition-colors hover:bg-accent/50"
                  >
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{attempt.quiz_title}</p>
                      <p className="text-xs text-muted-foreground">
                        {[
                          attempt.grade_level_name,
                          attempt.subject_name,
                          formatDateTime(attempt.submitted_at),
                        ]
                          .filter(Boolean)
                          .join(" · ")}
                      </p>
                    </div>
                    {attempt.results_visible && attempt.score !== null ? (
                      <Badge
                        variant="outline"
                        className={`border-transparent tabular-nums ${
                          attempt.score / attempt.max_score >= 0.5
                            ? "bg-success/10 text-success"
                            : "bg-destructive/10 text-destructive"
                        }`}
                      >
                        {Number(attempt.score)} / {Number(attempt.max_score)}
                      </Badge>
                    ) : (
                      <Badge variant="secondary">{t(`attempts.statuses.${attempt.status}`)}</Badge>
                    )}
                  </button>
                ))}
              </div>
            ))}
        </div>
      </div>
    </div>
  )
}
