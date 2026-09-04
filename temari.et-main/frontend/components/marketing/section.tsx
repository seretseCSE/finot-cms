import { cn } from "@/lib/utils"

/** Standard marketing section: consistent vertical rhythm + gutter. */
export function Section({
  children,
  className,
  id,
}: {
  children: React.ReactNode
  className?: string
  id?: string
}) {
  return (
    <section id={id} className={cn("px-4 py-16 md:px-8 md:py-24", className)}>
      <div className="mx-auto w-full max-w-6xl">{children}</div>
    </section>
  )
}

/** Section header: headline + optional sub, stacked (never split left/right). */
export function SectionHeader({
  headline,
  sub,
  center = false,
  className,
}: {
  headline: string
  sub?: string
  center?: boolean
  className?: string
}) {
  return (
    <div className={cn("mb-10 md:mb-14", center && "text-center", className)}>
      <h2 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
        {headline}
      </h2>
      {sub && (
        <p
          className={cn(
            "mt-4 max-w-2xl text-base leading-relaxed text-muted-foreground md:text-lg",
            center && "mx-auto"
          )}
        >
          {sub}
        </p>
      )}
    </div>
  )
}
