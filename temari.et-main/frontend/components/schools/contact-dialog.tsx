"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useEffect, useState, type ReactNode } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

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
import type { Branch, Contact, School } from "@/lib/types"
import { ethPhone } from "@/lib/validators"

/** Who we're (re)assigning: a school-level role, or a branch's director. */
export type ContactTarget =
  | { kind: "school"; schoolId: number; role: "principal" | "school_admin" }
  | { kind: "branch"; branchId: number }

const schema = z.object({
  name: z.string().min(2, "Name is required"),
  phone: ethPhone(),
})

type FormValues = z.infer<typeof schema>

/**
 * Replace the person holding a school/branch contact role. Provisions (or
 * reuses) the account server-side and SMSes them a set-password link; the old
 * holder is deactivated. Shared by principal, IT admin, and director.
 */
export function ContactDialog({
  target,
  current,
  trigger,
  title,
  description,
  onSchoolSaved,
  onBranchSaved,
}: {
  target: ContactTarget
  current?: Contact | null
  trigger: ReactNode
  title: string
  description: string
  onSchoolSaved?: (school: School) => void
  onBranchSaved?: (branch: Branch) => void
}) {
  const { t: tc } = useTranslation("common")
  const [open, setOpen] = useState(false)

  const defaults: FormValues = {
    name: current?.name ?? "",
    phone: current?.phone ?? "",
  }

  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: defaults })
  useLiveValidation(form)

  useEffect(() => {
    if (open) form.reset({ name: current?.name ?? "", phone: current?.phone ?? "" })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  function handleOpenChange(value: boolean) {
    setOpen(value)
    if (!value) form.reset(defaults)
  }

  async function onSubmit(values: FormValues) {
    try {
      if (target.kind === "school") {
        const res = await apiFetch<{ data: School }>(`/schools/${target.schoolId}/contacts`, {
          method: "PUT",
          body: { role: target.role, name: values.name, phone: values.phone },
        })
        onSchoolSaved?.(res.data)
      } else {
        const res = await apiFetch<{ data: Branch }>(`/branches/${target.branchId}/director`, {
          method: "PUT",
          body: { name: values.name, phone: values.phone },
        })
        onBranchSaved?.(res.data)
      }
      toast.success(tc("actions.saved"))
      handleOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          if (field === "name" || field === "phone") {
            form.setError(field, { type: "server", message: messages[0] })
          }
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error("Something went wrong.")
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={handleOpenChange}>
      <ResponsiveSheetTrigger asChild>{trigger}</ResponsiveSheetTrigger>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{title}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{description}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tc("columns.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder="Full name" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tc("fields.phone")}</FormLabel>
                    <FormControl>
                      <PhoneInput placeholder="09… or 07…" {...field} />
                    </FormControl>
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
