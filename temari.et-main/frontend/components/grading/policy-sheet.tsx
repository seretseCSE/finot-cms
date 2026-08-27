"use client"

import { useEffect, useState } from "react"
import { toast } from "sonner"

import { useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { GradeLevel, GradingDisplay, GradingPolicy, GradingScale } from "@/lib/types"

const SCHOOL_WIDE = "school"
const OPEN = "open"
const DISPLAYS: GradingDisplay[] = ["numeric", "letter", "both"]

interface Props {
  policy: GradingPolicy | null
  scales: GradingScale[]
  gradeLevels: GradeLevel[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

/** Create/edit one grading rule (scale + display for a grade window). */
export function GradingPolicySheet({ policy, scales, gradeLevels, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { needsBranch, branches } = useBranchScope()

  const [scaleId, setScaleId] = useState<string>("")
  const [display, setDisplay] = useState<GradingDisplay>("numeric")
  const [scope, setScope] = useState<string>(SCHOOL_WIDE)
  const [minSort, setMinSort] = useState<string>(OPEN)
  const [maxSort, setMaxSort] = useState<string>(OPEN)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- sync sheet with the edited row
    setScaleId(policy ? String(policy.grading_scale_id) : "")
    setDisplay(policy?.display ?? "numeric")
    setScope(policy?.branch_id != null ? String(policy.branch_id) : SCHOOL_WIDE)
    setMinSort(policy?.min_grade_sort != null ? String(policy.min_grade_sort) : OPEN)
    setMaxSort(policy?.max_grade_sort != null ? String(policy.max_grade_sort) : OPEN)
    setErrors({})
  }, [open, policy])

  async function save() {
    setSaving(true)
    setErrors({})
    try {
      const body = {
        grading_scale_id: Number(scaleId),
        display,
        // The scope select only exists in the school-wide workspace; branch
        // contexts write to their own branch server-side.
        ...(needsBranch && !policy
          ? { branch_id: scope === SCHOOL_WIDE ? null : Number(scope) }
          : {}),
        min_grade_sort: minSort === OPEN ? null : Number(minSort),
        max_grade_sort: maxSort === OPEN ? null : Number(maxSort),
      }
      await apiFetch(policy ? `/grading-policies/${policy.id}` : "/grading-policies", {
        method: policy ? "PUT" : "POST",
        body,
      })
      toast.success(t("policies.saved"))
      onOpenChange(false)
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.errors) {
        setErrors(error.errors)
        toast.error(error.message)
      } else {
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      }
    } finally {
      setSaving(false)
    }
  }

  const fieldError = (key: string) => errors[key]?.[0] ?? null

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {policy ? t("policies.edit") : t("policies.add")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-5">
          {needsBranch && !policy && (
            <div className="space-y-2">
              <Label>{t("policies.scope")}</Label>
              <Select value={scope} onValueChange={setScope}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SCHOOL_WIDE}>{t("policies.schoolWide")}</SelectItem>
                  {branches.map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                      {b.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="space-y-2">
            <Label>
              {t("policies.scale")} <span className="text-destructive">*</span>
            </Label>
            <Select value={scaleId} onValueChange={setScaleId}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {scales
                  .filter((s) => s.is_active)
                  .map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
            {fieldError("grading_scale_id") && (
              <p className="text-destructive text-xs">{fieldError("grading_scale_id")}</p>
            )}
          </div>

          <div className="space-y-2">
            <Label>{t("policies.display")}</Label>
            <Select value={display} onValueChange={(v) => setDisplay(v as GradingDisplay)}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {DISPLAYS.map((d) => (
                  <SelectItem key={d} value={d}>
                    {t(`policies.displays.${d}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>{t("policies.gradesFrom")}</Label>
              <Select value={minSort} onValueChange={setMinSort}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={OPEN}>{t("policies.openEnded")}</SelectItem>
                  {gradeLevels.map((g) => (
                    <SelectItem key={g.id} value={String(g.sort_order)}>
                      {g.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {fieldError("min_grade_sort") && (
                <p className="text-destructive text-xs">{fieldError("min_grade_sort")}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label>{t("policies.gradesTo")}</Label>
              <Select value={maxSort} onValueChange={setMaxSort}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={OPEN}>{t("policies.openEnded")}</SelectItem>
                  {gradeLevels.map((g) => (
                    <SelectItem key={g.id} value={String(g.sort_order)}>
                      {g.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button variant="outline" className="h-11 flex-1" onClick={() => onOpenChange(false)} disabled={saving}>
            {tc("actions.cancel")}
          </Button>
          <Button className="h-11 flex-1" onClick={save} loading={saving} disabled={!scaleId}>
            {tc("actions.save")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
