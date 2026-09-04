"use client"

import { Loader2, Map, Sparkles, X } from "lucide-react"
import { Dialog as DialogPrimitive } from "radix-ui"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { AnnualLessonPlanRow, AnnualPlanUnit } from "@/lib/types"

/**
 * Add/edit one unit of the annual roadmap — the full-screen studio (same
 * shell as the question editor): the MoE grid content (title, objectives,
 * rationale, prerequisites, methodology, aids, assessment) on the middle
 * canvas, and the schedule (date window, period budget, textbook pages) on
 * the right rail — the numbers the pacing math measures against. With the
 * plan in hand, ✨ next to the title drafts the grid fields from the typed
 * title — filling only what's still EMPTY, never overwriting the teacher.
 */
export function UnitSheet({
  planId,
  plan = null,
  unit,
  open,
  onOpenChange,
  onSaved,
}: {
  planId: number
  /** The owning plan (subject/grade ground the AI draft; optional). */
  plan?: AnnualLessonPlanRow | null
  unit: AnnualPlanUnit | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")

  const [title, setTitle] = useState("")
  const [objectives, setObjectives] = useState("")
  const [rationale, setRationale] = useState("")
  const [prerequisite, setPrerequisite] = useState("")
  const [methods, setMethods] = useState("")
  const [aids, setAids] = useState("")
  const [assessment, setAssessment] = useState("")
  const [pageFrom, setPageFrom] = useState("")
  const [pageTo, setPageTo] = useState("")
  const [startsOn, setStartsOn] = useState("")
  const [endsOn, setEndsOn] = useState("")
  const [periods, setPeriods] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [aiBusy, setAiBusy] = useState(false)

  useEffect(() => {
    if (!open) return
    /* eslint-disable react-hooks/set-state-in-effect -- seed editor state on open */
    setTitle(unit?.title ?? "")
    setObjectives(unit?.objectives ?? "")
    setRationale(unit?.rationale ?? "")
    setPrerequisite(unit?.prerequisite_knowledge ?? "")
    setMethods(unit?.methods ?? "")
    setAids(unit?.teaching_aids ?? "")
    setAssessment(unit?.assessment_techniques ?? "")
    setPageFrom(unit?.page_from != null ? String(unit.page_from) : "")
    setPageTo(unit?.page_to != null ? String(unit.page_to) : "")
    setStartsOn(unit?.starts_on ?? "")
    setEndsOn(unit?.ends_on ?? "")
    setPeriods(unit?.planned_periods ? String(unit.planned_periods) : "")
    setError(null)
    /* eslint-enable react-hooks/set-state-in-effect */
  }, [open, unit])

  /** ✨ Draft the grid fields for the typed title — fills only EMPTY fields. */
  async function draftWithAi() {
    if (!plan || title.trim() === "") return
    setAiBusy(true)
    try {
      const res = await apiFetch<{
        data: {
          units: {
            objectives: string | null
            rationale: string | null
            prerequisite_knowledge: string | null
            methods: string | null
            teaching_aids: string | null
            assessment_techniques: string | null
            planned_periods: number
          }[]
        }
      }>("/ai/actions", {
        method: "POST",
        body: {
          action: "annual_units",
          params: {
            subject: plan.subject.name ?? "",
            grade: plan.grade_level.name ?? "",
            chapters: title.trim(),
            count: 1,
          },
        },
      })

      const ai = res.data.units[0]
      if (!ai) {
        toast.error(t("aiUnits.none"))
        return
      }
      const keep = (current: string, drafted: string | null) =>
        current.trim() !== "" ? current : (drafted ?? "")
      setObjectives((v) => keep(v, ai.objectives))
      setRationale((v) => keep(v, ai.rationale))
      setPrerequisite((v) => keep(v, ai.prerequisite_knowledge))
      setMethods((v) => keep(v, ai.methods))
      setAids((v) => keep(v, ai.teaching_aids))
      setAssessment((v) => keep(v, ai.assessment_techniques))
      setPeriods((v) =>
        v.trim() !== "" || ai.planned_periods <= 0 ? v : String(ai.planned_periods)
      )
      toast.success(t("day.aiDrafted"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setAiBusy(false)
    }
  }

  async function save() {
    if (!title.trim()) {
      setError(t("unit.title"))
      return
    }
    setSaving(true)
    try {
      const body = {
        title: title.trim(),
        objectives: objectives.trim() || null,
        rationale: rationale.trim() || null,
        prerequisite_knowledge: prerequisite.trim() || null,
        methods: methods.trim() || null,
        teaching_aids: aids.trim() || null,
        assessment_techniques: assessment.trim() || null,
        page_from: pageFrom === "" ? null : Number(pageFrom),
        page_to: pageTo === "" ? null : Number(pageTo),
        starts_on: startsOn || null,
        ends_on: endsOn || null,
        planned_periods: periods === "" ? 0 : Number(periods),
      }
      await apiFetch(
        unit ? `/plan-units/${unit.id}` : `/lesson-plans/${planId}/units`,
        {
          method: unit ? "PUT" : "POST",
          body,
        }
      )
      toast.success(t("unit.saved"))
      onOpenChange(false)
      onSaved()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-black/20 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
        <DialogPrimitive.Content
          className="fixed inset-0 z-50 flex flex-col bg-background data-open:animate-in data-open:fade-in-0 data-open:zoom-in-[0.99] data-closed:animate-out data-closed:fade-out-0"
          onEscapeKeyDown={(e) => e.preventDefault()}
          onPointerDownOutside={(e) => e.preventDefault()}
          onInteractOutside={(e) => e.preventDefault()}
        >
          {/* ── Top bar ─────────────────────────────────────────────── */}
          <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background px-3 md:px-5">
            <Button
              variant="ghost"
              size="icon"
              className="text-muted-foreground"
              onClick={() => onOpenChange(false)}
              aria-label={tc("actions.close")}
            >
              <X className="size-5" />
            </Button>
            <div className="flex min-w-0 items-center gap-2.5">
              <div className="hidden size-8 items-center justify-center rounded-lg bg-primary/10 text-primary sm:flex">
                <Map className="size-4.5" />
              </div>
              <div className="min-w-0">
                <DialogPrimitive.Title className="truncate text-sm font-semibold">
                  {unit ? t("plan.editUnit") : t("plan.addUnit")}
                </DialogPrimitive.Title>
                <p className="hidden truncate text-xs text-muted-foreground sm:block">
                  {title.trim() !== "" ? title : t("unit.titlePlaceholder")}
                </p>
              </div>
            </div>

            <div className="ml-auto">
              <Button
                className="h-10 px-4 md:px-5"
                disabled={saving || title.trim() === ""}
                onClick={save}
              >
                {saving && <Loader2 className="size-4 animate-spin" />}
                {t("unit.save")}
              </Button>
            </div>
          </header>

          {/* ── Body: canvas + schedule rail. The date window / periods are
              the unit's frame, so on mobile the rail comes FIRST (flex
              order); desktop keeps it on the right. */}
          <div className="flex min-h-0 flex-1 flex-col overflow-y-auto bg-muted/40 dark:bg-muted/15 md:flex-row md:overflow-hidden">
            <main className="md:min-h-0 md:flex-1 md:overflow-y-auto">
              <div className="mx-auto w-full max-w-5xl space-y-5 p-4 pb-8 md:p-8">
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <Label htmlFor="unit-title" className="mb-2 block">
                    {t("unit.title")}{" "}
                    <span className="text-destructive">*</span>
                  </Label>
                  <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                      id="unit-title"
                      value={title}
                      onChange={(e) => {
                        setTitle(e.target.value)
                        setError(null)
                      }}
                      placeholder={t("unit.titlePlaceholder")}
                      className="h-12 flex-1 text-base md:text-sm"
                    />
                    {plan !== null && (
                      <Button
                        type="button"
                        variant="outline"
                        className="h-12 shrink-0"
                        loading={aiBusy}
                        disabled={title.trim() === ""}
                        onClick={() => void draftWithAi()}
                        title={t("day.aiDraft")}
                      >
                        <Sparkles className="size-4 text-primary" />
                        {t("day.aiDraft")}
                      </Button>
                    )}
                  </div>
                  {error && (
                    <p className="mt-2 text-sm text-destructive">{error}</p>
                  )}

                  <Label htmlFor="unit-objectives" className="mt-4 mb-2 block">
                    {t("unit.objectives")}
                  </Label>
                  <Textarea
                    id="unit-objectives"
                    value={objectives}
                    onChange={(e) => setObjectives(e.target.value)}
                    placeholder={t("unit.objectivesPlaceholder")}
                    rows={3}
                    className="text-base md:text-sm"
                  />
                </section>

                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="unit-rationale">
                        {t("unit.rationale")}
                      </Label>
                      <Textarea
                        id="unit-rationale"
                        value={rationale}
                        onChange={(e) => setRationale(e.target.value)}
                        placeholder={t("unit.rationalePlaceholder")}
                        rows={3}
                        className="text-base md:text-sm"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="unit-prerequisite">
                        {t("unit.prerequisite")}
                      </Label>
                      <Textarea
                        id="unit-prerequisite"
                        value={prerequisite}
                        onChange={(e) => setPrerequisite(e.target.value)}
                        placeholder={t("unit.prerequisitePlaceholder")}
                        rows={3}
                        className="text-base md:text-sm"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="unit-methods">{t("unit.methods")}</Label>
                      <Textarea
                        id="unit-methods"
                        value={methods}
                        onChange={(e) => setMethods(e.target.value)}
                        placeholder={t("unit.methodsPlaceholder")}
                        rows={3}
                        className="text-base md:text-sm"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="unit-aids">{t("unit.teachingAids")}</Label>
                      <Textarea
                        id="unit-aids"
                        value={aids}
                        onChange={(e) => setAids(e.target.value)}
                        placeholder={t("unit.teachingAidsPlaceholder")}
                        rows={3}
                        className="text-base md:text-sm"
                      />
                    </div>
                  </div>

                  <div className="mt-4 space-y-2">
                    <Label htmlFor="unit-assessment">
                      {t("unit.assessment")}
                    </Label>
                    <Textarea
                      id="unit-assessment"
                      value={assessment}
                      onChange={(e) => setAssessment(e.target.value)}
                      placeholder={t("unit.assessmentPlaceholder")}
                      rows={3}
                      className="text-base md:text-sm"
                    />
                  </div>
                </section>
              </div>
            </main>

            <aside className="order-first border-b bg-background md:order-none md:min-h-0 md:w-[340px] md:shrink-0 md:overflow-y-auto md:border-l md:border-b-0">
              <div className="space-y-5 p-4 md:p-5">
                <div className="space-y-2">
                  <Label>{t("unit.startsOn")}</Label>
                  <DatePicker
                    value={startsOn}
                    onChange={setStartsOn}
                    placeholder={t("unit.startsOn")}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t("unit.endsOn")}</Label>
                  <DatePicker
                    value={endsOn}
                    onChange={setEndsOn}
                    min={startsOn || undefined}
                    placeholder={t("unit.endsOn")}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="unit-periods">
                    {t("unit.plannedPeriods")}
                  </Label>
                  <Input
                    id="unit-periods"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    max={500}
                    value={periods}
                    onChange={(e) => setPeriods(e.target.value)}
                    className="no-spinner h-12 w-full text-base md:text-sm"
                  />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-2">
                    <Label htmlFor="unit-page-from">{t("unit.pageFrom")}</Label>
                    <Input
                      id="unit-page-from"
                      type="number"
                      inputMode="numeric"
                      min={1}
                      value={pageFrom}
                      onChange={(e) => setPageFrom(e.target.value)}
                      className="no-spinner h-12 w-full text-base md:text-sm"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="unit-page-to">{t("unit.pageTo")}</Label>
                    <Input
                      id="unit-page-to"
                      type="number"
                      inputMode="numeric"
                      min={1}
                      value={pageTo}
                      onChange={(e) => setPageTo(e.target.value)}
                      className="no-spinner h-12 w-full text-base md:text-sm"
                    />
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
