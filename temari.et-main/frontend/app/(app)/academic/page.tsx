"use client"

import { Pencil, Trash2 } from "lucide-react"

import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { AcademicYearSheet } from "@/components/academic/academic-year-sheet"
import { YearStatusSelect } from "@/components/academic/year-status-select"
import { Badge } from "@/components/ui/badge"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, Paginated } from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"

export default function AcademicYearsPage() {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const [years, setYears] = useState<AcademicYear[] | null>(null)
  const [editing, setEditing] = useState<AcademicYear | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  // School → Branch narrowing for the school-wide / platform workspaces —
  // applied server-side (refetch), the rest of the filters stay client-side.
  const scope = useScopeQuery()

  const canCreate = permissions.includes("academic_years.create")
  const canUpdate = permissions.includes("academic_years.update")
  const canDelete = permissions.includes("academic_years.delete")
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers create from the school-wide workspace too — the sheet
  // asks for the target branch (BranchField).
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    let cancelled = false
    apiFetch<Paginated<AcademicYear>>(`/academic-years?per_page=100${scope.params}`)
      .then((res) => {
        if (!cancelled) setYears(res.data)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(
          error instanceof ApiError
            ? error.message
            : "Failed to load academic years."
        )
        setYears([])
      })
    return () => {
      cancelled = true
    }
  }, [hasBranch, isGlobal, active.branchId, active.schoolId, scope.params])

  function handleSaved(year: AcademicYear) {
    setYears((prev) => {
      const list = prev ?? []
      const exists = list.some((y) => y.id === year.id)
      const next = exists
        ? list.map((y) => (y.id === year.id ? year : y))
        : [year, ...list]
      // Mirror the backend: one operating year per branch. When this year just
      // became active, complete any sibling active year in the same branch so
      // the table matches the DB without a reload.
      if (year.status === "active") {
        return next.map((y) =>
          y.id !== year.id && y.branch_id === year.branch_id && y.status === "active"
            ? { ...y, status: "completed", status_label: t("years.statuses.completed"), is_current: false, is_active: false }
            : y
        )
      }
      return next
    })
  }

  async function handleDelete(year: AcademicYear) {
    try {
      await apiFetch(`/academic-years/${year.id}`, { method: "DELETE" })
      setYears((prev) => (prev ?? []).filter((y) => y.id !== year.id))
      toast.success(t("years.deleted"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Something went wrong."
      )
    }
  }

  const columns: DataTableColumn<AcademicYear>[] = useMemo(
    () => [
      ...(isGlobal
        ? [
            {
              key: "branch_name",
              label: tc("columns.branch"),
              render: (row: AcademicYear) => (
                <span className="text-xs text-muted-foreground">
                  {row.school_name} · {row.branch_name}
                </span>
              ),
            } as DataTableColumn<AcademicYear>,
          ]
        : []),
      {
        key: "name",
        label: t("years.name"),
        sortable: true,
        primary: true,
        render: (row) => (
          <span className="flex items-center gap-2 font-medium">
            {row.name}
            {row.is_current && <Badge>{t("years.current")}</Badge>}
          </span>
        ),
        exportValue: (row) => row.name,
      },
      {
        key: "terms_count",
        label: t("years.terms"),
        mobileHidden: true,
        render: (row) => row.terms?.length ?? row.terms_count ?? 0,
        exportValue: (row) => String(row.terms?.length ?? row.terms_count ?? 0),
      },
      {
        key: "fees_count",
        label: t("years.fees"),
        mobileHidden: true,
        render: (row) => row.fees_count ?? 0,
        exportValue: (row) => String(row.fees_count ?? 0),
      },
      {
        key: "starts_on",
        label: t("years.startsOn"),
        sortable: true,
        mobileHidden: true,
        render: (row) => row.starts_on ?? "—",
        exportValue: (row) => row.starts_on ?? "",
      },
      {
        key: "ends_on",
        label: t("years.endsOn"),
        mobileHidden: true,
        render: (row) => row.ends_on ?? "—",
        exportValue: (row) => row.ends_on ?? "",
      },
      {
        // Lifecycle as an in-place dropdown with confirmation — switching to
        // Active makes this the operating year and completes the previous one.
        key: "status",
        label: tc("columns.status"),
        render: (row) => (
          <YearStatusSelect
            year={row}
            years={years ?? []}
            canUpdate={canUpdate}
            onChanged={handleSaved}
          />
        ),
        exportValue: (row) => row.status_label,
      },
    ],

    [t, tc, isGlobal, canUpdate, years]
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {t("years.title")}
          </h1>
          <p className="text-sm text-muted-foreground">{t("years.subtitle")}</p>
        </div>
        {canCreate && canTargetBranch && (
          <AcademicYearSheet
            year={editing}
            open={sheetOpen}
            onOpenChange={(v) => {
              setSheetOpen(v)
              if (!v) setEditing(null)
            }}
            onSaved={handleSaved}
            showTrigger
          />
        )}
      </div>

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={years ?? []}
          loading={years === null}
          searchKeys={["name"]}
          searchPlaceholder={tc("actions.search")}
          filters={[
            ...scope.filters,
            {
              key: "status",
              label: tc("filters.status"),
              options: (["planned", "active", "completed", "archived"] as const).map((s) => ({
                label: t(`years.statuses.${s}`),
                value: s,
              })),
            },
          ]}
          filterValues={scope.values}
          onFilterChange={scope.setFilter}
          onRowClick={(row) => router.push(`/academic/${row.id}`)}
          actions={
            canUpdate || canDelete
              ? [
                  ...(canUpdate
                    ? [
                        {
                          label: tc("actions.edit"),
                          icon: Pencil,
                          onClick: (row: AcademicYear) => {
                            setEditing(row)
                            setSheetOpen(true)
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
                          onClick: (row: AcademicYear) =>
                            confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.name })),
                        },
                      ]
                    : []),
                ]
              : undefined
          }
          emptyMessage={t("years.empty")}
          exportFilename="academic-years"
        />
      )}
    </div>
  )
}
