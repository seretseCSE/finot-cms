import type { DataTableColumn } from "@/components/ui/data-table"
import type { Transcript } from "@/lib/types"

/** One flat Excel line: a student × academic-year slice of the transcript. */
export interface TranscriptExportRow {
  id: string
  student: string
  public_id: string
  year: string
  grade: string
  school: string
  branch: string
  sem1: string
  sem2: string
  annual: string
  [key: string]: string
}

export interface TranscriptExportLabels {
  student: string
  publicId: string
  year: string
  grade: string
  school: string
  branch: string
  sem1: string
  sem2: string
  annual: string
}

/**
 * Flatten batch transcripts into one row per student × year — flat and
 * re-sortable in Excel (the printable layout stays on the print page).
 */
export function transcriptExportRows(transcripts: Transcript[]): TranscriptExportRow[] {
  return transcripts.flatMap((transcript) =>
    transcript.years.map((year) => ({
      id: `${transcript.student.id}-${year.academic_year_id}`,
      student: transcript.student.full_name,
      public_id: transcript.student.public_id ?? "",
      year: year.academic_year ?? "",
      grade: year.grade_level ?? "",
      school: year.school_name ?? "",
      branch: year.branch_name ?? "",
      sem1: year.terms[0]?.average != null ? String(year.terms[0].average) : "",
      sem2: year.terms[1]?.average != null ? String(year.terms[1].average) : "",
      annual: year.annual_average != null ? String(year.annual_average) : "",
    })),
  )
}

/** Column model for the standalone exportCSV/exportExcel helpers. */
export function transcriptExportColumns(
  labels: TranscriptExportLabels,
): DataTableColumn<TranscriptExportRow>[] {
  return [
    { key: "student", label: labels.student },
    { key: "public_id", label: labels.publicId },
    { key: "year", label: labels.year },
    { key: "grade", label: labels.grade },
    { key: "school", label: labels.school },
    { key: "branch", label: labels.branch },
    { key: "sem1", label: labels.sem1 },
    { key: "sem2", label: labels.sem2 },
    { key: "annual", label: labels.annual },
  ]
}
