"use client"

import { TriangleAlert } from "lucide-react"
import { useState } from "react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { FormControl } from "@/components/ui/form"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { useTranslation } from "@/lib/i18n"
import type { AcademicYear } from "@/lib/types"

/** The branch's operating year — what every new enrollment should default to. */
export function activeAcademicYearId(years: AcademicYear[]): string {
  const active = years.find((year) => year.is_current) ?? years.find((year) => year.status === "active")
  return active ? String(active.id) : ""
}

export function yearIsActive(year: AcademicYear): boolean {
  return year.is_current || year.status === "active"
}

/**
 * Year options for enrollment pickers: every option carries its status chip so
 * a planned/completed year is visibly not the operating one before it's picked.
 */
export function AcademicYearSelectItems({ years }: { years: AcademicYear[] }) {
  const { t: ta } = useTranslation("academic")

  return (
    <>
      {years.map((year) => (
        <SelectItem key={year.id} value={String(year.id)}>
          <span className="flex items-center gap-2">
            {year.name}
            {yearIsActive(year) ? (
              <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                {ta(`years.statuses.${year.status}`)}
              </span>
            ) : (
              <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                {ta(`years.statuses.${year.status}`)}
              </span>
            )}
          </span>
        </SelectItem>
      ))}
    </>
  )
}

/**
 * Alert under the year picker when a non-active year is chosen — new students
 * belong in the current cycle; picking another year is almost always a slip.
 */
export function YearStatusWarning({ year }: { year: AcademicYear | undefined }) {
  const { t } = useTranslation("students")
  const { t: ta } = useTranslation("academic")

  if (!year || yearIsActive(year)) return null

  return (
    // Same light amber tint as the system's other warnings (import studio),
    // with dark text for contrast.
    <div className="flex items-start gap-2.5 rounded-xl border border-amber-300/60 bg-amber-50/50 p-3 dark:border-amber-500/30 dark:bg-amber-500/5">
      <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
      <p className="text-sm font-medium leading-relaxed text-amber-800 dark:text-amber-300">
        {t("enroll.yearNotActive", { status: ta(`years.statuses.${year.status}`) })}
      </p>
    </div>
  )
}

/**
 * The one enrollment year picker: options wear status chips, a non-active pick
 * must be CONFIRMED in a modal before it applies (cancel keeps the previous
 * value), and a confirmed non-active year keeps the warning below the field.
 * Renders inside an RHF <FormField> (FormControl wires the aria attributes).
 */
export function AcademicYearPicker({
  years,
  value,
  onChange,
  placeholder,
}: {
  years: AcademicYear[]
  value: string
  onChange: (value: string) => void
  placeholder: string
}) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const { t: ta } = useTranslation("academic")
  const [pending, setPending] = useState<AcademicYear | null>(null)
  const selectedYear = years.find((year) => String(year.id) === value)

  function handlePick(next: string) {
    const year = years.find((y) => String(y.id) === next)
    if (year && !yearIsActive(year) && next !== value) {
      setPending(year)
      return
    }
    onChange(next)
  }

  return (
    <>
      <Select value={value} onValueChange={handlePick}>
        <FormControl>
          <SelectTrigger className="w-full">
            <SelectValue placeholder={placeholder} />
          </SelectTrigger>
        </FormControl>
        <SelectContent>
          <AcademicYearSelectItems years={years} />
        </SelectContent>
      </Select>
      <YearStatusWarning year={selectedYear} />

      <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("enroll.yearConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>
              {pending
                ? t("enroll.yearConfirmBody", {
                    year: pending.name,
                    status: ta(`years.statuses.${pending.status}`),
                  })
                : null}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{tc("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (pending) onChange(String(pending.id))
                setPending(null)
              }}
            >
              {t("enroll.yearConfirmAction")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
