"use client"

import { Briefcase, KeyRound, Plus, ShieldCheck, Star, X } from "lucide-react"
import type { UseFieldArrayReturn, UseFormReturn } from "react-hook-form"

import {
  EMPLOYMENT_TYPES,
  ROMAN_LEVELS,
  emptyPosition,
  type EmployeeFormValues,
  type PositionValue,
} from "@/components/employees/wizard/schema"
import { RecordAttachments, type AttachmentProps } from "@/components/employees/wizard/steps/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { OptionCombobox } from "@/components/ui/combobox"
import { DatePicker } from "@/components/ui/date-picker"
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { JOB_TITLES } from "@/lib/data"
import { useTranslation } from "@/lib/i18n"
import type { Employee } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * Which of the three mutually exclusive portal-account notices to show. The
 * wizard decides this (it owns the policy fetch); the step only renders it.
 */
export interface AccountState {
  /** The employee already signs in — nothing to offer. */
  hasAccount: boolean
  /** A role-mapped job title: an account is provisioned server-side. */
  accountRequired: boolean
  /** The school's policy allows an account for this title — user's call. */
  showAccountChoice: boolean
}

interface Props {
  active: boolean
  form: UseFormReturn<EmployeeFormValues>
  positionArray: UseFieldArrayReturn<EmployeeFormValues, "positions">
  /** Live position values — `fields` alone does not re-render on edits. */
  watchedPositions: (Partial<PositionValue> | undefined)[]
  isEdit: boolean
  employee?: Employee | null
  account: AccountState
  onSetPrimary: (index: number) => void
  attachmentProps: AttachmentProps
}

/**
 * The jobs the person holds. Multi-job-title is normal (a teacher who is also
 * a department head), so this is a field array; exactly one current row is
 * primary. The portal-account notice lives here because it is driven by the
 * picked job titles.
 */
export function PositionsStep({
  active,
  form,
  positionArray,
  watchedPositions,
  isEdit,
  employee,
  account,
  onSetPrimary,
  attachmentProps,
}: Props) {
  const { t } = useTranslation("employees")
  const { hasAccount, accountRequired, showAccountChoice } = account

  return (
    <div className={cn("space-y-4", !active && "hidden")}>
      <div className="flex items-center justify-between">
        <p className="text-xs text-muted-foreground">{t("positions.hint")}</p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-8"
          onClick={() =>
            positionArray.append({
              ...emptyPosition,
              is_primary: watchedPositions.length === 0,
            })
          }
        >
          <Plus className="size-3.5" />
          {t("positions.add")}
        </Button>
      </div>
      {form.formState.errors.positions?.message && (
        <p className="text-sm text-destructive">{form.formState.errors.positions.message}</p>
      )}
      {form.formState.errors.positions?.root?.message && (
        <p className="text-sm text-destructive">{form.formState.errors.positions.root.message}</p>
      )}

      {positionArray.fields.map((row, index) => {
        const ended = !!watchedPositions[index]?.ended_on
        const isPrimary = !!watchedPositions[index]?.is_primary
        return (
          <div
            key={row.id}
            className={cn(
              "space-y-3 rounded-xl border p-3",
              ended && "opacity-70",
              isPrimary && !ended && "border-primary/40 bg-primary/[0.03]"
            )}
          >
            <div className="flex items-center gap-2">
              <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent">
                <Briefcase className="size-4 text-muted-foreground" />
              </div>
              <span className="flex-1 text-sm font-medium">
                {watchedPositions[index]?.job_title
                  ? t(`jobTitles.${watchedPositions[index]!.job_title}`)
                  : t("positions.new")}
              </span>
              {ended ? (
                <Badge variant="secondary">{t("positions.ended")}</Badge>
              ) : (
                <button
                  type="button"
                  onClick={() => onSetPrimary(index)}
                  className={cn(
                    "pressable inline-flex min-h-8 items-center gap-1 rounded-full border px-2.5 text-xs font-medium transition-colors",
                    isPrimary
                      ? "border-primary/40 bg-primary/10 text-primary"
                      : "text-muted-foreground hover:bg-muted"
                  )}
                  aria-pressed={isPrimary}
                >
                  <Star className={cn("size-3.5", isPrimary && "fill-current")} />
                  {t("positions.primary")}
                </button>
              )}
              {positionArray.fields.length > 1 && (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                  onClick={() => positionArray.remove(index)}
                  aria-label={t("positions.remove")}
                >
                  <X className="size-4" />
                </Button>
              )}
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField
                control={form.control}
                name={`positions.${index}.job_title`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.jobTitle")}</FormLabel>
                    <FormControl>
                      <OptionCombobox
                        options={JOB_TITLES.map((code) => ({
                          value: code,
                          label: t(`jobTitles.${code}`),
                        }))}
                        value={field.value}
                        onChange={field.onChange}
                        placeholder={t("fields.jobTitlePlaceholder")}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name={`positions.${index}.employment_type`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.employmentType")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t("fields.selectEmploymentType")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {EMPLOYMENT_TYPES.map((type) => (
                          <SelectItem key={type} value={type}>
                            {t(`employmentTypes.${type}`)}
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
                name={`positions.${index}.salary`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.salary")}</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        inputMode="numeric"
                        min={0}
                        placeholder={
                          isPrimary
                            ? t("fields.salaryPlaceholder")
                            : t("positions.salaryCoveredByPrimary")
                        }
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name={`positions.${index}.salary_level`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.salaryLevel")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t("fields.salaryLevelPlaceholder")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {ROMAN_LEVELS.map((roman, level) => (
                          <SelectItem key={roman} value={String(level + 1)}>
                            {roman}
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
                name={`positions.${index}.hired_on`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.hiredOn")}</FormLabel>
                    <FormControl>
                      <DatePicker
                        value={field.value}
                        onChange={field.onChange}
                        onBlur={field.onBlur}
                        max={new Date().toISOString().slice(0, 10)}
                        placeholder={t("fields.hiredOn")}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name={`positions.${index}.last_promoted_on`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.lastPromotedOn")}</FormLabel>
                    <FormControl>
                      <DatePicker
                        value={field.value}
                        onChange={field.onChange}
                        onBlur={field.onBlur}
                        placeholder={t("fields.lastPromotedOn")}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              {isEdit && (
                <FormField
                  control={form.control}
                  name={`positions.${index}.ended_on`}
                  render={({ field }) => (
                    <FormItem className="sm:col-span-2">
                      <FormLabel>{t("positions.endedOn")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          placeholder={t("positions.endedOnPlaceholder")}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}
            </div>

            {/* Documents for THIS position (contract, appointment letter…) */}
            <RecordAttachments
              kind="position"
              recordId={watchedPositions[index]?.id}
              anchor={
                watchedPositions[index]?.id ? `p:${watchedPositions[index]!.id}` : `pi:${index}`
              }
              {...attachmentProps}
            />
          </div>
        )
      })}

      {/* Portal account — settings-gated per job title. Hidden when
          policy excludes every picked title; locked-on for the four
          role-mapped titles (their memberships require a user). */}
      {hasAccount ? (
        <div className="flex items-start gap-3 rounded-xl border border-success/30 bg-success/5 p-3.5">
          <ShieldCheck className="mt-0.5 size-4.5 shrink-0 text-success" />
          <div className="min-w-0 text-sm">
            <p className="font-medium">{t("account.exists")}</p>
            <p className="text-xs text-muted-foreground">
              {t("account.existsHint", { id: employee?.user?.public_id ?? "" })}
            </p>
          </div>
        </div>
      ) : accountRequired ? (
        <div className="flex items-start gap-3 rounded-xl border border-primary/30 bg-primary/5 p-3.5">
          <KeyRound className="mt-0.5 size-4.5 shrink-0 text-primary" />
          <div className="min-w-0 text-sm">
            <p className="font-medium">{t("account.required")}</p>
            <p className="text-xs text-muted-foreground">{t("account.requiredHint")}</p>
          </div>
        </div>
      ) : showAccountChoice ? (
        <FormField
          control={form.control}
          name="create_user_account"
          render={({ field }) => (
            <FormItem>
              <label className="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5">
                <FormControl>
                  <Checkbox
                    checked={field.value}
                    onCheckedChange={(checked) => field.onChange(checked === true)}
                    className="mt-0.5"
                  />
                </FormControl>
                <span className="min-w-0 text-sm">
                  <span className="block font-medium">{t("account.create")}</span>
                  <span className="block text-xs text-muted-foreground">
                    {t("account.createHint")}
                  </span>
                </span>
              </label>
              <FormMessage />
            </FormItem>
          )}
        />
      ) : null}
    </div>
  )
}
