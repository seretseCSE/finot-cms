"use client"

import { ArrowRight, ClipboardCheck, Gauge, Map, Sun } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { CreatePlanSheet } from "@/components/lesson-plans/create-plan-sheet"
import { MyDay } from "@/components/lesson-plans/my-day"
import { PacingBoard } from "@/components/lesson-plans/pacing-board"
import { PlanShelf } from "@/components/lesson-plans/plan-shelf"
import { ReviewInbox } from "@/components/lesson-plans/review-inbox"
import {
  PLAN_STATUS_ROW,
  PlanStatusBadge,
  ProgressBar,
} from "@/components/lesson-plans/shared"
import { BranchScopePicker } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import {
  ProfileTabBar,
  useProfileTabs,
  type ProfileTab,
} from "@/components/ui/profile-tabs"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { AnnualLessonPlanRow, LessonPlanStatus } from "@/lib/types"

/** Register row + the flat keys DataTable's client-mode filters read. */
type RegisterRow = AnnualLessonPlanRow & {
  subject_key: string
  grade_key: string
  teacher_key: string
  year_key: string
}

const TAB_KEYS = ["today", "plans", "review", "pacing"] as const

/**
 * The lesson-planning hub. Teachers land on their own annual plans;
 * directors/principals get the same register across their scope plus the
 * review inbox and the pacing dashboard as sibling tabs.
 */
export default function LessonPlansPage() {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const canReview = permissions.includes("lesson_plans.review")
  const supervisor = canReview || permissions.includes("lesson_plans.view")
  const canAuthor = permissions.includes("lesson_plans.manage_own")

  // Teachers land on their teaching day; supervisors on the register.
  const [tab, setTab] = useProfileTabs(TAB_KEYS, canAuthor ? "today" : "plans")
  const [branchFilter, setBranchFilter] = useState<number | null>(null)
  const [rows, setRows] = useState<RegisterRow[] | null>(null)
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})
  const [reloadKey, setReloadKey] = useState(0)

  const hasWorkspace = !isPlatform && active.schoolId !== null
  const needsBranchPick = hasWorkspace && active.branchId === null

  useEffect(() => {
    if (!hasWorkspace || tab !== "plans") return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset for the new query
    setRows(null)
    const branchParam =
      branchFilter !== null ? `&branch_id=${branchFilter}` : ""
    apiFetch<{ data: AnnualLessonPlanRow[] }>(
      `/lesson-plans?per_page=100${branchParam}`
    )
      .then(
        (res) =>
          !cancelled &&
          setRows(
            res.data.map((r) => ({
              ...r,
              subject_key: r.subject.name ?? "",
              grade_key: r.grade_level.name ?? "",
              teacher_key: r.teacher_name ?? "",
              year_key: r.academic_year.name ?? "",
            }))
          )
      )
      .catch((error) => {
        if (cancelled) return
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
        setRows([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- tc is stable enough
  }, [
    hasWorkspace,
    active.schoolId,
    active.branchId,
    branchFilter,
    tab,
    reloadKey,
  ])

  const columns: DataTableColumn<RegisterRow>[] = useMemo(
    () => [
      {
        key: "subject_key",
        label: t("register.subject"),
        primary: true,
        render: (row) => (
          <div className="min-w-0">
            <p className="truncate font-medium">
              {row.subject.name}
              <span className="font-normal text-muted-foreground">
                {" · "}
                {row.grade_level.name}
              </span>
            </p>
            <p className="truncate text-xs text-muted-foreground">
              {row.academic_year.name}
            </p>
          </div>
        ),
        exportValue: (row) =>
          `${row.subject.name ?? ""} — ${row.grade_level.name ?? ""} (${row.academic_year.name ?? ""})`,
      },
      ...(supervisor
        ? [
            {
              key: "teacher_key",
              label: t("register.teacher"),
              mobileHidden: true,
              render: (row: RegisterRow) => row.teacher_name ?? "—",
              exportValue: (row: RegisterRow) => row.teacher_name ?? "",
            } as DataTableColumn<RegisterRow>,
          ]
        : []),
      {
        key: "units_count",
        label: t("register.units"),
        mobileHidden: true,
        render: (row) => (
          <span className="tabular-nums">{row.units_count ?? 0}</span>
        ),
        exportValue: (row) => String(row.units_count ?? 0),
      },
      {
        key: "progress",
        label: t("register.progress"),
        render: (row) =>
          row.pacing && row.pacing.planned_periods > 0 ? (
            <div className="w-28 space-y-1">
              <ProgressBar
                percent={row.pacing.progress_percent}
                behind={row.pacing.lag_periods > 0}
              />
              <p className="text-xs text-muted-foreground tabular-nums">
                {row.pacing.progress_percent}%
              </p>
            </div>
          ) : (
            <span className="text-muted-foreground">—</span>
          ),
        exportValue: (row) => `${row.pacing?.progress_percent ?? 0}%`,
      },
      {
        key: "status",
        label: t("register.status"),
        render: (row) => <PlanStatusBadge status={row.status} />,
        exportValue: (row) => row.status,
      },
      {
        key: "open",
        label: "",
        mobileHidden: true,
        render: () => (
          <Button variant="ghost" size="sm" className="gap-1.5">
            {t("register.open")}
            <ArrowRight className="size-3.5" />
          </Button>
        ),
        exportValue: () => "",
      },
    ],
    [t, supervisor]
  )

  const optionsFor = (key: keyof RegisterRow) =>
    [
      ...new Set((rows ?? []).map((r) => String(r[key] ?? "")).filter(Boolean)),
    ].sort((a, b) => a.localeCompare(b, undefined, { numeric: true }))

  const tabs: ProfileTab<(typeof TAB_KEYS)[number]>[] = [
    ...(canAuthor
      ? [{ key: "today" as const, label: t("tabs.today"), icon: Sun }]
      : []),
    { key: "plans", label: t("tabs.plans"), icon: Map },
    ...(canReview
      ? [
          {
            key: "review" as const,
            label: t("tabs.review"),
            icon: ClipboardCheck,
          },
        ]
      : []),
    ...(supervisor
      ? [{ key: "pacing" as const, label: t("tabs.pacing"), icon: Gauge }]
      : []),
  ]

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("title")}
        description={supervisor ? t("queueSubtitle") : t("subtitle")}
        actions={
          hasWorkspace ? (
            <div className="flex flex-wrap items-center gap-2">
              {needsBranchPick && tab !== "review" && (
                <BranchScopePicker
                  value={branchFilter}
                  onChange={setBranchFilter}
                />
              )}
              {canAuthor && tab === "plans" && (
                <CreatePlanSheet onCreated={() => setReloadKey((k) => k + 1)} />
              )}
            </div>
          ) : undefined
        }
      />

      {!hasWorkspace ? (
        <div className="page-gutter">
          <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            {t("noBranch")}
          </div>
        </div>
      ) : (
        <>
          {tabs.length > 1 && (
            <div className="page-gutter">
              <ProfileTabBar tabs={tabs} value={tab} onChange={setTab} />
            </div>
          )}

          {tab === "today" && canAuthor && <MyDay />}

          {/* Teachers get the app-like shelf; supervisors keep the full
              register table (teacher search, filters, export). */}
          {tab === "plans" && !supervisor && (
            <PlanShelf rows={rows ?? []} loading={rows === null} />
          )}

          {tab === "plans" && supervisor && (
            <DataTable
              columns={columns}
              data={rows ?? []}
              loading={rows === null}
              searchKeys={["subject_key", "teacher_key"]}
              searchPlaceholder={tc("actions.search")}
              filters={[
                ...(optionsFor("year_key").length > 1
                  ? [
                      {
                        key: "year_key",
                        label: t("register.year"),
                        options: optionsFor("year_key").map((v) => ({
                          label: v,
                          value: v,
                        })),
                      },
                    ]
                  : []),
                ...(optionsFor("grade_key").length > 0
                  ? [
                      {
                        key: "grade_key",
                        label: t("register.grade"),
                        options: optionsFor("grade_key").map((v) => ({
                          label: v,
                          value: v,
                        })),
                      },
                    ]
                  : []),
                ...(supervisor && optionsFor("teacher_key").length > 0
                  ? [
                      {
                        key: "teacher_key",
                        label: t("register.teacher"),
                        options: optionsFor("teacher_key").map((v) => ({
                          label: v,
                          value: v,
                        })),
                      },
                    ]
                  : []),
                {
                  key: "status",
                  label: t("register.status"),
                  options: (
                    [
                      "draft",
                      "submitted",
                      "approved",
                      "declined",
                    ] as LessonPlanStatus[]
                  ).map((s) => ({ label: t(`statuses.${s}`), value: s })),
                },
              ]}
              filterValues={filterValues}
              onFilterChange={(key, value) =>
                setFilterValues((prev) => ({ ...prev, [key]: value }))
              }
              rowClassName={(row) => PLAN_STATUS_ROW[row.status]}
              onRowClick={(row) => router.push(`/lesson-plans/${row.id}`)}
              emptyMessage={t("register.empty")}
              exportFilename="lesson-plans"
            />
          )}

          {tab === "review" && canReview && <ReviewInbox />}

          {tab === "pacing" && supervisor && (
            <PacingBoard branchFilter={needsBranchPick ? branchFilter : null} />
          )}
        </>
      )}
    </div>
  )
}
