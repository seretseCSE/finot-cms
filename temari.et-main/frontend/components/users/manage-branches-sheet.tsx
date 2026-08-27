"use client"

import { Ban, Check, Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useTranslation } from "@/lib/i18n"
import { BRANCH_ROLE_OPTIONS, roleLabel } from "@/lib/roles"
import type { AdminUser, UserBranchRef } from "@/lib/types"
import { cn } from "@/lib/utils"

interface BranchChoice {
  id: number
  name: string
  schoolName: string | null
}

interface Props {
  user: AdminUser | null
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Branches the acting admin may assign the user into. */
  branchOptions: BranchChoice[]
  /**
   * Whether the actor may assign the user to a branch. Directors can only manage
   * access for people already in their branch, so they never see the assign form.
   */
  canAssign: boolean
  /** Called after any change so the parent can refresh the list. */
  onUpdated: () => void
}

export function ManageBranchesSheet({ user, open, onOpenChange, branchOptions, canAssign, onUpdated }: Props) {
  const { t } = useTranslation("users")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const [branches, setBranches] = useState<UserBranchRef[]>([])
  const [busy, setBusy] = useState<number | null>(null)
  const [assignBranch, setAssignBranch] = useState<string>("")
  const [assignRoles, setAssignRoles] = useState<string[]>([])
  const [assigning, setAssigning] = useState(false)

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- seed local list when opened
    if (open && user) setBranches(user.branches)
  }, [open, user])

  if (!user) return null

  async function run(
    fn: () => Promise<{ data: AdminUser }>,
    membershipId: number | null,
    successMessage: string,
  ) {
    setBusy(membershipId)
    try {
      const res = await fn()
      setBranches(res.data.branches)
      onUpdated()
      toast.success(successMessage)
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      setBusy(null)
    }
  }

  async function toggle(b: UserBranchRef) {
    await run(
      () =>
        apiFetch<{ data: AdminUser }>(`/memberships/${b.membership_id}/status`, {
          method: "PATCH",
          body: { is_active: !b.membership_active },
        }),
      b.membership_id,
      t("toast.membershipUpdated"),
    )
  }

  async function remove(b: UserBranchRef) {
    await run(
      () => apiFetch<{ data: AdminUser }>(`/memberships/${b.membership_id}`, { method: "DELETE" }),
      b.membership_id,
      t("toast.removed"),
    )
  }

  async function assign() {
    if (!assignBranch || assignRoles.length === 0) return
    setAssigning(true)
    // One membership per role (a branch membership is keyed by role), assigned
    // sequentially. Keep the latest user snapshot so a mid-way failure still
    // reflects the roles that did get assigned.
    let latest: AdminUser | null = null
    try {
      for (const role of assignRoles) {
        const res = await apiFetch<{ data: AdminUser }>(`/users/${user!.id}/memberships`, {
          method: "POST",
          body: { branch_id: Number(assignBranch), role },
        })
        latest = res.data
      }
      setAssignBranch("")
      setAssignRoles([])
      toast.success(t("toast.assigned"))
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : t("toast.error"))
    } finally {
      if (latest) {
        setBranches(latest.branches)
        onUpdated()
      }
      setAssigning(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        {confirmDialog}
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{user.name}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{user.phone}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-6">
          {/* Current branches */}
          <div className="space-y-2">
            <p className="text-sm font-medium">{t("columns.branches")}</p>
            {branches.length === 0 ? (
              <p className="text-sm text-muted-foreground">—</p>
            ) : (
              <ul className="space-y-2">
                {branches.map((b) => (
                  <li
                    key={b.membership_id}
                    className="flex items-center gap-2 rounded-lg border px-3 py-2"
                  >
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{b.name}</p>
                      <p className="text-xs text-muted-foreground capitalize">{roleLabel(b.role)}</p>
                    </div>
                    <Badge variant={b.membership_active ? "default" : "secondary"} className="text-xs">
                      {b.membership_active ? t("status.active") : t("status.inactive")}
                    </Badge>
                    {/* Action buttons only when the actor may manage this membership;
                        peers/higher roles and out-of-scope branches stay read-only. */}
                    {b.can_manage ? (
                      <>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="size-8"
                          title={b.membership_active ? t("actions.deactivateInBranch") : t("actions.activateInBranch")}
                          loading={busy === b.membership_id}
                          onClick={() => toggle(b)}
                        >
                          {b.membership_active ? <Ban className="size-4" /> : <Check className="size-4" />}
                        </Button>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="size-8 text-destructive hover:text-destructive"
                          title={t("actions.removeFromBranch")}
                          loading={busy === b.membership_id}
                          onClick={() =>
                            confirmDelete(() => remove(b), tc("confirmDelete.named", { name: b.name }))
                          }
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </>
                    ) : (
                      <span className="text-muted-foreground px-1 text-xs">{t("manage.readOnly")}</span>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Assign to a branch — hidden for actors who can only manage existing access. */}
          {canAssign && (
          <div className="space-y-3 rounded-lg border p-3">
            <p className="text-sm font-medium">{t("assign.title")}</p>
            <div className="space-y-2">
              <label className="text-xs text-muted-foreground">{t("assign.branch")}</label>
              <select
                value={assignBranch}
                onChange={(e) => setAssignBranch(e.target.value)}
                className="h-10 w-full rounded-lg border bg-background px-3 text-sm"
              >
                <option value="">—</option>
                {branchOptions.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.schoolName ? `${b.schoolName} — ${b.name}` : b.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <label className="text-xs text-muted-foreground">
                {t("assign.roles")}
                {assignRoles.length > 0 && (
                  <span className="ml-1.5">{t("assign.rolesSelected", { count: assignRoles.length })}</span>
                )}
              </label>
              <div className="grid grid-cols-2 gap-1.5">
                {BRANCH_ROLE_OPTIONS.map((r) => {
                  const active = assignRoles.includes(r.value)
                  return (
                    <label
                      key={r.value}
                      className={cn(
                        "flex min-h-11 cursor-pointer select-none items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors",
                        active
                          ? "border-primary bg-primary/5 font-medium"
                          : "border-border bg-background hover:bg-muted",
                      )}
                    >
                      <Checkbox
                        checked={active}
                        onCheckedChange={(checked) =>
                          setAssignRoles((prev) =>
                            checked ? [...prev, r.value] : prev.filter((v) => v !== r.value),
                          )
                        }
                      />
                      <span className="truncate">{r.label}</span>
                    </label>
                  )
                })}
              </div>
            </div>
            <Button
              type="button"
              className="h-10 w-full"
              loading={assigning} disabled={!assignBranch || assignRoles.length === 0}
              onClick={assign}
            >
              <Plus className="size-4" />
              {t("assign.submit")}
            </Button>
          </div>
          )}
        </ResponsiveSheetBody>
        <ResponsiveSheetFooter>
          <Button variant="outline" className="flex-1 h-11" onClick={() => onOpenChange(false)}>
            {t("confirm.cancel")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
