import type {
  RosterColumn,
  TermRosterRow,
  YearRosterMeta,
  YearRosterStudent,
} from "@/lib/types"

const esc = (v: string | number | null | undefined) =>
  `"${String(v ?? "").replaceAll('"', '""')}"`

/** Trigger a download of CSV text (UTF-8 BOM so Amharic opens right in Excel). */
export function downloadCsv(csv: string, filename: string) {
  const blob = new Blob(["\uFEFF", csv], { type: "text/csv;charset=utf-8" })
  const url = URL.createObjectURL(blob)
  const link = document.createElement("a")
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

/** The semester roster as a flat students × subjects sheet. */
export function termRosterCsv(
  columns: RosterColumn[],
  rows: TermRosterRow[],
  labels: { student: string; section: string; total: string; average: string; rank: string },
): string {
  const header = [
    "#",
    labels.student,
    "ID",
    labels.section,
    ...columns.map((c) => c.name),
    labels.total,
    labels.average,
    labels.rank,
  ]
  const lines = [header.map(esc).join(",")]
  rows.forEach((row, i) => {
    lines.push(
      [
        i + 1,
        esc(row.full_name),
        esc(row.public_id),
        esc(row.section_name),
        ...columns.map((c) => row.scores[String(c.subject_id)]?.total ?? ""),
        row.total ?? "",
        row.average ?? "",
        row.rank ?? "",
      ].join(","),
    )
  })
  return lines.join("\n")
}

/** The yearly roster: one line per (student, term) plus the year line. */
export function yearRosterCsv(
  columns: RosterColumn[],
  students: YearRosterStudent[],
  terms: YearRosterMeta["terms"],
  labels: {
    student: string
    section: string
    period: string
    total: string
    average: string
    rank: string
    yearAvg: string
  },
): string {
  const termNames = new Map(terms.map((t) => [t.id, t.name]))
  const header = [
    labels.student,
    "ID",
    labels.section,
    labels.period,
    ...columns.map((c) => c.name),
    labels.total,
    labels.average,
    labels.rank,
  ]
  const lines = [header.map(esc).join(",")]
  for (const student of students) {
    for (const term of student.terms) {
      lines.push(
        [
          esc(student.full_name),
          esc(student.public_id),
          esc(student.section_name),
          esc(termNames.get(term.term_id) ?? term.term_id),
          ...columns.map((c) => term.scores[String(c.subject_id)]?.total ?? ""),
          term.total ?? "",
          term.average ?? "",
          term.rank ?? "",
        ].join(","),
      )
    }
    lines.push(
      [
        esc(student.full_name),
        esc(student.public_id),
        esc(student.section_name),
        esc(labels.yearAvg),
        ...columns.map(() => ""),
        "",
        student.year.average ?? "",
        student.year.rank ?? "",
      ].join(","),
    )
  }
  return lines.join("\n")
}
