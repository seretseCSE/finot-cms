"use client"

import Link from "next/link"
import { useParams } from "next/navigation"
import { ArrowLeft, ArrowUpRight, CalendarDays, CalendarRange, ClipboardCopy, Copy, Pencil, Plus, Trash2, Wallet } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { useRouter } from "next/navigation"

import { useTermColumns, useTermFilters } from "@/components/academic/term-columns"
import {
  TermCloneCopyDialog,
  type TermGridAction,
} from "@/components/academic/term-clone-copy-dialog"
import { TermSheet } from "@/components/academic/term-sheet"
import { YearStatusSelect } from "@/components/academic/year-status-select"
import { useFeeColumns, useFeeFilters } from "@/components/fees/fee-columns"
import { FeeStructureSheet } from "@/components/fees/fee-structure-sheet"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DataTable } from "@/components/ui/data-table"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useGradeLevels } from "@/lib/data/use-grade-levels"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear, FeeStructure, Term } from "@/lib/types"

export default function AcademicYearDetailPage() {
  const params = useParams<{ id: string }>()
  const yearId = Number(params.id)
  const { t } = useTranslation("academic")
  const { t: tf } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const permissions = useEffectivePermissions()

  const [year, setYear] = useState<AcademicYear | null>(null)
  // Branch-scoped grade offering, session-cached across pages.
  const { grades: gradeLevels } = useGradeLevels()

  const [editingTerm, setEditingTerm] = useState<Term | null>(null)
  const [termSheetOpen, setTermSheetOpen] = useState(false)
  const [gridAction, setGridAction] = useState<TermGridAction>(null)
  const router = useRouter()
  const [editingFee, setEditingFee] = useState<FeeStructure | null>(null)
  const [feeSheetOpen, setFeeSheetOpen] = useState(false)

  const canUpdate = permissions.includes("academic_years.update")
  const canManageFees = permissions.includes("fees.manage")

  function reload() {
    apiFetch<{ data: AcademicYear }>(`/academic-years/${yearId}`)
      .then((res) => setYear(res.data))
      .catch((error) =>
        toast.error(error instanceof ApiError ? error.message : "Failed to load academic year."),
      )
  }

  useEffect(() => {
    reload()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [yearId])

  async function deleteTerm(term: Term) {
    try {
      await apiFetch(`/terms/${term.id}`, { method: "DELETE" })
      toast.success(t("terms.deleted"))
      reload()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Something went wrong.")
    }
  }

  async function deleteFee(fee: FeeStructure) {
    try {
      await apiFetch(`/fee-structures/${fee.id}`, { method: "DELETE" })
      toast.success(tf("structures.deleted"))
      reload()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "Something went wrong.")
    }
  }

  // Grouped by education program (Regular / Night / …), then sequence — two
  // "Semester 1" rows from different programs must never sit side by side.
  const terms = (year?.terms ?? [])
    .slice()
    .sort(
      (a, b) =>
        (a.program?.name ?? "").localeCompare(b.program?.name ?? "") ||
        a.sequence - b.sequence,
    )
  const fees = year?.fees ?? []

  // Same table language as the standalone Semesters / Fees pages — the year
  // column is dropped because everything here belongs to this year.
  const termColumns = useTermColumns({
    showYear: false,
    allTerms: terms,
    canUpdateStatus: canUpdate,
    onStatusChanged: () => reload(),
  })
  const termFilters = useTermFilters({ terms, showYearFilter: false })
  const feeColumns = useFeeColumns({ showYear: false, showInvoices: false })
  const feeFilters = useFeeFilters({ fees, showYearFilter: false })

  return (
    <div className="space-y-6">
      {confirmDialog}
      <Button asChild variant="ghost" size="sm" className="ml-4 md:ml-8">
        <Link href="/academic">
          <ArrowLeft className="size-4" />
          {tc("nav.academicYears")}
        </Link>
      </Button>

      {/* Year header: name, lifecycle, calendar range */}
      <div className="flex flex-col gap-3 px-4 sm:flex-row sm:items-start sm:justify-between md:px-8">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
              {year ? year.name : <Skeleton className="h-8 w-40" />}
            </h1>
            {year && <YearStatusSelect year={year} canUpdate={canUpdate} onChanged={setYear} />}
          </div>
          {year && (year.starts_on || year.ends_on) && (
            <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
              <CalendarDays className="size-3.5" />
              {year.starts_on ?? "…"} → {year.ends_on ?? "…"}
            </p>
          )}
        </div>
      </div>

      {/* ── Semesters ─────────────────────────────────────────────────── */}
      <section className="space-y-3">
        <div className="flex items-center justify-between gap-3 px-4 md:px-8">
          <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            <CalendarRange className="size-4" />
            {t("terms.title")}
            {year && (
              <Badge variant="secondary" className="px-1.5 py-0 text-[11px] tabular-nums">
                {terms.length}
              </Badge>
            )}
          </h2>
          {canUpdate && year && terms.length < 5 && (
            <Button
              variant="outline"
              size="sm"
              className="h-9"
              onClick={() => {
                setEditingTerm(null)
                setTermSheetOpen(true)
              }}
            >
              <Plus className="size-4" />
              {t("terms.create")}
            </Button>
          )}
        </div>

        <DataTable
          columns={termColumns}
          data={terms}
          loading={year === null}
          groupBy={(row) => row.program?.name ?? t("terms.noProgram")}
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
          searchKeys={["name"]}
          searchPlaceholder={tc("actions.search")}
          filters={termFilters}
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
                      setEditingTerm(row)
                      setTermSheetOpen(true)
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
                      confirmDelete(() => deleteTerm(row), tc("confirmDelete.named", { name: row.name })),
                  },
                ]
              : []),
          ]}
          emptyMessage={t("terms.empty")}
          exportFilename={`semesters-${yearId}`}
        />
      </section>

      {/* ── Fees ──────────────────────────────────────────────────────── */}
      <section className="space-y-3 pb-4">
        <div className="flex items-center justify-between gap-3 px-4 md:px-8">
          <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            <Wallet className="size-4" />
            {tf("structures.title")}
            {year && (
              <Badge variant="secondary" className="px-1.5 py-0 text-[11px] tabular-nums">
                {fees.length}
              </Badge>
            )}
          </h2>
          {canManageFees && year && (
            <Button
              variant="outline"
              size="sm"
              className="h-9"
              onClick={() => {
                setEditingFee(null)
                setFeeSheetOpen(true)
              }}
            >
              <Plus className="size-4" />
              {tf("structures.create")}
            </Button>
          )}
        </div>

        <DataTable
          columns={feeColumns}
          data={fees}
          loading={year === null}
          searchKeys={["name"]}
          searchPlaceholder={tc("actions.search")}
          filters={feeFilters}
          onRowClick={
            canManageFees
              ? (row) => {
                  setEditingFee(row)
                  setFeeSheetOpen(true)
                }
              : undefined
          }
          actions={
            canManageFees
              ? [
                  {
                    label: tc("actions.edit"),
                    icon: Pencil,
                    onClick: (row: FeeStructure) => {
                      setEditingFee(row)
                      setFeeSheetOpen(true)
                    },
                  },
                  {
                    label: tc("actions.delete"),
                    icon: Trash2,
                    destructive: true,
                    onClick: (row: FeeStructure) =>
                      confirmDelete(() => deleteFee(row), tc("confirmDelete.named", { name: row.name })),
                  },
                ]
              : undefined
          }
          emptyMessage={tf("structures.emptyForYear")}
          exportFilename={`fees-${yearId}`}
        />
      </section>

      <TermCloneCopyDialog
        action={gridAction}
        terms={terms}
        onOpenChange={(open) => !open && setGridAction(null)}
        onDone={() => reload()}
      />

      <TermSheet
        term={editingTerm}
        academicYear={year}
        open={termSheetOpen}
        onOpenChange={(v) => {
          setTermSheetOpen(v)
          if (!v) setEditingTerm(null)
        }}
        onSaved={() => reload()}
      />

      {year && (
        <FeeStructureSheet
          feeStructure={editingFee}
          academicYears={[year]}
          fixedYearId={year.id}
          gradeLevels={gradeLevels}
          open={feeSheetOpen}
          onOpenChange={(v) => {
            setFeeSheetOpen(v)
            if (!v) setEditingFee(null)
          }}
          onSaved={() => reload()}
        />
      )}
    </div>
  )
}
