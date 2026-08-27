"use client"

import { ClipboardList, FileBadge, ScrollText } from "lucide-react"
import { useMemo } from "react"

import { MarkCell, RankChip } from "@/components/grading/roster-matrix"
import {
  DataTable,
  type DataTableBulkAction,
  type DataTableColumn,
} from "@/components/ui/data-table"
import { Input } from "@/components/ui/input"
import { useTranslation } from "@/lib/i18n"
import type { RosterColumn, TermRosterRow } from "@/lib/types"

/** DataTable rows need an `id`; selection + keys ride the student id. */
export type RosterRow = TermRosterRow & { id: number }

/**
 * The semester roster as a proper register: sortable columns, search, paging,
 * checkbox selection with bulk printing, inline conduct entry and the
 * per-student report-card / transcript / extra-assessment actions.
 */
export function RosterTable({
  columns,
  rows,
  showSection,
  conductEditable,
  onConductEdit,
  conductValue,
  onExtras,
  onPrint,
  onTranscript,
  onBulkPrint,
  onBulkTranscripts,
}: {
  columns: RosterColumn[]
  rows: TermRosterRow[]
  showSection: boolean
  conductEditable: boolean
  /** Fired per keystroke — the parent tracks edits in a ref, so typing never
   *  re-renders this table (the inputs are deliberately uncontrolled; the
   *  parent remounts the table per term so stale values can't survive). */
  onConductEdit: (row: TermRosterRow, conduct: string) => void
  /** Initial value for a row's conduct input — reads the parent's edit ref
   *  so an unsaved edit survives paging away and back. MUST be stable
   *  (useCallback([])) or the column set rebuilds every parent render. */
  conductValue: (row: TermRosterRow) => string
  onExtras: (row: TermRosterRow) => void
  onPrint: (rows: TermRosterRow[]) => void
  onTranscript: (row: TermRosterRow) => void
  onBulkPrint: (rows: TermRosterRow[]) => void
  onBulkTranscripts: (rows: TermRosterRow[]) => void
}) {
  const { t } = useTranslation("grading")
  const { t: tc } = useTranslation("common")

  const data: RosterRow[] = useMemo(
    () => rows.map((row) => ({ ...row, id: row.student_id })),
    [rows],
  )

  const tableColumns: DataTableColumn<RosterRow>[] = useMemo(() => {
    const cols: DataTableColumn<RosterRow>[] = [
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
        mobileHidden: false,
        exportValue: (row) => row.section_name ?? "",
      })
    }

    for (const col of columns) {
      cols.push({
        key: `subject_${col.subject_id}`,
        label: col.code ?? col.name,
        className: "text-center",
        mobileHidden: true,
        render: (row) => <MarkCell cell={row.scores[String(col.subject_id)]} />,
        sortValue: (row) => row.scores[String(col.subject_id)]?.total ?? null,
        exportValue: (row) => {
          const cell = row.scores[String(col.subject_id)]
          return cell?.total != null ? String(cell.total) : ""
        },
      })
    }

    cols.push(
      {
        key: "total",
        label: t("rosters.total"),
        className: "text-right",
        mobileHidden: true,
        render: (row) => (
          <span className="font-medium tabular-nums">{row.total ?? "—"}</span>
        ),
        sortValue: (row) => row.total,
        exportValue: (row) => (row.total != null ? String(row.total) : ""),
      },
      {
        key: "average",
        label: t("rosters.average"),
        className: "text-right",
        render: (row) => (
          <span className="font-semibold tabular-nums">{row.average ?? "—"}</span>
        ),
        sortValue: (row) => row.average,
        exportValue: (row) => (row.average != null ? String(row.average) : ""),
      },
      {
        key: "rank",
        label: t("rosters.rank"),
        className: "text-center",
        render: (row) => <RankChip rank={row.rank} rankOf={row.rank_of} />,
        sortValue: (row) => row.rank,
        exportValue: (row) => (row.rank != null ? String(row.rank) : ""),
      },
      {
        key: "conduct",
        label: t("reportCards.conduct"),
        className: "text-center",
        sortable: false,
        render: (row) =>
          conductEditable ? (
            <div onClick={(e) => e.stopPropagation()}>
              <Input
                defaultValue={conductValue(row)}
                maxLength={5}
                placeholder="A–E"
                aria-label={t("reportCards.conduct")}
                className="mx-auto h-8 w-14 text-center"
                onChange={(e) => onConductEdit(row, e.target.value)}
              />
            </div>
          ) : (
            (row.conduct ?? "—")
          ),
        exportValue: (row) => row.conduct ?? "",
      },
      {
        key: "absence_days",
        label: t("reportCards.absences"),
        className: "text-center",
        mobileHidden: true,
        render: (row) => <span className="tabular-nums">{row.absence_days ?? "—"}</span>,
        sortValue: (row) => row.absence_days,
        exportValue: (row) => (row.absence_days != null ? String(row.absence_days) : ""),
      },
    )

    return cols
  }, [t, columns, showSection, conductEditable, onConductEdit, conductValue])

  const bulkActions: DataTableBulkAction<RosterRow>[] = useMemo(
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
      loading={false}
      searchKeys={["full_name", "public_id"]}
      searchPlaceholder={tc("actions.search")}
      filters={[
        {
          key: "conduct",
          label: t("reportCards.conduct"),
          options: ["A", "B", "C", "D", "E"].map((v) => ({ label: v, value: v })),
        },
      ]}
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
      exportFilename="roster"
    />
  )
}
