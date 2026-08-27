"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import {
  AlarmClock,
  BellRing,
  CalendarClock,
  CalendarDays,
  CalendarRange,
  ClipboardPen,
  Layers,
  Plus,
  Repeat,
  Send,
  Wallet,
  type LucideIcon,
} from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
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
  ResponsiveSheetTrigger,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { BankAccountsSheet, BankLogo } from "@/components/fees/bank-accounts-sheet"
import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { AcademicYear, BankAccount, FeeStructure, FeeType, GradeLevel, Paginated } from "@/lib/types"
import { cn } from "@/lib/utils"

const FEE_TYPES: { value: FeeType; icon: LucideIcon }[] = [
  { value: "registration", icon: ClipboardPen },
  { value: "one_time", icon: Wallet },
  { value: "daily", icon: AlarmClock },
  { value: "weekly", icon: CalendarClock },
  { value: "monthly", icon: CalendarDays },
  { value: "quarterly", icon: CalendarRange },
  { value: "semester", icon: Layers },
  { value: "yearly", icon: Repeat },
]

const schema = z
  .object({
    academic_year_id: z.string().min(1, "Academic year is required"),
    name: z.string().min(2, "Name is required").max(255),
    type: z.enum([
      "registration",
      "one_time",
      "daily",
      "weekly",
      "monthly",
      "quarterly",
      "semester",
      "yearly",
    ]),
    amount: z
      .string()
      .min(1, "Amount is required")
      .refine((v) => Number(v) > 0, "Amount must be greater than zero"),
    bank_account_ids: z.array(z.number()),
    all_grades: z.boolean(),
    grade_level_ids: z.array(z.number()),
    starts_on: z.string().optional(),
    due_on: z.string().optional(),
    billing_day: z.string().optional(),
    auto_generate: z.boolean(),
    notify_parents: z.boolean(),
    notify_students: z.boolean(),
    penalty_type: z.enum(["none", "fixed", "incremental"]),
    penalty_amount: z.string().optional(),
    penalty_increment_days: z.string().optional(),
  })
  .refine((v) => v.all_grades || v.grade_level_ids.length > 0, {
    message: "Pick at least one grade or apply to all",
    path: ["grade_level_ids"],
  })
  .refine((v) => v.penalty_type === "none" || (v.penalty_amount ?? "") !== "", {
    message: "Penalty amount is required",
    path: ["penalty_amount"],
  })
  .refine(
    (v) => v.penalty_type !== "incremental" || (v.penalty_increment_days ?? "") !== "",
    { message: "How many days per increment?", path: ["penalty_increment_days"] },
  )

type FormValues = z.infer<typeof schema>

const defaults: FormValues = {
  academic_year_id: "",
  name: "",
  type: "one_time",
  amount: "",
  bank_account_ids: [],
  all_grades: true,
  grade_level_ids: [],
  starts_on: "",
  due_on: "",
  billing_day: "",
  auto_generate: false,
  notify_parents: false,
  notify_students: false,
  penalty_type: "none",
  penalty_amount: "",
  penalty_increment_days: "",
}

interface Props {
  feeStructure?: FeeStructure | null
  academicYears: AcademicYear[]
  /** Pin the fee to one year (academic year detail page) — hides the picker. */
  fixedYearId?: number
  gradeLevels: GradeLevel[]
  onSaved: (fee: FeeStructure) => void
  open?: boolean
  onOpenChange?: (open: boolean) => void
  showTrigger?: boolean
}

export function FeeStructureSheet({
  feeStructure,
  academicYears,
  fixedYearId,
  gradeLevels,
  onSaved,
  open,
  onOpenChange,
  showTrigger,
}: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const isEdit = !!feeStructure

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: defaults })
  useLiveValidation(form)
  const type = useWatch({ control: form.control, name: "type" })
  const allGrades = useWatch({ control: form.control, name: "all_grades" })
  const penaltyType = useWatch({ control: form.control, name: "penalty_type" })
  const startsOn = useWatch({ control: form.control, name: "starts_on" })

  const isRegistration = type === "registration"
  // Monthly/quarterly fees run through the recurring engine (Ethiopian-month
  // billing periods) — they grow an auto-generate switch and a due day.
  const isRecurring = type === "monthly" || type === "quarterly"
  const yearId = useWatch({ control: form.control, name: "academic_year_id" })

  // School-wide workspace: creating requires naming the target branch; years
  // and usable accounts then follow that branch instead of the context.
  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [branchYears, setBranchYears] = useState<AcademicYear[]>([])
  const years = needsBranch && !isEdit ? branchYears : academicYears

  useEffect(() => {
    if (!open || isEdit || !needsBranch || branchId == null) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- no branch, no years
      setBranchYears([])
      return
    }
    let cancelled = false
    apiFetch<Paginated<AcademicYear>>(`/academic-years?branch_id=${branchId}&per_page=100`)
      .then((res) => {
        if (cancelled) return
        setBranchYears(res.data)
        if (!form.getValues("academic_year_id")) {
          const current = res.data.find((y) => y.is_current) ?? res.data[0]
          if (current) form.setValue("academic_year_id", String(current.id))
        }
      })
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [open, isEdit, needsBranch, branchId, form])

  // Bill everyone the fee applies to right after saving (create mode only) —
  // the same idempotent fan-out as the row's "Generate invoices" action.
  const [issueNow, setIssueNow] = useState(false)

  // Collection accounts usable by the target branch (both switches on).
  const [accounts, setAccounts] = useState<BankAccount[]>([])
  const [accountsOpen, setAccountsOpen] = useState(false)
  const loadAccounts = () => {
    if (needsBranch && branchId == null) {
      setAccounts([])
      return Promise.resolve()
    }
    const branchParam = branchId != null ? `&branch_id=${branchId}` : ""
    return apiFetch<{ data: BankAccount[] }>(`/bank-accounts?usable=1${branchParam}`)
      .then((res) => setAccounts(res.data))
      .catch(() => {})
  }
  useEffect(() => {
    if (!open) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reload accounts on scope change
    loadAccounts()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, needsBranch, branchId])

  // Picking any type pre-fills a sensible name ("Monthly fee — 2017 E.C.",
  // "Registration fee — 2017 E.C.", …) that keeps following the selected type
  // and year until the user types their own.
  const lastAutoName = useRef<string>("")
  useEffect(() => {
    if (!open || isEdit || !type) return
    const yearName = years.find((y) => String(y.id) === yearId)?.name ?? ""
    const typeLabel = t(`types.${type}`)
    const auto = yearName
      ? t("structures.autoName", { type: typeLabel, year: yearName })
      : t("structures.autoNameNoYear", { type: typeLabel })
    const current = form.getValues("name")
    if (current === "" || current === lastAutoName.current) {
      form.setValue("name", auto)
      lastAutoName.current = auto
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [type, yearId, open, isEdit])

  useEffect(() => {
    if (!open) return
    const currentYear = academicYears.find((y) => y.is_current)
    form.reset(
      feeStructure
        ? {
            academic_year_id: String(feeStructure.academic_year_id),
            name: feeStructure.name,
            type: feeStructure.type,
            amount: String(feeStructure.amount),
            bank_account_ids: (feeStructure.bank_accounts ?? []).map((a) => a.id),
            all_grades: (feeStructure.grade_levels?.length ?? 0) === 0,
            grade_level_ids: (feeStructure.grade_levels ?? []).map((g) => g.id),
            starts_on: feeStructure.starts_on ?? "",
            due_on: feeStructure.due_on ?? "",
            billing_day: feeStructure.billing_day ? String(feeStructure.billing_day) : "",
            auto_generate: feeStructure.auto_generate ?? false,
            notify_parents: feeStructure.notify_parents,
            notify_students: feeStructure.notify_students,
            penalty_type: feeStructure.penalty_type ?? "none",
            penalty_amount: feeStructure.penalty_amount ? String(feeStructure.penalty_amount) : "",
            penalty_increment_days: feeStructure.penalty_increment_days
              ? String(feeStructure.penalty_increment_days)
              : "",
          }
        : (() => {
            // Seed the auto-generated name straight away so create mode opens
            // with "One-time fee — 2017 E.C." already in place.
            const yearIdValue = fixedYearId
              ? String(fixedYearId)
              : currentYear
                ? String(currentYear.id)
                : ""
            const yearName = academicYears.find((y) => String(y.id) === yearIdValue)?.name ?? ""
            const typeLabel = t(`types.${defaults.type}`)
            const auto = yearName
              ? t("structures.autoName", { type: typeLabel, year: yearName })
              : t("structures.autoNameNoYear", { type: typeLabel })
            lastAutoName.current = auto
            return { ...defaults, academic_year_id: yearIdValue, name: auto }
          })(),
    )
    // eslint-disable-next-line react-hooks/set-state-in-effect -- seed sheet state on open
    setBranchId(null)
    setBranchError(null)
    setIssueNow(false)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, feeStructure, fixedYearId])

  function close() {
    onOpenChange?.(false)
    if (!isEdit) form.reset(defaults)
  }

  async function onSubmit(values: FormValues) {
    if (!isEdit && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    const scheduled = values.type !== "registration"
    // Guard: if the row we're editing arrived WITHOUT its accounts loaded,
    // never send the (empty-looking) selection — it would wipe the fee's
    // accounts server-side. The backend only syncs when the key is present.
    const accountsKnown = !isEdit || feeStructure?.bank_accounts !== undefined
    const body = {
      ...(!isEdit && branchId != null ? { branch_id: branchId } : {}),
      name: values.name,
      type: values.type,
      amount: Number(values.amount),
      // Where payments on this fee may land (branch-enabled accounts).
      ...(accountsKnown ? { bank_account_ids: values.bank_account_ids } : {}),
      grade_level_ids: values.all_grades ? [] : values.grade_level_ids,
      ...(scheduled
        ? {
            starts_on: values.starts_on || null,
            due_on: values.due_on || null,
            billing_day: values.billing_day ? Number(values.billing_day) : null,
            auto_generate: values.auto_generate,
            notify_parents: values.notify_parents,
            notify_students: values.notify_students,
            penalty_type: values.penalty_type === "none" ? null : values.penalty_type,
            penalty_amount:
              values.penalty_type !== "none" && values.penalty_amount
                ? Number(values.penalty_amount)
                : null,
            penalty_increment_days:
              values.penalty_type === "incremental" && values.penalty_increment_days
                ? Number(values.penalty_increment_days)
                : null,
          }
        : {}),
      ...(isEdit ? {} : { academic_year_id: Number(values.academic_year_id) }),
    }

    try {
      const res = await apiFetch<{ data: FeeStructure }>(
        isEdit ? `/fee-structures/${feeStructure!.id}` : "/fee-structures",
        { method: isEdit ? "PUT" : "POST", body },
      )
      toast.success(isEdit ? t("structures.updated") : t("structures.created"))

      // Optional immediate billing run — idempotent, so a retry from the fee
      // row can safely fill any gap if this call fails.
      if (!isEdit && issueNow) {
        try {
          const generated = await apiFetch<{ meta: { created: number } }>(
            `/fee-structures/${res.data.id}/generate-invoices`,
            { method: "POST", body: {} },
          )
          toast.success(t("structures.generated", { count: generated.meta.created }))
        } catch (error) {
          toast.error(
            error instanceof ApiError ? error.message : t("structures.issueNowFailed"),
          )
        }
      }

      onSaved(res.data)
      close()
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

  return (
    <ResponsiveSheet open={open} onOpenChange={(v) => (v ? onOpenChange?.(true) : close())}>
      {showTrigger && (
        <ResponsiveSheetTrigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("structures.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-2xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("structures.editTitle") : t("structures.createTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              {/* Invoices snapshot the fee at generation time — editing the
                  amount or schedule only shapes invoices issued from now on. */}
              {isEdit && (feeStructure?.invoices_count ?? 0) > 0 && (
                <div className="rounded-xl border border-info/30 bg-info/10 px-3.5 py-2.5 text-xs leading-relaxed text-info">
                  {t("structures.editBilledHint", {
                    count: feeStructure?.invoices_count ?? 0,
                  })}
                </div>
              )}
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
              {/* 1 · What kind of fee? */}
              <FormField
                control={form.control}
                name="type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("structures.type")}</FormLabel>
                    <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                      {FEE_TYPES.map(({ value, icon: Icon }) => {
                        const selected = field.value === value
                        return (
                          <button
                            key={value}
                            type="button"
                            onClick={() => field.onChange(value)}
                            className={cn(
                              "pressable flex min-h-11 flex-col items-center justify-center gap-1 rounded-xl border px-2 py-2.5 text-xs font-medium transition-colors",
                              selected
                                ? "border-primary bg-primary/5 text-primary"
                                : "border-border bg-background text-muted-foreground hover:bg-muted",
                            )}
                            aria-pressed={selected}
                          >
                            <Icon className="size-4" />
                            {t(`types.${value}`)}
                          </button>
                        )
                      })}
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {t(`typeHints.${type}`)}
                    </p>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {/* 2 · Basics */}
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("structures.name")}</FormLabel>
                      <FormControl>
                        <Input placeholder={t("structures.namePlaceholder")} {...field} />
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
                        <Input
                          type="number"
                          inputMode="decimal"
                          min={0}
                          placeholder={t("structures.amountPlaceholder")}
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              {/* Collection accounts — where payments on this fee may land.
                  Toggle one or many. The + opens the accounts manager so new
                  accounts can be added without leaving the form. */}
              <FormField
                control={form.control}
                name="bank_account_ids"
                render={({ field }) => (
                  <FormItem>
                    <div className="flex items-center justify-between gap-2">
                      <FormLabel>{t("structures.bankAccount")}</FormLabel>
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-9 shrink-0 rounded-xl"
                        onClick={() => setAccountsOpen(true)}
                        aria-label={t("accounts.add")}
                      >
                        <Plus className="size-4" />
                      </Button>
                    </div>
                    {accounts.length === 0 ? (
                      <p className="text-sm text-muted-foreground">
                        {t("structures.noBankAccount")}
                      </p>
                    ) : (
                      <div className="flex flex-wrap gap-1.5">
                        {accounts.map((account) => {
                          const selected = field.value.includes(account.id)
                          return (
                            <button
                              key={account.id}
                              type="button"
                              onClick={() =>
                                field.onChange(
                                  selected
                                    ? field.value.filter((id) => id !== account.id)
                                    : [...field.value, account.id],
                                )
                              }
                              className={cn(
                                "pressable flex min-h-11 items-center gap-2 rounded-xl border px-3 text-xs font-medium transition-colors",
                                selected
                                  ? "border-primary bg-primary/5 text-primary"
                                  : "border-border bg-background text-muted-foreground hover:bg-muted",
                              )}
                              aria-pressed={selected}
                            >
                              <BankLogo bank={account.bank} size={20} />
                              <span className="truncate">
                                {account.bank?.name} · {account.account_number}
                              </span>
                            </button>
                          )
                        })}
                      </div>
                    )}
                    <p className="text-xs text-muted-foreground">
                      {t("structures.bankAccountHint")}
                    </p>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {!isEdit && !fixedYearId && (
                <FormField
                  control={form.control}
                  name="academic_year_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("structures.academicYear")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("structures.selectYear")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent emptyNotice={tc("emptySelect.years")}>
                          {years.map((y) => (
                            <SelectItem key={y.id} value={String(y.id)}>
                              <span className="flex items-center gap-2">
                                {y.name}
                                {y.is_current && (
                                  <Badge className="px-1.5 py-0 text-[11px]">
                                    {t("structures.currentYear")}
                                  </Badge>
                                )}
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

              {/* 3 · Who pays it */}
              <FormField
                control={form.control}
                name="all_grades"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between gap-3 rounded-xl border bg-muted/30 px-3 py-2.5">
                    <div>
                      <FormLabel className="!mt-0 font-normal">
                        {t("structures.allGrades")}
                      </FormLabel>
                      <p className="text-xs text-muted-foreground">
                        {t("structures.allGradesHint")}
                      </p>
                    </div>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              {!allGrades && (
                <FormField
                  control={form.control}
                  name="grade_level_ids"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("structures.applicableGrades")}</FormLabel>
                      <div className="flex flex-wrap gap-1.5">
                        {gradeLevels.map((g) => {
                          const selected = field.value.includes(g.id)
                          return (
                            <button
                              key={g.id}
                              type="button"
                              onClick={() =>
                                field.onChange(
                                  selected
                                    ? field.value.filter((id) => id !== g.id)
                                    : [...field.value, g.id],
                                )
                              }
                              className={cn(
                                "pressable min-h-9 rounded-full border px-3 text-xs font-medium transition-colors",
                                selected
                                  ? "border-primary bg-primary text-primary-foreground"
                                  : "border-border bg-background text-muted-foreground hover:bg-muted",
                              )}
                              aria-pressed={selected}
                            >
                              {g.name}
                            </button>
                          )
                        })}
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}

              {/* Bill immediately? Off by default — fees are policies, invoices
                  are issued deliberately (a typo here must not fan out). */}
              {!isEdit && (
                <div className="flex flex-row items-center justify-between gap-3 rounded-xl border bg-muted/30 px-3 py-2.5">
                  <div>
                    <p className="flex items-center gap-1.5 text-sm font-medium">
                      <Send className="size-3.5" />
                      {t("structures.issueNow")}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {t("structures.issueNowHint")}
                    </p>
                  </div>
                  <Switch checked={issueNow} onCheckedChange={setIssueNow} />
                </div>
              )}

              {/* 4 · Billing window + reminders + penalty — everything but registration */}
              {!isRegistration && (
                <>
                  <div className="grid grid-cols-2 gap-3">
                    <FormField
                      control={form.control}
                      name="starts_on"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>
                            {t(isRecurring ? "structures.windowStart" : "structures.startsOn")}
                          </FormLabel>
                          <FormControl>
                            <DatePicker
                              value={field.value}
                              onChange={field.onChange}
                              onBlur={field.onBlur}
                              placeholder={t("structures.startsOn")}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="due_on"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>
                            {t(isRecurring ? "structures.windowEnd" : "structures.dueOn")}
                          </FormLabel>
                          <FormControl>
                            <DatePicker
                              value={field.value}
                              onChange={field.onChange}
                              onBlur={field.onBlur}
                              min={startsOn || undefined}
                              placeholder={t("structures.dueOn")}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>

                  {isRecurring && (
                    <div className="space-y-3 rounded-xl border bg-muted/30 p-3">
                      <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <Repeat className="size-3.5" />
                        {t("structures.recurring")}
                      </p>
                      <FormField
                        control={form.control}
                        name="auto_generate"
                        render={({ field }) => (
                          <FormItem className="flex flex-row items-center justify-between gap-3">
                            <div>
                              <FormLabel className="!mt-0 font-normal">
                                {t("structures.autoGenerate")}
                              </FormLabel>
                              <p className="text-xs text-muted-foreground">
                                {t("structures.autoGenerateHint")}
                              </p>
                            </div>
                            <FormControl>
                              <Switch checked={field.value} onCheckedChange={field.onChange} />
                            </FormControl>
                          </FormItem>
                        )}
                      />
                      <FormField
                        control={form.control}
                        name="billing_day"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel>{t("structures.billingDay")}</FormLabel>
                            <FormControl>
                              <Input
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={30}
                                placeholder="10"
                                className="no-spinner"
                                {...field}
                              />
                            </FormControl>
                            <p className="text-xs text-muted-foreground">
                              {t("structures.billingDayHint")}
                            </p>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                    </div>
                  )}

                  <div className="space-y-2 rounded-xl border bg-muted/30 p-3">
                    <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                      <BellRing className="size-3.5" />
                      {t("structures.notifications")}
                    </p>
                    <FormField
                      control={form.control}
                      name="notify_parents"
                      render={({ field }) => (
                        <FormItem className="flex flex-row items-center justify-between gap-3">
                          <FormLabel className="!mt-0 font-normal">
                            {t("structures.notifyParents")}
                          </FormLabel>
                          <FormControl>
                            <Switch checked={field.value} onCheckedChange={field.onChange} />
                          </FormControl>
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="notify_students"
                      render={({ field }) => (
                        <FormItem className="flex flex-row items-center justify-between gap-3">
                          <FormLabel className="!mt-0 font-normal">
                            {t("structures.notifyStudents")}
                          </FormLabel>
                          <FormControl>
                            <Switch checked={field.value} onCheckedChange={field.onChange} />
                          </FormControl>
                        </FormItem>
                      )}
                    />
                  </div>

                  <FormField
                    control={form.control}
                    name="penalty_type"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("penalty.title")}</FormLabel>
                        <div className="grid grid-cols-3 gap-1.5">
                          {(["none", "fixed", "incremental"] as const).map((value) => (
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
                              {t(`penalty.${value}`)}
                            </button>
                          ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                          {t(`penalty.hints.${penaltyType}`)}
                        </p>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  {penaltyType !== "none" && (
                    <div className="grid grid-cols-2 gap-3">
                      <FormField
                        control={form.control}
                        name="penalty_amount"
                        render={({ field }) => (
                          <FormItem>
                            <FormLabel>{t("penalty.amount")}</FormLabel>
                            <FormControl>
                              <Input
                                type="number"
                                inputMode="decimal"
                                min={0}
                                placeholder={t("structures.amountPlaceholder")}
                                {...field}
                              />
                            </FormControl>
                            <FormMessage />
                          </FormItem>
                        )}
                      />
                      {penaltyType === "incremental" && (
                        <FormField
                          control={form.control}
                          name="penalty_increment_days"
                          render={({ field }) => (
                            <FormItem>
                              <FormLabel>{t("penalty.incrementDays")}</FormLabel>
                              <FormControl>
                                <Input
                                  type="number"
                                  inputMode="numeric"
                                  min={1}
                                  max={365}
                                  placeholder={t("penalty.incrementDaysPlaceholder")}
                                  {...field}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      )}
                    </div>
                  )}
                </>
              )}
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="button" variant="outline" className="h-11 flex-1" onClick={close}>
                {tc("actions.cancel")}
              </Button>
              <Button type="submit" className="h-11 flex-1" loading={form.formState.isSubmitting}>
                {tc("actions.save")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
      <BankAccountsSheet
        open={accountsOpen}
        onOpenChange={setAccountsOpen}
        onChanged={() => loadAccounts()}
      />
    </ResponsiveSheet>
  )
}
