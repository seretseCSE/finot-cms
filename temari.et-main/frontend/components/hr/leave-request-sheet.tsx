"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus } from "lucide-react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
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
import { ApiError, apiFetch } from "@/lib/api"
import { addisToday } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { LeaveRequest, LeaveType } from "@/lib/types"

const schema = z.object({
  employee_id: z.string().optional(),
  leave_type_id: z.string().min(1, "Pick a leave type"),
  start_date: z.string().min(1, "Pick the first day"),
  end_date: z.string().min(1, "Pick the last day"),
  is_half_day: z.boolean(),
  reason: z.string().optional(),
})

type FormValues = z.infer<typeof schema>

const defaults: FormValues = {
  employee_id: "",
  leave_type_id: "",
  start_date: "",
  end_date: "",
  is_half_day: false,
  reason: "",
}

/**
 * Client-side preview of consumed days (weekdays only). The server is the
 * source of truth — it also subtracts the holiday calendar.
 */
function weekdaysBetween(start: string, end: string): number {
  const from = new Date(start)
  const to = new Date(end)
  if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime()) || from > to) return 0
  let days = 0
  for (const d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) {
    if (d.getDay() !== 0 && d.getDay() !== 6) days++
  }
  return days
}

/**
 * Shared submit form: managers pass `employees` (request on behalf of anyone
 * in the branch); the self-service page omits it and the backend resolves the
 * requester's own staff profile.
 */
export function LeaveRequestSheet({
  open,
  onOpenChange,
  onSaved,
  leaveTypes,
  employees,
  branchId,
  showTrigger = false,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: (request: LeaveRequest) => void
  leaveTypes: LeaveType[]
  /** When set, a staff-member picker is shown (supervisory lane). */
  employees?: { id: number; name: string }[]
  /** School-wide workspace: the branch this request targets. */
  branchId?: number | null
  showTrigger?: boolean
}) {
  const { t } = useTranslation("hr")

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: defaults })
  useLiveValidation(form)

  const values = useWatch({ control: form.control })
  const selectedType = leaveTypes.find((lt) => String(lt.id) === values.leave_type_id)
  const previewDays = values.is_half_day
    ? 0.5
    : values.start_date && values.end_date
      ? weekdaysBetween(values.start_date, values.end_date)
      : 0

  function handleOpenChange(value: boolean) {
    onOpenChange(value)
    if (!value) form.reset(defaults)
  }

  async function onSubmit(formValues: FormValues) {
    // With the picker visible, submitting is always ON BEHALF — an unpicked
    // employee must not silently become a request for the manager themselves.
    if (employees && !formValues.employee_id) {
      form.setError("employee_id", { message: t("leave.form.selectEmployee") })
      return
    }
    if (selectedType?.requires_note && !formValues.reason?.trim()) {
      form.setError("reason", { message: t("leave.form.noteRequired") })
      return
    }

    try {
      const response = await apiFetch<{ data: LeaveRequest }>("/hr/leave-requests", {
        method: "POST",
        body: {
          ...(branchId != null ? { branch_id: branchId } : {}),
          employee_id: formValues.employee_id ? Number(formValues.employee_id) : undefined,
          leave_type_id: Number(formValues.leave_type_id),
          start_date: formValues.start_date,
          end_date: formValues.is_half_day ? formValues.start_date : formValues.end_date,
          is_half_day: formValues.is_half_day,
          reason: formValues.reason?.trim() || undefined,
        },
      })
      toast.success(t("leave.form.submitted"))
      onSaved(response.data)
      handleOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error(t("error"))
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={handleOpenChange}>
      {showTrigger && (
        <ResponsiveSheetTrigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("leave.newRequest")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("leave.newRequest")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("leave.subtitle")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-4">
              {employees && (
                <FormField
                  control={form.control}
                  name="employee_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("leave.form.employee")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder={t("leave.form.selectEmployee")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {employees.map((e) => (
                            <SelectItem key={e.id} value={String(e.id)}>
                              {e.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}

              <FormField
                control={form.control}
                name="leave_type_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("leave.form.type")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder={t("leave.form.selectType")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {leaveTypes.map((lt) => (
                          <SelectItem key={lt.id} value={String(lt.id)}>
                            {lt.name}
                            {lt.days_per_year != null && (
                              <span className="text-muted-foreground">
                                {" "}
                                · {lt.days_per_year}
                              </span>
                            )}
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
                  name="start_date"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("leave.form.startDate")}</FormLabel>
                      <FormControl>
                        {/* Self-service asks for UPCOMING leave — today at the
                            earliest. The on-behalf lane (picker shown) may
                            backdate: HR records sick leave after the fact. */}
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          min={employees ? undefined : addisToday()}
                          clearable={false}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                {!values.is_half_day && (
                  <FormField
                    control={form.control}
                    name="end_date"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("leave.form.endDate")}</FormLabel>
                        <FormControl>
                          <DatePicker
                            value={field.value}
                            onChange={field.onChange}
                            min={values.start_date || (employees ? undefined : addisToday())}
                            clearable={false}
                          />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                )}
              </div>

              <FormField
                control={form.control}
                name="is_half_day"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center gap-2.5 space-y-0">
                    <FormControl>
                      <Checkbox
                        checked={field.value}
                        onCheckedChange={(checked) => {
                          field.onChange(checked === true)
                          if (checked === true && values.start_date) {
                            form.setValue("end_date", values.start_date)
                          }
                        }}
                      />
                    </FormControl>
                    <FormLabel className="!mt-0 font-normal">
                      {t("leave.form.halfDayLabel")}
                    </FormLabel>
                  </FormItem>
                )}
              />

              {previewDays > 0 && (
                <p className="rounded-xl bg-muted/50 px-3.5 py-2.5 text-sm text-muted-foreground tabular-nums">
                  ≈ {previewDays} {t("leave.columns.days").toLowerCase()}
                </p>
              )}

              <FormField
                control={form.control}
                name="reason"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>
                      {t("leave.form.reason")}
                      {selectedType?.requires_note && " *"}
                    </FormLabel>
                    <FormControl>
                      <textarea
                        {...field}
                        rows={3}
                        placeholder={t("leave.form.reasonPlaceholder")}
                        className="w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="submit" className="h-11 w-full" loading={form.formState.isSubmitting}>
                {t("leave.form.submit")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
