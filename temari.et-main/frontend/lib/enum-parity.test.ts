import { readdirSync, readFileSync } from "node:fs"
import { dirname, join } from "node:path"
import { fileURLToPath } from "node:url"

import { describe, expect, it } from "vitest"

/**
 * `lib/types.ts` is 4,000+ hand-maintained lines mirroring the backend's API
 * shapes. Most of it cannot be checked mechanically — but the string-union
 * types that mirror a PHP enum can, and those are the ones where drift is both
 * most likely and most damaging: add a case to a PHP enum, forget the TS union,
 * and the frontend silently mishandles a status it has never heard of (a filter
 * that drops rows, a switch that falls through to a blank label).
 *
 * This test is the guard. It parses both sides and demands they agree.
 *
 * When it fails, do not "fix" it by loosening the test — update whichever side
 * is behind. If a divergence is deliberate, add it to KNOWN_DIVERGENCES with
 * the reason.
 */

const here = dirname(fileURLToPath(import.meta.url))
const ENUM_DIR = join(here, "..", "..", "backend", "app", "Enums")
const TYPES_FILE = join(here, "types.ts")

/** PHP enum name → the TS type that mirrors it under a different name. */
const ALIASES: Record<string, string> = {
  LessonStage: "LessonStageKey",
}

/**
 * PHP enums with no TS union to compare against, and why. Each entry is a
 * deliberate decision, not a backlog item to ignore — but several ARE latent
 * drift risk, and are marked as such.
 */
const KNOWN_DIVERGENCES: Record<string, string> = {
  // Deliberate — the frontend must not reason about these.
  Role: "Role is an open string on the client. Authority is decided server-side by the kernel; memberships carry role_label for display.",
  RoleScope: "Read off the membership object, never matched as a literal.",
  DocumentCategory: "Client categories live in lib/document-categories.ts with icons and copy attached.",

  // Inlined on an interface field rather than given a named alias. Still typed,
  // so still safe — just not reachable by this test's parser.
  AssignmentStatus: "Inlined as a field union on the assignment interface.",
  PaymentVerificationStatus: "Inlined as a field union on the verification interface.",
  AbsenceExcuseStatus: "Inlined as a field union on the excuse interface.",
  SubmissionStatus: "Inlined as a field union on the submission interface.",
  TransferApplicationStatus: "Inlined as a field union on the transfer-application interface.",
  TutorStatus: "Inlined as a field union on the tutor-profile interface.",

  // NOT yet typed on the client at all — the tutoring marketplace and payment
  // gateway are v2 surfaces with little frontend. Give these a real union when
  // those screens get built; until then there is nothing to keep in sync.
  CycleStatus: "v2 tutoring escrow — no client surface types this yet.",
  EngagementStatus: "v2 tutoring escrow — no client surface types this yet.",
  TutoringRequestStatus: "v2 tutoring marketplace — no client surface types this yet.",
  TutoringSessionStatus: "v2 tutoring marketplace — no client surface types this yet.",
  GatewayPurpose: "Gateway screens are operator-only and untyped on the client so far.",
  GatewayTransactionStatus: "Gateway screens are operator-only and untyped on the client so far.",
  PayoutStatus: "Tutor payouts are operator-only and untyped on the client so far.",
}

function phpEnums(): Map<string, string[]> {
  const out = new Map<string, string[]>()

  for (const file of readdirSync(ENUM_DIR).filter((f) => f.endsWith(".php"))) {
    const source = readFileSync(join(ENUM_DIR, file), "utf8")
    const values = [...source.matchAll(/^\s*case\s+\w+\s*=\s*'([^']*)'\s*;/gm)].map((m) => m[1])
    if (values.length > 0) out.set(file.replace(/\.php$/, ""), values)
  }

  return out
}

function tsUnions(): Map<string, string[]> {
  const source = readFileSync(TYPES_FILE, "utf8")
  const out = new Map<string, string[]>()

  // Matches both the single-line and the pipe-per-line prettier formats, and
  // stops at the first line that is not part of the union.
  const re = /^export type (\w+) =\s*((?:\s*\|?\s*"[^"]*")+)\s*$/gm

  for (const match of source.matchAll(re)) {
    const values = [...match[2].matchAll(/"([^"]*)"/g)].map((m) => m[1])
    out.set(match[1], values)
  }

  return out
}

describe("backend enum ↔ frontend union parity", () => {
  const php = phpEnums()
  const ts = tsUnions()

  it("finds enums on both sides (the parser still works)", () => {
    // Guards against a format change silently turning this whole suite into a
    // no-op that passes because it compared nothing.
    expect(php.size).toBeGreaterThan(40)
    expect(ts.size).toBeGreaterThan(40)
  })

  const tsNameFor = (phpName: string) => ALIASES[phpName] ?? phpName

  const comparable = [...php.keys()]
    .filter((name) => !(name in KNOWN_DIVERGENCES))
    .filter((name) => ts.has(tsNameFor(name)))

  it("compares a meaningful number of enums", () => {
    expect(comparable.length).toBeGreaterThan(25)
  })

  it.each(comparable)("%s matches the PHP enum exactly", (name) => {
    const backend = [...php.get(name)!].sort()
    const frontend = [...ts.get(tsNameFor(name))!].sort()

    expect(
      frontend,
      `lib/types.ts ${tsNameFor(name)} has drifted from backend App\\Enums\\${name}`,
    ).toEqual(backend)
  })

  it("every PHP enum is either mirrored or explicitly excused", () => {
    const unaccounted = [...php.keys()].filter(
      (name) => !ts.has(tsNameFor(name)) && !(name in KNOWN_DIVERGENCES),
    )

    expect(
      unaccounted,
      "These backend enums have no frontend union and no entry in KNOWN_DIVERGENCES. Either mirror them in lib/types.ts or record why they are server-only.",
    ).toEqual([])
  })

  it("has no KNOWN_DIVERGENCES entry that is actually mirrored", () => {
    // Stops the excuse list from quietly becoming a place things go to die: if
    // someone adds the union later, this tells them to delete the excuse and
    // let the parity check take over.
    const nowMirrored = Object.keys(KNOWN_DIVERGENCES).filter(
      (name) => php.has(name) && ts.has(tsNameFor(name)),
    )

    expect(
      nowMirrored,
      "These enums now HAVE a frontend union — remove them from KNOWN_DIVERGENCES so they get checked.",
    ).toEqual([])
  })

  it("has no stale KNOWN_DIVERGENCES entries", () => {
    const stale = Object.keys(KNOWN_DIVERGENCES).filter((name) => !php.has(name))

    expect(stale, "These excused enums no longer exist in the backend — drop them.").toEqual([])
  })
})
