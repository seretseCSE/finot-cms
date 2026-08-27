"use client"

import { useCallback, useEffect, useState } from "react"
import { useWatch, type UseFormReturn } from "react-hook-form"

import { AcademicYearPicker } from "@/components/students/academic-year-select"

import { AsyncCombobox, type AsyncComboboxOption } from "@/components/ui/async-combobox"
import { Checkbox } from "@/components/ui/checkbox"
import { DatePicker } from "@/components/ui/date-picker"
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { Switch } from "@/components/ui/switch"
import { BankLogo } from "@/components/fees/bank-accounts-sheet"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type {
  AcademicYear,
  BankAccount,
  FeeStructure,
  GradeLevel,
  Paginated,
  ProgramRef,
  SchoolDirectoryEntry,
  Section,
} from "@/lib/types"
import { cn } from "@/lib/utils"

import {
  defaultFeeSelection,
  type FeeSelection,
  type RegistrationValues,
  type WizardConcession,
} from "./schema"

const PAYMENT_METHODS = ["cash", "wallet", "bank_transfer", "other"] as const

const CONCESSION_CATEGORIES = [
  "sibling",
  "staff_child",
  "merit",
  "hardship",
  "scholarship",
  "other",
] as const

interface Props {
  form: UseFormReturn<RegistrationValues>
  academicYears: AcademicYear[]
  gradeLevels: GradeLevel[]
  sections: Section[]
  programs: ProgramRef[]
  applicableFees: FeeStructure[]
  onApplicableFees: (fees: FeeStructure[]) => void
  feeSelections: Record<number, FeeSelection>
  onFeeSelections: (selections: Record<number, FeeSelection>) => void
  /** Accounts the branch can take money on — pay-now account fallback when
   *  the fee has no collection accounts of its own. */
  accounts: BankAccount[]
  /** fees.manage staff may file a standing concession with the registration. */
  canGrantConcession: boolean
  concession: WizardConcession
  onConcession: (concession: WizardConcession) => void
}

/** Server search over the platform school directory, with inline add. */
export function useSchoolDirectorySearch() {
  const fetcher = useCallback(async (query: string): Promise<AsyncComboboxOption[]> => {
    const res = await apiFetch<{ data: SchoolDirectoryEntry[] }>(
      `/school-directory?q=${encodeURIComponent(query)}`,
    )
    return res.data.map((entry) => ({
      value: String(entry.id),
      label: entry.name,
      description: [entry.city, entry.region].filter(Boolean).join(", ") || undefined,
      badge: entry.school_id != null ? "Temari" : undefined,
    }))
  }, [])

  const create = useCallback(async (name: string): Promise<AsyncComboboxOption> => {
    const res = await apiFetch<{ data: SchoolDirectoryEntry }>("/school-directory", {
      method: "POST",
      body: { name },
    })
    return { value: String(res.data.id), label: res.data.name }
  }, [])

  return { fetcher, create }
}

export function StepEnrollmentFees({
  form,
  academicYears,
  gradeLevels,
  sections,
  programs,
  applicableFees,
  onApplicableFees,
  feeSelections,
  onFeeSelections,
  accounts,
  canGrantConcession,
  concession,
  onConcession,
}: Props) {
  const { t } = useTranslation("students")
  const { t: tf } = useTranslation("fees")
  const directory = useSchoolDirectorySearch()
  const [feesLoading, setFeesLoading] = useState(false)

  const enrollNow = useWatch({ control: form.control, name: "enroll_now" })
  const yearId = useWatch({ control: form.control, name: "academic_year_id" })
  const gradeId = useWatch({ control: form.control, name: "grade_level_id" })
  const sectionId = useWatch({ control: form.control, name: "section_id" })

  const gradeSections = sections.filter(
    (section) => !gradeId || String(section.grade_level_id) === gradeId,
  )


  // Programs cascade from the grade: the branch's offering matrix says which
  // programs teach the chosen grade (grade rows carry their program ids).
  const selectedGrade = gradeLevels.find((grade) => String(grade.id) === gradeId)
  const gradePrograms = selectedGrade?.program_ids
    ? programs.filter((program) => selectedGrade.program_ids!.includes(program.id))
    : programs

  // Applicable fees follow the year + grade choice. The fees section only
  // renders once both are chosen, so no synchronous reset is needed here.
  useEffect(() => {
    if (!enrollNow || !yearId || !gradeId) return
    let cancelled = false
    const timer = setTimeout(() => {
      if (cancelled) return
      setFeesLoading(true)
      apiFetch<Paginated<FeeStructure>>(
        `/fee-structures/applicable?academic_year_id=${yearId}&grade_level_id=${gradeId}`,
      )
        .then((res) => {
          if (!cancelled) onApplicableFees(res.data)
        })
        .catch(() => {
          if (!cancelled) onApplicableFees([])
        })
        .finally(() => {
          if (!cancelled) setFeesLoading(false)
        })
    }, 0)
    return () => {
      cancelled = true
      clearTimeout(timer)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- callbacks are stable setters
  }, [enrollNow, yearId, gradeId])

  const selectionFor = (feeId: number): FeeSelection => feeSelections[feeId] ?? defaultFeeSelection
  const patchSelection = (feeId: number, patch: Partial<FeeSelection>) =>
    onFeeSelections({ ...feeSelections, [feeId]: { ...selectionFor(feeId), ...patch } })

  /** Pay-now account options: the fee's own collection accounts when it has
   *  any, otherwise every account the branch can take money on. */
  function accountOptions(fee: FeeStructure) {
    if ((fee.bank_accounts?.length ?? 0) > 0) {
      return fee.bank_accounts!.map((account) => ({
        id: account.id,
        name: [account.bank_name, account.account_name].filter(Boolean).join(" · "),
        number: account.account_number,
        bank: account.bank_logo !== undefined || account.bank_name
          ? {
              id: account.id,
              code: account.bank_code ?? "",
              name: account.bank_name ?? "",
              type: account.bank_type ?? ("bank" as const),
              logo: account.bank_logo,
            }
          : null,
      }))
    }
    return accounts.map((account) => ({
      id: account.id,
      name: [account.bank?.name, account.account_name].filter(Boolean).join(" · "),
      number: account.account_number,
      bank: account.bank ?? null,
    }))
  }

  return (
    <div className="space-y-6">
      <FormField
        control={form.control}
        name="enroll_now"
        render={({ field }) => (
          <FormItem className="flex items-center justify-between rounded-2xl border p-4">
            <div className="space-y-0.5 pr-4">
              <FormLabel>{t("wizard.enrollNow")}</FormLabel>
              <p className="text-xs text-muted-foreground">{t("wizard.enrollNowHint")}</p>
            </div>
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
          </FormItem>
        )}
      />

      {enrollNow ? (
        <>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField
              control={form.control}
              name="academic_year_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("enroll.academicYear")}</FormLabel>
                  <AcademicYearPicker
                    years={academicYears}
                    value={field.value ?? ""}
                    onChange={field.onChange}
                    placeholder={t("enroll.selectYear")}
                  />
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="grade_level_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("wizard.gradeLevel")}</FormLabel>
                  <Select
                    value={field.value ?? ""}
                    onValueChange={(val) => {
                      field.onChange(val)
                      // A section from another grade is stale — clear it.
                      if (sectionId) form.setValue("section_id", "")
                      // Same for a program the new grade isn't offered in.
                      const grade = gradeLevels.find((g) => String(g.id) === val)
                      const programId = form.getValues("school_program_id")
                      if (
                        programId &&
                        grade?.program_ids &&
                        !grade.program_ids.includes(Number(programId))
                      ) {
                        form.setValue("school_program_id", "")
                      }
                    }}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t("wizard.selectGrade")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {gradeLevels.map((grade) => (
                        <SelectItem key={grade.id} value={String(grade.id)}>
                          {grade.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="section_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("wizard.sectionOptional")}</FormLabel>
                  <Select value={field.value ?? ""} onValueChange={field.onChange} disabled={!gradeId}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue
                          placeholder={gradeId ? t("wizard.assignLater") : t("wizard.gradeFirst")}
                        />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {gradeSections.map((section) => (
                        <SelectItem key={section.id} value={String(section.id)}>
                          {section.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted-foreground">{t("wizard.sectionHint")}</p>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="school_program_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("wizard.program")}</FormLabel>
                  <Select value={field.value ?? ""} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t("wizard.defaultProgram")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {gradePrograms.map((program) => (
                        <SelectItem key={program.id} value={String(program.id)}>
                          {program.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="enrolled_on"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("wizard.registeredOn")}</FormLabel>
                  <FormControl>
                    <DatePicker
                      value={field.value}
                      onChange={field.onChange}
                      onBlur={field.onBlur}
                      placeholder={t("wizard.today")}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="previous_school_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("wizard.previousSchool")}</FormLabel>
                  <FormControl>
                    <AsyncCombobox
                      value={
                        field.value
                          ? {
                              value: field.value,
                              label: form.getValues("previous_school_label") || field.value,
                            }
                          : null
                      }
                      onChange={(option) => {
                        field.onChange(option?.value ?? "")
                        form.setValue("previous_school_label", option?.label ?? "")
                      }}
                      fetcher={directory.fetcher}
                      onCreate={directory.create}
                      placeholder={t("wizard.previousSchoolPlaceholder")}
                      searchPlaceholder={t("wizard.previousSchoolSearch")}
                      emptyText={t("wizard.noSchoolsFound")}
                      createText={t("wizard.addSchool")}
                    />
                  </FormControl>
                  <p className="text-xs text-muted-foreground">{t("wizard.previousSchoolHint")}</p>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          {/* Fees */}
          <section className="space-y-3">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {t("wizard.fees")}
            </h3>

            {!yearId || !gradeId ? (
              <p className="text-sm text-muted-foreground">{t("wizard.feesNeedGrade")}</p>
            ) : feesLoading ? (
              <div className="space-y-2">
                <Skeleton className="h-14 w-full rounded-2xl" />
                <Skeleton className="h-14 w-full rounded-2xl" />
              </div>
            ) : applicableFees.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t("wizard.noApplicableFees")}</p>
            ) : (
              <div className="space-y-2">
                {applicableFees.map((fee) => {
                  const selection = selectionFor(fee.id)
                  return (
                    <div
                      key={fee.id}
                      className={cn(
                        "rounded-2xl border p-3 transition-colors",
                        selection.selected && "border-primary/40 bg-primary/5",
                      )}
                    >
                      <label className="flex cursor-pointer items-center gap-3">
                        <Checkbox
                          checked={selection.selected}
                          onCheckedChange={(checked) =>
                            patchSelection(fee.id, { selected: checked === true })
                          }
                        />
                        <span className="flex-1 text-sm font-medium">{fee.name}</span>
                        <span className="text-sm tabular-nums text-muted-foreground">
                          {Number(fee.amount).toLocaleString()} ETB
                        </span>
                      </label>

                      {(fee.bank_accounts?.length ?? 0) > 0 && (
                        <p className="mt-1.5 pl-7 text-xs text-muted-foreground">
                          {tf("payments.collectionAccounts")}:{" "}
                          {fee.bank_accounts!
                            .map((a) => `${a.bank_name ?? ""} ${a.account_number}`)
                            .join(" · ")}
                        </p>
                      )}

                      {selection.selected ? (
                        <div className="mt-3 space-y-3 border-t pt-3">
                          <div className="flex flex-wrap gap-2">
                            {(
                              [
                                ["unpaid", tf("registration.unpaid")],
                                ["pay_now", tf("registration.payNow")],
                                ["scholarship", tf("registration.scholarship")],
                              ] as const
                            ).map(([action, label]) => (
                              <button
                                key={action}
                                type="button"
                                onClick={() => patchSelection(fee.id, { action })}
                                className={cn(
                                  "h-8 rounded-full border px-3 text-xs font-medium transition-colors",
                                  selection.action === action
                                    ? "border-primary bg-primary/10 text-primary"
                                    : "bg-background text-muted-foreground hover:bg-muted",
                                )}
                              >
                                {label}
                              </button>
                            ))}
                          </div>

                          {selection.action === "pay_now" ? (
                            (() => {
                              const options = accountOptions(fee)
                              const needsAccount =
                                selection.method === "wallet" ||
                                selection.method === "bank_transfer"
                              return (
                                <div className="space-y-2">
                                  <div className="flex flex-wrap gap-2">
                                    <Select
                                      value={selection.method}
                                      onValueChange={(method) =>
                                        patchSelection(fee.id, {
                                          method: method as FeeSelection["method"],
                                          // Cash/other never take an account.
                                          bank_account_id:
                                            method === "cash" || method === "other"
                                              ? ""
                                              : selection.bank_account_id,
                                        })
                                      }
                                    >
                                      <SelectTrigger className="w-full sm:max-w-[240px]">
                                        <SelectValue />
                                      </SelectTrigger>
                                      <SelectContent>
                                        {PAYMENT_METHODS.map((method) => (
                                          <SelectItem key={method} value={method}>
                                            {tf(`methods.${method}`)}
                                          </SelectItem>
                                        ))}
                                      </SelectContent>
                                    </Select>

                                    {/* Which account/wallet received the money —
                                        the fee's own collection accounts, or any
                                        usable branch account as fallback. */}
                                    {needsAccount && options.length > 0 ? (
                                      <Select
                                        value={selection.bank_account_id}
                                        onValueChange={(id) =>
                                          patchSelection(fee.id, { bank_account_id: id })
                                        }
                                      >
                                        <SelectTrigger className="w-full sm:max-w-[300px]">
                                          <SelectValue
                                            placeholder={tf("payments.selectAccount")}
                                          />
                                        </SelectTrigger>
                                        <SelectContent>
                                          {options.map((option) => (
                                            <SelectItem key={option.id} value={String(option.id)}>
                                              <span className="flex items-center gap-2">
                                                <BankLogo bank={option.bank} size={20} />
                                                <span className="min-w-0">
                                                  {option.name}{" "}
                                                  <span className="text-muted-foreground tabular-nums">
                                                    ({option.number})
                                                  </span>
                                                </span>
                                              </span>
                                            </SelectItem>
                                          ))}
                                        </SelectContent>
                                      </Select>
                                    ) : null}
                                  </div>

                                  {needsAccount && options.length > 0 && !selection.bank_account_id ? (
                                    <p className="text-xs text-destructive">
                                      {tf("payments.accountRequired")}
                                    </p>
                                  ) : null}
                                </div>
                              )
                            })()
                          ) : null}

                          {selection.action === "scholarship" ? (
                            <Input
                              value={selection.scholarship_reason}
                              onChange={(e) =>
                                patchSelection(fee.id, { scholarship_reason: e.target.value })
                              }
                              placeholder={tf("registration.scholarshipReasonPlaceholder")}
                            />
                          ) : null}
                        </div>
                      ) : null}
                    </div>
                  )
                })}
              </div>
            )}
          </section>

          {/* Standing concession — a money decision, so fees.manage only. */}
          {canGrantConcession ? (
            <section className="space-y-3">
              <div className="flex items-center justify-between rounded-2xl border p-4">
                <div className="space-y-0.5 pr-4">
                  <p className="text-sm font-medium">{tf("registration.concessionTitle")}</p>
                  <p className="text-xs text-muted-foreground">
                    {tf("registration.concessionHint")}
                  </p>
                </div>
                <Switch
                  checked={concession.enabled}
                  onCheckedChange={(enabled) => onConcession({ ...concession, enabled })}
                />
              </div>

              {concession.enabled ? (
                <div className="space-y-3 rounded-2xl border p-4">
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                      <p className="text-sm font-medium">{tf("concessions.category")}</p>
                      <Select
                        value={concession.category}
                        onValueChange={(category) =>
                          onConcession({
                            ...concession,
                            category: category as WizardConcession["category"],
                          })
                        }
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {CONCESSION_CATEGORIES.map((category) => (
                            <SelectItem key={category} value={category}>
                              {tf(`concessions.categories.${category}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-1.5">
                      <p className="text-sm font-medium">{tf("concessions.value")}</p>
                      <Select
                        value={concession.discount_type}
                        onValueChange={(type) =>
                          onConcession({
                            ...concession,
                            discount_type: type as WizardConcession["discount_type"],
                          })
                        }
                      >
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {(["percentage", "fixed", "full_scholarship"] as const).map((type) => (
                            <SelectItem key={type} value={type}>
                              {tf(`scholarship.types.${type}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>

                  {concession.discount_type !== "full_scholarship" ? (
                    <Input
                      type="number"
                      inputMode="decimal"
                      min={0.01}
                      max={concession.discount_type === "percentage" ? 100 : undefined}
                      value={concession.discount_value}
                      onChange={(e) =>
                        onConcession({ ...concession, discount_value: e.target.value })
                      }
                      placeholder={
                        concession.discount_type === "percentage"
                          ? tf("concessions.percentValue")
                          : tf("concessions.fixedValue")
                      }
                      className="no-spinner"
                    />
                  ) : null}

                  <Input
                    value={concession.reason}
                    onChange={(e) => onConcession({ ...concession, reason: e.target.value })}
                    placeholder={tf("concessions.reasonPlaceholder")}
                  />
                </div>
              ) : null}
            </section>
          ) : null}
        </>
      ) : null}
    </div>
  )
}
