import { readdirSync, readFileSync, statSync } from "node:fs"
import { dirname, join, relative } from "node:path"
import { fileURLToPath } from "node:url"

import { describe, expect, it } from "vitest"

/**
 * `useTranslation(...).t()` falls back to the KEY when a key is missing, so a
 * typo or a forgotten translation does not crash — it silently renders
 * "messages.saved" or "notifications.categories.tutoring" in the middle of the
 * UI. Nothing else in the toolchain catches that.
 *
 * This test is the guard. It reads every `t("…")` call back out of the source,
 * resolves it against the dictionary of the domain that `t` was bound to, and
 * fails on anything that would render as a raw key. It also keeps the three
 * languages at identical key coverage, and pins the notification categories to
 * the backend catalog (the one category list the server, not the client, owns).
 *
 * When it fails, add the missing copy in all three languages — never loosen the
 * test or delete the call site's key.
 */

const here = dirname(fileURLToPath(import.meta.url))
const APP = join(here, "..")
const I18N = join(here, "i18n")
const LOCALES = ["en", "am", "om"] as const
const FALLBACK = "en"

/** Domain name as written in `useTranslation("x")` → its JSON file base. */
const DOMAIN_FILE: Record<string, string> = {
  common: "common",
  auth: "auth",
  schools: "schools",
  academic: "academic",
  students: "students",
  employees: "employees",
  attendance: "attendance",
  devices: "devices",
  fees: "fees",
  users: "users",
  payroll: "payroll",
  hr: "hr",
  inventory: "inventory",
  docs: "docs",
  me: "me",
  promotion: "promotion",
  transfers: "transfers",
  timetable: "timetable",
  catalogs: "catalogs",
  grading: "grading",
  lms: "lms",
  chat: "chat",
  lessonPlans: "lesson-plans",
  tutoring: "tutoring",
  ai: "ai",
}

type Dict = Record<string, unknown>

function load(locale: string, domain: string): Dict {
  return JSON.parse(readFileSync(join(I18N, locale, `${DOMAIN_FILE[domain]}.json`), "utf8"))
}

function resolve(dict: Dict, key: string): unknown {
  return key
    .split(".")
    .reduce<unknown>(
      (acc, part) =>
        acc && typeof acc === "object" && part in acc
          ? (acc as Record<string, unknown>)[part]
          : undefined,
      dict,
    )
}

function flatten(value: unknown, prefix = "", out: string[] = []): string[] {
  if (value && typeof value === "object" && !Array.isArray(value)) {
    for (const [key, child] of Object.entries(value)) flatten(child, `${prefix}${key}.`, out)
  } else {
    out.push(prefix.slice(0, -1))
  }
  return out
}

/** Every .ts/.tsx file under the app, excluding build output and deps. */
function sourceFiles(dir: string, out: string[] = []): string[] {
  for (const entry of readdirSync(dir)) {
    if (entry === "node_modules" || entry === ".next" || entry === ".git") continue
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) sourceFiles(full, out)
    else if (/\.tsx?$/.test(entry) && !/\.test\.tsx?$/.test(entry)) out.push(full)
  }
  return out
}

/** `const { t, t: tc } = useTranslation("common")` → { t: "common", tc: "common" } */
function boundTranslators(source: string): Record<string, string> {
  const bound: Record<string, string> = {}
  const hook = /const\s*\{([^}]*)\}\s*=\s*useTranslation\(\s*["'`](\w+)["'`]\s*\)/g

  for (const match of source.matchAll(hook)) {
    for (const part of match[1].split(",")) {
      const [original, alias] = part.split(":").map((piece) => piece.trim())
      if (original === "t") bound[alias || "t"] = match[2]
    }
  }

  return bound
}

describe("i18n keys", () => {
  it("every t() call with a literal key resolves to copy", () => {
    const dicts = new Map(Object.keys(DOMAIN_FILE).map((d) => [d, load(FALLBACK, d)]))
    const broken: string[] = []

    for (const file of sourceFiles(APP)) {
      const source = readFileSync(file, "utf8")
      if (!source.includes("useTranslation")) continue

      const bound = boundTranslators(source)
      const names = Object.keys(bound).sort((a, b) => b.length - a.length)
      if (names.length === 0) continue

      const lines = source.split("\n")
      for (const [index, line] of lines.entries()) {
        for (const name of names) {
          // A literal key only — template keys interpolate a value this test
          // cannot know, and are covered by the enum-parity guard instead.
          const call = new RegExp(`(?<![\\w.$])${name}\\(\\s*"([^"\\\\]+)"`, "g")
          for (const match of line.matchAll(call)) {
            const key = match[1]
            if (typeof resolve(dicts.get(bound[name])!, key) === "string") continue
            broken.push(`${relative(APP, file)}:${index + 1} — ${bound[name]}: ${key}`)
          }
        }
      }
    }

    expect(broken).toEqual([])
  })

  it("all three languages carry the same keys", () => {
    const drift: string[] = []

    for (const domain of Object.keys(DOMAIN_FILE)) {
      const base = flatten(load(FALLBACK, domain))
      for (const locale of LOCALES) {
        if (locale === FALLBACK) continue
        const other = new Set(flatten(load(locale, domain)))
        const baseSet = new Set(base)
        for (const key of base) if (!other.has(key)) drift.push(`${locale}/${domain}: missing ${key}`)
        for (const key of other) if (!baseSet.has(key)) drift.push(`${locale}/${domain}: extra ${key}`)
      }
    }

    expect(drift).toEqual([])
  })

  it("notification categories match the backend catalog", () => {
    const catalog = readFileSync(
      join(APP, "..", "backend", "app", "Support", "NotificationCatalog.php"),
      "utf8",
    )
    const block = catalog.match(/public const CATEGORIES = \[([\s\S]*?)\];/)
    expect(block).not.toBeNull()
    const backend = [...block![1].matchAll(/'([a-z_]+)'/g)].map((m) => m[1])

    // The label map every category chip, filter pill and settings row reads.
    for (const locale of LOCALES) {
      const categories = resolve(load(locale, "common"), "notifications.categories") as Dict
      expect(Object.keys(categories).sort(), locale).toEqual([...backend].sort())
    }

    // The client union that types the same values.
    const types = readFileSync(join(here, "types.ts"), "utf8")
    const union = types.match(/export type NotificationCategory =([\s\S]*?)\n\n/)
    expect(union).not.toBeNull()
    const client = [...union![1].matchAll(/"([a-z_]+)"/g)].map((m) => m[1])
    expect(client.sort()).toEqual([...backend].sort())
  })
})
