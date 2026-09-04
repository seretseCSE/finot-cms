"use client"

import { generate } from "lean-qr"
import { toSvgDataURL } from "lean-qr/extras/svg"
import { useEffect, useState } from "react"

import { useTranslation } from "@/lib/i18n"
import type { Transcript, TranscriptYear } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The printable multi-year transcript body — mirror of
 * resources/views/documents/transcript.blade.php, keep in step. The
 * Ethiopian transcript grid: years side-by-side as column groups
 * (Sem 1 | Sem 2 | Avg), subject-union rows, footer rows for Total /
 * Average / Rank / Conduct / Absences / year-end outcome, grading key and
 * signature block. Shared by the single and bulk print pages.
 */

/** Trim trailing zeros: 84.50 → 84.5, 84.00 → 84. */
function fmt(value: number | null | undefined): string {
  if (value === null || value === undefined) return "—"
  return String(Math.round(value * 100) / 100)
}

/**
 * Chunk years into grid blocks so the sheet never overflows: a year costs
 * its term columns + one Avg column; a block holds at most 12 value columns
 * (4 two-semester years side by side, like the paper transcripts).
 */
function chunkYears(years: TranscriptYear[]): TranscriptYear[][] {
  const chunks: TranscriptYear[][] = []
  let current: TranscriptYear[] = []
  let cols = 0
  for (const year of years) {
    const cost = year.terms.length + 1
    if (current.length > 0 && cols + cost > 12) {
      chunks.push(current)
      current = []
      cols = 0
    }
    current.push(year)
    cols += cost
  }
  if (current.length > 0) chunks.push(current)
  return chunks
}

const CELL = "border border-border px-1 py-0.5 text-center tabular-nums"
const LABEL_CELL = "border border-border px-2 py-1 text-left font-semibold"

export function TranscriptArticle({
  transcript,
  publicToken,
}: {
  transcript: Transcript
  /** When known, renders the QR that opens the public transcript page. */
  publicToken?: string | null
}) {
  const { t } = useTranslation("grading")

  // QR encodes the public transcript URL — window is only known client-side.
  const [qr, setQr] = useState<string | null>(null)
  useEffect(() => {
    if (!publicToken) return
    // eslint-disable-next-line react-hooks/set-state-in-effect -- QR needs window.origin (client only)
    setQr(
      toSvgDataURL(generate(`${window.location.origin}/transcripts/${publicToken}`), {
        on: "#000000",
        off: "#ffffff",
        pad: 2,
      }),
    )
  }, [publicToken])

  const issuer = transcript.issued_by
  const years = transcript.years
  // The print page may narrow years client-side — recompute the stamp.
  const isPartial =
    transcript.is_partial ||
    (transcript.available_years !== undefined &&
      years.length < transcript.available_years.length)
  const coveredGrades = [
    ...new Set(years.map((y) => y.grade_level).filter(Boolean)),
  ].join(", ")

  // Years recorded at ANOTHER school than the issuing one carry a footnote —
  // the honest way to present transfer history (ADR-017).
  const footnotes = new Map<string, { n: number; school: string; branch: string | null }>()
  for (const year of years) {
    if (issuer && year.school_name && year.school_name !== issuer.school_name) {
      const key = `${year.school_name}·${year.branch_name ?? ""}`
      if (!footnotes.has(key)) {
        footnotes.set(key, {
          n: footnotes.size + 1,
          school: year.school_name,
          branch: year.branch_name,
        })
      }
    }
  }
  const footnoteFor = (year: TranscriptYear): number | null =>
    issuer && year.school_name && year.school_name !== issuer.school_name
      ? (footnotes.get(`${year.school_name}·${year.branch_name ?? ""}`)?.n ?? null)
      : null

  return (
    <article className="bg-card space-y-4 rounded-2xl border p-6 print:border-0 print:p-0 print:shadow-none">
      {/* masthead: school logo · issuing school · student photo */}
      <header className="border-foreground flex items-center gap-6 border-b-2 pb-3.5">
        {/* Side columns share a width so the school name stays centered. */}
        <div className="w-28 shrink-0">
          {issuer?.logo_url && (
            // eslint-disable-next-line @next/next/no-img-element -- signed R2 URL, print document
            <img src={issuer.logo_url} alt="" className="size-20 object-contain" />
          )}
        </div>
        <div className="min-w-0 flex-1 text-center">
          <h1 className="font-display text-xl font-bold">
            {issuer?.school_name ?? t("transcript.title")}
          </h1>
          {(() => {
            const contactLine = [
              issuer?.branch_name,
              issuer?.address,
              issuer?.phone ? `☎ ${issuer.phone}` : null,
            ]
              .filter(Boolean)
              .join(" · ")
            return contactLine ? (
              <p className="text-muted-foreground text-xs">{contactLine}</p>
            ) : null
          })()}
          <p className="mt-1 text-xs font-semibold uppercase tracking-widest">
            {t("transcript.official")}
          </p>
          {isPartial && (
            <p className="text-destructive mt-0.5 text-[11px] font-semibold">
              {t("transcript.partial", {
                grades: coveredGrades || t("transcript.partialSelected"),
              })}
            </p>
          )}
        </div>
        <div className="flex w-28 shrink-0 justify-end">
          {transcript.student.photo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- signed R2 URL, print document
            <img
              src={transcript.student.photo_url}
              alt=""
              className="border-border size-28 rounded-md border object-cover"
            />
          ) : (
            /* 4×4 dashed frame so the school can affix a physical photo. */
            <div className="border-border flex size-28 items-center justify-center rounded-md border-[1.5px] border-dashed">
              <span className="text-muted-foreground text-[10px] uppercase tracking-wider">
                {t("transcript.photoBox")}
              </span>
            </div>
          )}
        </div>
      </header>

      {/* student identity strip */}
      <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-[13px] md:grid-cols-[2fr_1fr_1fr_1fr]">
        {(
          [
            [t("transcript.studentName"), transcript.student.full_name],
            [t("transcript.studentId"), transcript.student.public_id ?? "—"],
            [
              t("transcript.sex"),
              transcript.student.gender
                ? transcript.student.gender.charAt(0).toUpperCase() +
                  transcript.student.gender.slice(1)
                : "—",
            ],
            [t("transcript.dateOfBirth"), transcript.student.date_of_birth ?? "—"],
          ] as const
        ).map(([label, value]) => (
          <div key={label}>
            <dt className="text-muted-foreground text-[11px]">{label}</dt>
            <dd className="font-semibold">{value}</dd>
          </div>
        ))}
      </dl>

      {years.length === 0 && (
        <p className="text-muted-foreground py-10 text-center text-sm">
          {t("transcript.empty")}
        </p>
      )}

      {chunkYears(years).map((chunk, chunkIndex) => {
        // Subject-union rows for THIS block (insertion = chronological order).
        const subjects = new Map<number, string>()
        for (const year of chunk) {
          for (const term of year.terms) {
            for (const line of term.subjects) {
              if (!subjects.has(line.subject_id)) subjects.set(line.subject_id, line.name)
            }
          }
        }

        return (
          <div key={chunkIndex} className="overflow-x-auto">
            <table className="w-full border-collapse text-xs">
              <thead>
                <tr>
                  <th rowSpan={2} className={cn(LABEL_CELL, "w-44 align-bottom")}>
                    {t("reportCards.card.subject")}
                  </th>
                  {chunk.map((year) => {
                    const note = footnoteFor(year)
                    return (
                      <th
                        key={year.academic_year_id}
                        colSpan={year.terms.length + 1}
                        className={cn(CELL, "px-1.5 py-1 font-semibold")}
                      >
                        {year.academic_year ?? "—"} · {year.grade_level ?? "—"}
                        {note !== null && <sup>{note}</sup>}
                      </th>
                    )
                  })}
                </tr>
                <tr className="text-muted-foreground text-[11px]">
                  {chunk.flatMap((year) => [
                    ...year.terms.map((term) => (
                      <th
                        key={`${year.academic_year_id}-${term.term_id}`}
                        className={cn(CELL, "font-medium")}
                      >
                        {term.term_name ?? "—"}
                      </th>
                    )),
                    <th key={`${year.academic_year_id}-avg`} className={cn(CELL, "font-semibold")}>
                      {t("transcript.avg")}
                    </th>,
                  ])}
                </tr>
              </thead>
              <tbody>
                {[...subjects].map(([subjectId, name]) => (
                  <tr key={subjectId}>
                    <td className="border-border border px-2 py-0.5">{name}</td>
                    {chunk.flatMap((year) => {
                      const totals: number[] = []
                      const cells = year.terms.map((term) => {
                        const line = term.subjects.find((s) => s.subject_id === subjectId)
                        if (line?.total !== null && line?.total !== undefined) {
                          totals.push(line.total)
                        }
                        const letters = term.grading?.display === "letter"
                        return (
                          <td
                            key={`${year.academic_year_id}-${term.term_id}`}
                            className={cn(
                              CELL,
                              line?.is_passing === false && "text-destructive",
                            )}
                          >
                            {line ? (letters ? (line.letter ?? "—") : fmt(line.total)) : "—"}
                          </td>
                        )
                      })
                      cells.push(
                        <td
                          key={`${year.academic_year_id}-avg`}
                          className={cn(CELL, "font-semibold")}
                        >
                          {totals.length === 0
                            ? "—"
                            : fmt(totals.reduce((a, b) => a + b, 0) / totals.length)}
                        </td>,
                      )
                      return cells
                    })}
                  </tr>
                ))}

                {/* footer rows: the year-summary lines every Ethiopian transcript carries */}
                <tr className="font-semibold">
                  <td className={LABEL_CELL}>{t("transcript.total")}</td>
                  {chunk.flatMap((year) => [
                    ...year.terms.map((term) => (
                      <td key={`${year.academic_year_id}-${term.term_id}`} className={CELL}>
                        {fmt(term.total)}
                      </td>
                    )),
                    <td key={`${year.academic_year_id}-avg`} className={CELL} />,
                  ])}
                </tr>
                <tr className="font-semibold">
                  <td className={LABEL_CELL}>{t("transcript.semesterAverage")}</td>
                  {chunk.flatMap((year) => [
                    ...year.terms.map((term) => (
                      <td key={`${year.academic_year_id}-${term.term_id}`} className={CELL}>
                        {fmt(term.average)}
                      </td>
                    )),
                    <td key={`${year.academic_year_id}-avg`} className={CELL}>
                      {fmt(year.annual_average)}
                    </td>,
                  ])}
                </tr>
                <tr>
                  <td className={LABEL_CELL}>{t("transcript.rank")}</td>
                  {chunk.flatMap((year) => [
                    ...year.terms.map((term) => (
                      <td key={`${year.academic_year_id}-${term.term_id}`} className={CELL}>
                        {term.rank !== null
                          ? `${term.rank}${term.rank_of !== null ? ` / ${term.rank_of}` : ""}`
                          : "—"}
                      </td>
                    )),
                    <td key={`${year.academic_year_id}-avg`} className={CELL} />,
                  ])}
                </tr>
                <tr>
                  <td className={LABEL_CELL}>{t("transcript.conduct")}</td>
                  {chunk.flatMap((year) => [
                    ...year.terms.map((term) => (
                      <td key={`${year.academic_year_id}-${term.term_id}`} className={CELL}>
                        {term.conduct ?? "—"}
                      </td>
                    )),
                    <td key={`${year.academic_year_id}-avg`} className={CELL} />,
                  ])}
                </tr>
                <tr>
                  <td className={LABEL_CELL}>{t("transcript.daysAbsent")}</td>
                  {chunk.flatMap((year) => [
                    ...year.terms.map((term) => (
                      <td key={`${year.academic_year_id}-${term.term_id}`} className={CELL}>
                        {term.absence_days ?? "—"}
                      </td>
                    )),
                    <td key={`${year.academic_year_id}-avg`} className={CELL} />,
                  ])}
                </tr>
                {/* Only rendered when at least one year has a recorded
                    outcome — an all-empty Status row is noise. */}
                {chunk.some((year) => year.outcome !== null) && (
                  <tr>
                    <td className={LABEL_CELL}>{t("transcript.status")}</td>
                    {chunk.map((year) => (
                      <td
                        key={year.academic_year_id}
                        colSpan={year.terms.length + 1}
                        className={cn(CELL, "font-semibold")}
                      >
                        {year.outcome
                          ? year.outcome.decision === "promoted" && year.outcome.to_grade_level
                            ? t("transcript.promotedTo", { grade: year.outcome.to_grade_level })
                            : year.outcome.label
                          : "—"}
                      </td>
                    ))}
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        )
      })}

      {footnotes.size > 0 && (
        <div className="text-muted-foreground space-y-0.5 text-[11px]">
          {[...footnotes.values()].map((note) => (
            <p key={note.n}>
              <sup>{note.n}</sup>{" "}
              {t("transcript.recordedAt", {
                school: `${note.school}${note.branch ? ` — ${note.branch}` : ""}`,
              })}
            </p>
          ))}
        </div>
      )}

      {/* QR (opens the live transcript online) · signatures */}
      <div className="flex flex-col justify-between gap-6 pt-2 md:flex-row md:items-end">
        {qr ? (
          <div className="flex shrink-0 items-center gap-3">
            {/* eslint-disable-next-line @next/next/no-img-element -- data URL QR */}
            <img src={qr} alt="QR" className="size-21 shrink-0 rounded-md bg-white" />
            <div className="max-w-56">
              <p className="text-[11px] font-semibold">{t("transcript.qrTitle")}</p>
              <p className="text-muted-foreground mt-0.5 text-[10.5px]">
                {t("transcript.qrHint")}
              </p>
            </div>
          </div>
        ) : (
          <div />
        )}

        <div className="flex shrink-0 gap-10">
          {[t("transcript.preparedBy"), t("transcript.director")].map((label) => (
            <div key={label} className="w-44">
              <p className="text-muted-foreground text-[10px] font-semibold uppercase tracking-wide">
                {label}
              </p>
              <div className="mt-4 flex items-baseline gap-2">
                <span className="text-muted-foreground shrink-0 text-[10px]">
                  {t("transcript.signName")}
                </span>
                <div className="border-foreground flex-1 border-b" />
              </div>
              <div className="mt-5 flex items-baseline gap-2">
                <span className="text-muted-foreground shrink-0 text-[10px]">
                  {t("transcript.signSignature")}
                </span>
                <div className="border-foreground flex-1 border-b" />
              </div>
            </div>
          ))}
        </div>
      </div>
    </article>
  )
}
