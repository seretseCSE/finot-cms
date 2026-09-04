import type { StudentImportRow } from "@/lib/types"

/**
 * SheetJS glue for the import studio. The library (~400KB) is dynamically
 * imported so the students register never pays for it — only the studio does,
 * and only once a file is actually touched.
 */

export interface ParsedSheet {
  headers: string[]
  /** Raw cell matrix, header row excluded. Numbers preserved (Excel dates). */
  rows: unknown[][]
}

async function sheetjs() {
  return import("xlsx")
}

/** Parse the first worksheet of an .xlsx/.xls/.csv file into headers + rows. */
export async function parseSpreadsheet(file: File): Promise<ParsedSheet> {
  const XLSX = await sheetjs()
  const workbook = XLSX.read(await file.arrayBuffer(), { type: "array" })
  const sheet = workbook.Sheets[workbook.SheetNames[0]]
  if (!sheet) return { headers: [], rows: [] }

  const matrix: unknown[][] = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: "", raw: true })

  // The header row is the first row with at least two non-empty cells —
  // school files often start with a title/logo row.
  const headerIndex = matrix.findIndex(
    (row) => row.filter((cell) => String(cell ?? "").trim() !== "").length >= 2,
  )
  if (headerIndex === -1) return { headers: [], rows: [] }

  const headers = matrix[headerIndex].map((cell) => String(cell ?? "").trim())
  const rows = matrix
    .slice(headerIndex + 1)
    .filter((row) => row.some((cell) => String(cell ?? "").trim() !== ""))

  return { headers, rows }
}

/** Build and download the localized import template. */
export async function downloadTemplate(headers: string[], example: string[], fileName: string) {
  const XLSX = await sheetjs()
  const sheet = XLSX.utils.aoa_to_sheet([headers, example])
  sheet["!cols"] = headers.map((header) => ({ wch: Math.max(14, header.length + 2) }))
  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, sheet, "Students")
  XLSX.writeFile(workbook, fileName)
}

/** Download the not-imported rows as a fix-and-re-upload workbook. */
export async function downloadFailedRows(
  headers: string[],
  rows: StudentImportRow[],
  fileName: string,
) {
  const XLSX = await sheetjs()
  const matrix = [
    [...headers, "Error"],
    ...rows.map((row) => {
      const p = row.payload
      const guardian = p.guardians?.[0]
      const name = [guardian?.first_name, guardian?.father_name, guardian?.grandfather_name]
        .filter(Boolean)
        .join(" ")
      return [
        p.first_name ?? "", p.father_name ?? "", p.grandfather_name ?? "", p.mother_name ?? "",
        p.gender ?? "", p.date_of_birth ?? "", p.primary_phone ?? "",
        name, guardian?.phone ?? "", guardian?.relationship ?? "",
        row.error ?? row.issues.map((issue) => issue.message).join("; "),
      ]
    }),
  ]
  const sheet = XLSX.utils.aoa_to_sheet(matrix)
  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, sheet, "Fix these rows")
  XLSX.writeFile(workbook, fileName)
}

/** Column headers of the failed-rows workbook (matches the template's core). */
export const FAILED_EXPORT_HEADERS = [
  "First Name", "Father Name", "Grandfather Name", "Mother Name",
  "Gender", "Date of Birth", "Student Phone",
  "Guardian Name", "Guardian Phone", "Relationship",
]
