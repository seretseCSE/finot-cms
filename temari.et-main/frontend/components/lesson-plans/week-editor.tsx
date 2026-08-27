"use client"

import {
  AlertTriangle,
  ArrowRight,
  CalendarRange,
  Check,
  Copy,
  Plus,
  RotateCcw,
  Send,
  Trash2,
  X,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { DeclineDialog } from "@/components/lesson-plans/decline-dialog"
import {
  addDays,
  CoverageBadge,
  fmtDate,
  fmtWeekday,
  PlanStatusBadge,
} from "@/components/lesson-plans/shared"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DatePicker } from "@/components/ui/date-picker"
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useLocale, useTranslation } from "@/lib/i18n"
import type {
  AnnualLessonPlanRow,
  DailyPlanRow,
  DailyPlanSummary,
  WeeklyLessonPlanRow,
  WeeklyPrefill,
} from "@/lib/types"

/**
 * The weekly panel: the container the pacing gate rides on. Lists the
 * week's DAILY lesson plans as cards (tap → the studio), offers last week's
 * uncovered lessons as one-tap carryover copies, and carries the submit →
 * approve/decline workflow with the lag-justification field surfaced —
 * never a silent 422. Day content itself is edited in the daily studio.
 */
export function WeekEditor({
  plan,
  weekStart,
  onChanged,
}: {
  plan: AnnualLessonPlanRow
  weekStart: string
  onChanged: () => void
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { locale } = useLocale()
  const router = useRouter()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [week, setWeek] = useState<WeeklyLessonPlanRow | null | undefined>(
    undefined
  )
  const [prefill, setPrefill] = useState<WeeklyPrefill | null>(null)
  const [notes, setNotes] = useState("")
  const [justification, setJustification] = useState("")
  const [dirty, setDirty] = useState(false)
  const [working, setWorking] = useState(false)
  const [declineOpen, setDeclineOpen] = useState(false)
  const [addOpen, setAddOpen] = useState(false)

  const existing = plan.weekly_plans?.find(
    (w) => w.week_starts_on === weekStart
  )

  useEffect(() => {
    let cancelled = false
    /* eslint-disable react-hooks/set-state-in-effect -- reset for the new week */
    setWeek(undefined)
    setPrefill(null)
    setDirty(false)
    /* eslint-enable react-hooks/set-state-in-effect */

    const prefillReq = apiFetch<{ data: WeeklyPrefill }>(
      `/lesson-plans/${plan.id}/weeks/prefill?week_starts_on=${weekStart}`
    )

    if (existing) {
      Promise.all([
        apiFetch<{ data: WeeklyLessonPlanRow }>(`/weekly-plans/${existing.id}`),
        prefillReq,
      ])
        .then(([weekRes, prefillRes]) => {
          if (cancelled) return
          hydrate(weekRes.data)
          setPrefill(prefillRes.data)
        })
        .catch(() => !cancelled && setWeek(null))
    } else {
      prefillReq
        .then((res) => {
          if (cancelled) return
          setPrefill(res.data)
          setWeek(null)
          setNotes("")
          setJustification("")
        })
        .catch(() => !cancelled && setWeek(null))
    }
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- keyed by plan + week
  }, [plan.id, weekStart, existing?.id])

  function hydrate(data: WeeklyLessonPlanRow) {
    setWeek(data)
    setNotes(data.notes ?? "")
    setJustification(data.lag_justification ?? "")
  }

  const status = week?.status ?? "draft"
  const isEditable =
    plan.is_own &&
    (week === null || status === "draft" || status === "declined")
  const isLocked =
    week != null && (status === "submitted" || status === "approved")
  const canReview = plan.can_review && status === "submitted"
  const days = week?.days ?? []

  async function reload(weekId: number) {
    const fresh = await apiFetch<{ data: WeeklyLessonPlanRow }>(
      `/weekly-plans/${weekId}`
    )
    hydrate(fresh.data)
    onChanged()
  }

  async function saveMeta() {
    if (!week) return
    setWorking(true)
    try {
      await apiFetch(`/weekly-plans/${week.id}`, {
        method: "PUT",
        body: {
          notes: notes.trim() || null,
          lag_justification: justification.trim() || null,
        },
      })
      setDirty(false)
      toast.success(t("week.saved"))
      await reload(week.id)
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setWorking(false)
    }
  }

  async function action(
    verb: "submit" | "approve" | "reopen",
    successKey: string
  ) {
    if (!week) return
    setWorking(true)
    try {
      const body =
        verb === "submit" && justification.trim() !== ""
          ? { lag_justification: justification.trim() }
          : undefined
      await apiFetch(`/weekly-plans/${week.id}/${verb}`, {
        method: "POST",
        body,
      })
      toast.success(t(successKey))
      await reload(week.id)
    } catch (error) {
      if (error instanceof ApiError && error.errors.lag_justification) {
        toast.error(error.errors.lag_justification[0])
      } else {
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
      }
    } finally {
      setWorking(false)
    }
  }

  async function submitWeek() {
    if (!week) return
    if (dirty) await saveMeta()
    await action("submit", "week.submitted")
  }

  async function declineWeek(reason: string) {
    if (!week) return
    try {
      await apiFetch(`/weekly-plans/${week.id}/decline`, {
        method: "POST",
        body: { reason },
      })
      toast.success(t("week.declined"))
      await reload(week.id)
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  function deleteWeek() {
    if (!week) return
    confirmDelete(async () => {
      await apiFetch(`/weekly-plans/${week.id}`, { method: "DELETE" })
      toast.success(t("week.deleted"))
      onChanged()
    }, t("week.confirmDelete"))
  }

  /** Carry one uncovered lesson forward: a copy on the same weekday here. */
  async function carryForward(
    lesson: WeeklyPrefill["carryover"]["lessons"][number]
  ) {
    const weekday = new Date(`${lesson.teaches_on}T00:00:00`).getDay()
    const offset = weekday === 0 ? 6 : weekday - 1
    try {
      const res = await apiFetch<{ data: DailyPlanRow }>(
        `/daily-plans/${lesson.id}/duplicate`,
        {
          method: "POST",
          body: { teaches_on: addDays(weekStart, offset) },
        }
      )
      toast.success(t("week.carriedForward"))
      onChanged()
      router.push(`/lesson-plans/days/${res.data.id}`)
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  if (week === undefined) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-16 w-full rounded-2xl" />
        <Skeleton className="h-48 w-full rounded-2xl" />
      </div>
    )
  }

  const needsJustification =
    prefill?.needs_justification === true && justification.trim() === ""
  const scheduledUnits = prefill?.units ?? []
  const canAddDay =
    plan.is_own &&
    (week === null || status === "draft" || status === "declined")

  // Group day cards by date, Monday → Sunday.
  const byDate = new Map<string, DailyPlanSummary[]>()
  for (const day of days) {
    byDate.set(day.teaches_on, [...(byDate.get(day.teaches_on) ?? []), day])
  }
  const dates = [...byDate.keys()].sort()

  return (
    <div className="space-y-4">
      {/* ── Week header: title, status, workflow actions ── */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2.5">
          <div className="flex size-10 items-center justify-center rounded-xl bg-accent">
            <CalendarRange className="size-5" strokeWidth={1.75} />
          </div>
          <div>
            <h2 className="font-display text-lg font-semibold tracking-tight">
              {t("week.weekOf", { date: fmtDate(weekStart, locale) })}
            </h2>
            <div className="flex items-center gap-2">
              <PlanStatusBadge status={status} />
              {week?.lag_justification && status !== "draft" && (
                <span className="inline-flex items-center gap-1 text-xs text-warning">
                  <AlertTriangle className="size-3" />
                  {t("week.lagFlag")}
                </span>
              )}
            </div>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {canReview && (
            <>
              <Button
                className="h-10"
                loading={working}
                onClick={() => action("approve", "week.approved")}
              >
                <Check className="size-4" />
                {t("week.approve")}
              </Button>
              <Button
                variant="outline"
                className="h-10 text-destructive"
                loading={working}
                onClick={() => setDeclineOpen(true)}
              >
                <X className="size-4" />
                {t("week.decline")}
              </Button>
            </>
          )}
          {plan.is_own && status === "submitted" && week && (
            <Button
              variant="outline"
              className="h-10"
              loading={working}
              onClick={() => action("reopen", "week.reopened")}
            >
              <RotateCcw className="size-4" />
              {t("week.withdraw")}
            </Button>
          )}
          {plan.can_review && status === "approved" && (
            <Button
              variant="outline"
              className="h-10"
              loading={working}
              onClick={() => action("reopen", "week.reopened")}
            >
              <RotateCcw className="size-4" />
              {t("week.reopen")}
            </Button>
          )}
          {isEditable && week !== null && (
            <Button
              variant="ghost"
              size="icon"
              className="size-10 text-destructive"
              onClick={deleteWeek}
              title={t("week.confirmDelete")}
              aria-label={t("week.confirmDelete")}
            >
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>
      </div>

      {/* ── Banners ── */}
      {status === "declined" && week?.decline_reason && (
        <div className="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          {t("week.declinedBanner", { reason: week.decline_reason })}
        </div>
      )}
      {isLocked && week?.lag_justification && (
        <div className="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm">
          <p className="font-medium text-warning">{t("week.justification")}</p>
          <p className="mt-0.5 text-foreground/80">{week.lag_justification}</p>
        </div>
      )}
      {canAddDay && plan.status !== "approved" && (
        <div className="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
          {t("week.planNotApproved")}
        </div>
      )}

      {/* ── Carryover from last week ── */}
      {canAddDay && (prefill?.carryover.lessons.length ?? 0) > 0 && (
        <div className="space-y-2 rounded-2xl border border-warning/30 bg-warning/5 px-4 py-3">
          <p className="flex items-center gap-1.5 text-sm font-medium text-warning">
            <AlertTriangle className="size-4" />
            {t("week.carryoverTitle")}
          </p>
          <p className="text-xs text-muted-foreground">
            {t("week.carryoverHint")}
          </p>
          <ul className="space-y-1.5">
            {prefill!.carryover.lessons.map((l) => (
              <li
                key={l.id}
                className="flex items-center justify-between gap-2 text-sm"
              >
                <span className="min-w-0 truncate">
                  {l.topic}
                  {l.uncovered_sections.length > 0 && (
                    <span className="text-muted-foreground">
                      {" "}
                      · {l.uncovered_sections.join(", ")}
                    </span>
                  )}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  className="h-8 shrink-0"
                  onClick={() => carryForward(l)}
                >
                  <Copy className="size-3.5" />
                  {t("week.carryoverAdd")}
                </Button>
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* ── Scheduled unit hint ── */}
      {canAddDay && scheduledUnits.length > 0 && (
        <p className="text-sm text-muted-foreground">
          {t("week.fromUnit", {
            unit: scheduledUnits.map((u) => u.title).join(", "),
          })}
        </p>
      )}

      {/* ── Day cards ── */}
      <div className="space-y-3">
        {dates.length === 0 && (
          <div className="rounded-2xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground">
            {t("week.noLessons")}
          </div>
        )}

        {dates.map((date) => (
          <div key={date} className="space-y-2">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {fmtWeekday(date, locale)}
              <span className="font-normal normal-case">
                {" "}
                · {fmtDate(date, locale)}
              </span>
            </p>
            {(byDate.get(date) ?? []).map((day) => (
              <button
                key={day.id}
                type="button"
                onClick={() => router.push(`/lesson-plans/days/${day.id}`)}
                className="flex w-full items-center gap-3 rounded-2xl border bg-card p-4 text-left shadow-xs transition-colors hover:bg-accent/50"
              >
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium">
                    {day.topic}
                    {day.subtopic && (
                      <span className="font-normal text-muted-foreground">
                        {" "}
                        — {day.subtopic}
                      </span>
                    )}
                  </p>
                  {day.unit_title && (
                    <p className="truncate text-xs text-muted-foreground">
                      {day.unit_title}
                    </p>
                  )}
                  <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    {day.deliveries.map((d) => (
                      <span
                        key={d.id}
                        className="inline-flex items-center gap-1.5 text-xs"
                      >
                        <span className="text-muted-foreground">
                          {d.section.name}
                        </span>
                        <CoverageBadge coverage={d.coverage} />
                      </span>
                    ))}
                  </div>
                </div>
                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
              </button>
            ))}
          </div>
        ))}

        {canAddDay && (
          <Button
            variant="outline"
            className="h-11 w-full border-dashed"
            onClick={() => setAddOpen(true)}
          >
            <Plus className="size-4" />
            {t("week.addLesson")}
          </Button>
        )}
      </div>

      {/* ── Notes + justification + submit (editable weeks with content) ── */}
      {canAddDay && week !== null && (
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label className="text-xs">{t("week.notes")}</Label>
            <Textarea
              value={notes}
              onChange={(e) => {
                setNotes(e.target.value)
                setDirty(true)
              }}
              placeholder={t("week.notesPlaceholder")}
              rows={2}
              className="text-base md:text-sm"
            />
          </div>

          {prefill?.needs_justification && (
            <div className="space-y-2 rounded-2xl border border-warning/30 bg-warning/5 px-4 py-3">
              <p className="flex items-center gap-1.5 text-sm font-medium text-warning">
                <AlertTriangle className="size-4" />
                {t("week.gateTitle")}
              </p>
              <p className="text-xs text-muted-foreground">
                {t("week.gateHint")}
              </p>
              <Textarea
                value={justification}
                onChange={(e) => {
                  setJustification(e.target.value)
                  setDirty(true)
                }}
                placeholder={t("week.justificationPlaceholder")}
                rows={2}
                className="bg-background text-base md:text-sm"
              />
            </div>
          )}

          <div className="flex flex-col gap-2 sm:flex-row">
            <Button
              variant="outline"
              className="h-11 flex-1"
              loading={working}
              disabled={!dirty}
              onClick={saveMeta}
            >
              {t("week.save")}
            </Button>
            <Button
              className="h-11 flex-1"
              loading={working}
              disabled={
                plan.status !== "approved" ||
                needsJustification ||
                days.length === 0
              }
              onClick={submitWeek}
            >
              <Send className="size-4" />
              {t("week.submit")}
            </Button>
          </div>
        </div>
      )}

      <AddDaySheet
        plan={plan}
        weekStart={weekStart}
        suggestedUnitId={scheduledUnits[0]?.id ?? null}
        open={addOpen}
        onOpenChange={setAddOpen}
        onCreated={(dayId) => {
          setAddOpen(false)
          onChanged()
          router.push(`/lesson-plans/days/${dayId}`)
        }}
      />
      <DeclineDialog
        open={declineOpen}
        onOpenChange={setDeclineOpen}
        onDecline={declineWeek}
      />
      {confirmDialog}
    </div>
  )
}

/**
 * New daily plan inside this week: date, topic, chapter and the sections it
 * will teach (all of the plan's classes by default) — the studio opens for
 * the pedagogy right after.
 */
function AddDaySheet({
  plan,
  weekStart,
  suggestedUnitId,
  open,
  onOpenChange,
  onCreated,
}: {
  plan: AnnualLessonPlanRow
  weekStart: string
  suggestedUnitId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: (dayId: number) => void
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")

  const [date, setDate] = useState(weekStart)
  const [topic, setTopic] = useState("")
  const [unitId, setUnitId] = useState<number | null>(suggestedUnitId)
  const [sectionIds, setSectionIds] = useState<number[]>([])
  const [saving, setSaving] = useState(false)

  const sections = plan.sections ?? []

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- seed on open */
    setDate(weekStart)
    setTopic("")
    setUnitId(suggestedUnitId)
    setSectionIds(sections.map((s) => s.id))
    /* eslint-enable react-hooks/set-state-in-effect */
    // eslint-disable-next-line react-hooks/exhaustive-deps -- seed once per open
  }, [open])

  async function create() {
    if (topic.trim() === "" || sectionIds.length === 0) return
    setSaving(true)
    try {
      const res = await apiFetch<{ data: DailyPlanRow }>(
        `/lesson-plans/${plan.id}/days`,
        {
          method: "POST",
          body: {
            teaches_on: date,
            topic: topic.trim(),
            annual_plan_unit_id: unitId,
            deliveries: sectionIds.map((id) => ({
              section_id: id,
              teaches_on: date,
            })),
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
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("week.addLesson")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          <div className="space-y-2">
            <Label>{t("day.date")}</Label>
            <DatePicker
              value={date}
              onChange={(v) => v && setDate(v)}
              min={weekStart}
              max={addDays(weekStart, 6)}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="add-day-topic">{t("day.topic")}</Label>
            <Input
              id="add-day-topic"
              value={topic}
              onChange={(e) => setTopic(e.target.value)}
              placeholder={t("day.topicPlaceholder")}
              className="h-12 text-base md:text-sm"
            />
          </div>
          <div className="space-y-2">
            <Label>{t("day.unit")}</Label>
            <Select
              value={unitId !== null ? String(unitId) : "none"}
              onValueChange={(v) => setUnitId(v === "none" ? null : Number(v))}
            >
              <SelectTrigger className="h-12 w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">{t("week.noUnit")}</SelectItem>
                {(plan.units ?? []).map((u) => (
                  <SelectItem key={u.id} value={String(u.id)}>
                    {u.sequence}. {u.title}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          {sections.length > 1 && (
            <div className="space-y-2">
              <Label>{t("day.sittings")}</Label>
              <div className="flex flex-wrap gap-2">
                {sections.map((s) => {
                  const on = sectionIds.includes(s.id)
                  return (
                    <button
                      key={s.id}
                      type="button"
                      onClick={() =>
                        setSectionIds((prev) =>
                          on
                            ? prev.filter((id) => id !== s.id)
                            : [...prev, s.id]
                        )
                      }
                      className={
                        on
                          ? "touch-target rounded-full border border-primary bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                          : "touch-target rounded-full border bg-card px-4 py-2 text-sm font-medium hover:bg-accent"
                      }
                    >
                      {s.name}
                    </button>
                  )
                })}
              </div>
            </div>
          )}
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
            disabled={topic.trim() === "" || sectionIds.length === 0}
            onClick={create}
          >
            {t("myDay.startPlanning")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
