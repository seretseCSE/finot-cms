"use client"

import * as React from "react"
import { Command as CommandPrimitive } from "cmdk"
import { CheckIcon, ChevronsUpDownIcon } from "lucide-react"

import { cn, preventAutoFocusOnTouch } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"

export interface MultiComboboxOption {
  value: number
  label: string
}

interface MultiComboboxProps {
  options: readonly MultiComboboxOption[]
  value: number[]
  onChange: (value: number[]) => void
  /** Shown when nothing is selected (i.e. "all" on this axis). */
  allLabel: string
  searchPlaceholder?: string
  emptyText?: string
  disabled?: boolean
  className?: string
  align?: "start" | "center" | "end"
}

/**
 * Searchable multi-select (checkbox rows + a summary trigger) over
 * {value, label} options. An empty selection reads as "all" — the trigger
 * shows `allLabel`. Same Popover + cmdk shape as OptionCombobox, so it stays
 * scrollable inside sheets/dialogs (modal popover).
 */
export function MultiCombobox({
  options,
  value,
  onChange,
  allLabel,
  searchPlaceholder = "Search...",
  emptyText = "No results found.",
  disabled,
  className,
  align = "start",
}: MultiComboboxProps) {
  const [open, setOpen] = React.useState(false)

  const selectedLabels = React.useMemo(
    () =>
      options
        .filter((option) => value.includes(option.value))
        .map((option) => option.label),
    [options, value]
  )

  // Long selections summarize ("Biology (BIO), Chemistry (CHEM) +5") instead
  // of truncating into an unreadable half-word on narrow screens.
  const summary =
    selectedLabels.length === 0
      ? allLabel
      : selectedLabels.length <= 2
        ? selectedLabels.join(", ")
        : `${selectedLabels.slice(0, 2).join(", ")} +${selectedLabels.length - 2}`

  function toggle(optionValue: number) {
    onChange(
      value.includes(optionValue)
        ? value.filter((v) => v !== optionValue)
        : [...value, optionValue]
    )
  }

  return (
    <Popover open={open} onOpenChange={setOpen} modal>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          disabled={disabled}
          className={cn(
            "h-11 w-full justify-between rounded-xl border-input/70 bg-muted/30 px-3.5 font-normal hover:bg-muted/50",
            selectedLabels.length === 0 && "text-muted-foreground",
            className
          )}
        >
          <span className="truncate">{summary}</span>
          <ChevronsUpDownIcon className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className="w-(--radix-popover-trigger-width) p-0"
        align={align}
        onOpenAutoFocus={preventAutoFocusOnTouch}
      >
        <CommandPrimitive className="flex h-full w-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground">
          <div className="flex items-center border-b px-3">
            <CommandPrimitive.Input
              placeholder={searchPlaceholder}
              className="flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"
            />
          </div>
          <CommandPrimitive.List className="max-h-[min(40vh,18rem)] overflow-x-hidden overflow-y-auto overscroll-contain">
            <CommandPrimitive.Empty className="py-6 text-center text-sm">
              {emptyText}
            </CommandPrimitive.Empty>
            {options.map((option) => {
              const selected = value.includes(option.value)
              return (
                <CommandPrimitive.Item
                  key={option.value}
                  value={option.label}
                  onSelect={() => toggle(option.value)}
                  className="relative flex cursor-default items-center gap-2 rounded-lg px-2 py-1.5 text-sm outline-none select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground"
                >
                  <span
                    className={cn(
                      "flex size-4 items-center justify-center rounded border",
                      selected
                        ? "border-primary bg-primary text-primary-foreground"
                        : "border-input"
                    )}
                  >
                    {selected && <CheckIcon className="size-3" />}
                  </span>
                  <span className="truncate">{option.label}</span>
                </CommandPrimitive.Item>
              )
            })}
          </CommandPrimitive.List>
        </CommandPrimitive>
      </PopoverContent>
    </Popover>
  )
}
