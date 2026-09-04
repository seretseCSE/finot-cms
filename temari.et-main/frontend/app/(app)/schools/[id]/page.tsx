"use client"

import dynamic from "next/dynamic"
import Link from "next/link"
import { useParams, useRouter } from "next/navigation"
import { ArrowLeft, Building2, LayoutDashboard, Pencil, Plus, Settings2, UserCog } from "lucide-react"
import { useEffect, useState, type ReactNode } from "react"
import { toast } from "sonner"

import { ContactDialog } from "@/components/schools/contact-dialog"
import { ContactCell } from "@/components/schools/contact-cell"
import { BranchEditor } from "@/components/schools/branch-editor"
import { EditSchoolDialog } from "@/components/schools/edit-school-dialog"
import { formatGradeSpan, orgStatLinks, OrgStatTiles } from "@/components/schools/org-stats"
import { PolicyCard } from "@/components/schools/policy-card"
import { SchoolLogoTile } from "@/components/schools/school-logo-tile"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useRequirePermission } from "@/lib/auth/use-require-permission"
import { useTranslation } from "@/lib/i18n"
import type { Branch, Contact, OrgStats, Paginated, School } from "@/lib/types"

// Charts are heavy (recharts) and below the fold — load on demand.
const OrgStatsCharts = dynamic(
  () => import("@/components/schools/org-stats-charts").then((m) => m.OrgStatsCharts),
  { ssr: false, loading: () => <Skeleton className="h-64 rounded-2xl" /> },
)

const TAB_KEYS = ["overview", "branches", "settings"] as const

function addressLine(branch: Branch): string {
  return [branch.address.sub_city, branch.address.city, branch.address.state]
    .filter(Boolean)
    .join(", ")
}

export default function SchoolDetailPage() {
  const params = useParams<{ id: string }>()
  const schoolId = Number(params.id)
  const router = useRouter()
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const permissions = useEffectivePermissions()
  const { isPlatform } = useSchoolContext()

  // The school profile is visible to Temari.et staff (schools.view) and to a
  // school's own managers (principal / school_admin hold branches.view). Branch
  // staff such as directors hold neither and are sent to /unauthorized.
  const { authorized } = useRequirePermission(["schools.view", "branches.view"])

  const [tab, setTab] = useProfileTabs(TAB_KEYS, "overview")

  const [school, setSchool] = useState<School | null>(null)
  const [branches, setBranches] = useState<Branch[] | null>(null)
  const [stats, setStats] = useState<OrgStats | null>(null)
  const [editingBranch, setEditingBranch] = useState<Branch | null>(null)
  const [editOpen, setEditOpen] = useState(false)
  const [createOpen, setCreateOpen] = useState(false)

  const canManageSchools = permissions.includes("schools.view")
  const canCreate = permissions.includes("branches.create")
  const canEditSchool = permissions.includes("schools.update")
  const canManageContacts = permissions.includes("schools.update")
  const canEditBranch = permissions.includes("branches.update")
  const showGeo = permissions.includes("branches.view_geo")

  // The hero needs the school itself; everything else loads with its tab.
  useEffect(() => {
    // Don't fetch (and flash an error toast) while the guard is redirecting an
    // unauthorized user away.
    if (!authorized) return

    apiFetch<{ data: School }>(`/schools/${schoolId}`)
      .then((response) => setSchool(response.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : "Failed to load school."),
      )
  }, [schoolId, authorized])

  // Vitals: fetched on first Overview visit, kept for the session after.
  useEffect(() => {
    if (!authorized || tab !== "overview" || stats !== null) return

    apiFetch<{ data: OrgStats }>(`/schools/${schoolId}/stats`)
      .then((response) => setStats(response.data))
      .catch(() => {})
    // eslint-disable-next-line react-hooks/exhaustive-deps -- fetch-once guard
  }, [schoolId, authorized, tab])

  // Branch list: fetched on first Branches visit.
  useEffect(() => {
    if (!authorized || tab !== "branches" || branches !== null) return

    apiFetch<Paginated<Branch>>(`/schools/${schoolId}/branches`)
      .then((response) => setBranches(response.data))
      .catch((error) => {
        toast.error(error instanceof ApiError ? error.message : "Failed to load branches.")
        setBranches([])
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps -- fetch-once guard
  }, [schoolId, authorized, tab])

  function handleCreated(branch: Branch) {
    // An unopened Branches tab fetches fresh on first visit — only merge into
    // an already-loaded list.
    setBranches((prev) => (prev === null ? null : [branch, ...prev]))
    setSchool((prev) =>
      prev ? { ...prev, branches_count: (prev.branches_count ?? 0) + 1 } : prev,
    )
    setTab("branches")
  }

  function updateBranch(updated: Branch) {
    setBranches((prev) => (prev ?? []).map((b) => (b.id === updated.id ? updated : b)))
  }

  const columns: DataTableColumn<Branch>[] = [
    {
      key: "name",
      label: t("branches.name"),
      sortable: true,
      primary: true,
      render: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: "code",
      label: t("branches.code"),
      sortable: true,
    },
    {
      key: "address",
      label: t("branches.city"),
      sortable: false,
      mobileHidden: true,
      render: (row) => addressLine(row) || "—",
      exportValue: (row) => addressLine(row),
    },
    {
      key: "grades",
      label: t("stats.gradesColumn"),
      mobileHidden: true,
      render: (row) => formatGradeSpan(row.grade_min, row.grade_max) ?? "—",
      exportValue: (row) => formatGradeSpan(row.grade_min, row.grade_max) ?? "",
    },
    {
      key: "students_count",
      label: t("stats.students"),
      render: (row) => <span className="tabular-nums">{row.students_count ?? 0}</span>,
      exportValue: (row) => String(row.students_count ?? 0),
    },
    {
      key: "teachers_count",
      label: t("stats.teachers"),
      mobileHidden: true,
      render: (row) => <span className="tabular-nums">{row.teachers_count ?? 0}</span>,
      exportValue: (row) => String(row.teachers_count ?? 0),
    },
    {
      key: "director",
      label: t("contacts.director"),
      mobileHidden: true,
      render: (row) => <ContactCell contact={row.director} />,
      exportValue: (row) => row.director?.name ?? "",
    },
    ...(showGeo
      ? [
          {
            key: "geo",
            label: "Geo",
            mobileHidden: true,
            render: (row: Branch) =>
              row.latitude && row.longitude ? `${row.latitude}, ${row.longitude}` : "—",
            exportValue: (row: Branch) =>
              row.latitude && row.longitude ? `${row.latitude}, ${row.longitude}` : "",
          } satisfies DataTableColumn<Branch>,
        ]
      : []),
    {
      key: "is_active",
      label: tc("columns.status"),
      render: (row) => (
        <Badge variant={row.is_active ? "default" : "secondary"}>
          {row.is_active ? tc("states.active") : tc("states.inactive")}
        </Badge>
      ),
      exportValue: (row) => (row.is_active ? tc("states.active") : tc("states.inactive")),
    },
    ...(canEditBranch
      ? [
          {
            key: "manage",
            label: "",
            // Rows navigate to the branch profile — keep taps on the action
            // icons from also triggering the navigation.
            render: (row: Branch) => (
              <div
                className="flex items-center justify-end gap-1"
                onClick={(e) => e.stopPropagation()}
              >
                <Button
                  variant="ghost"
                  size="icon"
                  className="size-8"
                  title={t("branches.editTitle")}
                  onClick={() => {
                    setEditingBranch(row)
                    setEditOpen(true)
                  }}
                >
                  <Pencil className="size-4" />
                </Button>
                <ContactDialog
                  target={{ kind: "branch", branchId: row.id }}
                  current={row.director}
                  onBranchSaved={updateBranch}
                  title={t("contacts.replaceDirectorTitle")}
                  description={t("contacts.replaceDescription")}
                  trigger={
                    <Button variant="ghost" size="icon" className="size-8" title={t("contacts.replaceDirector")}>
                      <UserCog className="size-4" />
                    </Button>
                  }
                />
              </div>
            ),
          } satisfies DataTableColumn<Branch>,
        ]
      : []),
  ]

  if (!authorized) return null

  return (
    <div className="space-y-6">
      <Button asChild variant="ghost" size="sm" className="-ml-2 ml-4 md:ml-8">
        <Link href={canManageSchools ? "/schools" : "/branches"}>
          <ArrowLeft className="size-4" />
          {canManageSchools ? tc("nav.schools") : tc("nav.branches")}
        </Link>
      </Button>

      {/* Identity hero: the school as a card, not a bare heading. */}
      <div className="px-4 md:px-8">
        <section className="rounded-2xl border bg-card p-5 shadow-xs">
          <div className="flex flex-wrap items-center gap-4">
            {/* Logo edits are Temari.et platform staff only (SchoolPolicy@manageLogo). */}
            <SchoolLogoTile
              school={school}
              canManage={isPlatform && permissions.includes("schools.update")}
              onUpdated={(logoUrl) =>
                setSchool((prev) => (prev ? { ...prev, logo_url: logoUrl } : prev))
              }
            />
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="font-display text-xl font-semibold tracking-tight md:text-2xl">
                  {school ? school.name : <Skeleton className="h-7 w-48" />}
                </h1>
                {school && (
                  <Badge
                    variant="outline"
                    className={
                      school.is_active
                        ? "rounded-full border-success/30 bg-success/10 text-success"
                        : "rounded-full border-border bg-muted text-muted-foreground"
                    }
                  >
                    {school.is_active ? tc("states.active") : tc("states.inactive")}
                  </Badge>
                )}
              </div>
              {school && (
                <p className="mt-0.5 text-sm text-muted-foreground">
                  {t("branchesCount", { count: school.branches_count ?? branches?.length ?? 0 })}
                  {stats && formatGradeSpan(stats.grades[0]?.name, stats.grades.at(-1)?.name) && (
                    <> · {formatGradeSpan(stats.grades[0]?.name, stats.grades.at(-1)?.name)}</>
                  )}
                  {" · "}
                  {t("policy.registrationGate")}:{" "}
                  {school.registration_gate === "hard"
                    ? t("branchSettings.gateHardShort")
                    : t("branchSettings.gateSoftShort")}
                  {" · "}
                  {t("policy.threshold")}: {school.promotion_threshold ?? 50}%
                </p>
              )}
            </div>
            <div className="flex flex-wrap items-center gap-2">
              {canEditSchool && school && <EditSchoolDialog school={school} onUpdated={setSchool} />}
              {canCreate && (
                <>
                  <Button className="h-11" onClick={() => setCreateOpen(true)}>
                    <Plus className="size-4" />
                    {t("branches.create")}
                  </Button>
                  <BranchEditor
                    open={createOpen}
                    onOpenChange={setCreateOpen}
                    schoolId={schoolId}
                    onSaved={handleCreated}
                  />
                </>
              )}
            </div>
          </div>
        </section>
      </div>

      <div className="px-4 md:px-8">
        <ProfileTabBar
          tabs={[
            { key: "overview", label: t("profileTabs.overview"), icon: LayoutDashboard },
            { key: "branches", label: t("profileTabs.branches"), icon: Building2 },
            { key: "settings", label: t("profileTabs.settings"), icon: Settings2 },
          ]}
          value={tab}
          onChange={setTab}
        />
      </div>

      {tab === "overview" && (
        <>
          {/* At-a-glance vitals across every branch. */}
          <div className="px-4 md:px-8">
            <OrgStatTiles stats={stats} links={orgStatLinks(permissions)} />
          </div>

          {/* Who studies and works here, chart by chart. */}
          <div className="px-4 md:px-8">
            {stats === null ? (
              <Skeleton className="h-64 rounded-2xl" />
            ) : (
              <OrgStatsCharts stats={stats} />
            )}
          </div>
        </>
      )}

      {tab === "branches" && (
        <DataTable
          columns={columns}
          data={branches ?? []}
          loading={branches === null}
          searchKeys={["name", "code"]}
          searchPlaceholder={t("branches.search")}
          filters={[
            {
              key: "is_active",
              label: tc("filters.status"),
              options: [
                { label: tc("states.active"), value: "true" },
                { label: tc("states.inactive"), value: "false" },
              ],
            },
          ]}
          onRowClick={(row) => router.push(`/branches/${row.id}`)}
          emptyMessage={t("branches.empty")}
          exportFilename={`branches-${schoolId}`}
        />
      )}

      {tab === "settings" && (
        <>
          {/* Contacts: who runs this school */}
          <div className="grid gap-3 px-4 md:grid-cols-2 md:px-8">
            <ContactCard
              title={t("contacts.principal")}
              contact={school?.principal}
              loading={!school}
              action={
                canManageContacts && school ? (
                  <ContactDialog
                    target={{ kind: "school", schoolId, role: "principal" }}
                    current={school.principal}
                    onSchoolSaved={setSchool}
                    title={t("contacts.replacePrincipalTitle")}
                    description={t("contacts.replaceDescription")}
                    trigger={
                      <Button variant="outline" size="sm">
                        {school.principal ? t("contacts.replace") : t("contacts.assign")}
                      </Button>
                    }
                  />
                ) : null
              }
              empty={t("contacts.none")}
            />
            <ContactCard
              title={t("contacts.itAdmin")}
              contact={school?.school_admin}
              loading={!school}
              action={
                canManageContacts && school ? (
                  <ContactDialog
                    target={{ kind: "school", schoolId, role: "school_admin" }}
                    current={school.school_admin}
                    onSchoolSaved={setSchool}
                    title={t("contacts.replaceItAdminTitle")}
                    description={t("contacts.replaceDescription")}
                    trigger={
                      <Button variant="outline" size="sm">
                        {school.school_admin ? t("contacts.replace") : t("contacts.assign")}
                      </Button>
                    }
                  />
                ) : null
              }
              empty={t("contacts.none")}
            />
          </div>

          {/* School-wide academic policy (registration gate + pass mark). */}
          <div className="px-4 md:px-8">
            <PolicyCard
              school={school}
              canEdit={permissions.includes("branches.create")}
              onSaved={setSchool}
            />
          </div>
        </>
      )}

      <BranchEditor
        branch={editingBranch}
        open={editOpen}
        onOpenChange={(v) => {
          setEditOpen(v)
          if (!v) setEditingBranch(null)
        }}
        showGeo={showGeo}
        onSaved={updateBranch}
      />
    </div>
  )
}

/** A single "who runs this" contact panel with name, phone, status and an action. */
function ContactCard({
  title,
  contact,
  loading,
  action,
  empty,
}: {
  title: string
  contact?: Contact | null
  loading: boolean
  action: ReactNode
  empty: string
}) {
  const { t: tc } = useTranslation("common")

  return (
    <div className="flex items-start justify-between gap-3 rounded-lg border bg-card p-4">
      <div className="min-w-0 space-y-1">
        <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
          {title}
        </span>
        {loading ? (
          <Skeleton className="h-5 w-40" />
        ) : contact?.name ? (
          <div className="space-y-0.5">
            <div className="flex items-center gap-2">
              <span className="font-medium">{contact.name}</span>
              {!contact.is_active && (
                <Badge variant="secondary">{tc("states.inactive")}</Badge>
              )}
            </div>
            {contact.phone && (
              <p className="text-sm text-muted-foreground">{contact.phone}</p>
            )}
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">{empty}</p>
        )}
      </div>
      {action}
    </div>
  )
}
