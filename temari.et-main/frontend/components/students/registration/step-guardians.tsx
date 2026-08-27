"use client"

import { FileText, ImagePlus, Paperclip, Plus, Search, Trash2, UserPlus } from "lucide-react"
import { useCallback, useEffect, useRef, useState } from "react"
import { useFieldArray, useWatch, type UseFormReturn } from "react-hook-form"

import { DocumentCategorySelect } from "@/components/students/document-category"
import {
  ExistingParentHint,
  useExistingParentMatch,
} from "@/components/students/existing-parent-hint"
import { AsyncCombobox, type AsyncComboboxOption } from "@/components/ui/async-combobox"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
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
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { GuardianLinkDefaults, GuardianSearchResult } from "@/lib/types"
import { cn } from "@/lib/utils"

import { emptyGuardianRow, RELATIONSHIPS, type RegistrationValues } from "./schema"

const GUARDIAN_DOCUMENT_ACCEPT = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
const GUARDIAN_MAX_DOCUMENT_BYTES = 10 * 1024 * 1024

const LINK_FLAGS = [
  "can_view_grades",
  "can_view_attendance",
  "can_pay_fees",
  "can_receive_sms",
] as const

interface Props {
  form: UseFormReturn<RegistrationValues>
}

/** Server search over ALL Temari parents (public id, phone or name). */
export function useGuardianSearch() {
  return useCallback(async (query: string): Promise<AsyncComboboxOption[]> => {
    const res = await apiFetch<{ data: GuardianSearchResult[] }>(
      `/guardians/search?q=${encodeURIComponent(query)}`,
    )
    return res.data.map((parent) => ({
      value: String(parent.parent_id),
      label: parent.name ?? "—",
      description: [parent.public_id, parent.phone].filter(Boolean).join(" · "),
      badge: parent.children_count > 0 ? `${parent.children_count}` : undefined,
      meta: parent.defaults ?? null,
    }))
  }, [])
}

export function StepGuardians({ form }: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const rows = useFieldArray({ control: form.control, name: "guardians" })
  const guardians = useWatch({ control: form.control, name: "guardians" })
  const searchParents = useGuardianSearch()
  // Root-level error from the schema's `.min(1)` rule (a student must have a
  // guardian on file). RHF nests it under `.root` for array fields.
  const guardiansError =
    form.formState.errors.guardians?.root?.message ?? form.formState.errors.guardians?.message

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{t("wizard.guardiansHint")}</p>

      {rows.fields.length === 0 ? (
        <div
          className={cn(
            "rounded-2xl border border-dashed px-6 py-10 text-center text-sm text-muted-foreground",
            guardiansError && "border-destructive/60 text-destructive",
          )}
        >
          {guardiansError ? t("wizard.guardianRequired") : t("wizard.noGuardiansYet")}
        </div>
      ) : null}

      {rows.fields.map((row, index) => {
        const mode = guardians?.[index]?.mode ?? "new"

        return (
          <div key={row.id} className="space-y-4 rounded-2xl border p-4">
            <div className="flex items-center justify-between gap-2">
              <div className="flex h-9 overflow-hidden rounded-full border text-xs font-medium">
                {(
                  [
                    ["search", Search, t("wizard.findExisting")],
                    ["new", UserPlus, t("wizard.newGuardian")],
                  ] as const
                ).map(([value, Icon, label], i) => (
                  <button
                    key={value}
                    type="button"
                    onClick={() => form.setValue(`guardians.${index}.mode`, value)}
                    className={cn(
                      "flex items-center gap-1.5 px-3.5 transition-colors",
                      i > 0 && "border-l",
                      mode === value
                        ? "bg-primary text-primary-foreground"
                        : "bg-background text-muted-foreground hover:bg-muted",
                    )}
                  >
                    <Icon className="size-3.5" />
                    {label}
                  </button>
                ))}
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-9 rounded-full text-destructive"
                onClick={() => rows.remove(index)}
                aria-label={tc("actions.delete")}
              >
                <Trash2 className="size-4" />
              </Button>
            </div>

            {mode === "search" ? (
              <FormField
                control={form.control}
                name={`guardians.${index}.parent_id`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("wizard.searchParent")}</FormLabel>
                    <FormControl>
                      <AsyncCombobox
                        value={
                          field.value
                            ? { value: field.value, label: guardians?.[index]?.parent_label || field.value }
                            : null
                        }
                        onChange={(option) => {
                          field.onChange(option?.value ?? "")
                          form.setValue(`guardians.${index}.parent_label`, option?.label ?? "")
                          // A returning parent's latest link seeds this one —
                          // relationship + consent flags rarely differ between siblings.
                          const defaults = option?.meta as GuardianLinkDefaults | null | undefined
                          if (defaults) {
                            form.setValue(`guardians.${index}.relationship`, defaults.relationship)
                            form.setValue(`guardians.${index}.can_view_grades`, defaults.can_view_grades)
                            form.setValue(`guardians.${index}.can_view_attendance`, defaults.can_view_attendance)
                            form.setValue(`guardians.${index}.can_pay_fees`, defaults.can_pay_fees)
                            form.setValue(`guardians.${index}.can_receive_sms`, defaults.can_receive_sms)
                            form.setValue(`guardians.${index}.emergency_contact`, defaults.emergency_contact)
                          }
                        }}
                        fetcher={searchParents}
                        placeholder={t("wizard.searchParentPlaceholder")}
                        searchPlaceholder={t("wizard.searchParentQuery")}
                        emptyText={t("wizard.noParentsFound")}
                      />
                    </FormControl>
                    <p className="text-xs text-muted-foreground">{t("wizard.searchParentHint")}</p>
                    <FormMessage />
                  </FormItem>
                )}
              />
            ) : (
              <>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                  {(
                    [
                      [`guardians.${index}.first_name`, t("fields.firstName")],
                      [`guardians.${index}.father_name`, t("fields.fatherName")],
                      [`guardians.${index}.grandfather_name`, t("fields.grandfatherName")],
                    ] as const
                  ).map(([name, label]) => (
                    <FormField
                      key={name}
                      control={form.control}
                      name={name}
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{label}</FormLabel>
                          <FormControl>
                            <Input {...field} value={field.value ?? ""} />
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
                    name={`guardians.${index}.phone`}
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("guardians.phone")}</FormLabel>
                        <FormControl>
                          <PhoneInput placeholder={t("guardians.phonePlaceholder")} {...field} value={field.value ?? ""} />
                        </FormControl>
                        <p className="text-xs text-muted-foreground">
                          {form.watch(`guardians.${index}.is_primary`)
                            ? t("guardians.primaryPhoneHint")
                            : t("guardians.phoneHint")}
                        </p>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name={`guardians.${index}.email`}
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
                    name={`guardians.${index}.occupation`}
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("wizard.occupation")}</FormLabel>
                        <FormControl>
                          <Input {...field} value={field.value ?? ""} />
                        </FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name={`guardians.${index}.gender`}
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{t("fields.gender")}</FormLabel>
                        <Select value={field.value ?? ""} onValueChange={field.onChange}>
                          <FormControl>
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder={t("fields.gender")} />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            <SelectItem value="male">{t("fields.male")}</SelectItem>
                            <SelectItem value="female">{t("fields.female")}</SelectItem>
                          </SelectContent>
                        </Select>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>

                {/* Typed a phone that already belongs to a Temari.et parent?
                    Prompt to attach the existing record instead. */}
                <GuardianDuplicateLookup form={form} index={index} />
              </>
            )}

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField
                control={form.control}
                name={`guardians.${index}.relationship`}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("guardians.relationship")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue placeholder={t("guardians.selectRelationship")} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {RELATIONSHIPS.map((relationship) => (
                          <SelectItem key={relationship} value={relationship}>
                            {t(`guardians.relationships.${relationship}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="flex items-end gap-4 pb-1">
                <FormField
                  control={form.control}
                  name={`guardians.${index}.is_primary`}
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-2">
                      <FormControl>
                        <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                      </FormControl>
                      <FormLabel className="!mt-0 text-sm font-normal">{t("guardians.primary")}</FormLabel>
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name={`guardians.${index}.emergency_contact`}
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-2">
                      <FormControl>
                        <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                      </FormControl>
                      <FormLabel className="!mt-0 text-sm font-normal">{t("guardians.emergency")}</FormLabel>
                    </FormItem>
                  )}
                />
              </div>
            </div>

            <div>
              <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {t("guardians.permissions")}
              </p>
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {LINK_FLAGS.map((flag) => (
                  <FormField
                    key={flag}
                    control={form.control}
                    name={`guardians.${index}.${flag}`}
                    render={({ field }) => (
                      <FormItem className="flex items-center gap-2">
                        <FormControl>
                          <Checkbox checked={field.value} onCheckedChange={field.onChange} />
                        </FormControl>
                        <FormLabel className="!mt-0 text-sm font-normal">
                          {t(`guardians.${flagKey(flag)}`)}
                        </FormLabel>
                      </FormItem>
                    )}
                  />
                ))}
              </div>
            </div>

            <GuardianFiles form={form} index={index} />
          </div>
        )
      })}

      <Button
        type="button"
        variant="outline"
        className="h-10 rounded-full"
        onClick={() => rows.append({ ...emptyGuardianRow, is_primary: rows.fields.length === 0 })}
      >
        <Plus className="size-4" />
        {t("guardians.add")}
      </Button>
    </div>
  )
}

/**
 * Per-guardian profile photo + documents. Files are STAGED in the form row
 * and uploaded by the wizard after save, once the parent_id is known.
 */
function GuardianFiles({ form, index }: { form: UseFormReturn<RegistrationValues>; index: number }) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const photoInput = useRef<HTMLInputElement>(null)
  const fileInput = useRef<HTMLInputElement>(null)

  const photo = useWatch({ control: form.control, name: `guardians.${index}.photo` })
  const documents = useWatch({ control: form.control, name: `guardians.${index}.documents` }) ?? []

  // Created inside the effect (not useMemo) so StrictMode's double-invoke
  // can't revoke a URL that is still being rendered. State lands via a timer
  // so the effect body never sets state synchronously.
  const [photoPreview, setPhotoPreview] = useState<string | null>(null)
  useEffect(() => {
    const url = photo ? URL.createObjectURL(photo) : null
    const timer = setTimeout(() => setPhotoPreview(url), 0)
    return () => {
      clearTimeout(timer)
      if (url) URL.revokeObjectURL(url)
    }
  }, [photo])

  // Dropped files take the same staged path as picked ones.
  const documentDrop = useFileDrop({
    accept: GUARDIAN_DOCUMENT_ACCEPT,
    maxSize: GUARDIAN_MAX_DOCUMENT_BYTES,
    multiple: true,
    onFiles: (files) =>
      form.setValue(`guardians.${index}.documents`, [
        ...documents,
        ...files.map((file) => ({
          id: crypto.randomUUID(),
          name: file.name.replace(/\.[^.]+$/, ""),
          category: "",
          file,
        })),
      ]),
  })

  const photoDrop = useFileDrop({
    accept: "image/jpeg,image/png,image/webp",
    onFiles: ([file]) => form.setValue(`guardians.${index}.photo`, file),
  })

  return (
    <div
      {...documentDrop.dropProps}
      className={cn("space-y-3 rounded-2xl", documentDrop.dragOver && DROP_ACTIVE)}
    >
      <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        {t("guardians.files")}
      </p>

      <div
        {...photoDrop.dropProps}
        className={cn("flex items-center gap-3 rounded-2xl", photoDrop.dragOver && DROP_ACTIVE)}
      >
        <button
          type="button"
          onClick={() => photoInput.current?.click()}
          className="relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-dashed bg-muted/30 text-muted-foreground transition-colors hover:bg-muted/50"
        >
          {photoPreview ? (
            // eslint-disable-next-line @next/next/no-img-element -- local preview
            <img src={photoPreview} alt="" className="size-full object-cover" />
          ) : (
            <ImagePlus className="size-5" />
          )}
        </button>
        <div className="min-w-0 text-xs text-muted-foreground">
          <p className="text-sm font-medium text-foreground">{t("guardians.photo")}</p>
          {t("wizard.photoHint")}
        </div>
        <input
          ref={photoInput}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={(e) => {
            photoDrop.takeFiles(e.target.files)
            e.target.value = ""
          }}
        />
      </div>

      {documents.length > 0 ? (
        <div className="space-y-2">
          {documents.map((draft) => (
            <div key={draft.id} className="flex items-start gap-3 rounded-xl border border-dashed px-3 py-2">
              <FileText className="mt-2.5 size-4 shrink-0 text-muted-foreground" />
              <div className="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
                <Input
                  value={draft.name}
                  onChange={(e) =>
                    form.setValue(
                      `guardians.${index}.documents`,
                      documents.map((d) => (d.id === draft.id ? { ...d, name: e.target.value } : d)),
                    )
                  }
                  className="h-9"
                  placeholder={t("wizard.documentNamePlaceholder")}
                />
                <DocumentCategorySelect
                  value={draft.category}
                  onChange={(category) =>
                    form.setValue(
                      `guardians.${index}.documents`,
                      documents.map((d) => (d.id === draft.id ? { ...d, category } : d)),
                    )
                  }
                />
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                className="shrink-0 text-destructive"
                aria-label={tc("actions.delete")}
                onClick={() =>
                  form.setValue(
                    `guardians.${index}.documents`,
                    documents.filter((d) => d.id !== draft.id),
                  )
                }
              >
                <Trash2 className="size-4" />
              </Button>
            </div>
          ))}
        </div>
      ) : null}

      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="outline"
          className="h-9 rounded-full"
          onClick={() => fileInput.current?.click()}
        >
          <Paperclip className="size-4" />
          {t("wizard.attachDocument")}
        </Button>
        <DropHint />
      </div>
      <input
        ref={fileInput}
        type="file"
        accept={GUARDIAN_DOCUMENT_ACCEPT}
        multiple
        className="hidden"
        onChange={(e) => {
          documentDrop.takeFiles(e.target.files)
          e.target.value = ""
        }}
      />
    </div>
  )
}

function flagKey(flag: (typeof LINK_FLAGS)[number]): string {
  return {
    can_view_grades: "canViewGrades",
    can_view_attendance: "canViewAttendance",
    can_pay_fees: "canPayFees",
    can_receive_sms: "canReceiveSms",
  }[flag]
}

/**
 * Watches a new-guardian row's phone and, when it already belongs to a
 * Temari.et parent, offers to attach that record instead of duplicating it —
 * one tap flips the row to search mode with the parent preselected (and
 * their usual link flags applied).
 */
function GuardianDuplicateLookup({
  form,
  index,
}: {
  form: UseFormReturn<RegistrationValues>
  index: number
}) {
  const phone = useWatch({ control: form.control, name: `guardians.${index}.phone` })
  const match = useExistingParentMatch(phone)

  return (
    <ExistingParentHint
      match={match}
      onUse={(parent) => {
        form.setValue(`guardians.${index}.mode`, "search")
        form.setValue(`guardians.${index}.parent_id`, String(parent.parent_id))
        form.setValue(`guardians.${index}.parent_label`, parent.name ?? "")
        // parent_id and a typed phone are mutually exclusive server-side.
        form.setValue(`guardians.${index}.phone`, "")
        const defaults = parent.defaults
        if (defaults) {
          form.setValue(`guardians.${index}.relationship`, defaults.relationship)
          form.setValue(`guardians.${index}.can_view_grades`, defaults.can_view_grades)
          form.setValue(`guardians.${index}.can_view_attendance`, defaults.can_view_attendance)
          form.setValue(`guardians.${index}.can_pay_fees`, defaults.can_pay_fees)
          form.setValue(`guardians.${index}.can_receive_sms`, defaults.can_receive_sms)
          form.setValue(`guardians.${index}.emergency_contact`, defaults.emergency_contact)
        }
      }}
    />
  )
}
