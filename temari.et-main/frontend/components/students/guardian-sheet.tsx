"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import {
  FileText,
  ImagePlus,
  Paperclip,
  Plus,
  Search,
  Trash2,
  UserPlus,
} from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import {
  DocumentCategoryBadge,
  DocumentCategorySelect,
} from "@/components/students/document-category"
import {
  ExistingParentHint,
  useExistingParentMatch,
} from "@/components/students/existing-parent-hint"
import { useGuardianSearch } from "@/components/students/registration/step-guardians"
import { AsyncCombobox } from "@/components/ui/async-combobox"
import { AttachmentTile } from "@/components/ui/attachment"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { PhoneInput } from "@/components/ui/phone-input"
import { useMediaPreview } from "@/components/ui/media-preview"
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
import { useTranslation } from "@/lib/i18n"
import type {
  Guardian,
  GuardianLinkDefaults,
  GuardianRelationship,
  StudentAttachment,
} from "@/lib/types"
import { useLiveValidation } from "@/lib/use-live-validation"
import { optionalEthPhone } from "@/lib/validators"
import { cn } from "@/lib/utils"

const DOCUMENT_ACCEPT = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024

const RELATIONSHIPS: GuardianRelationship[] = [
  "father",
  "mother",
  "grandfather",
  "grandmother",
  "uncle",
  "aunt",
  "sibling",
  "legal_guardian",
  "other",
]

const PERMISSION_FIELDS = [
  "can_view_grades",
  "can_view_attendance",
  "can_pay_fees",
  "can_receive_sms",
] as const

const PERMISSION_LABEL: Record<(typeof PERMISSION_FIELDS)[number], string> = {
  can_view_grades: "canViewGrades",
  can_view_attendance: "canViewAttendance",
  can_pay_fees: "canPayFees",
  can_receive_sms: "canReceiveSms",
}

const schema = z
  .object({
    mode: z.enum(["search", "new"]),
    parent_id: z.string().optional(),
    parent_label: z.string().optional(),
    first_name: z.string().max(255).optional(),
    father_name: z.string().max(255).optional(),
    grandfather_name: z.string().max(255).optional(),
    phone: optionalEthPhone(),
    email: z
      .string()
      .email("Enter a valid email")
      .max(255)
      .or(z.literal(""))
      .optional(),
    occupation: z.string().max(255).optional(),
    relationship: z.enum([
      "father",
      "mother",
      "grandfather",
      "grandmother",
      "uncle",
      "aunt",
      "sibling",
      "legal_guardian",
      "other",
    ]),
    secondary_phone: optionalEthPhone(),
    can_view_grades: z.boolean(),
    can_view_attendance: z.boolean(),
    can_pay_fees: z.boolean(),
    can_receive_sms: z.boolean(),
    is_primary: z.boolean(),
    emergency_contact: z.boolean(),
    notes: z.string().max(1000).optional(),
  })
  .superRefine((v, ctx) => {
    if (v.mode === "search" && !v.parent_id) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["parent_id"],
        message: "Search and pick a parent",
      })
    }
    if (v.mode === "new") {
      if (!v.first_name?.trim())
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["first_name"],
          message: "First name is required",
        })
      if (!v.phone?.trim())
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["phone"],
          message: "Phone is required",
        })
    }
  })

type FormValues = z.infer<typeof schema>

const defaults: FormValues = {
  mode: "new",
  parent_id: "",
  parent_label: "",
  first_name: "",
  father_name: "",
  grandfather_name: "",
  phone: "",
  email: "",
  occupation: "",
  relationship: "mother",
  secondary_phone: "",
  can_view_grades: true,
  can_view_attendance: true,
  can_pay_fees: true,
  can_receive_sms: true,
  is_primary: false,
  emergency_contact: false,
  notes: "",
}

interface Props {
  studentId: number
  guardian?: Guardian | null
  onSaved: (guardian: Guardian) => void
  open?: boolean
  onOpenChange?: (open: boolean) => void
  showTrigger?: boolean
}

export function GuardianSheet({
  studentId,
  guardian,
  onSaved,
  open,
  onOpenChange,
  showTrigger,
}: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()
  const { openPreview, previewDialog } = useMediaPreview()
  const isEdit = !!guardian
  const searchParents = useGuardianSearch()

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: defaults,
  })
  useLiveValidation(form)
  const mode = useWatch({ control: form.control, name: "mode" })
  const parentLabel = useWatch({ control: form.control, name: "parent_label" })
  // Duplicate guard: a typed phone that already belongs to a parent prompts
  // "use the existing record" instead of creating a lookalike (create only).
  const typedPhone = useWatch({ control: form.control, name: "phone" })
  const phoneMatch = useExistingParentMatch(isEdit ? null : typedPhone)

  // Photo + documents attach to the parent PROFILE. They're staged locally
  // and uploaded after save (create mode has no parent_id until then).
  const photoInput = useRef<HTMLInputElement>(null)
  const fileInput = useRef<HTMLInputElement>(null)
  const [photoFile, setPhotoFile] = useState<File | null>(null)
  const [drafts, setDrafts] = useState<
    { id: string; name: string; category: string; file: File }[]
  >([])
  const [existingAttachments, setExistingAttachments] = useState<
    StudentAttachment[]
  >([])
  const [existingPhotoUrl, setExistingPhotoUrl] = useState<string | null>(null)

  const photoPreview = photoFile
    ? URL.createObjectURL(photoFile)
    : existingPhotoUrl

  // Dropped files take the same staged path as picked ones — documents keep
  // their rename + category row, the photo keeps its preview.
  const documentDrop = useFileDrop({
    accept: DOCUMENT_ACCEPT,
    maxSize: MAX_DOCUMENT_BYTES,
    multiple: true,
    onFiles: (files) =>
      setDrafts((prev) => [
        ...prev,
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
    onFiles: ([file]) => setPhotoFile(file),
  })

  useEffect(() => {
    if (!open) return
    const timer = setTimeout(() => {
      setPhotoFile(null)
      setDrafts([])
      setExistingAttachments(guardian?.attachments ?? [])
      setExistingPhotoUrl(guardian?.photo_url ?? null)
    }, 0)
    form.reset(
      guardian
        ? {
            ...defaults,
            mode: "new",
            first_name: guardian.first_name ?? "",
            father_name: guardian.father_name ?? "",
            grandfather_name: guardian.grandfather_name ?? "",
            phone: guardian.phone ?? "",
            email: guardian.email ?? "",
            occupation: guardian.occupation ?? "",
            relationship: guardian.relationship,
            secondary_phone: guardian.secondary_phone ?? "",
            can_view_grades: guardian.can_view_grades,
            can_view_attendance: guardian.can_view_attendance,
            can_pay_fees: guardian.can_pay_fees,
            can_receive_sms: guardian.can_receive_sms,
            is_primary: guardian.is_primary,
            emergency_contact: guardian.emergency_contact,
            notes: guardian.notes ?? "",
          }
        : defaults
    )
    return () => clearTimeout(timer)
  }, [open, guardian, form])

  function close() {
    onOpenChange?.(false)
    if (!isEdit) form.reset(defaults)
  }

  /** Upload staged photo + documents; returns the guardian patched with them. */
  async function uploadFiles(saved: Guardian): Promise<Guardian> {
    let result = saved

    if (photoFile) {
      const photoBody = new FormData()
      photoBody.append("photo", photoFile)
      const res = await apiFetch<{ data: { photo_url: string | null } }>(
        `/parents/${saved.parent_id}/photo`,
        { method: "POST", body: photoBody }
      )
      result = { ...result, photo_url: res.data.photo_url }
      setPhotoFile(null)
    }

    const uploaded: StudentAttachment[] = [...existingAttachments]
    for (const draft of drafts) {
      const fileBody = new FormData()
      fileBody.append("name", draft.name || draft.file.name)
      if (draft.category) fileBody.append("category", draft.category)
      fileBody.append("file", draft.file)
      const res = await apiFetch<{ data: StudentAttachment }>(
        `/parents/${saved.parent_id}/attachments`,
        { method: "POST", body: fileBody }
      )
      uploaded.push(res.data)
      setDrafts((prev) => prev.filter((d) => d.id !== draft.id))
    }

    return { ...result, attachments: uploaded }
  }

  async function removeExistingAttachment(attachmentId: number) {
    if (!guardian) return
    try {
      await apiFetch(
        `/parents/${guardian.parent_id}/attachments/${attachmentId}`,
        {
          method: "DELETE",
        }
      )
      setExistingAttachments((prev) =>
        prev.filter((a) => a.id !== attachmentId)
      )
      toast.success(t("documents.removed"))
    } catch (error) {
      toast.error(
        error instanceof ApiError ? error.message : tc("errors.generic")
      )
    }
  }

  async function onSubmit(values: FormValues) {
    const link = {
      relationship: values.relationship,
      can_view_grades: values.can_view_grades,
      can_view_attendance: values.can_view_attendance,
      can_pay_fees: values.can_pay_fees,
      can_receive_sms: values.can_receive_sms,
      is_primary: values.is_primary,
      emergency_contact: values.emergency_contact,
      notes: values.notes || undefined,
    }

    const profile = {
      first_name: values.first_name,
      father_name: values.father_name || null,
      grandfather_name: values.grandfather_name || null,
      phone: values.phone,
      email: values.email || null,
      occupation: values.occupation || null,
      secondary_phone: values.secondary_phone || null,
    }

    const body = isEdit
      ? { ...link, ...profile }
      : values.mode === "search"
        ? { ...link, parent_id: Number(values.parent_id) }
        : {
            ...link,
            first_name: values.first_name,
            father_name: values.father_name || undefined,
            grandfather_name: values.grandfather_name || undefined,
            phone: values.phone,
            email: values.email || undefined,
            occupation: values.occupation || undefined,
            secondary_phone: values.secondary_phone || undefined,
          }

    try {
      const res = await apiFetch<{ data: Guardian }>(
        isEdit
          ? `/guardians/${guardian!.id}`
          : `/students/${studentId}/guardians`,
        { method: isEdit ? "PUT" : "POST", body }
      )

      // Profile files go up once the parent_id is known.
      const saved = await uploadFiles(res.data)

      toast.success(isEdit ? t("guardians.updated") : t("guardians.added"))
      onSaved(saved)
      close()
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof FormValues, {
            type: "server",
            message: messages[0],
          })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error(tc("errors.generic"))
      }
    }
  }

  return (
    <ResponsiveSheet
      open={open}
      onOpenChange={(v) => (v ? onOpenChange?.(true) : close())}
    >
      {showTrigger && (
        <ResponsiveSheetTrigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("guardians.add")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        {confirmDialog}
        {previewDialog}
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("guardians.editTitle") : t("guardians.addTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit)}
            className="flex min-h-0 flex-1 flex-col"
          >
            <ResponsiveSheetBody className="space-y-5">
              {!isEdit && (
                <div className="flex h-10 overflow-hidden rounded-full border text-xs font-medium">
                  {(
                    [
                      ["search", Search, t("wizard.findExisting")],
                      ["new", UserPlus, t("wizard.newGuardian")],
                    ] as const
                  ).map(([value, Icon, label], i) => (
                    <button
                      key={value}
                      type="button"
                      onClick={() => form.setValue("mode", value)}
                      className={cn(
                        "flex flex-1 items-center justify-center gap-1.5 transition-colors",
                        i > 0 && "border-l",
                        mode === value
                          ? "bg-primary text-primary-foreground"
                          : "bg-background text-muted-foreground hover:bg-muted"
                      )}
                    >
                      <Icon className="size-3.5" />
                      {label}
                    </button>
                  ))}
                </div>
              )}

              {!isEdit && mode === "search" ? (
                <FormField
                  control={form.control}
                  name="parent_id"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("wizard.searchParent")}</FormLabel>
                      <FormControl>
                        <AsyncCombobox
                          value={
                            field.value
                              ? {
                                  value: field.value,
                                  label: parentLabel || field.value,
                                }
                              : null
                          }
                          onChange={(option) => {
                            field.onChange(option?.value ?? "")
                            form.setValue("parent_label", option?.label ?? "")
                            // A returning parent's latest link seeds this one —
                            // relationship + consent flags rarely differ between siblings.
                            const defaults = option?.meta as
                              | GuardianLinkDefaults
                              | null
                              | undefined
                            if (defaults) {
                              form.setValue(
                                "relationship",
                                defaults.relationship
                              )
                              form.setValue(
                                "can_view_grades",
                                defaults.can_view_grades
                              )
                              form.setValue(
                                "can_view_attendance",
                                defaults.can_view_attendance
                              )
                              form.setValue(
                                "can_pay_fees",
                                defaults.can_pay_fees
                              )
                              form.setValue(
                                "can_receive_sms",
                                defaults.can_receive_sms
                              )
                              form.setValue(
                                "emergency_contact",
                                defaults.emergency_contact
                              )
                            }
                          }}
                          fetcher={searchParents}
                          placeholder={t("wizard.searchParentPlaceholder")}
                          searchPlaceholder={t("wizard.searchParentQuery")}
                          emptyText={t("wizard.noParentsFound")}
                        />
                      </FormControl>
                      <p className="text-xs text-muted-foreground">
                        {t("wizard.searchParentHint")}
                      </p>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ) : (
                <>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <FormField
                      control={form.control}
                      name="first_name"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{t("fields.firstName")}</FormLabel>
                          <FormControl>
                            <Input {...field} value={field.value ?? ""} />
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
                            <Input {...field} value={field.value ?? ""} />
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
                            <Input {...field} value={field.value ?? ""} />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="occupation"
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
                  </div>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <FormField
                      control={form.control}
                      name="phone"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{t("guardians.phone")}</FormLabel>
                          <FormControl>
                            <PhoneInput
                              placeholder={t("guardians.phonePlaceholder")}
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
                      name="email"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{t("fields.email")}</FormLabel>
                          <FormControl>
                            <Input
                              type="email"
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
                      name="secondary_phone"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>{t("guardians.secondaryPhone")}</FormLabel>
                          <FormControl>
                            <PhoneInput
                              placeholder={t("guardians.phonePlaceholder")}
                              {...field}
                              value={field.value ?? ""}
                            />
                          </FormControl>
                          <FormMessage />
                        </FormItem>
                      )}
                    />
                  </div>

                  {/* Typed a phone that already belongs to a Temari.et parent?
                      Prompt to attach the existing record instead. */}
                  {!isEdit ? (
                    <ExistingParentHint
                      match={phoneMatch}
                      onUse={(parent) => {
                        form.setValue("mode", "search")
                        form.setValue("parent_id", String(parent.parent_id))
                        form.setValue("parent_label", parent.name ?? "")
                        // parent_id and a typed phone are mutually exclusive.
                        form.setValue("phone", "")
                        const defaults = parent.defaults
                        if (defaults) {
                          form.setValue("relationship", defaults.relationship)
                          form.setValue("can_view_grades", defaults.can_view_grades)
                          form.setValue("can_view_attendance", defaults.can_view_attendance)
                          form.setValue("can_pay_fees", defaults.can_pay_fees)
                          form.setValue("can_receive_sms", defaults.can_receive_sms)
                          form.setValue("emergency_contact", defaults.emergency_contact)
                        }
                      }}
                    />
                  ) : null}
                </>
              )}

              <FormField
                control={form.control}
                name="relationship"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("guardians.relationship")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue
                            placeholder={t("guardians.selectRelationship")}
                          />
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

              <div className="flex flex-wrap gap-4">
                <FormField
                  control={form.control}
                  name="is_primary"
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-2">
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={field.onChange}
                        />
                      </FormControl>
                      <FormLabel className="!mt-0 text-sm font-normal">
                        {t("guardians.primary")}
                      </FormLabel>
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="emergency_contact"
                  render={({ field }) => (
                    <FormItem className="flex items-center gap-2">
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={field.onChange}
                        />
                      </FormControl>
                      <FormLabel className="!mt-0 text-sm font-normal">
                        {t("guardians.emergency")}
                      </FormLabel>
                    </FormItem>
                  )}
                />
              </div>

              <div>
                <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("guardians.permissions")}
                </p>
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                  {PERMISSION_FIELDS.map((flag) => (
                    <FormField
                      key={flag}
                      control={form.control}
                      name={flag}
                      render={({ field }) => (
                        <FormItem className="flex items-center gap-2">
                          <FormControl>
                            <Checkbox
                              checked={field.value}
                              onCheckedChange={field.onChange}
                            />
                          </FormControl>
                          <FormLabel className="!mt-0 text-sm font-normal">
                            {t(`guardians.${PERMISSION_LABEL[flag]}`)}
                          </FormLabel>
                        </FormItem>
                      )}
                    />
                  ))}
                </div>
              </div>

              <FormField
                control={form.control}
                name="notes"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("guardians.notes")}</FormLabel>
                    <FormControl>
                      <Input
                        placeholder={t("guardians.notesPlaceholder")}
                        {...field}
                        value={field.value ?? ""}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {/* Photo & documents — attach to the parent PROFILE (uploaded
                  after save, once the parent_id is known). */}
              <div
                {...documentDrop.dropProps}
                className={cn(
                  "space-y-3 rounded-2xl",
                  documentDrop.dragOver && DROP_ACTIVE,
                )}
              >
                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  {t("guardians.files")}
                </p>

                <div
                  {...photoDrop.dropProps}
                  className={cn(
                    "flex items-center gap-3 rounded-2xl",
                    photoDrop.dragOver && DROP_ACTIVE,
                  )}
                >
                  <button
                    type="button"
                    onClick={() => photoInput.current?.click()}
                    className="relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-dashed bg-muted/30 text-muted-foreground transition-colors hover:bg-muted/50"
                  >
                    {photoPreview ? (
                      // eslint-disable-next-line @next/next/no-img-element -- local preview / signed URL
                      <img
                        src={photoPreview}
                        alt=""
                        className="size-full object-cover"
                      />
                    ) : (
                      <ImagePlus className="size-5" />
                    )}
                  </button>
                  <div className="min-w-0 text-xs text-muted-foreground">
                    <p className="text-sm font-medium text-foreground">
                      {t("guardians.photo")}
                    </p>
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

                <div className="space-y-2">
                  {existingAttachments.map((attachment, index) => (
                    <AttachmentTile
                      key={attachment.id}
                      file={attachment}
                      description={
                        <DocumentCategoryBadge category={attachment.category} />
                      }
                      onPreview={() => openPreview(existingAttachments, index)}
                      onDelete={() =>
                        confirmDelete(
                          () => removeExistingAttachment(attachment.id),
                          tc("confirmDelete.named", { name: attachment.name })
                        )
                      }
                    />
                  ))}
                  {drafts.map((draft) => (
                    <div
                      key={draft.id}
                      className="flex items-start gap-3 rounded-xl border border-dashed px-3 py-2"
                    >
                      <FileText className="mt-2.5 size-4 shrink-0 text-muted-foreground" />
                      <div className="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
                        <Input
                          value={draft.name}
                          onChange={(e) =>
                            setDrafts((prev) =>
                              prev.map((d) =>
                                d.id === draft.id
                                  ? { ...d, name: e.target.value }
                                  : d
                              )
                            )
                          }
                          className="h-9"
                          placeholder={t("wizard.documentNamePlaceholder")}
                        />
                        <DocumentCategorySelect
                          value={draft.category}
                          onChange={(category) =>
                            setDrafts((prev) =>
                              prev.map((d) =>
                                d.id === draft.id ? { ...d, category } : d
                              )
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
                          setDrafts((prev) =>
                            prev.filter((d) => d.id !== draft.id)
                          )
                        }
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  ))}
                </div>

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
                  accept={DOCUMENT_ACCEPT}
                  multiple
                  className="hidden"
                  onChange={(e) => {
                    documentDrop.takeFiles(e.target.files)
                    e.target.value = ""
                  }}
                />
              </div>
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1"
                onClick={close}
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
