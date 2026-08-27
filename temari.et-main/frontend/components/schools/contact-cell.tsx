"use client"

import type { Contact } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * Compact table rendering of a single contact (principal / IT admin / director):
 * an optional role label above the person's name, or a muted dash when unset.
 */
export function ContactCell({
  contact,
  label,
}: {
  contact?: Contact | null
  label?: string
}) {
  if (!contact?.name) {
    return <span className="text-sm text-muted-foreground">—</span>
  }

  return (
    <div className="leading-tight">
      {label && (
        <span className="block text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
          {label}
        </span>
      )}
      <span className={cn("text-sm", !contact.is_active && "text-muted-foreground line-through")}>
        {contact.name}
      </span>
    </div>
  )
}

/** Flattened contact for CSV/Excel export. */
export function contactExport(contact?: Contact | null): string {
  if (!contact?.name) return ""
  return contact.phone ? `${contact.name} (${contact.phone})` : contact.name
}
