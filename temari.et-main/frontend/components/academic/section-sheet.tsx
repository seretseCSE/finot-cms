"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { Employee, GradeLevel, Paginated, Section } from "@/lib/types"
import { cn } from "@/lib/utils"

const schema = z.object({
  grade_level_id: z.string().min(1, "Grade level is required"),
  name: z.string().min(1, "Section name is required").max(50),
  room_number: z.string().max(30).optional(),
  capacity: z
    .string()
    .optional()
    .refine((v) => !v || (Number(v) >= 1 && Number(v) <= 1000), "Capacity must be 1–1000"),
  homeroom_employee_id: z.string().optional(),
  is_active: z.boolean(),
})

const NO_HOMEROOM = "none" 

type FormValues = z.infer<typeof schema>

interface Props {
  gradeLevels: GradeLevel[]
  /** The year the homeroom applies to (defaults server-side to the active year). */
  academicYearId?: number | null
  /** Existing sections — used to suggest the next free letter per grade. */
  existingSections?: Section[]
  /** When provided, the sheet edits this section; otherwise it creates one. */
  section?: Section | null
  onSaved: (section: Section) => void
  open?: boolean
  onOpenChange?: (open: boolean) => void
  showTrigger?: boolean
}

export function SectionSheet({
  gradeLevels,
  academicYearId,
  existingSections = [],
  section,
  onSaved,
  open,
  onOpenChange,
  showTrigger,
}: Props) {
  const { t } = useTranslation("academic")
  const { t: tc } = useTranslation("common")
  const isEdit = !!section

  // School-wide workspace: creating requires naming the target branch.
  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      grade_level_id: "",
      name: "",
      room_number: "",
      capacity: "",
      homeroom_employee_id: "",
      is_active: true,
    },
  })
  useLiveValidation(form)

  // Suggest the next free letter for the picked grade (A, B, C…) — only while
  // the user hasn't typed their own name.
  const pickedGrade = useWatch({ control: form.control, name: "grade_level_id" })
  const lastSuggestion = useRef("")
  useEffect(() => {
    if (isEdit || !pickedGrade) return
    const taken = new Set(
      existingSections
        .filter((s) => String(s.grade_level_id) === pickedGrade)
        .filter((s) => branchId == null || s.branch_id === branchId)
        .map((s) => s.name.toUpperCase()),
    )
    const next = "ABCDEFGHIJKLMNOPQRSTUVWXYZ".split("").find((letter) => !taken.has(letter)) ?? ""
    const current = form.getValues("name")
    if (!current || current === lastSuggestion.current || /^[A-Z]$/.test(current)) {
      form.setValue("name", next)
      lastSuggestion.current = next
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pickedGrade, isEdit])

  // Homeroom candidates: branch staff holding an active teacher position. In
  // the school-wide workspace the list follows the picked target branch.
  const [teachers, setTeachers] = useState<Employee[]>([])
  useEffect(() => {
    if (!open || (needsBranch && branchId == null)) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- no branch, no candidates
      setTeachers([])
      return
    }
    const branchParam = branchId != null ? `&branch_id=${branchId}` : ""
    apiFetch<Paginated<Employee>>(
      `/employees?job_title=teacher&is_active=true&per_page=100${branchParam}`,
    )
      .then((res) => setTeachers(res.data))
      .catch(() => {})
  }, [open, needsBranch, branchId])

  useEffect(() => {
    if (!open) return
    form.reset(
      section
        ? {
            grade_level_id: String(section.grade_level_id),
            name: section.name,
            room_number: section.room_number ?? "",
            capacity: section.capacity ? String(section.capacity) : "",
            homeroom_employee_id: section.homeroom_employee_id
              ? String(section.homeroom_employee_id)
              : "",
            is_active: section.is_active,
          }
        : {
            grade_level_id: "",
            name: "",
            room_number: "",
            capacity: "",
            homeroom_employee_id: "",
            is_active: true,
          },
    )
    // eslint-disable-next-line react-hooks/set-state-in-effect -- seed sheet state on open
    setBranchId(null)
    setBranchError(null)
  }, [open, section, form])

  function close() {
    onOpenChange?.(false)
    if (!isEdit) form.reset()
  }

  async function onSubmit(values: FormValues) {
    if (!isEdit && needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    const body = {
      ...(!isEdit && branchId != null ? { branch_id: branchId } : {}),
      grade_level_id: Number(values.grade_level_id),
      name: values.name,
      room_number: values.room_number || null,
      capacity: values.capacity ? Number(values.capacity) : null,
      // Homeroom is YEAR-scoped: it lands on the selected academic year.
      homeroom_employee_id:
        values.homeroom_employee_id && values.homeroom_employee_id !== NO_HOMEROOM
          ? Number(values.homeroom_employee_id)
          : null,
      ...(academicYearId ? { academic_year_id: academicYearId } : {}),
      is_active: values.is_active,
    }

    try {
      const endpoint = isEdit ? `/sections/${section!.id}` : "/sections"
      const res = await apiFetch<{ data: Section }>(endpoint, {
        method: isEdit ? "PUT" : "POST",
        body,
      })
      toast.success(isEdit ? t("sections.updated") : t("sections.created"))
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
            {t("sections.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("sections.editTitle") : t("sections.createTitle")}
          </ResponsiveSheetTitle>
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
              <FormField
                control={form.control}
                name="grade_level_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("sections.grade")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange} disabled={isEdit}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder={t("sections.gradePlaceholder")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent emptyNotice={tc("emptySelect.grades")}>
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
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("sections.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("sections.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="room_number"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("sections.roomNumber")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("sections.roomNumberPlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="capacity"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("sections.capacity")}</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        inputMode="numeric"
                        min={1}
                        placeholder={t("sections.capacityPlaceholder")}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="homeroom_employee_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("sections.homeroomName")}</FormLabel>
                    <Select
                      value={field.value || NO_HOMEROOM}
                      onValueChange={(v) => field.onChange(v === NO_HOMEROOM ? "" : v)}
                    >
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t("sections.homeroomPlaceholder")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value={NO_HOMEROOM}>{t("sections.noHomeroom")}</SelectItem>
                        {teachers.map((teacher) => (
                          <SelectItem key={teacher.id} value={String(teacher.id)}>
                            {teacher.full_name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">{t("sections.homeroomHint")}</p>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_active"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tc("states.active")}</FormLabel>
                    <div className="flex rounded-lg border overflow-hidden">
                      {([true, false] as const).map((val, i) => (
                        <button
                          key={String(val)}
                          type="button"
                          onClick={() => field.onChange(val)}
                          className={cn(
                            "flex-1 py-2 text-xs font-medium transition-colors",
                            i > 0 && "border-l",
                            field.value === val
                              ? "bg-primary text-primary-foreground"
                              : "bg-background hover:bg-muted text-foreground",
                          )}
                        >
                          {val ? tc("states.active") : tc("states.inactive")}
                        </button>
                      ))}
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="button" variant="outline" className="flex-1 h-11" onClick={close}>
                {tc("actions.cancel")}
              </Button>
              <Button type="submit" className="flex-1 h-11" loading={form.formState.isSubmitting}>
                {tc("actions.save")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
