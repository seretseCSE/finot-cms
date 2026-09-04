"use client"

import { IdCard, ImagePlus, KeyRound, Smartphone, X } from "lucide-react"
import { useRef } from "react"
import type { UseFormReturn } from "react-hook-form"

import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { PhoneInput } from "@/components/ui/phone-input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

import { LANGUAGES, type RegistrationValues } from "./schema"

const MARITAL_STATUSES = ["single", "married", "divorced", "widowed"] as const

interface Props {
  form: UseFormReturn<RegistrationValues>
  photo: File | null
  onPhotoChange: (file: File | null) => void
}

export function StepIdentity({ form, photo, onPhotoChange }: Props) {
  const { t } = useTranslation("students")
  const fileInput = useRef<HTMLInputElement>(null)
  const photoUrl = photo ? URL.createObjectURL(photo) : null

  // The photo tile accepts a dragged image as well as a tap.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: "image/jpeg,image/png,image/webp",
    onFiles: ([file]) => onPhotoChange(file),
  })

  return (
    <div className="space-y-5">
      {/* Photo — optional, tap-friendly (and drop-friendly on a desktop) */}
      <div
        {...dropProps}
        className={cn("flex items-center gap-4 rounded-2xl", dragOver && DROP_ACTIVE)}
      >
        <button
          type="button"
          onClick={() => fileInput.current?.click()}
          className="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-dashed bg-muted/30 text-muted-foreground transition-colors hover:bg-muted/50"
        >
          {photoUrl ? (
            // eslint-disable-next-line @next/next/no-img-element -- local object URL preview
            <img src={photoUrl} alt="" className="size-full object-cover" />
          ) : (
            <ImagePlus className="size-6" />
          )}
        </button>
        <div className="min-w-0">
          <p className="text-sm font-medium">{t("wizard.photo")}</p>
          <p className="text-xs text-muted-foreground">{t("wizard.photoHint")}</p>
          {photo ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="mt-1 h-7 rounded-full px-2 text-xs text-destructive"
              onClick={() => onPhotoChange(null)}
            >
              <X className="size-3" /> {t("wizard.removePhoto")}
            </Button>
          ) : null}
        </div>
        <input
          ref={fileInput}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={(e) => {
            takeFiles(e.target.files)
            e.target.value = ""
          }}
        />
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {(
          [
            ["first_name", t("fields.firstName"), t("fields.firstNamePlaceholder")],
            ["father_name", t("fields.fatherName"), t("fields.fatherNamePlaceholder")],
            ["grandfather_name", t("fields.grandfatherName"), t("fields.grandfatherNamePlaceholder")],
            ["mother_name", t("fields.motherName"), t("fields.motherNamePlaceholder")],
          ] as const
        ).map(([field, label, placeholder]) => (
          <FormField
            key={field}
            control={form.control}
            name={field}
            render={({ field: f }) => (
              <FormItem>
                <FormLabel>{label}</FormLabel>
                <FormControl>
                  <Input placeholder={placeholder} {...f} value={f.value ?? ""} />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        ))}
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField
          control={form.control}
          name="gender"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.gender")}</FormLabel>
              <div className="flex h-11 overflow-hidden rounded-xl border">
                {(["male", "female"] as const).map((val, i) => (
                  <button
                    key={val}
                    type="button"
                    onClick={() => field.onChange(val)}
                    className={cn(
                      "flex-1 text-sm font-medium transition-colors",
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
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
        <FormField
          control={form.control}
          name="fayda_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t("fields.faydaId")}</FormLabel>
              <FormControl>
                <Input placeholder={t("fields.faydaIdPlaceholder")} {...field} value={field.value ?? ""} />
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
                  {MARITAL_STATUSES.map((status) => (
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

      {/* Home languages — chip multi-select, Amharic preselected */}
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
                      "h-9 rounded-full border px-3.5 text-xs font-medium transition-colors",
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

      {/* Student's own contact + login. The student's number is the STUDENT's —
          parent phones live on the Guardians step; mixing them up is the exact
          bug this card exists to prevent. */}
      <div className="overflow-hidden rounded-2xl border">
        <FormField
          control={form.control}
          name="student_has_phone"
          render={({ field }) => (
            <FormItem className="flex items-center justify-between gap-4 p-4">
              <div className="flex min-w-0 items-start gap-3">
                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <Smartphone className="size-4.5" />
                </span>
                <div className="min-w-0 space-y-0.5">
                  <FormLabel>{t("wizard.hasPhone")}</FormLabel>
                  <p className="text-xs text-muted-foreground">{t("wizard.hasPhoneHint")}</p>
                </div>
              </div>
              <FormControl>
                <Switch
                  checked={field.value}
                  onCheckedChange={(on) => {
                    field.onChange(on)
                    if (!on) {
                      form.setValue("primary_phone", "", { shouldValidate: true })
                      form.setValue("email", "", { shouldValidate: true })
                    }
                  }}
                />
              </FormControl>
            </FormItem>
          )}
        />

        {form.watch("student_has_phone") ? (
          <div className="space-y-3 border-t bg-muted/20 p-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.email")}</FormLabel>
                    <FormControl>
                      <Input type="email" placeholder={t("fields.emailPlaceholder")} {...field} value={field.value ?? ""} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            {/* Logins are mandatory — every student leaves registration able
                to sign in, so this is a statement, not a choice. */}
            <div className="flex items-start gap-3 rounded-xl border bg-background p-3">
              <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <KeyRound className="size-4" />
              </span>
              <div className="min-w-0 space-y-0.5">
                <p className="text-sm font-medium">{t("wizard.accountAuto")}</p>
                <p className="text-xs text-muted-foreground">{t("wizard.createAccountHint")}</p>
              </div>
            </div>
          </div>
        ) : (
          <div className="space-y-3 border-t bg-muted/20 p-4">
            <div className="flex items-start gap-3">
              <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <IdCard className="size-4.5" />
              </span>
              <div className="min-w-0 space-y-0.5">
                <p className="text-sm font-medium">{t("wizard.idLoginTitle")}</p>
                <p className="text-xs leading-relaxed text-muted-foreground">{t("wizard.idLoginBody")}</p>
              </div>
            </div>
            <div className="flex items-start gap-3 rounded-xl border bg-background p-3">
              <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <KeyRound className="size-4" />
              </span>
              <div className="min-w-0 space-y-0.5">
                <p className="text-sm font-medium">{t("wizard.idLoginAuto")}</p>
                <p className="text-xs text-muted-foreground">{t("wizard.createIdLoginHint")}</p>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
