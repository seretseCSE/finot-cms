"use client"

import { Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label, MOBILE_FIELD_LABEL } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { GradingScale, GradingScaleBand } from "@/lib/types"

interface Props {
  scale: GradingScale | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

const EMPTY_BAND: GradingScaleBand = {
  min_score: 0,
  max_score: 100,
  letter: null,
  label: "",
  grade_points: null,
  is_passing: true,
}

/** Create/edit a school grading scale with its bands. */
export function GradingScaleSheet({
  scale,
  open,
  onOpenChange,
  onSaved,
}: Props) {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")

  const [name, setName] = useState("")
  const [code, setCode] = useState("")
  const [description, setDescription] = useState("")
  const [bands, setBands] = useState<GradingScaleBand[]>([EMPTY_BAND])
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- sync sheet with the edited row
    setName(scale?.name ?? "")
    setCode(scale?.code ?? "")
    setDescription(scale?.description ?? "")
    setBands(
      scale?.bands?.length
        ? scale.bands.map((b) => ({ ...b }))
        : [{ ...EMPTY_BAND }]
    )
    setErrors({})
  }, [open, scale])

  function setBand(index: number, patch: Partial<GradingScaleBand>) {
    setBands((prev) =>
      prev.map((b, i) => (i === index ? { ...b, ...patch } : b))
    )
  }

  async function save() {
    setSaving(true)
    setErrors({})
    try {
      const body = {
        name,
        code,
        description: description || null,
        bands: bands.map((b) => ({
          min_score: Number(b.min_score),
          max_score: Number(b.max_score),
          letter: b.letter || null,
          label: b.label,
          grade_points:
            b.grade_points === null || b.grade_points === undefined
              ? null
              : Number(b.grade_points),
          is_passing: b.is_passing,
        })),
      }
      await apiFetch(
        scale ? `/grading-scales/${scale.id}` : "/grading-scales",
        {
          method: scale ? "PUT" : "POST",
          body,
        }
      )
      toast.success(t("scales.saved"))
      onOpenChange(false)
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors)
        toast.error(error.message)
      } else {
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
      }
    } finally {
      setSaving(false)
    }
  }

  const fieldError = (key: string) => errors[key]?.[0] ?? null

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-2xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {scale ? t("scales.edit") : t("scales.add")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>
                {t("scales.name")} <span className="text-destructive">*</span>
              </Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} />
              {fieldError("name") && (
                <p className="text-xs text-destructive">{fieldError("name")}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label>
                {t("scales.code")} <span className="text-destructive">*</span>
              </Label>
              <Input value={code} onChange={(e) => setCode(e.target.value)} />
              {fieldError("code") && (
                <p className="text-xs text-destructive">{fieldError("code")}</p>
              )}
            </div>
          </div>
          <div className="space-y-2">
            <Label>{t("scales.description")}</Label>
            <Input
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <Label>{t("scales.bands")}</Label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setBands((prev) => [...prev, { ...EMPTY_BAND }])}
              >
                <Plus className="size-3.5" />
                {t("scales.addBand")}
              </Button>
            </div>
            {fieldError("bands") && (
              <p className="text-xs text-destructive">{fieldError("bands")}</p>
            )}

            <div className="space-y-2">
              {/* Header row (desktop). */}
              <div className="hidden grid-cols-[1fr_1fr_4rem_1.4fr_4rem_4.5rem_2rem] gap-2 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:grid">
                <span>{t("scales.bandMin")}</span>
                <span>{t("scales.bandMax")}</span>
                <span>{t("scales.bandLetter")}</span>
                <span>{t("scales.bandLabel")}</span>
                <span>{t("scales.bandPoints")}</span>
                <span>{t("scales.bandPassing")}</span>
                <span />
              </div>
              {bands.map((band, i) => (
                <div
                  key={i}
                  className="grid grid-cols-2 gap-2 rounded-xl border p-3 sm:grid-cols-[1fr_1fr_4rem_1.4fr_4rem_4.5rem_2rem] sm:items-center sm:rounded-lg sm:border-0 sm:p-0 sm:px-1"
                >
                  <div className="min-w-0 space-y-1 sm:contents">
                    <span className={MOBILE_FIELD_LABEL}>
                      {t("scales.bandMin")}
                    </span>
                    <Input
                      type="number"
                      inputMode="decimal"
                      aria-label={t("scales.bandMin")}
                      value={band.min_score}
                      onChange={(e) =>
                        setBand(i, { min_score: Number(e.target.value) })
                      }
                    />
                  </div>
                  <div className="min-w-0 space-y-1 sm:contents">
                    <span className={MOBILE_FIELD_LABEL}>
                      {t("scales.bandMax")}
                    </span>
                    <Input
                      type="number"
                      inputMode="decimal"
                      aria-label={t("scales.bandMax")}
                      value={band.max_score}
                      onChange={(e) =>
                        setBand(i, { max_score: Number(e.target.value) })
                      }
                    />
                  </div>
                  <div className="min-w-0 space-y-1 sm:contents">
                    <span className={MOBILE_FIELD_LABEL}>
                      {t("scales.bandLetter")}
                    </span>
                    <Input
                      aria-label={t("scales.bandLetter")}
                      value={band.letter ?? ""}
                      maxLength={8}
                      placeholder="A"
                      onChange={(e) =>
                        setBand(i, { letter: e.target.value || null })
                      }
                    />
                  </div>
                  <div className="min-w-0 space-y-1 sm:contents">
                    <span className={MOBILE_FIELD_LABEL}>
                      {t("scales.bandLabel")}
                    </span>
                    <Input
                      aria-label={t("scales.bandLabel")}
                      value={band.label}
                      onChange={(e) => setBand(i, { label: e.target.value })}
                    />
                  </div>
                  <div className="min-w-0 space-y-1 sm:contents">
                    <span className={MOBILE_FIELD_LABEL}>
                      {t("scales.bandPoints")}
                    </span>
                    <Input
                      type="number"
                      inputMode="decimal"
                      aria-label={t("scales.bandPoints")}
                      value={band.grade_points ?? ""}
                      placeholder="—"
                      onChange={(e) =>
                        setBand(i, {
                          grade_points:
                            e.target.value === ""
                              ? null
                              : Number(e.target.value),
                        })
                      }
                    />
                  </div>
                  <div className="col-span-2 flex items-center justify-between gap-2 sm:contents">
                    <div className="flex items-center gap-2">
                      <Switch
                        checked={band.is_passing}
                        onCheckedChange={(v) => setBand(i, { is_passing: v })}
                        aria-label={t("scales.bandPassing")}
                      />
                      <span className={MOBILE_FIELD_LABEL}>
                        {t("scales.bandPassing")}
                      </span>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="size-8 text-muted-foreground hover:text-destructive"
                      disabled={bands.length <= 1}
                      onClick={() =>
                        setBands((prev) => prev.filter((_, x) => x !== i))
                      }
                      aria-label={tc("actions.delete")}
                    >
                      <Trash2 className="size-3.5" />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          </div>
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
            onClick={save}
            loading={saving}
            disabled={!name || !code || bands.some((b) => !b.label)}
          >
            {tc("actions.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
