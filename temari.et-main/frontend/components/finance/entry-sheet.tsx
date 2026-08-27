"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { Button } from "@/components/ui/button"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { addisToday } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { BankAccount, Expense, ExpenseMethod, FinanceCategory, OtherIncome } from "@/lib/types"
import { cn } from "@/lib/utils"

const METHODS: ExpenseMethod[] = ["cash", "bank_transfer", "wallet", "other"]

const schema = z.object({
  finance_category_id: z.string().min(1, "Category is required"),
  title: z.string().min(2, "Title is required").max(255),
  amount: z
    .string()
    .min(1, "Amount is required")
    .refine((v) => Number(v) > 0, "Amount must be greater than zero"),
  entry_date: z.string().min(1, "Date is required"),
  method: z.enum(["cash", "bank_transfer", "wallet", "other"]),
  bank_account_id: z.string(),
  counterparty: z.string().max(255),
  reference: z.string().max(255),
  note: z.string().max(255),
})

type FormValues = z.infer<typeof schema>

const defaults: FormValues = {
  finance_category_id: "",
  title: "",
  amount: "",
  entry_date: "",
  method: "cash",
  bank_account_id: "",
  counterparty: "",
  reference: "",
  note: "",
}

interface Props {
  /** expense → /finance/expenses (payee, approval); income → /finance/other-incomes (source). */
  kind: "expense" | "income"
  entry?: Expense | OtherIncome | null
  categories: FinanceCategory[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}

/**
 * One sheet for both money-out (expense) and non-fee money-in (other income)
 * rows — identical shape apart from the endpoint, the date field name and
 * the counterparty label (payee vs source).
 */
export function FinanceEntrySheet({ kind, entry, categories, open, onOpenChange, onSaved }: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const isEdit = !!entry
  const isExpense = kind === "expense"

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: defaults })
  useLiveValidation(form)
  const method = form.watch("method")
  const needsAccount = method === "bank_transfer" || method === "wallet"

  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)

  const [accounts, setAccounts] = useState<BankAccount[]>([])
  useEffect(() => {
    if (!open) return
    const branchParam =
      !isEdit && needsBranch ? (branchId != null ? `&branch_id=${branchId}` : null) : ""
    if (branchParam === null) {
      setAccounts([])
      return
    }
    let cancelled = false
    apiFetch<{ data: BankAccount[] }>(`/bank-accounts?usable=1${branchParam}`)
      .then((res) => !cancelled && setAccounts(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [open, isEdit, needsBranch, branchId])

  useEffect(() => {
    if (!open) return
    form.reset(
      entry
        ? {
            finance_category_id: String(entry.finance_category_id),
            title: entry.title,
            amount: String(entry.amount),
            entry_date:
              ("expense_date" in entry ? entry.expense_date : entry.received_on) ?? "",
            method: entry.method,
            bank_account_id: entry.bank_account_id != null ? String(entry.bank_account_id) : "",
            counterparty: ("payee" in entry ? entry.payee : entry.source) ?? "",
            reference: entry.reference ?? "",
            note: entry.note ?? "",
          }
        : defaults,
    )
    setBranchId(null)
    setBranchError(null)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, entry])

  async function onSubmit(values: FormValues) {
    if (!isEdit && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    const endpoint = isExpense ? "/finance/expenses" : "/finance/other-incomes"
    const body = {
      ...(!isEdit && branchId != null ? { branch_id: branchId } : {}),
      finance_category_id: Number(values.finance_category_id),
      title: values.title,
      amount: Number(values.amount),
      [isExpense ? "expense_date" : "received_on"]: values.entry_date,
      method: values.method,
      bank_account_id:
        needsAccount && values.bank_account_id ? Number(values.bank_account_id) : null,
      [isExpense ? "payee" : "source"]: values.counterparty || null,
      reference: values.reference || null,
      note: values.note || null,
    }

    try {
      await apiFetch(isEdit ? `${endpoint}/${entry!.id}` : endpoint, {
        method: isEdit ? "PUT" : "POST",
        body,
      })
      toast.success(
        isEdit
          ? t("books.entrySaved")
          : isExpense
            ? t("books.expenseRecorded")
            : t("books.incomeRecorded"),
      )
      onSaved()
      onOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          const local =
            field === "expense_date" || field === "received_on"
              ? "entry_date"
              : field === "payee" || field === "source"
                ? "counterparty"
                : field
          form.setError(local as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error(tc("errors.generic"))
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit
              ? t(isExpense ? "books.editExpense" : "books.editIncome")
              : t(isExpense ? "books.recordExpense" : "books.recordIncome")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-4">
              {!isEdit && (
                <BranchField
                  value={branchId}
                  onChange={(id) => {
                    setBranchId(id)
                    setBranchError(null)
                  }}
                  error={branchError}
                />
              )}

              <FormField
                control={form.control}
                name="finance_category_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("books.category")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t("books.selectCategory")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {categories.map((category) => (
                          <SelectItem key={category.id} value={String(category.id)}>
                            {category.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="title"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("books.entryTitle")}</FormLabel>
                      <FormControl>
                        <Input {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="amount"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("structures.amount")}</FormLabel>
                      <FormControl>
                        <Input type="number" inputMode="decimal" min={0} {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="entry_date"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>
                        {t(isExpense ? "books.expenseDate" : "books.receivedOn")}
                      </FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          max={addisToday()}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="counterparty"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t(isExpense ? "books.payee" : "books.source")}</FormLabel>
                      <FormControl>
                        <Input
                          placeholder={t(
                            isExpense ? "books.payeePlaceholder" : "books.sourcePlaceholder",
                          )}
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <FormField
                control={form.control}
                name="method"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("payments.method")}</FormLabel>
                    <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                      {METHODS.map((value) => (
                        <button
                          key={value}
                          type="button"
                          onClick={() => field.onChange(value)}
                          className={cn(
                            "pressable min-h-10 rounded-xl border px-2 text-xs font-medium transition-colors",
                            field.value === value
                              ? "border-primary bg-primary/5 text-primary"
                              : "border-border bg-background text-muted-foreground hover:bg-muted",
                          )}
                          aria-pressed={field.value === value}
                        >
                          {t(`methods.${value}`)}
                        </button>
                      ))}
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {needsAccount && accounts.length > 0 && (
                <FormField
                  control={form.control}
                  name="bank_account_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>
                        {t(isExpense ? "books.paidFrom" : "payments.receivedInto")}
                      </FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("payments.selectAccount")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {accounts.map((account) => (
                            <SelectItem key={account.id} value={String(account.id)}>
                              <span className="flex items-center gap-2">
                                <BankLogo bank={account.bank} size={18} />
                                {account.bank?.name} · {account.account_number}
                              </span>
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="reference"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("payments.reference")}</FormLabel>
                      <FormControl>
                        <Input placeholder={t("books.referencePlaceholder")} {...field} />
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
                        <Input {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
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
              <Button type="submit" className="h-11 flex-1" loading={form.formState.isSubmitting}>
                {tc("actions.save")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
