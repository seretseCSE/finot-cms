"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Pencil } from "lucide-react"
import { useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { LogoField } from "@/components/schools/logo-field"
import { Button } from "@/components/ui/button"
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
import type { School } from "@/lib/types"
import { optionalEthContactPhone } from "@/lib/validators"

const schema = z.object({
  name: z.string().min(2, "School name is required"),
  phone: optionalEthContactPhone(),
  address: z.string().optional(),
  is_active: z.enum(["true", "false"]),
})

type FormValues = z.infer<typeof schema>

export function EditSchoolDialog({
  school,
  onUpdated,
}: {
  school: School
  onUpdated: (school: School) => void
}) {
  const { t } = useTranslation("schools")
  const { t: tc } = useTranslation("common")
  const [open, setOpen] = useState(false)
  // This dialog only opens for holders of schools.update (platform staff),
  // the same gate as SchoolPolicy@manageLogo — so the logo field is safe here.
  const [logoFile, setLogoFile] = useState<File | null>(null)

  const defaults = (s: School): FormValues => ({
    name: s.name,
    phone: s.phone ?? "",
    address: s.address ?? "",
    is_active: s.is_active ? "true" : "false",
  })

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: defaults(school),
  })
  useLiveValidation(form)

  useEffect(() => {
    form.reset(defaults(school))
  }, [school, form])

  function handleOpenChange(value: boolean) {
    setOpen(value)
    if (!value) {
      form.reset(defaults(school))
      setLogoFile(null)
    }
  }

  async function onSubmit(values: FormValues) {
    try {
      const response = await apiFetch<{ data: School }>(`/schools/${school.id}`, {
        method: "PATCH",
        body: {
          name: values.name,
          phone: values.phone || null,
          address: values.address || null,
          is_active: values.is_active === "true",
        },
      })
      let updated = response.data

      if (logoFile) {
        try {
          const body = new FormData()
          body.append("logo", logoFile)
          const logo = await apiFetch<{ data: { logo_url: string | null } }>(
            `/schools/${school.id}/logo`,
            { method: "POST", body },
          )
          updated = { ...updated, logo_url: logo.data.logo_url }
        } catch {
          toast.error(t("logo.uploadFailed"))
        }
      }

      toast.success(t("updated"))
      onUpdated(updated)
      handleOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof FormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={handleOpenChange}>
      <ResponsiveSheetTrigger asChild>
        <Button variant="outline" className="h-11">
          <Pencil className="size-4" />
          {tc("actions.edit")}
        </Button>
      </ResponsiveSheetTrigger>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("editTitle")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("editDescription")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              <LogoField currentUrl={school.logo_url} onChange={setLogoFile} />
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("fields.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="phone"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.schoolPhone")}</FormLabel>
                      <FormControl>
                        <PhoneInput
                          mode="contact"
                          placeholder={t("fields.schoolPhonePlaceholder")}
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="address"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.schoolAddress")}</FormLabel>
                      <FormControl>
                        <Input placeholder={t("fields.schoolAddressPlaceholder")} {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <FormField
                control={form.control}
                name="is_active"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tc("columns.status")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value="true">{tc("states.active")}</SelectItem>
                        <SelectItem value="false">{tc("states.inactive")}</SelectItem>
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button
                type="button"
                variant="outline"
                className="flex-1 h-11"
                onClick={() => handleOpenChange(false)}
              >
                {tc("actions.cancel")}
              </Button>
              <Button
                type="submit"
                className="flex-1 h-11"
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
