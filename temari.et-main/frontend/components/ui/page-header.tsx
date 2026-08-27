"use client"

import { ArrowLeft } from "lucide-react"
import Link from "next/link"

import { cn } from "@/lib/utils"

/**
 * THE standard page anatomy (DESIGN.md §5). Every app page starts with this —
 * never a hand-rolled h1 block — so titles, descriptions, back links and
 * primary actions sit in the same place on every screen.
 *
 * ```tsx
 * <PageHeader title={t("title")} description={t("subtitle")}
 *   actions={<Button onClick={openCreate}>{t("create")}</Button>} />
 * ```
 */
export function PageHeader({
  title,
  description,
  actions,
  backHref,
  backLabel,
  className,
  children,
}: {
  title: React.ReactNode
  description?: React.ReactNode
  /** Primary + secondary actions, right-aligned (stacked below on mobile). */
  actions?: React.ReactNode
  /** Renders a back link above the title — every drill-in page needs a way back. */
  backHref?: string
  backLabel?: string
  className?: string
  /** Optional extra row (stat strip, tabs) rendered under the header. */
  children?: React.ReactNode
}) {
  return (
    <header className={cn("page-gutter", className)}>
      {backHref && (
        <Link
          href={backHref}
          className="text-muted-foreground hover:text-foreground mb-2 inline-flex h-8 items-center gap-1.5 text-sm transition-colors"
        >
          <ArrowLeft className="size-3.5" />
          {backLabel ?? "Back"}
        </Link>
      )}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
        <div className="min-w-0">
          <h1 className="font-display text-2xl font-semibold tracking-tight md:text-[1.75rem]">
            {title}
          </h1>
          {description && (
            <p className="text-muted-foreground mt-1 max-w-2xl text-sm leading-relaxed">
              {description}
            </p>
          )}
        </div>
        {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
      </div>
      {children}
    </header>
  )
}
