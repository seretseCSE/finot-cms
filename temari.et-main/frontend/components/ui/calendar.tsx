"use client"

import { ChevronDown, ChevronLeft, ChevronRight, ChevronUp } from "lucide-react"
import { DayPicker, type DayPickerProps } from "react-day-picker"

import { buttonVariants } from "@/components/ui/button"
import { cn } from "@/lib/utils"

/**
 * The Temari calendar — a thin, design-system skin over react-day-picker.
 * Not used directly in forms: reach for `DatePicker`, which wraps this in a
 * popover and speaks ISO `yyyy-MM-dd` strings.
 *
 * Day-state styling is driven by the `data-*` attributes react-day-picker
 * sets on each gridcell (`data-selected`, `data-today`, …) via `group/day`,
 * which keeps the library's built-in keyboard focus handling intact.
 */
function Calendar({
  className,
  classNames,
  showOutsideDays = true,
  ...props
}: DayPickerProps) {
  return (
    <DayPicker
      showOutsideDays={showOutsideDays}
      className={cn("p-3", className)}
      classNames={{
        months: "relative flex flex-col gap-4 sm:flex-row",
        month: "flex w-full flex-col gap-4",
        month_caption: "flex h-8 items-center justify-center px-8",
        caption_label: "flex items-center gap-1 text-sm font-medium",
        dropdowns:
          "flex items-center justify-center gap-1 text-sm font-medium",
        // Month/year jump: a native select overlays each caption pill — the
        // pill look + chevron make it obviously tappable.
        dropdown_root:
          "relative inline-flex items-center rounded-md px-1.5 py-0.5 transition-colors hover:bg-muted",
        dropdown:
          "absolute inset-0 cursor-pointer opacity-0 [&_option]:bg-popover [&_option]:text-popover-foreground",
        nav: "absolute inset-x-0 top-0 flex items-center justify-between",
        button_previous: cn(
          buttonVariants({ variant: "ghost", size: "icon-sm" }),
          "text-muted-foreground disabled:opacity-40"
        ),
        button_next: cn(
          buttonVariants({ variant: "ghost", size: "icon-sm" }),
          "text-muted-foreground disabled:opacity-40"
        ),
        month_grid: "w-full border-collapse",
        weekdays: "flex",
        weekday:
          "w-9 rounded-md text-[0.8rem] font-normal text-muted-foreground",
        week: "mt-1.5 flex w-full",
        day: "group/day size-9 p-0 text-center text-sm",
        day_button: cn(
          buttonVariants({ variant: "ghost", size: "icon-sm" }),
          "size-9 rounded-full font-normal",
          "group-data-[selected=true]/day:bg-primary group-data-[selected=true]/day:text-primary-foreground group-data-[selected=true]/day:hover:bg-primary/90 group-data-[selected=true]/day:hover:text-primary-foreground",
          "group-data-[today=true]/day:font-medium group-data-[today=true]/day:ring-1 group-data-[today=true]/day:ring-primary/40 group-data-[today=true]/day:ring-inset",
          "group-data-[outside=true]/day:text-muted-foreground/40",
          "group-data-[disabled=true]/day:opacity-40"
        ),
        hidden: "invisible",
        ...classNames,
      }}
      components={{
        Chevron: ({
          orientation,
          className: chevronClassName,
          ...chevronProps
        }) => {
          const Icon =
            orientation === "left"
              ? ChevronLeft
              : orientation === "right"
                ? ChevronRight
                : orientation === "up"
                  ? ChevronUp
                  : ChevronDown
          return (
            <Icon
              className={cn(
                orientation === "down"
                  ? "size-3.5 text-muted-foreground"
                  : "size-4",
                chevronClassName
              )}
              {...chevronProps}
            />
          )
        },
      }}
      {...props}
    />
  )
}

export { Calendar }
