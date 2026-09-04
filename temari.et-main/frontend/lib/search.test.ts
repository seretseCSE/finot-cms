import { describe, expect, it } from "vitest"

import { matchesSearch, searchWords } from "./search"

describe("searchWords", () => {
  it("splits on any run of whitespace and lowercases", () => {
    expect(searchWords("  Abdi   Fikre Gemeda ")).toEqual(["abdi", "fikre", "gemeda"])
  })

  it("treats a blank query as no words", () => {
    expect(searchWords("   ")).toEqual([])
  })
})

describe("matchesSearch", () => {
  // The regression this whole rule exists for: names live in three columns,
  // so a full name matches none of them on its own.
  const student = ["Abdi", "Fikre", "Gemeda", "0911223344", "TMR-4821"]

  it("finds a record by its full name across separate fields", () => {
    expect(matchesSearch(student, "Abdi Fikre Gemeda")).toBe(true)
  })

  it("accepts the words in any order", () => {
    expect(matchesSearch(student, "gemeda abdi")).toBe(true)
  })

  it("still matches a single word", () => {
    expect(matchesSearch(student, "abdi")).toBe(true)
  })

  it("mixes a name with a phone or an id", () => {
    expect(matchesSearch(student, "Abdi 0911")).toBe(true)
    expect(matchesSearch(student, "tmr-4821 gemeda")).toBe(true)
  })

  it("rejects a query whose every word is not present", () => {
    expect(matchesSearch(student, "Abdi Kebede")).toBe(false)
  })

  it("matches a phrase that lives whole inside one field", () => {
    expect(matchesSearch(["Bole Main Campus"], "Bole Main")).toBe(true)
    expect(matchesSearch(["Bole Main Campus"], "Campus Bole")).toBe(true)
  })

  it("matches everything on a blank query", () => {
    expect(matchesSearch(student, "  ")).toBe(true)
  })

  it("stringifies nullish and non-string values instead of throwing", () => {
    expect(matchesSearch([null, undefined, 42, "Abebe"], "42 abebe")).toBe(true)
  })
})
