"use client"

import * as React from "react"
import { Label as LabelPrimitive } from "radix-ui"

import { cn } from "@/lib/utils"

function Label({
  className,
  ...props
}: React.ComponentProps<typeof LabelPrimitive.Root>) {
  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        "flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
      )}
      {...props}
    />
  )
}

/**
 * Mobile-only caption above a field in a compact editor row whose column
 * headers exist only at sm+ (weights, scores, band limits…). On desktop the
 * header row labels the column; below sm every field must name itself —
 * two bare "10" inputs side by side are indistinguishable.
 */
const MOBILE_FIELD_LABEL =
  "text-[10px] font-semibold uppercase tracking-wide text-muted-foreground sm:hidden"

export { Label, MOBILE_FIELD_LABEL }
