"use client"

import { Check, ChevronDown, RefreshCw, Sparkles } from "lucide-react"
import { useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  Sheet,
  SheetBody,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { stripHtml } from "@/lib/sanitize-html"
import type { AnnualLessonPlanRow } from "@/lib/types"
import { cn } from "@/lib/utils"

interface DraftUnit {
  title: string
  objectives: string | null
  rationale: string | null
  prerequisite_knowledge: string | null
  methods: string | null
  teaching_aids: string | null
  assessment_techniques: string | null
  planned_periods: number
}

/**
 * ✨ Draft the year's units with AI — a half-screen slide-over on the plan
 * workspace. The teacher describes what the year covers (or pastes the
 * textbook chapter list), reviews the STRUCTURED draft units as cards,
 * unticks any they don't want, and inserts the rest straight into the
 * roadmap through the normal unit endpoints — no copy-paste. Dates stay the
 * teacher's job: pacing anchors on real calendar windows.
 */
export function AiUnitsSheet({
  plan,
  open,
  onOpenChange,
  onSaved,
}: {
  plan: AnnualLessonPlanRow
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: ta } = useTranslation("ai")
  const { t: tc } = useTranslation("common")

  const [chapters, setChapters] = useState("")
  const [count, setCount] = useState(6)
  const [notes, setNotes] = useState("")
  const [notesOpen, setNotesOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [drafts, setDrafts] = useState<DraftUnit[] | null>(null)
  const [excluded, setExcluded] = useState<Set<number>>(new Set())
  const [expanded, setExpanded] = useState<number | null>(null)
  const [adding, setAdding] = useState(false)

  const selectedCount = (drafts?.length ?? 0) - excluded.size

  async function generate() {
    setBusy(true)
    setDrafts(null)
    setExcluded(new Set())
    setExpanded(null)
    try {
      const res = await apiFetch<{ data: { units: DraftUnit[] } }>(
        "/ai/actions",
        {
          method: "POST",
          body: {
            action: "annual_units",
            params: {
              subject: plan.subject.name ?? "",
              grade: plan.grade_level.name ?? "",
              goals: stripHtml(plan.goals ?? ""),
              chapters: chapters.trim(),
              count,
              total_periods: plan.total_periods ?? 0,
              ...(notes.trim() !== "" ? { notes: notes.trim() } : {}),
            },
          },
        }
      )
      setDrafts(res.data.units ?? [])
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setBusy(false)
    }
  }

  async function addSelected() {
    if (!drafts) return
    const keep = drafts.filter((_, i) => !excluded.has(i))
    if (keep.length === 0) return
    setAdding(true)
    try {
      // Sequential on purpose: the server assigns sequence = max + 1, so
      // parallel posts would race into duplicate ordering.
      for (const unit of keep) {
        await apiFetch(`/lesson-plans/${plan.id}/units`, {
          method: "POST",
          body: {
            title: unit.title,
            objectives: unit.objectives,
            rationale: unit.rationale,
            prerequisite_knowledge: unit.prerequisite_knowledge,
            methods: unit.methods,
            teaching_aids: unit.teaching_aids,
            assessment_techniques: unit.assessment_techniques,
            planned_periods: unit.planned_periods,
          },
        })
      }
      toast.success(t("aiUnits.added", { count: keep.length }))
      onOpenChange(false)
      setDrafts(null)
      setChapters("")
      setNotes("")
      onSaved()
    } catch (error) {
      // Partial inserts are fine — the roadmap shows what landed.
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
      onSaved()
    } finally {
      setAdding(false)
    }
  }

  const detail = (label: string, value: string | null) =>
    value ? (
      <div>
        <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
        <dd className="text-sm">{value}</dd>
      </div>
    ) : null

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        className="flex w-full flex-col gap-0 data-[side=right]:sm:max-w-[600px]"
      >
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            <Sparkles className="size-4 text-primary" />
            {t("aiUnits.title")}
          </SheetTitle>
          <SheetDescription>{ta("assist.draftNote")}</SheetDescription>
        </SheetHeader>

        <SheetBody className="min-h-0 flex-1 space-y-4 overflow-y-auto">
          <div className="space-y-2">
            <Label htmlFor="ai-units-chapters">{t("aiUnits.chapters")}</Label>
            <Textarea
              id="ai-units-chapters"
              value={chapters}
              onChange={(e) => setChapters(e.target.value)}
              placeholder={t("aiUnits.chaptersPlaceholder")}
              rows={3}
              maxLength={1500}
              className="text-base md:text-sm"
            />
          </div>

          <div className="flex flex-wrap items-end gap-3">
            <div className="space-y-2">
              <Label htmlFor="ai-units-count">{t("aiUnits.count")}</Label>
              <Input
                id="ai-units-count"
                type="number"
                inputMode="numeric"
                min={1}
                max={15}
                value={count}
                onChange={(e) =>
                  setCount(Math.min(15, Math.max(1, Number(e.target.value) || 6)))
                }
                className="no-spinner h-11 w-24 text-base md:text-sm"
              />
            </div>
            <Button
              className="h-11 flex-1"
              loading={busy}
              onClick={() => void generate()}
            >
              {drafts === null ? (
                <Sparkles className="size-4" />
              ) : (
                <RefreshCw className="size-4" />
              )}
              {drafts === null ? ta("assist.generate") : t("aiUnits.regenerate")}
            </Button>
          </div>

          <div className="space-y-1.5">
            <button
              type="button"
              onClick={() => setNotesOpen((v) => !v)}
              aria-expanded={notesOpen}
              className="flex items-center gap-1 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
              <ChevronDown
                className={cn(
                  "size-4 transition-transform",
                  !notesOpen && "-rotate-90"
                )}
                aria-hidden
              />
              {ta("assist.customInstructions")}
              {!notesOpen && notes.trim() !== "" && (
                <span className="size-1.5 rounded-full bg-primary" aria-hidden />
              )}
            </button>
            {notesOpen && (
              <Textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                rows={2}
                maxLength={500}
                placeholder={ta("assist.customInstructionsHint")}
                className="text-base md:text-sm"
              />
            )}
          </div>

          {busy && (
            <div className="space-y-2.5">
              {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton key={i} className="h-16 w-full rounded-2xl" />
              ))}
            </div>
          )}

          {drafts !== null && drafts.length === 0 && !busy && (
            <p className="rounded-xl border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
              {t("aiUnits.none")}
            </p>
          )}

          {drafts !== null && drafts.length > 0 && (
            <div className="space-y-2.5">
              <p className="text-xs text-muted-foreground">
                {t("aiUnits.selectHint")}
              </p>
              {drafts.map((unit, index) => {
                const included = !excluded.has(index)
                const isOpen = expanded === index
                return (
                  <div
                    key={index}
                    className={cn(
                      "rounded-2xl border bg-card transition-colors",
                      included ? "border-primary/30" : "opacity-60"
                    )}
                  >
                    <div className="flex items-start gap-3 p-3.5">
                      <Checkbox
                        checked={included}
                        onCheckedChange={(checked) =>
                          setExcluded((prev) => {
                            const next = new Set(prev)
                            if (checked === true) next.delete(index)
                            else next.add(index)
                            return next
                          })
                        }
                        aria-label={unit.title}
                        className="mt-0.5"
                      />
                      <button
                        type="button"
                        className="min-w-0 flex-1 text-left"
                        onClick={() => setExpanded(isOpen ? null : index)}
                        aria-expanded={isOpen}
                      >
                        <p className="font-medium">
                          <span className="text-muted-foreground">
                            {index + 1}.
                          </span>{" "}
                          {unit.title}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                          {unit.planned_periods > 0 && (
                            <span className="me-3 tabular-nums">
                              {unit.planned_periods}{" "}
                              {t("week.periods").toLowerCase()}
                            </span>
                          )}
                          {!isOpen && unit.objectives && (
                            <span className="line-clamp-1">
                              {unit.objectives}
                            </span>
                          )}
                        </p>
                      </button>
                      <ChevronDown
                        className={cn(
                          "mt-1 size-4 shrink-0 text-muted-foreground transition-transform",
                          isOpen && "rotate-180"
                        )}
                        aria-hidden
                      />
                    </div>
                    {isOpen && (
                      <dl className="space-y-2.5 border-t px-3.5 py-3 ps-10">
                        {detail(t("unit.objectives"), unit.objectives)}
                        {detail(t("unit.rationale"), unit.rationale)}
                        {detail(
                          t("unit.prerequisite"),
                          unit.prerequisite_knowledge
                        )}
                        {detail(t("unit.methods"), unit.methods)}
                        {detail(t("unit.teachingAids"), unit.teaching_aids)}
                        {detail(
                          t("unit.assessment"),
                          unit.assessment_techniques
                        )}
                      </dl>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </SheetBody>

        {drafts !== null && drafts.length > 0 && (
          <SheetFooter>
            <Button
              variant="outline"
              className="h-11 flex-1"
              disabled={adding}
              onClick={() => onOpenChange(false)}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              loading={adding}
              disabled={selectedCount === 0}
              onClick={() => void addSelected()}
            >
              <Check className="size-4" />
              {t("aiUnits.add", { count: selectedCount })}
            </Button>
          </SheetFooter>
        )}
      </SheetContent>
    </Sheet>
  )
}
