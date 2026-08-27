/**
 * @vitest-environment jsdom
 *
 * The rows-per-page preference is localStorage-backed, so these cases need a
 * `window` even though nothing here renders. Only the pure helpers and the
 * store are exercised — the React hook is covered by the tables that use it.
 */
import { beforeEach, describe, expect, it } from "vitest"

import {
  DEFAULT_PAGE_SIZE,
  PAGE_SIZE_OPTIONS,
  __resetPageSizeCache,
  initialPageSize,
  normalizePageSize,
  setStoredPageSize,
  storedPageSize,
} from "./use-page-size"

beforeEach(() => {
  window.localStorage.clear()
  __resetPageSizeCache()
})

describe("PAGE_SIZE_OPTIONS", () => {
  it("never offers more than the backend's per_page ceiling of 100", () => {
    expect(Math.max(...PAGE_SIZE_OPTIONS)).toBeLessThanOrEqual(100)
  })

  it("starts at the platform default", () => {
    expect(PAGE_SIZE_OPTIONS[0]).toBe(DEFAULT_PAGE_SIZE)
  })
})

describe("normalizePageSize", () => {
  it("keeps offered sizes as they are", () => {
    for (const size of PAGE_SIZE_OPTIONS) {
      expect(normalizePageSize(size)).toBe(size)
    }
  })

  it("parses stored strings", () => {
    expect(normalizePageSize("50")).toBe(50)
  })

  it("snaps an unlisted size onto the nearest option", () => {
    expect(normalizePageSize(30)).toBe(25)
    expect(normalizePageSize(60)).toBe(50)
    expect(normalizePageSize(1000)).toBe(100)
  })

  it("rounds a tie up — asking for more rows never yields fewer", () => {
    expect(normalizePageSize(37.5)).toBe(50)
  })

  it("falls back on junk, and normalizes the fallback too", () => {
    expect(normalizePageSize(null)).toBe(DEFAULT_PAGE_SIZE)
    expect(normalizePageSize("abc", 50)).toBe(50)
    expect(normalizePageSize(0, 50)).toBe(50)
    expect(normalizePageSize(undefined, 60)).toBe(50)
  })
})

describe("the stored preference", () => {
  it("is absent until the user picks one", () => {
    expect(storedPageSize()).toBeNull()
  })

  it("survives a reload", () => {
    setStoredPageSize(100)
    __resetPageSizeCache()
    expect(storedPageSize()).toBe(100)
  })

  it("wins over a table's own default once set", () => {
    expect(initialPageSize(50)).toBe(50)
    setStoredPageSize(25)
    expect(initialPageSize(50)).toBe(25)
  })

  it("normalizes a hand-edited value", () => {
    window.localStorage.setItem("temari:rows-per-page", "9999")
    __resetPageSizeCache()
    expect(storedPageSize()).toBe(100)
  })
})
