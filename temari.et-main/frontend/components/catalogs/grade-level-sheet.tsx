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
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { Cycle, GradeLevel } from "@/lib/types"

export const CYCLES: Cycle[] = [
  "kindergarten",
  "lower_primary",
  "upper_primary",
  "secondary",
  "preparatory",
]

const schema = z.object({
  code: z.string().min(1, "Code is required").max(10),
  name: z.string().min(1, "Name is required").max(100),
  cycle: z.string().min(1),
  sort_order: z.string().refine((v) => Number(v) >= 1 && Number(v) <= 100, "1–100"),
  has_national_exam: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  gradeLevel?: GradeLevel | null
  /** Suggested position for a new level (one past the current ladder). */
  nextSortOrder: number
  onSaved: (level: GradeLevel) => void
  open: boolean
  onOpenChange: (open: boolean) => void
  showTrigger?: boolean
}

/** Create/edit a rung of the national grade ladder (platform studio). */
export function GradeLevelSheet({ gradeLevel, nextSortOrder, onSaved, open, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const isEdit = !!gradeLevel

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: "",
      name: "",
      cycle: "kindergarten",
      sort_order: String(nextSortOrder),
      has_national_exam: false,
    },
  })
  useLiveValidation(form)

  useEffect(() => {
    if (!open) return
    form.reset(
      gradeLevel
        ? {
            code: gradeLevel.code,
            name: gradeLevel.name,
            cycle: gradeLevel.cycle,
            sort_order: String(gradeLevel.sort_order),
            has_national_exam: gradeLevel.has_national_exam,
          }
        : {
            code: "",
            name: "",
            cycle: "kindergarten",
            sort_order: String(nextSortOrder),
            has_national_exam: false,
          },
    )
  }, [open, gradeLevel, nextSortOrder, form])

  async function onSubmit(values: FormValues) {
    const body = {
      code: values.code.toUpperCase(),
      name: values.name,
      cycle: values.cycle,
      sort_order: Number(values.sort_order),
      has_national_exam: values.has_national_exam,
    }

    try {
      const res = await apiFetch<{ data: GradeLevel }>(
        isEdit ? `/catalogs/grade-levels/${gradeLevel!.id}` : "/catalogs/grade-levels",
        { method: isEdit ? "PUT" : "POST", body },
      )
      toast.success(isEdit ? t("gradeLevels.updated") : t("gradeLevels.created"))
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
            {t("gradeLevels.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("gradeLevels.editTitle") : t("gradeLevels.createTitle")}
          </ResponsiveSheetTitle>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-5">
              <div className="grid grid-cols-[7.5rem_1fr] gap-3">
                <FormField
                  control={form.control}
                  name="code"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.code")}</FormLabel>
                      <FormControl>
                        <Input placeholder="G9" className="font-mono uppercase" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="name"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("fields.name")}</FormLabel>
                      <FormControl>
                        <Input placeholder={t("gradeLevels.namePlaceholder")} {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <FormField
                control={form.control}
                name="cycle"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("gradeLevels.cycle")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {CYCLES.map((c) => (
                          <SelectItem key={c} value={c}>
                            {t(`gradeLevels.cycles.${c}`)}
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
                name="sort_order"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("gradeLevels.sortOrder")}</FormLabel>
                    <FormControl>
                      <Input type="number" inputMode="numeric" min={1} max={100} {...field} />
                    </FormControl>
                    <p className="text-xs text-muted-foreground">{t("gradeLevels.sortOrderHint")}</p>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="has_national_exam"
                render={({ field }) => (
                  <FormItem className="flex items-center justify-between gap-4 rounded-xl border bg-muted/30 px-4 py-3">
                    <div className="space-y-0.5">
                      <FormLabel>{t("gradeLevels.nationalExam")}</FormLabel>
                      <p className="text-xs text-muted-foreground">
                        {t("gradeLevels.nationalExamHint")}
                      </p>
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
