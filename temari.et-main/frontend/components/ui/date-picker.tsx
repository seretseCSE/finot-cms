"use client"

import * as React from "react"
import { format, isValid, parse } from "date-fns"
import { CalendarIcon, X } from "lucide-react"
import type { Matcher } from "react-day-picker"

import { Button } from "@/components/ui/button"
import { Calendar } from "@/components/ui/calendar"
import { EthiopianCalendar } from "@/components/ui/ethiopian-calendar"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { fmtDate } from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { useCalendar } from "@/lib/use-calendar"
import { cn } from "@/lib/utils"

const ISO = "yyyy-MM-dd"

/** Parse an ISO `yyyy-MM-dd` string into a local Date, or `undefined`. */
function fromISO(value?: string | null): Date | undefined {
  if (!value) return undefined
  const parsed = parse(value, ISO, new Date())
  return isValid(parsed) ? parsed : undefined
}

export interface DatePickerProps {
  /** ISO `yyyy-MM-dd` string (or empty). */
  value?: string | null
  /** Emits an ISO `yyyy-MM-dd` string, or `""` when cleared. */
  onChange: (value: string) => void
  onBlur?: () => void
  placeholder?: string
  /** Earliest selectable day, ISO `yyyy-MM-dd`. */
  min?: string
  /** Latest selectable day, ISO `yyyy-MM-dd`. */
  max?: string
  disabled?: boolean
  /** Show a clear action once a date is chosen. Defaults to `true`. */
  clearable?: boolean
  /** Month/year navigation. Defaults to `"dropdown"` — jumping ten years back
   * must never mean ten arrow taps. Pass `"label"` for arrows-only. */
  captionLayout?: "label" | "dropdown" | "dropdown-months" | "dropdown-years"
  /** Classes for the trigger button. */
  className?: string
  id?: string
  "aria-describedby"?: string
  "aria-invalid"?: boolean | "true" | "false"
  "aria-label"?: string
}

export function DatePicker({
  value,
  onChange,
  onBlur,
  placeholder,
  min,
  max,
  disabled,
  clearable = true,
  captionLayout = "dropdown",
  className,
  id,
  ...aria
}: DatePickerProps) {
  const { t } = useTranslation("common")
  const { calendar } = useCalendar()
  const [open, setOpen] = React.useState(false)

  const selected = fromISO(value)
  const minDate = fromISO(min)
  const maxDate = fromISO(max)

  // The dropdown year list needs bounds; when the field doesn't narrow them,
  // span the useful registry range — birth dates a century back, planning a
  // decade ahead (mirrors the Ethiopian calendar's year options).
  const thisYear = new Date().getFullYear()
  const navStart = minDate ?? new Date(thisYear - 110, 0, 1)
  const navEnd = maxDate ?? new Date(thisYear + 10, 11, 31)

  const outOfRange: Matcher[] = []
  if (minDate) outOfRange.push({ before: minDate })
  if (maxDate) outOfRange.push({ after: maxDate })

  function handleClear(event: React.MouseEvent) {
    event.preventDefault()
    event.stopPropagation()
    onChange("")
    onBlur?.()
  }

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (!next) onBlur?.()
      }}
    >
      <PopoverTrigger asChild>
        <Button
          id={id}
          type="button"
          variant="outline"
          disabled={disabled}
          data-empty={!selected}
          className={cn(
            "h-11 w-full justify-start rounded-xl border-input/70 bg-muted/30 px-3.5 font-normal data-[empty=true]:text-muted-foreground dark:bg-input/30",
            className
          )}
          {...aria}
        >
          <CalendarIcon className="size-4 shrink-0 text-muted-foreground" />
          <span className="min-w-0 truncate">
            {selected && value
              ? fmtDate(value)
              : (placeholder ?? t("datePicker.placeholder"))}
          </span>
          {clearable && selected && !disabled && (
            <span
              role="button"
              tabIndex={-1}
              aria-label={t("datePicker.clear")}
              onClick={handleClear}
              onPointerDown={(e) => e.stopPropagation()}
              className="ml-auto grid size-5 shrink-0 place-items-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
            >
              <X className="size-3.5" />
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-auto p-0">
        {calendar === "ethiopian" ? (
          <EthiopianCalendar
            selected={value ?? undefined}
            min={min}
            max={max}
            onSelect={(iso) => {
              onChange(iso)
              setOpen(false)
            }}
          />
        ) : (
          <Calendar
            mode="single"
            selected={selected}
            defaultMonth={selected ?? maxDate}
            startMonth={navStart}
            endMonth={navEnd}
            captionLayout={captionLayout}
            disabled={outOfRange.length ? outOfRange : undefined}
            onSelect={(date) => {
              onChange(date ? format(date, ISO) : "")
              setOpen(false)
            }}
            autoFocus
          />
        )}
      </PopoverContent>
    </Popover>
  )
}
