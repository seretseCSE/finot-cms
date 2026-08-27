"use client"

import { ChartColumn, Plus, Trash2 } from "lucide-react"
import { useRouter } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"

import { BankAccountsSheet, BankLogo } from "@/components/fees/bank-accounts-sheet"
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
import { useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { ContactActionCell } from "@/components/ui/contact-action-cell"
import { DataTable, type DataTableColumn, type DataTableFilter } from "@/components/ui/data-table"
import { PageHeader } from "@/components/ui/page-header"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import { formatETB } from "@/lib/utils"
import type { BankAccount } from "@/lib/types"

/** A pending on/off flip awaiting the user's confirmation. */
interface PendingToggle {
  account: BankAccount
  field: "is_active" | "branch_active"
  value: boolean
}

export default function PaymentAccountsPage() {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { active, isPlatform } = useSchoolContext()
  const permissions = useEffectivePermissions()
  const router = useRouter()

  const [accounts, setAccounts] = useState<(BankAccount & { branch_ids: number[] })[] | null>(null)
  const [sheetOpen, setSheetOpen] = useState(false)
  const [pending, setPending] = useState<PendingToggle | null>(null)
  const [toggleWorking, setToggleWorking] = useState(false)

  const canManage = permissions.includes("fees.manage")
  const hasBranch = active.branchId != null
  const hasScope = hasBranch || active.schoolId != null || isPlatform

  // Accounts are SCHOOL-owned with a per-branch attachment pivot, so branch
  // "narrowing" is a client-side lens over the loaded rows: which accounts
  // are attached to the chosen branch. Only the school-wide workspace needs
  // it — a branch workspace already shows its own attachment column.
  const { needsBranch, branches: scopeBranches } = useBranchScope()
  const branchFilter: DataTableFilter[] =
    needsBranch && scopeBranches.length > 1
      ? [
          {
            key: "branch_ids",
            label: tc("filters.branch"),
            options: scopeBranches.map((b) => ({ label: b.name, value: String(b.id) })),
          },
        ]
      : []

  const load = useCallback(async () => {
    try {
      const res = await apiFetch<{ data: BankAccount[] }>("/bank-accounts")
      // Client-mode filters read FLAT row keys — flatten the attachment pivot.
      setAccounts(
        res.data.map((account) => ({
          ...account,
          branch_ids: account.branches.map((b) => b.id),
        })),
      )
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("accounts.loadFailed"))
      setAccounts([])
    }
  }, [t])

  useEffect(() => {
    if (!hasScope) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- async load
    load()
  }, [hasScope, active.branchId, active.schoolId, load])

  async function applyToggle() {
    if (!pending) return
    setToggleWorking(true)
    try {
      await apiFetch(`/bank-accounts/${pending.account.id}`, {
        method: "PUT",
        body: { [pending.field]: pending.value },
      })
      toast.success(t("accounts.updated"))
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("accounts.updateFailed"))
    } finally {
      setToggleWorking(false)
      setPending(null)
    }
  }

  async function handleDelete(account: BankAccount) {
    try {
      await apiFetch(`/bank-accounts/${account.id}`, { method: "DELETE" })
      toast.success(t("accounts.removed"))
      await load()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("accounts.updateFailed"))
    }
  }

  const columns: DataTableColumn<BankAccount>[] = [
    {
      key: "account_name",
      label: t("accounts.columns.account"),
      primary: true,
      render: (row) => (
        <span className="flex items-center gap-2.5">
          <BankLogo bank={row.bank} size={32} />
          <span className="flex min-w-0 flex-col">
            <span className="flex items-center gap-1.5 font-medium">
              <span className="truncate">{row.account_name}</span>
              {row.bank?.type === "wallet" && (
                <Badge variant="outline" className="text-[11px]">
                  {t("accounts.wallet")}
                </Badge>
              )}
            </span>
            <span className="text-xs text-muted-foreground">
              {row.bank?.name} ·{" "}
              <ContactActionCell
                kind="value"
                value={row.account_number}
                name={row.account_name}
                triggerClassName="text-xs"
              />
            </span>
          </span>
        </span>
      ),
      exportValue: (row) =>
        `${row.bank?.name ?? ""} ${row.account_name} (${row.account_number})`,
    },
    {
      key: "branches",
      label: t("accounts.columns.branches"),
      mobileHidden: true,
      render: (row) => (
        <span className="text-xs text-muted-foreground">
          {row.branches.length === 0
            ? "—"
            : row.branches
                .map((b) => (b.is_active ? b.name : `${b.name} (${tc("states.inactive")})`))
                .join(", ")}
        </span>
      ),
      exportValue: (row) => row.branches.map((b) => b.name).join(", "),
    },
    {
      key: "collected_sum",
      label: t("accounts.columns.collected"),
      render: (row) => (
        <span className="font-medium tabular-nums">{formatETB(row.collected_sum ?? 0)}</span>
      ),
      exportValue: (row) => row.collected_sum ?? "0",
    },
    {
      key: "payments_count",
      label: t("accounts.columns.transactions"),
      mobileHidden: true,
      render: (row) => row.payments_count ?? 0,
      exportValue: (row) => String(row.payments_count ?? 0),
    },
    {
      key: "fee_structures_count",
      label: t("accounts.columns.fees"),
      mobileHidden: true,
      render: (row) => row.fee_structures_count ?? 0,
      exportValue: (row) => String(row.fee_structures_count ?? 0),
    },
    {
      key: "last_payment_at",
      label: t("accounts.columns.lastPayment"),
      mobileHidden: true,
      render: (row) => row.last_payment_at ?? "—",
      exportValue: (row) => row.last_payment_at ?? "",
    },
    ...(canManage && hasBranch
      ? [
          {
            key: "branch_active",
            label: t("accounts.activeForBranch"),
            render: (row: BankAccount) => (
              <span onClick={(e) => e.stopPropagation()}>
                <Switch
                  checked={row.attached_to_branch && row.branch_active === true}
                  onCheckedChange={(v) =>
                    setPending({ account: row, field: "branch_active", value: v })
                  }
                />
              </span>
            ),
            exportValue: (row: BankAccount) =>
              row.attached_to_branch && row.branch_active ? tc("states.active") : tc("states.inactive"),
          } as DataTableColumn<BankAccount>,
        ]
      : []),
    ...(canManage
      ? [
          {
            key: "is_active",
            label: t("accounts.activeForSchool"),
            render: (row: BankAccount) => (
              <span onClick={(e) => e.stopPropagation()}>
                <Switch
                  checked={row.is_active}
                  onCheckedChange={(v) =>
                    setPending({ account: row, field: "is_active", value: v })
                  }
                />
              </span>
            ),
            exportValue: (row: BankAccount) =>
              row.is_active ? tc("states.active") : tc("states.inactive"),
          } as DataTableColumn<BankAccount>,
        ]
      : []),
  ]

  const pendingMessage = pending
    ? t(
        pending.value ? "accounts.confirmEnable" : "accounts.confirmDisable",
        {
          name: pending.account.account_name,
          scope:
            pending.field === "is_active"
              ? t("accounts.scopeSchool")
              : t("accounts.scopeBranch"),
        }
      )
    : ""

  return (
    <div className="space-y-6">
      {confirmDialog}
      <PageHeader
        title={t("accounts.pageTitle")}
        description={t("accounts.pageSubtitle")}
        actions={
          canManage ? (
            <Button className="h-11" onClick={() => setSheetOpen(true)}>
              <Plus className="size-4" />
              {t("accounts.add")}
            </Button>
          ) : undefined
        }
      />

      {!hasScope ? (
        <div className="mx-4 rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground md:mx-8">
          {t("noBranch")}
        </div>
      ) : (
        <DataTable
          columns={columns}
          data={accounts ?? []}
          loading={accounts === null}
          searchKeys={["account_name", "account_number"]}
          searchPlaceholder={tc("actions.search")}
          filters={branchFilter}
          onRowClick={(row) => router.push(`/payment-accounts/${row.id}`)}
          actions={[
            {
              label: t("accounts.viewReport"),
              icon: ChartColumn,
              onClick: (row: BankAccount) => router.push(`/payment-accounts/${row.id}`),
            },
            ...(canManage
              ? [
                  {
                    label: tc("actions.delete"),
                    icon: Trash2,
                    destructive: true,
                    onClick: (row: BankAccount) =>
                      confirmDelete(
                        () => handleDelete(row),
                        tc("confirmDelete.named", { name: row.account_name })
                      ),
                  },
                ]
              : []),
          ]}
          emptyMessage={t("accounts.emptyTitle")}
          exportFilename="payment-accounts"
        />
      )}

      {/* Flipping a collection switch can stop live fee collection — confirm. */}
      <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("accounts.confirmToggleTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{pendingMessage}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={toggleWorking}>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              loading={toggleWorking}
              onClick={(e) => {
                e.preventDefault()
                applyToggle()
              }}
            >
              {tc("actions.confirm")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Add/manage sheet (shared with the fees page). */}
      <BankAccountsSheet
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        onChanged={() => load()}
      />
    </div>
  )
}
