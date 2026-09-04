"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import Image from "next/image"
import { Landmark, Plus, Trash2 } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Badge } from "@/components/ui/badge"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { OptionCombobox } from "@/components/ui/combobox"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { Bank, BankAccount } from "@/lib/types"
import { cn, initials } from "@/lib/utils"

/** Bank/wallet logo with an initials fallback (not every bank ships a logo). */
export function BankLogo({ bank, size = 32 }: { bank: Bank | null; size?: number }) {
  if (bank?.logo) {
    return (
      <span
        className="flex shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-white"
        style={{ width: size, height: size }}
      >
        <Image
          src={`${bank.logo}`}
          alt={bank.name}
          width={size - 8}
          height={size - 8}
          className="object-contain"
        />
      </span>
    )
  }
  return (
    <span
      className="flex shrink-0 items-center justify-center rounded-lg bg-accent text-[10px] font-semibold text-muted-foreground"
      style={{ width: size, height: size }}
    >
      {bank ? initials(bank.name) : "—"}
    </span>
  )
}

const schema = z.object({
  bank_id: z.string().min(1, "Pick a bank"),
  account_name: z.string().min(1, "Account name is required").max(255),
  account_number: z
    .string()
    .min(4, "Account number looks too short")
    .max(30)
    .regex(/^[0-9][0-9 -]*$/, "Digits only (spaces or dashes allowed)"),
})

type FormValues = z.infer<typeof schema>

/**
 * The branch's payment collection accounts. Accounts belong to the SCHOOL so
 * branches can share them; each row shows the school-level switch and THIS
 * branch's own switch (turning the branch off never affects sister branches).
 */
export function BankAccountsSheet({
  open,
  onOpenChange,
  onChanged,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Fired after any change so fee forms can refresh their account options. */
  onChanged?: () => void
}) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [accounts, setAccounts] = useState<BankAccount[] | null>(null)
  const [banks, setBanks] = useState<Bank[]>([])
  const [adding, setAdding] = useState(false)

  // School-wide workspace: a new account still needs one initial branch
  // attachment, so the add form asks for the target branch.
  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { bank_id: "", account_name: "", account_number: "" },
  })
  useLiveValidation(form)

  const load = useCallback(async () => {
    const res = await apiFetch<{ data: BankAccount[] }>("/bank-accounts")
    setAccounts(res.data)
  }, [])

  useEffect(() => {
    if (!open) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- async load, guarded by `cancelled`
    load().catch((error) => {
      if (cancelled) return
      toast.error(error instanceof ApiError ? error.message : t("accounts.loadFailed"))
      setAccounts([])
    })
    if (banks.length === 0) {
      apiFetch<{ data: Bank[] }>("/banks")
        .then((res) => !cancelled && setBanks(res.data))
        .catch(() => {})
    }
    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  async function submit(values: FormValues) {
    if (needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    try {
      await apiFetch("/bank-accounts", {
        method: "POST",
        body: {
          ...values,
          bank_id: Number(values.bank_id),
          ...(branchId != null ? { branch_id: branchId } : {}),
        },
      })
      toast.success(t("accounts.created"))
      form.reset({ bank_id: "", account_name: "", account_number: "" })
      setBranchId(null)
      setAdding(false)
      await load()
      onChanged?.()
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    }
  }

  async function toggle(account: BankAccount, field: "branch_active" | "is_active", value: boolean) {
    try {
      await apiFetch(`/bank-accounts/${account.id}`, { method: "PUT", body: { [field]: value } })
      await load()
      onChanged?.()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("accounts.updateFailed"))
    }
  }

  async function remove(account: BankAccount) {
    try {
      await apiFetch(`/bank-accounts/${account.id}`, { method: "DELETE" })
      toast.success(t("accounts.removed"))
      await load()
      onChanged?.()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("accounts.updateFailed"))
    }
  }

  const bankGroups: { label: string; items: Bank[] }[] = [
    { label: t("accounts.banksGroup"), items: banks.filter((b) => b.type === "bank") },
    { label: t("accounts.walletsGroup"), items: banks.filter((b) => b.type === "wallet") },
  ]

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        {confirmDialog}
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("accounts.title")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <ResponsiveSheetBody className="space-y-4">
          <p className="text-xs text-muted-foreground">{t("accounts.hint")}</p>

          {accounts === null ? (
            <>
              <Skeleton className="h-20 rounded-2xl" />
              <Skeleton className="h-20 rounded-2xl" />
            </>
          ) : accounts.length === 0 && !adding ? (
            <EmptyState
              icon={Landmark}
              title={t("accounts.emptyTitle")}
              description={t("accounts.emptyDescription")}
            />
          ) : (
            accounts.map((account) => (
              <div
                key={account.id}
                className={cn(
                  "space-y-3 rounded-2xl border p-3",
                  !account.is_active && "opacity-70",
                )}
              >
                <div className="flex items-center gap-2.5">
                  <BankLogo bank={account.bank} size={36} />
                  <div className="min-w-0 flex-1 leading-tight">
                    <p className="truncate text-sm font-medium">{account.account_name}</p>
                    <p className="text-xs text-muted-foreground tabular-nums">
                      {account.bank?.name} · {account.account_number}
                    </p>
                  </div>
                  {account.bank?.type === "wallet" && (
                    <Badge variant="outline" className="text-[11px]">
                      {t("accounts.wallet")}
                    </Badge>
                  )}
                  <Button
                    variant="ghost"
                    size="icon"
                    className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                    onClick={() =>
                      confirmDelete(
                        () => remove(account),
                        tc("confirmDelete.named", { name: account.account_name }),
                      )
                    }
                    aria-label={tc("actions.delete")}
                  >
                    <Trash2 className="size-4" />
                  </Button>
                </div>

                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                  {/* This branch's switch — sister branches keep their own.
                      Hidden school-wide: there is no "this branch" there. */}
                  {!needsBranch && (
                    <label className="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-muted/30 px-3 py-2">
                      <span className="text-xs font-medium">{t("accounts.activeForBranch")}</span>
                      <Switch
                        checked={account.attached_to_branch && account.branch_active === true}
                        onCheckedChange={(v) => toggle(account, "branch_active", v)}
                      />
                    </label>
                  )}
                  {/* School-level switch — kills the account everywhere. */}
                  <label className="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-muted/30 px-3 py-2">
                    <span className="text-xs font-medium">{t("accounts.activeForSchool")}</span>
                    <Switch
                      checked={account.is_active}
                      onCheckedChange={(v) => toggle(account, "is_active", v)}
                    />
                  </label>
                </div>

                {account.branches.length > 1 && (
                  <p className="text-xs text-muted-foreground">
                    {t("accounts.sharedWith", {
                      branches: account.branches.map((b) => b.name).join(", "),
                    })}
                  </p>
                )}
              </div>
            ))
          )}

          {adding ? (
            <Form {...form}>
              <form
                onSubmit={form.handleSubmit(submit)}
                className="space-y-3 rounded-2xl border border-dashed p-3"
              >
                <BranchField
                  value={branchId}
                  onChange={(id) => {
                    setBranchId(id)
                    setBranchError(null)
                  }}
                  error={branchError}
                />
                <FormField
                  control={form.control}
                  name="bank_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("accounts.bank")}</FormLabel>
                      <FormControl>
                        {/* ~34 banks + wallets — searchable, wallets last. */}
                        <OptionCombobox
                          options={bankGroups.flatMap((group) =>
                            group.items.map((bank) => ({
                              value: String(bank.id),
                              label: bank.type === "wallet" ? `${bank.name} · ${t("accounts.wallet")}` : bank.name,
                              leading: <BankLogo bank={bank} size={20} />,
                            })),
                          )}
                          value={field.value}
                          onChange={field.onChange}
                          placeholder={t("accounts.bankPlaceholder")}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <FormField
                    control={form.control}
                    name="account_name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("accounts.accountName")}</FormLabel>
                        <FormControl>
                          <Input placeholder={t("accounts.accountNamePlaceholder")} {...field} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="account_number"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("accounts.accountNumber")}</FormLabel>
                        <FormControl>
                          <Input
                            inputMode="numeric"
                            placeholder={t("accounts.accountNumberPlaceholder")}
                            {...field}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-9 flex-1"
                    onClick={() => setAdding(false)}
                  >
                    {tc("actions.cancel")}
                  </Button>
                  <Button
                    type="submit"
                    size="sm"
                    className="h-9 flex-1"
                    loading={form.formState.isSubmitting}
                  >
                    {t("accounts.addCta")}
                  </Button>
                </div>
              </form>
            </Form>
          ) : (
            <button
              type="button"
              onClick={() => setAdding(true)}
              className="pressable flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-dashed text-sm font-medium text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary"
            >
              <Plus className="size-4" />
              {t("accounts.add")}
            </button>
          )}
        </ResponsiveSheetBody>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
