"use client"

import { MinusCircle, Plus, X } from "lucide-react"
import type { UseFieldArrayReturn, UseFormReturn } from "react-hook-form"

import type { EmployeeFormValues } from "@/components/employees/wizard/schema"
import { Button } from "@/components/ui/button"
import { Combobox } from "@/components/ui/combobox"
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
import { TimePicker } from "@/components/ui/time-picker"
import { ALLOWANCE_TYPES, PROFESSIONAL_LEVELS } from "@/lib/data"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

interface Props {
  active: boolean
  form: UseFormReturn<EmployeeFormValues>
  allowanceArray: UseFieldArrayReturn<EmployeeFormValues, "allowances">
  deductionArray: UseFieldArrayReturn<EmployeeFormValues, "deductions">
}

/**
 * Person-level career facts, the recurring pay lines that feed payroll, and
 * the daily attendance window.
 */
export function CompensationStep({ active, form, allowanceArray, deductionArray }: Props) {
  const { t } = useTranslation("employees")

  return (
    <div className={cn("space-y-4", !active && "hidden")}>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField
          control={form.control}
          name="professional_level"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.professionalLevel")}</FormLabel>
              <FormControl>
                <Combobox
                  options={PROFESSIONAL_LEVELS}
                  value={field.value}
                  onChange={field.onChange}
                  placeholder={t("fields.professionalLevelPlaceholder")}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="retirement_on"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.retirementOn")}</FormLabel>
              <FormControl>
                <DatePicker
                  value={field.value}
                  onChange={field.onChange}
                  onBlur={field.onBlur}
                  placeholder={t("fields.retirementOn")}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>

      {/* Allowance lines */}
      <div className="space-y-2">
        <p className="text-xs font-medium text-muted-foreground">{t("allowances.title")}</p>
        {allowanceArray.fields.map((row, index) => (
          <div key={row.id} className="flex items-start gap-2">
            <FormField
              control={form.control}
              name={`allowances.${index}.name`}
              render={({ field }) => (
                <FormItem className="flex-1">
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t("allowances.typePlaceholder")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {ALLOWANCE_TYPES.map((type) => (
                        <SelectItem key={type} value={type}>
                          {t(`allowances.types.${type}`)}
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
              name={`allowances.${index}.amount`}
              render={({ field }) => (
                <FormItem className="w-32">
                  <FormControl>
                    <Input
                      type="number"
                      inputMode="numeric"
                      min={0}
                      placeholder={t("allowances.amountPlaceholder")}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="mt-0.5 size-9 shrink-0 text-muted-foreground hover:text-destructive"
              onClick={() => allowanceArray.remove(index)}
              aria-label={t("allowances.remove")}
            >
              <X className="size-4" />
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-9"
          onClick={() => allowanceArray.append({ name: "", amount: "" })}
        >
          <Plus className="size-4" />
          {t("allowances.add")}
        </Button>
      </div>

      {/* Deduction lines */}
      <div className="space-y-2">
        <p className="text-xs font-medium text-muted-foreground">{t("deductions.title")}</p>
        <p className="text-xs text-muted-foreground">{t("deductions.hint")}</p>
        {deductionArray.fields.map((row, index) => (
          <div key={row.id} className="flex items-start gap-2">
            <FormField
              control={form.control}
              name={`deductions.${index}.name`}
              render={({ field }) => (
                <FormItem className="flex-1">
                  <FormControl>
                    <Input placeholder={t("deductions.namePlaceholder")} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name={`deductions.${index}.amount`}
              render={({ field }) => (
                <FormItem className="w-32">
                  <FormControl>
                    <Input
                      type="number"
                      inputMode="numeric"
                      min={0}
                      placeholder={t("deductions.amountPlaceholder")}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="mt-0.5 size-9 shrink-0 text-muted-foreground hover:text-destructive"
              onClick={() => deductionArray.remove(index)}
              aria-label={t("deductions.remove")}
            >
              <MinusCircle className="size-4" />
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-9"
          onClick={() => deductionArray.append({ name: "", amount: "" })}
        >
          <Plus className="size-4" />
          {t("deductions.add")}
        </Button>
      </div>

      {/* Attendance window */}
      <div className="space-y-2 border-t pt-4">
        <p className="text-xs font-medium text-muted-foreground">{t("sections.schedule")}</p>
        <p className="text-xs text-muted-foreground">{t("schedule.hint")}</p>
        <div className="grid grid-cols-2 gap-3">
          <FormField
            control={form.control}
            name="check_in"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t("fields.checkIn")}</FormLabel>
                <FormControl>
                  <TimePicker {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
          <FormField
            control={form.control}
            name="check_out"
            render={({ field }) => (
              <FormItem>
                <FormLabel>{t("fields.checkOut")}</FormLabel>
                <FormControl>
                  <TimePicker {...field} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>
      </div>
    </div>
  )
}
