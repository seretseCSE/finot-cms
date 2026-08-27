"use client"

import type { LucideIcon } from "lucide-react"

import { Skeleton } from "@/components/ui/skeleton"
import { cn } from "@/lib/utils"

/**
 * Dashboard stat tile: value in tabular mono, quiet label, optional icon tile
 * and trend hint. `value === null` renders its own skeleton so pages don't
 * hand-roll loading states.
 */
export function StatCard({
  label,
  value,
  icon: Icon,
  hint,
  className,
  onClick,
}: {
  label: string
  /** `null` renders the loading skeleton; anything else renders as-is. */
  value: React.ReactNode
  icon?: LucideIcon
  hint?: string
  className?: string
  onClick?: () => void
}) {
  const Comp = onClick ? "button" : "div"

  return (
    <Comp
      onClick={onClick}
      className={cn(
        "flex items-start gap-3.5 rounded-2xl border bg-card p-4 text-left shadow-xs",
        onClick &&
          "pressable cursor-pointer transition-all hover:border-primary/30 hover:shadow-sm",
        className
      )}
    >
      {Icon && (
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
          <Icon className="size-[18px]" strokeWidth={1.75} />
        </span>
      )}
      <span className="min-w-0">
        {/* Wraps rather than truncates — a clipped label ("Collected this mon…")
            tells the reader nothing. */}
        <span className="line-clamp-2 block text-[13px] leading-tight text-muted-foreground">
          {label}
        </span>
        {value === null ? (
          <Skeleton className="mt-1.5 h-7 w-16" />
        ) : (
          <span className="mt-0.5 block font-display text-2xl font-semibold tracking-tight tabular-nums">
            {value}
          </span>
        )}
        {hint && (
          <span className="mt-0.5 block text-xs text-muted-foreground/80">
            {hint}
          </span>
        )}
      </span>
    </Comp>
  )
}
