"use client"

import { ClipboardList, FileBadge, ScrollText } from "lucide-react"
import { useMemo } from "react"

import { MarkCell, RankChip } from "@/components/grading/roster-matrix"
import { meanScores } from "@/components/grading/yearly-roster"
import {
  DataTable,
  type DataTableBulkAction,
  type DataTableColumn,
} from "@/components/ui/data-table"
import { useTranslation } from "@/lib/i18n"
import type {
  RosterColumn,
  YearRosterMeta,
  YearRosterStudent,
} from "@/lib/types"

export type YearlyRow = YearRosterStudent & { id: number }

/** Compact per-term column labels: quarters Q1…Qn, semesters S1…Sn. */
export function termShortLabels(terms: YearRosterMeta["terms"]): Map<number, string> {
  const labels = new Map<number, string>()
  let quarters = 0
  let semesters = 0
  for (const term of terms) {
    labels.set(term.id, term.is_quarter ? `Q${++quarters}` : `S${++semesters}`)
  }
  return labels
}

/**
 * The yearly roster as a register: one selectable row per student — subject
 * columns carry the YEAR mean, one compact column per term carries that
 * term's average + rank, then the year average/rank. The full per-term
 * subject grid lives one tap away in the extra-assessment surface and on the
 * printed yearly card itself.
 */
export function YearlyRosterTable({
  columns,
  students,
  terms,
  showSection,
  onExtras,
  onPrint,
  onTranscript,
  onBulkPrint,
  onBulkTranscripts,
}: {
  columns: RosterColumn[]
  students: YearRosterStudent[]
  terms: YearRosterMeta["terms"]
  showSection: boolean
  onExtras: (row: YearRosterStudent) => void
  onPrint: (rows: YearRosterStudent[]) => void
  onTranscript: (row: YearRosterStudent) => void
  onBulkPrint: (rows: YearRosterStudent[]) => void
  onBulkTranscripts: (rows: YearRosterStudent[]) => void
}) {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")

  const shortLabels = useMemo(() => termShortLabels(terms), [terms])

  const data: YearlyRow[] = useMemo(
    () => students.map((s) => ({ ...s, id: s.student_id })),
    [students],
  )

  // student_id → subject year means, computed once per dataset.
  const yearMeans = useMemo(() => {
    const map = new Map<number, ReturnType<typeof meanScores>>()
    for (const student of students) {
      map.set(student.student_id, meanScores(columns, student.terms))
    }
    return map
  }, [students, columns])

  const tableColumns: DataTableColumn<YearlyRow>[] = useMemo(() => {
    const cols: DataTableColumn<YearlyRow>[] = [
      {
        key: "full_name",
        label: t("rosters.student"),
        primary: true,
        render: (row) => (
          <div className="min-w-0 max-w-56">
            <p className="truncate font-medium">{row.full_name ?? "—"}</p>
            {row.public_id && (
              <p className="text-muted-foreground text-[11px]">{row.public_id}</p>
            )}
          </div>
        ),
        exportValue: (row) => row.full_name ?? "",
      },
    ]

    if (showSection) {
      cols.push({
        key: "section_name",
        label: t("reportCards.section"),
        className: "whitespace-nowrap text-xs",
        exportValue: (row) => row.section_name ?? "",
      })
    }

    // Subject columns: the year mean (what the printed card's Year Avg shows).
    for (const col of columns) {
      cols.push({
        key: `subject_${col.subject_id}`,
        label: col.code ?? col.name,
        className: "text-center",
        mobileHidden: true,
        render: (row) => (
          <MarkCell cell={yearMeans.get(row.student_id)?.[String(col.subject_id)]} />
        ),
        sortValue: (row) =>
          yearMeans.get(row.student_id)?.[String(col.subject_id)]?.total ?? null,
        exportValue: (row) => {
          const cell = yearMeans.get(row.student_id)?.[String(col.subject_id)]
          return cell?.total != null ? String(cell.total) : ""
        },
      })
    }

    // One compact column per term: that term's average (+ tiny rank).
    for (const term of terms) {
      cols.push({
        key: `term_${term.id}`,
        label: shortLabels.get(term.id) ?? term.name,
        className: "text-center",
        render: (row) => {
          const line = row.terms.find((x) => x.term_id === term.id)
          if (!line) return <span className="text-muted-foreground">—</span>
          return (
            <span className="tabular-nums">
              {line.average ?? "—"}
              {line.rank !== null && (
                <span className="text-muted-foreground ml-1 text-[10px]">#{line.rank}</span>
              )}
            </span>
          )
        },
        sortValue: (row) => row.terms.find((x) => x.term_id === term.id)?.average ?? null,
        exportValue: (row) => {
          const line = row.terms.find((x) => x.term_id === term.id)
          return line?.average != null ? String(line.average) : ""
        },
      })
    }

    cols.push(
      {
        key: "year_average",
        label: t("rosters.yearAvg"),
        className: "text-right",
        render: (row) => (
          <span className="font-semibold tabular-nums">{row.year.average ?? "—"}</span>
        ),
        sortValue: (row) => row.year.average,
        exportValue: (row) => (row.year.average != null ? String(row.year.average) : ""),
      },
      {
        key: "year_rank",
        label: t("rosters.rank"),
        className: "text-center",
        render: (row) => <RankChip rank={row.year.rank} rankOf={row.year.rank_of} />,
        sortValue: (row) => row.year.rank,
        exportValue: (row) => (row.year.rank != null ? String(row.year.rank) : ""),
      },
    )

    return cols
  }, [t, columns, terms, showSection, shortLabels, yearMeans])

  const bulkActions: DataTableBulkAction<YearlyRow>[] = useMemo(
    () => [
      { label: t("printCards.bulkReportCards"), icon: FileBadge, onClick: onBulkPrint },
      { label: t("printCards.bulkTranscripts"), icon: ScrollText, onClick: onBulkTranscripts },
    ],
    [t, onBulkPrint, onBulkTranscripts],
  )

  return (
    <DataTable
      columns={tableColumns}
      data={data}
      dense
      searchKeys={["full_name", "public_id"]}
      searchPlaceholder={tc("actions.search")}
      actions={[
        {
          label: t("extras.title"),
          icon: ClipboardList,
          onClick: onExtras,
          primary: true,
        },
        {
          label: t("printCards.reportCard"),
          icon: FileBadge,
          onClick: (row) => onPrint([row]),
        },
        {
          label: t("reportCards.transcript"),
          icon: ScrollText,
          onClick: onTranscript,
        },
      ]}
      bulkActions={bulkActions}
      emptyMessage={`${t("rosters.empty")} ${t("rosters.emptyHint")}`}
      exportFilename="yearly-roster"
    />
  )
}
