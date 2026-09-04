"use client"

import { ArrowUpRight, ClipboardCopy, Copy, Pencil, Plus, Trash2 } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { useRouter } from "next/navigation"

import { useTermColumns, useTermFilters } from "@/components/academic/term-columns"
import {
  TermCloneCopyDialog,
  type TermGridAction,
} from "@/components/academic/term-clone-copy-dialog"
import { TermSheet } from "@/components/academic/term-sheet"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { ApiError, apiFetch } from "@/lib/api"
import { useRefList } from "@/lib/data/use-ref-list"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, Paginated, Term } from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"

export default function SemestersPage() {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [terms, setTerms] = useState<Term[] | null>(null)
  const { items: years } = useRefList<AcademicYear>("/academic-years")
  const [editing, setEditing] = useState<Term | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [gridAction, setGridAction] = useState<TermGridAction>(null)
  const router = useRouter()
  // School → Branch narrowing for the school-wide / platform workspaces —
  // applied server-side (refetch), the rest of the filters stay client-side.
  const scope = useScopeQuery()

  const canUpdate = permissions.includes("academic_years.update")
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers create from the school-wide workspace too — the sheet
  // asks for the target branch (BranchField).
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  function refetchTerms() {
    apiFetch<Paginated<Term>>(`/terms?per_page=100${scope.params}`)
      .then((res) => setTerms(res.data))
      .catch(() => {})
  }

  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    let cancelled = false
    apiFetch<Paginated<Term>>(`/terms?per_page=100${scope.params}`)
      .then((res) => !cancelled && setTerms(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : t("terms.loadFailed"))
        setTerms([])
      })
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [hasBranch, isGlobal, active.branchId, active.schoolId, scope.key])

  function handleSaved(term: Term) {
    setTerms((prev) => {
      const list = prev ?? []
      return list.some((x) => x.id === term.id)
        ? list.map((x) => (x.id === term.id ? term : x))
        : [term, ...list]
    })
  }

  async function handleDelete(term: Term) {
    try {
      await apiFetch(`/terms/${term.id}`, { method: "DELETE" })
      setTerms((prev) => (prev ?? []).filter((x) => x.id !== term.id))
      toast.success(t("terms.deleted"))
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Something went wrong.")
    }
  }

  // Activating a semester auto-closes siblings server-side — refetch so the
  // table reflects every affected row, not just the changed one.
  const columns = useTermColumns({
    showBranch: isGlobal,
    allTerms: terms ?? [],
    canUpdateStatus: canUpdate,
    onStatusChanged: () => refetchTerms(),
  })
  const filterDefs = useTermFilters({ terms: terms ?? [] })

  // Grouped display: newest year first, then program, then sequence — the
  // group header ("2018 E.C. — Regular") makes a flat semester list readable.
  const groupKey = (term: Term) =>
    [
      isGlobal ? `${term.school_name ?? ""} · ${term.branch_name ?? ""} — ` : "",
      term.academic_year_name ?? "",
      term.program?.name ? ` — ${term.program.name}` : "",
    ].join("")

  const grouped = useMemo(
    () =>
      (terms ?? [])
        .slice()
        .sort(
          (a, b) =>
            b.academic_year_id - a.academic_year_id ||
            (a.program?.name ?? "").localeCompare(b.program?.name ?? "") ||
            a.sequence - b.sequence
        ),
    [terms]
  )

  return (
    <div className="space-y-6">
      {confirmDialog}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {t("terms.pageTitle")}
          </h1>
          <p className="text-sm text-muted-foreground">{t("terms.pageSubtitle")}</p>
        </div>
        {canUpdate && canTargetBranch && (
          <Button
            className="h-11"
            onClick={() => {
              setEditing(null)
              setSheetOpen(true)
            }}
          >
            <Plus className="size-4" />
            {t("terms.create")}
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
          data={grouped}
          loading={terms === null}
          groupBy={groupKey}
          renderGroupHeader={(key, rows) => (
            <span className="flex items-center gap-2">
              <span>{key}</span>
              <span className="font-normal text-muted-foreground">
                {t("terms.groupCount", { count: rows.length })}
              </span>
              {rows.some((row) => row.status === "active") && (
                <span className="rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-medium text-success">
                  {t("terms.groupActive")}
                </span>
              )}
            </span>
          )}
          searchKeys={["name", "academic_year_name"]}
          searchPlaceholder={tc("actions.search")}
          filters={[...scope.filters, ...filterDefs]}
          filterValues={scope.values}
          onFilterChange={scope.setFilter}
          onRowClick={(row) => router.push(`/semesters/${row.id}`)}
          actions={[
            {
              label: t("matrix.openAction"),
              icon: ArrowUpRight,
              onClick: (row: Term) => router.push(`/semesters/${row.id}`),
            },
            ...(canUpdate
              ? [
                  {
                    label: tc("actions.edit"),
                    icon: Pencil,
                    onClick: (row: Term) => {
                      setEditing(row)
                      setSheetOpen(true)
                    },
                  },
                  {
                    label: t("terms.cloneAction"),
                    icon: Copy,
                    onClick: (row: Term) => setGridAction({ mode: "clone", term: row }),
                  },
                  {
                    label: t("terms.copyAction"),
                    icon: ClipboardCopy,
                    onClick: (row: Term) => setGridAction({ mode: "copy", term: row }),
                  },
                  {
                    label: tc("actions.delete"),
                    icon: Trash2,
                    destructive: true,
                    onClick: (row: Term) =>
                      confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.name })),
                  },
                ]
              : []),
          ]}
          emptyMessage={t("terms.empty")}
          exportFilename="semesters"
        />
      )}

      <TermCloneCopyDialog
        action={gridAction}
        terms={terms ?? []}
        onOpenChange={(open) => !open && setGridAction(null)}
        onDone={() => refetchTerms()}
      />

      <TermSheet
        term={editing}
        academicYears={years}
        open={sheetOpen}
        onOpenChange={(v) => {
          setSheetOpen(v)
          if (!v) setEditing(null)
        }}
        onSaved={handleSaved}
      />
    </div>
  )
}
