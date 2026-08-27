"use client"

import { Plus, X } from "lucide-react"
import type { UseFieldArrayReturn, UseFormReturn } from "react-hook-form"

import type { EmployeeFormValues } from "@/components/employees/wizard/schema"
import { RecordAttachments, type AttachmentProps } from "@/components/employees/wizard/steps/shared"
import { Button } from "@/components/ui/button"
import { Combobox } from "@/components/ui/combobox"
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { EDUCATIONAL_INSTITUTIONS, FIELDS_OF_GRADUATION, QUALIFICATION_LEVELS } from "@/lib/data"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

interface Props {
  active: boolean
  form: UseFormReturn<EmployeeFormValues>
  qualificationArray: UseFieldArrayReturn<EmployeeFormValues, "qualifications">
  /** Live qualification values — `fields` alone does not re-render on edits. */
  watchedQualifications: (
    | { id?: number; education_level?: string; field_of_study?: string }
    | undefined
  )[]
  attachmentProps: AttachmentProps
}

/** Credentials the person holds, each with its own scans (degree, transcript). */
export function QualificationsStep({
  active,
  form,
  qualificationArray,
  watchedQualifications,
  attachmentProps,
}: Props) {
  const { t } = useTranslation("employees")

  return (
    <div className={cn("space-y-4", !active && "hidden")}>
      <p className="text-xs text-muted-foreground">{t("qualifications.hint")}</p>
      {qualificationArray.fields.map((row, index) => (
        <div key={row.id} className="space-y-3 rounded-xl border p-3">
          <div className="flex items-center justify-between">
            <p className="text-sm font-medium">
              {watchedQualifications[index]?.education_level
                ? t(`qualificationLevels.${watchedQualifications[index]!.education_level}`)
                : t("qualifications.new")}
            </p>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-8 text-muted-foreground hover:text-destructive"
              onClick={() => qualificationArray.remove(index)}
              aria-label={t("qualifications.remove")}
            >
              <X className="size-4" />
            </Button>
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FormField
              control={form.control}
              name={`qualifications.${index}.education_level`}
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("fields.educationLevel")}</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t("fields.educationLevelPlaceholder")} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {QUALIFICATION_LEVELS.map((level) => (
                        <SelectItem key={level} value={level}>
                          {t(`qualificationLevels.${level}`)}
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
              name={`qualifications.${index}.field_of_study`}
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("fields.fieldOfStudy")}</FormLabel>
                  <FormControl>
                    <Combobox
                      options={FIELDS_OF_GRADUATION}
                      value={field.value}
                      onChange={field.onChange}
                      placeholder={t("fields.fieldOfStudyPlaceholder")}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name={`qualifications.${index}.institution`}
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("fields.institution")}</FormLabel>
                  <FormControl>
                    <Combobox
                      options={EDUCATIONAL_INSTITUTIONS}
                      value={field.value}
                      onChange={field.onChange}
                      placeholder={t("fields.institutionPlaceholder")}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name={`qualifications.${index}.graduation_year`}
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("fields.graduationYear")}</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      inputMode="numeric"
                      min={1950}
                      max={2100}
                      placeholder={t("fields.graduationYearPlaceholder")}
                      {...field}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </div>

          {/* Scans for THIS credential (degree, transcript…) */}
          <RecordAttachments
            kind="qualification"
            recordId={watchedQualifications[index]?.id}
            anchor={
              watchedQualifications[index]?.id
                ? `q:${watchedQualifications[index]!.id}`
                : `qi:${index}`
            }
            {...attachmentProps}
          />
        </div>
      ))}
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="h-9"
        onClick={() =>
          qualificationArray.append({
            education_level: "",
            field_of_study: "",
            institution: "",
            graduation_year: "",
          })
        }
      >
        <Plus className="size-4" />
        {t("qualifications.add")}
      </Button>
    </div>
  )
}
