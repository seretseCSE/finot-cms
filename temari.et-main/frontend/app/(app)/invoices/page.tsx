"use client"

import { AlertTriangle, BadgePercent, Eye, HandCoins, Receipt, SlidersHorizontal, Trash2, Wallet } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { InvoiceDetailSheet } from "@/components/fees/invoice-detail-sheet"
import { InvoiceSheet } from "@/components/fees/invoice-sheet"
import { RecordPaymentSheet } from "@/components/fees/record-payment-sheet"
import { ScholarshipInvoiceDialog } from "@/components/fees/scholarship-invoice-dialog"
import { Badge } from "@/components/ui/badge"
import { CopyableId } from "@/components/ui/copyable-id"
import {
  DataTable,
  type DataTableColumn,
  type DataTableFilter,
} from "@/components/ui/data-table"
import { groupTermsByYear, termFullLabel } from "@/components/academic/term-select"
import { DateRangeFilter } from "@/components/ui/date-range-filter"
import { PageHeader } from "@/components/ui/page-header"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { StatCard } from "@/components/ui/stat-card"
import { ApiError, apiFetch } from "@/lib/api"
import { useRefList } from "@/lib/data/use-ref-list"
import { runBulk, useBulkConfirm } from "@/components/ui/bulk-actions"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  BankAccount,
  FeeBankAccount,
  FeeStructure,
  Invoice,
  InvoiceStats,
  InvoiceStatus,
  Paginated,
  PaymentMethod,
  Student,
  Term,
} from "@/lib/types"
import { useServerTable } from "@/lib/use-server-table"
import { useScopeFilters } from "@/lib/use-scope-filters"

const STATUS_VARIANT: Record<
  InvoiceStatus,
  "default" | "secondary" | "outline"
> = {
  paid: "default",
  partial: "secondary",
  unpaid: "outline",
  scholarship: "secondary",
  void: "secondary",
}

const METHODS: PaymentMethod[] = [
  "wallet",
  "bank_transfer",
  "cash",
  "other",
]

function etb(value: string | number): string {
  return `${Number(value).toLocaleString()} ETB`
}

/**
 * A collection-account logo that reveals the account details in a popover on
 * tap — the table cell itself stays logo-only. Click never reaches the row.
 */
function PaidAccountLogo({ account }: { account: FeeBankAccount }) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label={account.bank_name ?? account.account_name}
          className="rounded-full transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
          onClick={(e) => e.stopPropagation()}
        >
          <BankLogo
            bank={{
              id: account.id,
              code: account.bank_code ?? "",
              name: account.bank_name ?? "",
              type: account.bank_type ?? "bank",
              logo: account.bank_logo,
            }}
            size={24}
          />
        </button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        className="w-auto min-w-52 p-3"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center gap-2.5">
          <BankLogo
            bank={{
              id: account.id,
              code: account.bank_code ?? "",
              name: account.bank_name ?? "",
              type: account.bank_type ?? "bank",
              logo: account.bank_logo,
            }}
            size={32}
          />
          <div className="min-w-0 space-y-0.5">
            {account.bank_name && (
              <p className="text-sm font-medium leading-tight">{account.bank_name}</p>
            )}
            <p className="truncate text-xs text-muted-foreground">
              {account.account_name}
            </p>
            <CopyableId value={account.account_number} />
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}

export default function InvoicesPage() {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { confirmBulk, bulkDialog } = useBulkConfirm()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()

  const [students, setStudents] = useState<Student[]>([])
  const [stats, setStats] = useState<InvoiceStats | null>(null)
  const [createOpen, setCreateOpen] = useState(false)
  const [paying, setPaying] = useState<Invoice | null>(null)
  const [payOpen, setPayOpen] = useState(false)
  const [viewing, setViewing] = useState<Invoice | null>(null)
  const [viewOpen, setViewOpen] = useState(false)
  const [granting, setGranting] = useState<Invoice | null>(null)
  const [scholarshipOpen, setScholarshipOpen] = useState(false)

  const canManage = permissions.includes("fees.manage")
  const canRecord = permissions.includes("payments.record")
  const hasBranch = active.branchId != null
  const isGlobal = !hasBranch && (isPlatform || active.schoolId != null)
  // School managers create from the school-wide workspace too — the sheet
  // asks for the target branch (BranchField).
  const canTargetBranch = hasBranch || (!isPlatform && active.schoolId != null)

  const table = useServerTable<Invoice>({
    endpoint: "/invoices",
    exportEndpoint: "/invoices/export",
    defaultSort: { key: "created_at", dir: "desc" },
    enabled: hasBranch || isGlobal,
    refreshKey: `${active.schoolId ?? ""}-${active.branchId ?? ""}`,
    loadFailedMessage: t("invoices.loadFailed"),
  })

  // Tiles mirror the table: same scope, same filters — refetched with them
  // (and after any mutation, via the version counter).
  const [statsVersion, setStatsVersion] = useState(0)
  const statsKey = JSON.stringify({
    f: table.filters,
    d: table.dates,
    s: table.searchInput,
    v: statsVersion,
  })
  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    let cancelled = false
    const params = new URLSearchParams()
    if (table.searchInput) params.set("search", table.searchInput)
    for (const [k, v] of Object.entries(table.filters)) if (v) params.set(k, v)
    for (const [k, v] of Object.entries(table.dates)) if (v) params.set(k, v)
    const id = setTimeout(() => {
      apiFetch<{ data: InvoiceStats }>(`/invoices/stats?${params.toString()}`)
        .then((res) => !cancelled && setStats(res.data))
        .catch(() => {})
    }, 300)
    return () => {
      cancelled = true
      clearTimeout(id)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- statsKey captures filters/search/dates
  }, [hasBranch, isGlobal, active.branchId, active.schoolId, statsKey])

  // Reference lists for the filters + create sheet — shared, auto-refreshing
  // (a year/semester/fee/account created elsewhere appears without a reload).
  const refsEnabled = hasBranch || isGlobal
  const { items: academicYears } = useRefList<AcademicYear>("/academic-years", {
    enabled: refsEnabled,
  })
  const { items: terms } = useRefList<Term>("/terms?per_page=100", { enabled: refsEnabled })
  const { items: feeStructures } = useRefList<FeeStructure>("/fee-structures?per_page=100", {
    enabled: refsEnabled,
  })
  const { items: accounts } = useRefList<BankAccount>("/bank-accounts", { enabled: refsEnabled })

  useEffect(() => {
    if (!hasBranch && !isGlobal) return
    let cancelled = false
    apiFetch<Paginated<Student>>("/students")
      .then((res) => !cancelled && setStudents(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [hasBranch, isGlobal, active.branchId, active.schoolId])

  function upsert() {
    table.refetch()
    setStatsVersion((v) => v + 1)
  }

  async function handleDelete(invoice: Invoice) {
    try {
      await apiFetch(`/invoices/${invoice.id}`, { method: "DELETE" })
      toast.success(t("invoices.deleted"))
      upsert()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : "Something went wrong."
      )
    }
  }

  const columns: DataTableColumn<Invoice>[] = useMemo(
    () => [
      ...(isGlobal
        ? [
            {
              key: "branch_name",
              label: tc("columns.branch"),
              render: (row: Invoice) => (
                <span className="text-xs text-muted-foreground">
                  {row.school_name} · {row.branch_name}
                </span>
              ),
            } as DataTableColumn<Invoice>,
          ]
        : []),
      {
        key: "number",
        label: t("invoices.columns.number"),
        render: (row) => <CopyableId value={row.number} fallback="—" />,
        exportValue: (row) => row.number,
      },
      {
        key: "student_name",
        label: t("invoices.columns.student"),
        primary: true,
        render: (row) => (
          <span className="flex flex-col">
            <span className="font-medium">{row.student_name ?? "—"}</span>
            {row.student_public_id && (
              <CopyableId
                value={row.student_public_id}
                className="text-xs text-muted-foreground"
              />
            )}
          </span>
        ),
        exportValue: (row) =>
          [row.student_name, row.student_public_id].filter(Boolean).join(" · "),
      },
      {
        key: "title",
        label: t("invoices.columns.title"),
        render: (row) => (
          <span className="flex flex-col">
            <span>{row.title}</span>
            <span className="text-xs text-muted-foreground">
              {[row.academic_year_name, row.term_name].filter(Boolean).join(" · ")}
            </span>
          </span>
        ),
        exportValue: (row) =>
          [row.title, row.academic_year_name, row.term_name].filter(Boolean).join(" · "),
      },
      {
        key: "amount",
        label: t("invoices.columns.amount"),
        sortable: true,
        // Show the discounted (payable) amount when a concession applies.
        render: (row) =>
          row.net_amount != null && row.net_amount !== row.amount ? (
            <span>
              <span className="text-muted-foreground line-through">{row.amount}</span>{" "}
              {row.net_amount} ETB
            </span>
          ) : (
            `${row.amount} ETB`
          ),
        exportValue: (row) => row.net_amount ?? row.amount,
      },
      {
        key: "amount_paid",
        label: t("invoices.columns.paid"),
        mobileHidden: true,
        render: (row) => `${row.amount_paid} ETB`,
        exportValue: (row) => row.amount_paid,
      },
      {
        key: "balance",
        label: t("invoices.columns.balance"),
        mobileHidden: true,
        render: (row) => `${row.balance} ETB`,
        exportValue: (row) => row.balance,
      },
      {
        key: "paid_to",
        label: t("invoices.columns.paidTo"),
        mobileHidden: true,
        render: (row) =>
          (row.paid_accounts?.length ?? 0) > 0 ? (
            <span className="flex items-center gap-1.5">
              {row.paid_accounts!.map((a) => (
                <PaidAccountLogo key={a.id} account={a} />
              ))}
            </span>
          ) : (
            <span className="text-muted-foreground">—</span>
          ),
        exportValue: (row) =>
          (row.paid_accounts ?? [])
            .map((a) => `${a.bank_name ?? ""} ${a.account_number}`)
            .join(" · "),
      },
      {
        key: "status",
        label: t("invoices.columns.status"),
        sortable: true,
        render: (row) => (
          <span className="flex flex-wrap items-center gap-1.5">
            <Badge variant={STATUS_VARIANT[row.status]}>
              {t(`statuses.${row.status}`)}
            </Badge>
            {row.is_overdue && (
              <Badge
                variant="outline"
                className="border-destructive/30 bg-destructive/10 text-destructive"
              >
                {t("invoices.overdue")}
              </Badge>
            )}
            {/* A parent submitted a payment proof — finance should look. */}
            {(row.pending_verifications_count ?? 0) > 0 && (
              <Badge
                variant="outline"
                className="border-warning/30 bg-warning/10 text-warning"
              >
                {t("invoices.verifications.pendingBadge")}
              </Badge>
            )}
          </span>
        ),
        exportValue: (row) =>
          row.is_overdue ? `${row.status_label} (${t("invoices.overdue")})` : row.status_label,
      },
    ],
    [t, tc, isGlobal]
  )

  const scopeFilters = useScopeFilters(table.filters)

  const filterDefs: DataTableFilter[] = [
    {
      key: "status",
      label: t("invoices.columns.status"),
      options: (
        ["unpaid", "partial", "paid", "scholarship", "void"] as InvoiceStatus[]
      ).map((s) => ({ label: t(`statuses.${s}`), value: s })),
    },
    {
      key: "overdue",
      label: t("invoices.overdue"),
      options: [{ label: t("invoices.overdueOnly"), value: "1" }],
    },
    {
      key: "pending_verification",
      label: t("invoices.verifications.pendingBadge"),
      options: [{ label: t("invoices.verifications.pendingOnly"), value: "1" }],
    },
    ...(academicYears.length > 0
      ? [
          {
            key: "academic_year_id",
            label: t("invoices.academicYear"),
            options: academicYears.map((y) => ({
              label: y.name,
              value: String(y.id),
            })),
          },
        ]
      : []),
    ...(terms.length > 0
      ? [
          {
            key: "term_id",
            label: t("invoices.semester"),
            options: groupTermsByYear(terms).flatMap((group) =>
              group.terms.map((term) => ({
                label: termFullLabel(term),
                value: String(term.id),
              })),
            ),
          },
        ]
      : []),
    ...(feeStructures.length > 0
      ? [
          {
            key: "fee_structure_id",
            label: t("invoices.fee"),
            options: feeStructures.map((fee) => ({
              label: fee.academic_year_name
                ? `${fee.name} — ${fee.academic_year_name}`
                : fee.name,
              value: String(fee.id),
            })),
          },
        ]
      : []),
    {
      key: "method",
      label: t("payments.method"),
      options: METHODS.map((m) => ({ label: t(`methods.${m}`), value: m })),
    },
    // Reconciliation lens — invoices with money received into this account.
    ...(accounts.length > 0
      ? [
          {
            key: "bank_account_id",
            label: t("invoices.columns.paidTo"),
            options: accounts.map((a) => ({
              label: [a.bank?.name, a.account_name].filter(Boolean).join(" · "),
              value: String(a.id),
            })),
          },
        ]
      : []),
  ]

  /** Paid/void/scholarship rows open the details; open bills jump straight
   *  to recording the payment (details stay reachable from the row menu) —
   *  UNLESS a family payment submission awaits review: then the details
   *  sheet opens on the claim, since reviewing it IS the next action. */
  function handleRowClick(row: Invoice) {
    const openBill = row.status === "unpaid" || row.status === "partial"
    const needsReview = (row.pending_verifications_count ?? 0) > 0
    if (openBill && canRecord && !needsReview) {
      setPaying(row)
      setPayOpen(true)
    } else {
      setViewing(row)
      setViewOpen(true)
    }
  }

  const rowActions = [
    {
      label: t("invoices.viewDetails"),
      icon: Eye,
      onClick: (row: Invoice) => {
        setViewing(row)
        setViewOpen(true)
      },
    },
    ...(canRecord
      ? [
          {
            label: t("invoices.recordPayment"),
            icon: HandCoins,
            onClick: (row: Invoice) => {
              setPaying(row)
              setPayOpen(true)
            },
            hidden: (row: Invoice) =>
              row.status === "paid" || row.status === "void" || row.status === "scholarship",
          },
        ]
      : []),
    ...(canManage
      ? [
          {
            // Granting a NEW discount on a fully paid bill is impossible
            // (payments would exceed the net) — offer it only pre-settlement.
            label: t("scholarship.action"),
            icon: BadgePercent,
            onClick: (row: Invoice) => {
              setGranting(row)
              setScholarshipOpen(true)
            },
            hidden: (row: Invoice) => row.status === "void" || row.status === "paid",
          },
          {
            // On a PAID bill the only legitimate move is correcting an
            // existing discount (clearing it re-opens the balance).
            label: t("scholarship.adjustAction"),
            icon: SlidersHorizontal,
            onClick: (row: Invoice) => {
              setGranting(row)
              setScholarshipOpen(true)
            },
            hidden: (row: Invoice) =>
              row.status !== "paid" ||
              !row.discount_type ||
              row.discount_type === "none",
          },
        ]
      : []),
    ...(canManage
      ? [
          {
            label: tc("actions.delete"),
            icon: Trash2,
            destructive: true,
            onClick: (row: Invoice) =>
              confirmDelete(() => handleDelete(row), tc("confirmDelete.named", { name: row.title })),
          },
        ]
      : []),
  ]

  return (
    <div className="space-y-6">
      {confirmDialog}
      {bulkDialog}
      <PageHeader
        title={t("invoices.title")}
        description={t("invoices.subtitle")}
        actions={
          canManage && canTargetBranch ? (
            <InvoiceSheet
              students={students}
              academicYears={academicYears}
              feeStructures={feeStructures}
              open={createOpen}
              onOpenChange={setCreateOpen}
              onSaved={upsert}
              showTrigger
            />
          ) : undefined
        }
      />

      {!hasBranch && !isGlobal ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 px-4 md:px-8 xl:grid-cols-4">
            <StatCard
              label={t("invoices.stats.invoiced")}
              value={stats ? etb(stats.invoiced) : null}
              icon={Receipt}
            />
            <StatCard
              label={t("invoices.stats.collected")}
              value={stats ? etb(stats.collected) : null}
              icon={HandCoins}
            />
            <StatCard
              label={t("invoices.stats.outstanding")}
              value={stats ? etb(stats.outstanding) : null}
              icon={Wallet}
            />
            <StatCard
              label={t("invoices.stats.overdue")}
              value={stats ? etb(stats.overdue_amount) : null}
              hint={stats ? t("invoices.stats.overdueCount", { count: stats.overdue_count }) : undefined}
              icon={AlertTriangle}
              onClick={() =>
                table.setFilter("overdue", table.filters.overdue === "1" ? "" : "1")
              }
            />
          </div>

          <DataTable
            columns={columns}
            data={table.rows}
            loading={table.loading}
            serverMode
            searchable
            searchValue={table.searchInput}
            onSearchChange={table.setSearchInput}
            searchPlaceholder={t("invoices.searchPlaceholder")}
            filters={[...scopeFilters, ...filterDefs]}
            filterValues={table.filters}
            onFilterChange={table.setFilter}
            toolbarSlot={
              <DateRangeFilter
                fields={[
                  { key: "due_from", label: t("invoices.filters.dueFrom") },
                  { key: "due_to", label: t("invoices.filters.dueTo") },
                  { key: "issued_from", label: t("invoices.filters.issuedFrom") },
                  { key: "issued_to", label: t("invoices.filters.issuedTo") },
                ]}
                values={table.dates}
                onChange={table.setDate}
                onClear={table.clearDates}
              />
            }
            onSortChange={table.onSortChange}
            onExport={table.handleExport}
            onRowClick={handleRowClick}
            actions={rowActions.length > 0 ? rowActions : undefined}
            bulkActions={
              canManage
                ? [
                    {
                      // Undoing a mis-generated billing run. Invoices that have
                      // taken money are history — the server skips those.
                      label: tc("actions.delete"),
                      icon: Trash2,
                      destructive: true,
                      onClick: (rows: Invoice[]) =>
                        confirmBulk({
                          title: t("invoices.bulk.deleteTitle", { count: rows.length }),
                          description: t("invoices.bulk.deleteDesc"),
                          confirmLabel: tc("actions.delete"),
                          destructive: true,
                          action: async () => {
                            await runBulk({
                              url: "/invoices/bulk/delete",
                              ids: rows.map((r) => r.id),
                              countKey: "deleted",
                              success: (count) => t("invoices.bulk.deleted", { count }),
                              tc,
                            })
                            table.refetch()
                          },
                        }),
                    },
                  ]
                : undefined
            }
            emptyMessage={t("invoices.empty")}
            exportFilename="invoices"
            pagination={table.pagination}
          />
        </>
      )}

      <RecordPaymentSheet
        invoice={paying}
        open={payOpen}
        onOpenChange={(v) => {
          setPayOpen(v)
          if (!v) setPaying(null)
        }}
        onRecorded={upsert}
      />

      <InvoiceDetailSheet
        invoice={viewing}
        open={viewOpen}
        onOpenChange={(v) => {
          setViewOpen(v)
          if (!v) setViewing(null)
        }}
        canRecordPayment={canRecord}
        canManageFees={canManage}
        onRecordPayment={(row) => {
          setPaying(row)
          setPayOpen(true)
        }}
        onChanged={upsert}
      />

      <ScholarshipInvoiceDialog
        invoice={granting}
        open={scholarshipOpen}
        onOpenChange={(v) => {
          setScholarshipOpen(v)
          if (!v) setGranting(null)
        }}
        onApplied={upsert}
      />
    </div>
  )
}
