"use client"

import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
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
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { ReportCardSkill, SkillRating } from "@/lib/types"
import { cn } from "@/lib/utils"

const RATINGS: SkillRating[] = ["E", "VG", "S", "NI"]

/** One editable term of the extras surface. */
export interface ExtrasTerm {
  termId: number
  termName: string
  enrollmentId: number
  conduct: string | null
  comment: string | null
  skills: Record<string, string> | null
  /** Closed terms are read-only (TermGate) — render, never edit. */
  editable: boolean
}

export interface ExtrasTarget {
  studentName: string | null
  terms: ExtrasTerm[]
  /** Which term opens first (the roster's anchor term in semester mode). */
  initialTermId?: number
}

/**
 * The "Extra assessment" surface behind every roster row: conduct (ሥነ ምግባር),
 * the homeroom comment and the school's behavioral skill checklist (E/VG/S/NI
 * per term — quarters each get their own column on the printed yearly card).
 * One term is edited at a time; saving posts through the same conduct lane
 * the homeroom teacher already owns.
 */
export function ReportCardExtras({
  target,
  skills,
  onOpenChange,
  onSaved,
}: {
  target: ExtrasTarget | null
  skills: ReportCardSkill[]
  onOpenChange: (open: boolean) => void
  onSaved: (termId: number, patch: { conduct: string | null; comment: string | null; skills: Record<string, string> | null }) => void
}) {
  const { t } = useTranslation("grading")
  const { t: tc, locale } = useTranslation("common")

  const [termId, setTermId] = useState<number | null>(null)
  const [conduct, setConduct] = useState("")
  const [comment, setComment] = useState("")
  const [ratings, setRatings] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const term = target?.terms.find((x) => x.termId === termId) ?? null

  // A fresh target lands on its anchor term with that term's saved values.
  useEffect(() => {
    if (!target) return
    const first =
      target.terms.find((x) => x.termId === target.initialTermId) ?? target.terms[0]
    if (first) selectTerm(first)
  }, [target])

  function selectTerm(next: ExtrasTerm) {
    setTermId(next.termId)
    setConduct(next.conduct ?? "")
    setComment(next.comment ?? "")
    setRatings(next.skills ?? {})
  }

  const grouped = useMemo(() => {
    const habits = skills.filter((s) => s.group !== "character")
    const character = skills.filter((s) => s.group === "character")
    return [
      { key: "habits" as const, rows: habits },
      { key: "character" as const, rows: character },
    ].filter((g) => g.rows.length > 0)
  }, [skills])

  if (target === null) return null

  const skillLabel = (skill: ReportCardSkill) =>
    skill.label[locale as keyof typeof skill.label] || skill.label.en

  async function save() {
    if (!target || !term) return
    setSaving(true)
    try {
      const skillsPatch = Object.keys(ratings).length > 0 ? ratings : null
      await apiFetch(`/terms/${term.termId}/conduct`, {
        method: "POST",
        body: {
          rows: [
            {
              student_enrollment_id: term.enrollmentId,
              conduct: conduct || null,
              comment: comment || null,
              skills: skillsPatch,
            },
          ],
        },
      })
      onSaved(term.termId, {
        conduct: conduct || null,
        comment: comment || null,
        skills: skillsPatch,
      })
      toast.success(t("extras.saved"))
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <ResponsiveSheet open onOpenChange={(open) => !saving && onOpenChange(open)}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-lg">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("extras.title")}</ResponsiveSheetTitle>
          <p className="text-muted-foreground text-sm">{target.studentName ?? "—"}</p>
        </ResponsiveSheetHeader>

        <ResponsiveSheetBody className="space-y-5">
          {/* Term pills — yearly mode edits one quarter/semester at a time. */}
          {target.terms.length > 1 && (
            <div className="flex flex-wrap gap-1.5">
              {target.terms.map((option) => (
                <button
                  key={option.termId}
                  type="button"
                  onClick={() => selectTerm(option)}
                  aria-pressed={option.termId === termId}
                  className={cn(
                    "min-h-9 rounded-full border px-3 text-xs font-medium transition-colors",
                    option.termId === termId
                      ? "border-primary bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-accent/40",
                  )}
                >
                  {option.termName}
                </button>
              ))}
            </div>
          )}

          {term && !term.editable && (
            <p className="text-muted-foreground bg-muted/50 rounded-xl px-3 py-2 text-xs">
              {t("extras.closedTerm")}
            </p>
          )}

          <div className="grid grid-cols-[6rem_1fr] gap-3">
            <div className="space-y-2">
              <Label htmlFor="extras-conduct">{t("reportCards.conduct")}</Label>
              <Input
                id="extras-conduct"
                value={conduct}
                maxLength={5}
                placeholder="A–E"
                disabled={!term?.editable}
                className="text-center"
                onChange={(e) => setConduct(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="extras-comment">{t("extras.comment")}</Label>
              <Textarea
                id="extras-comment"
                value={comment}
                maxLength={255}
                rows={2}
                disabled={!term?.editable}
                onChange={(e) => setComment(e.target.value)}
              />
            </div>
          </div>

          {grouped.length === 0 ? (
            <p className="text-muted-foreground rounded-xl border border-dashed px-3 py-4 text-center text-xs">
              {t("extras.noSkills")}
            </p>
          ) : (
            grouped.map((group) => (
              <div key={group.key} className="space-y-1">
                <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                  {t(`extras.groups.${group.key}`)}
                </p>
                <div className="divide-y rounded-xl border">
                  {group.rows.map((skill) => (
                    <div
                      key={skill.key}
                      className="flex items-center justify-between gap-3 px-3 py-2"
                    >
                      <p className="min-w-0 flex-1 text-sm">{skillLabel(skill)}</p>
                      <div className="flex gap-1" role="radiogroup" aria-label={skillLabel(skill)}>
                        {RATINGS.map((rating) => {
                          const active = ratings[skill.key] === rating
                          return (
                            <button
                              key={rating}
                              type="button"
                              role="radio"
                              aria-checked={active}
                              disabled={!term?.editable}
                              onClick={() =>
                                setRatings((prev) => {
                                  const next = { ...prev }
                                  if (active) delete next[skill.key]
                                  else next[skill.key] = rating
                                  return next
                                })
                              }
                              className={cn(
                                "min-h-8 min-w-9 rounded-lg border text-xs font-semibold transition-colors",
                                active
                                  ? "border-primary bg-primary text-primary-foreground"
                                  : "text-muted-foreground hover:bg-accent/40",
                                !term?.editable && "opacity-60",
                              )}
                            >
                              {rating}
                            </button>
                          )
                        })}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))
          )}

          {grouped.length > 0 && (
            <p className="text-muted-foreground text-[11px]">{t("extras.legend")}</p>
          )}
        </ResponsiveSheetBody>

        <ResponsiveSheetFooter>
          <Button
            variant="outline"
            className="h-11 flex-1"
            disabled={saving}
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            className="h-11 flex-1"
            onClick={save}
            loading={saving}
            disabled={!term?.editable}
          >
            {tc("actions.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
