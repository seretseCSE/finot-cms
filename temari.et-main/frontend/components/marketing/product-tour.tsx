import { Reveal } from "@/components/marketing/reveal"
import { Section, SectionHeader } from "@/components/marketing/section"
import type { MarketingDict } from "@/lib/marketing/content"
import { cn } from "@/lib/utils"

/**
 * Real product screenshots in a light browser frame. Order matches
 * dict.home.tour.items 1:1 — change both together.
 */
const TOUR_SHOTS = [
  { src: "/images/screenshots/dashboard.jpg", url: "temari.et/dashboard" },
  { src: "/images/screenshots/students.jpg", url: "temari.et/students" },
  { src: "/images/screenshots/timetable.jpg", url: "temari.et/timetable" },
  { src: "/images/screenshots/invoices.jpg", url: "temari.et/invoices" },
  { src: "/images/screenshots/lesson-plans.jpg", url: "temari.et/lesson-plans" },
]

export function ProductTour({ dict }: { dict: MarketingDict }) {
  const tour = dict.home.tour

  return (
    <Section className="overflow-hidden bg-muted/40">
      <Reveal>
        <SectionHeader headline={tour.headline} sub={tour.sub} center />
      </Reveal>
      <div className="grid gap-6 md:grid-cols-2">
        {TOUR_SHOTS.map((shot, i) => {
          const item = tour.items[i]
          if (!item) return null
          // The lead screenshot spans the full row; the rest pair up.
          const wide = i === 0
          return (
            <Reveal
              key={shot.src}
              delay={(i % 2) * 60}
              className={cn(wide && "md:col-span-2")}
            >
              <figure className="group h-full">
                <div className="overflow-hidden rounded-2xl border bg-card shadow-sm transition-shadow duration-300 group-hover:shadow-lg">
                  {/* Browser chrome */}
                  <div className="flex items-center gap-2 border-b bg-muted/60 px-4 py-2.5">
                    <span aria-hidden className="flex gap-1.5">
                      <i className="size-2.5 rounded-full bg-border" />
                      <i className="size-2.5 rounded-full bg-border" />
                      <i className="size-2.5 rounded-full bg-border" />
                    </span>
                    <span className="mx-auto rounded-full bg-background px-3 py-0.5 font-mono text-[10px] text-muted-foreground">
                      {shot.url}
                    </span>
                  </div>
                  <img
                    src={shot.src}
                    alt={item.title}
                    loading="lazy"
                    decoding="async"
                    className="w-full"
                    width={1280}
                    height={800}
                  />
                </div>
                <figcaption className="mt-4 px-1">
                  <p className="font-semibold">{item.title}</p>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                    {item.body}
                  </p>
                </figcaption>
              </figure>
            </Reveal>
          )
        })}
      </div>
    </Section>
  )
}
