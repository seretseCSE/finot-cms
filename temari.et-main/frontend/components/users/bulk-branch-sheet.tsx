"use client"

import { Ban, Check, UserPlus } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
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
import { BRANCH_ROLE_OPTIONS } from "@/lib/roles"
import type { AdminUser } from "@/lib/types"
import { cn } from "@/lib/utils"

import { reportBulkResult, type BulkSkip } from "@/components/ui/bulk-actions"

interface BranchChoice {
  id: number
  name: string
  schoolName: string | null
}

type Mode = "assign" | "activate" | "deactivate"

interface Props {
  users: AdminUser[]
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Branches the acting admin may work in. */
  branchOptions: BranchChoice[]
  /** Assigning users to a branch — principals/school admins only. */
  canAssign: boolean
  /** Turning existing branch access on/off — principals and directors. */
  canManageAccess: boolean
  /** Called after a successful run so the list can refresh. */
  onDone: () => void
}

/**
 * Branch access for a hand-picked set of users: put them all in one branch, or
 * sweep access on/off there. Same authority rules as the single-user sheet,
 * applied per row server-side — anyone out of reach is skipped and reported,
 * so one protected row never blocks the batch.
 */
export function BulkBranchSheet({
  users,
  open,
  onOpenChange,
  branchOptions,
  canAssign,
  canManageAccess,
  onDone,
}: Props) {
  const { t } = useTranslation("users")
  const { t: tc } = useTranslation("common")

  const modes = useMemo(() => {
    const list: { key: Mode; icon: typeof UserPlus; label: string }[] = []
    if (canAssign) list.push({ key: "assign", icon: UserPlus, label: t("bulkBranch.modeAssign") })
    if (canManageAccess) {
      list.push({ key: "activate", icon: Check, label: t("bulkBranch.modeActivate") })
      list.push({ key: "deactivate", icon: Ban, label: t("bulkBranch.modeDeactivate") })
    }
    return list
  }, [canAssign, canManageAccess, t])

  const [mode, setMode] = useState<Mode>(modes[0]?.key ?? "assign")
  const [branchId, setBranchId] = useState("")
  const [roles, setRoles] = useState<string[]>([])
  const [working, setWorking] = useState(false)

  // Fresh form every time it opens; a single branch needs no picking.
  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset form on open
    setMode(modes[0]?.key ?? "assign")
    setRoles([])
    setBranchId(branchOptions.length === 1 ? String(branchOptions[0].id) : "")
  }, [open, modes, branchOptions])

  const canSubmit =
    users.length > 0 && branchId !== "" && (mode !== "assign" || roles.length > 0)

  async function submit() {
    if (!canSubmit) return
    setWorking(true)
    const ids = users.map((u) => u.id)
    try {
      if (mode === "assign") {
        const res = await apiFetch<{ meta: { assigned: number; skipped: BulkSkip[] } }>(
          "/users/bulk/memberships",
          { method: "POST", body: { ids, branch_id: Number(branchId), roles } },
        )
        reportBulkResult(
          res.meta.assigned,
          res.meta.skipped,
          t("bulkBranch.assigned", { count: res.meta.assigned }),
          tc,
        )
      } else {
        const res = await apiFetch<{ meta: { updated: number; skipped: BulkSkip[] } }>(
          "/users/bulk/branch-access",
          {
            method: "POST",
            body: { ids, branch_id: Number(branchId), is_active: mode === "activate" },
          },
        )
        reportBulkResult(
          res.meta.updated,
          res.meta.skipped,
          mode === "activate"
            ? t("bulkBranch.activated", { count: res.meta.updated })
            : t("bulkBranch.deactivated", { count: res.meta.updated }),
          tc,
        )
      }
      onOpenChange(false)
      onDone()
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setWorking(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("bulkBranch.title")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("bulkBranch.desc")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>

        <ResponsiveSheetBody className="space-y-5">
          {/* Who is affected — a compact roll call, so nobody sweeps blind. */}
          <div className="rounded-2xl border px-4 py-3">
            <p className="text-sm font-medium">
              {t("bulkBranch.selectedCount", { count: users.length })}
            </p>
            <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">
              {users
                .slice(0, 6)
                .map((u) => u.name)
                .join(" · ")}
              {users.length > 6 ? ` +${users.length - 6}` : ""}
            </p>
          </div>

          {/* What to do. A director only manages existing access, so they see two. */}
          {modes.length > 1 && (
            <div
              className={cn(
                "grid gap-0 overflow-hidden rounded-xl border",
                modes.length === 3 ? "grid-cols-3" : "grid-cols-2",
              )}
            >
              {modes.map(({ key, icon: Icon, label }) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => setMode(key)}
                  aria-pressed={mode === key}
                  className={cn(
                    "flex min-h-11 items-center justify-center gap-2 px-3 py-2 text-sm font-medium transition-colors",
                    mode === key
                      ? "bg-primary text-primary-foreground"
                      : "bg-card text-muted-foreground",
                  )}
                >
                  <Icon className="size-4 shrink-0" />
                  <span className="truncate">{label}</span>
                </button>
              ))}
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="bulk-branch">{t("assign.branch")}</Label>
            <Select value={branchId} onValueChange={setBranchId}>
              <SelectTrigger id="bulk-branch" className="w-full">
                <SelectValue placeholder={tc("actions.select")} />
              </SelectTrigger>
              <SelectContent emptyNotice={tc("emptySelect.branches")}>
                {branchOptions.map((b) => (
                  <SelectItem key={b.id} value={String(b.id)}>
                    {b.schoolName ? `${b.schoolName} — ${b.name}` : b.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {mode === "assign" && (
            <div className="space-y-2">
              <Label>
                {t("assign.roles")}
                {roles.length > 0 && (
                  <span className="text-muted-foreground ml-1.5 font-normal">
                    {t("assign.rolesSelected", { count: roles.length })}
                  </span>
                )}
              </Label>
              <div className="grid grid-cols-2 gap-1.5">
                {BRANCH_ROLE_OPTIONS.map((r) => {
                  const active = roles.includes(r.value)
                  return (
                    <label
                      key={r.value}
                      className={cn(
                        "flex min-h-11 cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors select-none",
                        active
                          ? "border-primary bg-primary/5 font-medium"
                          : "border-border bg-background hover:bg-muted",
                      )}
                    >
                      <Checkbox
                        checked={active}
                        onCheckedChange={(checked) =>
                          setRoles((prev) =>
                            checked ? [...prev, r.value] : prev.filter((v) => v !== r.value),
                          )
                        }
                      />
                      <span className="truncate">{r.label}</span>
                    </label>
                  )
                })}
              </div>
              <p className="text-muted-foreground text-xs">{t("bulkBranch.rolesHint")}</p>
            </div>
          )}

          {mode === "deactivate" && (
            <p className="text-muted-foreground text-xs">{t("bulkBranch.deactivateHint")}</p>
          )}
        </ResponsiveSheetBody>

        <ResponsiveSheetFooter>
          <Button
            variant="outline"
            className="h-11 flex-1"
            disabled={working}
            onClick={() => onOpenChange(false)}
          >
            {t("confirm.cancel")}
          </Button>
          <Button
            className="h-11 flex-1"
            variant={mode === "deactivate" ? "destructive" : "default"}
            loading={working}
            disabled={!canSubmit}
            onClick={submit}
          >
            {mode === "assign"
              ? t("assign.submit")
              : mode === "activate"
                ? t("bulkBranch.modeActivate")
                : t("bulkBranch.modeDeactivate")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
