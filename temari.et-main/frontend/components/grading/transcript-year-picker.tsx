"use client"

import { CalendarRange, ChevronDown } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useTranslation } from "@/lib/i18n"

export interface TranscriptYearOption {
  id: number
  label: string
}

/**
 * "Years covered" picker for transcripts: every year we hold frozen results
 * for, all pre-checked (the DEFAULT transcript is the complete record).
 * Unchecking years produces a PARTIAL transcript — stamped as such on the
 * sheet and on the QR verify page, so a subset can never masquerade as the
 * full record. `value === null` means "all years".
 */
export function TranscriptYearPicker({
  options,
  value,
  onChange,
}: {
  options: TranscriptYearOption[]
  value: number[] | null
  onChange: (value: number[] | null) => void
}) {
  const { t } = useTranslation("grading")

  if (options.length <= 1) return null

  const selected = value ?? options.map((o) => o.id)
  const allSelected = value === null || selected.length === options.length

  function toggle(id: number) {
    const next = selected.includes(id)
      ? selected.filter((v) => v !== id)
      : [...selected, id]
    // Never allow an empty sheet; back to "all" when everything is on.
    if (next.length === 0) return
    onChange(next.length === options.length ? null : next)
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" size="sm">
          <CalendarRange className="size-4" />
          {allSelected
            ? t("transcript.fullRecord", { count: options.length })
            : t("transcript.partialBadge") + ` · ${selected.length}/${options.length}`}
          <ChevronDown className="size-3.5 opacity-60" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-64">
        <DropdownMenuLabel>{t("transcript.yearsCovered")}</DropdownMenuLabel>
        <DropdownMenuCheckboxItem
          checked={allSelected}
          onSelect={(e) => e.preventDefault()}
          onCheckedChange={() => onChange(null)}
        >
          {t("transcript.fullRecord", { count: options.length })}
        </DropdownMenuCheckboxItem>
        <DropdownMenuSeparator />
        {options.map((option) => (
          <DropdownMenuCheckboxItem
            key={option.id}
            checked={selected.includes(option.id)}
            onSelect={(e) => e.preventDefault()}
            onCheckedChange={() => toggle(option.id)}
          >
            {option.label}
          </DropdownMenuCheckboxItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
