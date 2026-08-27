"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus } from "lucide-react"
import { useEffect } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

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
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { SchoolDirectoryEntry } from "@/lib/types"

const schema = z.object({
  name: z.string().min(1, "Name is required").max(255),
  region: z.string().max(100),
  zone: z.string().max(100),
  city: z.string().max(100),
  is_verified: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  entry?: SchoolDirectoryEntry | null
  onSaved: (entry: SchoolDirectoryEntry) => void
  open: boolean
  onOpenChange: (open: boolean) => void
  showTrigger?: boolean
}

/**
 * Create/edit an Ethiopian school directory row (platform studio). New
 * platform-curated entries are verified by default; edits go through the
 * platform-gated /school-directory routes.
 */
export function DirectoryEntrySheet({ entry, onSaved, open, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const isEdit = !!entry

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", region: "", zone: "", city: "", is_verified: true },
  })
  useLiveValidation(form)

  useEffect(() => {
    if (!open) return
    form.reset(
      entry
        ? {
            name: entry.name,
            region: entry.region ?? "",
            zone: entry.zone ?? "",
            city: entry.city ?? "",
            is_verified: entry.is_verified,
          }
        : { name: "", region: "", zone: "", city: "", is_verified: true },
    )
  }, [open, entry, form])

  async function onSubmit(values: FormValues) {
    const body = {
      name: values.name,
      region: values.region || null,
      zone: values.zone || null,
      city: values.city || null,
      is_verified: values.is_verified,
    }

    try {
      const res = await apiFetch<{ data: SchoolDirectoryEntry }>(
        isEdit ? `/school-directory/${entry!.id}` : "/catalogs/school-directory",
        { method: isEdit ? "PUT" : "POST", body },
      )
      toast.success(isEdit ? t("directory.updated") : t("directory.created"))
      onSaved(res.data)
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
      {showTrigger && (
        <ResponsiveSheetTrigger asChild>
          <Button className="h-11">
            <Plus className="size-4" />
            {t("directory.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("directory.editTitle") : t("directory.createTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("directory.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="grid grid-cols-2 gap-3">
                <FormField
                  control={form.control}
                  name="region"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("directory.region")}</FormLabel>
                      <FormControl>
                        <Input placeholder={t("directory.regionPlaceholder")} {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="zone"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("directory.zone")}</FormLabel>
                      <FormControl>
                        <Input {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <FormField
                control={form.control}
                name="city"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("directory.city")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("directory.cityPlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_verified"
                render={({ field }) => (
                  <FormItem className="flex items-center justify-between gap-4 rounded-xl border bg-muted/30 px-4 py-3">
                    <div className="space-y-0.5">
                      <FormLabel>{t("directory.verified")}</FormLabel>
                      <p className="text-xs text-muted-foreground">{t("directory.verifiedHint")}</p>
                    </div>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="button" variant="outline" className="h-11 flex-1" onClick={() => onOpenChange(false)}>
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
