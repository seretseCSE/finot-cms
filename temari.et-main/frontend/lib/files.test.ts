import { readFileSync } from "node:fs"
import { dirname, join } from "node:path"
import { fileURLToPath } from "node:url"

import { describe, expect, it } from "vitest"

import { COURSEWORK_ACCEPT, downloadFileName, fileKind } from "./files"

/**
 * The accept list a picker offers and the `mimes:` list the endpoint enforces
 * are two halves of one contract. When they drift the user pays for it late:
 * the file looks accepted, they write the whole assignment, and the save
 * bounces. This test parses the PHP constant and demands the two agree.
 *
 * When it fails, update whichever side is behind — never loosen the test.
 */
const here = dirname(fileURLToPath(import.meta.url))
const PHP_FILE = join(here, "..", "..", "backend", "app", "Support", "CourseworkFiles.php")

function phpExtensions(): string[] {
  const source = readFileSync(PHP_FILE, "utf8")
  const block = /const EXTENSIONS = \[([\s\S]*?)\];/.exec(source)
  if (!block) throw new Error("CourseworkFiles::EXTENSIONS not found — did the constant move?")
  return [...block[1].matchAll(/'([a-z0-9]+)'/g)].map((match) => match[1])
}

describe("coursework upload types", () => {
  it("offers exactly what the endpoint accepts", () => {
    const client = COURSEWORK_ACCEPT.split(",").map((entry) => entry.trim().replace(/^\./, ""))
    expect([...client].sort()).toEqual([...phpExtensions()].sort())
  })

  it("takes the spreadsheet and text formats teachers actually hand out", () => {
    for (const ext of ["csv", "txt", "xlsx", "ods", "odt", "pdf"]) {
      expect(COURSEWORK_ACCEPT).toContain(`.${ext}`)
    }
  })

  it("never offers a type the browser would execute from a signed link", () => {
    for (const ext of ["svg", "html", "htm", "js", "exe", "apk", "sh"]) {
      expect(COURSEWORK_ACCEPT).not.toContain(`.${ext}`)
    }
  })
})

describe("fileKind", () => {
  it("sorts the coursework mime types into the right icon buckets", () => {
    expect(fileKind("text/csv")).toBe("sheet")
    expect(fileKind("application/vnd.oasis.opendocument.spreadsheet")).toBe("sheet")
    expect(fileKind("text/plain")).toBe("doc")
    expect(fileKind("application/pdf")).toBe("pdf")
    expect(fileKind("image/heic")).toBe("image")
    expect(fileKind("audio/amr")).toBe("audio")
    expect(fileKind("video/webm")).toBe("video")
    expect(fileKind("application/zip")).toBe("archive")
    expect(fileKind(null)).toBe("other")
  })
})

describe("downloadFileName", () => {
  it("restores the extension stripped at upload, and never doubles it", () => {
    expect(downloadFileName({ name: "Term 1 marks", url: null, mime_type: "text/csv" })).toBe(
      "Term 1 marks.csv",
    )
    expect(downloadFileName({ name: "Term 1 marks.csv", url: null, mime_type: "text/csv" })).toBe(
      "Term 1 marks.csv",
    )
  })
})
