/**
 * Minimal Ethiopian (Ge'ez) calendar helpers — the client-side twin of the
 * backend's App\Support\EthiopianDate, anchored on the same rule: Ethiopian
 * new year (Meskerem 1) falls on Sep 11, or Sep 12 when the FOLLOWING
 * Gregorian year is a leap year. Months are 30 days + Pagume. Valid
 * 1900–2099. Dates are plain `YYYY-MM-DD` strings, no timezone maths.
 */

export interface EthiopianDateParts {
  year: number
  month: number // 1–13
  day: number
}

function isGregorianLeap(year: number): boolean {
  return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0
}

function iso(y: number, m: number, d: number): string {
  return `${y}-${String(m).padStart(2, "0")}-${String(d).padStart(2, "0")}`
}

function addDays(isoDate: string, days: number): string {
  const [y, m, d] = isoDate.split("-").map(Number)
  const dt = new Date(Date.UTC(y, m - 1, d + days))
  return iso(dt.getUTCFullYear(), dt.getUTCMonth() + 1, dt.getUTCDate())
}

function diffDays(fromIso: string, toIso: string): number {
  const [fy, fm, fd] = fromIso.split("-").map(Number)
  const [ty, tm, td] = toIso.split("-").map(Number)
  return Math.round((Date.UTC(ty, tm - 1, td) - Date.UTC(fy, fm - 1, fd)) / 86_400_000)
}

/** Gregorian date of Meskerem 1 for the Ethiopian year starting in `gregorianYear`. */
function meskerem1(gregorianYear: number): string {
  return iso(gregorianYear, 9, isGregorianLeap(gregorianYear + 1) ? 12 : 11)
}

/** Gregorian date of Ethiopian new year's day for Ethiopian year `year`. */
function newYearOf(year: number): string {
  // EC 2017 begins in GC 2024 — the offset is a constant 7.
  return meskerem1(year + 7)
}

/** Convert a Gregorian `YYYY-MM-DD` to Ethiopian (year, month, day). */
export function toEthiopian(isoDate: string): EthiopianDateParts {
  const gy = Number(isoDate.slice(0, 4))
  let year = gy - 8
  if (isoDate >= newYearOf(year + 1)) year += 1
  const offset = diffDays(newYearOf(year), isoDate)
  return { year, month: Math.floor(offset / 30) + 1, day: (offset % 30) + 1 }
}

/** Convert Ethiopian (year, month 1–13, day) to a Gregorian `YYYY-MM-DD`. */
export function fromEthiopian(parts: EthiopianDateParts): string | null {
  const { year, month, day } = parts
  if (year < 1900 || year > 2099 || month < 1 || month > 13 || day < 1 || day > 30) return null
  if (month === 13 && day > ethDaysInMonth(year, 13)) return null
  return addDays(newYearOf(year), (month - 1) * 30 + (day - 1))
}

/** 30 for the twelve months; 5 or 6 for Pagume. */
export function ethDaysInMonth(year: number, month: number): number {
  if (month < 13) return 30
  return diffDays(newYearOf(year), newYearOf(year + 1)) - 360
}

/** Gregorian window of one Ethiopian month, as ISO dates. */
export function ethiopianMonthRange(year: number, month: number): { from: string; to: string } {
  const start = addDays(newYearOf(year), (month - 1) * 30)
  const days = month < 13 ? 30 : diffDays(newYearOf(year), newYearOf(year + 1)) - 360
  return { from: start, to: addDays(start, days - 1) }
}
