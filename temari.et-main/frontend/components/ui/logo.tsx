import { cn } from "@/lib/utils"

/**
 * The Temari brand mark: the sprouting seedling (the "growing student") in
 * off-white on the green brand tile — the same artwork as the app icon /
 * favicon (app/icon0.svg). One mark, four sizes; never rebuild it ad-hoc.
 */
export function LogoMark({
  size = "md",
  className,
}: {
  size?: "sm" | "md" | "lg" | "xl"
  className?: string
}) {
  const sizes = {
    sm: "size-7 rounded-lg",
    md: "size-9 rounded-xl",
    lg: "size-12 rounded-2xl",
    xl: "size-20 rounded-3xl",
  } as const

  return (
    <span
      aria-hidden
      className={cn(
        "brand-tile flex shrink-0 select-none items-center justify-center overflow-hidden",
        sizes[size],
        className,
      )}
    >
      {/* Matches app/icon0.svg exactly (off-white sprout, #FCFBF8). */}
      <svg viewBox="0 0 512 512" fill="none" className="size-full" aria-hidden>
        <path
          d="M270 424 C270 350 278 306 260 248"
          fill="none"
          stroke="#FCFBF8"
          strokeWidth={62}
          strokeLinecap="round"
        />
        <path
          d="M262 258 C198 258 124 226 88 126 C182 106 250 158 262 258 Z"
          fill="#FCFBF8"
        />
        <path
          d="M268 250 C318 248 366 222 390 148 C322 136 280 178 268 250 Z"
          fill="#FCFBF8"
        />
      </svg>
    </span>
  )
}

/** Mark + wordmark lockup for headers and auth screens. */
export function Logo({
  size = "md",
  className,
  tagline,
}: {
  size?: "sm" | "md" | "lg"
  className?: string
  /** Optional line under the wordmark (e.g. "School platform"). */
  tagline?: string
}) {
  return (
    <span className={cn("flex items-center gap-2.5", className)}>
      <LogoMark size={size} />
      <span className="min-w-0 leading-none">
        <span
          className={cn(
            "font-display block font-bold tracking-tight",
            size === "sm" ? "text-base" : size === "lg" ? "text-2xl" : "text-lg",
          )}
        >
          Temari
          <span className="text-primary">.et</span>
        </span>
        {tagline && (
          <span className="text-muted-foreground mt-1 block truncate text-[11px] font-normal">
            {tagline}
          </span>
        )}
      </span>
    </span>
  )
}
