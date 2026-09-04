"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { useEffect, useState } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { BranchField, useBranchScope } from "@/components/ui/branch-select"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form"
import { Input } from "@/components/ui/input"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { PayrollRun } from "@/lib/types"

/**
 * Prefill the run with the Ethiopian month that today falls in: EC months run
 * ≈ the 11th (Gregorian) to the 10th of the next month; the EC year rolls over
 * on Meskerem 1 (≈ Sep 11).
 */
function currentEthiopianPeriod(): { name: string; period_start: string; period_end: string } {
  const MONTHS = [
    "Meskerem", "Tikimt", "Hidar", "Tahsas", "Tir", "Yekatit",
    "Megabit", "Miyazya", "Ginbot", "Sene", "Hamle", "Nehase",
  ]
  const pad = (n: number) => String(n).padStart(2, "0")
  const now = new Date()
  // The EC month starts on the 11th of a Gregorian month (approximation that
  // holds for payroll purposes; the dates stay editable).
  const anchor = now.getDate() >= 11 ? new Date(now.getFullYear(), now.getMonth(), 11) : new Date(now.getFullYear(), now.getMonth() - 1, 11)
  const end = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 10)
  const gm = anchor.getMonth() + 1 // 9=Sep → Meskerem
  const monthIndex = (gm - 9 + 12) % 12
  const ecYear = gm >= 9 ? anchor.getFullYear() - 7 : anchor.getFullYear() - 8
  return {
    name: `${MONTHS[monthIndex]} ${ecYear} E.C.`,
    period_start: `${anchor.getFullYear()}-${pad(anchor.getMonth() + 1)}-${pad(anchor.getDate())}`,
    period_end: `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}`,
  }
}

const schema = z
  .object({
    name: z.string().min(1, "Name is required").max(255),
    period_start: z.string().min(1, "Start date is required"),
    period_end: z.string().min(1, "End date is required"),
    notes: z.string().max(2000, "Keep notes under 2000 characters").optional(),
  })
  .refine((v) => !v.period_start || !v.period_end || v.period_end > v.period_start, {
    message: "End must be after start",
    path: ["period_end"],
  })

type FormValues = z.infer<typeof schema>

/**
 * Creates a DRAFT payroll run for one pay period — the backend computes every
 * payslip from current HR data immediately; the draft stays recomputable.
 */
export function PayrollRunSheet({
  open,
  onOpenChange,
  onSaved,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: (run: PayrollRun) => void
}) {
  const { t } = useTranslation("payroll")
  const { t: tc } = useTranslation("common")

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", period_start: "", period_end: "", notes: "" },
  })
  useLiveValidation(form)

  // School-wide workspace: a run is computed for one named branch.
  const { needsBranch } = useBranchScope()
  const [branchId, setBranchId] = useState<number | null>(null)
  const [branchError, setBranchError] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      form.reset({ ...currentEthiopianPeriod(), notes: "" })
      // eslint-disable-next-line react-hooks/set-state-in-effect -- seed dialog state on open
      setBranchId(null)
      setBranchError(null)
    }
  }, [open, form])

  async function onSubmit(values: FormValues) {
    if (needsBranch && branchId == null) {
      setBranchError(tc("branchField.required"))
      return
    }

    try {
      const res = await apiFetch<{ data: PayrollRun }>("/payroll-runs", {
        method: "POST",
        body: {
          ...values,
          notes: values.notes || null,
          ...(branchId != null ? { branch_id: branchId } : {}),
        },
      })
      toast.success(t("created"))
      onSaved(res.data)
      onOpenChange(false)
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
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t("createTitle")}</DialogTitle>
          <DialogDescription>{t("createDescription")}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <BranchField
              value={branchId}
              onChange={(id) => {
                setBranchId(id)
                setBranchError(null)
              }}
              error={branchError}
            />
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
            <div className="grid grid-cols-2 gap-3">
              <FormField
                control={form.control}
                name="period_start"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.periodStart")}</FormLabel>
                    <FormControl>
                      <DatePicker
                        value={field.value}
                        onChange={field.onChange}
                        onBlur={field.onBlur}
                        placeholder={t("fields.periodStart")}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="period_end"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("fields.periodEnd")}</FormLabel>
                    <FormControl>
                      <DatePicker
                        value={field.value}
                        onChange={field.onChange}
                        onBlur={field.onBlur}
                        placeholder={t("fields.periodEnd")}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
            <FormField
              control={form.control}
              name="notes"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t("fields.notes")}</FormLabel>
                  <FormControl>
                    <Input placeholder={t("fields.notesPlaceholder")} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                {tc("actions.cancel")}
              </Button>
              <Button type="submit" loading={form.formState.isSubmitting}>
                {t("createCta")}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  )
}
