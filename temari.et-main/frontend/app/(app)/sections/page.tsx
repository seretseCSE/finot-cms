"use client"

import { DoorOpen, Pencil, Trash2, Users } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { SectionSheet } from "@/components/academic/section-sheet"
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
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, Employee, GradeLevel, Paginated, Section } from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"
import { cn } from "@/lib/utils"

function gradeName(grade: GradeLevel | undefined): string {
  return grade?.name ?? "—"
}

const NO_HOMEROOM = "none"

/** A section row shown for ONE academic year (homerooms are year-scoped). */
type SectionRow = Section & {
  academic_year_id?: number
  academic_year_name?: string
  /** Flat copy of grade_level.name — search/filters read flat keys only. */
  grade_name?: string
}

/** Flat keys the table's search + filters match on. */
function toRow(section: Section): SectionRow {
  return { ...section, grade_name: gradeName(section.grade_level) }
}

/** A pending inline change awaiting the confirmation dialog. */
type PendingChange =
  | {
      kind: "homeroom"
      section: SectionRow
      employeeId: number | null
      employeeName: string | null
    }
  | { kind: "status"; section: SectionRow; active: boolean }

export default function SectionsPage() {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  // Branch-scoped grade offering, session-cached across pages.
  const { grades: gradeLevels } = useGradeLevels()
  const [sections, setSections] = useState<SectionRow[] | null>(null)
  const [years, setYears] = useState<AcademicYear[] | null>(null)
  // Table filters are CONTROLLED so the academic-year filter can default to
  // the current year (multi-select, like the semesters table).
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})
  // School → Branch narrowing for the school-wide / platform workspaces —
  // applied server-side (refetch); the other filters stay client-side.
  const scope = useScopeQuery({
    values: filterValues,
    setFilter: (key, value) => setFilterValues((prev) => ({ ...prev, [key]: value })),
  })
  const [reloadKey, setReloadKey] = useState(0)
  const [teachers, setTeachers] = useState<Employee[]>([])
  const [editing, setEditing] = useState<Section | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [pending, setPending] = useState<PendingChange | null>(null)
  const [working, setWorking] = useState(false)

  const canCreate = permissions.includes("sections.create")
  const canUpdate = permissions.includes("sections.update")
  const canDelete = permissions.includes("sections.delete")
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers create from the school-wide workspace too — the sheet
  // asks for the target branch (BranchField).
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  useEffect(() => {
    if (!hasBranch) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on context switch
      setYears(null)
      setFilterValues({})
      setTeachers([])
      return
    }
    let cancelled = false
    apiFetch<Paginated<AcademicYear>>("/academic-years?per_page=100")
      .then((res) => {
        if (cancelled) return
        setYears(res.data)
        // Default the year FILTER to the current (active) year; else the
        // newest — never "no year", otherwise homerooms would silently have
        // nowhere to save. The user may widen or clear it afterwards.
        const current = res.data.find((y) => y.status === "active")?.id ?? res.data[0]?.id ?? null
        setFilterValues((prev) =>
          prev.academic_year_id === undefined && current !== null
            ? { ...prev, academic_year_id: String(current) }
            : prev,
        )
      })
      .catch(() => setYears([]))
    // Only the homeroom picker reads this list, and only sections.update
    // renders it — roles with a read-only sections.view (finance officer,
    // registrar) have no employees.view and would just 403 on every load.
    if (canUpdate) {
      apiFetch<Paginated<Employee>>("/employees?job_title=teacher&is_active=true&per_page=100")
        .then((res) => !cancelled && setTeachers(res.data))
        .catch(() => {})
    }
    return () => {
      cancelled = true
    }
  }, [hasBranch, active.branchId, canUpdate])

  /** Year ids the filter selects — empty means "every year". */
  const selectedYearIds = useMemo(
    () =>
      (filterValues.academic_year_id ?? "")
        .split(",")
        .filter(Boolean)
        .map(Number),
    [filterValues.academic_year_id],
  )
  const selectedYearsKey = selectedYearIds.join(",")

  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    // Branch view waits for the years to load so the default filter applies
    // before the first fetch.
    if (hasBranch && years === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset to the loading state
    setSections(null)

    // One request per selected year: each row carries THAT year's homeroom.
    const yearIds = hasBranch
      ? selectedYearIds.length > 0
        ? selectedYearIds
        : (years ?? []).map((y) => y.id)
      : []
    const yearName = new Map((years ?? []).map((y) => [y.id, y.name]))

    const request: Promise<SectionRow[]> =
      yearIds.length === 0
        ? apiFetch<Paginated<Section>>(`/sections?per_page=100${scope.params}`).then((res) =>
            res.data.map(toRow),
          )
        : Promise.all(
            yearIds.map((id) =>
              apiFetch<Paginated<Section>>(
                `/sections?academic_year_id=${id}&per_page=100`,
              ).then((res) =>
                res.data.map((section) => ({
                  ...toRow(section),
                  academic_year_id: id,
                  academic_year_name: yearName.get(id),
                })),
              ),
            ),
          ).then((groups) => groups.flat())

    request
      .then((rows) => {
        if (!cancelled) setSections(rows)
      })
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : "Failed to load sections.")
        setSections([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- selectedYearsKey stands in for selectedYearIds
  }, [hasBranch, isGlobal, active.branchId, active.schoolId, years, selectedYearsKey, reloadKey, scope.key])

  /** Rows are per-year snapshots — after any write, re-read the truth. */
  function reload() {
    setReloadKey((k) => k + 1)
  }

  // The year new sections / sheet-managed homerooms save into: the first
  // selected year, else the current one.
  const primaryYearId =
    selectedYearIds[0] ??
    (years ?? []).find((y) => y.status === "active")?.id ??
    (years ?? [])[0]?.id ??
    null

  async function handleDelete(section: Section) {
    try {
      await apiFetch(`/sections/${section.id}`, { method: "DELETE" })
      setSections((prev) => (prev ?? []).filter((s) => s.id !== section.id))
      toast.success(t("sections.deleted"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Something went wrong.")
    }
  }

  /** Apply a confirmed inline change (homeroom / status). */
  async function applyPending() {
    if (!pending) return
    setWorking(true)
    try {
      const rowYearId = pending.section.academic_year_id ?? primaryYearId
      const body =
        pending.kind === "homeroom"
          ? {
              name: pending.section.name,
              homeroom_employee_id: pending.employeeId,
              ...(rowYearId ? { academic_year_id: rowYearId } : {}),
            }
          : { name: pending.section.name, is_active: pending.active }
      await apiFetch<{ data: Section }>(`/sections/${pending.section.id}`, {
        method: "PUT",
        body,
      })
      reload()
      toast.success(t("sections.updated"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Something went wrong.")
    } finally {
      setWorking(false)
      setPending(null)
    }
  }

  // With several years selected the same section appears once per year — the
  // year column tells the rows apart.
  const showYearColumn = hasBranch && selectedYearIds.length !== 1 && (years?.length ?? 0) > 1

  // Homeroom filter options come from the loaded rows (covers the global view
  // too, where the teachers list isn't fetched).
  const homeroomOptions = useMemo(
    () =>
      [...new Set((sections ?? []).map((s) => s.homeroom_name).filter(Boolean) as string[])].sort(
        (a, b) => a.localeCompare(b),
      ),
    [sections],
  )

  const columns: DataTableColumn<SectionRow>[] = useMemo(
    () => [
      ...(isGlobal
        ? [
            {
              key: "branch_name",
              label: tc("columns.branch"),
              render: (row: SectionRow) => (
                <span className="text-xs text-muted-foreground">
                  {row.school_name} · {row.branch_name}
                </span>
              ),
            } as DataTableColumn<SectionRow>,
          ]
        : []),
      {
        key: "grade",
        label: t("sections.grade"),
        sortable: true,
        primary: true,
        render: (row) => <span className="font-medium">{gradeName(row.grade_level)}</span>,
        exportValue: (row) => gradeName(row.grade_level),
      },
      ...(showYearColumn
        ? [
            {
              key: "academic_year_name",
              label: t("sections.academicYear"),
              render: (row: SectionRow) => (
                <span className="text-xs text-muted-foreground">
                  {row.academic_year_name ?? "—"}
                </span>
              ),
              exportValue: (row: SectionRow) => row.academic_year_name ?? "",
            } as DataTableColumn<SectionRow>,
          ]
        : []),
      {
        key: "name",
        label: t("sections.name"),
        sortable: true,
      },
      {
        key: "room_number",
        label: t("sections.roomNumber"),
        mobileHidden: true,
        render: (row) =>
          row.room_number ? (
            <span className="inline-flex items-center gap-1 text-sm tabular-nums">
              <DoorOpen className="size-3.5 text-muted-foreground" />
              {row.room_number}
            </span>
          ) : (
            "—"
          ),
        exportValue: (row) => row.room_number ?? "",
      },
      {
        key: "capacity",
        label: t("sections.capacity"),
        mobileHidden: true,
        render: (row) => row.capacity ?? "—",
        exportValue: (row) => (row.capacity ? String(row.capacity) : ""),
      },
      {
        key: "homeroom_name",
        label: t("sections.homeroomName"),
        // Inline, confirmed change — the fastest way to (re)assign homerooms
        // at the start of a year.
        render: (row) =>
          canUpdate && hasBranch ? (
            <div onClick={(e) => e.stopPropagation()}>
              <Select
                value={row.homeroom_employee_id ? String(row.homeroom_employee_id) : NO_HOMEROOM}
                onValueChange={(v) => {
                  const employeeId = v === NO_HOMEROOM ? null : Number(v)
                  if (employeeId === (row.homeroom_employee_id ?? null)) return
                  setPending({
                    kind: "homeroom",
                    section: row,
                    employeeId,
                    employeeName: teachers.find((x) => x.id === employeeId)?.full_name ?? null,
                  })
                }}
              >
                <SelectTrigger
                  className={cn(
                    "h-8 w-auto min-w-36 gap-1.5 rounded-full border-border/70 bg-muted/30 px-3 text-xs font-medium",
                    !row.homeroom_employee_id && "text-muted-foreground",
                  )}
                  aria-label={t("sections.homeroomName")}
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NO_HOMEROOM}>{t("sections.noHomeroom")}</SelectItem>
                  {teachers.map((teacher) => (
                    <SelectItem key={teacher.id} value={String(teacher.id)}>
                      {teacher.full_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          ) : (
            (row.homeroom_name ?? "—")
          ),
        exportValue: (row) => row.homeroom_name ?? "",
      },
      {
        key: "is_active",
        label: tc("columns.status"),
        render: (row) =>
          canUpdate ? (
            <div className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
              <Switch
                checked={row.is_active}
                onCheckedChange={(v) => setPending({ kind: "status", section: row, active: v })}
                aria-label={tc("columns.status")}
              />
              <span className="text-xs text-muted-foreground">
                {row.is_active ? tc("states.active") : tc("states.inactive")}
              </span>
            </div>
          ) : (
            <Badge variant={row.is_active ? "default" : "secondary"}>
              {row.is_active ? tc("states.active") : tc("states.inactive")}
            </Badge>
          ),
        exportValue: (row) => (row.is_active ? "Active" : "Inactive"),
      },
    ],
    [t, tc, isGlobal, canUpdate, hasBranch, teachers, showYearColumn],
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {t("sections.title")}
          </h1>
          <p className="text-sm text-muted-foreground">{t("sections.subtitle")}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {canUpdate && hasBranch && (
            <Button variant="outline" asChild>
              <Link href="/sections/assign">
                <Users className="size-4" />
                {tc("nav.assignSections")}
              </Link>
            </Button>
          )}
          {canCreate && canTargetBranch && (
          <SectionSheet
            gradeLevels={gradeLevels}
            academicYearId={primaryYearId}
            existingSections={sections ?? []}
            section={editing}
            open={sheetOpen}
            onOpenChange={(v) => {
              setSheetOpen(v)
              if (!v) setEditing(null)
            }}
            onSaved={reload}
            showTrigger
          />
          )}
        </div>
      </div>

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={sections ?? []}
          loading={sections === null}
          searchKeys={["name", "room_number", "grade_name", "homeroom_name"]}
          searchPlaceholder={tc("actions.search")}
          filters={[
            ...scope.filters,
            // Year first — the primary grouping (homerooms are year-scoped).
            // Defaults to the current year; multiple years can be selected.
            ...(hasBranch && (years?.length ?? 0) > 0
              ? [
                  {
                    key: "academic_year_id",
                    label: t("sections.academicYear"),
                    options: (years ?? []).map((year) => ({
                      label:
                        year.status === "active"
                          ? `${year.name} · ${t("years.statuses.active")}`
                          : year.name,
                      value: String(year.id),
                    })),
                  },
                ]
              : []),
            {
              key: "grade_level_id",
              label: t("sections.grade"),
              options: gradeLevels.map((g) => ({
                label: gradeName(g),
                value: String(g.id),
              })),
            },
            ...(homeroomOptions.length > 0
              ? [
                  {
                    key: "homeroom_name",
                    label: t("sections.homeroomName"),
                    options: homeroomOptions.map((name) => ({ label: name, value: name })),
                  },
                ]
              : []),
            {
              key: "is_active",
              label: tc("states.active"),
              options: [
                { label: tc("states.active"), value: "true" },
                { label: tc("states.inactive"), value: "false" },
              ],
            },
          ]}
          filterValues={filterValues}
          onFilterChange={(key, value) => setFilterValues((prev) => ({ ...prev, [key]: value }))}
          onRowClick={(row) => router.push(`/sections/${row.id}`)}
          actions={
            canUpdate || canDelete
              ? [
                  ...(canUpdate
                    ? [
                        {
                          label: tc("actions.edit"),
                          icon: Pencil,
                          onClick: (row: Section) => {
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
                          onClick: (row: Section) =>
                            confirmDelete(
                              () => handleDelete(row),
                              tc("confirmDelete.named", { name: row.name }),
                            ),
                        },
                      ]
                    : []),
                ]
              : undefined
          }
          emptyMessage={t("sections.empty")}
          exportFilename="sections"
        />
      )}

      {/* Confirmation for inline homeroom/status changes. */}
      <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {pending?.kind === "homeroom"
                ? t("sections.homeroomConfirmTitle")
                : t("sections.statusConfirmTitle")}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {pending?.kind === "homeroom"
                ? pending.employeeId
                  ? t("sections.homeroomConfirmDesc", {
                      name: `${gradeName(pending.section.grade_level)} ${pending.section.name}`,
                      teacher: pending.employeeName ?? "",
                    })
                  : t("sections.homeroomClearDesc", {
                      name: `${gradeName(pending.section.grade_level)} ${pending.section.name}`,
                    })
                : pending?.active
                  ? t("sections.statusActivateDesc", {
                      name: `${gradeName(pending.section.grade_level)} ${pending.section.name}`,
                    })
                  : t("sections.statusDeactivateDesc", {
                      name: `${gradeName(pending?.section.grade_level)} ${pending?.section.name}`,
                    })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={working}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={working}
              onClick={(e) => {
                e.preventDefault()
                applyPending()
              }}
            >
              {tc("actions.confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
