"use client"

import * as React from "react"
import { Clock, X } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import {
  dayPeriodLabel,
  ethClockParts,
  ethClockToHour24,
  fmtTime,
  type DayPeriod,
} from "@/lib/dates"
import { useTranslation } from "@/lib/i18n"
import { useCalendar } from "@/lib/use-calendar"
import { cn } from "@/lib/utils"

type Period = "AM" | "PM"

interface TimeParts {
  /** 24-hour clock, 0–23. */
  hour: number
  minute: number
}

/** Parse an `HH:MM` (24h) string, or `undefined`. */
function fromHM(value?: string | null): TimeParts | undefined {
  if (!value) return undefined
  const match = /^(\d{1,2}):(\d{2})/.exec(value)
  if (!match) return undefined
  const hour = Number(match[1])
  const minute = Number(match[2])
  if (hour > 23 || minute > 59) return undefined
  return { hour, minute }
}

function toHM({ hour, minute }: TimeParts): string {
  return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`
}

function to12h(hour: number): { hour12: number; period: Period } {
  return { hour12: hour % 12 === 0 ? 12 : hour % 12, period: hour < 12 ? "AM" : "PM" }
}

function to24h(hour12: number, period: Period): number {
  const base = hour12 % 12
  return period === "PM" ? base + 12 : base
}

const HOURS = Array.from({ length: 12 }, (_, i) => (i === 0 ? 12 : i)) // 12, 1 … 11
// Ethiopian dawn-count wheel: 12, 1 … 11 too, but 12 = the top of a half
// (6:00 or 18:00) — the natural reading order of the spoken clock.
const ETH_HOURS = [12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]
const ETH_PERIODS: DayPeriod[] = ["morning", "afternoon", "evening", "night"]
const MINUTES = Array.from({ length: 60 }, (_, i) => i)

/** One snap-scrolling column of the picker; keeps the active option centered. */
function WheelColumn<T extends number | string>({
  options,
  selected,
  format,
  onSelect,
  open,
}: {
  options: readonly T[]
  selected: T | undefined
  format: (option: T) => string
  onSelect: (option: T) => void
  open: boolean
}) {
  const listRef = React.useRef<HTMLDivElement>(null)

  // Center the selection when the popover opens and whenever it changes.
  React.useEffect(() => {
    if (!open || selected === undefined) return
    const el = listRef.current?.querySelector<HTMLElement>(`[data-option="${selected}"]`)
    el?.scrollIntoView({ block: "center" })
  }, [open, selected])

  return (
    <div
      ref={listRef}
      className="scrollbar-none flex h-56 min-w-14 snap-y snap-mandatory flex-col gap-0.5 overflow-y-auto overscroll-contain px-1 py-[6.5rem]"
    >
      {options.map((option) => (
        <button
          key={option}
          type="button"
          data-option={option}
          onClick={() => onSelect(option)}
          className={cn(
            "pressable shrink-0 snap-center rounded-lg py-1.5 text-sm tabular-nums transition-colors",
            option === selected
              ? "bg-primary font-semibold text-primary-foreground"
              : "text-foreground/80 hover:bg-muted"
          )}
        >
          {format(option)}
        </button>
      ))}
    </div>
  )
}

export interface TimePickerProps {
  /** 24-hour `HH:MM` string (or empty). */
  value?: string | null
  /** Emits a 24-hour `HH:MM` string, or `""` when cleared. */
  onChange: (value: string) => void
  onBlur?: () => void
  /** Forwarded to the trigger — table cells stop row-click propagation here. */
  onClick?: (event: React.MouseEvent<HTMLButtonElement>) => void
  placeholder?: string
  disabled?: boolean
  /** Show a clear action once a time is chosen. Defaults to `true`. */
  clearable?: boolean
  /** Classes for the trigger button. */
  className?: string
  id?: string
  "aria-describedby"?: string
  "aria-invalid"?: boolean | "true" | "false"
  "aria-label"?: string
}

/**
 * The Temari time field: a DatePicker-style trigger opening hour / minute /
 * AM-PM wheels — replaces the browser-native `<input type="time">`, which
 * clipped its value at narrow widths and looked different per browser.
 */
export function TimePicker({
  value,
  onChange,
  onBlur,
  onClick,
  placeholder,
  disabled,
  clearable = true,
  className,
  id,
  ...aria
}: TimePickerProps) {
  const { t, locale } = useTranslation("common")
  const { clock } = useCalendar()
  const [open, setOpen] = React.useState(false)

  const parts = fromHM(value)
  const display = parts ? to12h(parts.hour) : undefined
  const ethDisplay = parts ? ethClockParts(parts.hour) : undefined

  /** Merge one wheel's pick into the current value (defaults fill the rest). */
  function pick(patch: Partial<{ hour12: number; minute: number; period: Period }>) {
    const current = {
      hour12: display?.hour12 ?? 8,
      minute: parts?.minute ?? 0,
      period: display?.period ?? "AM",
      ...patch,
    }
    onChange(toHM({ hour: to24h(current.hour12, current.period), minute: current.minute }))
  }

  /** The Ethiopian-clock twin: hour + day-period wheels → 24h storage. */
  function pickEth(patch: Partial<{ ethHour: number; minute: number; period: DayPeriod }>) {
    const current = {
      ethHour: ethDisplay?.ethHour ?? 2, // 2 ጠዋት = 8:00 AM, the school morning
      minute: parts?.minute ?? 0,
      period: ethDisplay?.period ?? "morning",
      ...patch,
    }
    onChange(toHM({ hour: ethClockToHour24(current.ethHour, current.period), minute: current.minute }))
  }

  function handleClear(event: React.MouseEvent) {
    event.preventDefault()
    event.stopPropagation()
    onChange("")
    onBlur?.()
  }

  return (
    <Popover
      open={open}
      // Modal: takes over the scroll lock so the wheels stay scrollable when
      // the picker opens inside a Sheet/Dialog (whose lock would otherwise
      // swallow wheel events aimed at the portaled popover).
      modal
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
          data-empty={!parts}
          onClick={onClick}
          className={cn(
            "h-11 w-full justify-start rounded-xl border-input/70 bg-muted/30 px-3.5 font-normal data-[empty=true]:text-muted-foreground dark:bg-input/30",
            className
          )}
          {...aria}
        >
          <Clock className="size-4 shrink-0 text-muted-foreground" />
          <span className="min-w-0 truncate tabular-nums">
            {parts && display
              ? clock === "ethiopian"
                ? fmtTime(value, { clock, locale })
                : `${display.hour12}:${String(parts.minute).padStart(2, "0")} ${t(`timePicker.${display.period.toLowerCase()}`)}`
              : (placeholder ?? t("timePicker.placeholder"))}
          </span>
          {clearable && parts && !disabled && (
            <span
              role="button"
              tabIndex={-1}
              aria-label={t("timePicker.clear")}
              onClick={handleClear}
              onPointerDown={(e) => e.stopPropagation()}
              className="ml-auto grid size-5 shrink-0 place-items-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
            >
              <X className="size-3.5" />
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        className="w-auto p-0"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="relative flex divide-x divide-border/60">
          {/* Center-line highlight behind the three wheels */}
          <div className="pointer-events-none absolute inset-x-1 top-1/2 h-9 -translate-y-1/2 rounded-lg bg-muted/50" />
          {clock === "ethiopian" ? (
            <>
              <WheelColumn
                options={ETH_HOURS}
                selected={ethDisplay?.ethHour}
                format={(h) => String(h)}
                onSelect={(ethHour) => pickEth({ ethHour })}
                open={open}
              />
              <WheelColumn
                options={MINUTES}
                selected={parts?.minute}
                format={(m) => String(m).padStart(2, "0")}
                onSelect={(minute) => pickEth({ minute })}
                open={open}
              />
              <WheelColumn
                options={ETH_PERIODS}
                selected={ethDisplay?.period}
                format={(p) => dayPeriodLabel(p, locale)}
                onSelect={(period) => pickEth({ period })}
                open={open}
              />
            </>
          ) : (
            <>
              <WheelColumn
                options={HOURS}
                selected={display?.hour12}
                format={(h) => String(h)}
                onSelect={(hour12) => pick({ hour12 })}
                open={open}
              />
              <WheelColumn
                options={MINUTES}
                selected={parts?.minute}
                format={(m) => String(m).padStart(2, "0")}
                onSelect={(minute) => pick({ minute })}
                open={open}
              />
              <WheelColumn
                options={["AM", "PM"] as const}
                selected={display?.period}
                format={(p) => t(`timePicker.${p.toLowerCase()}`)}
                onSelect={(period) => pick({ period })}
                open={open}
              />
            </>
          )}
        </div>
        <div className="flex items-center justify-between gap-2 border-t border-border/60 p-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => {
              const now = new Date()
              onChange(toHM({ hour: now.getHours(), minute: now.getMinutes() }))
            }}
          >
            {t("timePicker.now")}
          </Button>
          <Button type="button" size="sm" onClick={() => setOpen(false)}>
            {t("timePicker.done")}
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}
