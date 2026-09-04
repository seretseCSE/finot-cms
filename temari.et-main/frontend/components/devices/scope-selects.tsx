"use client"

import { useMemo } from "react"

import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useContextsResponse, useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { ContextSchool } from "@/lib/types"

export interface ScopeValue {
  schoolId: number | null
  branchId: number | null
}

/**
 * The cascading School → Branch target used across the hardware area:
 * Temari.et staff pick a school then one of ITS branches; school managers in
 * the All-branches workspace pick a branch of their own school; inside a
 * concrete branch workspace nothing renders (the context already scopes).
 * `allOption` turns the selects into narrowing FILTERS instead of a required
 * target (adds an "All …" row).
 */
export function useScopeCatalog(): {
  schools: ContextSchool[]
  isPlatform: boolean
  needsSchool: boolean
  needsBranch: boolean
  contextSchool: ContextSchool | null
} {
  const { active, isPlatform } = useSchoolContext()

  const inBranchContext = active.branchId != null
  const wanted = !inBranchContext && (isPlatform || active.schoolId != null)

  // Shared, auto-refreshing contexts source — a just-created branch is
  // immediately targetable here without a reload.
  const { data: contextsData } = useContextsResponse(wanted)
  const schools = useMemo(() => contextsData?.schools ?? [], [contextsData])

  const contextSchool = useMemo(
    () =>
      !isPlatform && active.schoolId != null
        ? (schools.find((s) => s.id === active.schoolId) ?? null)
        : null,
    [isPlatform, active.schoolId, schools],
  )

  return {
    schools,
    isPlatform,
    needsSchool: wanted && isPlatform,
    needsBranch: wanted,
    contextSchool,
  }
}

export function ScopeSelects({
  value,
  onChange,
  allOption = false,
  required = false,
  layout = "row",
}: {
  value: ScopeValue
  onChange: (value: ScopeValue) => void
  /** Render "All schools / All branches" rows — filter mode. */
  allOption?: boolean
  /** Mark labels with the required asterisk (target mode). */
  required?: boolean
  layout?: "row" | "stack"
}) {
  const { t } = useTranslation("common")
  const { schools, needsSchool, needsBranch, contextSchool } = useScopeCatalog()

  if (!needsBranch) return null

  const school = needsSchool
    ? (schools.find((s) => s.id === value.schoolId) ?? null)
    : contextSchool

  const wrap = layout === "row" ? "flex flex-wrap items-end gap-2" : "space-y-4"
  const field = layout === "row" ? "space-y-1.5" : "space-y-2"
  const triggerClass = layout === "row" ? "h-9 w-44 text-xs" : "w-full"

  return (
    <div className={wrap}>
      {needsSchool && (
        <div className={field}>
          {layout === "stack" && (
            <Label>
              {t("filters.school")}
              {required && <span className="text-destructive"> *</span>}
            </Label>
          )}
          <Select
            value={value.schoolId != null ? String(value.schoolId) : allOption ? "all" : ""}
            onValueChange={(v) =>
              onChange({ schoolId: v === "all" ? null : Number(v), branchId: null })
            }
          >
            <SelectTrigger className={triggerClass}>
              <SelectValue placeholder={t("filters.school")} />
            </SelectTrigger>
            <SelectContent>
              {allOption && <SelectItem value="all">{t("scope.allSchools")}</SelectItem>}
              {schools.map((s) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {s.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
      {school && (
        <div className={field}>
          {layout === "stack" && (
            <Label>
              {t("filters.branch")}
              {required && <span className="text-destructive"> *</span>}
            </Label>
          )}
          <Select
            value={value.branchId != null ? String(value.branchId) : allOption ? "all" : ""}
            onValueChange={(v) =>
              onChange({ ...value, branchId: v === "all" ? null : Number(v) })
            }
          >
            <SelectTrigger className={triggerClass}>
              <SelectValue placeholder={t("filters.branch")} />
            </SelectTrigger>
            <SelectContent>
              {allOption && <SelectItem value="all">{t("scope.allBranches")}</SelectItem>}
              {school.branches.map((b) => (
                <SelectItem key={b.id} value={String(b.id)}>
                  {b.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      )}
    </div>
  )
}
