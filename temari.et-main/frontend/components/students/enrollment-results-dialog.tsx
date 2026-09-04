"use client"

import { CalendarX, Medal, ShieldCheck } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { EmptyState } from "@/components/ui/empty-state"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { ResultGrading, ResultSubjectLine, StudentEnrollment } from "@/lib/types"
import { cn } from "@/lib/utils"

/** One frozen semester result of the enrollment, as the endpoint returns it. */
interface EnrollmentTermResult {
  id: number
  term: { id: number; name: string; status: string }
  average: number | null
  rank: number | null
  rank_of: number | null
  subject_count: number
  breakdown: ResultSubjectLine[]
  grading: ResultGrading | null
  conduct: string | null
  absence_days: number | null
  comment: string | null
}

/**
 * The academic story of ONE enrollment (year): every frozen semester result
 * with its per-subject breakdown, in the same slide-in sheet the create
 * flows use. Data loads only when the sheet opens and is cached per
 * enrollment — the profile page never pays for history it isn't showing.
 */
export function EnrollmentResultsDialog({
  enrollment,
  open,
  onOpenChange,
}: {
  enrollment: StudentEnrollment | null
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation("students")
  const { t: tg } = useTranslation("grading")
  const { t: tc } = useTranslation("common")

  const [results, setResults] = useState<EnrollmentTermResult[] | null>(null)
  // Already-fetched enrollments render instantly on reopen; failures are
  // NOT cached so closing and reopening retries.
  const cache = useRef(new Map<number, EnrollmentTermResult[]>())

  useEffect(() => {
    if (!open || enrollment === null) return
    const cached = cache.current.get(enrollment.id)
    if (cached) {
       
      setResults(cached)
      return
    }
    let cancelled = false
     
    setResults(null)
    apiFetch<{ data: EnrollmentTermResult[] }>(`/student-enrollments/${enrollment.id}/results`)
      .then((res) => {
        cache.current.set(enrollment.id, res.data)
        if (!cancelled) setResults(res.data)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setResults([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [open, enrollment])

  const yearAverage = (() => {
    const averages = (results ?? [])
      .map((r) => r.average)
      .filter((a): a is number => a !== null)
    if (averages.length === 0) return null
    return Math.round((averages.reduce((sum, a) => sum + a, 0) / averages.length) * 100) / 100
  })()

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-2xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle className="font-display">
            {enrollment
              ? `${enrollment.grade_level?.name ?? ""}${
                  enrollment.section_name ? ` — ${enrollment.section_name}` : ""
                } · ${enrollment.academic_year_name ?? ""}`
              : ""}
          </ResponsiveSheetTitle>
          <ResponsiveSheetDescription>
            {yearAverage !== null ? (
              <>
                {t("detail.performance.yearAverage")}{" "}
                <span className="text-foreground font-semibold tabular-nums">{yearAverage}</span>
              </>
            ) : (
              t("detail.performance.open")
            )}
          </ResponsiveSheetDescription>
        </ResponsiveSheetHeader>

        <ResponsiveSheetBody className="space-y-5">
          {results === null ? (
            <>
              <Skeleton className="h-24 rounded-xl" />
              <Skeleton className="h-40 rounded-xl" />
            </>
          ) : results.length === 0 ? (
            <EmptyState
              compact
              icon={Medal}
              title={t("detail.performance.empty")}
              description={t("detail.performance.emptyHint")}
            />
          ) : (
            results.map((result) => (
              <section key={result.id} className="rounded-2xl border">
                {/* Semester headline */}
                <div className="bg-muted/40 flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-t-2xl border-b px-4 py-3">
                  <h3 className="text-sm font-semibold">{result.term.name}</h3>
                  {result.grading?.overall &&
                    (result.grading.overall.letter || result.grading.overall.label) && (
                      <Badge
                        variant="outline"
                        className={cn(
                          result.grading.overall.is_passing
                            ? "border-success/30 bg-success/10 text-success"
                            : "border-destructive/30 bg-destructive/10 text-destructive",
                        )}
                      >
                        {result.grading.overall.letter ?? result.grading.overall.label}
                      </Badge>
                    )}
                  <div className="flex-1" />
                  <span className="text-sm">
                    {tg("reportCards.average")}{" "}
                    <span className="font-semibold tabular-nums">{result.average ?? "—"}</span>
                  </span>
                  {result.rank !== null && result.rank_of !== null && (
                    <span className="text-muted-foreground inline-flex items-center gap-1 text-sm">
                      <Medal className="size-3.5" />
                      {tg("reportCards.rankOf", { rank: result.rank, of: result.rank_of })}
                    </span>
                  )}
                </div>

                {/* Per-subject breakdown */}
                {result.breakdown.length > 0 && (
                  <ul className="divide-y px-4">
                    {result.breakdown.map((line) => (
                      <li
                        key={line.subject_id}
                        className="flex items-center justify-between gap-3 py-2 text-sm"
                      >
                        <span className="min-w-0 truncate">{line.name}</span>
                        <span className="flex shrink-0 items-center gap-2">
                          {line.letter && (
                            <span
                              className={cn(
                                "text-xs font-medium",
                                line.is_passing === false
                                  ? "text-destructive"
                                  : "text-muted-foreground",
                              )}
                            >
                              {line.letter}
                            </span>
                          )}
                          <span
                            className={cn(
                              "font-semibold tabular-nums",
                              line.is_passing === false && "text-destructive",
                            )}
                          >
                            {line.total ?? "—"}
                          </span>
                        </span>
                      </li>
                    ))}
                  </ul>
                )}

                {/* Conduct + absences footer */}
                {(result.conduct || result.absence_days !== null || result.comment) && (
                  <div className="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 border-t px-4 py-2.5 text-xs">
                    {result.conduct && (
                      <span className="inline-flex items-center gap-1">
                        <ShieldCheck className="size-3.5" />
                        {tg("reportCards.conduct")}: {result.conduct}
                      </span>
                    )}
                    {result.absence_days !== null && (
                      <span className="inline-flex items-center gap-1">
                        <CalendarX className="size-3.5" />
                        {tg("reportCards.absences")}: {result.absence_days}
                      </span>
                    )}
                    {result.comment && <span className="min-w-0">{result.comment}</span>}
                  </div>
                )}
              </section>
            ))
          )}
        </ResponsiveSheetBody>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
