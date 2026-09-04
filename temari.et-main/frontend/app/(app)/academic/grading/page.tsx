"use client"

import { Pencil, Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { GradingPolicySheet } from "@/components/grading/policy-sheet"
import { GradingScaleSheet } from "@/components/grading/scale-sheet"
import { Badge } from "@/components/ui/badge"
import { BranchScopePicker } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { GradeLevel, GradingPolicy, GradingScale } from "@/lib/types"
import { cn } from "@/lib/utils"

/** Grade-level name for a sort_order bound, or the "open" label. */
function gradeAt(gradeLevels: GradeLevel[], sort: number | null, openLabel: string): string {
  if (sort === null) return openLabel
  return gradeLevels.find((g) => g.sort_order === sort)?.name ?? `#${sort}`
}

export default function GradingSettingsPage() {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [scales, setScales] = useState<GradingScale[] | null>(null)
  const [policies, setPolicies] = useState<GradingPolicy[] | null>(null)
  // Branch-scoped grade offering, session-cached across pages.
  const { grades: gradeLevels } = useGradeLevels()
  const [reloadKey, setReloadKey] = useState(0)

  // School-wide lens over the policy list: one branch's effective policy set
  // is its own override rows PLUS the school-wide defaults. Scales stay
  // unfiltered — they are one definition for every branch by design.
  const [policyBranchId, setPolicyBranchId] = useState<number | null>(null)
  const visiblePolicies = (policies ?? []).filter(
    (p) => policyBranchId === null || p.branch_id === null || p.branch_id === policyBranchId,
  )

  const [editingScale, setEditingScale] = useState<GradingScale | null>(null)
  const [scaleSheetOpen, setScaleSheetOpen] = useState(false)
  const [editingPolicy, setEditingPolicy] = useState<GradingPolicy | null>(null)
  const [policySheetOpen, setPolicySheetOpen] = useState(false)

  const canManage = permissions.includes("grades.manage")
  const hasWorkspace = !isPlatform && active.schoolId !== null

  useEffect(() => {
    if (!hasWorkspace) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to loading on context switch
    setScales(null)
    setPolicies(null)
    Promise.all([
      apiFetch<{ data: GradingScale[] }>("/grading-scales?all=1"),
      apiFetch<{ data: GradingPolicy[] }>("/grading-policies"),
    ])
      .then(([s, p]) => {
        if (cancelled) return
        setScales(s.data)
        setPolicies(p.data)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setScales([])
        setPolicies([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [hasWorkspace, active.schoolId, active.branchId, reloadKey])

  const reload = () => setReloadKey((k) => k + 1)

  async function deleteScale(scale: GradingScale) {
    try {
      await apiFetch(`/grading-scales/${scale.id}`, { method: "DELETE" })
      toast.success(t("scales.deleted"))
      reload()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  async function deletePolicy(policy: GradingPolicy) {
    try {
      await apiFetch(`/grading-policies/${policy.id}`, { method: "DELETE" })
      toast.success(t("policies.removed"))
      reload()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    }
  }

  if (!hasWorkspace) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("settings.title")} description={t("settings.subtitle")} />
        <div className="page-gutter">
          <div className="text-muted-foreground rounded-2xl border border-dashed px-6 py-12 text-center text-sm">
            {t("noBranch")}
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-8 pb-10">
      {confirmDialog}
      <PageHeader title={t("settings.title")} description={t("settings.subtitle")} />

      {/* ── Grading scales ─────────────────────────────────────────── */}
      <section className="page-gutter space-y-3">
        <div className="flex items-center justify-between gap-3">
          <h2 className="text-sm font-semibold">{t("settings.scalesTab")}</h2>
          {canManage && (
            <Button
              size="sm"
              onClick={() => {
                setEditingScale(null)
                setScaleSheetOpen(true)
              }}
            >
              <Plus className="size-4" />
              {t("scales.add")}
            </Button>
          )}
        </div>

        {scales === null ? (
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {[0, 1, 2].map((i) => (
              <Skeleton key={i} className="h-48 rounded-2xl" />
            ))}
          </div>
        ) : scales.length === 0 ? (
          <EmptyState title={t("scales.empty")} />
        ) : (
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {scales.map((scale) => (
              <article
                key={scale.id}
                className={cn(
                  "bg-card rounded-2xl border p-4",
                  !scale.is_active && "opacity-60",
                )}
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <h3 className="truncate text-sm font-semibold">{scale.name}</h3>
                    <p className="text-muted-foreground mt-0.5 text-xs">
                      {scale.is_platform ? t("scales.platform") : t("scales.custom")}
                      {!scale.is_active && ` · ${tc("states.inactive")}`}
                    </p>
                  </div>
                  {canManage && !scale.is_platform && (
                    <div className="flex shrink-0 items-center gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={tc("actions.edit")}
                        onClick={() => {
                          setEditingScale(scale)
                          setScaleSheetOpen(true)
                        }}
                      >
                        <Pencil className="size-3.5" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        className="text-muted-foreground hover:text-destructive size-8"
                        aria-label={tc("actions.delete")}
                        onClick={() =>
                          confirmDelete(
                            () => deleteScale(scale),
                            tc("confirmDelete.named", { name: scale.name }),
                          )
                        }
                      >
                        <Trash2 className="size-3.5" />
                      </Button>
                    </div>
                  )}
                </div>

                <ul className="mt-3 space-y-1">
                  {scale.bands.map((band, i) => (
                    <li
                      key={band.id ?? i}
                      className="flex items-center justify-between gap-2 text-xs"
                    >
                      <span className="text-muted-foreground tabular-nums">
                        {band.min_score}–{band.max_score}
                      </span>
                      <span className="flex items-center gap-1.5">
                        {band.letter && (
                          <Badge variant="secondary" className="h-5 min-w-7 justify-center px-1.5">
                            {band.letter}
                          </Badge>
                        )}
                        <span className={cn(!band.is_passing && "text-destructive")}>
                          {band.label}
                        </span>
                      </span>
                    </li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
        )}
      </section>

      {/* ── Where scales apply ─────────────────────────────────────── */}
      <section className="page-gutter space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-sm font-semibold">{t("settings.policiesTab")}</h2>
            <p className="text-muted-foreground mt-0.5 text-xs">{t("policies.fallbackNote")}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {/* School-wide workspace: narrow the policy list to one branch's
                effective set (its overrides + the school-wide defaults). */}
            <BranchScopePicker
              value={policyBranchId}
              onChange={setPolicyBranchId}
              allOption
              className="h-8 w-full md:w-48"
            />
            {canManage && (
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  setEditingPolicy(null)
                  setPolicySheetOpen(true)
                }}
              >
                <Plus className="size-4" />
                {t("policies.add")}
              </Button>
            )}
          </div>
        </div>

        {policies === null ? (
          <Skeleton className="h-32 rounded-2xl" />
        ) : visiblePolicies.length === 0 ? (
          <EmptyState title={t("policies.empty")} />
        ) : (
          <div className="bg-card divide-y rounded-2xl border">
            {visiblePolicies.map((policy) => (
              <div
                key={policy.id}
                className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0 space-y-0.5">
                  <p className="text-sm font-medium">
                    {policy.scale?.name ?? "—"}
                    <span className="text-muted-foreground font-normal">
                      {" · "}
                      {t(`policies.displays.${policy.display}`)}
                    </span>
                  </p>
                  <p className="text-muted-foreground text-xs">
                    {policy.branch_id === null
                      ? t("policies.schoolWide")
                      : (policy.branch_name ?? "—")}
                    {" · "}
                    {policy.min_grade_sort === null && policy.max_grade_sort === null
                      ? t("policies.allGrades")
                      : `${gradeAt(gradeLevels, policy.min_grade_sort, t("policies.openEnded"))} → ${gradeAt(gradeLevels, policy.max_grade_sort, t("policies.openEnded"))}`}
                  </p>
                </div>
                {canManage && (
                  <div className="flex shrink-0 items-center gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="size-8"
                      aria-label={tc("actions.edit")}
                      onClick={() => {
                        setEditingPolicy(policy)
                        setPolicySheetOpen(true)
                      }}
                    >
                      <Pencil className="size-3.5" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="text-muted-foreground hover:text-destructive size-8"
                      aria-label={tc("actions.delete")}
                      onClick={() =>
                        confirmDelete(
                          () => deletePolicy(policy),
                          tc("confirmDelete.named", { name: policy.scale?.name ?? "" }),
                        )
                      }
                    >
                      <Trash2 className="size-3.5" />
                    </Button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </section>

      <GradingScaleSheet
        scale={editingScale}
        open={scaleSheetOpen}
        onOpenChange={setScaleSheetOpen}
        onSaved={reload}
      />
      <GradingPolicySheet
        policy={editingPolicy}
        scales={scales ?? []}
        gradeLevels={gradeLevels}
        open={policySheetOpen}
        onOpenChange={setPolicySheetOpen}
        onSaved={reload}
      />
    </div>
  )
}
