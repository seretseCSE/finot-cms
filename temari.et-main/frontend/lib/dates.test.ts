import { afterEach, describe, expect, it } from "vitest"

import {
  ethClockParts,
  ethClockToHour24,
  fmtDate,
  fmtDateTime,
  fmtDualDate,
  fmtMonthName,
  fmtTime,
  fmtWeekday,
  setCalendarPrefs,
  setDateLocale,
  weekdayName,
} from "./dates"

/**
 * The display layer. Storage and the wire stay Gregorian ISO + 24h UTC
 * everywhere; these functions are the ONLY place that decides how a date or
 * time is written for a human. Anything that formats a date without going
 * through here silently ignores the school's calendar_mode / clock_mode —
 * which is why eslint bans Intl.DateTimeFormat and toLocaleDateString outside
 * this module.
 */
afterEach(() => {
  setCalendarPrefs({ calendar: "ethiopian", clock: "ethiopian" })
  setDateLocale("en")
})

describe("fmtDate", () => {
  it("writes the Ethiopian calendar by default", () => {
    setCalendarPrefs({ calendar: "ethiopian" })
    expect(fmtDate("2026-07-25")).toBe("Hamle 18, 2018")
  })

  it("writes Gregorian when the school chose that mode", () => {
    setCalendarPrefs({ calendar: "gregorian" })
    expect(fmtDate("2026-07-25")).toBe("July 25, 2026")
  })

  it("honours the same ISO input in Amharic", () => {
    setCalendarPrefs({ calendar: "ethiopian" })
    setDateLocale("am")
    expect(fmtDate("2026-07-25")).toContain("ሐምሌ")
  })

  it("drops the year on request but keeps the month and day", () => {
    expect(fmtDate("2026-07-25", { noYear: true })).toBe("Hamle 18")
  })

  it("returns an empty string for missing values instead of throwing", () => {
    expect(fmtDate(null)).toBe("")
    expect(fmtDate(undefined)).toBe("")
    expect(fmtDate("not a date")).toBe("")
  })

  it("never shifts a bare ISO date across a day boundary", () => {
    // A bare date has no timezone. Parsing it as an instant would land on the
    // previous day for anyone behind UTC — the classic off-by-one.
    setCalendarPrefs({ calendar: "gregorian" })
    expect(fmtDate("2026-01-01")).toBe("January 1, 2026")
    expect(fmtDate("2026-12-31")).toBe("December 31, 2026")
  })
})

describe("fmtDualDate", () => {
  it("prints both calendars, Ethiopian first — the official-document format", () => {
    expect(fmtDualDate("2026-07-25")).toBe("Hamle 18, 2018 E.C. (July 25, 2026 G.C.)")
  })
})

/**
 * Ethiopian clock: the day is counted from dawn, so 8:00 AM is spoken as
 * "2:00 in the morning".
 */
describe("fmtTime", () => {
  it("counts hours from dawn in Ethiopian mode", () => {
    setCalendarPrefs({ clock: "ethiopian" })
    expect(fmtTime("08:00")).toBe("2:00 morning")
    expect(fmtTime("06:00")).toBe("12:00 morning") // dawn itself
    expect(fmtTime("12:00")).toBe("6:00 afternoon") // noon
    expect(fmtTime("18:00")).toBe("12:00 evening") // dusk
    expect(fmtTime("00:00")).toBe("6:00 night") // midnight
  })

  it("writes a standard 12-hour clock when the school chose that mode", () => {
    setCalendarPrefs({ clock: "standard" })
    expect(fmtTime("08:00")).toBe("8:00 AM")
    expect(fmtTime("12:00")).toBe("12:00 PM")
    expect(fmtTime("00:00")).toBe("12:00 AM")
    expect(fmtTime("18:30")).toBe("6:30 PM")
  })

  it("returns an empty string for values with no time in them", () => {
    expect(fmtTime("25:00")).toBe("")
    expect(fmtTime(null)).toBe("")
    expect(fmtTime("")).toBe("")
    expect(fmtTime("not a time")).toBe("")
    // A bare ISO date carries no clock — rendering "12:00 night" would be a lie.
    expect(fmtTime("2026-07-25")).toBe("")
  })
})

describe("ethClockParts / ethClockToHour24", () => {
  it("round-trips every hour of the day", () => {
    for (let hour = 0; hour < 24; hour++) {
      const { ethHour, period } = ethClockParts(hour)
      expect(ethClockToHour24(ethHour, period), `hour ${hour}`).toBe(hour)
    }
  })

  it("names the periods the way the clock is spoken", () => {
    expect(ethClockParts(8)).toEqual({ ethHour: 2, period: "morning" })
    expect(ethClockParts(14)).toEqual({ ethHour: 8, period: "afternoon" })
    expect(ethClockParts(20)).toEqual({ ethHour: 2, period: "evening" })
    expect(ethClockParts(2)).toEqual({ ethHour: 8, period: "night" })
  })
})

describe("fmtDateTime", () => {
  it("joins the active calendar and the active clock", () => {
    setCalendarPrefs({ calendar: "ethiopian", clock: "ethiopian" })
    // 08:00 UTC is 11:00 in Addis, which is the 5th hour of the Ethiopian day.
    expect(fmtDateTime("2026-07-25T08:00:00Z")).toBe("Hamle 18, 2018, 5:00 morning")
  })
})

describe("weekday helpers", () => {
  it("indexes weekdays from Sunday, matching getUTCDay()", () => {
    expect(weekdayName(0, "en")).toBe("Sunday")
    expect(weekdayName(6, "en", true)).toBe("Sat")
  })

  it("reads the weekday off a date", () => {
    // 2026-07-25 is a Saturday.
    expect(fmtWeekday("2026-07-25", true, "en")).toBe("Sat")
  })
})

describe("fmtMonthName", () => {
  it("gives the month name alone in the active calendar", () => {
    setCalendarPrefs({ calendar: "ethiopian" })
    expect(fmtMonthName("2026-07-15")).toBe("Hamle")

    setCalendarPrefs({ calendar: "gregorian" })
    expect(fmtMonthName("2026-07-15")).toBe("July")
  })
})
