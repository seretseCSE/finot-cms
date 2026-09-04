"use client"

import { ArrowDownCircle, ArrowUpCircle, Briefcase, CheckCircle2, CircleDollarSign, Clock3, HandCoins, Loader2, Pencil, PiggyBank, Plus, Receipt, Scale, Trash2, Wallet, XCircle } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { FinanceEntrySheet } from "@/components/finance/entry-sheet"
import { Badge } from "@/components/ui/badge"
import { runBulk } from "@/components/ui/bulk-actions"
import { BulkDecisionDialog } from "@/components/ui/bulk-decision-dialog"
import { DocumentDownloadButton } from "@/components/ui/document-download-button"
import {
  BranchScopePicker,
  useBranchScope,
} from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DataTable, type DataTableColumn } from "@/components/ui/data-table"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { PageHeader } from "@/components/ui/page-header"
import { ProfileTabBar, useProfileTabs } from "@/components/ui/profile-tabs"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { StatCard } from "@/components/ui/stat-card"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { toEthiopian, ethiopianMonthRange } from "@/lib/ethiopian-date"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  BudgetRow,
  CashbookEntry,
  Expense,
  FinanceCategory,
  FinanceStatement,
  OtherIncome,
  Paginated,
} from "@/lib/types"
import { useScopeFilters } from "@/lib/use-scope-filters"
import { useServerTable } from "@/lib/use-server-table"
import { cn } from "@/lib/utils"
import { addisToday } from "@/lib/dates"

function etb(value: string | number): string {
  return `${Number(value).toLocaleString()} ETB`
}

/** "Today" on the Addis wall clock. */
function today(): string {
  return addisToday()
}

function daysAgo(days: number): string {
  const [y, m, d] = today().split("-").map(Number)
  const dt = new Date(y, m - 1, d)
  dt.setDate(dt.getDate() - days)
  return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, "0")}-${String(dt.getDate()).padStart(2, "0")}`
}

const STATUS_TONE: Record<Expense["status"], string> = {
  pending: "border-warning/30 bg-warning/10 text-warning",
  approved: "border-success/30 bg-success/10 text-success",
  rejected: "border-destructive/30 bg-destructive/10 text-destructive",
}

/** Every cashbook source gets its own icon tile; direction sets the tone. */
const SOURCE_ICON: Record<CashbookEntry["source"], typeof Wallet> = {
  fee_payment: Receipt,
  other_income: PiggyBank,
  expense: HandCoins,
  payroll: Briefcase,
}

const TAB_KEYS = [
  "expenses",
  "income",
  "cashbook",
  "budget",
  "statement",
  "categories",
] as const
const PRESETS = ["thisEthMonth", "last30", "last90"] as const
type Preset = (typeof PRESETS)[number]

function presetRange(preset: Preset): { from: string; to: string } {
  if (preset === "thisEthMonth") {
    const ec = toEthiopian(today())
    const range = ethiopianMonthRange(ec.year, ec.month)
    return { from: range.from, to: today() < range.to ? today() : range.to }
  }
  return { from: daysAgo(preset === "last30" ? 29 : 89), to: today() }
}

/** Preset pills + compact from/to pickers — shared by cashbook & statement. */
function RangeControl({
  range,
  onChange,
}: {
  range: { from: string; to: string }
  onChange: (range: { from: string; to: string }) => void
}) {
  const { t } = useTranslation("fees")
  const activePreset = PRESETS.find(
    (p) => presetRange(p).from === range.from && presetRange(p).to === range.to
  )
  const ecMonthLabel = t(`months.${toEthiopian(today()).month}`)

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="no-scrollbar inline-flex max-w-full items-center gap-0.5 overflow-x-auto rounded-full border bg-card p-1 shadow-xs">
        {PRESETS.map((preset) => (
          <button
            key={preset}
            type="button"
            onClick={() => onChange(presetRange(preset))}
            aria-pressed={activePreset === preset}
            className={cn(
              "pressable rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors",
              activePreset === preset
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:text-foreground"
            )}
          >
            {preset === "thisEthMonth"
              ? t("reports.presets.thisEthMonth", { month: ecMonthLabel })
              : t(`reports.presets.${preset}`)}
          </button>
        ))}
      </div>
      <div className="flex items-center gap-2 sm:ml-auto">
        <DatePicker
          value={range.from}
          onChange={(v) => v && onChange({ ...range, from: v })}
          max={range.to}
          clearable={false}
          aria-label={t("reports.from")}
          className="w-36"
        />
        <span className="text-xs text-muted-foreground">–</span>
        <DatePicker
          value={range.to}
          onChange={(v) => v && onChange({ ...range, to: v })}
          min={range.from}
          max={today()}
          clearable={false}
          aria-label={t("reports.to")}
          className="w-36"
        />
      </div>
    </div>
  )
}

interface ExpenseStats {
  approved_total: string
  approved_count: number
  pending_total: string
  pending_count: number
  rejected_total: string
  rejected_count: number
}

export default function FinanceBooksPage() {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { user } = useAuth()
  const { active } = useSchoolContext()
  const workspace = `${active.schoolId ?? ""}-${active.branchId ?? ""}`
  const permissions = useEffectivePermissions()
  const canManage = permissions.includes("finance.books.manage")
  const canApprove = permissions.includes("finance.books.approve")

  const [tab, setTab] = useProfileTabs(TAB_KEYS, "expenses")
  const tabs = useMemo(
    () => TAB_KEYS.map((key) => ({ key, label: t(`books.tabs.${key}`) })),
    [t]
  )

  const { confirmDelete, confirmDialog } = useConfirmDelete()

  // Categories back both entry sheets and the categories tab.
  const [categories, setCategories] = useState<FinanceCategory[] | null>(null)
  const loadCategories = useCallback(() => {
    apiFetch<{ data: FinanceCategory[] }>(
      "/finance/categories?include_inactive=1"
    )
      .then((res) => setCategories(res.data))
      .catch(() => setCategories([]))
  }, [])
  useEffect(() => {
    loadCategories()
  }, [loadCategories, workspace])
  const activeCategories = useCallback(
    (kind: "expense" | "income") =>
      (categories ?? []).filter((c) => c.kind === kind && c.is_active),
    [categories]
  )

  const [refresh, setRefresh] = useState(0)
  const bump = () => setRefresh((n) => n + 1)

  // ── Expenses ──────────────────────────────────────────────────────
  const expenses = useServerTable<Expense>({
    endpoint: "/finance/expenses",
    defaultSort: { key: "expense_date", dir: "desc" },
    enabled: tab === "expenses",
    refreshKey: `${workspace}|${refresh}`,
  })
  const [expenseStats, setExpenseStats] = useState<ExpenseStats | null>(null)
  // The stat strip follows the table's scope narrowing (school/branch) so
  // the numbers always match the rows below.
  const expenseScopeParams = new URLSearchParams()
  if (expenses.filters.school_id) expenseScopeParams.set("school_id", expenses.filters.school_id)
  if (expenses.filters.branch_id) expenseScopeParams.set("branch_id", expenses.filters.branch_id)
  const expenseScopeQuery = expenseScopeParams.toString()
  useEffect(() => {
    if (tab !== "expenses") return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale scope data
    setExpenseStats(null)
    apiFetch<{ data: ExpenseStats }>(
      `/finance/expenses/stats${expenseScopeQuery ? `?${expenseScopeQuery}` : ""}`
    )
      .then((res) => !cancelled && setExpenseStats(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [tab, refresh, workspace, expenseScopeQuery])

  const [entrySheet, setEntrySheet] = useState<{
    kind: "expense" | "income"
    entry: Expense | OtherIncome | null
  } | null>(null)

  const [rejecting, setRejecting] = useState<Expense | null>(null)
  const [bulkDecision, setBulkDecision] = useState<{
    rows: Expense[]
    decision: "approved" | "rejected"
  } | null>(null)
  const [rejectNote, setRejectNote] = useState("")
  const [working, setWorking] = useState(false)

  async function decide(expense: Expense, approve: boolean, note?: string) {
    setWorking(true)
    try {
      await apiFetch(
        `/finance/expenses/${expense.id}/${approve ? "approve" : "reject"}`,
        {
          method: "POST",
          body: note ? { review_note: note } : {},
        }
      )
      toast.success(
        approve ? t("books.expenseApproved") : t("books.expenseRejected")
      )
      setRejecting(null)
      bump()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setWorking(false)
    }
  }

  /**
   * A countersigner clearing the pending queue. Rows they recorded themselves
   * are dropped up front — the four-eyes rule is re-checked per row server-side
   * anyway, but there is no point offering an action that will be skipped.
   */
  function openBulkExpense(rows: Expense[], decision: "approved" | "rejected") {
    const actionable = rows.filter(
      (r) => r.status === "pending" && r.recorded_by !== user?.id
    )
    if (actionable.length === 0) {
      toast.info(t("books.bulk.noneActionable"))
      return
    }
    setBulkDecision({ rows: actionable, decision })
  }

  const expenseColumns: DataTableColumn<Expense>[] = [
    {
      key: "title",
      label: t("books.columns.entry"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.title}</p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.category_name, row.payee, row.branch_name]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
      ),
      exportValue: (row) => row.title,
    },
    {
      key: "amount",
      label: t("structures.amount"),
      sortable: true,
      className: "text-right",
      render: (row) => (
        <span className="font-medium tabular-nums">{etb(row.amount)}</span>
      ),
      exportValue: (row) => row.amount,
    },
    {
      key: "expense_date",
      label: t("books.columns.date"),
      sortable: true,
      render: (row) => row.expense_date ?? "—",
      exportValue: (row) => row.expense_date ?? "",
    },
    {
      key: "method",
      label: t("payments.method"),
      mobileHidden: true,
      render: (row) => t(`methods.${row.method}`),
      exportValue: (row) => row.method,
    },
    {
      key: "status",
      label: t("books.columns.status"),
      sortable: true,
      render: (row) => (
        <Badge
          variant="outline"
          className={cn("rounded-full", STATUS_TONE[row.status])}
        >
          {t(`books.statuses.${row.status}`)}
        </Badge>
      ),
      exportValue: (row) => row.status,
    },
    {
      key: "recorded_by_name",
      label: t("payments.recordedBy"),
      mobileHidden: true,
      render: (row) => row.recorded_by_name ?? "—",
      exportValue: (row) => row.recorded_by_name ?? "",
    },
  ]

  // ── Other income ──────────────────────────────────────────────────
  const incomes = useServerTable<OtherIncome>({
    endpoint: "/finance/other-incomes",
    defaultSort: { key: "received_on", dir: "desc" },
    enabled: tab === "income",
    refreshKey: `${workspace}|${refresh}`,
  })

  const incomeColumns: DataTableColumn<OtherIncome>[] = [
    {
      key: "title",
      label: t("books.columns.entry"),
      primary: true,
      render: (row) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{row.title}</p>
          <p className="truncate text-xs text-muted-foreground">
            {[row.category_name, row.source, row.branch_name]
              .filter(Boolean)
              .join(" · ")}
          </p>
        </div>
      ),
      exportValue: (row) => row.title,
    },
    {
      key: "amount",
      label: t("structures.amount"),
      sortable: true,
      className: "text-right",
      render: (row) => (
        <span className="font-medium text-success tabular-nums">
          +{etb(row.amount)}
        </span>
      ),
      exportValue: (row) => row.amount,
    },
    {
      key: "received_on",
      label: t("books.columns.date"),
      sortable: true,
      render: (row) => row.received_on ?? "—",
      exportValue: (row) => row.received_on ?? "",
    },
    {
      key: "method",
      label: t("payments.method"),
      mobileHidden: true,
      render: (row) => t(`methods.${row.method}`),
      exportValue: (row) => row.method,
    },
    {
      key: "recorded_by_name",
      label: t("payments.recordedBy"),
      mobileHidden: true,
      render: (row) => row.recorded_by_name ?? "—",
      exportValue: (row) => row.recorded_by_name ?? "",
    },
  ]

  // ── Cashbook + statement share a reporting window ─────────────────
  const [range, setRange] = useState(() => presetRange("thisEthMonth"))

  // Money in / money out / net for the CURRENT filters — piggybacked on the
  // list response meta so the strip always matches the table.
  const [cashTotals, setCashTotals] = useState<{
    money_in: string
    money_out: string
    net: string
  } | null>(null)
  const onCashbookMeta = useCallback((meta: Record<string, unknown>) => {
    setCashTotals({
      money_in: String(meta.money_in ?? "0"),
      money_out: String(meta.money_out ?? "0"),
      net: String(meta.net ?? "0"),
    })
  }, [])

  const cashbook = useServerTable<CashbookEntry>({
    endpoint: "/finance/cashbook",
    defaultSort: { key: "entry_date", dir: "desc" },
    perPage: 50,
    enabled: tab === "cashbook",
    refreshKey: `${workspace}|${refresh}`,
    extraParams: { from: range.from, to: range.to },
    onMeta: onCashbookMeta,
  })

  // School → Branch narrowing for the school-wide / platform workspaces —
  // one hook instance following the active tab's filter state.
  const scopeFilters = useScopeFilters(
    tab === "income" ? incomes.filters : tab === "cashbook" ? cashbook.filters : expenses.filters
  )

  const cashbookColumns: DataTableColumn<CashbookEntry>[] = [
    {
      key: "description",
      label: t("books.columns.entry"),
      primary: true,
      render: (row) => {
        const Icon = SOURCE_ICON[row.source]
        const isIn = row.direction === "in"
        return (
          <div className="flex min-w-0 items-center gap-2.5">
            <span
              className={cn(
                "flex size-8 shrink-0 items-center justify-center rounded-lg",
                isIn ? "bg-success/10" : "bg-destructive/10"
              )}
            >
              <Icon
                className={cn(
                  "size-4",
                  isIn ? "text-success" : "text-destructive"
                )}
                strokeWidth={1.75}
              />
            </span>
            <div className="min-w-0">
              <p className="truncate font-medium">{row.description}</p>
              <p className="truncate text-xs text-muted-foreground">
                {[t(`books.sources.${row.source}`), row.branch_name]
                  .filter(Boolean)
                  .join(" · ")}
              </p>
            </div>
          </div>
        )
      },
      exportValue: (row) => row.description,
    },
    {
      key: "category",
      label: t("books.category"),
      mobileHidden: true,
      render: (row) => row.category ?? "—",
      exportValue: (row) => row.category ?? "",
    },
    {
      key: "method",
      label: t("payments.method"),
      mobileHidden: true,
      render: (row) => (row.method ? t(`methods.${row.method}`) : "—"),
      exportValue: (row) => row.method ?? "",
    },
    {
      key: "money_in",
      label: t("books.stats.moneyIn"),
      className: "text-right",
      mobileHidden: true,
      render: (row) =>
        row.direction === "in" ? (
          <span className="font-medium text-success tabular-nums">
            +{etb(row.amount)}
          </span>
        ) : (
          <span className="text-muted-foreground/40">—</span>
        ),
      exportValue: (row) => (row.direction === "in" ? row.amount : ""),
    },
    {
      key: "money_out",
      label: t("books.stats.moneyOut"),
      className: "text-right",
      render: (row) =>
        row.direction === "out" ? (
          <span className="font-medium text-destructive tabular-nums">
            −{etb(row.amount)}
          </span>
        ) : (
          <span className="hidden text-muted-foreground/40 md:inline">—</span>
        ),
      exportValue: (row) => (row.direction === "out" ? row.amount : ""),
    },
  ]

  /** Day header: Ethiopian date first, Gregorian beside, day totals right. */
  const renderCashbookDay = (key: string, rows: CashbookEntry[]) => {
    const inSum = rows
      .filter((r) => r.direction === "in")
      .reduce((s, r) => s + Number(r.amount), 0)
    const outSum = rows
      .filter((r) => r.direction === "out")
      .reduce((s, r) => s + Number(r.amount), 0)
    const ec = toEthiopian(key)
    return (
      <div className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
        <span className="font-semibold">
          {t(`months.${ec.month}`)} {ec.day}, {ec.year}
        </span>
        <span className="text-muted-foreground">{key}</span>
        <span className="ml-auto flex items-baseline gap-2.5 tabular-nums">
          {inSum > 0 && (
            <span className="font-medium text-success">+{etb(inSum)}</span>
          )}
          {outSum > 0 && (
            <span className="font-medium text-destructive">
              −{etb(outSum)}
            </span>
          )}
        </span>
      </div>
    )
  }

  // ── Statement ─────────────────────────────────────────────────────
  const [statement, setStatement] = useState<FinanceStatement | null>(null)
  useEffect(() => {
    if (tab !== "statement") return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale range data
    setStatement(null)
    apiFetch<{ data: FinanceStatement }>(
      `/finance/statement?from=${range.from}&to=${range.to}`
    )
      .then((res) => !cancelled && setStatement(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [tab, range, refresh, workspace])

  // ── Budget ────────────────────────────────────────────────────────
  const { needsBranch } = useBranchScope()
  const [budgetBranchId, setBudgetBranchId] = useState<number | null>(null)
  const [years, setYears] = useState<AcademicYear[]>([])
  const [yearId, setYearId] = useState<string>("")
  const [budgetRows, setBudgetRows] = useState<BudgetRow[] | null>(null)
  const [budgetEdits, setBudgetEdits] = useState<Record<number, string>>({})
  const [budgetMeta, setBudgetMeta] = useState<{
    window_from: string
    window_to: string | null
    pending_total: string
  } | null>(null)
  const [savingBudget, setSavingBudget] = useState(false)

  useEffect(() => {
    if (tab !== "budget") return
    if (needsBranch && budgetBranchId == null) return
    let cancelled = false
    const branchParam =
      budgetBranchId != null ? `?branch_id=${budgetBranchId}&` : "?"
    apiFetch<Paginated<AcademicYear>>(
      `/academic-years${branchParam}per_page=100`
    )
      .then((res) => {
        if (cancelled) return
        setYears(res.data)
        setYearId((current) => {
          if (current && res.data.some((y) => String(y.id) === current))
            return current
          const preferred = res.data.find((y) => y.is_current) ?? res.data[0]
          return preferred ? String(preferred.id) : ""
        })
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [tab, needsBranch, budgetBranchId, workspace])

  useEffect(() => {
    if (tab !== "budget" || !yearId) return
    if (needsBranch && budgetBranchId == null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale year data
    setBudgetRows(null)
    const branchParam =
      budgetBranchId != null ? `&branch_id=${budgetBranchId}` : ""
    apiFetch<{
      data: BudgetRow[]
      meta: {
        window_from: string
        window_to: string | null
        pending_total: string
      }
    }>(`/finance/budgets?academic_year_id=${yearId}${branchParam}`)
      .then((res) => {
        if (cancelled) return
        setBudgetRows(res.data)
        setBudgetMeta(res.meta)
        setBudgetEdits(
          Object.fromEntries(
            res.data.map((row) => [row.finance_category_id, row.budget ?? ""])
          )
        )
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [tab, yearId, needsBranch, budgetBranchId, refresh, workspace])

  const budgetTotals = useMemo(() => {
    const planned = Object.values(budgetEdits).reduce(
      (sum, v) => sum + (Number(v) || 0),
      0
    )
    const actual = (budgetRows ?? []).reduce(
      (sum, r) => sum + Number(r.actual),
      0
    )
    return { planned, actual }
  }, [budgetEdits, budgetRows])

  /** Flat client-mode rows: DataTable search/filters read top-level keys. */
  type BudgetTableRow = {
    id: number
    category: string
    budget: number
    actual: number
    pending: number
    remaining: number | null
    used: number | null
    usage: "over" | "high" | "ok" | "none"
  }
  const budgetTableRows = useMemo<BudgetTableRow[]>(
    () =>
      (budgetRows ?? []).map((row) => {
        const budget = Number(budgetEdits[row.finance_category_id] || 0)
        const actual = Number(row.actual)
        const used = budget > 0 ? Math.round((actual / budget) * 100) : null
        return {
          id: row.finance_category_id,
          category: row.category,
          budget,
          actual,
          pending: Number(row.pending ?? 0),
          remaining: budget > 0 ? budget - actual : null,
          used,
          usage:
            budget <= 0
              ? "none"
              : actual > budget
                ? "over"
                : (used ?? 0) >= 85
                  ? "high"
                  : "ok",
        }
      }),
    [budgetRows, budgetEdits]
  )

  const budgetColumns: DataTableColumn<BudgetTableRow>[] = [
    {
      key: "category",
      label: t("books.category"),
      primary: true,
      sortable: true,
      render: (row) => (
        <p className="min-w-0 truncate font-medium">{row.category}</p>
      ),
      exportValue: (row) => row.category,
    },
    {
      key: "budget",
      label: t("books.columns.budget"),
      className: "text-right",
      render: (row) => (
        <Input
          type="number"
          inputMode="decimal"
          min={0}
          disabled={!canManage}
          aria-label={t("books.columns.budget")}
          placeholder="—"
          className="no-spinner ml-auto h-9 w-28 text-right tabular-nums"
          value={budgetEdits[row.id] ?? ""}
          onChange={(e) =>
            setBudgetEdits((edits) => ({ ...edits, [row.id]: e.target.value }))
          }
        />
      ),
      exportValue: (row) => String(row.budget || ""),
    },
    {
      key: "actual",
      label: t("books.columns.actual"),
      sortable: true,
      className: "text-right",
      render: (row) => (
        <span
          className={cn(
            "tabular-nums",
            row.usage === "over" && "font-semibold text-destructive"
          )}
        >
          {etb(row.actual)}
        </span>
      ),
      exportValue: (row) => String(row.actual),
    },
    {
      key: "pending",
      label: t("books.columns.pendingApproval"),
      className: "text-right",
      mobileHidden: true,
      render: (row) =>
        row.pending > 0 ? (
          <span className="text-warning tabular-nums">+{etb(row.pending)}</span>
        ) : (
          <span className="text-muted-foreground/40">—</span>
        ),
      exportValue: (row) => (row.pending > 0 ? String(row.pending) : ""),
    },
    {
      key: "remaining",
      label: t("books.columns.remaining"),
      sortable: true,
      className: "text-right",
      mobileHidden: true,
      render: (row) =>
        row.remaining === null ? (
          <span className="text-muted-foreground/40">—</span>
        ) : (
          <span
            className={cn(
              "tabular-nums",
              row.remaining < 0
                ? "font-semibold text-destructive"
                : "text-muted-foreground"
            )}
          >
            {etb(row.remaining)}
          </span>
        ),
      exportValue: (row) => (row.remaining === null ? "" : String(row.remaining)),
    },
    {
      key: "used",
      label: t("books.columns.used"),
      sortable: true,
      render: (row) =>
        row.used === null ? (
          <span className="text-xs text-muted-foreground/60">
            {t("books.usage.none")}
          </span>
        ) : (
          <div className="flex min-w-28 items-center gap-2">
            <div className="h-1.5 w-full max-w-24 overflow-hidden rounded-full bg-muted">
              <div
                className={cn(
                  "h-full rounded-full",
                  row.usage === "over"
                    ? "bg-destructive"
                    : row.usage === "high"
                      ? "bg-warning"
                      : "bg-primary"
                )}
                style={{ width: `${Math.min(row.used, 100)}%` }}
              />
            </div>
            <span
              className={cn(
                "text-xs font-medium tabular-nums",
                row.usage === "over" && "text-destructive"
              )}
            >
              {row.used}%
            </span>
          </div>
        ),
      exportValue: (row) => (row.used === null ? "" : `${row.used}%`),
    },
  ]

  async function saveBudget() {
    if (!yearId) return
    setSavingBudget(true)
    try {
      const branchParam =
        budgetBranchId != null ? `&branch_id=${budgetBranchId}` : ""
      await apiFetch(
        `/finance/budgets?academic_year_id=${yearId}${branchParam}`,
        {
          method: "PUT",
          body: {
            items: Object.entries(budgetEdits).map(([id, amount]) => ({
              finance_category_id: Number(id),
              amount: amount === "" ? null : Number(amount),
            })),
          },
        }
      )
      toast.success(t("books.budgetSaved"))
      bump()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setSavingBudget(false)
    }
  }

  // ── Categories tab ────────────────────────────────────────────────
  const [newCategory, setNewCategory] = useState<
    Record<"expense" | "income", string>
  >({
    expense: "",
    income: "",
  })
  const [addingCategory, setAddingCategory] = useState(false)

  async function addCategory(kind: "expense" | "income") {
    const name = newCategory[kind].trim()
    if (!name) return
    setAddingCategory(true)
    try {
      await apiFetch("/finance/categories", {
        method: "POST",
        body: { kind, name },
      })
      toast.success(t("books.categoryCreated"))
      setNewCategory((c) => ({ ...c, [kind]: "" }))
      loadCategories()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    } finally {
      setAddingCategory(false)
    }
  }

  async function toggleCategory(category: FinanceCategory) {
    try {
      await apiFetch(`/finance/categories/${category.id}`, {
        method: "PUT",
        body: { is_active: !category.is_active },
      })
      loadCategories()
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  function deleteCategory(category: FinanceCategory) {
    confirmDelete(async () => {
      try {
        await apiFetch(`/finance/categories/${category.id}`, {
          method: "DELETE",
        })
        toast.success(t("books.categoryDeleted"))
        loadCategories()
      } catch (error) {
        toast.error(
          error instanceof ApiError ? error.message : tc("errors.generic")
        )
      }
    }, t("books.categoryDeleteHint"))
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("books.title")}
        description={t("books.subtitle")}
        actions={
          canManage && (tab === "expenses" || tab === "income") ? (
            <Button
              className="h-11"
              onClick={() =>
                setEntrySheet({
                  kind: tab === "expenses" ? "expense" : "income",
                  entry: null,
                })
              }
            >
              <Plus className="size-4" />
              {t(
                tab === "expenses"
                  ? "books.recordExpense"
                  : "books.recordIncome"
              )}
            </Button>
          ) : null
        }
      />

      <div className="page-gutter">
        <ProfileTabBar tabs={tabs} value={tab} onChange={setTab} />
      </div>

      {/* ══ Expenses ══ */}
      {tab === "expenses" && (
        <>
          <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
              label={t("books.stats.approvedTotal")}
              value={expenseStats ? etb(expenseStats.approved_total) : null}
              icon={CheckCircle2}
              hint={
                expenseStats
                  ? t("books.stats.entries", {
                      count: expenseStats.approved_count,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("books.stats.pendingTotal")}
              value={expenseStats ? etb(expenseStats.pending_total) : null}
              icon={Clock3}
              hint={
                expenseStats
                  ? t("books.stats.entries", {
                      count: expenseStats.pending_count,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("books.stats.rejectedTotal")}
              value={expenseStats ? etb(expenseStats.rejected_total) : null}
              icon={XCircle}
              hint={
                expenseStats
                  ? t("books.stats.entries", {
                      count: expenseStats.rejected_count,
                    })
                  : undefined
              }
            />
            <StatCard
              label={t("books.stats.allTotal")}
              value={
                expenseStats
                  ? etb(
                      Number(expenseStats.approved_total) +
                        Number(expenseStats.pending_total)
                    )
                  : null
              }
              icon={CircleDollarSign}
              hint={expenseStats ? t("books.stats.allTotalHint") : undefined}
            />
          </div>

          {/* Full-bleed — DataTable brings its own gutters. */}
          <DataTable
            columns={expenseColumns}
            data={expenses.rows}
            loading={expenses.loading}
            total={expenses.total}
            serverMode
            searchable
            searchValue={expenses.searchInput}
            onSearchChange={expenses.setSearchInput}
            searchPlaceholder={t("books.searchExpenses")}
            filters={[
              ...scopeFilters,
              {
                key: "status",
                label: t("books.columns.status"),
                options: (["pending", "approved", "rejected"] as const).map(
                  (s) => ({
                    value: s,
                    label: t(`books.statuses.${s}`),
                  })
                ),
              },
              ...(categories
                ? [
                    {
                      key: "finance_category_id",
                      label: t("books.category"),
                      options: categories
                        .filter((c) => c.kind === "expense")
                        .map((c) => ({ value: String(c.id), label: c.name })),
                    },
                  ]
                : []),
            ]}
            filterValues={expenses.filters}
            onFilterChange={expenses.setFilter}
            onSortChange={expenses.onSortChange}
            pagination={expenses.pagination}
            emptyMessage={t("books.noExpenses")}
            exportFilename="expenses"
            bulkActions={
              canApprove
                ? [
                    {
                      label: t("books.approve"),
                      icon: CheckCircle2,
                      onClick: (rows: Expense[]) => openBulkExpense(rows, "approved"),
                    },
                    {
                      label: t("books.reject"),
                      icon: XCircle,
                      destructive: true,
                      onClick: (rows: Expense[]) => openBulkExpense(rows, "rejected"),
                    },
                  ]
                : undefined
            }
            actions={[
              {
                label: t("books.approve"),
                icon: CheckCircle2,
                hidden: (row) =>
                  !canApprove ||
                  row.status !== "pending" ||
                  row.recorded_by === user?.id,
                onClick: (row) => decide(row, true),
                disabled: () => working,
              },
              {
                label: t("books.reject"),
                icon: XCircle,
                destructive: true,
                hidden: (row) =>
                  !canApprove ||
                  row.status !== "pending" ||
                  row.recorded_by === user?.id,
                onClick: (row) => {
                  setRejectNote("")
                  setRejecting(row)
                },
              },
              {
                label: tc("actions.edit"),
              icon: Pencil,
                primary: true,
                hidden: (row) => !canManage || row.status !== "pending",
                onClick: (row) =>
                  setEntrySheet({ kind: "expense", entry: row }),
              },
              {
                label: tc("actions.delete"),
              icon: Trash2,
                destructive: true,
                hidden: (row) => !canManage || row.status !== "pending",
                onClick: (row) =>
                  confirmDelete(async () => {
                    try {
                      await apiFetch(`/finance/expenses/${row.id}`, {
                        method: "DELETE",
                      })
                      toast.success(t("books.expenseDeleted"))
                      bump()
                    } catch (error) {
                      toast.error(
                        error instanceof ApiError
                          ? error.message
                          : tc("errors.generic")
                      )
                    }
                  }),
              },
            ]}
          />
        </>
      )}

      {/* ══ Other income ══ */}
      {tab === "income" && (
        <DataTable
          columns={incomeColumns}
          data={incomes.rows}
          loading={incomes.loading}
          total={incomes.total}
          serverMode
          searchable
          searchValue={incomes.searchInput}
          onSearchChange={incomes.setSearchInput}
          searchPlaceholder={t("books.searchIncome")}
          filters={[
            ...scopeFilters,
            ...(categories
              ? [
                  {
                    key: "finance_category_id",
                    label: t("books.category"),
                    options: categories
                      .filter((c) => c.kind === "income")
                      .map((c) => ({ value: String(c.id), label: c.name })),
                  },
                ]
              : []),
          ]}
          filterValues={incomes.filters}
          onFilterChange={incomes.setFilter}
          onSortChange={incomes.onSortChange}
          pagination={incomes.pagination}
          emptyMessage={t("books.noIncome")}
          exportFilename="other-income"
          actions={[
            {
              label: tc("actions.edit"),
              icon: Pencil,
              primary: true,
              hidden: () => !canManage,
              onClick: (row) => setEntrySheet({ kind: "income", entry: row }),
            },
            {
              label: tc("actions.delete"),
              icon: Trash2,
              destructive: true,
              hidden: () => !canManage,
              onClick: (row) =>
                confirmDelete(async () => {
                  try {
                    await apiFetch(`/finance/other-incomes/${row.id}`, {
                      method: "DELETE",
                    })
                    toast.success(t("books.incomeDeleted"))
                    bump()
                  } catch (error) {
                    toast.error(
                      error instanceof ApiError
                        ? error.message
                        : tc("errors.generic")
                    )
                  }
                }),
            },
          ]}
        />
      )}

      {/* ══ Cashbook ══ */}
      {tab === "cashbook" && (
        <>
          <div className="page-gutter">
            <RangeControl range={range} onChange={setRange} />
          </div>

          <div className="page-gutter grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
              label={t("books.stats.moneyIn")}
              value={cashTotals ? etb(cashTotals.money_in) : null}
              icon={ArrowUpCircle}
            />
            <StatCard
              label={t("books.stats.moneyOut")}
              value={cashTotals ? etb(cashTotals.money_out) : null}
              icon={ArrowDownCircle}
            />
            <StatCard
              label={t("books.stats.net")}
              value={cashTotals ? etb(cashTotals.net) : null}
              icon={Scale}
              hint={
                cashTotals && Number(cashTotals.net) < 0
                  ? t("books.stats.netNegative")
                  : undefined
              }
              className="col-span-2 lg:col-span-1"
            />
          </div>

          <DataTable
            columns={cashbookColumns}
            data={cashbook.rows}
            loading={cashbook.loading}
            total={cashbook.total}
            serverMode
            searchable
            searchValue={cashbook.searchInput}
            onSearchChange={cashbook.setSearchInput}
            searchPlaceholder={t("books.searchCashbook")}
            groupBy={(row) => row.entry_date}
            renderGroupHeader={renderCashbookDay}
            filters={[
              ...scopeFilters,
              {
                key: "direction",
                label: t("books.filters.direction"),
                options: [
                  { value: "in", label: t("books.stats.moneyIn") },
                  { value: "out", label: t("books.stats.moneyOut") },
                ],
              },
              {
                key: "source",
                label: t("books.filters.source"),
                options: (
                  ["fee_payment", "other_income", "expense", "payroll"] as const
                ).map((s) => ({ value: s, label: t(`books.sources.${s}`) })),
              },
              ...(categories
                ? [
                    {
                      key: "finance_category_id",
                      label: t("books.category"),
                      options: categories
                        .filter((c) => c.is_active)
                        .map((c) => ({ value: String(c.id), label: c.name })),
                    },
                  ]
                : []),
              {
                key: "method",
                label: t("payments.method"),
                options: (
                  ["cash", "bank_transfer", "wallet", "other"] as const
                ).map((m) => ({ value: m, label: t(`methods.${m}`) })),
              },
            ]}
            filterValues={cashbook.filters}
            onFilterChange={cashbook.setFilter}
            pagination={cashbook.pagination}
            emptyMessage={t("books.noEntriesHint")}
            exportFilename="cashbook"
          />
        </>
      )}

      {/* ══ Statement ══ */}
      {tab === "statement" && (
        <>
          <div className="page-gutter flex flex-wrap items-center gap-2">
            <RangeControl range={range} onChange={setRange} />
            {active.schoolId !== null && (
              <DocumentDownloadButton
                type="finance_statement"
                params={{
                  school_id: active.schoolId,
                  branch_id: active.branchId ?? undefined,
                  from: range.from,
                  to: range.to,
                }}
              />
            )}
          </div>

          <div className="page-gutter">
            {statement === null ? (
              <Skeleton className="mx-auto h-96 max-w-3xl rounded-2xl" />
            ) : (
              <section className="mx-auto max-w-3xl overflow-hidden rounded-2xl border bg-card shadow-xs">
                <div className="border-b bg-muted/30 px-5 py-4">
                  <h2 className="font-display text-base font-semibold">
                    {t("books.statement.title")}
                  </h2>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {range.from} — {range.to}
                  </p>
                </div>

                <div className="px-5 py-4">
                  <p className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-success uppercase">
                    <ArrowUpCircle className="size-3.5" strokeWidth={1.75} />
                    {t("books.statement.income")}
                  </p>
                  <ul className="mt-2 space-y-1.5 text-sm">
                    <li className="flex items-baseline justify-between gap-3">
                      <span>{t("books.statement.schoolFees")}</span>
                      <span className="font-medium tabular-nums">
                        {etb(statement.income.school_fees)}
                      </span>
                    </li>
                    {statement.income.other.map((row) => (
                      <li
                        key={row.category}
                        className="flex items-baseline justify-between gap-3"
                      >
                        <span className="min-w-0 truncate">{row.category}</span>
                        <span className="font-medium tabular-nums">
                          {etb(row.amount)}
                        </span>
                      </li>
                    ))}
                  </ul>
                  <div className="mt-2.5 flex items-baseline justify-between border-t pt-2 text-sm font-semibold">
                    <span>{t("books.statement.totalIncome")}</span>
                    <span className="tabular-nums">
                      {etb(statement.income.total)}
                    </span>
                  </div>
                </div>

                <div className="border-t px-5 py-4">
                  <p className="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-destructive uppercase">
                    <ArrowDownCircle className="size-3.5" strokeWidth={1.75} />
                    {t("books.statement.expenses")}
                  </p>
                  <ul className="mt-2 space-y-1.5 text-sm">
                    <li className="flex items-baseline justify-between gap-3">
                      <span>{t("books.statement.payroll")}</span>
                      <span className="font-medium tabular-nums">
                        {etb(statement.expenses.payroll)}
                      </span>
                    </li>
                    {statement.expenses.categories.map((row) => (
                      <li
                        key={row.category}
                        className="flex items-baseline justify-between gap-3"
                      >
                        <span className="min-w-0 truncate">{row.category}</span>
                        <span className="font-medium tabular-nums">
                          {etb(row.amount)}
                        </span>
                      </li>
                    ))}
                  </ul>
                  <div className="mt-2.5 flex items-baseline justify-between border-t pt-2 text-sm font-semibold">
                    <span>{t("books.statement.totalExpenses")}</span>
                    <span className="tabular-nums">
                      {etb(statement.expenses.total)}
                    </span>
                  </div>
                </div>

                <div
                  className={cn(
                    "flex items-baseline justify-between border-t px-5 py-4",
                    Number(statement.net) >= 0
                      ? "bg-success/5"
                      : "bg-destructive/5"
                  )}
                >
                  <p className="text-sm font-semibold">
                    {t("books.statement.net")}
                  </p>
                  <p
                    className={cn(
                      "font-display text-xl font-bold tabular-nums",
                      Number(statement.net) >= 0
                        ? "text-success"
                        : "text-destructive"
                    )}
                  >
                    {etb(statement.net)}
                  </p>
                </div>
              </section>
            )}
          </div>
        </>
      )}

      {/* ══ Budget ══ */}
      {tab === "budget" && (
        <>
          <div className="page-gutter flex flex-wrap items-center gap-2">
            <BranchScopePicker
              value={budgetBranchId}
              onChange={setBudgetBranchId}
            />
            <Select value={yearId} onValueChange={setYearId}>
              <SelectTrigger className="h-9 w-44">
                <SelectValue placeholder={t("structures.selectYear")} />
              </SelectTrigger>
              <SelectContent>
                {years.map((y) => (
                  <SelectItem key={y.id} value={String(y.id)}>
                    {y.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {canManage && budgetRows !== null && (
              <Button
                size="sm"
                className="ml-auto"
                onClick={saveBudget}
                disabled={savingBudget}
              >
                {savingBudget ? (
                  <Loader2 className="size-4 animate-spin" />
                ) : null}
                {t("books.saveBudget")}
              </Button>
            )}
          </div>

          {needsBranch && budgetBranchId == null ? (
            <div className="page-gutter">
              <EmptyState
                icon={Scale}
                title={t("books.pickBranchTitle")}
                description={t("books.pickBranchHint")}
              />
            </div>
          ) : budgetRows === null ? (
            <div className="page-gutter">
              <Skeleton className="h-96 rounded-2xl" />
            </div>
          ) : (
            <>
              {budgetMeta && (
                <p className="page-gutter text-xs text-muted-foreground">
                  {budgetMeta.window_to
                    ? t("books.budgetWindow", {
                        from: budgetMeta.window_from,
                        to: budgetMeta.window_to,
                      })
                    : t("books.budgetWindowOpen", {
                        from: budgetMeta.window_from,
                      })}
                </p>
              )}

              <DataTable
                columns={budgetColumns}
                data={budgetTableRows}
                searchKeys={["category"]}
                searchPlaceholder={t("books.searchBudget")}
                filters={[
                  {
                    key: "usage",
                    label: t("books.filters.usage"),
                    options: (["over", "high", "ok", "none"] as const).map(
                      (u) => ({ value: u, label: t(`books.usage.${u}`) })
                    ),
                  },
                ]}
                emptyMessage={t("books.noEntriesHint")}
                exportFilename="budget"
              />

              <div className="page-gutter">
                <div className="flex flex-wrap items-baseline justify-between gap-3 rounded-2xl border bg-muted/30 px-4 py-3 text-sm font-semibold">
                  <span>{t("books.budgetTotals")}</span>
                  <span className="tabular-nums">
                    {etb(budgetTotals.actual)}
                    <span className="text-muted-foreground">
                      {" "}
                      / {etb(budgetTotals.planned)}
                    </span>
                    {budgetMeta && Number(budgetMeta.pending_total) > 0 && (
                      <span className="ml-2 text-xs font-medium text-warning">
                        +{etb(budgetMeta.pending_total)}{" "}
                        {t("books.columns.pendingApproval").toLowerCase()}
                      </span>
                    )}
                  </span>
                </div>
              </div>
            </>
          )}
        </>
      )}

      {/* ══ Categories ══ */}
      {tab === "categories" && (
        <div className="page-gutter">
          {categories === null ? (
            <div className="grid gap-4 lg:grid-cols-2">
              <Skeleton className="h-72 rounded-2xl" />
              <Skeleton className="h-72 rounded-2xl" />
            </div>
          ) : (
            <div className="grid gap-4 lg:grid-cols-2">
              {(["expense", "income"] as const).map((kind) => (
                <section
                  key={kind}
                  className="rounded-2xl border bg-card p-4 shadow-xs md:p-5"
                >
                  <h2 className="font-display text-base font-semibold">
                    {t(`books.kinds.${kind}`)}
                  </h2>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {t(`books.kindHints.${kind}`)}
                  </p>

                  {canManage && (
                    <div className="mt-3 flex items-center gap-2">
                      <Input
                        value={newCategory[kind]}
                        onChange={(e) =>
                          setNewCategory((c) => ({
                            ...c,
                            [kind]: e.target.value,
                          }))
                        }
                        placeholder={t("books.categoryNamePlaceholder")}
                        className="h-10"
                        onKeyDown={(e) =>
                          e.key === "Enter" && addCategory(kind)
                        }
                      />
                      <Button
                        size="icon"
                        variant="outline"
                        className="size-10 shrink-0"
                        onClick={() => addCategory(kind)}
                        loading={addingCategory} disabled={!newCategory[kind].trim()}
                        aria-label={tc("actions.add")}
                      >
                        <Plus className="size-4" />
                      </Button>
                    </div>
                  )}

                  <ul className="mt-2 divide-y">
                    {categories
                      .filter((c) => c.kind === kind)
                      .map((category) => (
                        <li
                          key={category.id}
                          className="flex min-h-11 items-center gap-3 py-1.5"
                        >
                          <span
                            className={cn(
                              "min-w-0 flex-1 truncate text-sm",
                              !category.is_active &&
                                "text-muted-foreground line-through"
                            )}
                          >
                            {category.name}
                          </span>
                          {canManage && (
                            <>
                              <Switch
                                checked={category.is_active}
                                onCheckedChange={() => toggleCategory(category)}
                                aria-label={t("books.categoryActiveToggle", {
                                  name: category.name,
                                })}
                              />
                              <Button
                                variant="ghost"
                                size="icon"
                                className="size-9 text-muted-foreground hover:text-destructive"
                                onClick={() => deleteCategory(category)}
                                aria-label={tc("actions.delete")}
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            </>
                          )}
                        </li>
                      ))}
                  </ul>
                </section>
              ))}
            </div>
          )}
        </div>
      )}

      <FinanceEntrySheet
        kind={entrySheet?.kind ?? "expense"}
        entry={entrySheet?.entry ?? null}
        categories={activeCategories(entrySheet?.kind ?? "expense")}
        open={entrySheet !== null}
        onOpenChange={(v) => !v && setEntrySheet(null)}
        onSaved={bump}
      />

      {/* Reject an expense with the note the recorder will see. */}
      <Dialog
        open={rejecting !== null}
        onOpenChange={(v) => !v && setRejecting(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t("books.rejectTitle")}</DialogTitle>
            <DialogDescription>{t("books.rejectHint")}</DialogDescription>
          </DialogHeader>
          <Input
            value={rejectNote}
            onChange={(e) => setRejectNote(e.target.value)}
            placeholder={t("books.rejectPlaceholder")}
          />
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setRejecting(null)}
              disabled={working}
            >
              {tc("actions.cancel")}
            </Button>
            <Button
              variant="destructive"
              disabled={working || !rejectNote.trim()}
              onClick={() =>
                rejecting && decide(rejecting, false, rejectNote.trim())
              }
            >
              {working ? <Loader2 className="size-4 animate-spin" /> : null}
              {t("books.reject")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Countersign a batch of pending expenses. */}
      <BulkDecisionDialog
        open={bulkDecision !== null}
        onOpenChange={(v) => {
          if (!v) setBulkDecision(null)
        }}
        mode={bulkDecision?.decision === "rejected" ? "reject" : "approve"}
        title={
          bulkDecision?.decision === "rejected"
            ? t("books.bulk.rejectTitle", { count: bulkDecision?.rows.length ?? 0 })
            : t("books.bulk.approveTitle", { count: bulkDecision?.rows.length ?? 0 })
        }
        description={
          bulkDecision?.decision === "rejected"
            ? t("books.bulk.rejectDesc")
            : t("books.bulk.approveDesc")
        }
        noteLabel={t("books.reviewNote")}
        notePlaceholder={
          bulkDecision?.decision === "rejected"
            ? t("books.rejectNotePlaceholder")
            : t("books.bulk.noteOptional")
        }
        confirmLabel={
          bulkDecision?.decision === "rejected" ? t("books.reject") : t("books.approve")
        }
        onConfirm={async (note) => {
          if (!bulkDecision) return
          await runBulk({
            url: "/finance/expenses/bulk/decide",
            ids: bulkDecision.rows.map((r) => r.id),
            body: { decision: bulkDecision.decision, review_note: note || undefined },
            countKey: "decided",
            success: (count) =>
              bulkDecision.decision === "approved"
                ? t("books.bulk.approved", { count })
                : t("books.bulk.rejected", { count }),
            tc,
          })
          setBulkDecision(null)
          bump()
        }}
      />

      {confirmDialog}
    </div>
  )
}
