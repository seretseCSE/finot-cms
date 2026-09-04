"use client"

import { ChevronDown } from "lucide-react"
import { useEffect, useRef, useState } from "react"

import { PersonAvatar } from "@/components/ui/person-avatar"
import { useTranslation } from "@/lib/i18n"
import type { RosterColumn, RosterScoreCell, TermRosterRow } from "@/lib/types"
import { cn } from "@/lib/utils"

/** Rows rendered per chunk — keeps huge grades smooth on low-end devices. */
const CHUNK = 60

/**
 * Chunked rendering without a dependency: render the first CHUNK items and
 * grow when the sentinel scrolls into view.
 */
export function useLoadMore(total: number) {
  const [visible, setVisible] = useState(CHUNK)
  const sentinelRef = useRef<HTMLDivElement | null>(null)

  // New dataset → start over.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on data change
    setVisible(CHUNK)
  }, [total])

  useEffect(() => {
    const el = sentinelRef.current
    if (!el || visible >= total) return
    const observer = new IntersectionObserver(
      (entries) => entries[0]?.isIntersecting && setVisible((v) => v + CHUNK),
      { rootMargin: "600px" },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [visible, total])

  return { visible, sentinelRef, done: visible >= total }
}

/** Top-3 ranks get a medal tint; everyone else a quiet number. */
export function RankChip({ rank, rankOf }: { rank: number | null; rankOf?: number | null }) {
  if (rank === null) return <span className="text-muted-foreground">—</span>
  return (
    <span
      className={cn(
        "inline-flex min-w-6 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-semibold tabular-nums",
        rank === 1 && "bg-success/15 text-success",
        rank === 2 && "bg-info/15 text-info",
        rank === 3 && "bg-warning/15 text-warning",
        rank > 3 && "text-muted-foreground",
      )}
      title={rankOf != null ? `${rank}/${rankOf}` : undefined}
    >
      {rank}
    </span>
  )
}

/** One frozen mark: number (tinted red when failing) + tiny letter. */
export function MarkCell({ cell }: { cell: RosterScoreCell | undefined }) {
  if (!cell || cell.total === null) return <span className="text-muted-foreground">—</span>
  return (
    <span
      className={cn(
        "tabular-nums",
        cell.is_passing === false && "text-destructive font-medium",
      )}
    >
      {cell.total}
      {cell.letter ? (
        <span className="text-muted-foreground ml-0.5 text-[10px] font-normal">
          {cell.letter}
        </span>
      ) : null}
    </span>
  )
}

/**
 * The classic semester roster: students × subjects with total/average/rank.
 * Desktop = sticky-first-column spreadsheet; mobile = app-like student cards
 * with an expandable subject breakdown.
 */
export function RosterMatrix({
  columns,
  rows,
  showSection,
}: {
  columns: RosterColumn[]
  rows: TermRosterRow[]
  showSection: boolean
}) {
  const { t } = useTranslation("grading")
  const { visible, sentinelRef, done } = useLoadMore(rows.length)
  const shown = rows.slice(0, visible)

  return (
    <>
      {/* ── Mobile: rank-ordered student cards ── */}
      <div className="space-y-2 md:hidden">
        {shown.map((row) => (
          <MobileRosterCard key={row.student_id} row={row} columns={columns} showSection={showSection} />
        ))}
      </div>

      {/* ── Desktop: the full roster sheet ── */}
      <div className="bg-card hidden overflow-x-auto rounded-2xl border md:block">
        <table className="w-full min-w-[48rem] border-collapse text-sm">
          <thead>
            <tr className="bg-card sticky top-0 z-20 border-b">
              <th className="bg-card sticky left-0 z-30 px-4 py-3 text-left font-medium">
                {t("rosters.student")}
              </th>
              {showSection && (
                <th className="px-3 py-3 text-left text-xs font-medium">
                  {t("reportCards.section")}
                </th>
              )}
              {columns.map((col) => (
                <th
                  key={col.subject_id}
                  className="min-w-16 px-2 py-3 text-center text-xs font-semibold"
                  title={col.name}
                >
                  {col.code ?? col.name}
                </th>
              ))}
              <th className="min-w-16 px-3 py-3 text-right text-xs font-medium">
                {t("rosters.total")}
              </th>
              <th className="min-w-16 px-3 py-3 text-right text-xs font-medium">
                {t("rosters.average")}
              </th>
              <th className="min-w-14 px-3 py-3 text-center text-xs font-medium">
                {t("rosters.rank")}
              </th>
            </tr>
          </thead>
          <tbody>
            {shown.map((row, rowIndex) => (
              <tr
                key={row.student_id}
                className={cn(
                  "hover:bg-accent/30 border-b transition-colors last:border-0",
                  rowIndex % 2 === 1 && "bg-muted/20",
                )}
              >
                <td className="bg-card sticky left-0 z-10 max-w-56 px-4 py-2">
                  <p className="truncate font-medium">{row.full_name ?? "—"}</p>
                  {row.public_id && (
                    <p className="text-muted-foreground text-[11px]">{row.public_id}</p>
                  )}
                </td>
                {showSection && (
                  <td className="text-muted-foreground whitespace-nowrap px-3 py-2 text-xs">
                    {row.section_name ?? "—"}
                  </td>
                )}
                {columns.map((col) => (
                  <td key={col.subject_id} className="px-2 py-2 text-center">
                    <MarkCell cell={row.scores[String(col.subject_id)]} />
                  </td>
                ))}
                <td className="px-3 py-2 text-right font-medium tabular-nums">
                  {row.total ?? "—"}
                </td>
                <td className="px-3 py-2 text-right font-semibold tabular-nums">
                  {row.average ?? "—"}
                </td>
                <td className="px-3 py-2 text-center">
                  <RankChip rank={row.rank} rankOf={row.rank_of} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {!done && <div ref={sentinelRef} className="h-8" />}
    </>
  )
}

/** Mobile card: rank, identity, big average — tap to unfold subject marks. */
function MobileRosterCard({
  row,
  columns,
  showSection,
}: {
  row: TermRosterRow
  columns: RosterColumn[]
  showSection: boolean
}) {
  const { t } = useTranslation("grading")
  const [open, setOpen] = useState(false)

  return (
    <div className="bg-card rounded-2xl border">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex min-h-14 w-full items-center gap-3 px-3 py-2.5 text-left"
      >
        <RankChip rank={row.rank} rankOf={row.rank_of} />
        <PersonAvatar name={row.full_name ?? "?"} photoUrl={row.photo_url} className="size-9" />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">{row.full_name ?? "—"}</p>
          <p className="text-muted-foreground truncate text-xs">
            {[showSection ? row.section_name : null, row.public_id].filter(Boolean).join(" · ") ||
              "—"}
          </p>
        </div>
        <div className="text-right">
          <p className="text-base font-semibold tabular-nums">{row.average ?? "—"}</p>
          <p className="text-muted-foreground text-[10px]">{t("rosters.average")}</p>
        </div>
        <ChevronDown
          className={cn("text-muted-foreground size-4 shrink-0 transition-transform", open && "rotate-180")}
        />
      </button>
      {open && (
        <div className="grid grid-cols-2 gap-x-4 border-t px-4 py-3">
          {columns.map((col) => (
            <div
              key={col.subject_id}
              className="flex items-center justify-between gap-2 py-1 text-sm"
            >
              <span className="text-muted-foreground min-w-0 truncate text-xs">{col.name}</span>
              <MarkCell cell={row.scores[String(col.subject_id)]} />
            </div>
          ))}
          <div className="col-span-2 mt-2 flex items-center justify-between border-t pt-2 text-sm">
            <span className="text-muted-foreground text-xs">{t("rosters.total")}</span>
            <span className="font-semibold tabular-nums">{row.total ?? "—"}</span>
          </div>
        </div>
      )}
    </div>
  )
}
