import { cn } from "@/lib/utils"

/**
 * Layered hero texture: blueprint grid fading radially + two soft brand glows
 * + an optional giant Ge'ez watermark. Pure CSS, zero JS, sits behind content
 * (parent must be `relative overflow-hidden`).
 */
export function HeroBackdrop({
  watermark,
  className,
}: {
  /** A single Ge'ez character rendered as a faint oversized watermark. */
  watermark?: string
  className?: string
}) {
  return (
    <div
      aria-hidden
      className={cn("pointer-events-none absolute inset-0", className)}
    >
      <div className="mk-grid absolute inset-0" />
      <div className="absolute -top-48 right-[-12%] size-[36rem] rounded-full bg-primary/10 blur-3xl" />
      <div className="absolute top-64 left-[-14%] size-[28rem] rounded-full bg-success/10 blur-3xl" />
      {watermark && (
        <span className="absolute -top-24 right-[-4rem] hidden font-ethiopic text-[26rem] leading-none font-bold text-primary/6 select-none xl:block">
          {watermark}
        </span>
      )}
    </div>
  )
}
