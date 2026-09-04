"use client"

import { useRouter } from "next/navigation"

import { ContactCell, contactExport } from "@/components/schools/contact-cell"
import { CreateSchoolDialog } from "@/components/schools/create-school-dialog"
import { formatGradeSpan } from "@/components/schools/org-stats"
import { Badge } from "@/components/ui/badge"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import { DateRangeFilter } from "@/components/ui/date-range-filter"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useRequirePermission } from "@/lib/auth/use-require-permission"
import { useTranslation } from "@/lib/i18n"
import type { School } from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { fmtDate } from "@/lib/dates"

export default function SchoolsPage() {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const permissions = useEffectivePermissions()
  const router = useRouter()

  // School Management is Temari.et platform staff only. Principals/Directors
  // who reach this URL directly are bounced to the Unauthorized page.
  const { authorized } = useRequirePermission("schools.view")

  const canCreate = permissions.includes("schools.create")
  const canDelete = permissions.includes("schools.delete")

  const table = useServerTable<School>({
    endpoint: "/schools",
    exportEndpoint: "/schools/export",
    defaultSort: { key: "created_at", dir: "desc" },
  })

  const columns: DataTableColumn<School>[] = [
    {
      key: "name",
      label: tc("columns.name"),
      sortable: true,
      primary: true,
      render: (row) => (
        <div className="flex min-w-0 items-center gap-2.5">
          {row.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- signed R2 URL
            <img
              src={row.logo_url}
              alt=""
              className="bg-card size-7 shrink-0 rounded-lg border object-contain"
            />
          ) : (
            <span className="brand-tile flex size-7 shrink-0 items-center justify-center rounded-lg text-[11px] font-semibold text-white">
              {row.name.slice(0, 1)}
            </span>
          )}
          <span className="truncate font-medium">{row.name}</span>
        </div>
      ),
      exportValue: (row) => row.name,
    },
    {
      key: "principal",
      label: t("contacts.principal"),
      render: (row) => <ContactCell contact={row.principal} />,
      exportValue: (row) => contactExport(row.principal),
    },
    {
      key: "school_admin",
      label: t("contacts.itAdmin"),
      mobileHidden: true,
      render: (row) => <ContactCell contact={row.school_admin} />,
      exportValue: (row) => contactExport(row.school_admin),
    },
    {
      key: "branches_count",
      label: t("branches.title"),
      sortable: true,
      mobileHidden: true,
      render: (row) => <span className="tabular-nums">{row.branches_count ?? 0}</span>,
      exportValue: (row) => String(row.branches_count ?? 0),
    },
    {
      key: "grades",
      label: t("stats.gradesColumn"),
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
      key: "is_active",
      label: tc("columns.status"),
      sortable: true,
      render: (row) => (
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
  if (canDelete)
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
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">{t("title")}</h1>
          <p className="text-muted-foreground text-sm">{t("subtitle")}</p>
        </div>
        {canCreate && <CreateSchoolDialog onCreated={() => table.refetch()} />}
      </div>

      <DataTable
        columns={columns}
        data={table.rows}
        loading={table.loading}
        serverMode
        searchable
        searchValue={table.searchInput}
        onSearchChange={table.setSearchInput}
        searchPlaceholder={t("search")}
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
        onRowClick={(row) => router.push(`/schools/${row.id}`)}
        emptyMessage={t("empty")}
        exportFilename="schools"
        pagination={table.pagination}
      />
    </div>
  )
}
