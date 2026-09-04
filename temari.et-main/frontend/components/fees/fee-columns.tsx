"use client"

import { Check, Copy, Share2 } from "lucide-react"
import { useMemo, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { Button } from "@/components/ui/button"
import { copyText } from "@/components/ui/copyable-id"
import type { DataTableColumn, DataTableFilter } from "@/components/ui/data-table"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { useTranslation } from "@/lib/i18n"
import type { FeeBankAccount, FeeStructure, FeeType } from "@/lib/types"

const FEE_TYPES: FeeType[] = [
  "registration",
  "one_time",
  "daily",
  "weekly",
  "monthly",
  "quarterly",
  "semester",
  "yearly",
]

const DAY_MS = 24 * 60 * 60 * 1000

/** Days until (positive) or since (negative) the due date. */
function daysToDue(fee: FeeStructure): number | null {
  if (!fee.due_on) return null
  const due = new Date(`${fee.due_on}T00:00:00`).getTime()
  if (Number.isNaN(due)) return null
  return Math.ceil((due - Date.now()) / DAY_MS)
}

function gradeExport(fee: FeeStructure, allGrades: string): string {
  const grades = fee.grade_levels ?? []
  return grades.length === 0 ? allGrades : grades.map((g) => g.name).join(", ")
}

/**
 * One collection account rendered as its bank logo only — tapping it opens
 * a popover with the account details plus copy / native-share actions. Keeps
 * the fee row narrow no matter how many accounts a fee collects into.
 */
function BankAccountLogo({ account }: { account: FeeBankAccount }) {
  const { t } = useTranslation("fees")
  const [copied, setCopied] = useState(false)

  const shareText = [account.bank_name, account.account_name, account.account_number]
    .filter(Boolean)
    .join(" · ")

  async function copy() {
    if (await copyText(account.account_number)) {
      setCopied(true)
      setTimeout(() => setCopied(false), 1600)
    }
  }

  async function share() {
    // Native share where the platform offers it (Android/iOS); copy fallback.
    if (typeof navigator !== "undefined" && navigator.share) {
      try {
        await navigator.share({ text: shareText })
        return
      } catch {
        // Dismissed or unsupported payload — fall through to copy.
      }
    }
    if (await copyText(shareText)) toast.success(t("structures.accountCopied"))
  }

  const bank =
    account.bank_logo !== undefined
      ? {
          id: account.id,
          code: account.bank_code ?? "",
          name: account.bank_name ?? "",
          type: account.bank_type ?? ("bank" as const),
          logo: account.bank_logo,
        }
      : null

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          onClick={(e) => e.stopPropagation()}
          aria-label={`${account.bank_name ?? ""} ${account.account_number}`}
          className="pressable rounded-full ring-2 ring-background transition-transform hover:z-10 hover:scale-110"
        >
          <BankLogo bank={bank} size={26} />
        </button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        className="w-64 p-3"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center gap-2.5">
          <BankLogo bank={bank} size={32} />
          <div className="min-w-0">
            <p className="truncate text-sm font-medium">{account.bank_name}</p>
            <p className="truncate text-xs text-muted-foreground">{account.account_name}</p>
          </div>
        </div>
        <p className="mt-2 rounded-lg bg-muted/50 px-2.5 py-1.5 font-mono text-sm tabular-nums">
          {account.account_number}
        </p>
        <div className="mt-2 grid grid-cols-2 gap-1.5">
          <Button variant="outline" size="sm" onClick={copy}>
            {copied ? <Check className="size-3.5 text-success" /> : <Copy className="size-3.5" />}
            {t("structures.copyAccount")}
          </Button>
          <Button variant="outline" size="sm" onClick={share}>
            <Share2 className="size-3.5" />
            {t("structures.shareAccount")}
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}

/**
 * Shared fee table definition — one source of truth for the Fees page and the
 * table inside an academic year's detail page.
 */
export function useFeeColumns({
  showBranch = false,
  showYear = true,
  showInvoices = true,
}: {
  showBranch?: boolean
  showYear?: boolean
  showInvoices?: boolean
} = {}): DataTableColumn<FeeStructure>[] {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")

  return useMemo(
    () => [
      ...(showBranch
        ? [
            {
              key: "branch_name",
              label: tc("columns.branch"),
              render: (row: FeeStructure) => (
                <span className="text-xs text-muted-foreground">
                  {row.school_name} · {row.branch_name}
                </span>
              ),
            } as DataTableColumn<FeeStructure>,
          ]
        : []),
      {
        key: "name",
        label: t("structures.name"),
        sortable: true,
        primary: true,
        render: (row) => (
          <div className="leading-tight">
            <span className="block font-medium">{row.name}</span>
            <span className="text-xs text-muted-foreground">{t(`types.${row.type}`)}</span>
          </div>
        ),
        exportValue: (row) => row.name,
      },
      ...(showYear
        ? [
            {
              key: "academic_year",
              label: t("structures.academicYear"),
              render: (row: FeeStructure) => row.academic_year_name ?? "—",
              exportValue: (row: FeeStructure) => row.academic_year_name ?? "",
            } as DataTableColumn<FeeStructure>,
          ]
        : []),
      // NB: no separate Type column — the name column already carries the
      // payment type as its subtitle; the type filter reads the flat row key.
      {
        key: "grade",
        label: t("structures.grade"),
        // First two grades inline, the rest collapsed into a "+N" chip.
        render: (row) => {
          const grades = row.grade_levels ?? []
          if (grades.length === 0)
            return <span className="text-sm">{t("structures.allGrades")}</span>
          const inline = grades.slice(0, 2)
          return (
            <div className="flex flex-wrap items-center gap-1">
              {inline.map((g) => (
                <Badge key={g.id} variant="outline" className="px-1.5 py-0 text-[11px]">
                  {g.name}
                </Badge>
              ))}
              {grades.length > inline.length && (
                <Badge variant="secondary" className="px-1.5 py-0 text-[11px] tabular-nums">
                  +{grades.length - inline.length}
                </Badge>
              )}
            </div>
          )
        },
        exportValue: (row) => gradeExport(row, t("structures.allGrades")),
      },
      {
        key: "amount",
        label: t("structures.amount"),
        sortable: true,
        render: (row) => <span className="font-mono text-sm tabular-nums">{row.amount} ETB</span>,
        exportValue: (row) => row.amount,
      },
      {
        // Collection accounts as a row of tappable bank logos — the details
        // (account name/number, copy, share) live in each logo's popover.
        key: "bank_accounts",
        label: t("structures.bankAccount"),
        render: (row) => {
          const accounts = row.bank_accounts ?? []
          if (accounts.length === 0) return "—"
          return (
            <div className="flex items-center -space-x-1.5">
              {accounts.map((account) => (
                <BankAccountLogo key={account.id} account={account} />
              ))}
            </div>
          )
        },
        exportValue: (row) =>
          (row.bank_accounts ?? [])
            .map((a) => `${a.bank_name ?? ""} ${a.account_number}`.trim())
            .join("; "),
      },
      {
        // Billing window stacked vertically (start / due / countdown) so the
        // column stays narrow.
        key: "schedule",
        label: t("structures.schedule"),
        render: (row) => {
          if (!row.starts_on && !row.due_on)
            return <span className="text-muted-foreground">—</span>
          const days = daysToDue(row)
          return (
            <div className="leading-snug whitespace-nowrap flex flex-col items-center gap-1">
              <span className="block text-sm tabular-nums">{row.starts_on ?? "…"}</span>
              <span className="block text-sm tabular-nums">
                <span className="block text-muted-foreground text-center">↓</span>
           
                {row.due_on ?? "…"}
              </span>
              {days !== null && (
                <span
                  className={
                    days < 0 ? "block text-xs text-destructive" : "block text-xs text-muted-foreground"
                  }
                >
                  {days < 0
                    ? t("structures.overdueBy", { count: Math.abs(days) })
                    : t("structures.dueIn", { count: days })}
                </span>
              )}
            </div>
          )
        },
        exportValue: (row) => [row.starts_on, row.due_on].filter(Boolean).join(" → "),
      },
      {
        key: "penalty",
        label: t("penalty.title"),
        render: (row) => {
          if (!row.penalty_type) return <span className="text-muted-foreground">—</span>
          return (
            <div className="leading-tight">
              <span className="block text-sm tabular-nums text-warning">
                {row.penalty_type === "incremental"
                  ? t("penalty.detailIncremental", {
                      amount: row.penalty_amount ?? "0",
                      days: row.penalty_increment_days ?? 0,
                    })
                  : t("penalty.detailFixed", { amount: row.penalty_amount ?? "0" })}
              </span>
              <span className="block text-xs text-muted-foreground">
                {t(`penalty.${row.penalty_type}`)}
              </span>
            </div>
          )
        },
        exportValue: (row) =>
          row.penalty_type
            ? `${row.penalty_type}: ${row.penalty_amount ?? ""}${
                row.penalty_increment_days ? ` / ${row.penalty_increment_days}d` : ""
              }`
            : "",
      },
      ...(showInvoices
        ? [
            {
              key: "invoices_count",
              label: t("structures.invoices"),
                    render: (row: FeeStructure) => row.invoices_count ?? 0,
              exportValue: (row: FeeStructure) => String(row.invoices_count ?? 0),
            } as DataTableColumn<FeeStructure>,
          ]
        : []),
      {
        key: "is_active",
        label: tc("states.active"),
        render: (row) => (
          <Badge variant={row.is_active ? "default" : "secondary"}>
            {row.is_active ? tc("states.active") : tc("states.inactive")}
          </Badge>
        ),
        exportValue: (row) => (row.is_active ? "Active" : "Inactive"),
      },
    ],
    [t, tc, showBranch, showYear, showInvoices],
  )
}

/** Client-side filters for a fee table. */
export function useFeeFilters({
  fees,
  showYearFilter = true,
}: {
  fees: FeeStructure[]
  showYearFilter?: boolean
}): DataTableFilter[] {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")

  return useMemo(() => {
    const filters: DataTableFilter[] = [
      {
        key: "type",
        label: t("structures.type"),
        options: FEE_TYPES.map((type) => ({ label: t(`types.${type}`), value: type })),
      },
    ]

    if (showYearFilter) {
      const years = new Map<number, string>()
      for (const fee of fees) {
        if (fee.academic_year_name) years.set(fee.academic_year_id, fee.academic_year_name)
      }
      if (years.size > 1) {
        filters.push({
          key: "academic_year_id",
          label: t("structures.academicYear"),
          options: [...years.entries()].map(([id, name]) => ({
            label: name,
            value: String(id),
          })),
        })
      }
    }

    filters.push(
      {
        key: "penalty_type",
        label: t("penalty.title"),
        options: [
          { label: t("penalty.fixed"), value: "fixed" },
          { label: t("penalty.incremental"), value: "incremental" },
        ],
      },
      {
        key: "is_active",
        label: tc("filters.status"),
        options: [
          { label: tc("states.active"), value: "true" },
          { label: tc("states.inactive"), value: "false" },
        ],
      },
    )

    return filters
  }, [fees, showYearFilter, t, tc])
}
