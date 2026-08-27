"use client"

import { cn } from "@/lib/utils"

function initials(name: string): string {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("")
}

/**
 * Photo-or-initials avatar for people (students, parents, users) in tables,
 * cards and switchers. Photo URLs are short-lived signed R2 links.
 */
export function PersonAvatar({
  name,
  photoUrl,
  className,
}: {
  name: string
  photoUrl?: string | null
  className?: string
}) {
  return (
    <span
      className={cn(
        "flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10 text-[11px] font-semibold text-primary",
        className,
      )}
    >
      {photoUrl ? (
        // eslint-disable-next-line @next/next/no-img-element -- signed URL
        <img src={photoUrl} alt="" className="size-full object-cover" />
      ) : (
        initials(name) || "?"
      )}
    </span>
  )
}
