import {
  ArrowLeftRight,
  Banknote,
  BookOpen,
  CalendarCheck,
  CheckCheck,
  Cog,
  MessagesSquare,
  GraduationCap,
  ShieldAlert,
  UserRound,
  Users,
  type LucideIcon,
} from "lucide-react"

import type { NotificationCategory } from "@/lib/types"

/**
 * One visual identity per notification category — the bell popover, the feed
 * page and the settings matrix all read this map so a "finance" row looks
 * identical everywhere. Tints follow the chart/status token conventions.
 */
export const CATEGORY_META: Record<
  NotificationCategory,
  { icon: LucideIcon; bubble: string }
> = {
  security: { icon: ShieldAlert, bubble: "bg-destructive/10 text-destructive" },
  finance: { icon: Banknote, bubble: "bg-success/10 text-success" },
  attendance: { icon: CalendarCheck, bubble: "bg-warning/10 text-warning" },
  academics: { icon: GraduationCap, bubble: "bg-primary/10 text-primary" },
  lms: { icon: BookOpen, bubble: "bg-info/10 text-info" },
  chat: { icon: MessagesSquare, bubble: "bg-primary/10 text-primary" },
  movement: { icon: ArrowLeftRight, bubble: "bg-warning/10 text-warning" },
  approvals: { icon: CheckCheck, bubble: "bg-primary/10 text-primary" },
  hr: { icon: Users, bubble: "bg-info/10 text-info" },
  family: { icon: UserRound, bubble: "bg-primary/10 text-primary" },
  tutoring: { icon: GraduationCap, bubble: "bg-success/10 text-success" },
  system: { icon: Cog, bubble: "bg-muted text-muted-foreground" },
}

export const FALLBACK_META = CATEGORY_META.system

/** "2h ago" in the viewer's language via Intl — no library, 3G-friendly. */
export function timeAgo(iso: string, locale: string): string {
  const then = new Date(iso).getTime()
  const seconds = Math.round((then - Date.now()) / 1000)
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: "auto", style: "narrow" })

  const table: [Intl.RelativeTimeFormatUnit, number][] = [
    ["year", 31_536_000],
    ["month", 2_592_000],
    ["week", 604_800],
    ["day", 86_400],
    ["hour", 3_600],
    ["minute", 60],
  ]

  for (const [unit, size] of table) {
    if (Math.abs(seconds) >= size) return rtf.format(Math.trunc(seconds / size), unit)
  }

  return rtf.format(0, "minute")
}
