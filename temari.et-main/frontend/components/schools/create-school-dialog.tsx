"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus } from "lucide-react"
import { useState } from "react"
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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { School } from "@/lib/types"
import { ethPhone, optionalEthContactPhone, optionalEthPhone } from "@/lib/validators"

const schema = z.object({
  name: z.string().min(2, "School name is required"),
  phone: optionalEthContactPhone(),
  address: z.string().optional(),
  principal_name: z.string().min(2, "Principal name is required"),
  principal_phone: ethPhone(),
  technical_name: z.string().optional(),
  technical_phone: optionalEthPhone(),
})

type FormValues = z.infer<typeof schema>

export function CreateSchoolDialog({ onCreated }: { onCreated: (school: School) => void }) {
  const { t } = useTranslation("schools")
  const [open, setOpen] = useState(false)
  const [logoFile, setLogoFile] = useState<File | null>(null)

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: "",
      phone: "",
      address: "",
      principal_name: "",
      principal_phone: "",
      technical_name: "",
      technical_phone: "",
    },
  })
  useLiveValidation(form)

  function handleOpenChange(value: boolean) {
    setOpen(value)
    if (!value) {
      form.reset()
      setLogoFile(null)
    }
  }

  async function onSubmit(values: FormValues) {
    try {
      const response = await apiFetch<{ data: School }>("/schools", {
        method: "POST",
        body: {
          name: values.name,
          phone: values.phone || undefined,
          address: values.address || undefined,
          principal_name: values.principal_name,
          principal_phone: values.principal_phone,
          technical_name: values.technical_name || undefined,
          technical_phone: values.technical_phone || undefined,
        },
      })
      let school = response.data

      // The logo needs the school id — uploaded right after creation.
      if (logoFile) {
        try {
          const body = new FormData()
          body.append("logo", logoFile)
          const logo = await apiFetch<{ data: { logo_url: string | null } }>(
            `/schools/${school.id}/logo`,
            { method: "POST", body },
          )
          school = { ...school, logo_url: logo.data.logo_url }
        } catch {
          toast.error(t("logo.uploadFailed"))
        }
      }

      toast.success(t("created"))
      onCreated(school)
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
        <Button className="h-11">
          <Plus className="size-4" />
          {t("create")}
        </Button>
      </ResponsiveSheetTrigger>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("createTitle")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("createDescription")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              <LogoField onChange={setLogoFile} />
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.name")}</FormLabel>
                    <FormControl><Input placeholder={t("fields.namePlaceholder")} {...field} /></FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
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
                    <FormControl><Input placeholder={t("fields.schoolAddressPlaceholder")} {...field} /></FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="principal_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.principalName")}</FormLabel>
                    <FormControl><Input placeholder={t("fields.principalNamePlaceholder")} {...field} /></FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="principal_phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.principalPhone")}</FormLabel>
                    <FormControl><PhoneInput placeholder={t("fields.principalPhonePlaceholder")} {...field} /></FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="technical_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.technicalName")}</FormLabel>
                    <FormControl><Input placeholder={t("fields.technicalNamePlaceholder")} {...field} /></FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="technical_phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.technicalPhone")}</FormLabel>
                    <FormControl><PhoneInput placeholder={t("fields.technicalPhonePlaceholder")} {...field} /></FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="button" variant="outline" className="flex-1 h-11" onClick={() => handleOpenChange(false)}>Cancel</Button>
              <Button type="submit" className="flex-1 h-11" loading={form.formState.isSubmitting}>{t("create")}</Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
