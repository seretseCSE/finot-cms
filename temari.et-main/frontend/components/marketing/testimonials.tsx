import { Quote } from "lucide-react"

import { Reveal } from "@/components/marketing/reveal"
import { Section, SectionHeader } from "@/components/marketing/section"
import type { MarketingDict } from "@/lib/marketing/content"
import { cn } from "@/lib/utils"

const AVATAR_TONES = [
  "bg-white/15 text-white",
  "bg-primary/12 text-primary",
  "bg-accent text-accent-foreground",
] as const

/**
 * PLACEHOLDER testimonials (copy lives in the marketing dicts) — Abdul will
 * replace quotes and names with real ones. First card sits on the brand
 * surface for an asymmetric 1-big + 2-stacked composition.
 */
export function Testimonials({ dict }: { dict: MarketingDict }) {
  const t = dict.home.testimonials

  return (
    <Section>
      <Reveal>
        <SectionHeader headline={t.headline} sub={t.sub} center />
      </Reveal>
      <div className="grid gap-4 lg:grid-cols-[1.15fr_1fr]">
        <Reveal>
          <TestimonialCard item={t.items[0]} tone={0} featured />
        </Reveal>
        <div className="grid gap-4">
          {t.items.slice(1).map((item, i) => (
            <Reveal key={item.name} delay={(i + 1) * 80}>
              <TestimonialCard item={item} tone={i + 1} />
            </Reveal>
          ))}
        </div>
      </div>
    </Section>
  )
}

function TestimonialCard({
  item,
  tone,
  featured = false,
}: {
  item: { quote: string; name: string; role: string }
  tone: number
  featured?: boolean
}) {
  const initials = item.name
    .split(" ")
    .map((part) => part[0])
    .slice(0, 2)
    .join("")

  return (
    <figure
      className={cn(
        "flex h-full flex-col rounded-3xl p-8 md:p-10",
        featured
          ? "brand-hero relative overflow-hidden text-white"
          : "border bg-card shadow-xs"
      )}
    >
      {featured && (
        <div
          aria-hidden
          className="mk-grid-light pointer-events-none absolute inset-0"
        />
      )}
      <Quote
        className={cn("size-7", featured ? "text-emerald-300" : "text-primary")}
        strokeWidth={1.75}
        aria-hidden
      />
      <blockquote
        className={cn(
          "relative mt-5 flex-1 leading-relaxed text-balance",
          featured ? "font-display text-xl md:text-2xl" : "text-[15px]",
          !featured && "text-foreground/85"
        )}
      >
        {item.quote}
      </blockquote>
      <figcaption className="relative mt-7 flex items-center gap-3">
        <span
          className={cn(
            "flex size-10 items-center justify-center rounded-full font-display text-sm font-bold",
            AVATAR_TONES[tone % AVATAR_TONES.length]
          )}
        >
          {initials}
        </span>
        <span className="min-w-0">
          <span className="block text-sm font-semibold">{item.name}</span>
          <span
            className={cn(
              "block text-xs",
              featured ? "text-white/65" : "text-muted-foreground"
            )}
          >
            {item.role}
          </span>
        </span>
      </figcaption>
    </figure>
  )
}
