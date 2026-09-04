"use client"

import {
  Briefcase,
  ClipboardCheck,
  GraduationCap,
  HandCoins,
  LayoutGrid,
  School,
  Wallet,
  type LucideIcon,
} from "lucide-react"
import { useRouter } from "next/navigation"

import { rateBand } from "@/components/attendance/report-charts"
import { Skeleton } from "@/components/ui/skeleton"
import { useTranslation } from "@/lib/i18n"
import type { DashboardData } from "@/lib/types"
import { cn, formatETB } from "@/lib/utils"

/** Compact ETB for tiles: 1.2M / 340K — full precision lives in the hint. */
function compactETB(value: string): string {
  const n = Number(value) || 0
  if (n >= 1_000_000)
    return `${(n / 1_000_000).toFixed(1).replace(/\.0$/, "")}M`
  if (n >= 10_000) return `${Math.round(n / 1_000)}K`
  return new Intl.NumberFormat("en-ET", { maximumFractionDigits: 0 }).format(n)
}

type Tone = "primary" | "success" | "warning" | "danger"

const TONE_CHIP: Record<Tone, string> = {
  primary: "bg-primary/10 text-primary",
  success: "bg-success/10 text-success",
  warning: "bg-warning/10 text-warning",
  danger: "bg-destructive/10 text-destructive",
}

const TONE_BAR: Record<Tone, string> = {
  primary: "bg-primary",
  success: "bg-success",
  warning: "bg-warning",
  danger: "bg-destructive",
}

const BAND_TONE: Record<ReturnType<typeof rateBand>, Tone> = {
  high: "success",
  mid: "warning",
  low: "danger",
  none: "warning",
}

/** 0–100, guarded against nulls and division by zero. */
function share(part: number, whole: number): number {
  if (!whole || whole <= 0) return 0
  return Math.min(100, Math.max(0, (part / whole) * 100))
}

type Tile = {
  key: string
  label: string
  /** The headline: a number or short measure. */
  value: string
  /** Small suffix after the value — "ETB", "%". */
  unit?: string
  /** A word instead of a number ("Not marked") — renders smaller and wraps. */
  state?: boolean
  icon: LucideIcon
  tone?: Tone
  /** Bottom meter, 0–100. Always carries meaning, never decoration. */
  meter?: { value: number; tone: Tone }
  hint?: string
  /** Warning-tinted card: something on this tile is waiting to be done. */
  attention?: boolean
  href: string
}

/**
 * One vital. Stacked on purpose: the label owns a full line (nothing gets
 * truncated at 165px on a phone), the number carries the weight, and the
 * meter under it answers "out of what?" without a sentence.
 */
function PulseTile({
  tile,
  className,
  onClick,
}: {
  tile: Tile
  className?: string
  onClick: () => void
}) {
  const tone = tile.tone ?? "primary"
  const Icon = tile.icon

  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        "group pressable flex min-w-0 flex-col rounded-2xl border bg-card p-3.5 text-left shadow-xs transition-all hover:border-primary/30 hover:shadow-sm",
        tile.attention && "border-warning/30 bg-warning/[0.04]",
        className
      )}
    >
      <span className="flex items-start gap-2">
        <span className="min-w-0 flex-1 text-[11px] leading-tight font-medium text-balance text-muted-foreground sm:text-xs">
          {tile.label}
        </span>
        <span
          className={cn(
            "flex size-7 shrink-0 items-center justify-center rounded-lg transition-transform group-hover:scale-105",
            TONE_CHIP[tone]
          )}
        >
          <Icon className="size-4" strokeWidth={1.75} />
        </span>
      </span>

      {tile.state ? (
        <span className="mt-2 block text-[15px] leading-snug font-semibold text-balance">
          {tile.value}
        </span>
      ) : (
        <span className="mt-2 flex min-w-0 items-baseline gap-1">
          <span className="min-w-0 truncate font-display text-[26px] leading-none font-semibold tracking-tight tabular-nums">
            {tile.value}
          </span>
          {tile.unit && (
            <span className="shrink-0 text-[11px] font-medium text-muted-foreground">
              {tile.unit}
            </span>
          )}
        </span>
      )}

      {/* Footer sits at the bottom so a row of tiles lines up whatever it carries. */}
      <span className="mt-auto block">
        {tile.meter && (
          <span className="mt-3 block h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <span
              className={cn(
                "block h-full rounded-full transition-[width] duration-500",
                TONE_BAR[tile.meter.tone]
              )}
              style={{ width: `${tile.meter.value}%` }}
            />
          </span>
        )}
        {tile.hint && (
          <span className="mt-2 line-clamp-2 block text-[11px] leading-tight text-muted-foreground/80">
            {tile.hint}
          </span>
        )}
      </span>
    </button>
  )
}

/** Column counts per tile count — never leave a lone tile stranded. */
function gridCols(count: number): string {
  if (count <= 1) return "grid-cols-1"
  if (count === 2) return "grid-cols-2"
  if (count === 3) return "grid-cols-2 sm:grid-cols-3"
  if (count === 4) return "grid-cols-2 lg:grid-cols-4"
  if (count === 5) return "grid-cols-2 sm:grid-cols-3 lg:grid-cols-5"
  return "grid-cols-2 sm:grid-cols-3 lg:grid-cols-4"
}

/**
 * The vitals row — one glance answers "is my school okay today?": who is
 * here, what came in, who is owed, who is at work. Every tile is a doorway
 * into its module. Blocks the caller can't see simply don't render.
 */
export function DashboardKpis({ data }: { data: DashboardData | null }) {
  const { t } = useTranslation("common")
  const router = useRouter()

  if (data === null) {
    return (
      <section>
        <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
          {t("dashboard.atAGlance")}
        </h2>
        <div className="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-[124px] rounded-2xl" />
          ))}
        </div>
      </section>
    )
  }

  const tiles: Tile[] = []

  if (data.platform) {
    tiles.push(
      {
        key: "schools",
        label: t("dashboard.schools"),
        value: data.platform.schools.toLocaleString(),
        icon: School,
        href: "/schools",
      },
      {
        key: "branches",
        label: t("dashboard.branches"),
        value: data.platform.branches.toLocaleString(),
        icon: LayoutGrid,
        href: "/branches",
      },
      {
        key: "platform-students",
        label: t("dashboard.students"),
        value: data.platform.students.toLocaleString(),
        icon: GraduationCap,
        href: "/students",
      },
      {
        key: "employees",
        label: t("dashboard.employees"),
        value: data.platform.employees.toLocaleString(),
        icon: Briefcase,
        href: "/employees",
      }
    )
  }

  if (data.org) {
    const { active, pending } = data.org.students
    tiles.push({
      key: "students",
      label: t("dashboard.students"),
      value: active.toLocaleString(),
      icon: GraduationCap,
      // Pending enrollments hold seats but sit on no class list — show the share.
      meter:
        pending > 0
          ? { value: share(pending, active + pending), tone: "warning" }
          : undefined,
      hint:
        pending > 0
          ? t("dashboard.pendingEnrollments", { count: pending })
          : t("dashboard.sectionsHint", { count: data.org.academics.sections }),
      href: "/students",
    })
  }

  if (data.attendance) {
    const today = data.attendance.today
    const tone = BAND_TONE[rateBand(today.rate)]
    const marked = today.rate !== null
    tiles.push({
      key: "attendance",
      label: t("dashboard.attendanceToday"),
      value: marked ? `${today.rate}` : t("dashboard.notMarked"),
      unit: marked ? "%" : undefined,
      state: !marked,
      icon: ClipboardCheck,
      tone,
      // Marked → how present the branch is. Unmarked → how far the register got.
      meter: {
        value: marked ? (today.rate ?? 0) : share(today.marked, today.enrolled),
        tone,
      },
      hint: t("dashboard.attendanceHint", {
        marked: today.marked.toLocaleString(),
        enrolled: today.enrolled.toLocaleString(),
      }),
      attention: !marked,
      href: "/attendance/reports",
    })
  }

  if (data.finance) {
    // The month reads against the best of the last six — is this a strong month?
    const best = data.finance.trend.reduce(
      (max, m) => Math.max(max, Number(m.collected) || 0),
      0
    )
    const collected = Number(data.finance.month.collected) || 0
    const balance = Number(data.finance.receivables.balance) || 0
    const overdue = Number(data.finance.receivables.overdue) || 0

    tiles.push(
      {
        key: "collected",
        label: t("dashboard.collectedThisMonth"),
        value: compactETB(data.finance.month.collected),
        unit: "ETB",
        icon: Wallet,
        tone: "success",
        meter: { value: share(collected, best), tone: "success" },
        hint: t("dashboard.collectedHint", {
          amount: formatETB(data.finance.month.collected),
          count: data.finance.month.payments,
        }),
        href: "/finance",
      },
      {
        key: "outstanding",
        label: t("dashboard.outstanding"),
        value: compactETB(data.finance.receivables.balance),
        unit: "ETB",
        icon: HandCoins,
        tone: overdue > 0 ? "danger" : "primary",
        // How much of what families owe has already gone past its due date.
        meter: {
          value: share(overdue, balance),
          tone: overdue > 0 ? "danger" : "primary",
        },
        hint: t("dashboard.outstandingHint", {
          students: data.finance.receivables.students,
          overdue: formatETB(data.finance.receivables.overdue),
        }),
        href: "/invoices",
      }
    )
  }

  if (data.staff_today) {
    const s = data.staff_today
    const marked = s.marked > 0
    const rate = marked && s.total > 0 ? (s.present / s.total) * 100 : null
    const tone = BAND_TONE[rateBand(rate === null ? null : Math.round(rate))]
    tiles.push({
      key: "staff",
      label: t("dashboard.staffToday"),
      value: marked ? `${s.present}/${s.total}` : `${s.total}`,
      icon: Briefcase,
      tone: marked ? tone : "primary",
      meter: {
        value: marked ? (rate ?? 0) : 0,
        tone: marked ? tone : "warning",
      },
      // An unmarked register is the actionable fact — it outranks "on leave",
      // otherwise the amber tile explains itself with the wrong sentence.
      hint: !marked
        ? t("dashboard.staffNotMarked")
        : s.on_leave > 0
          ? t("dashboard.staffOnLeave", { count: s.on_leave })
          : t("dashboard.staffLate", { count: s.late }),
      attention: !marked,
      href: "/hr/attendance",
    })
  }

  if (tiles.length === 0) return null

  return (
    <section>
      <h2 className="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t("dashboard.atAGlance")}
      </h2>
      <div className={cn("grid gap-2.5 sm:gap-3", gridCols(tiles.length))}>
        {tiles.map((tile, i) => (
          <PulseTile
            key={tile.key}
            tile={tile}
            // An odd count leaves the last tile alone on the phone's 2-up
            // grid — let it stretch instead of dangling half-width.
            className={
              tiles.length > 1 &&
              tiles.length % 2 === 1 &&
              i === tiles.length - 1
                ? "max-sm:col-span-2"
                : undefined
            }
            onClick={() => router.push(tile.href)}
          />
        ))}
      </div>
    </section>
  )
}
