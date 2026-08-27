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

function Command({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive>) {
  return (
    <CommandPrimitive
      className={cn(
        "flex h-full w-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground",
        className
      )}
      {...props}
    />
  )
}

function CommandInput({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Input>) {
  return (
    <div className="flex items-center border-b px-3">
      <CommandPrimitive.Input
        className={cn(
          "flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50",
          className
        )}
        {...props}
      />
    </div>
  )
}

function CommandList({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.List>) {
  return (
    <CommandPrimitive.List
      className={cn(
        "max-h-[min(40vh,18rem)] overflow-x-hidden overflow-y-auto overscroll-contain",
        className
      )}
      {...props}
    />
  )
}

function CommandEmpty({
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Empty>) {
  return (
    <CommandPrimitive.Empty className="py-6 text-center text-sm" {...props} />
  )
}

function CommandItem({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Item>) {
  return (
    <CommandPrimitive.Item
      className={cn(
        "relative flex cursor-default items-center gap-2 rounded-lg px-2 py-1.5 text-sm outline-none select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
        className
      )}
      {...props}
    />
  )
}

export interface ComboboxProps {
  options: readonly string[]
  value?: string
  onChange?: (value: string) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  disabled?: boolean
  className?: string
  /** Set by `<FormControl>` so `<FormLabel htmlFor>` names the trigger. */
  id?: string
  "aria-describedby"?: string
  "aria-invalid"?: boolean | "true" | "false"
  "aria-label"?: string
}

export function Combobox({
  options,
  value,
  onChange,
  placeholder = "Select...",
  searchPlaceholder = "Search...",
  emptyText = "No results found.",
  disabled,
  className,
  id,
  ...aria
}: ComboboxProps) {
  const [open, setOpen] = React.useState(false)

  return (
    // modal: registers its own scroll-lock shard so the list stays wheel-
    // scrollable when the combobox lives inside a Sheet/Dialog (whose
    // scroll lock would otherwise swallow wheel events over the portal).
    <Popover open={open} onOpenChange={setOpen} modal>
      <PopoverTrigger asChild>
        <Button
          id={id}
          variant="outline"
          role="combobox"
          aria-expanded={open}
          disabled={disabled}
          className={cn(
            // Shaped like an Input (form control), not a pill button
            "h-11 w-full justify-between rounded-xl border-input/70 bg-muted/30 px-3.5 font-normal hover:bg-muted/50",
            !value && "text-muted-foreground",
            className
          )}
          {...aria}
        >
          <span className="truncate">{value || placeholder}</span>
          <ChevronsUpDownIcon className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className="w-(--radix-popover-trigger-width) p-0"
        align="start"
        onOpenAutoFocus={preventAutoFocusOnTouch}
      >
        <Command>
          <CommandInput placeholder={searchPlaceholder} />
          <CommandList>
            <CommandEmpty>{emptyText}</CommandEmpty>
            {options.map((option) => (
              <CommandItem
                key={option}
                value={option}
                onSelect={(current) => {
                  onChange?.(current === value ? "" : current)
                  setOpen(false)
                }}
              >
                <CheckIcon
                  className={cn(
                    "mr-2 h-4 w-4",
                    value === option ? "opacity-100" : "opacity-0"
                  )}
                />
                {option}
              </CommandItem>
            ))}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

export interface ComboboxOption {
  value: string
  label: string
  /** Optional leading visual (logo, icon) rendered before the label. */
  leading?: React.ReactNode
  /** When set, options with the same group render under a sticky header. */
  group?: string
}

interface OptionComboboxProps {
  options: readonly ComboboxOption[]
  value?: string
  onChange?: (value: string) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  disabled?: boolean
  className?: string
  /** Popover width; defaults to the trigger width. */
  contentClassName?: string
  align?: "start" | "center" | "end"
  /** Set by `<FormControl>` so `<FormLabel htmlFor>` names the trigger. */
  id?: string
  "aria-describedby"?: string
  "aria-invalid"?: boolean | "true" | "false"
  "aria-label"?: string
}

/**
 * Searchable select over {value, label} options — for long catalogs (banks,
 * job_titles…) where a plain Select is unusable. Filters by label; stores
 * the machine value.
 */
export function OptionCombobox({
  options,
  value,
  onChange,
  placeholder = "Select...",
  searchPlaceholder = "Search...",
  emptyText = "No results found.",
  disabled,
  className,
  contentClassName,
  align = "start",
  id,
  ...aria
}: OptionComboboxProps) {
  const [open, setOpen] = React.useState(false)
  const selected = options.find((option) => option.value === value)
  // See Combobox: modal keeps the list scrollable inside sheets/dialogs.

  // Preserve order; emit a header whenever the group label changes.
  const grouped = React.useMemo(() => {
    const rows: Array<
      | { type: "group"; label: string }
      | { type: "option"; option: ComboboxOption }
    > = []
    let lastGroup: string | undefined
    for (const option of options) {
      if (option.group && option.group !== lastGroup) {
        rows.push({ type: "group", label: option.group })
        lastGroup = option.group
      }
      rows.push({ type: "option", option })
    }
    return rows
  }, [options])

  return (
    <Popover open={open} onOpenChange={setOpen} modal>
      <PopoverTrigger asChild>
        <Button
          id={id}
          variant="outline"
          role="combobox"
          aria-expanded={open}
          disabled={disabled}
          className={cn(
            "h-11 w-full justify-between rounded-xl border-input/70 bg-muted/30 px-3.5 font-normal hover:bg-muted/50",
            !selected && "text-muted-foreground",
            className
          )}
          {...aria}
        >
          <span className="flex min-w-0 items-center gap-2">
            {selected?.leading}
            <span className="truncate">{selected?.label ?? placeholder}</span>
          </span>
          <ChevronsUpDownIcon className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className={cn(
          "w-(--radix-popover-trigger-width) p-0",
          contentClassName
        )}
        align={align}
        onOpenAutoFocus={preventAutoFocusOnTouch}
      >
        <Command>
          <CommandInput placeholder={searchPlaceholder} />
          <CommandList>
            <CommandEmpty>{emptyText}</CommandEmpty>
            {grouped.map((row) =>
              row.type === "group" ? (
                <div
                  key={`group:${row.label}`}
                  className="px-2 py-1.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                >
                  {row.label}
                </div>
              ) : (
                <CommandItem
                  key={row.option.value}
                  value={`${row.option.label} ${row.option.group ?? ""}`}
                  onSelect={() => {
                    onChange?.(
                      row.option.value === value ? "" : row.option.value
                    )
                    setOpen(false)
                  }}
                >
                  <CheckIcon
                    className={cn(
                      "mr-2 h-4 w-4",
                      value === row.option.value ? "opacity-100" : "opacity-0"
                    )}
                  />
                  {row.option.leading}
                  <span className="truncate">{row.option.label}</span>
                </CommandItem>
              )
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
