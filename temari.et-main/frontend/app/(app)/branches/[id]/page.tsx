"use client"

import { ArrowLeft, LayoutDashboard, MapPin, Pencil, SlidersHorizontal, UserCog } from "lucide-react"
import dynamic from "next/dynamic"
import Link from "next/link"
import { useParams } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { BranchEditor } from "@/components/schools/branch-editor"
import { BranchSettingsPanel } from "@/components/schools/branch-settings-panel"
import { ContactCell } from "@/components/schools/contact-cell"
import { ContactDialog } from "@/components/schools/contact-dialog"
import { formatGradeSpan, orgStatLinks, OrgStatTiles } from "@/components/schools/org-stats"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useRequirePermission } from "@/lib/auth/use-require-permission"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useTranslation } from "@/lib/i18n"
import type { Branch, GradeLevel, OrgStats } from "@/lib/types"

// Charts are heavy (recharts) and below the fold — load on demand.
const OrgStatsCharts = dynamic(
  () => import("@/components/schools/org-stats-charts").then((m) => m.OrgStatsCharts),
  { ssr: false, loading: () => <Skeleton className="h-64 rounded-2xl" /> },
)

function addressLine(branch: Branch): string {
  return [branch.address.sub_city, branch.address.city, branch.address.state]
    .filter(Boolean)
    .join(", ")
}

const TAB_KEYS = ["overview", "settings"] as const

/**
 * The branch profile: identity hero, at-a-glance vitals (students, workforce
 * by job title, what is taught grade by grade) and management actions. Reached
 * from the Branches table and the school profile's branch list.
 */
export default function BranchDetailPage() {
  const params = useParams<{ id: string }>()
  const branchId = Number(params.id)
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const permissions = useEffectivePermissions()

  // Branch profiles belong to Branch Management (Temari.et staff and school
  // managers) — branch staff such as directors work through operational modules.
  const { authorized } = useRequirePermission("branches.view")

  const canManageSchools = permissions.includes("schools.view")
  const canEdit = permissions.includes("branches.update")
  const showGeo = permissions.includes("branches.view_geo")
  // Branch settings moved in here from the sidebar — school managers tune a
  // branch where they look at it, without switching workspaces.
  const canManageSettings = permissions.includes("branch_settings.manage")

  const [branch, setBranch] = useState<Branch | null>(null)
  const [stats, setStats] = useState<OrgStats | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [tab, setTab] = useProfileTabs(TAB_KEYS, "overview")

  useEffect(() => {
    if (!authorized) return

    apiFetch<{ data: Branch }>(`/branches/${branchId}`)
      .then((response) => setBranch(response.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : t("branches.loadFailed")),
      )

    apiFetch<{ data: OrgStats }>(`/branches/${branchId}/stats`)
      .then((response) => setStats(response.data))
      .catch(() => {})
  }, [branchId, authorized, t])

  // Full ladder for the per-program grade-span badges (session-cached).
  const { grades } = useGradeLevels({ all: true, enabled: authorized && branch != null })

  if (!authorized) return null

  const gradeSpan = branch ? formatGradeSpan(branch.grade_min, branch.grade_max) : null
  const address = branch ? addressLine(branch) : ""

  function programSpan(ids: number[] | null | undefined): string | null {
    if (!ids || ids.length === 0 || grades.length === 0) return null
    const sorted = ids
      .map((id) => grades.find((g) => g.id === id))
      .filter((g): g is GradeLevel => g != null)
      .sort((a, b) => a.sort_order - b.sort_order)
    if (sorted.length === 0) return null
    return sorted.length === 1
      ? sorted[0].name
      : `${sorted[0].name} – ${sorted[sorted.length - 1].name}`
  }

  return (
    <div className="space-y-6 pb-6">
      <Button asChild variant="ghost" size="sm" className="ml-4 md:ml-8">
        <Link href="/branches">
          <ArrowLeft className="size-4" />
          {tc("nav.branches")}
        </Link>
      </Button>

      {/* Identity hero: who this branch is and who runs it. */}
      <div className="page-gutter">
        <section className="bg-card rounded-2xl border p-5 shadow-xs">
          <div className="flex flex-wrap items-center gap-4">
            <div className="brand-tile flex size-14 shrink-0 items-center justify-center rounded-2xl text-xl font-semibold text-white">
              {branch ? branch.name.slice(0, 1) : "…"}
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="font-display text-xl font-semibold tracking-tight md:text-2xl">
                  {branch ? branch.name : <Skeleton className="h-7 w-48" />}
                </h1>
                {branch && (
                  <>
                    <Badge variant="outline" className="rounded-full font-mono text-xs">
                      {branch.code}
                    </Badge>
                    <Badge
                      variant="outline"
                      className={
                        branch.is_active
                          ? "border-success/30 bg-success/10 text-success rounded-full"
                          : "border-border bg-muted text-muted-foreground rounded-full"
                      }
                    >
                      {branch.is_active ? tc("states.active") : tc("states.inactive")}
                    </Badge>
                  </>
                )}
              </div>
              {branch && (
                <p className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-2 text-sm">
                  {branch.school &&
                    (canManageSchools ? (
                      <Link
                        href={`/schools/${branch.school.id}`}
                        className="hover:text-foreground underline-offset-4 hover:underline"
                      >
                        {branch.school.name}
                      </Link>
                    ) : (
                      <span>{branch.school.name}</span>
                    ))}
                  {gradeSpan && (
                    <>
                      <span aria-hidden>·</span>
                      <span>{gradeSpan}</span>
                    </>
                  )}
                  {address && (
                    <>
                      <span aria-hidden>·</span>
                      <span className="inline-flex items-center gap-1">
                        <MapPin className="size-3.5" />
                        {address}
                      </span>
                    </>
                  )}
                </p>
              )}
              {branch && (branch.programs?.length ?? 0) > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                  {branch.programs!.map((program) => {
                    const span = programSpan(program.grade_level_ids)
                    return (
                      <Badge key={program.id} variant="secondary" className="rounded-full">
                        {program.name}
                        {span && <span className="ml-1 font-normal text-muted-foreground">· {span}</span>}
                      </Badge>
                    )
                  })}
                </div>
              )}
            </div>
            {canEdit && branch && (
              <div className="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" onClick={() => setEditOpen(true)}>
                  <Pencil className="size-4" />
                  {tc("actions.edit")}
                </Button>
                <ContactDialog
                  target={{ kind: "branch", branchId: branch.id }}
                  current={branch.director}
                  // Merge — the save response has no school relation or counts.
                  onBranchSaved={(updated) =>
                    setBranch((prev) => (prev ? { ...prev, ...updated } : updated))
                  }
                  title={t("contacts.replaceDirectorTitle")}
                  description={t("contacts.replaceDescription")}
                  trigger={
                    <Button variant="outline" size="sm">
                      <UserCog className="size-4" />
                      {branch.director ? t("contacts.replaceDirector") : t("contacts.assignDirector")}
                    </Button>
                  }
                />
              </div>
            )}
          </div>

          {/* Who runs this branch — tap-to-call, per the contact convention. */}
          <div className="mt-4 flex items-center gap-3 border-t pt-4">
            <span className="text-muted-foreground text-[11px] font-medium tracking-wide uppercase">
              {t("contacts.director")}
            </span>
            {branch ? (
              <ContactCell contact={branch.director} />
            ) : (
              <Skeleton className="h-5 w-40" />
            )}
          </div>
        </section>
      </div>

      {/* Overview | Settings — settings only for holders of the manage perm. */}
      {canManageSettings && (
        <div className="page-gutter">
          <ProfileTabBar
            tabs={[
              { key: "overview", label: t("profileTabs.overview"), icon: LayoutDashboard },
              { key: "settings", label: t("profileTabs.settings"), icon: SlidersHorizontal },
            ]}
            value={tab}
            onChange={setTab}
          />
        </div>
      )}

      {(tab === "overview" || !canManageSettings) && (
        <>
          {/* At-a-glance vitals. */}
          <div className="page-gutter">
            <OrgStatTiles stats={stats} links={orgStatLinks(permissions)} />
          </div>

          {/* Who studies and works here, chart by chart. */}
          <div className="page-gutter">
            {stats === null ? (
              <Skeleton className="h-64 rounded-2xl" />
            ) : (
              <OrgStatsCharts stats={stats} />
            )}
          </div>
        </>
      )}

      {tab === "settings" && canManageSettings && (
        <div className="page-gutter">
          <div className="mx-auto max-w-3xl">
            {/* The outer page owns ?tab= — the panel's sub-tabs stay local. */}
            <BranchSettingsPanel branchId={branchId} syncTabs={false} />
          </div>
        </div>
      )}

      <BranchEditor
        branch={branch}
        open={editOpen}
        onOpenChange={setEditOpen}
        showGeo={showGeo}
        // Merge — the update response has no school relation or counts.
        onSaved={(updated) => setBranch((prev) => (prev ? { ...prev, ...updated } : updated))}
      />
    </div>
  )
}
