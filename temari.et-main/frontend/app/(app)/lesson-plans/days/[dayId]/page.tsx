"use client"

import {
  Check,
  CheckCheck,
  Copy,
  FileDown,
  Minus,
  NotebookPen,
  Sparkles,
  Trash2,
  X,
} from "lucide-react"
import { useParams, useRouter } from "next/navigation"
import { useCallback, useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import {
  CoverageBadge,
  fmtDate,
  PlanStatusBadge,
} from "@/components/lesson-plans/shared"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
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
import { useSchoolContext } from "@/lib/auth/school-context"
import { useLocale, useTranslation } from "@/lib/i18n"
import { useDocumentDownload } from "@/lib/use-document"
import type {
  DailyPlanRow,
  DailyPlanStageRow,
  LessonCoverage,
  LessonStageKey,
} from "@/lib/types"
import { cn } from "@/lib/utils"

const STAGES: LessonStageKey[] = ["intro", "main", "conclusion"]
const STAGE_FIELDS = [
  "learning_contents",
  "teacher_activity",
  "student_activity",
  "assessment_techniques",
  "teaching_aids",
  "remark",
] as const
const COVERAGE_CYCLE: LessonCoverage[] = ["covered", "partial", "missed"]
const AUTOSAVE_MS = 900

type StageDraft = Record<LessonStageKey, DailyPlanStageRow>

function emptyStage(stage: LessonStageKey): DailyPlanStageRow {
  return {
    stage,
    learning_contents: null,
    page: null,
    teacher_activity: null,
    student_activity: null,
    assessment_techniques: null,
    teaching_aids: null,
    remark: null,
  }
}

/**
 * The daily lesson plan studio — the MoE daily format as a guided form:
 * topic block, objectives/rationale/prerequisites, the three teaching
 * stages, learner supports and the sittings (sections × period). Autosaves
 * while the week is editable; once the week is filed it flips to a review
 * surface with one-tap coverage per sitting. Prints the official sheet.
 */
export default function DailyPlanPage() {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { locale } = useLocale()
  const { active } = useSchoolContext()
  const params = useParams<{ dayId: string }>()
  const router = useRouter()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { print, generating } = useDocumentDownload()

  const [day, setDay] = useState<DailyPlanRow | null | undefined>(undefined)
  const [stages, setStages] = useState<StageDraft>({
    intro: emptyStage("intro"),
    main: emptyStage("main"),
    conclusion: emptyStage("conclusion"),
  })
  const [saveState, setSaveState] = useState<"saved" | "dirty" | "saving">(
    "saved"
  )
  const [duplicateOpen, setDuplicateOpen] = useState(false)
  const [aiBusy, setAiBusy] = useState(false)
  const pending = useRef<Record<string, unknown>>({})
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null)

  const load = useCallback(() => {
    let cancelled = false
    apiFetch<{ data: DailyPlanRow }>(`/daily-plans/${params.dayId}`)
      .then((res) => {
        if (cancelled) return
        setDay(res.data)
        const draft: StageDraft = {
          intro: emptyStage("intro"),
          main: emptyStage("main"),
          conclusion: emptyStage("conclusion"),
        }
        for (const row of res.data.stages) draft[row.stage] = row
        setStages(draft)
      })
      .catch(() => !cancelled && setDay(null))
    return () => {
      cancelled = true
    }
  }, [params.dayId])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on navigation
    setDay(undefined)
    return load()
  }, [load, active.schoolId, active.branchId])

  const flush = useCallback(async () => {
    const body = pending.current
    if (Object.keys(body).length === 0) return
    pending.current = {}
    setSaveState("saving")
    try {
      const res = await apiFetch<{ data: DailyPlanRow }>(
        `/daily-plans/${params.dayId}`,
        {
          method: "PUT",
          body,
        }
      )
      setDay((prev) =>
        prev
          ? {
              ...prev,
              ...res.data,
              plan: prev.plan,
              units: prev.units,
              sections: prev.sections,
            }
          : prev
      )
      setSaveState(Object.keys(pending.current).length > 0 ? "dirty" : "saved")
    } catch (error) {
      setSaveState("dirty")
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc stable
  }, [params.dayId])

  /** Queue a field for the debounced autosave PATCH. */
  const queue = useCallback(
    (patch: Record<string, unknown>) => {
      pending.current = { ...pending.current, ...patch }
      setSaveState("dirty")
      if (timer.current) clearTimeout(timer.current)
      timer.current = setTimeout(() => void flush(), AUTOSAVE_MS)
    },
    [flush]
  )

  // Flush pending edits when leaving the page.
  useEffect(
    () => () => {
      if (timer.current) clearTimeout(timer.current)
      void flush()
    },
    [flush]
  )

  function patchDay(patch: Partial<DailyPlanRow> & Record<string, unknown>) {
    setDay((prev) => (prev ? { ...prev, ...patch } : prev))
    queue(patch)
  }

  function patchStage(stage: LessonStageKey, field: string, value: string) {
    setStages((prev) => {
      const next = { ...prev, [stage]: { ...prev[stage], [field]: value } }
      queue({
        stages: STAGES.map((s) => ({
          ...next[s],
          stage: s,
        })),
      })
      return next
    })
  }

  function toggleSection(sectionId: number) {
    if (!day) return
    const has = day.deliveries.some((d) => d.section.id === sectionId)
    if (has && day.deliveries.length === 1) {
      toast.error(t("day.lastSection"))
      return
    }
    const next = has
      ? day.deliveries.filter((d) => d.section.id !== sectionId)
      : [
          ...day.deliveries,
          {
            id: 0,
            section: {
              id: sectionId,
              name: day.sections?.find((s) => s.id === sectionId)?.name ?? "",
            },
            teaches_on: day.teaches_on,
            period_number: null,
            coverage: "pending" as LessonCoverage,
            coverage_note: null,
          },
        ]
    setDay({ ...day, deliveries: next })
    queue({
      deliveries: next.map((d) => ({
        id: d.id > 0 ? d.id : null,
        section_id: d.section.id,
        teaches_on: d.teaches_on,
        period_number: d.period_number,
      })),
    })
  }

  async function markCoverage(deliveryId: number, coverage: LessonCoverage) {
    if (!day) return
    try {
      const res = await apiFetch<{ data: DailyPlanRow }>(
        `/daily-plans/${day.id}/coverage`,
        {
          method: "POST",
          body: { items: [{ delivery_id: deliveryId, coverage }] },
        }
      )
      setDay((prev) =>
        prev ? { ...prev, deliveries: res.data.deliveries } : prev
      )
      toast.success(t("myDay.coverageSaved"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  function deleteDay() {
    if (!day) return
    confirmDelete(async () => {
      await apiFetch(`/daily-plans/${day.id}`, { method: "DELETE" })
      toast.success(t("day.deleted"))
      router.replace(
        day.plan ? `/lesson-plans/${day.plan.id}` : "/lesson-plans"
      )
    }, t("day.confirmDelete"))
  }

  /** ✨ Fill the empty pedagogy fields from one structured AI draft. */
  async function draftWithAi() {
    if (!day?.plan) return
    setAiBusy(true)
    try {
      const res = await apiFetch<{
        data: {
          plan: {
            objectives: string
            rationale: string
            prerequisite_knowledge: string
            stages: (Partial<DailyPlanStageRow> & { stage: LessonStageKey })[]
            support_slow: string
            support_medium: string
            support_fast: string
            homework: string
          }
        }
      }>("/ai/actions", {
        method: "POST",
        body: {
          action: "daily_plan",
          params: {
            subject: day.plan.subject.name ?? "",
            grade: day.plan.grade_level.name ?? "",
            unit: day.unit_title ?? "",
            topic: [day.topic, day.subtopic].filter(Boolean).join(" — "),
          },
        },
      })

      const ai = res.data.plan
      const patch: Record<string, unknown> = {}
      const keep = (current: string | null, drafted: string) =>
        current && current.trim() !== "" ? current : drafted || null

      patch.objectives = keep(day.objectives, ai.objectives)
      patch.rationale = keep(day.rationale, ai.rationale)
      patch.prerequisite_knowledge = keep(
        day.prerequisite_knowledge,
        ai.prerequisite_knowledge
      )
      patch.support_slow = keep(day.support_slow, ai.support_slow)
      patch.support_medium = keep(day.support_medium, ai.support_medium)
      patch.support_fast = keep(day.support_fast, ai.support_fast)
      patch.homework = keep(day.homework, ai.homework)

      const nextStages: StageDraft = { ...stages }
      for (const row of ai.stages ?? []) {
        if (!STAGES.includes(row.stage)) continue
        const current = nextStages[row.stage]
        nextStages[row.stage] = {
          ...current,
          learning_contents:
            current.learning_contents || row.learning_contents || null,
          teacher_activity:
            current.teacher_activity || row.teacher_activity || null,
          student_activity:
            current.student_activity || row.student_activity || null,
          assessment_techniques:
            current.assessment_techniques || row.assessment_techniques || null,
          teaching_aids: current.teaching_aids || row.teaching_aids || null,
        }
      }
      setStages(nextStages)
      setDay((prev) =>
        prev ? { ...prev, ...(patch as Partial<DailyPlanRow>) } : prev
      )
      queue({ ...patch, stages: STAGES.map((s) => nextStages[s]) })
      toast.success(t("day.aiDrafted"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setAiBusy(false)
    }
  }

  if (day === undefined) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("day.title")} backHref="/lesson-plans" />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-28 w-full rounded-2xl" />
          <Skeleton className="h-64 w-full rounded-2xl" />
        </div>
      </div>
    )
  }

  if (day === null) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("day.title")} backHref="/lesson-plans" />
        <div className="page-gutter">
          <EmptyState icon={NotebookPen} title={tc("errors.generic")} />
        </div>
      </div>
    )
  }

  const editable = day.is_own && day.editable
  const locked =
    day.week_status === "submitted" || day.week_status === "approved"

  return (
    <div className="space-y-6">
      <PageHeader
        title={`${day.plan?.subject.name ?? ""} · ${day.plan?.grade_level.name ?? ""}`}
        description={`${t("day.title")} · ${fmtDate(day.teaches_on, locale)}`}
        backHref={
          day.plan
            ? `/lesson-plans/${day.plan.id}?week=${day.week_starts_on ?? ""}`
            : "/lesson-plans"
        }
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {day.week_status && <PlanStatusBadge status={day.week_status} />}
            {editable && (
              <span
                className={cn(
                  "text-xs",
                  saveState === "saved"
                    ? "text-success"
                    : "text-muted-foreground"
                )}
              >
                {saveState === "saved"
                  ? t("day.saved")
                  : saveState === "saving"
                    ? t("day.saving")
                    : t("day.unsaved")}
              </span>
            )}
            {editable && (
              <Button
                variant="outline"
                className="h-10"
                loading={aiBusy}
                onClick={draftWithAi}
              >
                <Sparkles className="size-4" />
                {t("day.aiDraft")}
              </Button>
            )}
            <Button
              variant="outline"
              className="h-10"
              loading={generating}
              onClick={() => print("daily_lesson_plan", day.id)}
            >
              <FileDown className="size-4" />
              {t("day.print")}
            </Button>
            {day.is_own && (
              <Button
                variant="outline"
                className="h-10"
                onClick={() => setDuplicateOpen(true)}
              >
                <Copy className="size-4" />
                {t("day.duplicate")}
              </Button>
            )}
            {editable && (
              <Button
                variant="ghost"
                size="icon"
                className="size-10 text-destructive"
                onClick={deleteDay}
                title={t("day.delete")}
                aria-label={t("day.delete")}
              >
                <Trash2 className="size-4" />
              </Button>
            )}
          </div>
        }
      />

      <div className="page-gutter space-y-6">
        {/* ── Topic block ── */}
        <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Field label={t("day.topic")}>
              {editable ? (
                <Input
                  value={day.topic}
                  onChange={(e) => patchDay({ topic: e.target.value })}
                  placeholder={t("day.topicPlaceholder")}
                  className="h-11 text-base md:text-sm"
                />
              ) : (
                <p className="font-medium">{day.topic}</p>
              )}
            </Field>
            <Field label={t("day.subtopic")}>
              {editable ? (
                <Input
                  value={day.subtopic ?? ""}
                  onChange={(e) =>
                    patchDay({ subtopic: e.target.value || null })
                  }
                  className="h-11 text-base md:text-sm"
                />
              ) : (
                <p>{day.subtopic ?? "—"}</p>
              )}
            </Field>
            <Field label={t("day.unit")}>
              {editable ? (
                <Select
                  value={day.unit_id !== null ? String(day.unit_id) : "none"}
                  onValueChange={(v) => {
                    const id = v === "none" ? null : Number(v)
                    patchDay({
                      annual_plan_unit_id: id,
                      unit_id: id,
                      unit_title:
                        day.units?.find((u) => u.id === id)?.title ?? null,
                    })
                  }}
                >
                  <SelectTrigger className="h-11 w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">{t("week.noUnit")}</SelectItem>
                    {(day.units ?? []).map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>
                        {u.sequence}. {u.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              ) : (
                <p>{day.unit_title ?? "—"}</p>
              )}
            </Field>
            <Field label={t("day.date")}>
              {editable ? (
                <DatePicker
                  value={day.teaches_on}
                  onChange={(v) => v && patchDay({ teaches_on: v })}
                />
              ) : (
                <p>{fmtDate(day.teaches_on, locale)}</p>
              )}
            </Field>
          </div>

          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <TextField
              label={t("day.rationale")}
              value={day.rationale}
              editable={editable}
              onChange={(v) => patchDay({ rationale: v })}
            />
            <TextField
              label={t("day.prerequisite")}
              value={day.prerequisite_knowledge}
              editable={editable}
              onChange={(v) => patchDay({ prerequisite_knowledge: v })}
            />
          </div>

          <TextField
            label={t("day.objectives")}
            hint={t("day.objectivesHint")}
            value={day.objectives}
            editable={editable}
            onChange={(v) => patchDay({ objectives: v })}
            rows={3}
          />
        </section>

        {/* ── Sittings ── */}
        <section className="space-y-3 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {t("day.sittings")}
          </h2>
          {editable && (day.sections?.length ?? 0) > 1 && (
            <div className="flex flex-wrap gap-2">
              {(day.sections ?? []).map((s) => {
                const on = day.deliveries.some((d) => d.section.id === s.id)
                return (
                  <button
                    key={s.id}
                    type="button"
                    onClick={() => toggleSection(s.id)}
                    className={cn(
                      "touch-target rounded-full border px-4 py-2 text-sm font-medium transition-colors",
                      on
                        ? "border-primary bg-primary text-primary-foreground"
                        : "bg-card hover:bg-accent"
                    )}
                  >
                    {s.name}
                  </button>
                )
              })}
            </div>
          )}
          <div className="space-y-2">
            {day.deliveries.map((d) => (
              <div
                key={`${d.section.id}-${d.id}`}
                className="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2.5"
              >
                <p className="min-w-0 text-sm font-medium">
                  {d.section.name}
                  <span className="font-normal text-muted-foreground">
                    {" · "}
                    {fmtDate(d.teaches_on, locale)}
                    {d.period_number !== null &&
                      ` · ${t("day.period")} ${d.period_number}`}
                  </span>
                </p>
                {locked && day.is_own ? (
                  <div className="flex items-center gap-1">
                    {COVERAGE_CYCLE.map((c) => {
                      const activeChoice = d.coverage === c
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
                          onClick={() => markCoverage(d.id, c)}
                        >
                          {c === "covered" ? (
                            <Check className="size-3.5" />
                          ) : c === "partial" ? (
                            <Minus className="size-3.5" />
                          ) : (
                            <X className="size-3.5" />
                          )}
                          <span className="hidden sm:inline">
                            {t(`coverage.${c}`)}
                          </span>
                        </Button>
                      )
                    })}
                  </div>
                ) : (
                  <CoverageBadge coverage={d.coverage} />
                )}
              </div>
            ))}
          </div>
        </section>

        {/* ── The three teaching stages ── */}
        <section className="space-y-3">
          <div>
            <h2 className="font-display text-lg font-semibold tracking-tight">
              {t("day.stagesTitle")}
            </h2>
            <p className="text-xs text-muted-foreground">
              {t("day.stagesHint")}
            </p>
          </div>
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
            {STAGES.map((stage) => (
              <div
                key={stage}
                className="space-y-3 rounded-2xl border bg-card p-4 shadow-xs"
              >
                <div className="flex items-center justify-between gap-2">
                  <h3 className="text-sm font-semibold">
                    {t(`stages.${stage}`)}
                  </h3>
                  {editable && (
                    <Input
                      value={stages[stage].page ?? ""}
                      onChange={(e) =>
                        patchStage(stage, "page", e.target.value)
                      }
                      placeholder={t("day.page")}
                      className="no-spinner h-9 w-20 text-center text-sm"
                    />
                  )}
                  {!editable && stages[stage].page && (
                    <span className="text-xs text-muted-foreground">
                      {t("day.page")} {stages[stage].page}
                    </span>
                  )}
                </div>
                {STAGE_FIELDS.map((field) => (
                  <TextField
                    key={field}
                    label={t(`day.stageFields.${field}`)}
                    value={stages[stage][field]}
                    editable={editable}
                    compact
                    onChange={(v) => patchStage(stage, field, v ?? "")}
                  />
                ))}
              </div>
            ))}
          </div>
        </section>

        {/* ── Learner supports + homework ── */}
        <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {t("day.supportsTitle")}
          </h2>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <TextField
              label={t("day.supportSlow")}
              value={day.support_slow}
              editable={editable}
              onChange={(v) => patchDay({ support_slow: v })}
            />
            <TextField
              label={t("day.supportMedium")}
              value={day.support_medium}
              editable={editable}
              onChange={(v) => patchDay({ support_medium: v })}
            />
            <TextField
              label={t("day.supportFast")}
              value={day.support_fast}
              editable={editable}
              onChange={(v) => patchDay({ support_fast: v })}
            />
          </div>
          <TextField
            label={t("week.homework")}
            value={day.homework}
            editable={editable}
            onChange={(v) => patchDay({ homework: v })}
          />
        </section>

        {editable && saveState !== "saved" && (
          <div className="sticky bottom-20 flex items-center justify-between gap-3 rounded-2xl border bg-card p-3 shadow-md md:bottom-4">
            <p className="min-w-0 truncate text-xs text-muted-foreground">
              {t("day.autosaveHint")}
            </p>
            <Button
              className="h-10 shrink-0"
              loading={saveState === "saving"}
              onClick={() => void flush()}
            >
              <CheckCheck className="size-4" />
              {tc("actions.save")}
            </Button>
          </div>
        )}
      </div>

      <DuplicateSheet
        day={day}
        open={duplicateOpen}
        onOpenChange={setDuplicateOpen}
        onDone={(newId) => {
          setDuplicateOpen(false)
          router.push(`/lesson-plans/days/${newId}`)
        }}
      />
      {confirmDialog}
    </div>
  )
}

function Field({
  label,
  hint,
  children,
}: {
  label: string
  hint?: string
  children: React.ReactNode
}) {
  return (
    <div className="space-y-1.5">
      <Label className="text-xs">{label}</Label>
      {children}
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  )
}

function TextField({
  label,
  hint,
  value,
  editable,
  compact = false,
  rows = 2,
  onChange,
}: {
  label: string
  hint?: string
  value: string | null
  editable: boolean
  compact?: boolean
  rows?: number
  onChange: (value: string | null) => void
}) {
  if (!editable) {
    if (!value && compact) return null
    return (
      <div className="space-y-1">
        <p className="text-xs font-medium text-muted-foreground">{label}</p>
        <p className="text-sm whitespace-pre-wrap">{value ?? "—"}</p>
      </div>
    )
  }

  return (
    <Field label={label} hint={hint}>
      <Textarea
        value={value ?? ""}
        onChange={(e) => onChange(e.target.value || null)}
        rows={compact ? 2 : rows}
        className="text-base md:text-sm"
      />
    </Field>
  )
}

/** Copy this lesson to another day — the bump, or reuse for other sections. */
function DuplicateSheet({
  day,
  open,
  onOpenChange,
  onDone,
}: {
  day: DailyPlanRow
  open: boolean
  onOpenChange: (open: boolean) => void
  onDone: (newId: number) => void
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const [date, setDate] = useState("")
  const [markMissed, setMarkMissed] = useState(false)
  const [saving, setSaving] = useState(false)

  const hasUncovered = day.deliveries.some(
    (d) =>
      d.coverage === "pending" ||
      d.coverage === "missed" ||
      d.coverage === "partial"
  )

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- seed on open */
    setDate("")
    setMarkMissed(false)
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [open])

  async function duplicate() {
    if (!date) return
    setSaving(true)
    try {
      const res = await apiFetch<{ data: DailyPlanRow }>(
        `/daily-plans/${day.id}/duplicate`,
        {
          method: "POST",
          body: { teaches_on: date, mark_source_missed: markMissed },
        }
      )
      toast.success(t("day.duplicated"))
      onDone(res.data.id)
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
          <ResponsiveSheetTitle>{t("day.duplicateTitle")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          <p className="text-sm text-muted-foreground">
            {t("day.duplicateHint")}
          </p>
          <div className="space-y-2">
            <Label>{t("day.duplicateDate")}</Label>
            <DatePicker value={date} onChange={setDate} min={day.teaches_on} />
          </div>
          {hasUncovered && (
            <label className="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-xl border px-4 py-3">
              <input
                type="checkbox"
                checked={markMissed}
                onChange={(e) => setMarkMissed(e.target.checked)}
                className="size-4 accent-primary"
              />
              <span className="text-sm">{t("day.markSourceMissed")}</span>
            </label>
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
            disabled={!date}
            onClick={duplicate}
          >
            {t("day.duplicate")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
