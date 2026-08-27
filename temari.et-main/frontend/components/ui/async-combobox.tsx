"use client"

import * as React from "react"
import { Command as CommandPrimitive } from "cmdk"
import { CheckIcon, ChevronsUpDownIcon, Loader2Icon, PlusIcon, SearchIcon } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"

export interface AsyncComboboxOption {
  value: string
  label: string
  /** Secondary line (city, masked phone…). */
  description?: string
  /** Trailing chip (e.g. "On Temari", children count). */
  badge?: string
  /** Caller-defined payload carried through to onChange (e.g. prefill data). */
  meta?: unknown
}

interface AsyncComboboxProps {
  value: AsyncComboboxOption | null
  onChange: (option: AsyncComboboxOption | null) => void
  /** Server search — called (debounced) with the typed query. Respect the signal. */
  fetcher: (query: string, signal: AbortSignal) => Promise<AsyncComboboxOption[]>
  /** When set, an "Add “query”" row appears when nothing matches exactly. */
  onCreate?: (label: string) => Promise<AsyncComboboxOption>
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  createText?: string
  /** Minimum characters before the fetcher fires (default 2). */
  minChars?: number
  disabled?: boolean
  className?: string
  /** Set by `<FormControl>` so `<FormLabel htmlFor>` names the trigger. */
  id?: string
  "aria-describedby"?: string
  "aria-invalid"?: boolean | "true" | "false"
  "aria-label"?: string
}

/**
 * Server-searchable combobox (debounced apiFetch, abort-on-retype) with an
 * optional inline-create row. Used for catalogs too large to preload: the
 * school directory, cross-school parent search. Client filtering is disabled
 * (shouldFilter=false) — the server is the source of truth.
 */
export function AsyncCombobox({
  value,
  onChange,
  fetcher,
  onCreate,
  placeholder = "Select...",
  searchPlaceholder = "Type to search...",
  emptyText = "No results found.",
  createText = "Add",
  minChars = 2,
  disabled,
  className,
  id,
  ...aria
}: AsyncComboboxProps) {
  const [open, setOpen] = React.useState(false)
  const [query, setQuery] = React.useState("")
  const [options, setOptions] = React.useState<AsyncComboboxOption[]>([])
  const [loading, setLoading] = React.useState(false)
  const [creating, setCreating] = React.useState(false)
  const [failed, setFailed] = React.useState(false)

  React.useEffect(() => {
    if (!open) return
    const trimmed = query.trim()
    const controller = new AbortController()

    // All state updates happen inside the timer callback (never synchronously
    // in the effect body) so typing can't trigger cascading renders.
    const timer = setTimeout(
      () => {
        if (controller.signal.aborted) return
        if (trimmed.length < minChars) {
          setOptions([])
          setLoading(false)
          return
        }

        setLoading(true)
        setFailed(false)
        fetcher(trimmed, controller.signal)
          .then((results) => {
            if (!controller.signal.aborted) setOptions(results)
          })
          .catch(() => {
            if (!controller.signal.aborted) setFailed(true)
          })
          .finally(() => {
            if (!controller.signal.aborted) setLoading(false)
          })
      },
      trimmed.length < minChars ? 0 : 300,
    )

    return () => {
      controller.abort()
      clearTimeout(timer)
    }
  }, [query, open, fetcher, minChars])

  const trimmed = query.trim()
  const exactMatch = options.some((o) => o.label.toLowerCase() === trimmed.toLowerCase())
  const showCreate = Boolean(onCreate) && trimmed.length >= minChars && !loading && !exactMatch

  const handleCreate = async () => {
    if (!onCreate || creating) return
    setCreating(true)
    try {
      const created = await onCreate(trimmed)
      onChange(created)
      setOpen(false)
      setQuery("")
    } finally {
      setCreating(false)
    }
  }

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
            "border-input/70 bg-muted/30 hover:bg-muted/50 h-11 w-full justify-between rounded-xl px-3.5 font-normal",
            !value && "text-muted-foreground",
            className,
          )}
          {...aria}
        >
          <span className="truncate">{value?.label ?? placeholder}</span>
          <ChevronsUpDownIcon className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
        <CommandPrimitive
          shouldFilter={false}
          className="flex h-full w-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground"
        >
          <div className="flex items-center gap-2 border-b px-3">
            <SearchIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
            <CommandPrimitive.Input
              value={query}
              onValueChange={setQuery}
              placeholder={searchPlaceholder}
              className="flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground"
            />
            {loading ? <Loader2Icon className="h-4 w-4 shrink-0 animate-spin opacity-50" /> : null}
          </div>
          <CommandPrimitive.List className="max-h-[240px] overflow-y-auto overflow-x-hidden p-1">
            {trimmed.length < minChars ? (
              <p className="py-6 text-center text-sm text-muted-foreground">{searchPlaceholder}</p>
            ) : failed ? (
              <p className="py-6 text-center text-sm text-destructive">{emptyText}</p>
            ) : !loading && options.length === 0 && !showCreate ? (
              <p className="py-6 text-center text-sm text-muted-foreground">{emptyText}</p>
            ) : null}
            {value ? (
              <CommandPrimitive.Item
                value="__clear__"
                onSelect={() => {
                  onChange(null)
                  setOpen(false)
                }}
                className="relative flex cursor-default select-none items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-muted-foreground outline-none data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground"
              >
                {placeholder}
              </CommandPrimitive.Item>
            ) : null}
            {options.map((option) => (
              <CommandPrimitive.Item
                key={option.value}
                value={option.value}
                onSelect={() => {
                  onChange(option)
                  setOpen(false)
                  setQuery("")
                }}
                className="relative flex cursor-default select-none items-center gap-2 rounded-lg px-2 py-1.5 text-sm outline-none data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground"
              >
                <CheckIcon
                  className={cn("h-4 w-4 shrink-0", value?.value === option.value ? "opacity-100" : "opacity-0")}
                />
                <span className="flex min-w-0 flex-1 flex-col">
                  <span className="truncate">{option.label}</span>
                  {option.description ? (
                    <span className="truncate text-xs text-muted-foreground">{option.description}</span>
                  ) : null}
                </span>
                {option.badge ? (
                  <span className="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-medium text-primary">
                    {option.badge}
                  </span>
                ) : null}
              </CommandPrimitive.Item>
            ))}
            {showCreate ? (
              <CommandPrimitive.Item
                value="__create__"
                onSelect={handleCreate}
                disabled={creating}
                className="relative flex cursor-default select-none items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-primary outline-none data-[selected=true]:bg-accent"
              >
                {creating ? (
                  <Loader2Icon className="h-4 w-4 shrink-0 animate-spin" />
                ) : (
                  <PlusIcon className="h-4 w-4 shrink-0" />
                )}
                {createText} &ldquo;{trimmed}&rdquo;
              </CommandPrimitive.Item>
            ) : null}
          </CommandPrimitive.List>
        </CommandPrimitive>
      </PopoverContent>
    </Popover>
  )
}
