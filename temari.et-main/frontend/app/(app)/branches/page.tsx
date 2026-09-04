"use client"

import { Plus, School } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useMemo, useState } from "react"
import { toast } from "sonner"

import { BranchEditor } from "@/components/schools/branch-editor"
import { contactExport } from "@/components/schools/contact-cell"
import { DirectorContactCell } from "@/components/schools/director-contact-cell"
import { formatGradeSpan } from "@/components/schools/org-stats"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import { DateRangeFilter } from "@/components/ui/date-range-filter"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useContextsResponse, useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useRequirePermission } from "@/lib/auth/use-require-permission"
import { useTranslation } from "@/lib/i18n"
import type { Branch } from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { fmtDate } from "@/lib/dates"

export default function BranchesPage() {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const router = useRouter()

  // Branch Management is for Temari.et staff and school managers (principal /
  // school_admin). Directors and other branch staff are sent to /unauthorized.
  const { authorized } = useRequirePermission("branches.view")

  // The active workspace already carries a school — new branches are created in
  // it, no school prompt. Null only for platform staff in the global context.
  const { active, isPlatform } = useSchoolContext()

  // Context-scoped, so a principal only gets manage actions for their own school.
  const permissions = useEffectivePermissions()
  const canCreate = permissions.includes("branches.create")
  const canEdit = permissions.includes("branches.update")

  const table = useServerTable<Branch>({
    endpoint: "/branches",
    exportEndpoint: "/branches/export",
    defaultSort: { key: "created_at", dir: "desc" },
  })

  // Status toggle → explicit confirmation. Deactivating a branch suspends every
  // branch-scoped activity for its staff, so it must never be a stray tap.
  const [pendingToggle, setPendingToggle] = useState<Branch | null>(null)
  const [toggling, setToggling] = useState(false)
  const [createOpen, setCreateOpen] = useState(false)

  // School filter options come from the shared, auto-refreshing contexts
  // source, same as the users page.
  const { data: contextsData } = useContextsResponse()
  const schoolOptions = useMemo(
    () => (contextsData?.schools ?? []).map((s) => ({ label: s.name, value: String(s.id) })),
    [contextsData],
  )

  async function confirmToggle() {
    if (!pendingToggle) return
    setToggling(true)
    try {
      await apiFetch(`/branches/${pendingToggle.id}`, {
        method: "PATCH",
        body: { is_active: !pendingToggle.is_active },
      })
      toast.success(
        pendingToggle.is_active ? t("branches.deactivated") : t("branches.activated"),
      )
      await table.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("branches.toggleFailed"))
    } finally {
      setToggling(false)
      setPendingToggle(null)
    }
  }

  const columns: DataTableColumn<Branch>[] = [
    {
      key: "name",
      label: t("branches.name"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <span className="block truncate font-medium">{row.name}</span>
          {/* In the global workspace, branches of many schools mix — say whose. */}
          {active.schoolId == null && row.school && (
            <span className="text-muted-foreground block truncate text-xs">
              {row.school.name}
            </span>
          )}
        </div>
      ),
    },
    {
      key: "code",
      label: t("branches.code"),
      sortable: true,
      mobileHidden: true,
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
      sortable: true,
      render: (row) => <span className="tabular-nums">{row.students_count ?? 0}</span>,
      exportValue: (row) => String(row.students_count ?? 0),
    },
    {
      key: "teachers_count",
      label: t("stats.teachers"),
      sortable: true,
      mobileHidden: true,
      render: (row) => <span className="tabular-nums">{row.teachers_count ?? 0}</span>,
      exportValue: (row) => String(row.teachers_count ?? 0),
    },
    {
      key: "director",
      label: t("contacts.director"),
      mobileHidden: true,
      render: (row) => <DirectorContactCell contact={row.director} />,
      exportValue: (row) => contactExport(row.director),
    },
    {
      key: "is_active",
      label: tc("columns.status"),
      sortable: true,
      render: (row) =>
        canEdit ? (
          <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
            <Switch
              checked={row.is_active}
              onCheckedChange={() => setPendingToggle(row)}
              aria-label={
                row.is_active ? t("branches.deactivateTitle") : t("branches.activateTitle")
              }
            />
            <span className="hidden text-xs text-muted-foreground sm:inline">
              {row.is_active ? tc("states.active") : tc("states.inactive")}
            </span>
          </div>
        ) : (
          <Badge variant={row.is_active ? "default" : "secondary"}>
            {row.is_active ? tc("states.active") : tc("states.inactive")}
          </Badge>
        ),
      exportValue: (row) => (row.is_active ? tc("states.active") : tc("states.inactive")),
    },
    {
      key: "created_at",
      label: tc("columns.created"),
      sortable: true,
      mobileHidden: true,
      render: (row) => fmtDate(row.created_at),
      exportValue: (row) => fmtDate(row.created_at),
    },
  ]

  const filterDefs: DataTableFilter[] = [
    {
      key: "is_active",
      label: tc("filters.status"),
      options: [
        { label: tc("states.active"), value: "true" },
        { label: tc("states.inactive"), value: "false" },
      ],
    },
  ]
  // The list already follows the active workspace's school; the cross-school
  // filter only makes sense in the global context (platform staff, no school).
  if (active.schoolId == null && schoolOptions.length > 1)
    filterDefs.push({ key: "school_id", label: tc("filters.school"), options: schoolOptions })
  // Deleted records are a Temari.et platform view — school admins never see them.
  if (isPlatform)
    filterDefs.push({
      key: "trashed",
      label: tc("filters.deleted"),
      options: [{ label: tc("filters.showDeleted"), value: "with" }],
    })

  if (!authorized) return null

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">{t("branches.pageTitle")}</h1>
          <p className="text-muted-foreground text-sm">{t("branches.pageSubtitle")}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {/* School managers reach their school profile (contacts + academic
              policy) from here — the Schools list is platform-only. */}
          {!isPlatform && active.schoolId != null && (
            <Button variant="outline" asChild>
              <Link href={`/schools/${active.schoolId}`}>
                <School className="size-4" />
                {t("branches.schoolProfile")}
              </Link>
            </Button>
          )}
          {canCreate && active.schoolId != null && (
            <>
              <Button className="h-11" onClick={() => setCreateOpen(true)}>
                <Plus className="size-4" />
                {t("branches.create")}
              </Button>
              <BranchEditor
                open={createOpen}
                onOpenChange={setCreateOpen}
                schoolId={active.schoolId}
                onSaved={() => table.refetch()}
              />
            </>
          )}
        </div>
      </div>

      <DataTable
        columns={columns}
        data={table.rows}
        loading={table.loading}
        serverMode
        searchable
        searchValue={table.searchInput}
        onSearchChange={table.setSearchInput}
        searchPlaceholder={t("branches.search")}
        filters={filterDefs}
        filterValues={table.filters}
        onFilterChange={table.setFilter}
        toolbarSlot={
          <DateRangeFilter
            fields={[
              { key: "created_from", label: tc("filters.createdFrom") },
              { key: "created_to", label: tc("filters.createdTo") },
            ]}
            values={table.dates}
            onChange={table.setDate}
            onClear={table.clearDates}
          />
        }
        onSortChange={table.onSortChange}
        onExport={table.handleExport}
        onRowClick={(row) => router.push(`/branches/${row.id}`)}
        emptyMessage={t("branches.empty")}
        exportFilename="branches"
        pagination={table.pagination}
      />

      {/* Activate / deactivate confirmation */}
      <AlertDialog open={pendingToggle !== null} onOpenChange={(open) => !open && setPendingToggle(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {pendingToggle?.is_active
                ? t("branches.deactivateTitle")
                : t("branches.activateTitle")}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {pendingToggle?.is_active
                ? t("branches.deactivateDesc", { name: pendingToggle?.name ?? "" })
                : t("branches.activateDesc", { name: pendingToggle?.name ?? "" })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={toggling}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={toggling}
              variant={pendingToggle?.is_active ? "destructive" : "default"}
              onClick={(e) => {
                e.preventDefault()
                confirmToggle()
              }}
            >
              {pendingToggle?.is_active
                  ? t("branches.deactivateConfirm")
                  : t("branches.activateConfirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
