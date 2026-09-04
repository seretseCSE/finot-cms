"use client"

import {
  BookOpen,
  CalendarDays,
  Check,
  ChevronLeft,
  ChevronRight,
  Minus,
  MoveRight,
  Pencil,
  Plus,
  X,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { CreatePlanSheet } from "@/components/lesson-plans/create-plan-sheet"
import { GradePill } from "@/components/lesson-plans/plan-shelf"
import {
  addDays,
  fmtDate,
  fmtTime,
  fmtWeekday,
} from "@/components/lesson-plans/shared"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useLocale, useTranslation } from "@/lib/i18n"
import type {
  DailyPlanRow,
  LessonCoverage,
  MyDayItem,
  MyDayPayload,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const COVERAGE_CYCLE: LessonCoverage[] = ["covered", "partial", "missed"]

/**
 * The teacher's day — the app-like home of lesson planning. A vertical
 * timeline of today's real periods (from the published timetable, or the
 * plain class list when none is published): planned periods open the studio,
 * unplanned ones offer one-tap "plan this lesson" (prefilled from the slot +
 * the unit scheduled for this week), and once the week is filed each sitting
 * takes one-tap coverage. Swipe or arrow between days.
 */
export function MyDay() {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { locale } = useLocale()
  const { active } = useSchoolContext()
  const router = useRouter()

  const today = new Date().toISOString().slice(0, 10)
  const [date, setDate] = useState(today)
  const [payload, setPayload] = useState<MyDayPayload | null>(null)
  const [planning, setPlanning] = useState<MyDayItem | null>(null)
  const [grade, setGrade] = useState<string | null>(null)
  // Quick-create: the slot whose annual plan is being created from here.
  const [createFor, setCreateFor] = useState<{
    subjectId: number
    gradeLevelId: number
  } | null>(null)
  const touchX = useRef<number | null>(null)

  const load = useCallback(() => {
    let cancelled = false
    apiFetch<{ data: MyDayPayload }>(`/lesson-plans/my-day?date=${date}`)
      .then((res) => !cancelled && setPayload(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
        setPayload({ date, has_timetable: false, periods: [], items: [] })
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [date, active.schoolId, active.branchId])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new date
    setPayload(null)
    return load()
  }, [load])

  async function markCoverage(item: MyDayItem, coverage: LessonCoverage) {
    if (!item.daily) return
    try {
      await apiFetch(`/daily-plans/${item.daily.id}/coverage`, {
        method: "POST",
        body: { items: [{ delivery_id: item.daily.delivery_id, coverage }] },
      })
      toast.success(t("myDay.coverageSaved"))
      load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  const items = payload?.items ?? []
  const isToday = date === today

  // Grade pills — a teacher with many classes narrows the day to one grade.
  const grades = [
    ...new Set(items.map((i) => i.grade_level.name ?? "").filter(Boolean)),
  ].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))
  const activeGrade = grade !== null && grades.includes(grade) ? grade : null
  const visibleItems =
    activeGrade === null
      ? items
      : items.filter((i) => i.grade_level.name === activeGrade)

  return (
    <div
      className="page-gutter space-y-4"
      onTouchStart={(e) => {
        touchX.current = e.touches[0]?.clientX ?? null
      }}
      onTouchEnd={(e) => {
        const start = touchX.current
        touchX.current = null
        if (start === null) return
        const dx = (e.changedTouches[0]?.clientX ?? start) - start
        if (Math.abs(dx) > 64) setDate((d) => addDays(d, dx < 0 ? 1 : -1))
      }}
    >
      {/* ── Date strip ── */}
      <div className="flex items-center justify-between gap-2">
        <Button
          variant="outline"
          size="icon"
          className="size-10 shrink-0"
          onClick={() => setDate((d) => addDays(d, -1))}
          title={t("myDay.previousDay")}
          aria-label={t("myDay.previousDay")}
        >
          <ChevronLeft className="size-4" />
        </Button>
        <button
          type="button"
          className="min-w-0 text-center"
          onClick={() => setDate(today)}
          title={t("myDay.jumpToday")}
        >
          <p className="truncate font-display text-lg font-semibold tracking-tight">
            {isToday ? t("myDay.today") : fmtWeekday(date, locale)}
          </p>
          <p className="text-xs text-muted-foreground">
            {fmtDate(date, locale)}
          </p>
        </button>
        <Button
          variant="outline"
          size="icon"
          className="size-10 shrink-0"
          onClick={() => setDate((d) => addDays(d, 1))}
          title={t("myDay.nextDay")}
          aria-label={t("myDay.nextDay")}
        >
          <ChevronRight className="size-4" />
        </Button>
      </div>

      {payload === null ? (
        <div className="space-y-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-24 w-full rounded-2xl" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-2xl border bg-card shadow-xs">
          <EmptyState
            compact
            icon={CalendarDays}
            title={t("myDay.empty")}
            description={t("myDay.emptyHint")}
          />
        </div>
      ) : (
        <div className="space-y-3">
          {!payload.has_timetable && (
            <p className="text-xs text-muted-foreground">
              {t("myDay.noTimetable")}
            </p>
          )}

          {grades.length > 1 && (
            <div className="-mx-4 flex scrollbar-none gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0">
              <GradePill
                label={t("shelf.allGrades")}
                active={activeGrade === null}
                onClick={() => setGrade(null)}
              />
              {grades.map((g) => (
                <GradePill
                  key={g}
                  label={g}
                  active={activeGrade === g}
                  onClick={() => setGrade(activeGrade === g ? null : g)}
                />
              ))}
            </div>
          )}

          {visibleItems.map((item, index) => {
            const planned = item.daily !== null
            const weekLocked =
              item.week !== null &&
              (item.week.status === "submitted" ||
                item.week.status === "approved")

            return (
              <div
                key={`${item.period_number ?? "x"}-${item.section.id}-${index}`}
                className={cn(
                  "flex gap-3 rounded-2xl border bg-card p-4 shadow-xs",
                  !planned && "border-dashed"
                )}
              >
                {/* period rail */}
                <div className="flex w-14 shrink-0 flex-col items-center">
                  <span className="flex size-10 items-center justify-center rounded-xl bg-accent text-sm font-semibold text-foreground/80 tabular-nums">
                    {item.period_number !== null ? (
                      item.period_number
                    ) : (
                      <BookOpen className="size-4" />
                    )}
                  </span>
                  {item.starts_at && (
                    <span className="mt-1 text-[10px] text-muted-foreground tabular-nums">
                      {fmtTime(item.starts_at)}
                    </span>
                  )}
                </div>

                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">
                    {item.subject.name ?? t("myDay.lesson")}
                    <span className="font-normal text-muted-foreground">
                      {" · "}
                      {[item.grade_level.name, item.section.name]
                        .filter(Boolean)
                        .join(" ")}
                    </span>
                  </p>

                  {planned ? (
                    <>
                      <p className="mt-0.5 truncate text-sm">
                        {item.daily!.topic}
                        {item.daily!.subtopic && (
                          <span className="text-muted-foreground">
                            {" "}
                            — {item.daily!.subtopic}
                          </span>
                        )}
                      </p>
                      <div className="mt-2 flex flex-wrap items-center gap-1.5">
                        {weekLocked ? (
                          COVERAGE_CYCLE.map((c) => {
                            const activeChoice = item.daily!.coverage === c
                            return (
                              <Button
                                key={c}
                                size="sm"
                                variant={activeChoice ? "default" : "outline"}
                                className={cn(
                                  "h-9",
                                  activeChoice &&
                                    (c === "covered"
                                      ? "bg-success text-white hover:bg-success/90"
                                      : c === "partial"
                                        ? "bg-warning text-white hover:bg-warning/90"
                                        : "bg-destructive text-white hover:bg-destructive/90")
                                )}
                                onClick={() => markCoverage(item, c)}
                              >
                                {c === "covered" ? (
                                  <Check className="size-3.5" />
                                ) : c === "partial" ? (
                                  <Minus className="size-3.5" />
                                ) : (
                                  <X className="size-3.5" />
                                )}
                                {t(`coverage.${c}`)}
                              </Button>
                            )
                          })
                        ) : (
                          <Button
                            size="sm"
                            variant="outline"
                            className="h-9"
                            onClick={() =>
                              router.push(
                                `/lesson-plans/days/${item.daily!.id}`
                              )
                            }
                          >
                            <Pencil className="size-3.5" />
                            {t("myDay.openPlan")}
                          </Button>
                        )}
                        {weekLocked && (
                          <Button
                            size="sm"
                            variant="ghost"
                            className="h-9"
                            onClick={() =>
                              router.push(
                                `/lesson-plans/days/${item.daily!.id}`
                              )
                            }
                            title={t("myDay.openPlan")}
                            aria-label={t("myDay.openPlan")}
                          >
                            <MoveRight className="size-3.5" />
                          </Button>
                        )}
                      </div>
                    </>
                  ) : item.plan === null ? (
                    <div className="mt-2">
                      {item.subject.id !== null &&
                      item.grade_level.id !== null ? (
                        <Button
                          size="sm"
                          variant="outline"
                          className="h-9"
                          onClick={() =>
                            setCreateFor({
                              subjectId: item.subject.id!,
                              gradeLevelId: item.grade_level.id!,
                            })
                          }
                        >
                          <Plus className="size-3.5" />
                          {t("register.newPlan")}
                        </Button>
                      ) : (
                        <p className="text-xs text-muted-foreground">
                          {t("myDay.noAnnualPlan")}
                        </p>
                      )}
                    </div>
                  ) : weekLocked ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                      {t("myDay.weekLocked")}
                    </p>
                  ) : (
                    <div className="mt-2">
                      <Button
                        size="sm"
                        className="h-9"
                        onClick={() => setPlanning(item)}
                      >
                        <Plus className="size-3.5" />
                        {t("myDay.planLesson")}
                      </Button>
                      {item.suggested_unit && (
                        <p className="mt-1.5 text-xs text-muted-foreground">
                          {t("myDay.suggestedUnit", {
                            unit: item.suggested_unit.title,
                          })}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}

      <PlanLessonSheet
        item={planning}
        date={date}
        onOpenChange={(open) => !open && setPlanning(null)}
        onCreated={(dayId) => {
          setPlanning(null)
          router.push(`/lesson-plans/days/${dayId}`)
        }}
      />

      {createFor !== null && (
        <CreatePlanSheet
          open
          onOpenChange={(open) => !open && setCreateFor(null)}
          initial={createFor}
          onCreated={() => setCreateFor(null)}
        />
      )}
    </div>
  )
}

/**
 * The one-field creation sheet: the slot already knows subject, section,
 * period and the scheduled unit — the teacher only names the topic, and the
 * full studio opens for the pedagogy.
 */
function PlanLessonSheet({
  item,
  date,
  onOpenChange,
  onCreated,
}: {
  item: MyDayItem | null
  date: string
  onOpenChange: (open: boolean) => void
  onCreated: (dayId: number) => void
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const [topic, setTopic] = useState("")
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per slot
    setTopic("")
  }, [item])

  async function create() {
    if (!item?.plan || !item.section.id || topic.trim() === "") return
    setSaving(true)
    try {
      const res = await apiFetch<{ data: DailyPlanRow }>(
        `/lesson-plans/${item.plan.id}/days`,
        {
          method: "POST",
          body: {
            teaches_on: date,
            topic: topic.trim(),
            annual_plan_unit_id: item.suggested_unit?.id ?? null,
            deliveries: [
              {
                section_id: item.section.id,
                teaches_on: date,
                period_number: item.period_number,
              },
            ],
          },
        }
      )
      onCreated(res.data.id)
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setSaving(false)
    }
  }

  return (
    <ResponsiveSheet open={item !== null} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {t("myDay.planTitle", {
              subject: item?.subject.name ?? "",
              section: [item?.grade_level.name, item?.section.name]
                .filter(Boolean)
                .join(" "),
            })}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          {item?.suggested_unit && (
            <div className="rounded-xl bg-accent/50 px-4 py-3 text-sm">
              <p className="text-xs text-muted-foreground">
                {t("myDay.thisWeeksUnit")}
              </p>
              <p className="mt-0.5 font-medium">
                {item.suggested_unit.sequence}. {item.suggested_unit.title}
              </p>
              {item.suggested_unit.page_from !== null && (
                <p className="mt-0.5 text-xs text-muted-foreground">
                  {t("myDay.pages")} {item.suggested_unit.page_from}
                  {item.suggested_unit.page_to !== null &&
                    item.suggested_unit.page_to !==
                      item.suggested_unit.page_from &&
                    `–${item.suggested_unit.page_to}`}
                </p>
              )}
            </div>
          )}
          <div className="space-y-2">
            <Label htmlFor="myday-topic">{t("day.topic")}</Label>
            <Input
              id="myday-topic"
              value={topic}
              onChange={(e) => setTopic(e.target.value)}
              placeholder={t("day.topicPlaceholder")}
              className="h-12 text-base md:text-sm"
              autoFocus
            />
          </div>
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            className="h-11 flex-1"
            loading={saving}
            disabled={topic.trim() === ""}
            onClick={create}
          >
            {t("myDay.startPlanning")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
