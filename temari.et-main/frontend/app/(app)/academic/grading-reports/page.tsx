"use client"

import {
  AlertTriangle,
  Award,
  BarChart3,
  CalendarX,
  CheckCircle2,
  ClipboardCheck,
  Download,
  Percent,
  SlidersHorizontal,
  Trophy,
  Users,
} from "lucide-react"
import dynamic from "next/dynamic"
import Link from "next/link"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { BranchScopePicker } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { PageHeader } from "@/components/ui/page-header"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { TermSelect } from "@/components/academic/term-select"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { GradingReport, Paginated, Section, Term } from "@/lib/types"
import { cn } from "@/lib/utils"

// Charts are heavy (recharts) and below the fold — load on demand.
const GradingReportCharts = dynamic(
  () => import("@/components/grading/grading-report-charts").then((m) => m.GradingReportCharts),
  { ssr: false, loading: () => <Skeleton className="h-72 rounded-2xl" /> },
)
// The analysis tab loads only when opened.
const MarklistAnalysisPanel = dynamic(
  () => import("@/components/grading/marklist-analysis").then((m) => m.MarklistAnalysisPanel),
  { ssr: false, loading: () => <Skeleton className="h-96 rounded-2xl" /> },
)
// The submission monitor loads only when opened.
const MarklistSubmissionsPanel = dynamic(
  () =>
    import("@/components/grading/marklist-submissions").then((m) => m.MarklistSubmissionsPanel),
  { ssr: false, loading: () => <Skeleton className="h-96 rounded-2xl" /> },
)

const ALL = "all"

/** "+2.4" / "−1.3" — a signed delta for the trend hints. */
function signed(delta: number): string {
  const rounded = Math.round(delta * 10) / 10
  return rounded >= 0 ? `+${rounded}` : `−${Math.abs(rounded)}`
}

/** The aggregate rows flattened into an exportable CSV. */
function reportCsv(report: GradingReport): string {
  const esc = (v: string | number | null) => `"${String(v ?? "").replaceAll('"', '""')}"`
  const lines: string[] = []
  lines.push("Subjects")
  lines.push("Subject,Students,Average,Pass rate %")
  for (const s of report.subjects) {
    lines.push([esc(s.name), s.students, s.average, s.pass_rate].join(","))
  }
  lines.push("")
  lines.push("Sections")
  lines.push("Section,Students,Average,Pass rate %")
  for (const s of report.sections) {
    lines.push([esc(s.name), s.students, s.average, s.pass_rate].join(","))
  }
  lines.push("")
  lines.push("Grade bands")
  lines.push("Band,Letter,Passing,Students")
  for (const b of report.bands) {
    lines.push([esc(b.label), esc(b.letter), b.is_passing ? "yes" : "no", b.count].join(","))
  }
  lines.push("")
  lines.push("Gender")
  lines.push("Gender,Students,Average,Pass rate %")
  for (const g of report.gender) {
    lines.push([esc(g.gender), g.students, g.average, g.pass_rate].join(","))
  }
  lines.push("")
  lines.push("Needs attention")
  lines.push("Student,Section,Average,Absence days")
  for (const s of report.at_risk) {
    lines.push([esc(s.full_name), esc(s.section), s.average, s.absence_days ?? ""].join(","))
  }
  return lines.join("\n")
}

export default function GradingReportsPage() {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()

  const [tab, setTab] = useProfileTabs(["overview", "analysis", "submissions"] as const, "overview")
  const [branchFilter, setBranchFilter] = useState<number | null>(null)
  const [terms, setTerms] = useState<Term[]>([])
  const [termId, setTermId] = useState<string>("")
  const [sections, setSections] = useState<Section[]>([])
  const [gradeId, setGradeId] = useState<string>(ALL)
  const [sectionId, setSectionId] = useState<string>(ALL)
  const [report, setReport] = useState<GradingReport | null>(null)

  const hasWorkspace = !isPlatform && active.schoolId !== null
  const needsBranchPick = hasWorkspace && active.branchId === null
  // Sections + terms are branch-scoped; school-wide managers pick a branch first.
  const scopeReady = hasWorkspace && (active.branchId !== null || branchFilter !== null)
  const branchParam = needsBranchPick && branchFilter !== null ? `branch_id=${branchFilter}` : ""

  useEffect(() => {
    if (!scopeReady) return
    let cancelled = false
    const suffix = branchParam ? `&${branchParam}` : ""
    apiFetch<Paginated<Term>>(`/terms?per_page=100${suffix}`)
      .then((res) => {
        if (cancelled) return
        setTerms(res.data)
        const current = res.data.find((x) => x.status === "active")?.id ?? res.data[0]?.id
        if (current) setTermId((prev) => prev || String(current))
      })
      .catch(() => setTerms([]))
    apiFetch<Paginated<Section>>(`/sections?per_page=100${suffix}`)
      .then((res) => !cancelled && setSections(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- branchParam derives from branchFilter
  }, [scopeReady, active.branchId, branchFilter])

  // The grade picker narrows both the report AND the section options.
  const gradeOptions = useMemo(() => {
    const seen = new Map<number, { id: number; name: string; sort: number }>()
    for (const section of sections) {
      const grade = section.grade_level
      if (grade && !seen.has(grade.id)) {
        seen.set(grade.id, { id: grade.id, name: grade.name, sort: grade.sort_order ?? 0 })
      }
    }
    return [...seen.values()].sort((a, b) => a.sort - b.sort)
  }, [sections])

  const sectionOptions = useMemo(
    () =>
      gradeId === ALL
        ? sections
        : sections.filter((s) => s.grade_level?.id === Number(gradeId)),
    [sections, gradeId],
  )

  // Grade change invalidates a section picked under another grade.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear the dependent filter
    setSectionId((prev) =>
      prev !== ALL && !sectionOptions.some((s) => String(s.id) === prev) ? ALL : prev,
    )
  }, [sectionOptions])

  useEffect(() => {
    if (!scopeReady || !termId) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new query
    setReport(null)
    const params = new URLSearchParams()
    if (sectionId !== ALL) params.set("section_id", sectionId)
    else if (gradeId !== ALL) params.set("grade_level_id", gradeId)
    const qs = params.size > 0 ? `?${params}` : ""
    apiFetch<{ data: GradingReport }>(`/terms/${termId}/grading-report${qs}`)
      .then((res) => !cancelled && setReport(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [scopeReady, active.branchId, termId, gradeId, sectionId])

  function exportCsv() {
    if (!report) return
    const blob = new Blob([reportCsv(report)], { type: "text/csv;charset=utf-8" })
    const url = URL.createObjectURL(blob)
    const link = document.createElement("a")
    link.href = url
    link.download = `grading-report-${report.term.name.replaceAll(" ", "-").toLowerCase()}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  // ── Derived insights (all client-side over the one payload) ──────────────
  const avgDelta =
    report?.previous?.average != null && report.totals.average !== null
      ? report.totals.average - report.previous.average
      : null
  const passDelta =
    report?.previous?.pass_rate != null && report.totals.pass_rate !== null
      ? report.totals.pass_rate - report.previous.pass_rate
      : null
  const bestSection =
    report && report.sections.length > 1
      ? [...report.sections].sort((a, b) => b.average - a.average)[0]
      : null
  const toughestSubject =
    report && report.subjects.length > 1
      ? [...report.subjects].sort((a, b) => a.average - b.average)[0]
      : null
  const male = report?.gender.find((g) => g.gender === "male")
  const female = report?.gender.find((g) => g.gender === "female")
  const highlights = [
    bestSection && {
      key: "bestSection",
      icon: Trophy,
      label: t("reports.bestSection"),
      value: bestSection.name,
      sub: `${t("reportCards.average")} ${bestSection.average}`,
    },
    toughestSubject && {
      key: "toughestSubject",
      icon: AlertTriangle,
      label: t("reports.toughestSubject"),
      value: toughestSubject.name,
      sub: `${t("reportCards.average")} ${toughestSubject.average} · ${t("reports.passRate")} ${toughestSubject.pass_rate}%`,
    },
    male &&
      female && {
        key: "boysGirlsPass",
        icon: Users,
        label: t("reports.boysGirlsPass"),
        value: `${male.pass_rate ?? "—"}% / ${female.pass_rate ?? "—"}%`,
        sub: `${male.students} / ${female.students}`,
      },
    report?.totals.avg_absence_days != null && {
      key: "avgAbsence",
      icon: CalendarX,
      label: t("reports.avgAbsence"),
      value: report.totals.avg_absence_days,
      sub: report.term.name,
    },
  ].filter((h): h is Exclude<typeof h, false | null | undefined> => Boolean(h))

  return (
    <div className="space-y-6 pb-10">
      <PageHeader
        title={t("reports.title")}
        description={t("reports.subtitle")}
        actions={
          hasWorkspace ? (
            <div className="flex flex-wrap items-center gap-2">
              {needsBranchPick && (
                <BranchScopePicker value={branchFilter} onChange={setBranchFilter} />
              )}
              <TermSelect
                terms={terms}
                value={termId}
                onValueChange={setTermId}
                placeholder={t("reports.term")}
                aria-label={t("reports.term")}
                className="h-9 w-full md:w-52"
              />
              <Select value={gradeId} onValueChange={setGradeId}>
                <SelectTrigger className="h-9 w-[calc(50%-0.25rem)] md:w-36" aria-label={t("reports.grade")}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>{t("reports.allGrades")}</SelectItem>
                  {gradeOptions.map((grade) => (
                    <SelectItem key={grade.id} value={String(grade.id)}>
                      {grade.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={sectionId} onValueChange={setSectionId}>
                <SelectTrigger className="h-9 w-[calc(50%-0.25rem)] md:w-36" aria-label={t("reportCards.section")}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>{t("reportCards.allSections")}</SelectItem>
                  {sectionOptions.map((section) => (
                    <SelectItem key={section.id} value={String(section.id)}>
                      {gradeId === ALL
                        ? `${section.grade_level?.name ?? ""} ${section.name}`
                        : section.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {tab === "overview" && (
                <Button variant="outline" size="sm" onClick={exportCsv} disabled={!report}>
                  <Download className="size-4" />
                  {t("reports.export")}
                </Button>
              )}
            </div>
          ) : undefined
        }
      />

      {!hasWorkspace || (needsBranchPick && branchFilter === null) ? (
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("noBranch")}
          </div>
        </div>
      ) : (
        <>
          <div className="page-gutter">
            <ProfileTabBar
              tabs={[
                { key: "overview" as const, label: t("reports.tabs.overview"), icon: BarChart3 },
                {
                  key: "analysis" as const,
                  label: t("reports.tabs.analysis"),
                  icon: SlidersHorizontal,
                },
                {
                  key: "submissions" as const,
                  label: t("reports.tabs.submissions"),
                  icon: ClipboardCheck,
                },
              ]}
              value={tab}
              onChange={setTab}
            />
          </div>

          {tab === "submissions" ? (
            <div className="page-gutter">
              <MarklistSubmissionsPanel termId={termId} gradeId={gradeId} sectionId={sectionId} />
            </div>
          ) : tab === "analysis" ? (
            <div className="page-gutter">
              <MarklistAnalysisPanel
                termId={termId}
                gradeId={gradeId}
                sectionId={sectionId}
                subjects={report?.subjects ?? []}
              />
            </div>
          ) : (
            <>
          {/* Headline stats — the trend hints compare against the previous semester. */}
          <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
            {report === null ? (
              [0, 1, 2, 3].map((i) => <Skeleton key={i} className="h-24 rounded-2xl" />)
            ) : (
              <>
                <StatCard
                  label={t("reports.students")}
                  value={report.totals.students}
                  icon={Users}
                  hint={t("reports.withResults", { count: report.totals.with_results })}
                />
                <StatCard
                  label={t("reportCards.average")}
                  value={report.totals.average ?? "—"}
                  icon={Award}
                  hint={
                    avgDelta !== null && report.previous
                      ? t("reports.vsPrevious", {
                          delta: signed(avgDelta),
                          term: report.previous.term.name,
                        })
                      : undefined
                  }
                />
                <StatCard
                  label={t("reports.passRate")}
                  value={report.totals.pass_rate !== null ? `${report.totals.pass_rate}%` : "—"}
                  icon={Percent}
                  hint={
                    passDelta !== null && report.previous
                      ? t("reports.vsPrevious", {
                          delta: `${signed(passDelta)}%`,
                          term: report.previous.term.name,
                        })
                      : undefined
                  }
                />
                <StatCard
                  label={t("reports.approvedMarklists")}
                  value={`${report.marklists.approved}/${report.marklists.total}`}
                  icon={CheckCircle2}
                  hint={t("reports.approvedOfTotal", {
                    approved: report.marklists.approved,
                    total: report.marklists.total,
                  })}
                />
              </>
            )}
          </div>

          {/* Highlights — the facts a director asks for first. */}
          {report !== null && highlights.length > 0 && (
            <div className="page-gutter">
              <div className="bg-card grid grid-cols-2 divide-y rounded-2xl border shadow-xs sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                {highlights.map(({ key, icon: Icon, label, value, sub }) => (
                  <div key={key} className="flex items-start gap-3 p-4">
                    <span className="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-xl">
                      <Icon className="size-4" strokeWidth={1.75} />
                    </span>
                    <div className="min-w-0">
                      <p className="text-muted-foreground truncate text-[11px] font-medium uppercase tracking-wide">
                        {label}
                      </p>
                      <p className="truncate text-sm font-semibold">{value}</p>
                      <p className="text-muted-foreground truncate text-xs">{sub}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="page-gutter">
            <GradingReportCharts report={report} />
          </div>

          {/* Top students + needs attention, side by side. */}
          <div className="page-gutter grid gap-4 lg:grid-cols-2">
            <section className="bg-card rounded-2xl border p-4 shadow-xs">
              <h2 className="font-display text-base font-semibold">{t("reports.topStudents")}</h2>
              <p className="text-muted-foreground mt-0.5 text-xs">{t("reports.topStudentsHint")}</p>
              {report === null ? (
                <Skeleton className="mt-4 h-40 rounded-xl" />
              ) : report.top_students.length === 0 ? (
                <p className="text-muted-foreground flex h-32 items-center justify-center text-sm">
                  {t("reports.noData")}
                </p>
              ) : (
                <ol className="mt-3 divide-y">
                  {report.top_students.map((student, i) => (
                    <li key={student.student_id}>
                      {/* Opens the profile in a new tab — the report stays put. */}
                      <Link
                        href={`/students/${student.student_id}`}
                        target="_blank"
                        className="hover:bg-accent/40 -mx-2 flex items-center gap-3 rounded-xl px-2 py-2.5 transition-colors"
                      >
                        <span
                          className={cn(
                            "flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums",
                            i < 3 ? "bg-success/10 text-success" : "text-muted-foreground",
                          )}
                        >
                          {i + 1}
                        </span>
                        <PersonAvatar
                          name={student.full_name ?? "?"}
                          photoUrl={student.photo_url}
                          className="size-8"
                        />
                        <p className="min-w-0 flex-1 truncate text-sm font-medium">
                          {student.full_name}
                          {student.section ? (
                            <span className="text-muted-foreground font-normal">
                              {" · "}
                              {student.section}
                            </span>
                          ) : null}
                        </p>
                        {student.letter && <Badge variant="secondary">{student.letter}</Badge>}
                        <span className="text-sm font-semibold tabular-nums">
                          {student.average}
                        </span>
                      </Link>
                    </li>
                  ))}
                </ol>
              )}
            </section>

            <section className="bg-card rounded-2xl border p-4 shadow-xs">
              <h2 className="font-display text-base font-semibold">{t("reports.atRisk")}</h2>
              <p className="text-muted-foreground mt-0.5 text-xs">{t("reports.atRiskHint")}</p>
              {report === null ? (
                <Skeleton className="mt-4 h-40 rounded-xl" />
              ) : report.at_risk.length === 0 ? (
                <p className="text-muted-foreground flex h-32 items-center justify-center text-center text-sm">
                  {report.totals.with_results === 0
                    ? t("reports.noData")
                    : t("reports.allPassing")}
                </p>
              ) : (
                <ol className="mt-3 divide-y">
                  {report.at_risk.map((student) => (
                    <li key={student.student_id}>
                      <Link
                        href={`/students/${student.student_id}`}
                        target="_blank"
                        className="hover:bg-accent/40 -mx-2 flex items-center gap-3 rounded-xl px-2 py-2.5 transition-colors"
                      >
                        <PersonAvatar
                          name={student.full_name ?? "?"}
                          photoUrl={student.photo_url}
                          className="size-8"
                        />
                        <p className="min-w-0 flex-1 truncate text-sm font-medium">
                          {student.full_name}
                          <span className="text-muted-foreground font-normal">
                            {student.section ? ` · ${student.section}` : ""}
                            {student.absence_days != null && student.absence_days > 0
                              ? ` · ${t("reports.absenceDays", { count: student.absence_days })}`
                              : ""}
                          </span>
                        </p>
                        {student.letter && (
                          <Badge
                            variant="outline"
                            className="border-destructive/30 bg-destructive/10 text-destructive"
                          >
                            {student.letter}
                          </Badge>
                        )}
                        <span className="text-destructive text-sm font-semibold tabular-nums">
                          {student.average}
                        </span>
                      </Link>
                    </li>
                  ))}
                </ol>
              )}
            </section>
          </div>
            </>
          )}
        </>
      )}
    </div>
  )
}
