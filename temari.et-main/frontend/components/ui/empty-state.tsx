"use client"

import type { LucideIcon } from "lucide-react"

import { cn } from "@/lib/utils"

/**
 * Composed empty state (DESIGN.md §6): an icon in a soft tile, a plain-language
 * headline, one sentence of guidance, and (when the user can act) exactly one
 * call to action. Use for first-run screens and zero-result lists — never leave
 * a bare "No records found".
 */
export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
  className,
  compact = false,
}: {
  icon?: LucideIcon
  title: string
  description?: string
  action?: React.ReactNode
  className?: string
  /** Tighter spacing for inline/table usage. */
  compact?: boolean
}) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center px-6 text-center",
        compact ? "py-10" : "py-16",
        className,
      )}
    >
      {Icon && (
        <div className="bg-accent text-accent-foreground ring-border/60 mb-4 flex size-13 items-center justify-center rounded-2xl ring-1">
          <Icon className="size-5.5" strokeWidth={1.75} />
        </div>
      )}
      <h3 className="font-display text-base font-semibold">{title}</h3>
      {description && (
        <p className="text-muted-foreground mt-1 max-w-sm text-sm leading-relaxed">{description}</p>
      )}
      {action && <div className="mt-4">{action}</div>}
    </div>
  )
}
