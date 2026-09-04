"use client"

import { zodResolver } from "@hookform/resolvers/zod"
import { CalendarOff, Pencil, Plus, Trash2 } from "lucide-react"
import { useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { useConfirmDelete } from "@/components/ui/confirm-delete"
import { DatePicker } from "@/components/ui/date-picker"
import { EmptyState } from "@/components/ui/empty-state"
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
  ResponsiveSheetDescription,
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
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { useLiveValidation } from "@/lib/use-live-validation"
import type { Holiday, LeaveType } from "@/lib/types"

// ─── Leave types (school policy) ────────────────────────────────────────────

const typeSchema = z.object({
  name: z.string().min(2, "Name is required"),
  no_cap: z.boolean(),
  days_per_year: z.string().optional(),
  is_paid: z.boolean(),
  applicable_gender: z.string(),
  requires_note: z.boolean(),
  is_active: z.boolean(),
})

type TypeFormValues = z.infer<typeof typeSchema>

const typeDefaults: TypeFormValues = {
  name: "",
  no_cap: false,
  days_per_year: "",
  is_paid: true,
  applicable_gender: "any",
  requires_note: false,
  is_active: true,
}

function LeaveTypeSheet({
  leaveType,
  open,
  onOpenChange,
  onSaved,
}: {
  leaveType: LeaveType | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
}) {
  const { t } = useTranslation("hr")
  const { t: tc } = useTranslation("common")

  const form = useForm<TypeFormValues>({
    resolver: zodResolver(typeSchema),
    defaultValues: typeDefaults,
    values: leaveType
      ? {
          name: leaveType.name,
          no_cap: leaveType.days_per_year == null,
          days_per_year: leaveType.days_per_year != null ? String(leaveType.days_per_year) : "",
          is_paid: leaveType.is_paid,
          applicable_gender: leaveType.applicable_gender ?? "any",
          requires_note: leaveType.requires_note,
          is_active: leaveType.is_active,
        }
      : typeDefaults,
  })
  useLiveValidation(form)

  const noCap = useWatch({ control: form.control, name: "no_cap" })

  function handleOpenChange(value: boolean) {
    onOpenChange(value)
    if (!value) form.reset(typeDefaults)
  }

  async function onSubmit(values: TypeFormValues) {
    try {
      const body = {
        name: values.name,
        days_per_year: values.no_cap || !values.days_per_year ? null : Number(values.days_per_year),
        is_paid: values.is_paid,
        applicable_gender: values.applicable_gender === "any" ? null : values.applicable_gender,
        requires_note: values.requires_note,
        is_active: values.is_active,
      }
      if (leaveType) {
        await apiFetch(`/hr/leave-types/${leaveType.id}`, { method: "PUT", body })
      } else {
        await apiFetch("/hr/leave-types", { method: "POST", body })
      }
      toast.success(t("leave.types.saved"))
      onSaved()
      handleOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof TypeFormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error(t("error"))
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={handleOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>
            {leaveType ? leaveType.name : t("leave.types.add")}
          </ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("leave.types.hint")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("leave.types.name")}</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FormField
                  control={form.control}
                  name="days_per_year"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("leave.types.daysPerYear")}</FormLabel>
                      <FormControl>
                        <Input
                          type="number"
                          min={0.5}
                          step={0.5}
                          disabled={noCap}
                          {...field}
                        />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="applicable_gender"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{t("leave.types.gender")}</FormLabel>
                      <Select value={field.value} onValueChange={field.onChange}>
                        <FormControl>
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value="any">{t("leave.types.genderAny")}</SelectItem>
                          <SelectItem value="female">{t("leave.types.female")}</SelectItem>
                          <SelectItem value="male">{t("leave.types.male")}</SelectItem>
                        </SelectContent>
                      </Select>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>
              <FormField
                control={form.control}
                name="no_cap"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center gap-2.5 space-y-0">
                    <FormControl>
                      <Checkbox
                        checked={field.value}
                        onCheckedChange={(checked) => field.onChange(checked === true)}
                      />
                    </FormControl>
                    <FormLabel className="!mt-0 font-normal">{t("leave.types.noCap")}</FormLabel>
                  </FormItem>
                )}
              />
              {(
                [
                  ["is_paid", "leave.types.paidLabel"],
                  ["requires_note", "leave.types.requiresNote"],
                  ["is_active", "leave.types.active"],
                ] as const
              ).map(([name, labelKey]) => (
                <FormField
                  key={name}
                  control={form.control}
                  name={name}
                  render={({ field }) => (
                    <FormItem className="flex flex-row items-center justify-between space-y-0 rounded-xl border px-3.5 py-3">
                      <FormLabel className="!mt-0 font-normal">{t(labelKey)}</FormLabel>
                      <FormControl>
                        <Switch checked={field.value} onCheckedChange={field.onChange} />
                      </FormControl>
                    </FormItem>
                  )}
                />
              ))}
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="submit" className="h-11 w-full" loading={form.formState.isSubmitting}>
                {tc("actions.save")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

// ─── Holidays ───────────────────────────────────────────────────────────────

const holidaySchema = z.object({
  name: z.string().min(2, "Name is required"),
  date: z.string().min(1, "Pick a date"),
  this_branch_only: z.boolean(),
})

type HolidayFormValues = z.infer<typeof holidaySchema>

const holidayDefaults: HolidayFormValues = { name: "", date: "", this_branch_only: false }

function HolidaySheet({
  open,
  onOpenChange,
  onSaved,
  branchId,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved: () => void
  branchId: number | null
}) {
  const { t } = useTranslation("hr")

  const form = useForm<HolidayFormValues>({
    resolver: zodResolver(holidaySchema),
    defaultValues: holidayDefaults,
  })
  useLiveValidation(form)

  function handleOpenChange(value: boolean) {
    onOpenChange(value)
    if (!value) form.reset(holidayDefaults)
  }

  async function onSubmit(values: HolidayFormValues) {
    try {
      await apiFetch("/hr/holidays", {
        method: "POST",
        body: {
          name: values.name,
          date: values.date,
          branch_id: values.this_branch_only && branchId != null ? branchId : null,
        },
      })
      toast.success(t("leave.holidays.added"))
      onSaved()
      handleOpenChange(false)
    } catch (error) {
      if (error instanceof ApiError) {
        for (const [field, messages] of Object.entries(error.errors)) {
          form.setError(field as keyof HolidayFormValues, { type: "server", message: messages[0] })
        }
        if (Object.keys(error.errors).length === 0) toast.error(error.message)
      } else {
        toast.error(t("error"))
      }
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={handleOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("leave.holidays.add")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("leave.holidays.hint")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex min-h-0 flex-1 flex-col">
            <ResponsiveSheetBody className="space-y-4">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("leave.holidays.name")}</FormLabel>
                    <FormControl>
                      <Input placeholder={t("leave.holidays.namePlaceholder")} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{t("leave.holidays.date")}</FormLabel>
                    <FormControl>
                      <DatePicker value={field.value} onChange={field.onChange} clearable={false} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              {branchId != null && (
                <FormField
                  control={form.control}
                  name="this_branch_only"
                  render={({ field }) => (
                    <FormItem className="flex flex-row items-center justify-between space-y-0 rounded-xl border px-3.5 py-3">
                      <FormLabel className="!mt-0 font-normal">
                        {t("leave.holidays.branch")}
                      </FormLabel>
                      <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        {field.value ? t("leave.holidays.branch") : t("leave.holidays.allBranches")}
                        <FormControl>
                          <Switch checked={field.value} onCheckedChange={field.onChange} />
                        </FormControl>
                      </div>
                    </FormItem>
                  )}
                />
              )}
            </ResponsiveSheetBody>
            <ResponsiveSheetFooter>
              <Button type="submit" className="h-11 w-full" loading={form.formState.isSubmitting}>
                {t("leave.holidays.add")}
              </Button>
            </ResponsiveSheetFooter>
          </form>
        </Form>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}

// ─── The settings panel (types + holidays) ──────────────────────────────────

export function LeaveSettings({
  leaveTypes,
  holidays,
  branchId,
  canManage,
  onChanged,
}: {
  leaveTypes: LeaveType[]
  holidays: Holiday[]
  branchId: number | null
  canManage: boolean
  onChanged: () => void
}) {
  const { t } = useTranslation("hr")
  const { t: tc } = useTranslation("common")
  const { confirmDelete, confirmDialog } = useConfirmDelete()

  const [editingType, setEditingType] = useState<LeaveType | null>(null)
  const [typeSheetOpen, setTypeSheetOpen] = useState(false)
  const [holidaySheetOpen, setHolidaySheetOpen] = useState(false)

  async function deleteType(type: LeaveType) {
    try {
      const res = await apiFetch<{ message: string }>(`/hr/leave-types/${type.id}`, {
        method: "DELETE",
      })
      toast.success(res.message ?? t("leave.types.deleted"))
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    }
  }

  async function deleteHoliday(holiday: Holiday) {
    try {
      await apiFetch(`/hr/holidays/${holiday.id}`, { method: "DELETE" })
      toast.success(t("leave.holidays.deleted"))
      onChanged()
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("error"))
    }
  }

  return (
    <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
      {confirmDialog}

      {/* Leave types */}
      <section className="rounded-2xl border bg-card shadow-xs">
        <header className="flex items-start justify-between gap-3 border-b p-4">
          <div>
            <h2 className="font-display text-base font-semibold">{t("leave.types.title")}</h2>
            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
              {t("leave.types.hint")}
            </p>
          </div>
          {canManage && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setEditingType(null)
                setTypeSheetOpen(true)
              }}
            >
              <Plus className="size-3.5" />
              {t("leave.types.add")}
            </Button>
          )}
        </header>
        <ul className="divide-y">
          {leaveTypes.map((type) => (
            <li key={type.id} className="flex items-center justify-between gap-3 px-4 py-3">
              <div className="min-w-0">
                <p className="truncate text-sm font-medium">
                  {type.name}
                  {!type.is_active && (
                    <Badge variant="secondary" className="ml-2 text-[11px]">
                      {tc("states.inactive")}
                    </Badge>
                  )}
                </p>
                <p className="mt-0.5 flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                  <span className="tabular-nums">
                    {type.days_per_year != null
                      ? `${type.days_per_year} ${t("leave.columns.days").toLowerCase()}`
                      : t("leave.types.noCap")}
                  </span>
                  <span>·</span>
                  <span>{type.is_paid ? t("leave.paid") : t("leave.unpaid")}</span>
                  {type.applicable_gender && (
                    <>
                      <span>·</span>
                      <span>
                        {type.applicable_gender === "female"
                          ? t("leave.types.female")
                          : t("leave.types.male")}
                      </span>
                    </>
                  )}
                  {type.service_bonus_days > 0 && type.service_bonus_every_years > 0 && (
                    <>
                      <span>·</span>
                      <span>
                        {t("leave.types.serviceBonus", {
                          days: type.service_bonus_days,
                          years: type.service_bonus_every_years,
                        })}
                      </span>
                    </>
                  )}
                </p>
              </div>
              {canManage && (
                <div className="flex shrink-0 gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t("leave.types.title")}
                    onClick={() => {
                      setEditingType(type)
                      setTypeSheetOpen(true)
                    }}
                  >
                    <Pencil className="size-3.5" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    className="text-destructive"
                    aria-label={t("leave.types.deleteConfirm")}
                    onClick={() =>
                      confirmDelete(() => deleteType(type), t("leave.types.deleteConfirm"))
                    }
                  >
                    <Trash2 className="size-3.5" />
                  </Button>
                </div>
              )}
            </li>
          ))}
        </ul>
      </section>

      {/* Holidays */}
      <section className="rounded-2xl border bg-card shadow-xs">
        <header className="flex items-start justify-between gap-3 border-b p-4">
          <div>
            <h2 className="font-display text-base font-semibold">{t("leave.holidays.title")}</h2>
            <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">
              {t("leave.holidays.hint")}
            </p>
          </div>
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => setHolidaySheetOpen(true)}>
              <Plus className="size-3.5" />
              {t("leave.holidays.add")}
            </Button>
          )}
        </header>
        {holidays.length === 0 ? (
          <EmptyState icon={CalendarOff} title={t("leave.holidays.empty")} compact />
        ) : (
          <ul className="divide-y">
            {holidays.map((holiday) => (
              <li key={holiday.id} className="flex items-center justify-between gap-3 px-4 py-3">
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium">{holiday.name}</p>
                  <p className="mt-0.5 text-xs text-muted-foreground tabular-nums">
                    {holiday.date}
                    {holiday.branch_id != null && holiday.branch_name && (
                      <> · {holiday.branch_name}</>
                    )}
                  </p>
                </div>
                {canManage && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="shrink-0 text-destructive"
                    aria-label={t("leave.holidays.deleteConfirm")}
                    onClick={() =>
                      confirmDelete(() => deleteHoliday(holiday), t("leave.holidays.deleteConfirm"))
                    }
                  >
                    <Trash2 className="size-3.5" />
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      <LeaveTypeSheet
        leaveType={editingType}
        open={typeSheetOpen}
        onOpenChange={(v) => {
          setTypeSheetOpen(v)
          if (!v) setEditingType(null)
        }}
        onSaved={onChanged}
      />
      <HolidaySheet
        open={holidaySheetOpen}
        onOpenChange={setHolidaySheetOpen}
        onSaved={onChanged}
        branchId={branchId}
      />
    </div>
  )
}
