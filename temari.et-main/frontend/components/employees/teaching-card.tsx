"use client"

import { ArrowUpRight, BookOpen, CalendarClock, Home } from "lucide-react"
import Link from "next/link"
import { useEffect, useMemo, useState } from "react"

import { WeeklyGridReadOnly } from "@/components/timetable/weekly-grid"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { TermPeriod } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtTime } from "@/lib/dates"

interface TeachingAssignment {
  id: number
  subject_id: number
  subject_name: string | null
  subject_code: string | null
  subject_category: string | null
  section_id: number
  section_name: string
  grade_level_name: string | null
  grade_level_sort: number | null
  periods_per_week: number | null
  students: number
  is_active: boolean
}

interface TeachingWeek {
  days: number[]
  periods: TermPeriod[]
  slots: {
    day_of_week: number
    period_number: number
    subject_code: string | null
    subject_name: string | null
    section_name: string
    grade_level_name: string | null
    room_name: string | null
  }[]
}

interface TeachingPayload {
  term_id: number | null
  assignments: TeachingAssignment[]
  homeroom_sections: {
    section_id: number
    section_name: string | null
    grade_level_name: string | null
  }[]
  week: TeachingWeek | null
}

interface TermOption {
  id: number
  name: string
  year_name: string | null
  status: "planned" | "active" | "closed"
  is_current: boolean
}

/** Subject categories wear the validated chart tokens, deterministically. */
const CATEGORY_TOKEN: Record<string, string> = {
  language: "var(--chart-2)",
  mathematics: "var(--chart-1)",
  natural_science: "var(--chart-4)",
  social_science: "var(--chart-3)",
  technology: "var(--chart-5)",
  arts_pe: "var(--chart-3)",
  vocational: "var(--chart-2)",
}

function categoryColor(category: string | null): string {
  return (category && CATEGORY_TOKEN[category]) || "var(--chart-1)"
}

/**
 * The teacher's real workload for a semester — live subject assignments from
 * the teaching grid, their homeroom, and (once the timetable is published)
 * their personal week. Desktop gets the full grid; mobile gets a day-pager
 * timeline that reads like a native schedule app.
 */
export function TeachingCard({
  employeeId,
  canManageTimetable,
}: {
  employeeId: number
  canManageTimetable: boolean
}) {
  const { t } = useTranslation("employees")
  const { t: tt } = useTranslation("timetable")

  const [payload, setPayload] = useState<TeachingPayload | null>(null)
  const [terms, setTerms] = useState<TermOption[]>([])
  const [termId, setTermId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    const query = termId ? `?term_id=${termId}` : ""
    apiFetch<{ data: TeachingPayload; meta: { terms: TermOption[] } }>(
      `/employees/${employeeId}/teaching${query}`
    )
      .then((res) => {
        if (cancelled) return
        setPayload(res.data)
        setTerms(res.meta.terms)
        if (termId === null) setTermId(res.data.term_id)
      })
      .catch(() => !cancelled && setPayload(null))
      .finally(() => !cancelled && setLoading(false))
    return () => {
      cancelled = true
    }
  }, [employeeId, termId])

  const assignments = useMemo(() => payload?.assignments ?? [], [payload])

  /** Assignments folded per subject, heaviest weekly load first. */
  const subjectGroups = useMemo(() => {
    const groups = new Map<
      number,
      {
        name: string
        code: string | null
        category: string | null
        periods: number
        rows: TeachingAssignment[]
      }
    >()
    for (const a of assignments) {
      const g = groups.get(a.subject_id) ?? {
        name: a.subject_name ?? String(a.subject_id),
        code: a.subject_code,
        category: a.subject_category,
        periods: 0,
        rows: [],
      }
      g.periods += a.periods_per_week ?? 0
      g.rows.push(a)
      groups.set(a.subject_id, g)
    }
    return [...groups.values()].sort((a, b) => b.periods - a.periods)
  }, [assignments])

  const totals = useMemo(() => {
    const sections = new Map<number, number>()
    let periods = 0
    for (const a of assignments) {
      sections.set(a.section_id, a.students)
      periods += a.periods_per_week ?? 0
    }
    return {
      subjects: subjectGroups.length,
      sections: sections.size,
      periods,
      students: [...sections.values()].reduce((sum, n) => sum + n, 0),
    }
  }, [assignments, subjectGroups])

  const maxSubjectPeriods = Math.max(1, ...subjectGroups.map((g) => g.periods))

  const selectedTerm = terms.find((x) => x.id === (payload?.term_id ?? termId))

  // Group the semester picker under academic-year headers (newest year first)
  // so it never reads as a flat "Semester 1 · 2018, Semester 2 · 2017…" jumble.
  const termGroups = useMemo(() => {
    const groups = new Map<string, TermOption[]>()
    for (const term of terms) {
      const key = term.year_name ?? ""
      groups.set(key, [...(groups.get(key) ?? []), term])
    }
    const yearNum = (name: string) => {
      const match = name.match(/\d+/)
      return match ? Number(match[0]) : -Infinity
    }
    return [...groups.entries()].sort((a, b) => yearNum(b[0]) - yearNum(a[0]))
  }, [terms])

  return (
    <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
      <div className="flex flex-wrap items-center gap-3 border-b px-4 py-3 sm:px-5">
        <div className="min-w-0 flex-1">
          <h3 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            <BookOpen className="size-3.5" />
            {t("workload.title")}
          </h3>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {t("workload.hint")}
          </p>
        </div>
        {terms.length > 0 && (
          <Select
            value={termId !== null ? String(termId) : undefined}
            onValueChange={(v) => {
              setLoading(true)
              setTermId(Number(v))
            }}
          >
            <SelectTrigger
              className="h-9 w-full rounded-xl bg-muted/30 text-xs sm:w-56"
              aria-label={t("workload.semester")}
            >
              {selectedTerm ? (
                <span data-slot="select-value" className="line-clamp-1">
                  {[selectedTerm.name, selectedTerm.year_name].filter(Boolean).join(" · ")}
                  {selectedTerm.is_current ? ` — ${t("workload.current")}` : ""}
                </span>
              ) : (
                <SelectValue placeholder={t("workload.semester")} />
              )}
            </SelectTrigger>
            <SelectContent>
              {termGroups.map(([yearName, yearTerms]) => (
                <SelectGroup key={yearName || "—"}>
                  {yearName ? <SelectLabel>{yearName}</SelectLabel> : null}
                  {yearTerms.map((term) => (
                    <SelectItem key={term.id} value={String(term.id)}>
                      {term.name}
                      {term.is_current ? ` — ${t("workload.current")}` : ""}
                    </SelectItem>
                  ))}
                </SelectGroup>
              ))}
            </SelectContent>
          </Select>
        )}
      </div>

      <div className="space-y-4 p-4 sm:p-5">
        {loading || payload === null ? (
          <>
            <Skeleton className="h-16 rounded-xl" />
            <Skeleton className="h-32 rounded-xl" />
          </>
        ) : terms.length === 0 ? (
          <EmptyState text={t("workload.noTerms")} />
        ) : assignments.length === 0 ? (
          <EmptyState text={t("workload.empty")} hint={t("workload.emptyHint")}>
            {canManageTimetable && payload.term_id !== null && (
              <Button asChild size="sm" variant="outline" className="mt-3">
                <Link href={`/semesters/${payload.term_id}`}>
                  {t("workload.openGrid")}
                  <ArrowUpRight className="size-4" />
                </Link>
              </Button>
            )}
          </EmptyState>
        ) : (
          <>
            {/* Homeroom ribbon — the one section that is "theirs". */}
            {payload.homeroom_sections.length > 0 && (
              <div className="flex items-center gap-2.5 rounded-xl border border-primary/20 bg-primary/5 px-3 py-2.5 text-sm">
                <Home className="size-4 shrink-0 text-primary" />
                <span className="font-medium">{t("workload.homeroom")}</span>
                <span className="text-muted-foreground">
                  {payload.homeroom_sections
                    .map((h) =>
                      [h.grade_level_name, h.section_name]
                        .filter(Boolean)
                        .join(" · ")
                    )
                    .join(", ")}
                </span>
              </div>
            )}

            {/* Load at a glance */}
            <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border/60 sm:grid-cols-4">
              <LoadStat
                label={t("workload.stats.subjects")}
                value={totals.subjects}
              />
              <LoadStat
                label={t("workload.stats.sections")}
                value={totals.sections}
              />
              <LoadStat
                label={t("workload.stats.periodsPerWeek")}
                value={totals.periods}
              />
              <LoadStat
                label={t("workload.stats.students")}
                value={totals.students}
              />
            </dl>

            {/* Per-subject breakdown with proportional load bars. */}
            <ul className="space-y-3">
              {subjectGroups.map((group) => (
                <li key={group.name} className="rounded-xl border p-3">
                  <div className="flex items-baseline justify-between gap-3">
                    <p className="min-w-0 truncate text-sm font-semibold">
                      <span
                        className="mr-2 inline-block size-2 rounded-full align-middle"
                        style={{ backgroundColor: categoryColor(group.category) }}
                        aria-hidden
                      />
                      {group.name}
                      {group.code && (
                        <span className="ml-1.5 text-[10px] font-normal text-muted-foreground">
                          {group.code}
                        </span>
                      )}
                    </p>
                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                      {t("workload.perWeek", { n: group.periods })}
                    </span>
                  </div>
                  <div
                    className="mt-2 h-1 overflow-hidden rounded-full bg-muted"
                    aria-hidden
                  >
                    <div
                      className="h-full rounded-full transition-[width] duration-300"
                      style={{
                        width: `${Math.round((group.periods / maxSubjectPeriods) * 100)}%`,
                        backgroundColor: categoryColor(group.category),
                      }}
                    />
                  </div>
                  <div className="mt-2.5 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                    {group.rows.map((row) => (
                      <div
                        key={row.id}
                        className={cn(
                          "rounded-lg border bg-muted/20 px-2.5 py-2",
                          !row.is_active && "opacity-60"
                        )}
                      >
                        <p className="truncate text-sm font-medium">
                          {[row.grade_level_name, row.section_name]
                            .filter(Boolean)
                            .join(" · ")}
                        </p>
                        <p className="mt-0.5 text-[11px] text-muted-foreground tabular-nums">
                          {t("workload.periods", {
                            n: row.periods_per_week ?? 0,
                          })}
                          {" · "}
                          {t("workload.students", { n: row.students })}
                        </p>
                      </div>
                    ))}
                  </div>
                </li>
              ))}
            </ul>

            {/* The published week — full grid on desktop, day pager on mobile. */}
            {payload.week !== null ? (
              <TeacherWeek
                week={payload.week}
                termName={selectedTerm?.name ?? ""}
                t={t}
                tt={tt}
              />
            ) : (
              <p className="flex items-center gap-2 rounded-xl border border-dashed px-3 py-2.5 text-xs text-muted-foreground">
                <CalendarClock className="size-4 shrink-0" />
                {t("workload.week.notPublished")}
              </p>
            )}
          </>
        )}
      </div>
    </section>
  )
}

function LoadStat({ label, value }: { label: string; value: number }) {
  return (
    <div className="bg-card px-3 py-2.5">
      <dt className="text-[11px] text-muted-foreground">{label}</dt>
      <dd className="mt-0.5 text-sm font-semibold tabular-nums">{value}</dd>
    </div>
  )
}

function EmptyState({
  text,
  hint,
  children,
}: {
  text: string
  hint?: string
  children?: React.ReactNode
}) {
  return (
    <div className="flex flex-col items-center py-8 text-center">
      <BookOpen className="size-8 text-muted-foreground/40" aria-hidden />
      <p className="mt-2 text-sm font-medium">{text}</p>
      {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
      {children}
    </div>
  )
}

/**
 * The teacher's published week. Mobile renders a day pager (chips + timeline,
 * today pre-selected) instead of squeezing a 6-column grid onto a phone.
 */
function TeacherWeek({
  week,
  termName,
  t,
  tt,
}: {
  week: TeachingWeek
  termName: string
  t: (key: string, vars?: Record<string, string | number>) => string
  tt: (key: string, vars?: Record<string, string | number>) => string
}) {
  const jsDay = new Date().getDay() // 0 = Sunday
  const today = jsDay === 0 ? 7 : jsDay
  const [day, setDay] = useState<number>(
    week.days.includes(today) ? today : (week.days[0] ?? 1)
  )

  /** period_number → clock window, for the mobile timeline's time rail. */
  const periodTimes = useMemo(() => {
    const map = new Map<number, { starts: string; ends: string }>()
    for (const p of week.periods) {
      if (p.type === "class" && p.period_number != null) {
        map.set(p.period_number, {
          starts: p.starts_at ? fmtTime(p.starts_at) : "",
          ends: p.ends_at ? fmtTime(p.ends_at) : "",
        })
      }
    }
    return map
  }, [week.periods])

  const daySlots = week.slots
    .filter((s) => s.day_of_week === day)
    .sort((a, b) => a.period_number - b.period_number)

  return (
    <div>
      <div className="mb-2 flex items-center justify-between gap-2">
        <h4 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("workload.week.title")}
        </h4>
        <Badge
          variant="outline"
          className="rounded-full border-success/30 bg-success/10 text-[10px] text-success"
        >
          {t("workload.week.published")}
        </Badge>
      </div>

      {/* Desktop: the shared read-only weekly grid. */}
      <div className="hidden md:block">
        <WeeklyGridReadOnly
          data={{
            term_id: 0,
            term_name: termName,
            section: null,
            days: week.days,
            periods: week.periods,
            slots: week.slots.map((s) => ({
              day_of_week: s.day_of_week,
              period_number: s.period_number,
              subject: s.subject_name,
              teacher: null,
              room: s.room_name,
              section: [s.grade_level_name, s.section_name]
                .filter(Boolean)
                .join(" · "),
            })),
          }}
        />
      </div>

      {/* Mobile: day chips + timeline, like a native schedule app. */}
      <div className="md:hidden">
        <div
          className="scrollbar-none -mx-1 flex gap-1.5 overflow-x-auto px-1 pb-2"
          role="tablist"
          aria-label={t("workload.week.title")}
        >
          {week.days.map((d) => (
            <button
              key={d}
              type="button"
              role="tab"
              aria-selected={day === d}
              onClick={() => setDay(d)}
              className={cn(
                "min-h-11 shrink-0 rounded-full border px-4 text-sm font-medium transition-colors",
                day === d
                  ? "border-primary bg-primary text-primary-foreground"
                  : "bg-card text-muted-foreground"
              )}
            >
              {tt(`daysShort.${d}`)}
              {d === today && (
                <span
                  className={cn(
                    "ml-1.5 inline-block size-1.5 rounded-full align-middle",
                    day === d ? "bg-primary-foreground" : "bg-primary"
                  )}
                  aria-hidden
                />
              )}
            </button>
          ))}
        </div>

        {daySlots.length === 0 ? (
          <p className="rounded-xl border border-dashed px-3 py-4 text-center text-xs text-muted-foreground">
            {t("workload.week.noLessons")}
          </p>
        ) : (
          <ol className="space-y-1.5">
            {daySlots.map((slot, i) => {
              const time = periodTimes.get(slot.period_number)
              return (
                <li
                  key={i}
                  className="flex items-stretch gap-3 rounded-xl border px-3 py-2"
                >
                  <div className="flex w-12 shrink-0 flex-col justify-center text-[11px] tabular-nums">
                    <span className="font-semibold">{time?.starts ?? "—"}</span>
                    <span className="text-muted-foreground">
                      {time?.ends ?? tt("period", { n: slot.period_number })}
                    </span>
                  </div>
                  <div
                    className="w-0.5 shrink-0 rounded-full bg-primary/30"
                    aria-hidden
                  />
                  <div className="min-w-0 flex-1 py-0.5">
                    <p className="truncate text-sm font-medium">
                      {slot.subject_name ?? slot.subject_code}
                    </p>
                    <p className="truncate text-[11px] text-muted-foreground">
                      {[
                        [slot.grade_level_name, slot.section_name]
                          .filter(Boolean)
                          .join(" · "),
                        slot.room_name,
                      ]
                        .filter(Boolean)
                        .join(" — ")}
                    </p>
                  </div>
                </li>
              )
            })}
          </ol>
        )}
      </div>
    </div>
  )
}
