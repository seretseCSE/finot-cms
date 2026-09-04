"use client"

import * as React from "react"

import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import type { Term } from "@/lib/types"

/**
 * The full, unambiguous label for a single term — "Semester 1 · 2018 E.C.".
 * Used for the collapsed trigger and anywhere a term is named outside a
 * year-grouped list (where the year already sits in the group header).
 */
export function termFullLabel(term: Term): string {
  return term.academic_year_name ? `${term.name} · ${term.academic_year_name}` : term.name
}

/** Leading numeric year in an academic-year name ("2018 E.C." → 2018), for ordering. */
function yearOrder(name: string | undefined): number {
  const match = name?.match(/\d+/)
  return match ? Number(match[0]) : -Infinity
}

export interface TermYearGroup {
  academicYearId: number
  academicYearName: string
  terms: Term[]
}

/**
 * Group a flat term list by academic year — newest year first, semesters in
 * sequence within each year. This is the ONE ordering used everywhere a term
 * dropdown is shown, so a raw `Semester 1 · 2018`, `Semester 2 · 2017`… jumble
 * never reaches the user.
 */
export function groupTermsByYear(terms: Term[]): TermYearGroup[] {
  const groups = new Map<number, TermYearGroup>()
  for (const term of terms) {
    let group = groups.get(term.academic_year_id)
    if (!group) {
      group = {
        academicYearId: term.academic_year_id,
        academicYearName: term.academic_year_name ?? "",
        terms: [],
      }
      groups.set(term.academic_year_id, group)
    }
    group.terms.push(term)
  }

  const list = [...groups.values()]
  for (const group of list) {
    group.terms.sort((a, b) => a.sequence - b.sequence)
  }
  list.sort(
    (a, b) =>
      yearOrder(b.academicYearName) - yearOrder(a.academicYearName) ||
      b.academicYearId - a.academicYearId,
  )
  return list
}

/**
 * The grouped `<SelectItem>`s for a term dropdown — each academic year renders
 * as a `<SelectGroup>` with the year as its header and its semesters listed
 * under it. Item text is the bare semester name (the year lives in the header);
 * the trigger shows the full label via {@link TermSelect}.
 *
 * Returns a flat ARRAY of groups (not a fragment) so it can be spread straight
 * into `<SelectContent>` and still leave the content's empty-state detection
 * working when `terms` is empty.
 */
export function renderTermGroups(terms: Term[]): React.ReactElement[] {
  return groupTermsByYear(terms).map((group) => (
    <SelectGroup key={group.academicYearId}>
      {group.academicYearName ? <SelectLabel>{group.academicYearName}</SelectLabel> : null}
      {group.terms.map((term) => (
        <SelectItem key={term.id} value={String(term.id)}>
          {term.name}
        </SelectItem>
      ))}
    </SelectGroup>
  ))
}

export interface TermSelectProps {
  terms: Term[]
  value: string
  onValueChange: (value: string) => void
  placeholder?: string
  /** Applied to the trigger. */
  className?: string
  size?: "sm" | "default"
  disabled?: boolean
  "aria-label"?: string
  emptyNotice?: React.ReactNode
  /** Optional non-term items rendered above the groups (e.g. an "All terms" row). */
  leadingItems?: { value: string; label: string }[]
}

/**
 * A term / semester picker that presents its options grouped under academic
 * year headers instead of a flat, order-confusing list. The collapsed trigger
 * still shows the full "Semester 1 · 2018 E.C." so a glance names both.
 */
export function TermSelect({
  terms,
  value,
  onValueChange,
  placeholder,
  className,
  size,
  disabled,
  emptyNotice,
  leadingItems,
  "aria-label": ariaLabel,
}: TermSelectProps) {
  const selectedTerm = terms.find((term) => String(term.id) === value)
  const selectedLeading = leadingItems?.find((item) => item.value === value)
  const displayLabel = selectedTerm ? termFullLabel(selectedTerm) : selectedLeading?.label

  return (
    <Select value={value} onValueChange={onValueChange} disabled={disabled}>
      <SelectTrigger className={className} size={size} aria-label={ariaLabel}>
        {displayLabel ? (
          <span data-slot="select-value" className="line-clamp-1">
            {displayLabel}
          </span>
        ) : (
          <SelectValue placeholder={placeholder} />
        )}
      </SelectTrigger>
      <SelectContent emptyNotice={emptyNotice}>
        {leadingItems?.map((item) => (
          <SelectItem key={item.value} value={item.value}>
            {item.label}
          </SelectItem>
        ))}
        {renderTermGroups(terms)}
      </SelectContent>
    </Select>
  )
}
