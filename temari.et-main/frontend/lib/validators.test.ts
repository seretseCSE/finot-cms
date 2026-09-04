import { describe, expect, it } from "vitest"

import {
  ALLOW_SAFARICOM,
  formatEthContactPhone,
  formatEthPhone,
  isEthContactPhone,
  isEthPhone,
  isPublicId,
  loginIdentifier,
  looksLikePublicId,
  normalizeEthContactPhone,
  normalizeEthPhone,
} from "./validators"

/**
 * One phone standard, mirrored from the backend's App\Support\PhoneNumber.
 * Every phone the app posts must already be in canonical local form, or an
 * account created as "+251911234567" becomes unreachable by "0911234567".
 *
 * These run with NEXT_PUBLIC_ALLOW_SAFARICOM unset — the production default,
 * where 07… numbers are rejected because the SMS provider cannot deliver
 * to Safaricom Ethiopia.
 */
describe("normalizeEthPhone", () => {
  it("reduces every accepted shape to the same canonical local number", () => {
    for (const input of [
      "0911234567",
      "+251911234567",
      "251911234567",
      "911234567",
      "0911 234 567",
      "+251 91 123 45 67",
      "0911-234-567",
      "  0911234567  ",
    ]) {
      expect(normalizeEthPhone(input), input).toBe("0911234567")
    }
  })

  it("rejects anything that is not an Ethiopian mobile number", () => {
    for (const input of [
      "",
      null,
      undefined,
      "091123456", // too short
      "09112345678", // too long
      "0811234567", // no such prefix
      "+1234567890", // another country
      "+44911234567", // + prefix that is not +251
      "0116629800", // landline — not valid as a personal mobile
      "not a phone",
    ]) {
      expect(normalizeEthPhone(input), String(input)).toBeNull()
    }
  })

  it("gates Safaricom 07… numbers behind NEXT_PUBLIC_ALLOW_SAFARICOM", () => {
    // Guards the default. If the flag is ever flipped on by default, this fails
    // loudly rather than silently letting undeliverable numbers into the DB.
    expect(ALLOW_SAFARICOM).toBe(false)
    expect(normalizeEthPhone("0711234567")).toBeNull()
    expect(normalizeEthPhone("+251711234567")).toBeNull()
    expect(isEthPhone("0711234567")).toBe(false)
  })
})

describe("normalizeEthContactPhone", () => {
  it("also accepts geographic landlines, for school office lines", () => {
    expect(normalizeEthContactPhone("+251 11 662 98 00")).toBe("0116629800")
    expect(normalizeEthContactPhone("0116629800")).toBe("0116629800")
    expect(normalizeEthContactPhone("0911234567")).toBe("0911234567")
    expect(isEthContactPhone("0116629800")).toBe(true)
  })

  it("still rejects non-Ethiopian numbers", () => {
    expect(normalizeEthContactPhone("+1234567890")).toBeNull()
    expect(normalizeEthContactPhone("0716629800")).toBeNull() // Safaricom, gated
  })
})

describe("formatting", () => {
  it("groups mobiles and landlines for display", () => {
    expect(formatEthPhone("+251911234567")).toBe("0911 234 567")
    expect(formatEthContactPhone("+251116629800")).toBe("011 662 98 00")
  })

  it("returns the raw value untouched when it cannot be parsed", () => {
    expect(formatEthPhone("nonsense")).toBe("nonsense")
    expect(formatEthPhone(null)).toBe("")
  })
})

/**
 * Student IDs are semi-public card codes. They use an unambiguous alphabet so
 * a child reading one off a card cannot confuse 0/O or 1/I.
 */
describe("isPublicId", () => {
  it("accepts six characters from the unambiguous alphabet", () => {
    expect(isPublicId("ABCDEF")).toBe(true)
    expect(isPublicId("abcdef")).toBe(true) // case-insensitive
    expect(isPublicId(" P2R4T6 ")).toBe(true)
  })

  it("rejects the ambiguous characters and the wrong length", () => {
    expect(isPublicId("ABCDE0")).toBe(false) // zero
    expect(isPublicId("ABCDEO")).toBe(false) // letter O
    expect(isPublicId("ABCDE1")).toBe(false) // one
    expect(isPublicId("ABCDEI")).toBe(false) // letter I
    expect(isPublicId("ABCDE")).toBe(false)
    expect(isPublicId("ABCDEFG")).toBe(false)
    expect(isPublicId(null)).toBe(false)
  })

  it("treats any letter as heading towards an ID, not a phone", () => {
    expect(looksLikePublicId("A")).toBe(true)
    expect(looksLikePublicId("0911")).toBe(false)
  })
})

/**
 * Sign-in is ONE field: a phone number or a student ID.
 */
describe("loginIdentifier", () => {
  it("normalises a phone to canonical local form", () => {
    expect(loginIdentifier().parse("+251911234567")).toBe("0911234567")
  })

  it("uppercases a student ID", () => {
    expect(loginIdentifier().parse(" p2r4t6 ")).toBe("P2R4T6")
  })

  it("refuses a value that is neither", () => {
    expect(() => loginIdentifier().parse("hello")).toThrow()
    expect(() => loginIdentifier().parse("0811234567")).toThrow()
  })
})
