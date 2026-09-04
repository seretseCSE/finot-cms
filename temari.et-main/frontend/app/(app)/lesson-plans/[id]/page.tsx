"use client"

import {
  BookOpen,
  CalendarRange,
  Check,
  Circle,
  CircleCheck,
  FileDown,
  Gauge,
  LayoutDashboard,
  Map,
  Pencil,
  Plus,
  RotateCcw,
  Send,
  Sparkles,
  Target,
  Trash2,
  Users,
  X,
} from "lucide-react"
import {
  useParams,
  usePathname,
  useRouter,
  useSearchParams,
} from "next/navigation"
import { useCallback, useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { AiUnitsSheet } from "@/components/lesson-plans/ai-units-sheet"
import { DeclineDialog } from "@/components/lesson-plans/decline-dialog"
import {
  addWeeks,
  fmtDay,
  mondayOf,
  PLAN_STATUS_BADGE,
  PlanStatusBadge,
  ProgressBar,
} from "@/components/lesson-plans/shared"
import { UnitSheet } from "@/components/lesson-plans/unit-sheet"
import { WeekEditor } from "@/components/lesson-plans/week-editor"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { EmptyState } from "@/components/ui/empty-state"
import { Label } from "@/components/ui/label"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, type ProfileTab } from "@/components/ui/profile-tabs"
import { RichTextEditor } from "@/components/ui/rich-text"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { sanitizeHtml, stripHtml } from "@/lib/sanitize-html"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useLocale, useTranslation } from "@/lib/i18n"
import { useDocumentDownload } from "@/lib/use-document"
import type { AnnualLessonPlanRow, AnnualPlanUnit } from "@/lib/types"
import { cn } from "@/lib/utils"

const TAB_KEYS = ["overview", "units", "weeks"] as const

/**
 * The annual-plan workspace, in three tabs: Overview (status, pacing hero,
 * goals & methods), Units (the AI-draftable MoE roadmap) and Weekly plans
 * (the rail + composer, ?week= deep-links straight in). One page serves
 * both hats: the owning teacher edits and submits; the director/principal
 * reviews and decides.
 */
export default function LessonPlanPage() {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { locale } = useLocale()
  const { active } = useSchoolContext()
  const params = useParams<{ id: string }>()
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { print, generating } = useDocumentDownload()

  const weekParam = searchParams.get("week")
  const tabParam = searchParams.get("tab") as (typeof TAB_KEYS)[number] | null
  const tab =
    tabParam !== null && TAB_KEYS.includes(tabParam) ? tabParam : "overview"
  // An open week always shows the weeks tab — deep links keep working.
  const activeTab = weekParam !== null ? "weeks" : tab

  const [plan, setPlan] = useState<AnnualLessonPlanRow | null | undefined>(
    undefined
  )
  const [working, setWorking] = useState(false)
  const [declineOpen, setDeclineOpen] = useState(false)
  const [unitSheetOpen, setUnitSheetOpen] = useState(false)
  const [aiOpen, setAiOpen] = useState(false)
  const [editingUnit, setEditingUnit] = useState<AnnualPlanUnit | null>(null)
  const [editingDetails, setEditingDetails] = useState(false)
  const [goals, setGoals] = useState("")
  const [methods, setMethods] = useState("")
  // Both editors report uploads here — Save stays disabled until the LAST
  // in-flight image lands, not just the most recent one.
  const uploadCount = useRef(0)
  const [imgUploading, setImgUploading] = useState(false)
  const trackUploading = (up: boolean) => {
    uploadCount.current = Math.max(0, uploadCount.current + (up ? 1 : -1))
    setImgUploading(uploadCount.current > 0)
  }

  const load = useCallback(() => {
    let cancelled = false
    apiFetch<{ data: AnnualLessonPlanRow }>(`/lesson-plans/${params.id}`)
      .then((res) => !cancelled && setPlan(res.data))
      .catch(() => !cancelled && setPlan(null))
    return () => {
      cancelled = true
    }
  }, [params.id])

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on navigation
    setPlan(undefined)
    return load()
  }, [load, active.schoolId, active.branchId])

  // One atomic query write — tab and week live in the same URL, so they must
  // be mutated together in a SINGLE router.replace. Two separate writers each
  // reading a stale searchParams snapshot would clobber one another (last one
  // wins), which is what made tab switches silently no-op after printing.
  const applyQuery = useCallback(
    (mutate: (params: URLSearchParams) => void) => {
      const next = new URLSearchParams(searchParams.toString())
      mutate(next)
      const query = next.toString()
      router.replace(query ? `${pathname}?${query}` : pathname, {
        scroll: false,
      })
    },
    [router, pathname, searchParams]
  )

  const setWeek = useCallback(
    (week: string | null) =>
      applyQuery((p) => (week === null ? p.delete("week") : p.set("week", week))),
    [applyQuery]
  )

  /** Tab switch; leaving the weeks tab also closes the open week. */
  const switchTab = useCallback(
    (next: (typeof TAB_KEYS)[number]) =>
      applyQuery((p) => {
        if (next !== "weeks") p.delete("week")
        if (next === "overview") p.delete("tab")
        else p.set("tab", next)
      }),
    [applyQuery]
  )

  async function workflow(
    verb: "submit" | "approve" | "reopen",
    successKey: string
  ) {
    if (!plan) return
    setWorking(true)
    try {
      await apiFetch(`/lesson-plans/${plan.id}/${verb}`, { method: "POST" })
      toast.success(t(successKey))
      load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setWorking(false)
    }
  }

  async function decline(reason: string) {
    if (!plan) return
    try {
      await apiFetch(`/lesson-plans/${plan.id}/decline`, {
        method: "POST",
        body: { reason },
      })
      toast.success(t("plan.declined"))
      load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  async function uploadImage(file: File) {
    const form = new FormData()
    form.append("file", file)
    const res = await apiFetch<{ data: { url: string; path: string } }>(
      "/lms/uploads",
      { method: "POST", body: form }
    )
    return res.data
  }

  async function saveDetails() {
    if (!plan) return
    setWorking(true)
    try {
      await apiFetch(`/lesson-plans/${plan.id}`, {
        method: "PUT",
        body: {
          goals: stripHtml(goals).trim() ? goals : null,
          methods: stripHtml(methods).trim() ? methods : null,
        },
      })
      setEditingDetails(false)
      load()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setWorking(false)
    }
  }

  function deletePlan() {
    if (!plan) return
    confirmDelete(async () => {
      await apiFetch(`/lesson-plans/${plan.id}`, { method: "DELETE" })
      toast.success(t("plan.deleted"))
      router.replace("/lesson-plans")
    }, t("plan.confirmDelete"))
  }

  function deleteUnit(unit: AnnualPlanUnit) {
    confirmDelete(async () => {
      await apiFetch(`/plan-units/${unit.id}`, { method: "DELETE" })
      toast.success(t("unit.removed"))
      load()
    }, t("unit.confirmDelete"))
  }

  if (plan === undefined) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("title")} backHref="/lesson-plans" />
        <div className="page-gutter space-y-3">
          <Skeleton className="h-28 w-full rounded-2xl" />
          <Skeleton className="h-64 w-full rounded-2xl" />
        </div>
      </div>
    )
  }

  if (plan === null) {
    return (
      <div className="space-y-6">
        <PageHeader title={t("title")} backHref="/lesson-plans" />
        <div className="page-gutter">
          <EmptyState icon={Map} title={tc("errors.generic")} />
        </div>
      </div>
    )
  }

  const editable =
    plan.is_own && (plan.status === "draft" || plan.status === "declined")
  const units = plan.units ?? []
  const weeks = plan.weekly_plans ?? []
  const pacing = plan.pacing
  const sections = plan.sections ?? []

  // The week rail spans planned weeks + the next unplanned one.
  const thisWeek = mondayOf(new Date())
  const railWeeks = [
    ...new Set([...weeks.map((w) => w.week_starts_on), thisWeek]),
  ].sort()
  const lastRail = railWeeks[railWeeks.length - 1]
  if (plan.status === "approved" && plan.is_own && lastRail <= thisWeek) {
    railWeeks.push(addWeeks(thisWeek, 1))
  }

  const tabs: ProfileTab<(typeof TAB_KEYS)[number]>[] = [
    { key: "overview", label: t("plan.tabs.overview"), icon: LayoutDashboard },
    { key: "units", label: t("plan.tabs.units"), icon: Map },
    { key: "weeks", label: t("plan.tabs.weeks"), icon: CalendarRange },
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title={`${plan.subject.name ?? ""} · ${plan.grade_level.name ?? ""}`}
        description={[
          plan.academic_year.name,
          plan.teacher_name,
          plan.periods_per_week !== null
            ? `${plan.periods_per_week} ${t("plan.periodsPerWeek")}`
            : null,
          plan.total_periods !== null
            ? `${plan.total_periods} ${t("plan.totalPeriods")}`
            : null,
        ]
          .filter(Boolean)
          .join(" · ")}
        backHref="/lesson-plans"
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <PlanStatusBadge status={plan.status} />
            <Button
              variant="outline"
              className="h-10"
              loading={generating}
              onClick={() => print("annual_plan", plan.id)}
              title={t("plan.print")}
              aria-label={t("plan.print")}
            >
              <FileDown className="size-4" />
              <span className="hidden sm:inline">{t("plan.print")}</span>
            </Button>
            {plan.is_own &&
              (plan.status === "draft" || plan.status === "declined") && (
                <Button
                  className="h-10"
                  loading={working}
                  disabled={units.length === 0}
                  onClick={() => workflow("submit", "plan.submitted")}
                >
                  <Send className="size-4" />
                  {t("plan.submit")}
                </Button>
              )}
            {plan.can_review && plan.status === "submitted" && (
              <>
                <Button
                  className="h-10"
                  loading={working}
                  onClick={() => workflow("approve", "plan.approved")}
                >
                  <Check className="size-4" />
                  {t("plan.approve")}
                </Button>
                <Button
                  variant="outline"
                  className="h-10 text-destructive"
                  loading={working}
                  onClick={() => setDeclineOpen(true)}
                >
                  <X className="size-4" />
                  {t("plan.decline")}
                </Button>
              </>
            )}
            {plan.is_own && plan.status === "submitted" && (
              <Button
                variant="outline"
                className="h-10"
                loading={working}
                onClick={() => workflow("reopen", "plan.reopened")}
              >
                <RotateCcw className="size-4" />
                {t("plan.withdraw")}
              </Button>
            )}
            {plan.can_review && plan.status === "approved" && (
              <Button
                variant="outline"
                className="h-10"
                loading={working}
                onClick={() => workflow("reopen", "plan.reopened")}
              >
                <RotateCcw className="size-4" />
                {t("plan.reopen")}
              </Button>
            )}
            {plan.is_own && plan.status === "draft" && (
              <Button
                variant="ghost"
                size="icon"
                className="size-10 text-destructive"
                onClick={deletePlan}
                title={t("plan.delete")}
                aria-label={t("plan.delete")}
              >
                <Trash2 className="size-4" />
              </Button>
            )}
          </div>
        }
      />

      <div className="page-gutter space-y-5">
        {/* ── Workflow banners (visible on every tab) ── */}
        {plan.status === "declined" && plan.decline_reason && (
          <div className="rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm">
            <p className="font-medium text-destructive">
              {t("plan.declinedBanner", { name: plan.decided_by_name ?? "—" })}
            </p>
            <p className="mt-0.5 text-foreground/80">{plan.decline_reason}</p>
          </div>
        )}
        {plan.status === "approved" && plan.is_own && (
          <div className="rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
            {t("plan.approvedBanner", {
              name: plan.decided_by_name ?? "—",
              date: fmtDay(plan.decided_at?.slice(0, 10) ?? null, locale),
            })}
          </div>
        )}

        <ProfileTabBar tabs={tabs} value={activeTab} onChange={switchTab} />

        {/* ═══ Overview ═══ */}
        {activeTab === "overview" && (
          <div className="space-y-5">
            {/* Pacing hero (approved, dated plans) or the getting-started path */}
            {pacing && pacing.planned_periods > 0 ? (
              <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                <div className="flex items-center gap-4 md:gap-6">
                  <ProgressRing
                    percent={pacing.progress_percent}
                    behind={pacing.lag_periods > 0}
                  />
                  <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        <Gauge className="size-3.5" />
                        {t("plan.pacing")}
                      </p>
                      <p
                        className={cn(
                          "text-xs font-medium",
                          pacing.lag_periods > 0
                            ? "text-warning"
                            : "text-success"
                        )}
                      >
                        {pacing.lag_periods > 0
                          ? `${t("plan.lagPeriods")} ${pacing.lag_periods}`
                          : t("plan.onTrack")}
                      </p>
                    </div>
                    <ProgressBar
                      percent={pacing.progress_percent}
                      behind={pacing.lag_periods > 0}
                    />
                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground tabular-nums">
                      <span>
                        {t("plan.coveredPeriods")}: {pacing.covered_periods}
                      </span>
                      <span>
                        {t("plan.expectedPeriods")}: {pacing.expected_periods}
                      </span>
                      <span>
                        {t("plan.plannedPeriods")}: {pacing.planned_periods}
                      </span>
                    </div>
                  </div>
                </div>
              </section>
            ) : (
              plan.is_own &&
              plan.status !== "approved" && (
                <section className="rounded-2xl border bg-card p-4 shadow-xs md:p-5">
                  <p className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    {t("plan.steps.title")}
                  </p>
                  <ol className="space-y-2.5">
                    <PlanStep
                      done={units.length > 0}
                      label={t("plan.steps.units")}
                      onClick={() => switchTab("units")}
                    />
                    <PlanStep
                      done={plan.status === "submitted"}
                      label={t("plan.steps.submit")}
                    />
                    <PlanStep
                      done={weeks.length > 0}
                      label={t("plan.steps.weeks")}
                      onClick={() => switchTab("weeks")}
                    />
                  </ol>
                </section>
              )
            )}

            {/* Quick stats */}
            <div className="grid grid-cols-3 gap-2.5">
              <StatTile
                icon={Map}
                value={units.length}
                label={t("plan.tabs.units")}
                onClick={() => switchTab("units")}
              />
              <StatTile
                icon={CalendarRange}
                value={weeks.length}
                label={t("plan.stats.weeksPlanned")}
                onClick={() => switchTab("weeks")}
              />
              <StatTile
                icon={Users}
                value={sections.length}
                label={t("plan.stats.sections")}
                hint={sections.map((s) => s.name).join(", ")}
              />
            </div>

            {/* Goals & methods */}
            <section className="space-y-4 rounded-2xl border bg-card p-4 shadow-xs md:p-5">
              <div className="flex items-center justify-between gap-2">
                <h2 className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  <Target className="size-3.5" />
                  {t("plan.goals")}
                </h2>
                {editable && !editingDetails && (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-9"
                    onClick={() => {
                      setGoals(plan.goals ?? "")
                      setMethods(plan.methods ?? "")
                      setEditingDetails(true)
                    }}
                  >
                    <Pencil className="size-3.5" />
                    {t("plan.editDetails")}
                  </Button>
                )}
              </div>

              {editingDetails ? (
                <div className="space-y-4">
                  <RichTextEditor
                    value={goals}
                    onChange={setGoals}
                    placeholder={t("create.goalsPlaceholder")}
                    onUploadingChange={trackUploading}
                    onUploadImage={async (file) => {
                      const stored = await uploadImage(file)
                      return { url: stored.url, path: stored.path }
                    }}
                  />
                  <div className="space-y-1.5">
                    <Label className="text-xs">{t("plan.methods")}</Label>
                    <RichTextEditor
                      value={methods}
                      onChange={setMethods}
                      placeholder={t("create.methodsPlaceholder")}
                      onUploadingChange={trackUploading}
                      onUploadImage={async (file) => {
                        const stored = await uploadImage(file)
                        return { url: stored.url, path: stored.path }
                      }}
                    />
                  </div>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      className="h-10 flex-1"
                      onClick={() => setEditingDetails(false)}
                    >
                      {tc("actions.cancel")}
                    </Button>
                    <Button
                      className="h-10 flex-1"
                      loading={working}
                      disabled={imgUploading}
                      onClick={saveDetails}
                    >
                      {tc("actions.save")}
                    </Button>
                  </div>
                </div>
              ) : (
                <>
                  {plan.goals ? (
                    <HtmlText html={plan.goals} />
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      {t("plan.noGoals")}
                    </p>
                  )}
                  <h3 className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                    <BookOpen className="size-3.5" />
                    {t("plan.methods")}
                  </h3>
                  {plan.methods ? (
                    <HtmlText html={plan.methods} />
                  ) : (
                    <p className="text-sm text-muted-foreground">
                      {t("plan.noMethods")}
                    </p>
                  )}
                </>
              )}
            </section>
          </div>
        )}

        {/* ═══ Units ═══ */}
        {activeTab === "units" && (
          <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 className="font-display text-lg font-semibold tracking-tight">
                  {t("plan.units")}
                </h2>
                <p className="text-xs text-muted-foreground">
                  {t("plan.unitsHint")}
                </p>
              </div>
              {editable && (
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    className="h-10"
                    onClick={() => setAiOpen(true)}
                  >
                    <Sparkles className="size-4 text-primary" />
                    {t("aiUnits.button")}
                  </Button>
                  <Button
                    variant="outline"
                    className="h-10"
                    onClick={() => {
                      setEditingUnit(null)
                      setUnitSheetOpen(true)
                    }}
                  >
                    <Plus className="size-4" />
                    {t("plan.addUnit")}
                  </Button>
                </div>
              )}
            </div>

            {units.length === 0 ? (
              <div className="rounded-2xl border bg-card shadow-xs">
                <EmptyState
                  compact
                  icon={Map}
                  title={t("plan.noUnits")}
                  description={editable ? t("plan.noUnitsHint") : undefined}
                  action={
                    editable ? (
                      <Button onClick={() => setAiOpen(true)}>
                        <Sparkles className="size-4" />
                        {t("aiUnits.button")}
                      </Button>
                    ) : undefined
                  }
                />
              </div>
            ) : (
              <div className="relative space-y-2.5 before:absolute before:inset-y-4 before:start-[17px] before:w-px before:bg-border">
                {units.map((unit) => (
                  <div key={unit.id} className="relative flex gap-3">
                    <span className="z-[1] mt-3 flex size-9 shrink-0 items-center justify-center rounded-full border bg-card text-xs font-semibold text-foreground/70 shadow-xs tabular-nums">
                      {unit.sequence}
                    </span>
                    <div className="min-w-0 flex-1 rounded-2xl border bg-card p-4 shadow-xs">
                      <div className="flex items-start gap-3">
                        <div className="min-w-0 flex-1">
                          <p className="font-medium">{unit.title}</p>
                          <p className="text-xs text-muted-foreground">
                            {(unit.starts_on || unit.ends_on) && (
                              <>
                                {fmtDay(unit.starts_on, locale)} –{" "}
                                {fmtDay(unit.ends_on, locale)}
                                {" · "}
                              </>
                            )}
                            {unit.planned_periods}{" "}
                            {t("week.periods").toLowerCase()}
                            {unit.page_from !== null && (
                              <>
                                {" · "}
                                {t("plan.unitPages")} {unit.page_from}
                                {unit.page_to !== null &&
                                  unit.page_to !== unit.page_from &&
                                  `–${unit.page_to}`}
                              </>
                            )}
                          </p>
                          {unit.objectives && (
                            <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                              {unit.objectives}
                            </p>
                          )}
                          {(unit.rationale ||
                            unit.prerequisite_knowledge ||
                            unit.teaching_aids ||
                            unit.assessment_techniques) && (
                            <div className="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground">
                              {unit.rationale && (
                                <span className="line-clamp-1">
                                  <span className="font-medium">
                                    {t("plan.unitRationale")}:
                                  </span>{" "}
                                  {unit.rationale}
                                </span>
                              )}
                              {unit.prerequisite_knowledge && (
                                <span className="line-clamp-1">
                                  <span className="font-medium">
                                    {t("plan.unitPrerequisite")}:
                                  </span>{" "}
                                  {unit.prerequisite_knowledge}
                                </span>
                              )}
                              {unit.teaching_aids && (
                                <span className="line-clamp-1">
                                  <span className="font-medium">
                                    {t("plan.unitAids")}:
                                  </span>{" "}
                                  {unit.teaching_aids}
                                </span>
                              )}
                              {unit.assessment_techniques && (
                                <span className="line-clamp-1">
                                  <span className="font-medium">
                                    {t("plan.unitAssessment")}:
                                  </span>{" "}
                                  {unit.assessment_techniques}
                                </span>
                              )}
                            </div>
                          )}
                        </div>
                        {editable && (
                          <div className="flex shrink-0 items-center gap-1">
                            <Button
                              variant="ghost"
                              size="icon"
                              className="size-9"
                              onClick={() => {
                                setEditingUnit(unit)
                                setUnitSheetOpen(true)
                              }}
                              title={t("plan.editUnit")}
                              aria-label={t("plan.editUnit")}
                            >
                              <Pencil className="size-4" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              className="size-9 text-destructive"
                              onClick={() => deleteUnit(unit)}
                              title={t("plan.deleteUnit")}
                              aria-label={t("plan.deleteUnit")}
                            >
                              <Trash2 className="size-4" />
                            </Button>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>
        )}

        {/* ═══ Weekly plans ═══ */}
        {activeTab === "weeks" && (
          <section className="space-y-3">
            <div>
              <h2 className="font-display text-lg font-semibold tracking-tight">
                {t("plan.weeks")}
              </h2>
              <p className="text-xs text-muted-foreground">
                {t("plan.weeksHint")}
              </p>
            </div>

            {weeks.length === 0 && plan.status !== "approved" ? (
              <div className="rounded-2xl border bg-card shadow-xs">
                <EmptyState
                  compact
                  icon={CalendarRange}
                  title={t("plan.noWeeks")}
                  description={t("plan.noWeeksHint")}
                />
              </div>
            ) : (
              <div className="-mx-4 flex scrollbar-none gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0">
                {railWeeks.map((ws) => {
                  const w = weeks.find((x) => x.week_starts_on === ws)
                  const activeWeek = weekParam === ws
                  return (
                    <button
                      key={ws}
                      type="button"
                      onClick={() => setWeek(activeWeek ? null : ws)}
                      className={cn(
                        "touch-target flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition-colors",
                        activeWeek
                          ? "border-primary bg-primary text-primary-foreground"
                          : "bg-card hover:bg-accent"
                      )}
                    >
                      {fmtDay(ws, locale)}
                      {w ? (
                        <span
                          className={cn(
                            "rounded-full border px-1.5 py-0.5 text-[10px] leading-none",
                            activeWeek
                              ? "border-white/40"
                              : PLAN_STATUS_BADGE[w.status]
                          )}
                        >
                          {t(`statuses.${w.status}`)}
                        </span>
                      ) : (
                        <Plus className="size-3.5" />
                      )}
                    </button>
                  )
                })}
              </div>
            )}

            {weekParam && (
              <div className="rounded-2xl border bg-muted/20 p-4 md:p-5">
                <WeekEditor plan={plan} weekStart={weekParam} onChanged={load} />
              </div>
            )}
          </section>
        )}
      </div>

      <UnitSheet
        planId={plan.id}
        plan={plan}
        unit={editingUnit}
        open={unitSheetOpen}
        onOpenChange={setUnitSheetOpen}
        onSaved={load}
      />
      <AiUnitsSheet
        plan={plan}
        open={aiOpen}
        onOpenChange={setAiOpen}
        onSaved={load}
      />
      <DeclineDialog
        open={declineOpen}
        onOpenChange={setDeclineOpen}
        onDecline={decline}
      />
      {confirmDialog}
    </div>
  )
}

/** Compact progress ring for the pacing hero. */
function ProgressRing({
  percent,
  behind,
}: {
  percent: number
  behind: boolean
}) {
  const r = 34
  const c = 2 * Math.PI * r
  const clamped = Math.min(100, Math.max(0, percent))
  return (
    <div className="relative size-20 shrink-0">
      <svg viewBox="0 0 80 80" className="size-20 -rotate-90">
        <circle
          cx="40"
          cy="40"
          r={r}
          fill="none"
          strokeWidth="7"
          className="stroke-muted"
        />
        <circle
          cx="40"
          cy="40"
          r={r}
          fill="none"
          strokeWidth="7"
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={c - (clamped / 100) * c}
          className={cn(
            "transition-[stroke-dashoffset] duration-500",
            behind ? "stroke-warning" : "stroke-success"
          )}
        />
      </svg>
      <span className="absolute inset-0 flex items-center justify-center text-sm font-semibold tabular-nums">
        {clamped}%
      </span>
    </div>
  )
}

/** One tile of the overview stats row. */
function StatTile({
  icon: Icon,
  value,
  label,
  hint,
  onClick,
}: {
  icon: typeof Map
  value: number
  label: string
  hint?: string
  onClick?: () => void
}) {
  const inner = (
    <>
      <Icon className="size-4 text-muted-foreground" strokeWidth={1.75} />
      <p className="mt-1.5 text-xl font-semibold tabular-nums">{value}</p>
      <p className="truncate text-xs text-muted-foreground">{label}</p>
      {hint && (
        <p className="truncate text-[11px] text-muted-foreground/70">{hint}</p>
      )}
    </>
  )
  return onClick ? (
    <button
      type="button"
      onClick={onClick}
      className="min-w-0 rounded-2xl border bg-card p-3.5 text-left shadow-xs transition-colors hover:bg-accent/50"
    >
      {inner}
    </button>
  ) : (
    <div className="min-w-0 rounded-2xl border bg-card p-3.5 shadow-xs">
      {inner}
    </div>
  )
}

/** One row of the getting-started checklist. */
function PlanStep({
  done,
  label,
  onClick,
}: {
  done: boolean
  label: string
  onClick?: () => void
}) {
  const content = (
    <>
      {done ? (
        <CircleCheck className="size-5 shrink-0 text-success" />
      ) : (
        <Circle className="size-5 shrink-0 text-muted-foreground/40" />
      )}
      <span
        className={cn(
          "text-sm",
          done ? "text-muted-foreground line-through" : "font-medium"
        )}
      >
        {label}
      </span>
    </>
  )
  return (
    <li>
      {onClick ? (
        <button
          type="button"
          onClick={onClick}
          className="flex min-h-9 w-full items-center gap-2.5 rounded-lg text-left transition-colors hover:bg-accent/50"
        >
          {content}
        </button>
      ) : (
        <span className="flex min-h-9 items-center gap-2.5">{content}</span>
      )}
    </li>
  )
}

/** Stored goals/methods are sanitized server-side; render through the same allowlist. */
function HtmlText({ html }: { html: string }) {
  return (
    <div
      className="text-sm leading-relaxed [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:list-disc [&_ul]:pl-5"
      dangerouslySetInnerHTML={{ __html: sanitizeHtml(html) }}
    />
  )
}
