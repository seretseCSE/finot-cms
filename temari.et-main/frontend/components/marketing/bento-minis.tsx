import { Check, ChevronRight, X } from "lucide-react"

import { cn } from "@/lib/utils"

/**
 * Tiny product-shaped vignettes for the home feature bento — real component
 * previews built from design tokens (sample data), never screenshot images.
 */

export function MiniStudent() {
  return (
    <div className="mt-6 flex items-center gap-3 rounded-xl border bg-card p-3 shadow-2xs">
      <span className="brand-tile flex size-9 items-center justify-center rounded-lg font-ethiopic text-sm font-bold text-white">
        ብ
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate text-[13px] font-semibold">
          <span className="font-ethiopic">ብሩክ ሰለሞን</span>
        </p>
        <p className="text-[11px] text-muted-foreground">Grade 7B · active</p>
      </div>
      <span className="rounded-full bg-muted px-2 py-0.5 font-mono text-[10px] text-muted-foreground tabular-nums">
        TMR-24107
      </span>
      <ChevronRight className="size-3.5 text-muted-foreground" />
    </div>
  )
}

const WEEK = [
  { d: "M", ok: true },
  { d: "T", ok: true },
  { d: "W", ok: false },
  { d: "T", ok: true },
  { d: "F", ok: true },
]

export function MiniWeek() {
  return (
    <div className="mt-6 flex items-center gap-1.5">
      {WEEK.map((day, i) => (
        <span
          key={i}
          className={cn(
            "flex h-9 flex-1 flex-col items-center justify-center gap-0.5 rounded-lg text-[10px] font-medium",
            day.ok
              ? "bg-success/10 text-success"
              : "bg-destructive/10 text-destructive"
          )}
        >
          {day.d}
          {day.ok ? <Check className="size-3" /> : <X className="size-3" />}
        </span>
      ))}
    </div>
  )
}

export function MiniReceipt() {
  return (
    <div className="mt-6 flex items-center gap-3 rounded-xl border border-dashed bg-card p-3">
      <span className="grid size-8 shrink-0 grid-cols-2 gap-0.5" aria-hidden>
        <span className="rounded-[3px] bg-foreground/80" />
        <span className="rounded-[3px] bg-foreground/80" />
        <span className="rounded-[3px] bg-foreground/80" />
        <span className="rounded-[3px] bg-primary" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate font-mono text-[11px] font-semibold tabular-nums">
          ETB 1,850.00
        </p>
        <p className="truncate text-[10px] text-muted-foreground">
          Telebirr · verified
        </p>
      </div>
      <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
        <Check className="size-3.5" />
      </span>
    </div>
  )
}

const GRADES = [
  { label: "A", tone: "bg-success/10 text-success" },
  { label: "A-", tone: "bg-success/10 text-success" },
  { label: "B+", tone: "bg-info/10 text-info" },
]

export function MiniGrades() {
  return (
    <div className="mt-6 flex items-center gap-1.5">
      {GRADES.map((grade) => (
        <span
          key={grade.label}
          className={cn(
            "flex size-9 items-center justify-center rounded-lg font-display text-sm font-bold",
            grade.tone
          )}
        >
          {grade.label}
        </span>
      ))}
      <span className="ml-auto rounded-full bg-muted px-2.5 py-1 text-[10px] font-semibold text-muted-foreground">
        Rank 4 / 46
      </span>
    </div>
  )
}

/* 5 days × 3 periods; a couple of primary blocks read as "today's subjects" */
const SLOTS = [0, 1, 0, 0, 2, 1, 0, 2, 0, 1, 0, 0, 1, 0, 2]

export function MiniTimetable() {
  return (
    <div className="mt-6 grid grid-cols-5 gap-1" aria-hidden>
      {SLOTS.map((tone, i) => (
        <span
          key={i}
          className={cn(
            "h-5 rounded-md",
            tone === 0 && "bg-muted",
            tone === 1 && "bg-primary/25",
            tone === 2 && "bg-info/20"
          )}
        />
      ))}
    </div>
  )
}

export function MiniChat() {
  return (
    <div className="mt-6 space-y-1.5">
      <p className="w-fit max-w-[85%] rounded-xl rounded-bl-sm bg-muted px-3 py-1.5 text-[11px] text-foreground/80">
        <span className="font-ethiopic">የነገው የወላጆች ስብሰባ 8:00 ላይ ይጀምራል።</span>
      </p>
      <p className="ml-auto w-fit max-w-[85%] rounded-xl rounded-br-sm bg-primary px-3 py-1.5 text-[11px] text-primary-foreground">
        <span className="font-ethiopic">እናመሰግናለን፣ እንገኛለን።</span>
      </p>
    </div>
  )
}

export function MiniCard() {
  return (
    <div className="mt-6 flex items-center gap-3 rounded-xl border bg-card p-3 shadow-2xs">
      <span className="brand-tile flex h-9 w-13 shrink-0 flex-col justify-between rounded-md p-1.5">
        <span className="block h-1 w-6 rounded-full bg-white/70" />
        <span className="block h-1 w-4 rounded-full bg-white/40" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate text-[11px] font-semibold">Gate · tapped in</p>
        <p className="font-mono text-[10px] text-muted-foreground tabular-nums">
          07:52 · TMR-24107
        </p>
      </div>
      <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-success/10 text-success">
        <Check className="size-3.5" />
      </span>
    </div>
  )
}

export function MiniCourse() {
  return (
    <div className="mt-6 space-y-2">
      <div className="flex items-center justify-between text-[10px] font-medium text-muted-foreground">
        <span>Unit 4 · Fractions</span>
        <span className="tabular-nums">7/9 lessons</span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted" aria-hidden>
        <span className="block h-full w-[78%] rounded-full bg-primary" />
      </div>
    </div>
  )
}

export function MiniStock() {
  return (
    <div className="mt-6 flex items-center gap-3 rounded-xl border bg-card p-3 shadow-2xs">
      <div className="min-w-0 flex-1">
        <p className="truncate text-[11px] font-semibold">Exercise books</p>
        <p className="font-mono text-[10px] text-muted-foreground tabular-nums">
          −120 issued · 7B
        </p>
      </div>
      <span className="rounded-full bg-muted px-2 py-0.5 font-mono text-[10px] text-muted-foreground tabular-nums">
        bal 1,430
      </span>
    </div>
  )
}

export function MiniPayslip() {
  // Two stacked rows (label/badge, note/amount) so the vignette never
  // wraps awkwardly inside a narrow single-column bento card.
  return (
    <div className="mt-6 rounded-xl border bg-card p-3 shadow-2xs">
      <div className="flex items-center justify-between gap-2">
        <p className="truncate text-[11px] font-semibold">Net pay · Sene</p>
        <span className="shrink-0 rounded-full bg-info/10 px-2 py-0.5 text-[10px] font-semibold text-info">
          Frozen
        </span>
      </div>
      <div className="mt-1 flex items-center justify-between gap-2">
        <p className="truncate font-mono text-[10px] text-muted-foreground">
          Tax + pension applied
        </p>
        <span className="shrink-0 font-mono text-[11px] font-semibold tabular-nums">
          ETB 14,382.50
        </span>
      </div>
    </div>
  )
}
