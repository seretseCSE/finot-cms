"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Banknote, Check, CircleDashed, Copy, Landmark, Wallet } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { Button } from "@/components/ui/button"
import { CopyableId, copyText } from "@/components/ui/copyable-id"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { DatePicker } from "@/components/ui/date-picker"
import { Input } from "@/components/ui/input"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { ApiError, apiFetch } from "@/lib/api"
import { addisToday } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { BankAccount, Invoice, Payment, PaymentMethod } from "@/lib/types"
import { cn } from "@/lib/utils"

const schema = z.object({
  amount: z.string().min(1, "Amount is required"),
  method: z.enum(["wallet", "bank_transfer", "cash", "other"]),
  bank_account_id: z.string().optional(),
  reference: z.string().max(255).optional(),
  paid_at: z.string().optional(),
  note: z.string().max(255).optional(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  invoice: Invoice | null
  onRecorded: (invoice: Invoice) => void
  open: boolean
  onOpenChange: (open: boolean) => void
}

/** The channel type an account implies — wallets are wallets, the rest banks. */
function methodForAccount(account: BankAccount): PaymentMethod {
  return account.bank?.type === "wallet" ? "wallet" : "bank_transfer"
}

/** Tap-to-copy account number chip used inside each account row. */
function AccountNumber({ number }: { number: string }) {
  const [copied, setCopied] = useState(false)

  return (
    <button
      type="button"
      onClick={async (e) => {
        e.stopPropagation()
        if (await copyText(number)) {
          setCopied(true)
          setTimeout(() => setCopied(false), 1600)
        }
      }}
      className="inline-flex min-w-0 items-center gap-1 rounded-md bg-muted/60 px-1.5 py-0.5 font-mono text-xs tabular-nums text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
    >
      <span className="truncate">{number}</span>
      {copied ? (
        <Check className="size-3 shrink-0 text-success" />
      ) : (
        <Copy className="size-3 shrink-0 opacity-60" />
      )}
    </button>
  )
}

/** One selectable "where the money landed" row (account, cash or other). */
function ChannelRow({
  selected,
  onSelect,
  icon,
  title,
  subtitle,
  trailing,
}: {
  selected: boolean
  onSelect: () => void
  icon: React.ReactNode
  title: string
  subtitle?: string | null
  trailing?: React.ReactNode
}) {
  return (
    <div
      role="radio"
      aria-checked={selected}
      tabIndex={0}
      onClick={onSelect}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault()
          onSelect()
        }
      }}
      className={cn(
        "flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors",
        selected
          ? "border-primary bg-primary/5"
          : "bg-background hover:bg-muted/50",
      )}
    >
      {icon}
      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-medium">{title}</span>
        {subtitle ? (
          <span className="block truncate text-xs text-muted-foreground">{subtitle}</span>
        ) : null}
      </span>
      {trailing}
      <span
        className={cn(
          "flex size-4.5 shrink-0 items-center justify-center rounded-full border transition-colors",
          selected ? "border-primary bg-primary text-primary-foreground" : "border-border",
        )}
        aria-hidden
      >
        {selected && <Check className="size-3" />}
      </span>
    </div>
  )
}

export function RecordPaymentSheet({
  invoice,
  onRecorded,
  open,
  onOpenChange,
}: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")

  // Every account the invoice's branch can take money on right now — the
  // fee's own collection accounts are listed first, the rest under "other"
  // (edge cases where money landed somewhere unusual).
  const [accounts, setAccounts] = useState<BankAccount[]>([])

  const collectionIds = useMemo(
    () => new Set((invoice?.collection_accounts ?? []).map((a) => a.id)),
    [invoice],
  )

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      amount: "",
      method: "cash",
      bank_account_id: "",
      reference: "",
      paid_at: today(),
      note: "",
    },
  })
  useLiveValidation(form)

  const method = form.watch("method")
  const selectedAccountId = form.watch("bank_account_id")

  useEffect(() => {
    if (!open || !invoice) return
    let cancelled = false
    // The INVOICE anchors the branch — school-wide staff pay any branch.
    apiFetch<{ data: BankAccount[] }>(`/bank-accounts?usable=1&branch_id=${invoice.branch_id ?? ""}`)
      .then((res) => !cancelled && setAccounts(res.data))
      .catch(() => !cancelled && setAccounts([]))
    return () => {
      cancelled = true
    }
  }, [open, invoice])

  useEffect(() => {
    if (open && invoice) {
      const sole =
        invoice.collection_accounts?.length === 1
          ? invoice.collection_accounts[0]
          : null
      form.reset({
        amount: invoice.balance,
        // A sole collection account is the overwhelmingly common case —
        // preselect it and derive its channel type; otherwise start at cash.
        method: sole ? (sole.bank_type === "wallet" ? "wallet" : "bank_transfer") : "cash",
        bank_account_id: sole ? String(sole.id) : "",
        reference: "",
        paid_at: today(),
        note: "",
      })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset on open only
  }, [open, invoice])

  const feeAccounts = accounts.filter((a) => collectionIds.has(a.id))
  const otherAccounts = accounts.filter((a) => !collectionIds.has(a.id))

  function selectAccount(account: BankAccount) {
    form.setValue("bank_account_id", String(account.id), { shouldValidate: true })
    form.setValue("method", methodForAccount(account))
    form.clearErrors("bank_account_id")
  }

  function selectPlain(next: PaymentMethod) {
    form.setValue("method", next)
    form.setValue("bank_account_id", "")
    form.clearErrors("bank_account_id")
  }

  async function onSubmit(values: FormValues) {
    if (!invoice) return
    const needsAccount = values.method === "wallet" || values.method === "bank_transfer"
    if (needsAccount && accounts.length > 0 && !values.bank_account_id) {
      form.setError("bank_account_id", { type: "required", message: t("payments.accountRequired") })
      return
    }
    try {
      const res = await apiFetch<{ data: Payment; meta: { invoice: Invoice } }>(
        `/invoices/${invoice.id}/payments`,
        {
          method: "POST",
          body: {
            amount: Number(values.amount),
            method: values.method,
            bank_account_id:
              needsAccount && values.bank_account_id
                ? Number(values.bank_account_id)
                : undefined,
            reference: values.reference || undefined,
            paid_at: values.paid_at || undefined,
            note: values.note || undefined,
          },
        }
      )
      toast.success(t("payments.recorded"))
      onRecorded(res.meta.invoice)
      onOpenChange(false)
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

  function accountRow(account: BankAccount) {
    const selected = selectedAccountId === String(account.id)
    return (
      <ChannelRow
        key={account.id}
        selected={selected}
        onSelect={() => selectAccount(account)}
        icon={<BankLogo bank={account.bank} size={32} />}
        title={account.bank?.name ?? account.account_name}
        subtitle={account.bank ? account.account_name : null}
        trailing={<AccountNumber number={account.account_number} />}
      />
    )
  }

  const accountError = form.formState.errors.bank_account_id?.message

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("payments.title")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit)}
            className="flex h-full flex-col"
          >
            <ResponsiveSheetBody className="space-y-5">
              {invoice && (
                <div className="rounded-xl border p-3 text-sm">
                  <div className="flex items-center justify-between gap-3">
                    <p className="min-w-0 truncate font-medium">{invoice.title}</p>
                    <CopyableId value={invoice.number} />
                  </div>
                  <p className="text-muted-foreground">
                    {invoice.student_name} · {t("payments.balanceDue")}:{" "}
                    {invoice.balance} ETB
                  </p>
                </div>
              )}
              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="amount"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("payments.amount")}</FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          inputMode="decimal"
                          min={0}
                          placeholder={t("payments.amountPlaceholder")}
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="paid_at"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("payments.paidAt")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          max={today()}
                          clearable={false}
                          placeholder={t("payments.paidAt")}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              {/* Where the money landed — one picker for both the channel and
                  the account. Picking an account derives wallet/bank transfer
                  from the bank catalog; cash and other never take an account. */}
              <div role="radiogroup" aria-label={t("payments.channel")} className="space-y-2">
                <p className="text-sm font-medium">{t("payments.channel")}</p>

                {feeAccounts.length > 0 && (
                  <div className="space-y-1.5">
                    <p className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                      <Landmark className="size-3.5" />
                      {t("payments.feeAccounts")}
                    </p>
                    {feeAccounts.map(accountRow)}
                  </div>
                )}

                {otherAccounts.length > 0 && (
                  <div className="space-y-1.5">
                    <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                      {feeAccounts.length > 0
                        ? t("payments.otherAccounts")
                        : t("payments.branchAccounts")}
                    </p>
                    {otherAccounts.map(accountRow)}
                  </div>
                )}

                {/* No accounts anywhere: wallet / bank transfer stay pickable
                    as bare channels so the payment can still be recorded. */}
                {accounts.length === 0 && (
                  <>
                    <ChannelRow
                      selected={method === "wallet"}
                      onSelect={() => selectPlain("wallet")}
                      icon={<Wallet className="size-5 text-muted-foreground" />}
                      title={t("methods.wallet")}
                    />
                    <ChannelRow
                      selected={method === "bank_transfer"}
                      onSelect={() => selectPlain("bank_transfer")}
                      icon={<Landmark className="size-5 text-muted-foreground" />}
                      title={t("methods.bank_transfer")}
                    />
                  </>
                )}

                <div className="grid grid-cols-2 gap-2">
                  <ChannelRow
                    selected={method === "cash"}
                    onSelect={() => selectPlain("cash")}
                    icon={<Banknote className="size-5 text-muted-foreground" />}
                    title={t("methods.cash")}
                  />
                  <ChannelRow
                    selected={method === "other"}
                    onSelect={() => selectPlain("other")}
                    icon={<CircleDashed className="size-5 text-muted-foreground" />}
                    title={t("methods.other")}
                  />
                </div>

                {accountError && (
                  <p className="text-sm text-destructive">{accountError}</p>
                )}
              </div>

              <FormField
                control={form.control}
                name="reference"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("payments.reference")}</FormLabel>
                    <FormControl>
                      <Input
                        placeholder={t("payments.referencePlaceholder")}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="note"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("payments.note")}</FormLabel>
                    <FormControl>
                      <Input
                        placeholder={t("payments.notePlaceholder")}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1"
                onClick={() => onOpenChange(false)}
              >
                {tc("actions.cancel")}
              </Button>
              <Button
                type="submit"
                className="h-11 flex-1"
                loading={form.formState.isSubmitting}
              >
                {t("invoices.recordPayment")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

function today(): string {
  return addisToday()
}
