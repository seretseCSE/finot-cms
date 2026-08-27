"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus, Upload } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Checkbox } from "@/components/ui/checkbox"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { PhoneInput } from "@/components/ui/phone-input"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
  ResponsiveSheetTrigger,
} from "@/components/ui/responsive-sheet"
import { ApiError, apiFetch, getToken } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import { ROLE_OPTIONS } from "@/lib/roles"
import type { AdminUser } from "@/lib/types"
import { cn } from "@/lib/utils"
import { ethPhone } from "@/lib/validators"

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1"

const schema = z.object({
  name: z.string().min(2, "Name is required"),
  phone: ethPhone(),
  email: z.string().email("Invalid email").or(z.literal("")).optional(),
  preferred_language: z.enum(["en", "am", "om"]),
  roles: z.array(z.string()),
})

type FormValues = z.infer<typeof schema>

interface Props {
  /** When provided, sheet is in edit mode for this user. When null, create mode. */
  user?: AdminUser | null
  /** Called after a successful save so the parent can refresh the list. */
  onSaved: () => void
  open?: boolean
  onOpenChange?: (open: boolean) => void
  showTrigger?: boolean
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? "")
    .join("")
}

export function UserSheet({ user, onSaved, open: controlledOpen, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("users")
  const isEdit = !!user
  const [internalOpen, setInternalOpen] = useState(false)
  const [avatarUrl, setAvatarUrl] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  const open = controlledOpen !== undefined ? controlledOpen : internalOpen

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", phone: "", email: "", preferred_language: "en", roles: [] },
  })
  useLiveValidation(form)

  function handleOpenChange(val: boolean) {
    if (onOpenChange) onOpenChange(val)
    else setInternalOpen(val)
    if (!val) form.reset()
  }

  /* eslint-disable react-hooks/set-state-in-effect -- sync local form/avatar with the selected user */
  useEffect(() => {
    if (open && user) {
      setAvatarUrl(user.avatar_url)
      form.reset({
        name: user.name,
        phone: user.phone,
        email: user.email ?? "",
        preferred_language: user.preferred_language,
        roles: user.roles,
      })
    } else if (open && !user) {
      setAvatarUrl(null)
      form.reset({ name: "", phone: "", email: "", preferred_language: "en", roles: [] })
    }
  }, [open, user, form])
  /* eslint-enable react-hooks/set-state-in-effect */

  async function onSubmit(values: FormValues) {
    const body = {
      name: values.name,
      phone: values.phone,
      email: values.email || undefined,
      preferred_language: values.preferred_language,
      roles: values.roles,
    }

    try {
      const endpoint = isEdit ? `/users/${user!.id}` : "/users"
      const method = isEdit ? "PUT" : "POST"
      await apiFetch(endpoint, { method, body })
      toast.success(isEdit ? t("toast.statusUpdated") : t("sheet.create"))
      onSaved()
      handleOpenChange(false)
    } catch (err) {
      if (err instanceof ApiError) {
        for (const [field, messages] of Object.entries(err.errors)) {
          form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(err.errors).length === 0) toast.error(err.message)
      } else {
        toast.error(t("toast.error"))
      }
    }
  }

  // Dropping a picture on the avatar card uploads it, same as the button.
  const avatarDrop = useFileDrop({
    accept: "image/*",
    disabled: uploading,
    onFiles: ([file]) => void handleAvatarUpload(file),
  })

  async function handleAvatarUpload(file: File) {
    if (!user) return
    setUploading(true)
    try {
      const fd = new FormData()
      fd.append("avatar", file)
      const res = await fetch(`${API_URL}/users/${user.id}/avatar`, {
        method: "POST",
        headers: { Accept: "application/json", Authorization: `Bearer ${getToken()}` },
        body: fd,
      })
      const payload = await res.json().catch(() => ({}))
      if (!res.ok) throw new Error(payload.message ?? "Upload failed")
      setAvatarUrl(payload.data?.avatar_url ?? null)
      onSaved()
      toast.success(t("toast.statusUpdated"))
    } catch (err) {
      toast.error(err instanceof Error ? err.message : t("toast.error"))
    } finally {
      setUploading(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={handleOpenChange}>
      {showTrigger && (
        <ResponsiveSheetTrigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("addUser")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent className="data-[side=right]:sm:max-w-2xl">
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{isEdit ? t("sheet.editTitle") : t("sheet.addTitle")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>
            {isEdit ? t("sheet.editDesc", { name: user!.name }) : t("sheet.addDesc")}
          </ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-6">
              {isEdit && (
                <div
                  {...avatarDrop.dropProps}
                  className={cn(
                    "flex items-center gap-4 rounded-xl border border-border/60 bg-muted/30 p-3",
                    avatarDrop.dragOver && DROP_ACTIVE,
                  )}
                >
                  <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted text-lg font-semibold text-muted-foreground">
                    {avatarUrl ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={avatarUrl} alt="" className="size-full object-cover" />
                    ) : (
                      initials(user!.name)
                    )}
                  </div>
                  <div>
                    <p className="text-sm font-medium">{t("sheet.avatar")}</p>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="mt-1.5 h-8"
                      loading={uploading}
                      onClick={() => fileRef.current?.click()}
                    >
                      <Upload className="size-3.5" />
                      {t("sheet.uploadAvatar")}
                    </Button>
                    <input
                      ref={fileRef}
                      type="file"
                      accept="image/*"
                      className="hidden"
                      onChange={(e) => {
                        avatarDrop.takeFiles(e.target.files)
                        e.target.value = ""
                      }}
                    />
                  </div>
                </div>
              )}

              <div className="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem className="sm:col-span-2">
                      <FormLabel>{t("sheet.name")}</FormLabel>
                      <FormControl><Input placeholder="e.g. Abebe Girma" {...field} /></FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="phone"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("sheet.phone")}</FormLabel>
                      <FormControl><PhoneInput placeholder="09XXXXXXXX" {...field} /></FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="email"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("sheet.email")} <span className="text-muted-foreground text-xs">{t("sheet.emailOptional")}</span></FormLabel>
                      <FormControl><Input type="email" placeholder="user@example.com" {...field} /></FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="preferred_language"
                  render={({ field }) => (
                    <FormItem className="sm:col-span-2">
                      <FormLabel>{t("sheet.language")}</FormLabel>
                      <div className="flex overflow-hidden rounded-lg border">
                        {(["en", "am", "om"] as const).map((lang, i) => (
                          <button
                            key={lang}
                            type="button"
                            onClick={() => field.onChange(lang)}
                            className={cn(
                              "flex-1 py-2 text-xs font-medium transition-colors",
                              i > 0 && "border-l",
                              field.value === lang
                                ? "bg-primary text-primary-foreground"
                                : "bg-background hover:bg-muted text-foreground",
                            )}
                          >
                            {lang === "en" ? "English" : lang === "am" ? "አማርኛ" : "Afaan Oromo"}
                          </button>
                        ))}
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <FormField
                control={form.control}
                name="roles"
                render={({ field }) => (
                  <FormItem className="border-t border-border/60 pt-5">
                    <FormLabel>
                      {t("sheet.roles")}
                      {field.value.length > 0 && (
                        <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                          {t("sheet.rolesSelected", { count: field.value.length })}
                        </span>
                      )}
                    </FormLabel>
                    <p className="-mt-1 text-xs text-muted-foreground">{t("sheet.rolesHint")}</p>
                    <div className="space-y-3 pt-1">
                      {/* Only PLATFORM roles are granted here (ADR-010). School/
                          branch roles come from school provisioning + branch
                          assignment; parent/student are relationships, never roles. */}
                      {(["platform"] as const).map((scope) => {
                        const roles = ROLE_OPTIONS.filter((r) => r.scope === scope)
                        if (roles.length === 0) return null
                        return (
                          <div key={scope} className="space-y-1.5">
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                              {t(`sheet.roleScope.${scope}`)}
                            </p>
                            <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                              {roles.map((role) => {
                                const active = field.value.includes(role.value)
                                return (
                                  <label
                                    key={role.value}
                                    className={cn(
                                      "flex min-h-11 cursor-pointer select-none items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors",
                                      active
                                        ? "border-primary bg-primary/5 font-medium"
                                        : "border-border bg-background hover:bg-muted",
                                    )}
                                  >
                                    <Checkbox
                                      checked={active}
                                      onCheckedChange={(checked) => {
                                        if (checked) field.onChange([...field.value, role.value])
                                        else field.onChange(field.value.filter((r) => r !== role.value))
                                      }}
                                    />
                                    <span className="truncate">{role.label}</span>
                                  </label>
                                )
                              })}
                            </div>
                          </div>
                        )
                      })}
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="button" variant="outline" className="flex-1 h-11" onClick={() => handleOpenChange(false)}>
                {t("confirm.cancel")}
              </Button>
              <Button type="submit" className="flex-1 h-11" loading={form.formState.isSubmitting}>
                {isEdit ? t("sheet.save") : t("sheet.create")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
