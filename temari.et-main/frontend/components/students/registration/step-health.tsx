"use client"

import { Plus, Trash2 } from "lucide-react"
import { useFieldArray, type UseFormReturn } from "react-hook-form"

import { Button } from "@/components/ui/button"
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useTranslation } from "@/lib/i18n"
import type { HealthCondition } from "@/lib/types"

import { BLOOD_TYPES, type RegistrationValues } from "./schema"

const SEVERITIES = ["mild", "moderate", "severe"] as const

interface Props {
  form: UseFormReturn<RegistrationValues>
  conditions: HealthCondition[]
}

export function StepHealth({ form, conditions }: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")

  const rows = useFieldArray({ control: form.control, name: "health_conditions" })

  return (
    <section className="space-y-4">
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
                      <Input placeholder={t("wizard.medicationPlaceholder")} {...field} value={field.value ?? ""} />
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
                      <Input placeholder={t("wizard.conditionNotesPlaceholder")} {...field} value={field.value ?? ""} />
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
            rows.append({ health_condition_id: "", severity: "", notes: "", medication: "" })
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
    </section>
  )
}
