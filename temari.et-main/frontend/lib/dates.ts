/**
 * THE date/time display layer. Storage and the wire are always Gregorian
 * (ISO `YYYY-MM-DD`, 24h `HH:MM`, UTC instants) — this module only decides
 * how a moment is WRITTEN, per the active workspace's display settings:
 *
 *  - calendar: `ethiopian` (national default) or `gregorian`
 *  - clock:    `standard` (8:00 AM) or `ethiopian` (the day counted from
 *              dawn — "2:00 ጠዋት" = 8:00 AM, how schools speak bell times)
 *
 * Every user-facing date in the app must come from these formatters — never
 * `toLocaleDateString` / date-fns `format` directly (client twin of the
 * backend's App\Support\DateFormatter; month tables mirror lang/dates.php).
 *
 * Prefs live in a module-level store (same pattern as the i18n validation
 * messages) so charts and non-React code format correctly; CalendarProvider
 * stamps it from the workspace and bumps a version for React subscribers.
 */

import type { CalendarMode, ClockMode, Locale } from "@/lib/types"

import { toEthiopian } from "./ethiopian-date"

export interface CalendarPrefs {
  calendar: CalendarMode
  clock: ClockMode
}

export const DEFAULT_CALENDAR_PREFS: CalendarPrefs = {
  calendar: "ethiopian",
  clock: "ethiopian",
}

// ---------------------------------------------------------------------------
// Vocabulary (fixed facts, not UI copy — mirrors backend lang/*/dates.php)
// ---------------------------------------------------------------------------

const ETH_MONTHS: Record<Locale, string[]> = {
  en: [
    "Meskerem", "Tikimt", "Hidar", "Tahsas", "Tir", "Yekatit", "Megabit",
    "Miazia", "Ginbot", "Sene", "Hamle", "Nehase", "Pagume",
  ],
  am: [
    "መስከረም", "ጥቅምት", "ኅዳር", "ታኅሣሥ", "ጥር", "የካቲት", "መጋቢት",
    "ሚያዝያ", "ግንቦት", "ሰኔ", "ሐምሌ", "ነሐሴ", "ጳጉሜ",
  ],
  om: [
    "Fulbaana", "Onkoloolessa", "Sadaasa", "Muddee", "Amajjii", "Guraandhala",
    "Bitootessa", "Elba", "Caamsaa", "Waxabajjii", "Adooleessa", "Hagayya", "Qaammee",
  ],
}

const GREG_MONTHS: Record<Locale, string[]> = {
  en: [
    "January", "February", "March", "April", "May", "June", "July",
    "August", "September", "October", "November", "December",
  ],
  am: [
    "ጃንዋሪ", "ፌብሯሪ", "ማርች", "ኤፕሪል", "ሜይ", "ጁን", "ጁላይ",
    "ኦገስት", "ሴፕቴምበር", "ኦክቶበር", "ኖቬምበር", "ዲሴምበር",
  ],
  om: [
    "Amajjii", "Guraandhala", "Bitootessa", "Elba", "Caamsaa", "Waxabajjii",
    "Adooleessa", "Hagayya", "Fulbaana", "Onkoloolessa", "Sadaasa", "Muddee",
  ],
}

/** 0 = Sunday … 6 = Saturday (JS getDay order). */
const WEEKDAYS: Record<Locale, string[]> = {
  en: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
  am: ["እሑድ", "ሰኞ", "ማክሰኞ", "ረቡዕ", "ሐሙስ", "ዓርብ", "ቅዳሜ"],
  om: ["Dilbata", "Wiixata", "Kibxata", "Roobii", "Kamiisa", "Jimaata", "Sanbata"],
}

const WEEKDAYS_SHORT: Record<Locale, string[]> = {
  en: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
  am: ["እሑድ", "ሰኞ", "ማክሰ", "ረቡዕ", "ሐሙስ", "ዓርብ", "ቅዳሜ"],
  om: ["Dil", "Wix", "Kib", "Rob", "Kam", "Jim", "San"],
}

/** Ethiopian-clock day periods: night 0–5, morning 6–11, afternoon 12–17, evening 18–23. */
const DAY_PERIODS: Record<Locale, { night: string; morning: string; afternoon: string; evening: string }> = {
  en: { night: "night", morning: "morning", afternoon: "afternoon", evening: "evening" },
  am: { night: "ሌሊት", morning: "ጠዋት", afternoon: "ከሰዓት", evening: "ምሽት" },
  om: { night: "halkan", morning: "ganama", afternoon: "waaree booda", evening: "galgala" },
}

const ERA: Record<Locale, { eth: string; greg: string }> = {
  en: { eth: "E.C.", greg: "G.C." },
  am: { eth: "ዓ.ም.", greg: "እ.ኤ.አ." },
  om: { eth: "A.L.I.", greg: "A.L.A." },
}

export function ethMonthName(month: number, locale: Locale = currentState.locale): string {
  return ETH_MONTHS[locale]?.[month - 1] ?? ETH_MONTHS.en[month - 1] ?? String(month)
}

export function gregMonthName(month: number, locale: Locale = currentState.locale): string {
  return GREG_MONTHS[locale]?.[month - 1] ?? GREG_MONTHS.en[month - 1] ?? String(month)
}

export function weekdayName(day: number, locale: Locale = currentState.locale, short = false): string {
  const table = short ? WEEKDAYS_SHORT : WEEKDAYS
  return table[locale]?.[day] ?? table.en[day] ?? String(day)
}

// ---------------------------------------------------------------------------
// Module store — stamped by CalendarProvider / the i18n provider
// ---------------------------------------------------------------------------

const currentState: { prefs: CalendarPrefs; locale: Locale } = {
  prefs: { ...DEFAULT_CALENDAR_PREFS },
  locale: "en",
}

const listeners = new Set<() => void>()

export function setCalendarPrefs(prefs: Partial<CalendarPrefs>): void {
  const next = { ...currentState.prefs, ...prefs }
  if (next.calendar === currentState.prefs.calendar && next.clock === currentState.prefs.clock) return
  currentState.prefs = next
  listeners.forEach((fn) => fn())
}

export function setDateLocale(locale: Locale): void {
  if (locale === currentState.locale) return
  currentState.locale = locale
  listeners.forEach((fn) => fn())
}

export function getCalendarPrefs(): CalendarPrefs {
  return currentState.prefs
}

/** Subscribe to pref/locale changes (used by the useCalendar hook). */
export function subscribeCalendar(fn: () => void): () => void {
  listeners.add(fn)
  return () => listeners.delete(fn)
}

// ---------------------------------------------------------------------------
// Parsing — Addis wall time, no DST, constant +3
// ---------------------------------------------------------------------------

const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/

interface DayParts {
  y: number
  m: number
  d: number
  weekday: number
  hour: number | null
  minute: number | null
}

/**
 * Normalize any accepted input to Addis-wall-time day parts. Bare ISO dates
 * ("2026-07-22") never shift; instants (Date / ISO datetime strings) render
 * on Africa/Addis_Ababa (fixed UTC+3 — no Intl round-trip needed).
 */
function toParts(value: string | Date | null | undefined): DayParts | null {
  if (value === null || value === undefined || value === "") return null

  if (typeof value === "string") {
    const bare = ISO_DATE.exec(value)
    if (bare) {
      const [y, m, d] = [Number(bare[1]), Number(bare[2]), Number(bare[3])]
      return { y, m, d, weekday: new Date(Date.UTC(y, m - 1, d)).getUTCDay(), hour: null, minute: null }
    }
  }

  const date = typeof value === "string" ? new Date(value) : value
  if (Number.isNaN(date.getTime())) return null

  // Shift the instant by +3h and read UTC fields = Addis wall clock.
  const addis = new Date(date.getTime() + 3 * 3600_000)
  return {
    y: addis.getUTCFullYear(),
    m: addis.getUTCMonth() + 1,
    d: addis.getUTCDate(),
    weekday: addis.getUTCDay(),
    hour: addis.getUTCHours(),
    minute: addis.getUTCMinutes(),
  }
}

/** Amharic writes "ሐምሌ 15 ቀን 2018"; Latin scripts "Hamle 15, 2018". */
function joinDate(month: string, day: number, year: number, locale: Locale): string {
  return locale === "am" ? `${month} ${day} ቀን ${year}` : `${month} ${day}, ${year}`
}

export interface FormatDateOptions {
  /** Override the workspace calendar (e.g. a per-school row in a platform table). */
  calendar?: CalendarMode
  locale?: Locale
  /** Append the era — "E.C." / "ዓ.ም." (used on official-looking surfaces). */
  era?: boolean
  /** Prefix the weekday name. */
  weekday?: boolean
  /** Month + year only ("Hamle 2018"). */
  monthOnly?: boolean
  /** Day + month only ("Hamle 15") — compact chips, chat headers. */
  noYear?: boolean
}

// ---------------------------------------------------------------------------
// Formatters
// ---------------------------------------------------------------------------

/** The everyday date: "Hamle 15, 2018" / "ሐምሌ 15 ቀን 2018" / "Jul 22, 2026". */
export function fmtDate(value: string | Date | null | undefined, options: FormatDateOptions = {}): string {
  const parts = toParts(value)
  if (!parts) return ""

  const locale = options.locale ?? currentState.locale
  const calendar = options.calendar ?? currentState.prefs.calendar
  const weekday = options.weekday ? `${weekdayName(parts.weekday, locale)}${locale === "am" ? "፣ " : ", "}` : ""

  if (calendar === "gregorian") {
    const era = options.era ? ` ${ERA[locale].greg}` : ""
    if (options.monthOnly) return `${gregMonthName(parts.m, locale)} ${parts.y}${era}`
    if (options.noYear) return `${weekday}${gregMonthName(parts.m, locale)} ${parts.d}`
    return weekday + joinDate(gregMonthName(parts.m, locale), parts.d, parts.y, locale) + era
  }

  const eth = toEthiopian(iso(parts))
  const era = options.era ? ` ${ERA[locale].eth}` : ""
  if (options.monthOnly) return `${ethMonthName(eth.month, locale)} ${eth.year}${era}`
  if (options.noYear) return `${weekday}${ethMonthName(eth.month, locale)} ${eth.day}`
  return weekday + joinDate(ethMonthName(eth.month, locale), eth.day, eth.year, locale) + era
}

/** Weekday name alone ("ረቡዕ" / "Wednesday"), on Addis wall time. */
export function fmtWeekday(value: string | Date | null | undefined, short = false, locale?: Locale): string {
  const parts = toParts(value)
  if (!parts) return ""
  return weekdayName(parts.weekday, locale ?? currentState.locale, short)
}

/** Both calendars, Ethiopian first — for official/printable surfaces. */
export function fmtDualDate(value: string | Date | null | undefined, locale?: Locale): string {
  if (!toParts(value)) return ""
  const loc = locale ?? currentState.locale
  return `${fmtDate(value, { calendar: "ethiopian", era: true, locale: loc })} (${fmtDate(value, { calendar: "gregorian", era: true, locale: loc })})`
}

export interface FormatTimeOptions {
  clock?: ClockMode
  locale?: Locale
}

/**
 * A time of day from `HH:MM`(:SS) strings or instants:
 * standard → "8:00 AM"; ethiopian → "2:00 ጠዋት" (dawn count).
 */
export function fmtTime(value: string | Date | null | undefined, options: FormatTimeOptions = {}): string {
  let hour: number | null = null
  let minute = 0

  if (typeof value === "string") {
    const m = /^(\d{1,2}):(\d{2})/.exec(value)
    if (m && Number(m[1]) < 24 && Number(m[2]) < 60) {
      hour = Number(m[1])
      minute = Number(m[2])
    } else {
      const parts = toParts(value)
      if (parts?.hour == null) return ""
      hour = parts.hour
      minute = parts.minute ?? 0
    }
  } else {
    const parts = toParts(value)
    if (parts?.hour == null) return ""
    hour = parts.hour
    minute = parts.minute ?? 0
  }

  const locale = options.locale ?? currentState.locale
  const clock = options.clock ?? currentState.prefs.clock
  const mm = String(minute).padStart(2, "0")

  if (clock === "ethiopian") {
    const ethHour = ((hour + 5) % 12) + 1
    const period =
      hour < 6 ? DAY_PERIODS[locale].night
      : hour < 12 ? DAY_PERIODS[locale].morning
      : hour < 18 ? DAY_PERIODS[locale].afternoon
      : DAY_PERIODS[locale].evening
    return `${ethHour}:${mm} ${period}`
  }

  const h12 = hour % 12 === 0 ? 12 : hour % 12
  return `${h12}:${mm} ${hour < 12 ? "AM" : "PM"}`
}

/** Date + time in one line: "Hamle 15, 2018, 2:30 ከሰዓት". */
export function fmtDateTime(
  value: string | Date | null | undefined,
  options: FormatDateOptions & FormatTimeOptions = {},
): string {
  const date = fmtDate(value, options)
  if (!date) return ""
  const time = fmtTime(value, options)
  return time ? `${date}, ${time}` : date
}

/** Month-granularity label: "Hamle 2018" / "July 2026". */
export function fmtMonthYear(value: string | Date | null | undefined, options: FormatDateOptions = {}): string {
  return fmtDate(value, { ...options, monthOnly: true })
}

/**
 * Month name alone, in the ACTIVE calendar: "Hamle" / "ሐምሌ" / "July".
 * For chart axes and compact labels where the year is already implied by the
 * surrounding range and would only add noise.
 *
 * Names are full length, matching fmtDate — there is no abbreviated table, and
 * Ethiopian month names have no conventional short form.
 */
export function fmtMonthName(value: string | Date | null | undefined, locale?: Locale): string {
  const parts = toParts(value)
  if (!parts) return ""

  const loc = locale ?? currentState.locale

  return currentState.prefs.calendar === "gregorian"
    ? gregMonthName(parts.m, loc)
    : ethMonthName(toEthiopian(iso(parts)).month, loc)
}

/** "Hamle 1 – 30, 2018" / "Sene 25 – Hamle 2, 2018" / cross-year fallback. */
export function fmtDateRange(
  from: string | Date | null | undefined,
  to: string | Date | null | undefined,
  options: FormatDateOptions = {},
): string {
  const a = fmtDate(from, options)
  const b = fmtDate(to, options)
  if (!a) return b
  if (!b || a === b) return a
  return `${a} – ${b}`
}

/**
 * Ethiopian or Gregorian day-of-month for compact chips/calendar cells,
 * following the active calendar.
 */
export function fmtDay(value: string | Date | null | undefined, options: FormatDateOptions = {}): number | null {
  const parts = toParts(value)
  if (!parts) return null
  const calendar = options.calendar ?? currentState.prefs.calendar
  if (calendar === "gregorian") return parts.d
  return toEthiopian(iso(parts)).day
}

// ---------------------------------------------------------------------------
// Ethiopian clock arithmetic (shared with the TimePicker wheels)
// ---------------------------------------------------------------------------

export type DayPeriod = "night" | "morning" | "afternoon" | "evening"

export function dayPeriodLabel(period: DayPeriod, locale: Locale = currentState.locale): string {
  return DAY_PERIODS[locale]?.[period] ?? DAY_PERIODS.en[period]
}

/** 24h → Ethiopian dawn-count hour + day period ("8:00" → 2 morning). */
export function ethClockParts(hour24: number): { ethHour: number; period: DayPeriod } {
  const ethHour = ((hour24 + 5) % 12) + 1
  const period: DayPeriod =
    hour24 < 6 ? "night" : hour24 < 12 ? "morning" : hour24 < 18 ? "afternoon" : "evening"
  return { ethHour, period }
}

/**
 * Ethiopian hour + period → 24h. Morning/afternoon share the day half
 * (6:00–17:59), evening/night the night half — the exact period is derived
 * back from the resolved hour, mirroring how the clock is actually spoken.
 */
export function ethClockToHour24(ethHour: number, period: DayPeriod): number {
  const base = period === "morning" || period === "afternoon" ? 6 : 18
  return ((ethHour % 12) + base) % 24
}

function iso(parts: DayParts): string {
  return `${parts.y}-${String(parts.m).padStart(2, "0")}-${String(parts.d).padStart(2, "0")}`
}

/** Today as a bare ISO date on Addis wall time (school-day anchor). */
export function addisToday(): string {
  const parts = toParts(new Date())!
  return iso(parts)
}
