"use client"

import { Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
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
import { useTranslation } from "@/lib/i18n"
import type { EvaluationTemplateData } from "@/lib/types"
import { cn } from "@/lib/utils"

interface CriterionDraft {
  domain: string
  label: string
  weight: string
  max_score: string
}

/**
 * The rubric studio: the school's appraisal criteria (seeded from the MoE
 * default) with editable labels, weights and rating ceilings. Weights must
 * total exactly 100 — the running total pins the rule to the screen.
 * Existing appraisals are snapshots and never change.
 */
export function EvaluationTemplateSheet({
  open,
  onOpenChange,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const { t } = useTranslation("hr")
  const { t: tc } = useTranslation("common")

  const [templateId, setTemplateId] = useState<number | null>(null)
  const [name, setName] = useState("")
  const [criteria, setCriteria] = useState<CriterionDraft[] | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per open
    setCriteria(null)
    apiFetch<{ data: EvaluationTemplateData }>("/hr/evaluation-template")
      .then((res) => {
        if (cancelled) return
        setTemplateId(res.data.id)
        setName(res.data.name)
        setCriteria(
          res.data.criteria.map((c) => ({
            domain: c.domain,
            label: c.label,
            weight: String(c.weight),
            max_score: String(c.max_score),
          })),
        )
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        onOpenChange(false)
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc/onOpenChange stable enough
  }, [open])

  const weightSum = Math.round(
    (criteria ?? []).reduce((sum, c) => sum + (Number(c.weight) || 0), 0) * 100,
  ) / 100
  const balanced = Math.abs(weightSum - 100) < 0.01

  async function save() {
    if (templateId === null || criteria === null) return
    if (criteria.some((c) => !c.label.trim())) {
      toast.error(t("evaluations.template.labelRequired"))
      return
    }
    if (!balanced) {
      toast.error(t("evaluations.template.weightRule"))
      return
    }

    setSaving(true)
    try {
      await apiFetch(`/hr/evaluation-templates/${templateId}`, {
        method: "PUT",
        body: {
          name: name.trim() || undefined,
          criteria: criteria.map((c) => ({
            domain: c.domain.trim() || "general",
            label: c.label.trim(),
            weight: Number(c.weight) || 0,
            max_score: Number(c.max_score) || 5,
          })),
        },
      })
      toast.success(tc("actions.saved"))
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSaving(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("evaluations.template.title")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <p className="text-muted-foreground text-xs">{t("evaluations.template.hint")}</p>

          {criteria === null ? (
            <>
              <Skeleton className="h-14 rounded-2xl" />
              <Skeleton className="h-40 rounded-2xl" />
            </>
          ) : (
            <>
              <Input
                value={name}
                onChange={(event) => setName(event.target.value)}
                aria-label={t("evaluations.template.name")}
                placeholder={t("evaluations.template.name")}
              />

              <div className="space-y-2.5">
                {criteria.map((criterion, index) => (
                  <div key={index} className="space-y-2 rounded-2xl border p-3">
                    <div className="flex items-center gap-2">
                      <Input
                        value={criterion.label}
                        onChange={(event) =>
                          setCriteria((current) =>
                            (current ?? []).map((c, i) =>
                              i === index ? { ...c, label: event.target.value } : c,
                            ),
                          )
                        }
                        placeholder={t("evaluations.template.labelPlaceholder")}
                        className="h-10 flex-1"
                      />
                      <Button
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground size-10 shrink-0"
                        onClick={() =>
                          setCriteria((current) => (current ?? []).filter((_, i) => i !== index))
                        }
                        aria-label={tc("actions.remove")}
                        title={tc("actions.remove")}
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                    <div className="flex items-center gap-2">
                      <label className="flex flex-1 items-center gap-2 text-xs">
                        <span className="text-muted-foreground shrink-0">
                          {t("evaluations.template.weight")}
                        </span>
                        <Input
                          type="number"
                          inputMode="decimal"
                          min={0.5}
                          max={100}
                          value={criterion.weight}
                          onChange={(event) =>
                            setCriteria((current) =>
                              (current ?? []).map((c, i) =>
                                i === index ? { ...c, weight: event.target.value } : c,
                              ),
                            )
                          }
                          className="no-spinner h-9 w-20 text-center tabular-nums"
                        />
                        <span className="text-muted-foreground">%</span>
                      </label>
                      <label className="flex items-center gap-2 text-xs">
                        <span className="text-muted-foreground shrink-0">
                          {t("evaluations.template.maxScore")}
                        </span>
                        <Input
                          type="number"
                          inputMode="numeric"
                          min={1}
                          max={100}
                          value={criterion.max_score}
                          onChange={(event) =>
                            setCriteria((current) =>
                              (current ?? []).map((c, i) =>
                                i === index ? { ...c, max_score: event.target.value } : c,
                              ),
                            )
                          }
                          className="no-spinner h-9 w-16 text-center tabular-nums"
                        />
                      </label>
                    </div>
                  </div>
                ))}
              </div>

              <Button
                variant="outline"
                className="w-full border-dashed"
                onClick={() =>
                  setCriteria((current) => [
                    ...(current ?? []),
                    { domain: "general", label: "", weight: "0", max_score: "5" },
                  ])
                }
              >
                <Plus className="size-4" />
                {t("evaluations.template.addCriterion")}
              </Button>

              <div
                className={cn(
                  "flex items-center justify-between rounded-xl border px-3.5 py-2.5 text-sm font-medium",
                  balanced
                    ? "border-success/30 bg-success/5 text-success"
                    : "border-warning/40 bg-warning/5 text-warning",
                )}
              >
                <span>{t("evaluations.template.totalWeight")}</span>
                <span className="tabular-nums">{weightSum}%</span>
              </div>
            </>
          )}
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
            disabled={saving}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            className="h-11 flex-1"
            onClick={() => void save()}
            loading={saving}
            disabled={criteria === null || !balanced}
          >
            {tc("actions.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
