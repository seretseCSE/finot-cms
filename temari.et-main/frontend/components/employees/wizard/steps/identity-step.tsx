"use client"

import { ImagePlus, X } from "lucide-react"
import type { UseFormReturn } from "react-hook-form"

import { MARITAL_STATUSES, type EmployeeFormValues } from "@/components/employees/wizard/schema"
import { Button } from "@/components/ui/button"
import { Combobox } from "@/components/ui/combobox"
import { DatePicker } from "@/components/ui/date-picker"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { PhoneInput } from "@/components/ui/phone-input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useTranslation } from "@/lib/i18n"
import type { Employee } from "@/lib/types"
import { cn } from "@/lib/utils"

/** Employees must be at least 15 (the BirthDate rule) — the picker simply
 * cannot offer a younger date, and opens on the latest allowed year. */
function employeeBirthMax(): string {
  const date = new Date()
  date.setFullYear(date.getFullYear() - 15)
  return date.toISOString().slice(0, 10)
}

interface Props {
  active: boolean
  form: UseFormReturn<EmployeeFormValues>
  employee?: Employee | null
  photoFile: File | null
  setPhotoFile: (file: File | null) => void
  photoPreview: string | null
  photoInputRef: React.RefObject<HTMLInputElement | null>
  nationalities: string[]
}

/** Who the person is: photo, patronymic names, contact and personal facts. */
export function IdentityStep({
  active,
  form,
  employee,
  photoFile,
  setPhotoFile,
  photoPreview,
  photoInputRef,
  nationalities,
}: Props) {
  const { t } = useTranslation("employees")

  // The photo tile accepts a dragged image as well as a tap.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: "image/jpeg,image/png,image/webp",
    onFiles: ([file]) => setPhotoFile(file),
  })

  return (
    <div className={cn("space-y-4", !active && "hidden")}>
      <div
        {...dropProps}
        className={cn("flex items-center gap-4 rounded-2xl", dragOver && DROP_ACTIVE)}
      >
        <button
          type="button"
          onClick={() => photoInputRef.current?.click()}
          className="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-dashed bg-muted/30 text-muted-foreground transition-colors hover:bg-muted/50"
        >
          {photoPreview || employee?.photo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- local preview / signed URL
            <img
              src={photoPreview ?? employee?.photo_url ?? ""}
              alt=""
              className="size-full object-cover"
            />
          ) : (
            <ImagePlus className="size-6" />
          )}
        </button>
        <div className="min-w-0">
          <p className="text-sm font-medium">{t("photo.label")}</p>
          <p className="text-xs text-muted-foreground">{t("photo.hint")}</p>
          {photoFile ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="mt-1 h-7 rounded-full px-2 text-xs text-destructive"
              onClick={() => setPhotoFile(null)}
            >
              <X className="size-3" /> {t("photo.remove")}
            </Button>
          ) : null}
        </div>
        <input
          ref={photoInputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={(e) => {
            takeFiles(e.target.files)
            e.target.value = ""
          }}
        />
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <FormField
          control={form.control}
          name="first_name"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.firstName")}</FormLabel>
              <FormControl>
                <Input placeholder={t("fields.firstNamePlaceholder")} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="father_name"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.fatherName")}</FormLabel>
              <FormControl>
                <Input placeholder={t("fields.fatherNamePlaceholder")} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="grandfather_name"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.grandfatherName")}</FormLabel>
              <FormControl>
                <Input placeholder={t("fields.grandfatherNamePlaceholder")} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField
          control={form.control}
          name="phone"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.phone")}</FormLabel>
              <FormControl>
                <PhoneInput placeholder={t("fields.phonePlaceholder")} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="email"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.email")}</FormLabel>
              <FormControl>
                <Input type="email" placeholder={t("fields.emailPlaceholder")} {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="gender"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.gender")}</FormLabel>
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("fields.selectGender")} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="female">{t("genders.female")}</SelectItem>
                  <SelectItem value="male">{t("genders.male")}</SelectItem>
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
        <FormField
          control={form.control}
          name="birth_date"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.birthDate")}</FormLabel>
              <FormControl>
                <DatePicker
                  value={field.value}
                  onChange={field.onChange}
                  onBlur={field.onBlur}
                  max={employeeBirthMax()}
                  placeholder={t("fields.birthDate")}
                />
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
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t("fields.selectMaritalStatus")} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {MARITAL_STATUSES.map((status) => (
                    <SelectItem key={status} value={status}>
                      {t(`maritalStatuses.${status}`)}
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
          name="nationality"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.nationality")}</FormLabel>
              <FormControl>
                <Combobox
                  options={nationalities}
                  value={field.value}
                  onChange={field.onChange}
                  placeholder={t("fields.nationalityPlaceholder")}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
    </div>
  )
}
