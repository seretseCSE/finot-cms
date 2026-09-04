"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus } from "lucide-react"
import { useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { Combobox } from "@/components/ui/combobox"
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
import { addisToday } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { AcademicYear, FeeStructure, Invoice, Paginated, Student } from "@/lib/types"

const schema = z.object({
  student_id: z.string().min(1, "Student is required"),
  fee_structure_id: z.string().optional(),
  academic_year_id: z.string().min(1, "Academic year is required"),
  title: z.string().min(2, "Title is required").max(255),
  amount: z.string().min(1, "Amount is required"),
  due_date: z.string().optional(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  students: Student[]
  academicYears: AcademicYear[]
  feeStructures?: FeeStructure[]
  onSaved: (invoice: Invoice) => void
  open: boolean
  onOpenChange: (open: boolean) => void
  showTrigger?: boolean
}

export function InvoiceSheet({
  students: contextStudents,
  academicYears,
  feeStructures: contextFees = [],
  onSaved,
  open,
  onOpenChange,
  showTrigger,
}: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")

  // School-wide workspace: the invoice targets a named branch; students and
  // years then follow that branch instead of the context props.
  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)
  const [branchStudents, setBranchStudents] = useState<Student[]>([])
  const [branchYears, setBranchYears] = useState<AcademicYear[]>([])
  const [branchFees, setBranchFees] = useState<FeeStructure[]>([])

  const students = needsBranch ? branchStudents : contextStudents
  const years = needsBranch ? branchYears : academicYears
  const fees = needsBranch ? branchFees : contextFees

  const currentYear = years.find((y) => y.is_current)

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      student_id: "",
      fee_structure_id: "",
      academic_year_id: currentYear ? String(currentYear.id) : "",
      title: "",
      amount: "",
      due_date: "",
    },
  })
  useLiveValidation(form)

  // Linking a fee prefills the bill and stamps the fee's collection accounts
  // and concession scope onto the invoice; "none" keeps it fully ad-hoc.
  function selectFee(id: string) {
    form.setValue("fee_structure_id", id)
    const fee = fees.find((f) => String(f.id) === id)
    if (!fee) return
    form.setValue("title", fee.name, { shouldValidate: true })
    form.setValue("amount", fee.amount, { shouldValidate: true })
    form.setValue("due_date", fee.due_on ?? "")
    form.setValue("academic_year_id", String(fee.academic_year_id), { shouldValidate: true })
  }

  useEffect(() => {
    if (!open || !needsBranch || branchId == null) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- no branch, no options
      setBranchStudents([])
      setBranchYears([])
      setBranchFees([])
      return
    }
    let cancelled = false
    apiFetch<Paginated<Student>>(`/students?branch_id=${branchId}&per_page=100`)
      .then((res) => !cancelled && setBranchStudents(res.data))
      .catch(() => {})
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
    apiFetch<Paginated<FeeStructure>>(`/fee-structures?branch_id=${branchId}&per_page=100`)
      .then((res) => !cancelled && setBranchFees(res.data))
      .catch(() => {})
    return () => {
      cancelled = true
    }
  }, [open, needsBranch, branchId, form])

  useEffect(() => {
    if (open) {
      form.reset({
        student_id: "",
        fee_structure_id: "",
        academic_year_id: currentYear ? String(currentYear.id) : "",
        title: "",
        amount: "",
        due_date: "",
      })
      // eslint-disable-next-line react-hooks/set-state-in-effect -- seed sheet state on open
      setBranchId(null)
      setBranchError(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  async function onSubmit(values: FormValues) {
    if (needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    try {
      const res = await apiFetch<{ data: Invoice }>("/invoices", {
        method: "POST",
        body: {
          ...(branchId != null ? { branch_id: branchId } : {}),
          student_id: Number(values.student_id),
          fee_structure_id: values.fee_structure_id
            ? Number(values.fee_structure_id)
            : null,
          academic_year_id: Number(values.academic_year_id),
          title: values.title,
          amount: Number(values.amount),
          due_date: values.due_date || null,
        },
      })
      toast.success(t("invoices.created"))
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

  const studentOptions = students.map((s) => s.full_name)

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      {showTrigger && (
        <ResponsiveSheetTrigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("invoices.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {t("invoices.createTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit)}
            className="flex h-full flex-col"
          >
            <ResponsiveSheetBody className="space-y-5">
              <BranchField
                value={branchId}
                onChange={(id) => {
                  setBranchId(id)
                  setBranchError(null)
                }}
                error={branchError}
              />
              <FormField
                control={form.control}
                name="student_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("invoices.student")}</FormLabel>
                    <FormControl>
                      <Combobox
                        options={studentOptions}
                        value={
                          students.find((s) => String(s.id) === field.value)
                            ?.full_name ?? ""
                        }
                        onChange={(name) => {
                          const match = students.find(
                            (s) => s.full_name === name
                          )
                          field.onChange(match ? String(match.id) : "")
                        }}
                        placeholder={t("invoices.selectStudent")}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              {fees.length > 0 && (
                <FormField
                  control={form.control}
                  name="fee_structure_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("invoices.feeOptional")}</FormLabel>
                      <Select
                        value={field.value || "none"}
                        onValueChange={(id) => selectFee(id === "none" ? "" : id)}
                      >
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue placeholder={t("invoices.noFee")} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="none">{t("invoices.noFee")}</SelectItem>
                          {fees.map((fee) => (
                            <SelectItem key={fee.id} value={String(fee.id)}>
                              {fee.academic_year_name
                                ? `${fee.name} — ${fee.academic_year_name}`
                                : fee.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">
                        {t("invoices.feeHint")}
                      </p>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              )}
              <FormField
                control={form.control}
                name="academic_year_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("invoices.academicYear")}</FormLabel>
                    <Select
                      value={field.value}
                      onValueChange={(id) => {
                        field.onChange(id)
                        // A linked fee from another year is stale — unlink it.
                        const feeId = form.getValues("fee_structure_id")
                        const fee = fees.find((f) => String(f.id) === feeId)
                        if (fee && String(fee.academic_year_id) !== id) {
                          form.setValue("fee_structure_id", "")
                        }
                      }}
                    >
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder={t("invoices.selectYear")} />
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
              <FormField
                control={form.control}
                name="title"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("invoices.name")}</FormLabel>
                    <FormControl>
                      <Input
                        placeholder={t("invoices.namePlaceholder")}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="amount"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("invoices.amount")}</FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          inputMode="decimal"
                          min={0}
                          placeholder={t("invoices.amountPlaceholder")}
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="due_date"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("invoices.dueDate")}</FormLabel>
                      <FormControl>
                        <DatePicker
                          value={field.value}
                          onChange={field.onChange}
                          onBlur={field.onBlur}
                          min={addisToday()}
                          placeholder={t("invoices.dueDate")}
                        />
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
              <Button
                type="submit"
                className="h-11 flex-1"
                loading={form.formState.isSubmitting}
              >
                {tc("actions.save")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
