"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useEffect } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
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
import type { DiscountType, Invoice } from "@/lib/types"

const schema = z
  .object({
    discount_type: z.enum(["none", "percentage", "fixed", "full_scholarship"]),
    discount_value: z.string().optional(),
    scholarship_reason: z.string().max(255).optional(),
  })
  .superRefine((v, ctx) => {
    if (v.discount_type === "percentage" || v.discount_type === "fixed") {
      const value = Number(v.discount_value)
      if (!v.discount_value || Number.isNaN(value) || value <= 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ["discount_value"],
          message: "Enter a positive amount",
        })
      }
    }
    if (v.discount_type === "full_scholarship" && !v.scholarship_reason?.trim()) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ["scholarship_reason"],
        message: "A reason is required for a scholarship",
      })
    }
  })

type FormValues = z.infer<typeof schema>

interface Props {
  invoice: Invoice | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onApplied: (invoice: Invoice) => void
}

/**
 * Apply a discount/scholarship to one invoice. A full scholarship is the
 * scholarship path; percentage/fixed cover partial concessions (sibling
 * discounts, staff children…). Never deletes anything — history stays exact.
 */
export function ScholarshipInvoiceDialog({ invoice, open, onOpenChange, onApplied }: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { discount_type: "full_scholarship", discount_value: "", scholarship_reason: "" },
  })
  useLiveValidation(form)

  const type = useWatch({ control: form.control, name: "discount_type" })

  useEffect(() => {
    if (open && invoice) {
      form.reset({
        discount_type: invoice.discount_type && invoice.discount_type !== "none" ? invoice.discount_type : "full_scholarship",
        discount_value: invoice.discount_value && Number(invoice.discount_value) > 0 ? invoice.discount_value : "",
        scholarship_reason: invoice.scholarship_reason ?? "",
      })
    }
  }, [open, invoice, form])

  async function onSubmit(values: FormValues) {
    if (!invoice) return
    try {
      const res = await apiFetch<{ data: Invoice }>(`/invoices/${invoice.id}/discount`, {
        method: "POST",
        body: {
          discount_type: values.discount_type,
          discount_value: values.discount_value ? Number(values.discount_value) : 0,
          scholarship_reason: values.scholarship_reason || undefined,
        },
      })
      toast.success(t("scholarship.applied"))
      onApplied(res.data)
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

  const TYPES: DiscountType[] = ["full_scholarship", "percentage", "fixed", "none"]

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {t("scholarship.title")}
            {invoice ? ` — ${invoice.title}` : ""}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              {invoice ? (
                <p className="text-sm text-muted-foreground">
                  {t("scholarship.summary", {
                    amount: invoice.amount,
                    paid: invoice.amount_paid,
                  })}
                </p>
              ) : null}

              <FormField
                control={form.control}
                name="discount_type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("scholarship.type")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {TYPES.map((type) => (
                          <SelectItem key={type} value={type}>
                            {t(`scholarship.types.${type}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {type === "percentage" || type === "fixed" ? (
                <FormField
                  control={form.control}
                  name="discount_value"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>
                        {type === "percentage" ? t("scholarship.percent") : t("scholarship.fixedAmount")}
                      </FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          inputMode="decimal"
                          min="0"
                          step="0.01"
                          {...field}
                          value={field.value ?? ""}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ) : null}

              {type !== "none" ? (
                <FormField
                  control={form.control}
                  name="scholarship_reason"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("scholarship.reason")}</FormLabel>
                      <FormControl>
                        <Input placeholder={t("scholarship.reasonPlaceholder")} {...field} value={field.value ?? ""} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ) : null}
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button
                type="button"
                variant="outline"
                className="h-11 flex-1"
                onClick={() => onOpenChange(false)}
              >
                {tc("actions.cancel")}
              </Button>
              <Button type="submit" className="h-11 flex-1" loading={form.formState.isSubmitting}>
                {t("scholarship.apply")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
