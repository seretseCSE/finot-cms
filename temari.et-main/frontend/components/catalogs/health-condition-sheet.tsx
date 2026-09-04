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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { HealthCondition, HealthConditionCategory } from "@/lib/types"
import { cn } from "@/lib/utils"

export const HEALTH_CATEGORIES: HealthConditionCategory[] = [
  "allergy",
  "chronic",
  "neurological",
  "physical",
  "sensory",
  "mental_health",
  "blood",
  "other",
]

const schema = z.object({
  name: z.string().min(1, "Name is required").max(255),
  category: z.string().min(1),
  is_active: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  condition?: HealthCondition | null
  onSaved: (condition: HealthCondition) => void
  open: boolean
  onOpenChange: (open: boolean) => void
  showTrigger?: boolean
}

/** Create/edit a health-condition catalog row (platform studio). */
export function HealthConditionSheet({ condition, onSaved, open, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const isEdit = !!condition

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", category: "other", is_active: true },
  })
  useLiveValidation(form)

  useEffect(() => {
    if (!open) return
    form.reset(
      condition
        ? { name: condition.name, category: condition.category, is_active: condition.is_active ?? true }
        : { name: "", category: "other", is_active: true },
    )
  }, [open, condition, form])

  async function onSubmit(values: FormValues) {
    try {
      const res = await apiFetch<{ data: HealthCondition }>(
        isEdit ? `/catalogs/health-conditions/${condition!.id}` : "/catalogs/health-conditions",
        { method: isEdit ? "PUT" : "POST", body: values },
      )
      toast.success(isEdit ? t("health.updated") : t("health.created"))
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
            {t("health.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("health.editTitle") : t("health.createTitle")}
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
                      <Input placeholder={t("health.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="category"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("health.category")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {HEALTH_CATEGORIES.map((c) => (
                          <SelectItem key={c} value={c}>
                            {t(`health.categories.${c}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
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
                    <p className="text-xs text-muted-foreground">{t("health.activeHint")}</p>
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
