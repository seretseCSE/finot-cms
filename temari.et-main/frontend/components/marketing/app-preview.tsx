import {
  CalendarCheck,
  ChevronRight,
  GraduationCap,
  Receipt,
} from "lucide-react"

import { cn } from "@/lib/utils"

/**
 * A real mini-rendition of the Temari family portal, built from the product's
 * own design tokens (not a fake screenshot image). Content is illustrative
 * sample data in the product's actual shape.
 */
export function AppPreview({ className }: { className?: string }) {
  return (
    <div
      className={cn(
        "w-full max-w-85 rounded-[2.5rem] border bg-card p-2.5 shadow-xl",
        className
      )}
      aria-hidden
    >
      <div className="overflow-hidden rounded-[2rem] border bg-background">
        {/* App header — a parent (Solomon) viewing his son (Bruk Solomon),
            patronymically consistent so the two names read as one family. */}
        <div className="brand-hero px-5 pt-6 pb-12 text-white">
          <p className="text-sm/none text-white/70">ሰላም 👋</p>
          <p className="mt-1.5 font-display text-lg font-semibold">
            Solomon Tesfaye
          </p>
          <div className="mt-4 inline-flex items-center gap-2 rounded-full bg-white/12 px-3 py-1.5 text-xs font-medium backdrop-blur">
            <span className="font-ethiopic text-white/60">ልጅዎ</span>
            <span className="font-ethiopic">ብሩክ ሰለሞን</span>
            <span className="text-white/60">· Grade 7B</span>
          </div>
        </div>

        {/* Attendance ring card */}
        <div className="-mt-7 px-4">
          <div className="flex items-center gap-4 rounded-2xl border bg-card p-4 shadow-sm">
            <AttendanceRing value={96} />
            <div className="min-w-0">
              <p className="text-sm font-semibold">Attendance</p>
              <p className="mt-0.5 text-xs text-muted-foreground">
                172 of 179 school days
              </p>
              <p className="mt-1 text-xs font-medium text-success">
                Present today
              </p>
            </div>
          </div>
        </div>

        {/* Feed rows */}
        <div className="space-y-2.5 p-4">
          <PreviewRow
            icon={<Receipt className="size-4" />}
            tone="success"
            title="Tuition · Meskerem"
            sub="Paid · receipt verified"
            badge="ETB 1,850"
          />
          <PreviewRow
            icon={<GraduationCap className="size-4" />}
            tone="info"
            title="Semester I report card"
            sub="Average 87.6 · rank 4 of 46"
            badge="New"
          />
          <PreviewRow
            icon={<CalendarCheck className="size-4" />}
            tone="default"
            title="This week"
            sub="Maths test Thu · Biology lab Fri"
          />
        </div>
      </div>
    </div>
  )
}

function AttendanceRing({ value }: { value: number }) {
  const r = 26
  const c = 2 * Math.PI * r
  return (
    <div className="relative size-16 shrink-0">
      <svg viewBox="0 0 64 64" className="size-16 -rotate-90">
        <circle
          cx="32"
          cy="32"
          r={r}
          fill="none"
          strokeWidth="6"
          className="stroke-muted"
        />
        <circle
          cx="32"
          cy="32"
          r={r}
          fill="none"
          strokeWidth="6"
          strokeLinecap="round"
          className="stroke-primary"
          strokeDasharray={c}
          strokeDashoffset={c * (1 - value / 100)}
        />
      </svg>
      <span className="absolute inset-0 flex items-center justify-center font-mono text-sm font-semibold tabular-nums">
        {value}%
      </span>
    </div>
  )
}

function PreviewRow({
  icon,
  title,
  sub,
  badge,
  tone,
}: {
  icon: React.ReactNode
  title: string
  sub: string
  badge?: string
  tone: "success" | "info" | "default"
}) {
  return (
    <div className="flex items-center gap-3 rounded-2xl border bg-card p-3">
      <span
        className={cn(
          "flex size-9 shrink-0 items-center justify-center rounded-xl",
          tone === "success" && "bg-success/10 text-success",
          tone === "info" && "bg-info/10 text-info",
          tone === "default" && "bg-accent text-accent-foreground"
        )}
      >
        {icon}
      </span>
      <div className="min-w-0 flex-1">
        <p className="truncate text-[13px] font-semibold">{title}</p>
        <p className="truncate text-xs text-muted-foreground">{sub}</p>
      </div>
      {badge ? (
        <span
          className={cn(
            "shrink-0 rounded-full px-2 py-0.5 font-mono text-[11px] font-medium tabular-nums",
            tone === "success" && "bg-success/10 text-success",
            tone === "info" && "bg-info/10 text-info",
            tone === "default" && "bg-muted text-muted-foreground"
          )}
        >
          {badge}
        </span>
      ) : (
        <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
      )}
    </div>
  )
}
