"use client"

import { useEffect, useState } from "react"
import { FilePlus2, Landmark, Pencil, Plus, Send, Trash2 } from "lucide-react"
import { toast } from "sonner"

import { useFeeColumns, useFeeFilters } from "@/components/fees/fee-columns"
import { BankAccountsSheet } from "@/components/fees/bank-accounts-sheet"
import { NotifyFeeDialog } from "@/components/fees/notify-fee-dialog"
import { Button } from "@/components/ui/button"
import { FeeStructureSheet } from "@/components/fees/fee-structure-sheet"
import { DataTable } from "@/components/ui/data-table"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useRefList } from "@/lib/data/use-ref-list"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  FeeStructure,
  Paginated,
} from "@/lib/types"
import { useScopeQuery } from "@/lib/use-scope-filters"

export default function FeesPage() {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [fees, setFees] = useState<FeeStructure[] | null>(null)
  // Branch-scoped grade offering, session-cached across pages.
  const { grades: gradeLevels } = useGradeLevels()
  const [editing, setEditing] = useState<FeeStructure | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [notifying, setNotifying] = useState<FeeStructure | null>(null)
  // School → Branch narrowing for the school-wide / platform workspaces —
  // applied server-side (refetch), the rest of the filters stay client-side.
  const scope = useScopeQuery()

  const canManage = permissions.includes("fees.manage")
  const [accountsOpen, setAccountsOpen] = useState(false)
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers create from the school-wide workspace too — the sheets
  // ask for the target branch (BranchField).
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  // Shared, auto-refreshing years list — a year created elsewhere shows up
  // in the fee sheet without a reload.
  const { items: academicYears } = useRefList<AcademicYear>("/academic-years", {
    enabled: hasBranch || isGlobal,
  })

  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    let cancelled = false
    apiFetch<Paginated<FeeStructure>>(`/fee-structures?per_page=100${scope.params}`)
      .then((res) => !cancelled && setFees(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(
          error instanceof ApiError ? error.message : "Failed to load fees."
        )
        setFees([])
      })
    return () => {
      cancelled = true
    }
  }, [hasBranch, isGlobal, active.branchId, active.schoolId, scope.params])

  function handleSaved(fee: FeeStructure) {
    setFees((prev) => {
      const list = prev ?? []
      const exists = list.some((f) => f.id === fee.id)
      return exists
        ? list.map((f) => (f.id === fee.id ? fee : f))
        : [fee, ...list]
    })
  }

  async function handleDelete(fee: FeeStructure) {
    try {
      await apiFetch(`/fee-structures/${fee.id}`, { method: "DELETE" })
      setFees((prev) => (prev ?? []).filter((f) => f.id !== fee.id))
      toast.success(t("structures.deleted"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Something went wrong."
      )
    }
  }

  async function handleGenerate(fee: FeeStructure) {
    try {
      const res = await apiFetch<{ meta: { created: number } }>(
        `/fee-structures/${fee.id}/generate-invoices`,
        { method: "POST", body: {} }
      )
      toast.success(t("structures.generated", { count: res.meta.created }))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Something went wrong."
      )
    }
  }

  const columns = useFeeColumns({ showBranch: isGlobal })
  const filterDefs = useFeeFilters({ fees: fees ?? [] })

  const rowActions = canManage
    ? [
        {
          label: t("structures.generate"),
          icon: FilePlus2,
          onClick: handleGenerate,
        },
        {
          // Catch-up notices for fees billed while notifications were off —
          // registration fees carry no notifications at all.
          label: t("notify.action"),
          icon: Send,
          onClick: (row: FeeStructure) => setNotifying(row),
          hidden: (row: FeeStructure) => row.type === "registration",
        },
        {
          label: tc("actions.edit"),
          icon: Pencil,
          onClick: (row: FeeStructure) => {
            setEditing(row)
            setSheetOpen(true)
          },
        },
        {
          label: tc("actions.delete"),
          icon: Trash2,
          destructive: true,
          onClick: (row: FeeStructure) =>
            confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.name })),
        },
      ]
    : undefined

  return (
    <div className="space-y-6">
      {confirmDialog}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6 md:px-8">
        <div>
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {t("structures.title")}
          </h1>
          <p className="text-sm text-muted-foreground">
            {t("structures.subtitle")}
          </p>
        </div>
        {canManage && canTargetBranch && (
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" className="h-11" onClick={() => setAccountsOpen(true)}>
              <Landmark className="size-4" />
              {t("accounts.manage")}
            </Button>
            <Button
              className="h-11"
              onClick={() => {
                setEditing(null)
                setSheetOpen(true)
              }}
            >
              <Plus className="size-4" />
              {t("structures.create")}
            </Button>
          </div>
        )}
      </div>

      {/* Mounted unconditionally: the row Edit action must work even where
          the create trigger is hidden (e.g. the platform workspace). */}
      <NotifyFeeDialog
        fee={notifying}
        open={notifying !== null}
        onOpenChange={(v) => !v && setNotifying(null)}
      />

      <FeeStructureSheet
        feeStructure={editing}
        academicYears={academicYears}
        gradeLevels={gradeLevels}
        open={sheetOpen}
        onOpenChange={(v) => {
          setSheetOpen(v)
          if (!v) setEditing(null)
        }}
        onSaved={handleSaved}
      />

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={fees ?? []}
          loading={fees === null}
          searchKeys={["name", "academic_year_name"]}
          searchPlaceholder={tc("actions.search")}
          filters={[...scope.filters, ...filterDefs]}
          filterValues={scope.values}
          onFilterChange={scope.setFilter}
          actions={rowActions}
          onRowClick={
            canManage
              ? (row) => {
                  setEditing(row)
                  setSheetOpen(true)
                }
              : undefined
          }
          emptyMessage={t("structures.empty")}
          exportFilename="fees"
        />
      )}

      <BankAccountsSheet open={accountsOpen} onOpenChange={setAccountsOpen} />
    </div>
  )
}
