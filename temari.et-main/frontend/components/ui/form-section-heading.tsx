"use client"

import type { ReactNode } from "react"

/** Section heading: icon tile + uppercase label, per the design system. */
export function FormSectionHeading({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <div className="flex items-center gap-2.5 pt-1">
      <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-muted-foreground [&_svg]:size-4">
        {icon}
      </div>
      <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">{children}</p>
    </div>
  )
}
