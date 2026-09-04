"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useEffect } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import {
  AcademicYearPicker,
  activeAcademicYearId,
} from "@/components/students/academic-year-select"
import { useSchoolDirectorySearch } from "@/components/students/registration/step-enrollment-fees"
import { AsyncCombobox } from "@/components/ui/async-combobox"
import { Button } from "@/components/ui/button"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { AcademicYear, GradeLevel, Section, Student, StudentEnrollment } from "@/lib/types"

const schema = z.object({
  academic_year_id: z.string().min(1, "Academic year is required"),
  grade_level_id: z.string().min(1, "Grade level is required"),
  section_id: z.string().optional(),
  previous_school_id: z.string().optional(),
  previous_school_label: z.string().optional(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  student: Student | null
  academicYears: AcademicYear[]
  gradeLevels: GradeLevel[]
  sections: Section[]
  onEnrolled: (studentId: number, enrollment: StudentEnrollment) => void
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function EnrollStudentSheet({
  student,
  academicYears,
  gradeLevels,
  sections,
  onEnrolled,
  open,
  onOpenChange,
}: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const directory = useSchoolDirectorySearch()

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      academic_year_id: "",
      grade_level_id: "",
      section_id: "",
      previous_school_id: "",
      previous_school_label: "",
    },
  })
  useLiveValidation(form)

  const gradeId = useWatch({ control: form.control, name: "grade_level_id" })
  const gradeSections = sections.filter(
    (section) => !gradeId || String(section.grade_level_id) === gradeId,
  )
  useEffect(() => {
    if (open)
      form.reset({
        // The active year is preselected — enrollments belong to the current
        // cycle unless the registrar deliberately picks another.
        academic_year_id: activeAcademicYearId(academicYears),
        grade_level_id: "",
        section_id: "",
        previous_school_id: "",
        previous_school_label: "",
      })
  }, [open, form, academicYears])

  async function onSubmit(values: FormValues) {
    if (!student) return
    try {
      const res = await apiFetch<{ data: StudentEnrollment }>(
        `/students/${student.id}/enrollments`,
        {
          method: "POST",
          body: {
            academic_year_id: Number(values.academic_year_id),
            grade_level_id: Number(values.grade_level_id),
            section_id: values.section_id ? Number(values.section_id) : undefined,
            previous_school_id: values.previous_school_id
              ? Number(values.previous_school_id)
              : undefined,
          },
        },
      )
      toast.success(t("enroll.enrolled"))
      onEnrolled(student.id, res.data)
      onOpenChange(false)
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
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {t("enroll.title")}
            {student ? ` — ${student.full_name}` : ""}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              <FormField
                control={form.control}
                name="academic_year_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("enroll.academicYear")}</FormLabel>
                    <AcademicYearPicker
                      years={academicYears}
                      value={field.value}
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
                      value={field.value}
                      onValueChange={(val) => {
                        field.onChange(val)
                        form.setValue("section_id", "")
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
                    <FormMessage />
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
                {t("enroll.action")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
