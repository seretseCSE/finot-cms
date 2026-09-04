"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useCallback, useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { AsyncCombobox, type AsyncComboboxOption } from "@/components/ui/async-combobox"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { Switch } from "@/components/ui/switch"
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
import { useGuardianSearch } from "@/components/students/registration/step-guardians"
import { ApiError, apiFetch } from "@/lib/api"
import { useRefList } from "@/lib/data/use-ref-list"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type {
  AcademicYear,
  ConcessionCategory,
  FeeConcession,
  FeeType,
  Paginated,
  Student,
  Term,
} from "@/lib/types"

const CATEGORIES: ConcessionCategory[] = [
  "sibling",
  "staff_child",
  "merit",
  "hardship",
  "scholarship",
  "other",
]

const FEE_TYPES: FeeType[] = [
  "registration",
  "one_time",
  "daily",
  "weekly",
  "monthly",
  "quarterly",
  "semester",
  "yearly",
]

const schema = z.object({
  subject: z.enum(["student", "guardian"]),
  category: z.enum(["sibling", "staff_child", "merit", "hardship", "scholarship", "other"]),
  discount_type: z.enum(["percentage", "fixed", "full_scholarship"]),
  discount_value: z.string().optional(),
  all_fees: z.boolean(),
  fee_types: z.array(z.string()),
  academic_year_id: z.string(),
  term_id: z.string(),
  reason: z.string().max(255).optional(),
  apply_to_open_invoices: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: (concession: FeeConcession) => void
  /** Pre-picked subject (student page shortcut) — hides the subject picker. */
  student?: Student | null
}

/**
 * Grant a standing concession: for a student or a guardian (all their
 * children), on all fees or specific fee types, for one year/semester or
 * lifetime. Manual grants are active immediately (the grantor IS finance).
 */
export function ConcessionSheet({ open, onOpenChange, onSaved, student }: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")
  const searchParents = useGuardianSearch()

  const { items: years } = useRefList<AcademicYear>("/academic-years", { enabled: open })
  const { items: terms } = useRefList<Term>("/terms?per_page=100", { enabled: open })
  const [studentOption, setStudentOption] = useState<AsyncComboboxOption | null>(null)
  const [guardianOption, setGuardianOption] = useState<AsyncComboboxOption | null>(null)
  const [subjectError, setSubjectError] = useState<string | null>(null)

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      subject: "student",
      category: "other",
      discount_type: "percentage",
      discount_value: "",
      all_fees: true,
      fee_types: [],
      academic_year_id: "",
      term_id: "",
      reason: "",
      apply_to_open_invoices: true,
    },
  })
  useLiveValidation(form)

  const subject = form.watch("subject")
  const discountType = form.watch("discount_type")
  const allFees = form.watch("all_fees")
  const yearId = form.watch("academic_year_id")

  useEffect(() => {
    if (open) {
      form.reset({
        subject: "student",
        category: "other",
        discount_type: "percentage",
        discount_value: "",
        all_fees: true,
        fee_types: [],
        academic_year_id: "",
        term_id: "",
        reason: "",
        apply_to_open_invoices: true,
      })
      setStudentOption(
        student
          ? {
              value: String(student.id),
              label: student.full_name,
              description: student.public_id ?? undefined,
            }
          : null
      )
      setGuardianOption(null)
      setSubjectError(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reset on open only
  }, [open, student])

  const searchStudents = useCallback(
    async (query: string): Promise<AsyncComboboxOption[]> => {
      const res = await apiFetch<Paginated<Student>>(
        `/students?search=${encodeURIComponent(query)}&per_page=10`
      )
      return res.data.map((s) => ({
        value: String(s.id),
        label: s.full_name,
        description: [s.public_id, s.current_enrollment?.grade_level?.name]
          .filter(Boolean)
          .join(" · "),
      }))
    },
    []
  )

  const yearTerms = terms.filter((term) => String(term.academic_year_id) === yearId)

  async function onSubmit(values: FormValues) {
    const subjectId = values.subject === "student" ? studentOption?.value : guardianOption?.value
    if (!subjectId) {
      setSubjectError(t("concessions.subjectRequired"))
      return
    }

    try {
      const res = await apiFetch<{ data: FeeConcession }>("/fee-concessions", {
        method: "POST",
        body: {
          student_id: values.subject === "student" ? Number(subjectId) : undefined,
          parent_id: values.subject === "guardian" ? Number(subjectId) : undefined,
          category: values.category,
          discount_type: values.discount_type,
          discount_value:
            values.discount_type === "full_scholarship"
              ? undefined
              : Number(values.discount_value || 0),
          fee_types: values.all_fees ? undefined : values.fee_types,
          academic_year_id: values.academic_year_id ? Number(values.academic_year_id) : undefined,
          term_id: values.term_id ? Number(values.term_id) : undefined,
          reason: values.reason || undefined,
          apply_to_open_invoices: values.apply_to_open_invoices,
        },
      })
      toast.success(t("concessions.created"))
      onSaved(res.data)
      onOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          if (field === "student_id" || field === "parent_id") {
            setSubjectError(messages[0])
          } else {
            form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
          }
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("concessions.createTitle")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex h-full flex-col">
            <ResponsiveSheetBody className="space-y-5">
              {/* Who it covers */}
              {!student && (
                <FormField
                  control={form.control}
                  name="subject"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("concessions.subject")}</FormLabel>
                      <div className="grid grid-cols-2 gap-2">
                        {(["student", "guardian"] as const).map((kind) => (
                          <button
                            key={kind}
                            type="button"
                            onClick={() => {
                              field.onChange(kind)
                              setSubjectError(null)
                            }}
                            className={
                              field.value === kind
                                ? "rounded-xl border border-primary bg-primary/5 px-3 py-2.5 text-sm font-medium text-primary"
                                : "rounded-xl border px-3 py-2.5 text-sm text-muted-foreground hover:bg-accent/50"
                            }
                          >
                            {t(`concessions.subjects.${kind}`)}
                          </button>
                        ))}
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {subject === "guardian"
                          ? t("concessions.guardianHint")
                          : t("concessions.studentHint")}
                      </p>
                    </FormItem>
                  )}
                />
              )}

              {(student || subject === "student") ? (
                <div className="space-y-1.5">
                  <FormLabel>{t("invoices.student")}</FormLabel>
                  <AsyncCombobox
                    value={studentOption}
                    onChange={(option) => {
                      setStudentOption(option)
                      setSubjectError(null)
                    }}
                    fetcher={searchStudents}
                    placeholder={t("invoices.selectStudent")}
                    disabled={!!student}
                  />
                  {subjectError && (
                    <p className="text-sm text-destructive">{subjectError}</p>
                  )}
                </div>
              ) : (
                <div className="space-y-1.5">
                  <FormLabel>{t("concessions.subjects.guardian")}</FormLabel>
                  <AsyncCombobox
                    value={guardianOption}
                    onChange={(option) => {
                      setGuardianOption(option)
                      setSubjectError(null)
                    }}
                    fetcher={(query) => searchParents(query)}
                    placeholder={t("concessions.selectGuardian")}
                  />
                  {subjectError && (
                    <p className="text-sm text-destructive">{subjectError}</p>
                  )}
                </div>
              )}

              {/* What it grants */}
              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="category"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("concessions.category")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {CATEGORIES.map((c) => (
                            <SelectItem key={c} value={c}>
                              {t(`concessions.categories.${c}`)}
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
                  name="discount_type"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("scholarship.type")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="percentage">
                            {t("scholarship.types.percentage")}
                          </SelectItem>
                          <SelectItem value="fixed">{t("scholarship.types.fixed")}</SelectItem>
                          <SelectItem value="full_scholarship">
                            {t("scholarship.types.full_scholarship")}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              {discountType !== "full_scholarship" && (
                <FormField
                  control={form.control}
                  name="discount_value"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>
                        {discountType === "percentage"
                          ? t("concessions.percentValue")
                          : t("concessions.fixedValue")}
                      </FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          inputMode="decimal"
                          min={0}
                          max={discountType === "percentage" ? 100 : undefined}
                          placeholder={discountType === "percentage" ? "10" : "500"}
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}

              {/* Which fees */}
              <FormField
                control={form.control}
                name="all_fees"
                render={({ field }) => (
                  <FormItem>
                    <label className="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-muted/30 px-3 py-2">
                      <span className="text-sm font-medium">{t("concessions.allFees")}</span>
                      <FormControl>
                        <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                      </FormControl>
                    </label>
                  </FormItem>
                )}
              />
              {!allFees && (
                <FormField
                  control={form.control}
                  name="fee_types"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("concessions.feeTypes")}</FormLabel>
                      <div className="grid grid-cols-2 gap-2">
                        {FEE_TYPES.map((type) => (
                          <label
                            key={type}
                            className="flex min-h-10 items-center gap-2 rounded-lg border px-3 text-sm"
                          >
                            <Checkbox
                              checked={field.value.includes(type)}
                              onCheckedChange={(checked) =>
                                field.onChange(
                                  checked
                                    ? [...field.value, type]
                                    : field.value.filter((v) => v !== type)
                                )
                              }
                            />
                            {t(`feeTypes.${type}`)}
                          </label>
                        ))}
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}

              {/* When it applies */}
              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="academic_year_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("invoices.academicYear")}</FormLabel>
                      <Select
                        value={field.value || "lifetime"}
                        onValueChange={(v) => {
                          field.onChange(v === "lifetime" ? "" : v)
                          form.setValue("term_id", "")
                        }}
                      >
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="lifetime">{t("concessions.lifetime")}</SelectItem>
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
                {yearId && yearTerms.length > 0 && (
                  <FormField
                    control={form.control}
                    name="term_id"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("invoices.semester")}</FormLabel>
                        <Select
                          value={field.value || "all"}
                          onValueChange={(v) => field.onChange(v === "all" ? "" : v)}
                        >
                          <FormControl>
                            <SelectTrigger>
                              <SelectValue />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            <SelectItem value="all">{t("concessions.wholeYear")}</SelectItem>
                            {yearTerms.map((term) => (
                              <SelectItem key={term.id} value={String(term.id)}>
                                {term.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                )}
              </div>

              <FormField
                control={form.control}
                name="reason"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("scholarship.reason")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("concessions.reasonPlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {/* Reaches bills issued moments before the grant (a fresh
                  registration invoice) — paid history is never touched. */}
              <FormField
                control={form.control}
                name="apply_to_open_invoices"
                render={({ field }) => (
                  <FormItem className="flex items-center justify-between rounded-2xl border p-4">
                    <div className="space-y-0.5 pr-4">
                      <FormLabel>{t("concessions.applyOpenInvoices")}</FormLabel>
                      <p className="text-xs text-muted-foreground">
                        {t("concessions.applyOpenInvoicesHint")}
                      </p>
                    </div>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
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
              <Button type="submit" className="h-11 flex-1" loading={form.formState.isSubmitting}>
                {t("concessions.grant")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
