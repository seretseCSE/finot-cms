"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { ChevronDown, Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { useFieldArray, useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import {
  BLOOD_TYPES,
  LANGUAGES,
} from "@/components/students/registration/schema"
import { AddressFields } from "@/components/ui/address-fields"
import { Button } from "@/components/ui/button"
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
import { PhoneInput } from "@/components/ui/phone-input"
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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { AcademicYear, HealthCondition, Section, Student } from "@/lib/types"
import { optionalEthPhone } from "@/lib/validators"
import { cn } from "@/lib/utils"

const schema = z.object({
  first_name: z.string().min(1, "First name is required").max(255),
  father_name: z.string().min(1, "Father's name is required").max(255),
  grandfather_name: z.string().max(255).optional(),
  mother_name: z.string().max(255).optional(),
  gender: z.enum(["male", "female"]),
  date_of_birth: z.string().optional(),
  national_student_id: z.string().max(50).optional(),
  primary_phone: optionalEthPhone(),
  email: z.string().email("Enter a valid email").max(255).or(z.literal("")).optional(),
  citizenship: z.string().max(100).optional(),
  marital_status: z.enum(["single", "married", "divorced", "widowed"]).or(z.literal("")).optional(),
  languages: z.array(z.enum(LANGUAGES)).min(1, "Pick at least one language"),
  birth_state: z.string().max(100).optional(),
  birth_city: z.string().max(100).optional(),
  birth_sub_city: z.string().max(100).optional(),
  birth_woreda: z.string().max(100).optional(),
  state: z.string().max(100).optional(),
  city: z.string().max(100).optional(),
  sub_city: z.string().max(100).optional(),
  woreda: z.string().max(100).optional(),
  house_no: z.string().max(50).optional(),
  blood_type: z.enum(BLOOD_TYPES).or(z.literal("")).optional(),
  health_notes: z.string().max(2000).optional(),
  health_conditions: z.array(
    z.object({
      health_condition_id: z.string().min(1, "Pick a condition"),
      severity: z.enum(["mild", "moderate", "severe"]).or(z.literal("")).optional(),
      notes: z.string().max(1000).optional(),
      medication: z.string().max(255).optional(),
    }),
  ),
})

type FormValues = z.infer<typeof schema>

const emptyValues: FormValues = {
  first_name: "",
  father_name: "",
  grandfather_name: "",
  mother_name: "",
  gender: "male",
  date_of_birth: "",
  national_student_id: "",
  primary_phone: "",
  email: "",
  citizenship: "Ethiopian",
  marital_status: "",
  languages: ["am"],
  birth_state: "",
  birth_city: "",
  birth_sub_city: "",
  birth_woreda: "",
  state: "",
  city: "",
  sub_city: "",
  woreda: "",
  house_no: "",
  blood_type: "",
  health_notes: "",
  health_conditions: [],
}

interface Props {
  student?: Student | null
  /** Kept for API compatibility with older callers; the wizard handles enrollment now. */
  academicYears?: AcademicYear[]
  sections?: Section[]
  onSaved: (student: Student) => void
  open?: boolean
  onOpenChange?: (open: boolean) => void
  showTrigger?: boolean
}

function OptionalSection({
  title,
  defaultOpen,
  children,
}: {
  title: string
  defaultOpen?: boolean
  children: React.ReactNode
}) {
  const [open, setOpen] = useState(defaultOpen ?? false)

  return (
    <div className="rounded-2xl border">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between px-4 py-3 text-sm font-medium"
      >
        {title}
        <ChevronDown className={cn("size-4 text-muted-foreground transition-transform", open && "rotate-180")} />
      </button>
      {open ? <div className="space-y-4 border-t p-4">{children}</div> : null}
    </div>
  )
}

export function StudentSheet({ student, onSaved, open, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const isEdit = !!student

  const [conditions, setConditions] = useState<HealthCondition[]>([])

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: emptyValues,
  })
  useLiveValidation(form)
  const conditionRows = useFieldArray({ control: form.control, name: "health_conditions" })

  useEffect(() => {
    if (!open) return
    apiFetch<{ data: HealthCondition[] }>("/health-conditions")
      .then((res) => setConditions(res.data))
      .catch(() => {})
    form.reset(
      student
        ? {
            ...emptyValues,
            first_name: student.first_name,
            father_name: student.father_name,
            grandfather_name: student.grandfather_name ?? "",
            mother_name: student.mother_name ?? "",
            gender: student.gender,
            date_of_birth: student.date_of_birth ?? "",
            national_student_id: student.national_student_id ?? "",
            primary_phone: student.primary_phone ?? "",
            email: student.email ?? "",
            citizenship: student.citizenship ?? "",
            marital_status: student.marital_status ?? "",
            languages:
              student.languages && student.languages.length > 0
                ? (student.languages as FormValues["languages"])
                : ["am"],
            birth_state: student.birth_state ?? "",
            birth_city: student.birth_city ?? "",
            birth_sub_city: student.birth_sub_city ?? "",
            birth_woreda: student.birth_woreda ?? "",
            state: student.state ?? "",
            city: student.city ?? "",
            sub_city: student.sub_city ?? "",
            woreda: student.woreda ?? "",
            house_no: student.house_no ?? "",
            blood_type: student.blood_type ?? "",
            health_notes: student.health_notes ?? "",
            health_conditions: (student.health_conditions ?? []).map((row) => ({
              health_condition_id: String(row.health_condition_id),
              severity: row.severity ?? "",
              notes: row.notes ?? "",
              medication: row.medication ?? "",
            })),
          }
        : emptyValues,
    )
  }, [open, student, form])

  function close() {
    onOpenChange?.(false)
    if (!isEdit) form.reset(emptyValues)
  }

  async function onSubmit(values: FormValues) {
    const body: Record<string, unknown> = {
      first_name: values.first_name,
      father_name: values.father_name,
      grandfather_name: values.grandfather_name || undefined,
      mother_name: values.mother_name || undefined,
      gender: values.gender,
      date_of_birth: values.date_of_birth || null,
      national_student_id: values.national_student_id || undefined,
      primary_phone: values.primary_phone || undefined,
      email: values.email || undefined,
      citizenship: values.citizenship || undefined,
      marital_status: values.marital_status || undefined,
      languages: values.languages,
      birth_state: values.birth_state || undefined,
      birth_city: values.birth_city || undefined,
      birth_sub_city: values.birth_sub_city || undefined,
      birth_woreda: values.birth_woreda || undefined,
      state: values.state || undefined,
      city: values.city || undefined,
      sub_city: values.sub_city || undefined,
      woreda: values.woreda || undefined,
      house_no: values.house_no || undefined,
      blood_type: values.blood_type || undefined,
      health_notes: values.health_notes || undefined,
      health_conditions: values.health_conditions
        .filter((row) => row.health_condition_id)
        .map((row) => ({
          health_condition_id: Number(row.health_condition_id),
          severity: row.severity || undefined,
          notes: row.notes || undefined,
          medication: row.medication || undefined,
        })),
    }

    try {
      const res = await apiFetch<{ data: Student }>(
        isEdit ? `/students/${student!.id}` : "/students",
        { method: isEdit ? "PUT" : "POST", body },
      )
      toast.success(isEdit ? t("updated") : t("created"))
      onSaved(res.data)
      close()
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error(tc("errors.generic"))
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={(v) => (v ? onOpenChange?.(true) : close())}>
      {showTrigger && (
        <ResponsiveSheetTrigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{isEdit ? t("editTitle") : t("createTitle")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              <div className="grid grid-cols-2 gap-3">
                {(
                  [
                    ["first_name", t("fields.firstName"), t("fields.firstNamePlaceholder")],
                    ["father_name", t("fields.fatherName"), t("fields.fatherNamePlaceholder")],
                    ["grandfather_name", t("fields.grandfatherName"), t("fields.grandfatherNamePlaceholder")],
                    ["mother_name", t("fields.motherName"), t("fields.motherNamePlaceholder")],
                  ] as const
                ).map(([name, label, placeholder]) => (
                  <FormField
                    key={name}
                    control={form.control}
                    name={name}
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{label}</FormLabel>
                        <FormControl>
                          <Input placeholder={placeholder} {...field} value={field.value ?? ""} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                ))}
              </div>

              <FormField
                control={form.control}
                name="gender"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.gender")}</FormLabel>
                    <div className="flex overflow-hidden rounded-lg border">
                      {(["male", "female"] as const).map((val, i) => (
                        <button
                          key={val}
                          type="button"
                          onClick={() => field.onChange(val)}
                          className={cn(
                            "flex-1 py-2 text-xs font-medium transition-colors",
                            i > 0 && "border-l",
                            field.value === val
                              ? "bg-primary text-primary-foreground"
                              : "bg-background text-foreground hover:bg-muted",
                          )}
                        >
                          {t(`fields.${val}`)}
                        </button>
                      ))}
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="date_of_birth"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.dateOfBirth")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          max={new Date().toISOString().slice(0, 10)}
                          captionLayout="dropdown"
                          placeholder={t("fields.dateOfBirth")}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="primary_phone"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.phone")}</FormLabel>
                      <FormControl>
                        <PhoneInput placeholder={t("fields.phonePlaceholder")} {...field} value={field.value ?? ""} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <FormField
                control={form.control}
                name="national_student_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.nationalId")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("fields.nationalIdPlaceholder")} {...field} value={field.value ?? ""} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <OptionalSection title={t("sheet.personal")}>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <FormField
                    control={form.control}
                    name="email"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("fields.email")}</FormLabel>
                        <FormControl>
                          <Input type="email" {...field} value={field.value ?? ""} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="citizenship"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("fields.citizenship")}</FormLabel>
                        <FormControl>
                          <Input {...field} value={field.value ?? ""} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="marital_status"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("fields.maritalStatus")}</FormLabel>
                        <Select value={field.value ?? ""} onValueChange={field.onChange}>
                          <FormControl>
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder={t("fields.maritalStatusPlaceholder")} />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            {(["single", "married", "divorced", "widowed"] as const).map((status) => (
                              <SelectItem key={status} value={status}>
                                {t(`fields.maritalStatuses.${status}`)}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>
                <FormField
                  control={form.control}
                  name="languages"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.languages")}</FormLabel>
                      <div className="flex flex-wrap gap-2">
                        {LANGUAGES.map((code) => {
                          const active = field.value?.includes(code)
                          return (
                            <button
                              key={code}
                              type="button"
                              onClick={() =>
                                field.onChange(
                                  active
                                    ? (field.value ?? []).filter((c) => c !== code)
                                    : [...(field.value ?? []), code],
                                )
                              }
                              className={cn(
                                "h-8 rounded-full border px-3 text-xs font-medium transition-colors",
                                active
                                  ? "border-primary bg-primary/10 text-primary"
                                  : "bg-background text-muted-foreground hover:bg-muted",
                              )}
                            >
                              {t(`fields.languageNames.${code}`)}
                            </button>
                          )
                        })}
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </OptionalSection>

              <OptionalSection title={t("wizard.birthplace")}>
                <AddressFields<FormValues> prefix="birth_" withHouseNo={false} />
              </OptionalSection>

              <OptionalSection title={t("wizard.currentAddress")}>
                <AddressFields<FormValues> />
              </OptionalSection>

              <OptionalSection title={t("wizard.health")}>
                <FormField
                  control={form.control}
                  name="blood_type"
                  render={({ field }) => (
                    <FormItem className="max-w-[200px]">
                      <FormLabel>{t("fields.bloodType")}</FormLabel>
                      <Select value={field.value ?? ""} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={t("fields.bloodTypePlaceholder")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {BLOOD_TYPES.map((type) => (
                            <SelectItem key={type} value={type}>
                              {type}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <div className="space-y-3">
                  {conditionRows.fields.map((row, index) => (
                    <div key={row.id} className="space-y-3 rounded-xl border p-3">
                      <div className="flex items-start gap-2">
                        <FormField
                          control={form.control}
                          name={`health_conditions.${index}.health_condition_id`}
                          render={({ field }) => (
                            <FormItem className="flex-1">
                              <Select value={field.value} onValueChange={field.onChange}>
                                <FormControl>
                                  <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t("wizard.selectCondition")} />
                                  </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                  {conditions.map((condition) => (
                                    <SelectItem key={condition.id} value={String(condition.id)}>
                                      {condition.name}
                                    </SelectItem>
                                  ))}
                                </SelectContent>
                              </Select>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="size-9 shrink-0 rounded-full text-destructive"
                          onClick={() => conditionRows.remove(index)}
                          aria-label={tc("actions.delete")}
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <FormField
                          control={form.control}
                          name={`health_conditions.${index}.severity`}
                          render={({ field }) => (
                            <FormItem>
                              <Select value={field.value ?? ""} onValueChange={field.onChange}>
                                <FormControl>
                                  <SelectTrigger className="w-full">
                                    <SelectValue placeholder={t("wizard.severity")} />
                                  </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                  {(["mild", "moderate", "severe"] as const).map((severity) => (
                                    <SelectItem key={severity} value={severity}>
                                      {t(`wizard.severities.${severity}`)}
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
                          name={`health_conditions.${index}.medication`}
                          render={({ field }) => (
                            <FormItem>
                              <FormControl>
                                <Input
                                  placeholder={t("wizard.medicationPlaceholder")}
                                  {...field}
                                  value={field.value ?? ""}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </div>
                    </div>
                  ))}
                  <Button
                    type="button"
                    variant="outline"
                    className="h-9 rounded-full"
                    onClick={() =>
                      conditionRows.append({
                        health_condition_id: "",
                        severity: "",
                        notes: "",
                        medication: "",
                      })
                    }
                  >
                    <Plus className="size-4" />
                    {t("wizard.addCondition")}
                  </Button>
                </div>

                <FormField
                  control={form.control}
                  name="health_notes"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.healthNotes")}</FormLabel>
                      <FormControl>
                        <textarea
                          {...field}
                          value={field.value ?? ""}
                          rows={3}
                          placeholder={t("fields.healthNotesPlaceholder")}
                          className="w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </OptionalSection>
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
