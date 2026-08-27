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
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"

/**
 * The school-wide workspace's branch targeting. School managers (principals)
 * work from the "All branches" context and NAME the branch a write applies to
 * instead of switching workspaces — the backend resolves it via the payload's
 * `branch_id` (Controller::targetBranch). This hook says whether the current
 * context needs that explicit choice, and which branches can be chosen.
 */
export function useBranchScope() {
  const { options, active, isPlatform } = useSchoolContext()

  // Branch-anchored writes need an explicit target only in a school-wide
  // (no-branch) school context. Platform staff have no branch catalog here.
  const needsBranch = !isPlatform && active.schoolId !== null && active.branchId === null

  const branches = useMemo(
    () =>
      options
        .filter((o) => o.schoolId === active.schoolId && o.branchId !== null)
        .map((o) => ({ id: o.branchId as number, name: o.branchName ?? "" })),
    [options, active.schoolId],
  )

  return { needsBranch, branches, activeBranchId: active.branchId }
}

interface BranchFieldProps {
  value: number | null
  onChange: (branchId: number | null) => void
  /** Marks the label with the required asterisk styling (default true). */
  required?: boolean
  error?: string | null
  className?: string
}

/**
 * "Branch" select for forms that create branch-anchored data. Renders nothing
 * in a concrete branch context (the workspace already names the branch) —
 * call sites mount it unconditionally and send `branch_id` when it has a
 * value. Pair with useBranchScope().needsBranch to block submit when unset.
 */
export function BranchField({ value, onChange, required = true, error, className }: BranchFieldProps) {
  const { needsBranch, branches } = useBranchScope()
  const { t } = useTranslation("common")

  if (!needsBranch) return null

  return (
    <div className={className ?? "space-y-2"}>
      <Label>
        {t("branchField.label")}
        {required && <span className="text-destructive"> *</span>}
      </Label>
      <Select
        value={value != null ? String(value) : ""}
        onValueChange={(v) => onChange(v ? Number(v) : null)}
      >
        <SelectTrigger className="w-full">
          <SelectValue placeholder={t("branchField.placeholder")} />
        </SelectTrigger>
        <SelectContent emptyNotice={t("emptySelect.branches")}>
          {branches.map((b) => (
            <SelectItem key={b.id} value={String(b.id)}>
              {b.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {branches.length === 0 ? (
        <p className="text-warning text-xs">{t("emptySelect.branches")}</p>
      ) : null}
      {error ? <p className="text-destructive text-xs">{error}</p> : null}
    </div>
  )
}

const ALL_BRANCHES = "__all__"

/**
 * Page-level branch picker for register-style screens (staff attendance, HR
 * reports…) that are inherently one-branch-at-a-time. Same source of truth as
 * BranchField, styled as a toolbar control; renders nothing when the
 * workspace already names a branch. `allOption` turns it into a narrowing
 * FILTER (an "All branches" row maps back to null) for report-style pages
 * that also make sense school-wide.
 */
export function BranchScopePicker({
  value,
  onChange,
  className,
  allOption = false,
}: Omit<BranchFieldProps, "required" | "error"> & { allOption?: boolean }) {
  const { needsBranch, branches } = useBranchScope()
  const { t } = useTranslation("common")

  if (!needsBranch) return null

  return (
    <Select
      value={value != null ? String(value) : allOption ? ALL_BRANCHES : ""}
      onValueChange={(v) => onChange(v && v !== ALL_BRANCHES ? Number(v) : null)}
    >
      <SelectTrigger className={className ?? "h-9 w-full md:w-56"}>
        <SelectValue placeholder={t("branchField.placeholder")} />
      </SelectTrigger>
      <SelectContent emptyNotice={t("emptySelect.branches")}>
        {allOption && <SelectItem value={ALL_BRANCHES}>{t("scope.allBranches")}</SelectItem>}
        {branches.map((b) => (
          <SelectItem key={b.id} value={String(b.id)}>
            {b.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
