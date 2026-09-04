"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { Plus } from "lucide-react"
import { useEffect } from "react"
import { useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/components/ui/button"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@/components/ui/form"
import { GradeMultiSelect } from "@/components/ui/grade-multi-select"
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
import type { GradeLevel, Subject } from "@/lib/types"
import { cn } from "@/lib/utils"

export const SUBJECT_CATEGORIES = [
  "language",
  "mathematics",
  "natural_science",
  "social_science",
  "technology",
  "arts_pe",
  "vocational",
] as const

export const SUBJECT_ROOM_TYPES = ["lab", "library", "ict", "gym", "music", "art", "hall"] as const

const NONE = "none"

const schema = z.object({
  code: z.string().min(1, "Code is required").max(20),
  name: z.string().min(1, "Name is required").max(255),
  category: z.string(),
  grade_level_ids: z.array(z.number()),
  weight: z.string(),
  room_type: z.string(),
  is_active: z.boolean(),
})

type FormValues = z.infer<typeof schema>

interface Props {
  /** The national grade ladder — powers the grade-window pickers. */
  gradeLevels: GradeLevel[]
  /** When set, edits this (platform) subject; otherwise creates one. */
  subject?: Subject | null
  onSaved: (subject: Subject) => void
  open: boolean
  onOpenChange: (open: boolean) => void
  showTrigger?: boolean
}

/** Create/edit a national-curriculum subject in the platform catalog studio. */
export function SubjectSheet({ gradeLevels, subject, onSaved, open, onOpenChange, showTrigger }: Props) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const isEdit = !!subject

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: "",
      name: "",
      category: NONE,
      grade_level_ids: [],
      weight: "3",
      room_type: NONE,
      is_active: true,
    },
  })
  useLiveValidation(form)

  useEffect(() => {
    if (!open) return
    form.reset(
      subject
        ? {
            code: subject.code,
            name: subject.name,
            category: subject.category ?? NONE,
            grade_level_ids: subject.grade_level_ids ?? [],
            weight: String(subject.weight ?? 3),
            room_type: subject.room_type ?? NONE,
            is_active: subject.is_active,
          }
        : {
            code: "",
            name: "",
            category: NONE,
            grade_level_ids: [],
            weight: "3",
            room_type: NONE,
            is_active: true,
          },
    )
  }, [open, subject, form])

  async function onSubmit(values: FormValues) {
    const body = {
      code: values.code,
      name: values.name,
      category: values.category === NONE ? null : values.category,
      grade_level_ids: values.grade_level_ids,
      weight: Number(values.weight),
      room_type: values.room_type === NONE ? null : values.room_type,
      is_active: values.is_active,
    }

    try {
      const res = await apiFetch<{ data: Subject }>(
        isEdit ? `/catalogs/subjects/${subject!.id}` : "/catalogs/subjects",
        { method: isEdit ? "PUT" : "POST", body },
      )
      toast.success(isEdit ? t("subjects.updated") : t("subjects.created"))
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
            {t("subjects.create")}
          </Button>
        </ResponsiveSheetTrigger>
      )}
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {isEdit ? t("subjects.editTitle") : t("subjects.createTitle")}
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
                        <Input placeholder="MATH" className="font-mono" {...field} />
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
                        <Input placeholder={t("subjects.namePlaceholder")} {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <FormField
                control={form.control}
                name="category"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("subjects.category")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value={NONE}>{t("subjects.noCategory")}</SelectItem>
                        {SUBJECT_CATEGORIES.map((c) => (
                          <SelectItem key={c} value={c}>
                            {t(`subjects.categories.${c}`)}
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
                name="grade_level_ids"
                render={({ field }) => (
                  <FormItem>
                    <div className="flex items-baseline justify-between">
                      <FormLabel>{t("subjects.grades")}</FormLabel>
                      <span className="text-muted-foreground text-xs tabular-nums">
                        {field.value.length === 0
                          ? t("subjects.allGrades")
                          : t("subjects.gradesSelected", { count: field.value.length })}
                      </span>
                    </div>
                    <GradeMultiSelect
                      gradeLevels={gradeLevels}
                      value={field.value}
                      onChange={field.onChange}
                    />
                    <p className="text-muted-foreground text-xs">{t("subjects.gradesHint")}</p>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="weight"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("subjects.weight")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {["1", "2", "3", "4", "5"].map((w) => (
                          <SelectItem key={w} value={w}>
                            {w} — {t(`subjects.weights.${w}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">{t("subjects.weightHint")}</p>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="room_type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("subjects.roomType")}</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger className="w-full">
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        <SelectItem value={NONE}>{t("subjects.ownClassroom")}</SelectItem>
                        {SUBJECT_ROOM_TYPES.map((r) => (
                          <SelectItem key={r} value={r}>
                            {t(`subjects.roomTypes.${r}`)}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">{t("subjects.roomTypeHint")}</p>
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
