"use client"

import { LifeBuoy, RefreshCw } from "lucide-react"
import dynamic from "next/dynamic"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useEffect } from "react"

import { hasStaffMembership } from "@/components/app-shell/nav-config"
import { ActionQueue } from "@/components/dashboard/action-queue"
import { BranchCompare } from "@/components/dashboard/branch-compare"
import { DashboardKpis } from "@/components/dashboard/kpis"
import { PlatformOverview } from "@/components/dashboard/platform-overview"
import { QuickActions } from "@/components/dashboard/quick-actions"
import { TeacherDay } from "@/components/dashboard/teacher-day"
import { TermStrip } from "@/components/dashboard/term-strip"
import { useDashboard } from "@/components/dashboard/use-dashboard"
import { Button } from "@/components/ui/button"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { useAuth } from "@/lib/auth/auth-context"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"

// Recharts is heavy — every chart loads lazily so first paint stays fast on 3G.
const AttendanceWeekChart = dynamic(
  () =>
    import("@/components/dashboard/charts").then((m) => m.AttendanceWeekChart),
  { ssr: false, loading: () => <Skeleton className="h-72 rounded-2xl" /> }
)
const CollectionsTrendChart = dynamic(
  () =>
    import("@/components/dashboard/charts").then(
      (m) => m.CollectionsTrendChart
    ),
  { ssr: false, loading: () => <Skeleton className="h-72 rounded-2xl" /> }
)
const OrgStatsCharts = dynamic(
  () =>
    import("@/components/schools/org-stats-charts").then(
      (m) => m.OrgStatsCharts
    ),
  { ssr: false, loading: () => <Skeleton className="h-64 rounded-2xl" /> }
)

function greetingKey(): string {
  const h = new Date().getHours()
  if (h < 12) return "goodMorning"
  if (h < 18) return "goodAfternoon"
  return "goodEvening"
}

/**
 * The staff landing page — a role-adaptive command center fed by ONE
 * aggregated request. The blocks the backend returns mirror the caller's
 * authority: a principal sees the school pulse and branch comparison, a
 * director the branch vitals and approval piles, a finance officer the money
 * desk, a registrar the enrollment pipeline, a teacher "my day". Missing
 * authority = missing block; the page simply composes what arrived.
 */
export default function DashboardPage() {
  const { user } = useAuth()
  const { activeOption, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const { t } = useTranslation("common")
  const router = useRouter()
  const { data, error, retry } = useDashboard()

  // Non-staff accounts never see the staff dashboard (ADR-012): family hats
  // land on /me, tutors on their tutoring home, hat-less B2C learners on
  // exam prep. Uses the shared helper so relationship-role membership rows
  // can never make an account count as staff.
  const nonStaff = user != null && !hasStaffMembership(user)

  useEffect(() => {
    if (!nonStaff || !user) return
    if (user.is_parent || user.is_student) router.replace("/me")
    else if (user.is_tutor) router.replace("/tutoring")
    else router.replace("/me/exam-prep")
  }, [nonStaff, user, router])

  if (nonStaff) return null

  const firstName = user?.name.split(" ")[0] ?? ""
  const contextName = activeOption
    ? (activeOption.branchName ??
      activeOption.schoolName ??
      t("dashboard.workspaceAll"))
    : t("dashboard.workspaceAll")

  // Pure teachers read their day first; supervisors read the vitals first.
  const teacherFirst =
    data?.teacher != null && !permissions.includes("attendance.reports.view")

  return (
    <div className="mx-auto w-full max-w-6xl space-y-6 pb-6">
      <PageHeader
        title={`${t(`dashboard.${greetingKey()}`)}, ${firstName}`}
        description={contextName}
      />

      <div className="page-gutter space-y-6">
        {/* ── Today / term strip ── */}
        {data === null && !error ? (
          <Skeleton className="h-12 rounded-2xl" />
        ) : data ? (
          <TermStrip context={data.context} />
        ) : null}

        {/* ── Error state: one retry, no dead end ── */}
        {error && (
          <div className="flex flex-col items-center gap-3 rounded-2xl border bg-card p-8 text-center">
            <p className="text-sm text-muted-foreground">
              {t("dashboard.loadFailed")}
            </p>
            <Button variant="outline" size="sm" onClick={retry}>
              <RefreshCw className="size-4" />
              {t("dashboard.retry")}
            </Button>
          </div>
        )}

        {/* ── Teacher's day (leads for pure teachers) ── */}
        {teacherFirst && data?.teacher && <TeacherDay teacher={data.teacher} />}

        {/* ── Vitals ── */}
        {!error && <DashboardKpis data={data} />}

        {/* ── Needs attention ── */}
        {!error && <ActionQueue queue={data === null ? null : data.queue} />}

        {/* ── Teacher's day (after the vitals for supervisors who also teach) ── */}
        {!teacherFirst && data?.teacher && (
          <TeacherDay teacher={data.teacher} />
        )}

        {/* ── Charts ── */}
        {(data?.attendance || data?.finance) && (
          <div className="grid gap-3 lg:grid-cols-2">
            {data.attendance && (
              <AttendanceWeekChart week={data.attendance.week} />
            )}
            {data.finance && (
              <CollectionsTrendChart trend={data.finance.trend} />
            )}
          </div>
        )}

        {/* ── Branch pulse (school-wide workspaces) ── */}
        {data?.branches && <BranchCompare branches={data.branches} />}

        {/* ── Platform pulse (Temari.et staff) ── */}
        {isPlatform && data?.platform && (
          <PlatformOverview platform={data.platform} />
        )}

        {/* ── Who studies here (composition charts) ── */}
        {data?.org && data.org.students.active > 0 && (
          <OrgStatsCharts stats={data.org} />
        )}

        {/* ── Shortcuts ── */}
        <QuickActions permissions={permissions} />

        {/* ── Help ── */}
        <Link
          href="/docs"
          className="pressable flex items-center gap-4 rounded-2xl border border-primary/15 bg-primary/5 p-4 transition-colors hover:bg-primary/10"
        >
          <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
            <LifeBuoy className="size-[18px]" strokeWidth={1.75} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-sm font-medium">
              {t("dashboard.helpTitle")}
            </span>
            <span className="block text-xs leading-relaxed text-muted-foreground">
              {t("dashboard.helpDesc")}
            </span>
          </span>
          <span className="shrink-0 text-sm font-medium text-primary">
            {t("dashboard.helpCta")}
          </span>
        </Link>
      </div>
    </div>
  )
}
