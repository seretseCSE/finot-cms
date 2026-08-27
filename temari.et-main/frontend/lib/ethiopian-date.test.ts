import { describe, expect, it } from "vitest"

import {
  ethDaysInMonth,
  ethiopianMonthRange,
  fromEthiopian,
  toEthiopian,
} from "./ethiopian-date"

/**
 * The Ethiopian calendar is the platform's time backbone — academic years,
 * billing cycles, leave years and payroll months all key off it. A silent
 * one-day drift here would misdate every invoice in the country, so the
 * anchors below are real-world Enkutatash (Ethiopian New Year) dates rather
 * than values read back out of the implementation.
 */
describe("toEthiopian", () => {
  it("puts Meskerem 1 on the real Enkutatash date, including the leap-year shift", () => {
    // Meskerem 1 falls on Sep 11, except when the FOLLOWING Gregorian year is
    // a leap year, which pushes it to Sep 12.
    expect(toEthiopian("2022-09-11")).toEqual({ year: 2015, month: 1, day: 1 })
    expect(toEthiopian("2023-09-12")).toEqual({ year: 2016, month: 1, day: 1 }) // 2024 is leap
    expect(toEthiopian("2024-09-11")).toEqual({ year: 2017, month: 1, day: 1 })
    expect(toEthiopian("2025-09-11")).toEqual({ year: 2018, month: 1, day: 1 })
  })

  it("keeps the day before new year in the outgoing year's Pagume", () => {
    expect(toEthiopian("2023-09-11")).toEqual({ year: 2015, month: 13, day: 6 })
    expect(toEthiopian("2024-09-10")).toEqual({ year: 2016, month: 13, day: 5 })
  })

  it("maps a mid-year date to the right month", () => {
    // Hamle is the 11th Ethiopian month and lands in July.
    expect(toEthiopian("2026-07-25")).toEqual({ year: 2018, month: 11, day: 18 })
  })
})

describe("fromEthiopian", () => {
  it("is the exact inverse of toEthiopian", () => {
    expect(fromEthiopian({ year: 2015, month: 1, day: 1 })).toBe("2022-09-11")
    expect(fromEthiopian({ year: 2016, month: 1, day: 1 })).toBe("2023-09-12")
    expect(fromEthiopian({ year: 2018, month: 11, day: 18 })).toBe("2026-07-25")
  })

  it("round-trips every day across a leap boundary", () => {
    // Walks ~2.5 years of Gregorian days through both conversions. Catches
    // off-by-one drift that single-date assertions would miss.
    const start = Date.UTC(2023, 0, 1)
    for (let i = 0; i < 900; i++) {
      const d = new Date(start + i * 86_400_000).toISOString().slice(0, 10)
      expect(fromEthiopian(toEthiopian(d)), `round-trip ${d}`).toBe(d)
    }
  })

  it("rejects impossible dates rather than silently wrapping", () => {
    expect(fromEthiopian({ year: 2016, month: 14, day: 1 })).toBeNull()
    expect(fromEthiopian({ year: 2016, month: 1, day: 31 })).toBeNull()
    expect(fromEthiopian({ year: 1899, month: 1, day: 1 })).toBeNull()
    // Pagume 6 does not exist in EC 2016 (a 5-day Pagume year).
    expect(fromEthiopian({ year: 2016, month: 13, day: 6 })).toBeNull()
    expect(fromEthiopian({ year: 2015, month: 13, day: 6 })).toBe("2023-09-11")
  })
})

describe("ethDaysInMonth", () => {
  it("gives 30 days to the twelve months", () => {
    for (const month of [1, 5, 12]) {
      expect(ethDaysInMonth(2016, month)).toBe(30)
    }
  })

  it("gives Pagume 6 days only in a leap year (EC year mod 4 === 3)", () => {
    expect(ethDaysInMonth(2015, 13)).toBe(6)
    expect(ethDaysInMonth(2016, 13)).toBe(5)
    expect(ethDaysInMonth(2017, 13)).toBe(5)
    expect(ethDaysInMonth(2018, 13)).toBe(5)
    expect(ethDaysInMonth(2019, 13)).toBe(6)
  })
})

describe("ethiopianMonthRange", () => {
  it("spans exactly one Ethiopian month in Gregorian dates", () => {
    // The billing window for one Ethiopian month — recurring fees key off this.
    expect(ethiopianMonthRange(2017, 1)).toEqual({ from: "2024-09-11", to: "2024-10-10" })
    expect(ethiopianMonthRange(2017, 11)).toEqual({ from: "2025-07-08", to: "2025-08-06" })
  })

  it("closes Pagume on the day before the next new year", () => {
    expect(ethiopianMonthRange(2015, 13)).toEqual({ from: "2023-09-06", to: "2023-09-11" })
    expect(ethiopianMonthRange(2016, 13)).toEqual({ from: "2024-09-06", to: "2024-09-10" })
  })

  it("leaves no gap between consecutive months", () => {
    for (let month = 1; month < 13; month++) {
      const current = ethiopianMonthRange(2017, month)
      const next = ethiopianMonthRange(2017, month + 1)
      const dayAfter = new Date(`${current.to}T00:00:00Z`)
      dayAfter.setUTCDate(dayAfter.getUTCDate() + 1)
      expect(dayAfter.toISOString().slice(0, 10), `gap after month ${month}`).toBe(next.from)
    }
  })
})
