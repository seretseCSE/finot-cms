import { describe, expect, it } from "vitest"

import { addMinutes, editBlock, minutesOf, removeBlock, shiftFrom } from "@/lib/period-schedule"

/** The standard generated day, trimmed to what these tests need. */
function day() {
  return [
    { type: "class", starts_at: "08:00", ends_at: "08:45" },
    { type: "class", starts_at: "08:45", ends_at: "09:30" },
    { type: "break", starts_at: "09:30", ends_at: "09:45" },
    { type: "class", starts_at: "09:45", ends_at: "10:30" },
  ]
}

describe("minutesOf / addMinutes", () => {
  it("converts and re-formats HH:mm", () => {
    expect(minutesOf("08:45")).toBe(525)
    expect(addMinutes("08:45", 45)).toBe("09:30")
    expect(addMinutes("09:05", -20)).toBe("08:45")
  })

  it("clamps to the same day in both directions", () => {
    expect(addMinutes("23:50", 30)).toBe("23:59")
    expect(addMinutes("00:10", -30)).toBe("00:00")
  })
})

describe("editBlock", () => {
  it("starts the next block exactly where this one now ends", () => {
    const rows = editBlock(day(), 0, { ends_at: "09:00" })

    expect(rows[0]).toMatchObject({ starts_at: "08:00", ends_at: "09:00" })
    expect(rows[1].starts_at).toBe("09:00")
  })

  it("carries the whole rest of the day, keeping every block's length", () => {
    const rows = editBlock(day(), 0, { ends_at: "09:00" })

    expect(rows.map((r) => [r.starts_at, r.ends_at])).toEqual([
      ["08:00", "09:00"],
      ["09:00", "09:45"],
      ["09:45", "10:00"],
      ["10:00", "10:45"],
    ])
  })

  it("pulls the day back when a block is shortened", () => {
    const rows = editBlock(day(), 1, { ends_at: "09:15" })

    expect(rows[2]).toMatchObject({ starts_at: "09:15", ends_at: "09:30" })
    expect(rows[3]).toMatchObject({ starts_at: "09:30", ends_at: "10:15" })
  })

  it("moves a block with its own length when the start changes", () => {
    const rows = editBlock(day(), 0, { starts_at: "08:30" })

    expect(rows[0]).toMatchObject({ starts_at: "08:30", ends_at: "09:15" })
    expect(rows[1].starts_at).toBe("09:15")
  })

  it("preserves a deliberate gap rather than closing it", () => {
    const rows = editBlock(
      [
        { starts_at: "08:00", ends_at: "08:45" },
        { starts_at: "09:00", ends_at: "09:45" },
      ],
      0,
      { ends_at: "09:00" },
    )

    expect(rows[1]).toMatchObject({ starts_at: "09:15", ends_at: "10:00" })
  })

  it("leaves earlier blocks alone", () => {
    const rows = editBlock(day(), 2, { ends_at: "10:00" })

    expect(rows.slice(0, 2)).toEqual(day().slice(0, 2))
  })

  it("ripples a type change that resets the block's length", () => {
    // What the type picker does: a break becomes a 45-minute class.
    const rows = editBlock(day(), 2, { type: "class", ends_at: addMinutes("09:30", 45) })

    expect(rows[2]).toMatchObject({ type: "class", starts_at: "09:30", ends_at: "10:15" })
    expect(rows[3]).toMatchObject({ starts_at: "10:15", ends_at: "11:00" })
  })

  it("is a no-op for an index that is not there", () => {
    expect(editBlock(day(), 9, { ends_at: "12:00" })).toEqual(day())
  })
})

describe("removeBlock", () => {
  it("closes the hole the removed block leaves", () => {
    const rows = removeBlock(day(), 2)

    expect(rows).toHaveLength(3)
    expect(rows[2]).toMatchObject({ starts_at: "09:30", ends_at: "10:15" })
  })

  it("just drops the last block", () => {
    const rows = removeBlock(day(), 3)

    expect(rows.map((r) => r.ends_at)).toEqual(["08:45", "09:30", "09:45"])
  })
})

describe("shiftFrom", () => {
  it("returns the same rows when nothing moves", () => {
    const rows = day()
    expect(shiftFrom(rows, 0, 0)).toBe(rows)
  })
})
