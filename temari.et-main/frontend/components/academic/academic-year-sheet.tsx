"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { BranchField, useBranchScope } from "@/components/ui/branch-select"
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
import { Input } from "@/components/ui/input"
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
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { AcademicYear, AcademicYearStatus } from "@/lib/types"
import { addisToday, getCalendarPrefs } from "@/lib/dates"
import { fromEthiopian, toEthiopian } from "@/lib/ethiopian-date"

const STATUSES: AcademicYearStatus[] = ["planned", "active", "completed", "archived"]

/**
 * The Ethiopian school year today falls in: Meskerem 1 through Sene 30 —
 * computed with real calendar math, never approximated Gregorian dates (the
 * new year shifts between Sep 11/12 and Sene 30 is NOT June 30).
 */
function currentAcademicYearWindow(): { starts_on: string; ends_on: string } {
  const eth = toEthiopian(addisToday())
  return {
    starts_on: fromEthiopian({ year: eth.year, month: 1, day: 1 }) ?? "",
    ends_on: fromEthiopian({ year: eth.year, month: 10, day: 30 }) ?? "",
  }
}

/**
 * Default year name from the start date, following the school's calendar:
 * "2018 E.C." for Ethiopian-calendar schools, "2025/26" for Gregorian ones.
 */
function ecYearName(startsOn: string): string {
  if (!startsOn) return ""
  if (getCalendarPrefs().calendar === "gregorian") {
    const y = Number(startsOn.slice(0, 4))
    if (Number.isNaN(y)) return ""
    return `${y}/${String((y + 1) % 100).padStart(2, "0")}`
  }
  return `${toEthiopian(startsOn).year} E.C.`
}

const schema = z
  .object({
    starts_on: z.string().min(1, "Start date is required"),
    ends_on: z.string().min(1, "End date is required"),
    name: z.string().min(2, "Year name is required").max(100),
    status: z.enum(["planned", "active", "completed", "archived"]),
    terms_count: z.string(),
    auto_generate_assignments: z.boolean(),
  })
  .refine((v) => !v.starts_on || !v.ends_on || v.ends_on >= v.starts_on, {
    message: "End date must be after start date",
    path: ["ends_on"],
  })

type FormValues = z.infer<typeof schema>

interface Props {
  year?: AcademicYear | null
  onSaved: (year: AcademicYear) => void
  open?: boolean
  onOpenChange?: (open: boolean) => void
  showTrigger?: boolean
}

export function AcademicYearSheet({ year, onSaved, open, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const isEdit = !!year

  // School-wide workspace: creating requires naming the target branch.
  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)

  function freshDefaults(): FormValues {
    const window = currentAcademicYearWindow()
    return {
      ...window,
      name: ecYearName(window.starts_on),
      status: "planned",
      terms_count: "2",
      auto_generate_assignments: false,
    }
  }

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: freshDefaults() })
  useLiveValidation(form)
  const startsOn = useWatch({ control: form.control, name: "starts_on" })
  const status = useWatch({ control: form.control, name: "status" })

  // The name auto-follows the start date's Ethiopian year, but only while the
  // user hasn't typed their own — a manual edit wins permanently.
  const lastAutoName = useRef<string>("")

  useEffect(() => {
    if (!open) return
    if (year) {
      form.reset({
        starts_on: year.starts_on ?? "",
        ends_on: year.ends_on ?? "",
        name: year.name,
        status: year.status,
        terms_count: "2",
        auto_generate_assignments: false,
      })
      lastAutoName.current = ""
    } else {
      const defaults = freshDefaults()
      form.reset(defaults)
      lastAutoName.current = defaults.name
    }
    // eslint-disable-next-line react-hooks/set-state-in-effect -- seed sheet state on open
    setBranchId(null)
    setBranchError(null)
  }, [open, year, form])

  useEffect(() => {
    if (!open || isEdit || !startsOn) return
    const auto = ecYearName(startsOn)
    const current = form.getValues("name")
    if (auto && (current === "" || current === lastAutoName.current)) {
      form.setValue("name", auto, { shouldValidate: current !== "" })
      lastAutoName.current = auto
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [startsOn, open, isEdit])

  function close() {
    onOpenChange?.(false)
    if (!isEdit) form.reset(freshDefaults())
  }

  async function onSubmit(values: FormValues) {
    if (!isEdit && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    const body = {
      ...(!isEdit && branchId != null ? { branch_id: branchId } : {}),
      name: values.name,
      starts_on: values.starts_on,
      ends_on: values.ends_on,
      status: values.status,
      ...(isEdit
        ? {}
        : {
            terms_count: Number(values.terms_count),
            // Opt-in only — pre-builds each semester's teaching grid.
            auto_generate_assignments: values.auto_generate_assignments,
          }),
    }

    try {
      const endpoint = isEdit ? `/academic-years/${year!.id}` : "/academic-years"
      const res = await apiFetch<{ data: AcademicYear }>(endpoint, {
        method: isEdit ? "PUT" : "POST",
        body,
      })
      toast.success(isEdit ? t("years.updated") : t("years.created"))
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
            {t("years.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("years.editTitle") : t("years.createTitle")}
          </ResponsiveSheetTitle>
          {!isEdit && (
            <ResponsiveSheetDescription>{t("years.createDescription")}</ResponsiveSheetDescription>
          )}
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
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
              {/* Calendar first — the name derives from it. Stacked so each
                  date field is wide enough to show the full formatted date. */}
              <div className="grid grid-cols-1 gap-4">
                <FormField
                  control={form.control}
                  name="starts_on"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("years.startsOn")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          placeholder={t("years.startsOn")}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="ends_on"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("years.endsOn")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          min={startsOn || undefined}
                          placeholder={t("years.endsOn")}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("years.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("years.namePlaceholder")} {...field} />
                    </FormControl>
                    {!isEdit && (
                      <p className="text-xs text-muted-foreground">{t("years.nameAutoHint")}</p>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />

              {!isEdit && (
                <FormField
                  control={form.control}
                  name="terms_count"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("years.termsCount")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {[1, 2, 3, 4, 5].map((n) => (
                            <SelectItem key={n} value={String(n)}>
                              {t("years.termsCountOption", { count: n })}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">{t("years.termsCountHint")}</p>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}

              {!isEdit && (
                <FormField
                  control={form.control}
                  name="auto_generate_assignments"
                  render={({ field }) => (
                    <FormItem className="space-y-2 rounded-xl border bg-muted/30 px-3 py-2.5">
                      <div className="flex flex-row items-center gap-3">
                        <FormControl>
                          <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                        </FormControl>
                        <FormLabel className="!mt-0 font-normal">
                          {t("terms.autoGenerate")}
                        </FormLabel>
                      </div>
                      {field.value && (
                        <p className="rounded-lg border border-warning/30 bg-warning/10 px-2.5 py-2 text-xs">
                          {t("terms.autoGenerateWarning")}
                        </p>
                      )}
                    </FormItem>
                  )}
                />
              )}

              <FormField
                control={form.control}
                name="status"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("years.status")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {STATUSES.map((s) => (
                          <SelectItem key={s} value={s}>
                            {t(`years.statuses.${s}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {status === "active" && (
                      <p className="text-xs text-muted-foreground">{t("years.activeHint")}</p>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />
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
    </ResponsiveSheet>
  )
}
