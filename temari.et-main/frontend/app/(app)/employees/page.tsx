"use client"

import { GitBranch, MessageCircleMore, Pencil, Plus, Trash2, Undo2 } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useMemo, useState } from "react"
import { toast } from "sonner"

import { useChatLauncher } from "@/components/chat/chat-launcher"
import {
  AffiliationsCell,
  affiliationExport,
} from "@/components/users/affiliations-cell"
import { ManageBranchesSheet } from "@/components/users/manage-branches-sheet"
import { Badge } from "@/components/ui/badge"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { DateRangeFilter } from "@/components/ui/date-range-filter"
import { Button } from "@/components/ui/button"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { ApiError, apiFetch } from "@/lib/api"
import { runBulk, useBulkConfirm } from "@/components/ui/bulk-actions"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { JOB_TITLES } from "@/lib/data"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useContextsResponse, useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { AdminUser, Employee, EmploymentType } from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { useScopeFilters } from "@/lib/use-scope-filters"

const EMPLOYMENT_TYPES: EmploymentType[] = [
  "full_time",
  "part_time",
  "volunteer",
  "substitute",
  "contract",
]

export default function EmployeesPage() {
  const { t } = useTranslation("employees")
  const { t: tc } = useTranslation("common")
  const { t: tChat } = useTranslation("chat")
  const { openChat, canTarget } = useChatLauncher()
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { confirmBulk, bulkDialog } = useBulkConfirm()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  // Branch access management, same lane as the Users page: principals may
  // assign staff into branches; directors manage access within their own.
  const [manageUser, setManageUser] = useState<AdminUser | null>(null)
  const [manageOpen, setManageOpen] = useState(false)

  const canCreate = permissions.includes("employees.create")
  const canUpdate = permissions.includes("employees.update")
  const canDelete = permissions.includes("employees.delete")
  const canAssignBranch = permissions.includes("users.assign_branch")
  const canManageBranchAccess = permissions.includes("users.manage_branch_access")
  const canOpenBranchSheet = canAssignBranch || canManageBranchAccess
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers hire from the school-wide workspace too — the wizard
  // asks for the target branch (BranchField).
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  const table = useServerTable<Employee>({
    endpoint: "/employees",
    exportEndpoint: "/employees/export",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: hasBranch || isGlobal,
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("loadFailed"),
  })

  // Branch options feed both the (global-context) filter and the assign
  // sheet — shared, auto-refreshing source, so a new branch shows up live.
  const { data: contextsData } = useContextsResponse()
  const branchOptions = useMemo(
    () =>
      (contextsData?.schools ?? []).flatMap((s) =>
        s.branches.map((b) => ({ id: b.id, name: b.name, schoolName: s.name })),
      ),
    [contextsData],
  )

  async function handleDelete(employee: Employee) {
    try {
      await apiFetch(`/employees/${employee.id}`, { method: "DELETE" })
      toast.success(t("deleted"))
      await table.refetch()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    }
  }

  const columns: DataTableColumn<Employee>[] = [
    ...(isGlobal
      ? [
          {
            key: "branch_name",
            label: tc("columns.branch"),
            render: (row: Employee) => (
              <span className="text-xs text-muted-foreground">
                {row.school_name} · {row.branch_name}
              </span>
            ),
            exportValue: (row: Employee) =>
              [row.school_name, row.branch_name].filter(Boolean).join(" · "),
          } as DataTableColumn<Employee>,
        ]
      : []),
    {
      key: "first_name",
      label: t("columns.name"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="flex min-w-0 items-center gap-2.5">
          <PersonAvatar
            name={row.full_name}
            photoUrl={row.photo_url}
            className="size-7 text-[10px]"
          />
          <span className="truncate font-medium">{row.full_name}</span>
        </div>
      ),
      exportValue: (row) => row.full_name,
    },
    {
      key: "job_title",
      label: t("columns.jobTitle"),
      mobileHidden: true,
      // A staff member may hold several jobs at once (director + teacher …).
      render: (row) =>
        (row.active_job_titles ?? []).length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {(row.active_job_titles ?? []).map((code) => (
              <Badge key={code} variant="secondary" className="text-[11px]">
                {t(`jobTitles.${code}`)}
              </Badge>
            ))}
          </div>
        ) : (
          "—"
        ),
      exportValue: (row) =>
        (row.active_job_titles ?? []).map((code) => t(`jobTitles.${code}`)).join(", "),
    },
    {
      key: "phone",
      label: t("columns.phone"),
      sortable: true,
      render: (row) => (
        <ContactActionCell
          value={row.phone}
          name={row.full_name}
          chat={row.user ? { kind: "user", userId: row.user.id, name: row.full_name } : undefined}
        />
      ),
      exportValue: (row) => row.phone ?? "",
    },
    {
      key: "access",
      label: t("columns.access"),
      // Same School → Branch → Role tree as the Users page, driven by the
      // linked account's memberships.
      render: (row) =>
        row.user ? (
          <AffiliationsCell user={row.user} />
        ) : (
          <span className="text-sm text-muted-foreground">—</span>
        ),
      exportValue: (row) => (row.user ? affiliationExport(row.user) : ""),
    },
    {
      key: "employment_type",
      label: t("columns.employmentType"),
      mobileHidden: true,
      render: (row) =>
        row.primary_position?.employment_type
          ? t(`employmentTypes.${row.primary_position.employment_type}`)
          : "—",
      exportValue: (row) =>
        row.primary_position?.employment_type
          ? t(`employmentTypes.${row.primary_position.employment_type}`)
          : "",
    },
    {
      key: "is_active",
      label: t("columns.status"),
      sortable: true,
      render: (row) => (
        <Badge variant={row.is_active ? "default" : "secondary"}>
          {row.is_active ? tc("states.active") : tc("states.inactive")}
        </Badge>
      ),
      exportValue: (row) => (row.is_active ? tc("states.active") : tc("states.inactive")),
    },
  ]

  const scopeFilters = useScopeFilters(table.filters)

  const filterDefs: DataTableFilter[] = [
    {
      key: "is_active",
      label: tc("filters.status"),
      options: [
        { label: tc("states.active"), value: "true" },
        { label: tc("states.inactive"), value: "false" },
      ],
    },
    {
      key: "job_title",
      label: t("columns.jobTitle"),
      options: JOB_TITLES.map((code) => ({
        label: t(`jobTitles.${code}`),
        value: code,
      })),
    },
    {
      key: "employment_type",
      label: tc("filters.employmentType"),
      options: EMPLOYMENT_TYPES.map((value) => ({
        label: t(`employmentTypes.${value}`),
        value,
      })),
    },
  ]
  // Branch narrowing comes from the shared scope filters (school → branch)
  // spread ahead of these defs — no bespoke branch filter here.
  // Deleted records are a Temari.et platform view — school admins never see them.
  if (isPlatform)
    filterDefs.push({
      key: "trashed",
      label: tc("filters.deleted"),
      options: [{ label: tc("filters.showDeleted"), value: "with" }],
    })

  const rowActions = [
    // Staff↔staff direct — needs a linked portal account, never yourself.
    {
      label: tChat("launcher.chatStaff"),
      icon: MessageCircleMore,
      onClick: (row: Employee) => {
        if (row.user) void openChat({ kind: "user", userId: row.user.id, name: row.full_name })
      },
      hidden: (row: Employee) =>
        !row.user || !canTarget({ kind: "user", userId: row.user.id }),
    },
    ...(canUpdate
      ? [
          {
            label: tc("actions.edit"),
            icon: Pencil,
            onClick: (row: Employee) => router.push(`/employees/${row.id}/edit`),
          },
        ]
      : []),
    ...(canOpenBranchSheet
      ? [
          {
            label: canAssignBranch ? t("actions.assignToBranch") : t("actions.manageBranches"),
            icon: GitBranch,
            hidden: (row: Employee) => !row.user,
            onClick: (row: Employee) => {
              if (!row.user) return
              setManageUser(row.user)
              setManageOpen(true)
            },
          },
        ]
      : []),
    ...(canDelete
      ? [
          {
            label: tc("actions.delete"),
            icon: Trash2,
            destructive: true,
            onClick: (row: Employee) =>
              confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.full_name })),
          },
        ]
      : []),
  ]

  // Removing staff and putting them back: the same authority, mirrored from the
  // row actions. Restore only appears once the "Show deleted" filter is on,
  // since that is the only way a removed row is on screen.
  const bulkActions = canDelete
    ? [
        {
          label: tc("actions.delete"),
          icon: Trash2,
          destructive: true,
          onClick: (rows: Employee[]) =>
            confirmBulk({
              title: t("bulk.deleteTitle", { count: rows.length }),
              description: t("bulk.deleteDesc"),
              confirmLabel: tc("actions.delete"),
              destructive: true,
              action: async () => {
                await runBulk({
                  url: "/employees/bulk/delete",
                  ids: rows.map((r) => r.id),
                  countKey: "deleted",
                  success: (count) => t("bulk.deleted", { count }),
                  tc,
                })
                await table.refetch()
              },
            }),
        },
        {
          label: tc("actions.restore"),
          icon: Undo2,
          onClick: (rows: Employee[]) =>
            confirmBulk({
              title: t("bulk.restoreTitle", { count: rows.length }),
              description: t("bulk.restoreDesc"),
              confirmLabel: tc("actions.restore"),
              action: async () => {
                await runBulk({
                  url: "/employees/bulk/restore",
                  ids: rows.map((r) => r.id),
                  countKey: "restored",
                  success: (count) => t("bulk.restored", { count }),
                  tc,
                })
                await table.refetch()
              },
            }),
        },
      ]
    : undefined

  return (
    <div className="space-y-6">
      {confirmDialog}
      {bulkDialog}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {t("title")}
          </h1>
          <p className="text-sm text-muted-foreground">{t("subtitle")}</p>
        </div>
        {canCreate && canTargetBranch && (
          <Button asChild className="h-11">
            <Link href="/employees/new">
              <Plus className="size-4" />
              {t("create")}
            </Link>
          </Button>
        )}
      </div>

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={table.rows}
          loading={table.loading}
          serverMode
          searchable
          searchValue={table.searchInput}
          onSearchChange={table.setSearchInput}
          searchPlaceholder={tc("actions.search")}
          filters={[...scopeFilters, ...filterDefs]}
          filterValues={table.filters}
          onFilterChange={table.setFilter}
          toolbarSlot={
            <DateRangeFilter
              fields={[
                { key: "hired_from", label: tc("filters.hiredFrom") },
                { key: "hired_to", label: tc("filters.hiredTo") },
              ]}
              values={table.dates}
              onChange={table.setDate}
              onClear={table.clearDates}
            />
          }
          onSortChange={table.onSortChange}
          onExport={table.handleExport}
          actions={rowActions.length > 0 ? rowActions : undefined}
          bulkActions={bulkActions}
          onRowClick={(row) => router.push(`/employees/${row.id}`)}
          emptyMessage={t("empty")}
          exportFilename="employees"
          pagination={table.pagination}
        />
      )}

      {/* Branch access sheet (assign / manage), reusing the Users page flow. */}
      <ManageBranchesSheet
        user={manageUser}
        open={manageOpen}
        onOpenChange={(v) => {
          setManageOpen(v)
          if (!v) setManageUser(null)
        }}
        branchOptions={branchOptions}
        canAssign={canAssignBranch}
        onUpdated={() => table.refetch()}
      />
    </div>
  )
}
