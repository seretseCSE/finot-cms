import { cn } from "@/lib/utils"

/**
 * PLACEHOLDER school roster — Abdul will swap in real customer schools.
 * Invented names get generated monogram marks (never bare text wordmarks).
 */
const PLACEHOLDER_SCHOOLS: { name: string; initials: string }[] = [
  { name: "Hiwot Academy", initials: "HA" },
  { name: "Entoto View School", initials: "EV" },
  { name: "Bole Unity Academy", initials: "BU" },
  { name: "Abay Minch School", initials: "AM" },
  { name: "Hawassa Lakeview Academy", initials: "HL" },
  { name: "Bahir Dar Vision School", initials: "BV" },
  { name: "Adama Excellence School", initials: "AE" },
  { name: "Gondar Fasil Academy", initials: "GF" },
  { name: "Mekelle Bright Future", initials: "MB" },
  { name: "Jimma Green Valley School", initials: "JG" },
]

const MARK_TONES = [
  "bg-primary/12 text-primary",
  "bg-brand-ink text-brand-ink-foreground",
  "bg-accent text-accent-foreground",
] as const

function SchoolChip({
  school,
  tone,
  hidden,
}: {
  school: (typeof PLACEHOLDER_SCHOOLS)[number]
  tone: number
  hidden?: boolean
}) {
  return (
    <li
      aria-hidden={hidden || undefined}
      className="flex shrink-0 items-center gap-2.5 pr-12"
    >
      <span
        className={cn(
          "flex size-8 items-center justify-center rounded-lg font-display text-[11px] font-bold",
          MARK_TONES[tone % MARK_TONES.length]
        )}
      >
        {school.initials}
      </span>
      <span className="text-sm font-medium whitespace-nowrap text-muted-foreground">
        {school.name}
      </span>
    </li>
  )
}

/** Edge-faded, hover-pausable logo marquee (the page's single marquee). */
export function SchoolMarquee({ title }: { title: string }) {
  return (
    <div className="py-2">
      <p className="mb-7 text-center text-sm font-medium text-muted-foreground">
        {title}
      </p>
      <div
        className="overflow-hidden"
        style={{
          maskImage:
            "linear-gradient(to right, transparent, black 12%, black 88%, transparent)",
          WebkitMaskImage:
            "linear-gradient(to right, transparent, black 12%, black 88%, transparent)",
        }}
      >
        <ul className="mk-marquee flex w-max items-center">
          {[0, 1].flatMap((copy) =>
            PLACEHOLDER_SCHOOLS.map((school, i) => (
              <SchoolChip
                key={`${copy}-${school.name}`}
                school={school}
                tone={i}
                hidden={copy === 1}
              />
            ))
          )}
        </ul>
      </div>
    </div>
  )
}
