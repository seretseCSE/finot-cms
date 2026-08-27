"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { CalendarDays, Clock, Info, LayoutList } from "lucide-react"
import { useEffect, useMemo, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { FormSectionHeading } from "@/components/ui/form-section-heading"
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
import { TimePicker } from "@/components/ui/time-picker"
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
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { AcademicYear, Paginated, ProgramRef, Term } from "@/lib/types"

interface ProgramsResponse {
  branch_programs: ProgramRef[]
  catalog: { type: string; name: string }[]
}

const schema = z
  .object({
    academic_year_id: z.string().min(1, "Academic year is required"),
    name: z.string().min(1, "Name is required").max(100),
    program_type: z.string(),
    starts_on: z.string().optional(),
    ends_on: z.string().optional(),
    class_starts_at: z.string().optional(),
    class_ends_at: z.string().optional(),
    period_minutes: z.string(),
    is_quarter: z.boolean(),
    semester: z.string(),
    auto_generate_assignments: z.boolean(),
  })
  .refine((v) => !v.starts_on || !v.ends_on || v.ends_on >= v.starts_on, {
    message: "End date must be after start date",
    path: ["ends_on"],
  })
  .refine(
    (v) => !v.class_starts_at || !v.class_ends_at || v.class_ends_at > v.class_starts_at,
    { message: "Class end must be after class start", path: ["class_ends_at"] },
  )

type FormValues = z.infer<typeof schema>

const defaults: FormValues = {
  academic_year_id: "",
  name: "",
  program_type: "regular",
  starts_on: "",
  ends_on: "",
  class_starts_at: "",
  class_ends_at: "",
  period_minutes: "45",
  is_quarter: false,
  semester: "",
  auto_generate_assignments: false,
}

interface Props {
  /** Null = create mode. */
  term: Term | null
  /** Pin creation to a specific year (year detail page). */
  academicYear?: AcademicYear | null
  /** Year choices when creating from the standalone Semesters page. */
  academicYears?: AcademicYear[]
  onSaved: (term: Term) => void
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function TermSheet({ term, academicYear, academicYears, onSaved, open, onOpenChange }: Props) {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const isEdit = !!term

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: defaults })
  useLiveValidation(form)
  const startsOn = useWatch({ control: form.control, name: "starts_on" })
  const programType = useWatch({ control: form.control, name: "program_type" })
  const isQuarter = useWatch({ control: form.control, name: "is_quarter" })
  const academicYearId = useWatch({ control: form.control, name: "academic_year_id" })

  // School-wide workspace: creating requires naming the target branch first —
  // the year choices and branch programs then follow that branch. The write
  // itself is anchored by the chosen year (the POST is nested under it).
  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [branchYears, setBranchYears] = useState<AcademicYear[]>([])
  const years = useMemo(
    () => (needsBranch && !isEdit ? branchYears : (academicYears ?? [])),
    [needsBranch, isEdit, branchYears, academicYears],
  )

  useEffect(() => {
    if (!open || isEdit || !needsBranch || branchId == null) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- no branch, no years
      setBranchYears([])
      return
    }
    let cancelled = false
    apiFetch<Paginated<AcademicYear>>(`/academic-years?branch_id=${branchId}&per_page=100`)
      .then((res) => !cancelled && setBranchYears(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [open, isEdit, needsBranch, branchId])

  // Branch programs + catalog: options the branch already runs come first;
  // picking one it doesn't run yet shows the "will update branch" hint.
  const [programs, setPrograms] = useState<ProgramsResponse | null>(null)
  useEffect(() => {
    if (!open) return
    let cancelled = false
    // Drop the previous branch's programs while the new set loads — otherwise
    // stale branch_programs briefly flash the "program is new" warning against
    // the just-picked branch.
    // eslint-disable-next-line react-hooks/set-state-in-effect -- invalidate on branch change
    setPrograms(null)
    const branchParam = branchId != null ? `?branch_id=${branchId}` : ""
    apiFetch<{ data: ProgramsResponse }>(`/programs${branchParam}`)
      .then((res) => !cancelled && setPrograms(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [open, branchId])

  useEffect(() => {
    if (!open) return
    form.reset(
      term
        ? {
            academic_year_id: String(term.academic_year_id),
            name: term.name,
            program_type: term.program?.type ?? "regular",
            starts_on: term.starts_on ?? "",
            ends_on: term.ends_on ?? "",
            class_starts_at: term.class_starts_at ?? "",
            class_ends_at: term.class_ends_at ?? "",
            period_minutes: String(term.period_minutes ?? 45),
            is_quarter: term.is_quarter,
            semester: term.semester ? String(term.semester) : "",
            auto_generate_assignments: false,
          }
        : { ...defaults, academic_year_id: academicYear ? String(academicYear.id) : "" },
    )
    // eslint-disable-next-line react-hooks/set-state-in-effect -- seed sheet state on open
    setBranchId(null)
    setBranchError(null)
  }, [open, term, academicYear, form])

  /** ISO date + n days (dates only — no timezone maths). */
  function addDaysIso(iso: string, days: number): string {
    const [y, m, d] = iso.split("-").map(Number)
    const dt = new Date(Date.UTC(y, m - 1, d + days))
    return dt.toISOString().slice(0, 10)
  }

  // Create mode: once a year is chosen, seed what the office would type
  // anyway — the next free stretch of the year window, and the branch's
  // running class hours (from the latest sibling that has them). Only fills
  // fields the user hasn't touched.
  useEffect(() => {
    if (!open || isEdit || !academicYearId) return
    const yr =
      academicYear && String(academicYear.id) === academicYearId
        ? academicYear
        : years.find((y) => String(y.id) === academicYearId)
    if (!yr) return

    const siblings = yr.terms ?? []
    const lastEnd = siblings.map((s) => s.ends_on).filter(Boolean).sort().at(-1) ?? null
    const withHours = [...siblings].reverse().find((s) => s.class_starts_at)
    const v = form.getValues()

    if (!v.starts_on) {
      const next = lastEnd ? addDaysIso(lastEnd, 1) : (yr.starts_on ?? "")
      if (next) form.setValue("starts_on", next)
    }
    if (!v.ends_on && yr.ends_on) {
      const start = form.getValues("starts_on")
      if (!start || yr.ends_on >= start) form.setValue("ends_on", yr.ends_on)
    }
    if (!v.class_starts_at && withHours?.class_starts_at) {
      form.setValue("class_starts_at", withHours.class_starts_at.slice(0, 5))
    }
    if (!v.class_ends_at && withHours?.class_ends_at) {
      form.setValue("class_ends_at", withHours.class_ends_at.slice(0, 5))
    }
    if (v.period_minutes === "45" && withHours?.period_minutes) {
      form.setValue("period_minutes", String(withHours.period_minutes))
    }
  }, [open, isEdit, academicYearId, academicYear, years, form])

  const branchTypes = new Set((programs?.branch_programs ?? []).map((p) => p.type))
  // The "will be added to the branch" hint only makes sense once the target
  // branch is known: in the school-wide workspace that means a branch is
  // picked. Before then there is nothing to compare the program against, so
  // don't scare the user with a stale warning against the default program.
  const branchResolved = !needsBranch || branchId != null
  const programIsNew =
    branchResolved && programs !== null && programType !== "" && !branchTypes.has(programType)

  async function onSubmit(values: FormValues) {
    if (!isEdit && !academicYear && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    const body = {
      name: values.name,
      program_type: values.program_type || null,
      starts_on: values.starts_on || null,
      ends_on: values.ends_on || null,
      class_starts_at: values.class_starts_at || null,
      class_ends_at: values.class_ends_at || null,
      period_minutes: Number(values.period_minutes) || 45,
      is_quarter: values.is_quarter,
      semester: values.is_quarter && values.semester ? Number(values.semester) : null,
      // Opt-in only — pre-builds the section/subject/teacher grid on creation.
      ...(isEdit ? {} : { auto_generate_assignments: values.auto_generate_assignments }),
    }

    try {
      const res = await apiFetch<{ data: Term }>(
        isEdit
          ? `/terms/${term!.id}`
          : `/academic-years/${values.academic_year_id}/terms`,
        { method: isEdit ? "PUT" : "POST", body },
      )
      toast.success(isEdit ? t("terms.updated") : t("terms.created"))
      onSaved(res.data)
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

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("terms.edit") : t("terms.create")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-4">
              <FormSectionHeading icon={<LayoutList />}>
                {t("terms.sections.basics")}
              </FormSectionHeading>

              {!isEdit && !academicYear && (
                <BranchField
                  value={branchId}
                  onChange={(id) => {
                    setBranchId(id)
                    setBranchError(null)
                    form.setValue("academic_year_id", "")
                  }}
                  error={branchError}
                />
              )}

              {/* Year picker only when creating from the standalone page. */}
              {!isEdit && !academicYear && years.length > 0 && (
                <FormField
                  control={form.control}
                  name="academic_year_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("terms.academicYear")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("terms.selectYear")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {years.map((y) => (
                            <SelectItem key={y.id} value={String(y.id)}>
                              {y.name}
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
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("terms.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("terms.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="program_type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("terms.program")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t("terms.selectProgram")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {(programs?.catalog ?? [{ type: "regular", name: "Regular" }]).map((p) => (
                          <SelectItem key={p.type} value={p.type}>
                            <span className="flex items-center gap-2">
                              {tc(`programs.${p.type}`)}
                              {branchTypes.has(p.type) && (
                                <span className="text-xs text-muted-foreground">
                                  {t("terms.programInBranch")}
                                </span>
                              )}
                            </span>
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {programIsNew && (
                      <p className="flex items-start gap-1.5 rounded-lg bg-warning/10 px-2.5 py-2 text-xs text-warning">
                        <Info className="mt-0.5 size-3.5 shrink-0" />
                        {t("terms.programWillBeAdded")}
                      </p>
                    )}
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="is_quarter"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between gap-3 rounded-xl border bg-muted/30 px-3 py-2.5">
                    <div>
                      <FormLabel className="!mt-0 font-normal">{t("terms.isQuarter")}</FormLabel>
                      <p className="text-xs text-muted-foreground">{t("terms.isQuarterHint")}</p>
                    </div>
                    <FormControl>
                      <Switch
                        checked={field.value}
                        onCheckedChange={(checked) => {
                          field.onChange(checked)
                          if (!checked) form.setValue("semester", "")
                        }}
                      />
                    </FormControl>
                  </FormItem>
                )}
              />

              {isQuarter && (
                <FormField
                  control={form.control}
                  name="semester"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("terms.semester")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("terms.semesterNone")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="1">{t("terms.semester1")}</SelectItem>
                          <SelectItem value="2">{t("terms.semester2")}</SelectItem>
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">{t("terms.semesterHint")}</p>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}

              <FormSectionHeading icon={<CalendarDays />}>
                {t("terms.sections.calendar")}
              </FormSectionHeading>
              {/* Stacked so each date field is wide enough for the full date. */}
              <div className="grid grid-cols-1 gap-4">
                <FormField
                  control={form.control}
                  name="starts_on"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("terms.startsOn")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          placeholder={t("terms.startsOn")}
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
                      <FormLabel>{t("terms.endsOn")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          min={startsOn || undefined}
                          placeholder={t("terms.endsOn")}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <FormSectionHeading icon={<Clock />}>
                {t("terms.sections.schedule")}
              </FormSectionHeading>
              <p className="-mt-2 text-xs text-muted-foreground">{t("terms.scheduleHint")}</p>
              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="class_starts_at"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("terms.classStartsAt")}</FormLabel>
                      <FormControl>
                        <TimePicker {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="class_ends_at"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("terms.classEndsAt")}</FormLabel>
                      <FormControl>
                        <TimePicker {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="period_minutes"
                  render={({ field }) => (
                    <FormItem className="col-span-2">
                      <FormLabel>{t("terms.periodMinutes")}</FormLabel>
                      <FormControl>
                        <Input type="number" inputMode="numeric" min={5} max={240} {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

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
