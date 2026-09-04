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
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { Bank } from "@/lib/types"
import { cn } from "@/lib/utils"

const schema = z.object({
  code: z
    .string()
    .min(1, "Code is required")
    .max(30)
    .regex(/^[a-z0-9_-]+$/, "Lowercase letters, digits, - and _ only"),
  name: z.string().min(1, "Name is required").max(255),
  type: z.enum(["bank", "wallet"]),
  is_active: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  bank?: Bank | null
  onSaved: (bank: Bank) => void
  open: boolean
  onOpenChange: (open: boolean) => void
  showTrigger?: boolean
}

/** Create/edit an Ethiopian bank or mobile wallet in the platform catalog. */
export function BankSheet({ bank, onSaved, open, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const isEdit = !!bank

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { code: "", name: "", type: "bank", is_active: true },
  })
  useLiveValidation(form)

  useEffect(() => {
    if (!open) return
    form.reset(
      bank
        ? { code: bank.code, name: bank.name, type: bank.type, is_active: bank.is_active ?? true }
        : { code: "", name: "", type: "bank", is_active: true },
    )
  }, [open, bank, form])

  async function onSubmit(values: FormValues) {
    try {
      const res = await apiFetch<{ data: Bank }>(
        isEdit ? `/catalogs/banks/${bank!.id}` : "/catalogs/banks",
        { method: isEdit ? "PUT" : "POST", body: values },
      )
      toast.success(isEdit ? t("banks.updated") : t("banks.created"))
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
            {t("banks.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("banks.editTitle") : t("banks.createTitle")}
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
                      <Input placeholder={t("banks.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.code")}</FormLabel>
                    <FormControl>
                      <Input placeholder="awash" className="font-mono lowercase" {...field} />
                    </FormControl>
                    <p className="text-xs text-muted-foreground">{t("banks.codeHint")}</p>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("banks.type")}</FormLabel>
                    <div className="flex overflow-hidden rounded-lg border">
                      {(["bank", "wallet"] as const).map((val, i) => (
                        <button
                          key={val}
                          type="button"
                          onClick={() => field.onChange(val)}
                          className={cn(
                            "flex-1 py-2 text-xs font-medium transition-colors",
                            i > 0 && "border-l",
                            field.value === val
                              ? "bg-primary text-primary-foreground"
                              : "bg-background text-foreground hover:bg-muted",
                          )}
                        >
                          {t(`banks.types.${val}`)}
                        </button>
                      ))}
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_active"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tc("columns.status")}</FormLabel>
                    <div className="flex overflow-hidden rounded-lg border">
                      {([true, false] as const).map((val, i) => (
                        <button
                          key={String(val)}
                          type="button"
                          onClick={() => field.onChange(val)}
                          className={cn(
                            "flex-1 py-2 text-xs font-medium transition-colors",
                            i > 0 && "border-l",
                            field.value === val
                              ? "bg-primary text-primary-foreground"
                              : "bg-background text-foreground hover:bg-muted",
                          )}
                        >
                          {val ? tc("states.active") : tc("states.inactive")}
                        </button>
                      ))}
                    </div>
                    <p className="text-xs text-muted-foreground">{t("banks.activeHint")}</p>
                    <FormMessage />
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
