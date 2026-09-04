"use client"

import { useTranslation } from "@/lib/i18n"
import type { ReportCard } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtDualDate } from "@/lib/dates"

/**
 * The printable report card body — the frozen term result rendered through
 * its snapshotted grading policy (numbers, letters or both). Shared by the
 * staff print page and the /me lanes so every surface prints the same card.
 */
export function ReportCardArticle({ card }: { card: ReportCard }) {
  const { t } = useTranslation("grading")

  const display = card.grading?.display ?? "numeric"
  const showNumbers = display !== "letter"
  const showLetters = display !== "numeric"

  return (
    <article className="bg-card space-y-6 rounded-2xl border p-6 print:border-0 print:p-0 print:shadow-none">
      {/* Header */}
      <header className="space-y-1 border-b pb-4 text-center">
        {card.school_logo_url && (
          // eslint-disable-next-line @next/next/no-img-element -- signed R2 URL, print document
          <img
            src={card.school_logo_url}
            alt=""
            className="mx-auto mb-2 size-14 object-contain"
          />
        )}
        <h2 className="font-display text-lg font-semibold">{card.school_name}</h2>
        {card.branch_name && <p className="text-muted-foreground text-sm">{card.branch_name}</p>}
        <p className="text-sm font-medium">{t("reportCards.card.title")}</p>
      </header>

      {/* Identity strip */}
      <dl className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
        <div>
          <dt className="text-muted-foreground text-xs">{t("reportCards.student")}</dt>
          <dd className="font-medium">{card.student.full_name}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">{t("reportCards.card.studentId")}</dt>
          <dd className="font-medium tabular-nums">{card.student.public_id ?? "—"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">{t("reportCards.card.academicYear")}</dt>
          <dd className="font-medium">{card.academic_year ?? "—"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">{t("reportCards.card.semester")}</dt>
          <dd className="font-medium">{card.term_name ?? "—"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">{t("reportCards.card.gradeLevel")}</dt>
          <dd className="font-medium">{card.grade_level ?? "—"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground text-xs">{t("reportCards.card.section")}</dt>
          <dd className="font-medium">{card.section_name ?? "—"}</dd>
        </div>
      </dl>

      {/* Subject table */}
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-sm">
          <thead>
            <tr className="border-y text-left">
              <th className="py-2 pr-3 font-medium">{t("reportCards.card.subject")}</th>
              {showNumbers && (
                <th className="w-20 px-2 py-2 text-right font-medium">
                  {t("reportCards.card.mark")}
                </th>
              )}
              {showLetters && (
                <th className="w-16 px-2 py-2 text-center font-medium">
                  {t("reportCards.card.letter")}
                </th>
              )}
              <th className="w-36 py-2 pl-2 text-right font-medium">
                {t("reportCards.card.remark")}
              </th>
            </tr>
          </thead>
          <tbody>
            {card.subjects.map((line) => (
              <tr key={line.subject_id} className="border-b last:border-0">
                <td className="py-1.5 pr-3">{line.name}</td>
                {showNumbers && (
                  <td className="px-2 py-1.5 text-right tabular-nums">{line.total ?? "—"}</td>
                )}
                {showLetters && (
                  <td className="px-2 py-1.5 text-center font-semibold">{line.letter ?? "—"}</td>
                )}
                <td
                  className={cn(
                    "text-muted-foreground py-1.5 pl-2 text-right text-xs",
                    line.is_passing === false && "text-destructive",
                  )}
                >
                  {line.band_label ?? "—"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Summary */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {showNumbers && (
          <SummaryTile label={t("reportCards.average")} value={card.average ?? "—"} />
        )}
        {showLetters && (
          <SummaryTile
            label={t("reportCards.grade")}
            value={card.grading?.overall?.letter ?? card.grading?.overall?.label ?? "—"}
          />
        )}
        <SummaryTile
          label={t("reportCards.rank")}
          value={
            card.rank !== null && card.rank_of !== null
              ? t("reportCards.rankOf", { rank: card.rank, of: card.rank_of })
              : "—"
          }
        />
        <SummaryTile label={t("reportCards.conduct")} value={card.conduct ?? "—"} />
        <SummaryTile label={t("reportCards.card.daysAbsent")} value={card.absence_days ?? "—"} />
      </div>

      {card.comment && (
        <p className="text-sm">
          <span className="text-muted-foreground text-xs">{t("reportCards.comment")}: </span>
          {card.comment}
        </p>
      )}

      {/* Signatures */}
      <div className="grid grid-cols-3 gap-6 pt-8">
        {[
          t("reportCards.card.homeroomTeacher"),
          t("reportCards.card.director"),
          t("reportCards.card.parent"),
        ].map((label) => (
          <div key={label} className="space-y-8 text-center">
            <div className="border-b" />
            <p className="text-muted-foreground -mt-6 text-xs">{label}</p>
          </div>
        ))}
      </div>

      {card.computed_at && (
        <p className="text-muted-foreground text-right text-[11px]">
          {t("reportCards.card.computedAt", {
            date: fmtDualDate(card.computed_at),
          })}
        </p>
      )}
    </article>
  )
}

function SummaryTile({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-xl border px-3 py-2">
      <p className="text-muted-foreground text-[11px]">{label}</p>
      <p className="text-sm font-semibold tabular-nums">{value}</p>
    </div>
  )
}
