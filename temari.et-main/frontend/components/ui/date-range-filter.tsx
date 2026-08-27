"use client"

import { CalendarRange } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { useTranslation } from "@/lib/i18n"

export interface DateRangeField {
  /** Query param key sent to the server (e.g. `created_from`). */
  key: string
  label: string
}

/**
 * Toolbar popover with one date input per field — the standard date-range
 * control for server-mode DataTables. Pair it with `useServerTable`'s
 * `dates` / `setDate` / `clearDates` state and pass it via `toolbarSlot`.
 */
export function DateRangeFilter({
  fields,
  values,
  onChange,
  onClear,
}: {
  fields: DateRangeField[]
  values: Record<string, string>
  onChange: (key: string, value: string) => void
  onClear: () => void
}) {
  const { t } = useTranslation("common")
  const activeCount = fields.filter((f) => values[f.key]).length

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className={`h-9 gap-1.5 rounded-full ${activeCount > 0 ? "border-primary/40 bg-primary/10" : ""}`}
        >
          <CalendarRange className="size-3.5" />
          {t("filters.dates")}
          {activeCount > 0 && (
            <Badge
              variant="secondary"
              className="px-1.5 py-0 text-xs font-normal"
            >
              {activeCount}
            </Badge>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-72 space-y-3">
        {fields.map(({ key, label }) => (
          <div key={key} className="space-y-1">
            <label className="text-xs text-muted-foreground">{label}</label>
            <DatePicker
              value={values[key] ?? ""}
              onChange={(value) => onChange(key, value)}
              className="h-9 rounded-lg"
            />
          </div>
        ))}
        {activeCount > 0 && (
          <Button
            variant="ghost"
            size="sm"
            className="h-7 w-full text-xs"
            onClick={onClear}
          >
            {t("filters.clear")}
          </Button>
        )}
      </PopoverContent>
    </Popover>
  )
}
