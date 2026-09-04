"use client"

import * as React from "react"
import { ChevronDown, ChevronLeft, ChevronRight } from "lucide-react"

import { buttonVariants } from "@/components/ui/button"
import {
  addisToday,
  ethMonthName,
  weekdayName,
} from "@/lib/dates"
import { ethDaysInMonth, fromEthiopian, toEthiopian, type EthiopianDateParts } from "@/lib/ethiopian-date"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * The Ge'ez-calendar twin of components/ui/calendar.tsx: a 13-month Ethiopian
 * grid (30-day months + Pagume) that SPEAKS ISO Gregorian strings — same
 * `value`/`onSelect` contract as the DayPicker path, so forms and endpoints
 * never notice which calendar the school reads. Weeks anchor on Monday (the
 * school week); month/year jump via invisible selects over the caption, the
 * same trick react-day-picker's dropdown layout uses.
 */

export interface EthiopianCalendarProps {
  /** ISO `yyyy-MM-dd` (or empty). */
  selected?: string
  onSelect: (iso: string) => void
  /** Earliest/latest selectable day, ISO. */
  min?: string
  max?: string
  className?: string
}

function clampView(parts: EthiopianDateParts): { year: number; month: number } {
  return { year: parts.year, month: parts.month }
}

export function EthiopianCalendar({
  selected,
  onSelect,
  min,
  max,
  className,
}: EthiopianCalendarProps) {
  const { locale } = useTranslation("common")
  const today = addisToday()
  const todayEth = toEthiopian(today)

  const initial = selected
    ? clampView(toEthiopian(selected))
    : max && max < today
      ? clampView(toEthiopian(max))
      : clampView(todayEth)

  const [view, setView] = React.useState(initial)

  // Year options span the useful registry range: birth dates a century back,
  // planning a decade ahead — clamped to min/max when the field narrows them.
  const yearOptions = React.useMemo(() => {
    const lo = min ? toEthiopian(min).year : todayEth.year - 110
    const hi = max ? toEthiopian(max).year : todayEth.year + 10
    const years: number[] = []
    for (let y = hi; y >= lo; y--) years.push(y)
    return years
  }, [min, max, todayEth.year])

  function step(delta: number) {
    setView((v) => {
      let month = v.month + delta
      let year = v.year
      if (month < 1) {
        month = 13
        year--
      } else if (month > 13) {
        month = 1
        year++
      }
      return { year, month }
    })
  }

  const count = ethDaysInMonth(view.year, view.month)
  const firstIso = fromEthiopian({ year: view.year, month: view.month, day: 1 })
  if (!firstIso) return null

  // Monday-anchored column for day 1 (JS getDay: 0=Sun … 6=Sat → 0=Mon).
  const firstWeekday = (new Date(`${firstIso}T00:00:00Z`).getUTCDay() + 6) % 7

  const cells: (number | null)[] = [
    ...Array.from({ length: firstWeekday }, () => null),
    ...Array.from({ length: count }, (_, i) => i + 1),
  ]
  while (cells.length % 7 !== 0) cells.push(null)

  const weeks: (number | null)[][] = []
  for (let i = 0; i < cells.length; i += 7) weeks.push(cells.slice(i, i + 7))

  const selectedEth = selected ? toEthiopian(selected) : null
  const prevDisabled =
    min !== undefined && (fromEthiopian({ year: view.year, month: view.month, day: 1 }) ?? "") <= min && view.month === toEthiopian(min).month && view.year === toEthiopian(min).year
  const nextDisabled =
    max !== undefined && view.month === toEthiopian(max).month && view.year === toEthiopian(max).year

  return (
    <div className={cn("w-fit p-3", className)}>
      {/* caption: prev / month+year label with invisible selects / next */}
      <div className="relative flex h-8 items-center justify-center px-8">
        <button
          type="button"
          onClick={() => step(-1)}
          disabled={prevDisabled}
          aria-label={ethMonthName(view.month === 1 ? 13 : view.month - 1, locale)}
          className={cn(
            buttonVariants({ variant: "ghost", size: "icon-sm" }),
            "absolute left-0 text-muted-foreground disabled:opacity-40"
          )}
        >
          <ChevronLeft className="size-4" />
        </button>
        <div className="flex items-center justify-center gap-1 text-sm font-medium">
          {/* Month/year jump: a native select overlays each caption pill —
              the pill look + chevron make it obviously tappable. */}
          <span className="relative inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 transition-colors hover:bg-muted">
            {ethMonthName(view.month, locale)}
            <ChevronDown className="size-3.5 text-muted-foreground" />
            <select
              value={view.month}
              onChange={(e) => setView((v) => ({ ...v, month: Number(e.target.value) }))}
              aria-label={ethMonthName(view.month, locale)}
              className="absolute inset-0 cursor-pointer opacity-0 [&_option]:bg-popover [&_option]:text-popover-foreground"
            >
              {Array.from({ length: 13 }, (_, i) => i + 1).map((m) => (
                <option key={m} value={m}>
                  {ethMonthName(m, locale)}
                </option>
              ))}
            </select>
          </span>
          <span className="relative inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 tabular-nums transition-colors hover:bg-muted">
            {view.year}
            <ChevronDown className="size-3.5 text-muted-foreground" />
            <select
              value={view.year}
              onChange={(e) => setView((v) => ({ ...v, year: Number(e.target.value) }))}
              aria-label={String(view.year)}
              className="absolute inset-0 cursor-pointer opacity-0 [&_option]:bg-popover [&_option]:text-popover-foreground"
            >
              {yearOptions.map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </span>
        </div>
        <button
          type="button"
          onClick={() => step(1)}
          disabled={nextDisabled}
          aria-label={ethMonthName(view.month === 13 ? 1 : view.month + 1, locale)}
          className={cn(
            buttonVariants({ variant: "ghost", size: "icon-sm" }),
            "absolute right-0 text-muted-foreground disabled:opacity-40"
          )}
        >
          <ChevronRight className="size-4" />
        </button>
      </div>

      {/* weekday header — Monday first, the school week */}
      <div className="mt-4 flex">
        {[1, 2, 3, 4, 5, 6, 0].map((d) => (
          <span
            key={d}
            className="w-9 rounded-md text-center text-[0.8rem] font-normal text-muted-foreground"
          >
            {weekdayName(d, locale, true)}
          </span>
        ))}
      </div>

      {weeks.map((week, wi) => (
        <div key={wi} className="mt-1.5 flex w-full">
          {week.map((day, di) => {
            if (day === null) {
              return <span key={di} className="size-9" />
            }
            const iso = fromEthiopian({ year: view.year, month: view.month, day })
            const disabled = !iso || (min !== undefined && iso < min) || (max !== undefined && iso > max)
            const isSelected =
              selectedEth !== null &&
              selectedEth.year === view.year &&
              selectedEth.month === view.month &&
              selectedEth.day === day
            const isToday =
              todayEth.year === view.year && todayEth.month === view.month && todayEth.day === day

            return (
              <button
                key={di}
                type="button"
                disabled={disabled}
                onClick={() => iso && onSelect(iso)}
                className={cn(
                  buttonVariants({ variant: "ghost", size: "icon-sm" }),
                  "size-9 rounded-full text-sm font-normal tabular-nums",
                  isSelected &&
                    "bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground",
                  isToday && !isSelected && "font-medium ring-1 ring-inset ring-primary/40",
                  disabled && "pointer-events-none opacity-40"
                )}
              >
                {day}
              </button>
            )
          })}
        </div>
      ))}
    </div>
  )
}
