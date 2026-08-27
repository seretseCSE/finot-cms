"use client"

import { ContactActionCell } from "@/components/ui/contact-action-cell"
import type { Contact } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * Director cell for the branches table: name + phone, with tap-to-act
 * Call / Copy via the shared contact popover.
 */
export function DirectorContactCell({ contact }: { contact?: Contact | null }) {
  if (!contact?.name) {
    return <span className="text-sm text-muted-foreground">—</span>
  }

  const body = (
    <div className="leading-tight text-left">
      <span
        className={cn(
          "block text-sm font-medium",
          !contact.is_active && "text-muted-foreground line-through",
        )}
      >
        {contact.name}
      </span>
      {contact.phone && (
        <span className="block font-mono text-xs text-muted-foreground tabular-nums">
          {contact.phone}
        </span>
      )}
    </div>
  )

  if (!contact.phone) return body

  return (
    <ContactActionCell value={contact.phone} name={contact.name}>
      {body}
    </ContactActionCell>
  )
}
