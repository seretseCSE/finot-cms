"use client"

import { ChevronLeft, ChevronRight, Flame, LogIn, LogOut } from "lucide-react"
import { useEffect, useMemo, useRef, useState, type TouchEvent as ReactTouchEvent } from "react"

import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { toEthiopian } from "@/lib/ethiopian-date"
import { useTranslation } from "@/lib/i18n"
import type { AttendanceStatus, MyAttendanceDay, MyAttendanceSummary } from "@/lib/types"
import { cn } from "@/lib/utils"
import { addisToday, fmtDate, gregMonthName } from "@/lib/dates"
import { useCalendar } from "@/lib/use-calendar"

const STATUS_TONE: Record<AttendanceStatus, string> = {
  present: "bg-success/10 text-success",
  late: "bg-warning/10 text-warning",
  absent: "bg-destructive/10 text-destructive",
  excused: "bg-info/10 text-info",
}

// Full-cell tint for the calendar grid — the day itself carries its status
// color, so a whole month reads at a glance without opening anything.
const STATUS_CELL: Record<AttendanceStatus, string> = {
  present: "bg-success/15 text-success hover:bg-success/25",
  late: "bg-warning/15 text-warning hover:bg-warning/25",
  absent: "bg-destructive/15 text-destructive hover:bg-destructive/25",
  excused: "bg-info/15 text-info hover:bg-info/25",
}

const STATUS_DOT: Record<AttendanceStatus, string> = {
  present: "bg-success",
  late: "bg-warning",
  absent: "bg-destructive",
  excused: "bg-info",
}

const STATUS_ORDER: AttendanceStatus[] = ["present", "late", "absent", "excused"]

// The school week is Monday–Saturday everywhere else in the app (timetable,
// HR) — Sunday is never a class day, so the grid never renders a 7th column.
const SCHOOL_WEEKDAYS = [1, 2, 3, 4, 5, 6] as const

function todayIso(): string {
  // Addis wall-clock date — keeps "today" aligned with the school day.
  return addisToday()
}

function monthOf(iso: string): string {
  return iso.slice(0, 7)
}

function pad(n: number): string {
  return String(n).padStart(2, "0")
}

function toIso(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function addDays(iso: string, delta: number): string {
  const [y, m, d] = iso.split("-").map(Number)
  return toIso(new Date(y, m - 1, d + delta))
}

function shiftMonth(month: string, delta: number): string {
  const [y, m] = month.split("-").map(Number)
  return monthOf(toIso(new Date(y, m - 1 + delta, 1)))
}

/** Monday of the school week (Mon–Sat) containing `iso`. */
function weekStart(iso: string): string {
  const [y, m, d] = iso.split("-").map(Number)
  const dt = new Date(y, m - 1, d)
  const jsDay = dt.getDay() // 0=Sun..6=Sat
  const mondayOffset = jsDay === 0 ? -6 : 1 - jsDay
  dt.setDate(dt.getDate() + mondayOffset)
  return toIso(dt)
}

/** "8:05 AM" from a 24h "HH:MM" string. */
function to12h(time: string | null): string | null {
  if (!time) return null
  const [h, m] = time.split(":").map(Number)
  const hour12 = h % 12 === 0 ? 12 : h % 12
  return `${hour12}:${pad(m)} ${h < 12 ? "AM" : "PM"}`
}

interface DayCell {
  date: string
  inMonth: boolean
}

interface MonthResponse {
  data: MyAttendanceDay[]
  meta?: { summary?: MyAttendanceSummary }
}

/**
 * Animated year-to-date attendance ring — the hero number of the page. Color
 * follows the status conventions: green when healthy, amber when slipping,
 * red when worrying.
 */
function AttendanceRing({ value, label }: { value: number | null; label: string }) {
  const radius = 34
  const circumference = 2 * Math.PI * radius
  const [drawn, setDrawn] = useState(false)

  useEffect(() => {
    const frame = requestAnimationFrame(() => setDrawn(true))
    return () => cancelAnimationFrame(frame)
  }, [])

  const pct = value ?? 0
  const tone =
    value == null
      ? "text-muted-foreground/40"
      : pct >= 90
        ? "text-success"
        : pct >= 75
          ? "text-warning"
          : "text-destructive"

  return (
    <div className="relative size-24 shrink-0 sm:size-28">
      <svg viewBox="0 0 80 80" className="size-full -rotate-90">
        <circle cx="40" cy="40" r={radius} className="text-muted" stroke="currentColor" strokeWidth="7" fill="none" />
        <circle
          cx="40"
          cy="40"
          r={radius}
          className={cn("transition-[stroke-dashoffset] duration-700", tone)}
          stroke="currentColor"
          strokeWidth="7"
          fill="none"
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={drawn ? circumference - (circumference * pct) / 100 : circumference}
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center gap-0.5">
        <span className="text-xl font-semibold tabular-nums sm:text-2xl">
          {value == null ? "—" : `${value}%`}
        </span>
        <span className="px-2 text-center text-[9px] font-medium tracking-wide text-muted-foreground uppercase">
          {label}
        </span>
      </div>
    </div>
  )
}

/** One check-in/out line with its icon — shared by the hero and day detail. */
function TimeRow({
  icon: Icon,
  label,
  time,
}: {
  icon: typeof LogIn
  label: string
  time: string
}) {
  return (
    <div className="flex items-center gap-2.5">
      <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted">
        <Icon className="size-3.5" strokeWidth={1.75} />
      </span>
      <span className="min-w-0 flex-1 truncate text-xs text-muted-foreground">{label}</span>
      <span className="text-sm font-medium tabular-nums">{time}</span>
    </div>
  )
}

/**
 * The family-lane attendance view, built like an app screen: a hero card
 * (year-to-date ring, today's status with in/out times, streak), then a
 * swipeable month calendar whose status tiles double as spotlight filters —
 * tap "Absent" and every absence lights up while the rest of the month fades.
 * Months are cached per endpoint and the neighbouring month is prefetched in
 * the background, so flipping back is instant even on 3G.
 */
export function AttendanceCalendar({ endpoint }: { endpoint: string }) {
  const { t, locale } = useTranslation("me")
  const { t: ta } = useTranslation("attendance")
  const { t: tt } = useTranslation("timetable")
  const { t: tf } = useTranslation("fees") // Ethiopian month names live with billing
  const { calendar } = useCalendar()

  const today = useMemo(() => todayIso(), [])
  const todayMonth = monthOf(today)

  const [month, setMonth] = useState(todayMonth)
  const [dir, setDir] = useState<"fwd" | "back" | null>(null)
  // undefined = untouched → the detail card defaults to today's record.
  const [selected, setSelected] = useState<string | null | undefined>(undefined)
  const [filter, setFilter] = useState<AttendanceStatus | null>(null)

  const [months, setMonths] = useState<Record<string, MyAttendanceDay[]>>({})
  const [summaries, setSummaries] = useState<Record<string, MyAttendanceSummary>>({})
  const requested = useRef<Set<string>>(new Set())
  const touchStart = useRef<{ x: number; y: number } | null>(null)

  useEffect(() => {
    const load = (m: string) => {
      const key = `${endpoint}|${m}`
      if (requested.current.has(key)) return
      requested.current.add(key)
      apiFetch<MonthResponse>(`${endpoint}?month=${m}`)
        .then((res) => {
          setMonths((prev) => ({ ...prev, [key]: res.data }))
          const summary = res.meta?.summary
          if (summary) setSummaries((prev) => ({ ...prev, [endpoint]: summary }))
        })
        .catch(() => {
          requested.current.delete(key)
          setMonths((prev) => ({ ...prev, [key]: [] }))
        })
    }
    load(month)
    // Today's month always loads — the hero's "today" card and the ring need
    // it even while browsing history.
    if (month !== todayMonth) load(todayMonth)
    // Prefetch the previous month once the visible one is on its way, so the
    // first swipe back never shows a skeleton.
    const idle = window.setTimeout(() => load(shiftMonth(month, -1)), 600)
    return () => window.clearTimeout(idle)
  }, [endpoint, month, todayMonth])

  const loading = !(`${endpoint}|${month}` in months)
  const summary: MyAttendanceSummary | undefined = summaries[endpoint]

  const dayMap = useMemo(() => {
    const map = new Map<string, MyAttendanceDay>()
    for (const m of [month, todayMonth]) {
      const rows = months[`${endpoint}|${m}`]
      if (!rows) continue
      for (const row of rows) map.set(row.date, row)
    }
    return map
  }, [months, endpoint, month, todayMonth])

  const monthWeeks = useMemo((): DayCell[][] => {
    const [y, m] = month.split("-").map(Number)
    const daysInMonth = new Date(y, m, 0).getDate()
    const first = `${month}-01`
    const last = `${month}-${pad(daysInMonth)}`
    const gridEnd = addDays(weekStart(last), 5)

    const weeks: DayCell[][] = []
    let cursor = weekStart(first)
    while (cursor <= gridEnd) {
      const week: DayCell[] = []
      for (let i = 0; i < 6; i++) {
        week.push({ date: cursor, inMonth: monthOf(cursor) === month })
        cursor = addDays(cursor, 1)
      }
      cursor = addDays(cursor, 1) // skip Sunday
      weeks.push(week)
    }
    return weeks
  }, [month])

  // Marked days of the viewed month drive the four status tiles.
  const markedDays = useMemo(
    () =>
      monthWeeks
        .flat()
        .filter((cell) => cell.inMonth && cell.date <= today)
        .map((cell) => dayMap.get(cell.date))
        .filter((day): day is MyAttendanceDay => day != null),
    [monthWeeks, dayMap, today]
  )

  const counts = useMemo(() => {
    const c: Partial<Record<AttendanceStatus, number>> = {}
    for (const day of markedDays) c[day.status] = (c[day.status] ?? 0) + 1
    return c
  }, [markedDays])

  // Until the user picks a day, the detail card follows today's record — the
  // page never opens onto an empty pane.
  const effectiveSelected =
    selected !== undefined ? selected : month === todayMonth && dayMap.has(today) ? today : null

  const monthLabel = useMemo(() => {
    const [, m] = month.split("-").map(Number)
    return `${gregMonthName(m, locale)} ${month.slice(0, 4)}`
  }, [month, locale])

  // The Ethiopian months this Gregorian month spans — a quiet cultural anchor
  // under the western label ("ሰኔ – ሐምሌ 2018").
  const ecLabel = useMemo(() => {
    const [y, m] = month.split("-").map(Number)
    const start = toEthiopian(`${month}-01`)
    const end = toEthiopian(`${month}-${pad(new Date(y, m, 0).getDate())}`)
    const startName = tf(`months.${start.month}`)
    const endName = tf(`months.${end.month}`)
    return start.month === end.month
      ? `${startName} ${start.year}`
      : `${startName} – ${endName} ${end.year}`
  }, [month, tf])

  const canGoNext = month < todayMonth
  const goPrev = () => {
    setDir("back")
    setSelected(undefined)
    setMonth((m) => shiftMonth(m, -1))
  }
  const goNext = () => {
    if (!canGoNext) return
    setDir("fwd")
    setSelected(undefined)
    setMonth((m) => shiftMonth(m, 1))
  }
  const goToday = () => {
    setDir(month < todayMonth ? "fwd" : "back")
    setMonth(todayMonth)
    setSelected(undefined)
  }

  const onTouchStart = (e: ReactTouchEvent) => {
    touchStart.current = { x: e.touches[0].clientX, y: e.touches[0].clientY }
  }
  const onTouchEnd = (e: ReactTouchEvent) => {
    const start = touchStart.current
    touchStart.current = null
    if (!start) return
    const dx = e.changedTouches[0].clientX - start.x
    const dy = e.changedTouches[0].clientY - start.y
    // A deliberate horizontal swipe only — never hijack vertical scrolling.
    if (Math.abs(dx) < 48 || Math.abs(dx) < Math.abs(dy) * 1.5) return
    if (dx < 0) goNext()
    else goPrev()
  }

  const todayRecord = dayMap.get(today)
  const todayIsSunday = new Date(`${today}T00:00:00`).getDay() === 0
  const selectedDay = effectiveSelected ? dayMap.get(effectiveSelected) : undefined

  const firstLoad = loading && !summary

  return (
    <div className="mx-auto grid grid-cols-1 items-start gap-3 sm:gap-4 md:grid-cols-[300px_minmax(0,1fr)]">
      {/* ── Hero: the ring, today, the streak ─────────────────────────── */}
      {firstLoad ? (
        <Skeleton className="h-44 w-full rounded-2xl" />
      ) : (
        <div className="rounded-2xl border bg-card p-4 shadow-xs sm:p-5">
          <div className="flex items-center gap-4">
            <AttendanceRing value={summary?.rate ?? null} label={t("attendance.thisYear")} />
            <div className="min-w-0 flex-1 space-y-1.5">
              <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                {t("attendance.today")} ·{" "}
                {fmtDate(today, { weekday: true, noYear: true })}
              </p>
              {todayRecord ? (
                <>
                  <span
                    className={cn(
                      "inline-flex rounded-full px-2.5 py-1 text-xs font-semibold",
                      STATUS_TONE[todayRecord.status]
                    )}
                  >
                    {ta(`statuses.${todayRecord.status}`)}
                  </span>
                  {(todayRecord.check_in || todayRecord.check_out) && (
                    <p className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground tabular-nums">
                      {todayRecord.check_in && (
                        <span className="inline-flex items-center gap-1">
                          <LogIn className="size-3" strokeWidth={1.75} />
                          {to12h(todayRecord.check_in)}
                        </span>
                      )}
                      {todayRecord.check_out && (
                        <span className="inline-flex items-center gap-1">
                          <LogOut className="size-3" strokeWidth={1.75} />
                          {to12h(todayRecord.check_out)}
                        </span>
                      )}
                    </p>
                  )}
                </>
              ) : (
                <p className="text-sm text-muted-foreground">
                  {todayIsSunday ? t("attendance.noSchool") : t("attendance.notMarked")}
                </p>
              )}
            </div>
          </div>

          {summary && summary.total > 0 && (
            <div className="mt-4 flex flex-wrap gap-1.5 border-t pt-3">
              <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-[11px] font-medium text-primary">
                <Flame className="size-3" strokeWidth={2} />
                {t("attendance.streakDays", { count: summary.streak })}
              </span>
              <span
                className={cn(
                  "inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-medium",
                  summary.absent > 0 ? STATUS_TONE.absent : "bg-muted/60 text-muted-foreground"
                )}
              >
                {t("attendance.absences", { count: summary.absent })}
              </span>
              <span className="inline-flex items-center rounded-full bg-muted/60 px-2 py-1 text-[11px] font-medium text-muted-foreground">
                {t("attendance.recordedDays", { count: summary.total })}
              </span>
            </div>
          )}
        </div>
      )}

      {/* ── Calendar: nav, spotlight tiles, swipeable grid ─────────────── */}
      <div className="overflow-hidden rounded-2xl border bg-card shadow-xs md:row-span-2">
        <div className="flex items-center justify-between gap-2 border-b px-3.5 py-2.5">
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold">
              {calendar === "ethiopian" ? ecLabel : monthLabel}
            </p>
            <p className="truncate text-[11px] text-muted-foreground">
              {calendar === "ethiopian" ? monthLabel : ecLabel}
            </p>
          </div>
          <div className="flex shrink-0 items-center gap-1">
            {month !== todayMonth && (
              <button
                type="button"
                onClick={goToday}
                className="pressable rounded-full px-2.5 py-1 text-[11px] font-semibold text-primary hover:bg-primary/10"
              >
                {t("attendance.today")}
              </button>
            )}
            <button
              type="button"
              onClick={goPrev}
              aria-label={t("attendance.previousMonth")}
              className="pressable inline-flex size-8 items-center justify-center rounded-full hover:bg-muted"
            >
              <ChevronLeft className="size-4" />
            </button>
            <button
              type="button"
              onClick={goNext}
              disabled={!canGoNext}
              aria-label={t("attendance.nextMonth")}
              className="pressable inline-flex size-8 items-center justify-center rounded-full hover:bg-muted disabled:pointer-events-none disabled:opacity-30"
            >
              <ChevronRight className="size-4" />
            </button>
          </div>
        </div>

        {/* Status tiles — tap one to spotlight those days in the grid. */}
        <div className="grid grid-cols-4 gap-1.5 px-2.5 pt-2.5 sm:px-3 sm:pt-3">
          {STATUS_ORDER.map((status) => {
            const count = counts[status] ?? 0
            const active = filter === status
            return (
              <button
                key={status}
                type="button"
                disabled={count === 0}
                aria-pressed={active}
                onClick={() => setFilter((f) => (f === status ? null : status))}
                className={cn(
                  "pressable flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-xl border py-1.5 transition-colors",
                  active ? cn(STATUS_TONE[status], "ring-1 ring-current") : "hover:bg-muted/50",
                  count === 0 && "opacity-40"
                )}
              >
                <span className="text-sm font-semibold tabular-nums sm:text-base">{count}</span>
                <span className="flex items-center gap-1 text-[10px] font-medium text-muted-foreground">
                  <span className={cn("size-1.5 rounded-full", STATUS_DOT[status])} />
                  {ta(`statuses.${status}`)}
                </span>
              </button>
            )
          })}
        </div>

        <div className="grid grid-cols-6 px-2.5 pt-2 text-center sm:px-3">
          {SCHOOL_WEEKDAYS.map((d) => (
            <div key={d} className="py-1.5 text-[10px] font-medium text-muted-foreground sm:text-[11px]">
              {tt(`daysShort.${d}`)}
            </div>
          ))}
        </div>

        <div onTouchStart={onTouchStart} onTouchEnd={onTouchEnd}>
          {loading ? (
            <div className="p-2.5 sm:p-3">
              <Skeleton className="h-56 w-full rounded-xl" />
            </div>
          ) : (
            <div
              key={month}
              className={cn(
                "grid grid-cols-6 gap-1 p-2.5 pt-0 sm:p-3 sm:pt-0",
                dir === "fwd" && "animate-in duration-200 fade-in slide-in-from-right-4",
                dir === "back" && "animate-in duration-200 fade-in slide-in-from-left-4"
              )}
            >
              {monthWeeks.flat().map((cell) => {
                const day = cell.inMonth ? dayMap.get(cell.date) : undefined
                const isToday = cell.date === today
                const isFuture = cell.date > today
                const isSelected = effectiveSelected === cell.date
                const dimmed = filter !== null && (!day || day.status !== filter)
                return (
                  <button
                    key={cell.date}
                    type="button"
                    disabled={!cell.inMonth || isFuture || !day}
                    onClick={() => setSelected(isSelected ? null : cell.date)}
                    className={cn(
                      "pressable flex h-11 items-center justify-center rounded-lg text-xs font-medium tabular-nums transition-all sm:h-12 sm:text-sm",
                      !cell.inMonth && "invisible",
                      isFuture && "text-muted-foreground/40",
                      !day && !isFuture && "text-foreground/70",
                      day && STATUS_CELL[day.status],
                      dimmed && "opacity-25",
                      isToday && "font-bold ring-1 ring-primary/50",
                      isSelected && "ring-2 ring-primary"
                    )}
                  >
                    {Number(cell.date.slice(8, 10))}
                  </button>
                )
              })}
            </div>
          )}
        </div>

        {!loading && markedDays.length === 0 && (
          <p className="border-t px-4 py-4 text-center text-xs text-muted-foreground">
            {t("attendance.empty")}
          </p>
        )}
      </div>

      {/* ── Day detail ─────────────────────────────────────────────────── */}
      {selectedDay && (
        <div
          key={selectedDay.date}
          className="rounded-2xl border bg-card p-4 shadow-xs duration-200 animate-in fade-in slide-in-from-bottom-2 md:col-start-1"
        >
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <p className="text-sm font-semibold">
                {fmtDate(selectedDay.date, { weekday: true, noYear: true })}
              </p>
              <p className="text-[11px] text-muted-foreground">
                {(() => {
                  const ec = toEthiopian(selectedDay.date)
                  return `${tf(`months.${ec.month}`)} ${ec.day}, ${ec.year}`
                })()}
              </p>
            </div>
            <span
              className={cn(
                "inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold",
                STATUS_TONE[selectedDay.status]
              )}
            >
              {ta(`statuses.${selectedDay.status}`)}
            </span>
          </div>

          {(selectedDay.check_in || selectedDay.check_out) && (
            <div className="mt-3 flex flex-col">
              {selectedDay.check_in && (
                <TimeRow icon={LogIn} label={t("attendance.checkedIn")} time={to12h(selectedDay.check_in)!} />
              )}
              {selectedDay.check_in && selectedDay.check_out && (
                <span className="ml-3.5 h-3 w-px bg-border" />
              )}
              {selectedDay.check_out && (
                <TimeRow icon={LogOut} label={t("attendance.checkedOut")} time={to12h(selectedDay.check_out)!} />
              )}
            </div>
          )}

          {selectedDay.note && (
            <p className="mt-3 rounded-xl bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
              {selectedDay.note}
            </p>
          )}
        </div>
      )}
    </div>
  )
}
