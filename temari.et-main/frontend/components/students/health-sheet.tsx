"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus, Trash2 } from "lucide-react"
import { useEffect, useState } from "react"
import { useFieldArray, useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { HealthCondition, Student } from "@/lib/types"

import { BLOOD_TYPES } from "./registration/schema"

const SEVERITIES = ["mild", "moderate", "severe"] as const

const schema = z.object({
  blood_type: z.enum(BLOOD_TYPES).or(z.literal("")).optional(),
  health_notes: z.string().max(2000).optional(),
  health_conditions: z.array(
    z.object({
      health_condition_id: z.string().min(1, "Pick a condition"),
      severity: z.enum(SEVERITIES).or(z.literal("")).optional(),
      medication: z.string().max(255).optional(),
      notes: z.string().max(1000).optional(),
    }),
  ),
})

type FormValues = z.infer<typeof schema>

/**
 * Edit the student's health profile from the detail page — the same catalog
 * picker + severity/medication rows as the registration wizard's health step.
 */
export function HealthSheet({
  student,
  open,
  onOpenChange,
  onSaved,
}: {
  student: Student
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")

  const [conditions, setConditions] = useState<HealthCondition[]>([])

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { blood_type: "", health_notes: "", health_conditions: [] },
  })
  useLiveValidation(form)
  const rows = useFieldArray({ control: form.control, name: "health_conditions" })

  useEffect(() => {
    if (!open) return
    let cancelled = false

    form.reset({
      blood_type: student.blood_type ?? "",
      health_notes: student.health_notes ?? "",
      health_conditions: (student.health_conditions ?? []).map((condition) => ({
        health_condition_id: String(condition.health_condition_id),
        severity: condition.severity ?? "",
        medication: condition.medication ?? "",
        notes: condition.notes ?? "",
      })),
    })

    apiFetch<{ data: HealthCondition[] }>("/health-conditions")
      .then((res) => !cancelled && setConditions(res.data))
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [open, student, form])

  async function onSubmit(values: FormValues) {
    try {
      // The update endpoint requires the identity essentials — echo them back.
      await apiFetch(`/students/${student.id}`, {
        method: "PUT",
        body: {
          first_name: student.first_name,
          father_name: student.father_name,
          gender: student.gender,
          blood_type: values.blood_type || null,
          health_notes: values.health_notes || null,
          health_conditions: values.health_conditions
            .filter((row) => row.health_condition_id)
            .map((row) => ({
              health_condition_id: Number(row.health_condition_id),
              severity: row.severity || undefined,
              medication: row.medication || undefined,
              notes: row.notes || undefined,
            })),
        },
      })
      toast.success(t("health.updated"))
      onSaved()
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
          <ResponsiveSheetTitle>{t("health.editTitle")}</ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
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
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t("wizard.conditions")}
                </p>

                {rows.fields.map((row, index) => (
                  <div key={row.id} className="space-y-3 rounded-2xl border p-3">
                    <div className="flex items-start gap-2">
                      <FormField
                        control={form.control}
                        name={`health_conditions.${index}.health_condition_id`}
                        render={({ field }) => (
                          <FormItem className="flex-1">
                            <FormLabel className="sr-only">{t("wizard.condition")}</FormLabel>
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
                        onClick={() => rows.remove(index)}
                        aria-label={tc("actions.delete")}
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
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
                                {SEVERITIES.map((severity) => (
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
                      <FormField
                        control={form.control}
                        name={`health_conditions.${index}.notes`}
                        render={({ field }) => (
                          <FormItem>
                            <FormControl>
                              <Input
                                placeholder={t("wizard.conditionNotesPlaceholder")}
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
                  className="h-10 rounded-full"
                  onClick={() =>
                    rows.append({ health_condition_id: "", severity: "", medication: "", notes: "" })
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
