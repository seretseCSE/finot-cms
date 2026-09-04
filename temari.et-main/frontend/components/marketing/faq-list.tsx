import { ChevronDown } from "lucide-react"

import type { FaqItem } from "@/lib/marketing/content/types"

/**
 * Zero-JS accordion built on native <details>, so answers are in the HTML for
 * crawlers and work without JavaScript.
 */
export function FaqList({ items }: { items: FaqItem[] }) {
  return (
    <div className="divide-y overflow-hidden rounded-2xl border bg-card shadow-xs">
      {items.map((item) => (
        <details key={item.q} className="group">
          <summary className="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-left text-[15px] font-medium transition-colors select-none hover:bg-muted/50 [&::-webkit-details-marker]:hidden">
            {item.q}
            <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180" />
          </summary>
          <p className="px-5 pb-5 text-sm leading-relaxed text-muted-foreground">
            {item.a}
          </p>
        </details>
      ))}
    </div>
  )
}
