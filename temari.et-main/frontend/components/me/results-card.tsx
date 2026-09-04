"use client"

import { FileBadge, ScrollText } from "lucide-react"
import Link from "next/link"
import { useEffect, useState } from "react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"

/** One line of the frozen report-card index (/me lanes). */
interface ReportCardSummary {
  term_id: number
  term_name: string | null
  academic_year: string | null
  grade_level: string | null
  average: number | null
  rank: number | null
  rank_of: number | null
  letter: string | null
  is_passing: boolean | null
}

/**
 * "Results" card for the /me surfaces: lists every frozen report card
 * (newest first) with print links, plus the transcript. `indexUrl` differs
 * per lane (parent child vs own student); the print pages resolve the same
 * lane server-side.
 */
export function ResultsCard({
  indexUrl,
  studentId,
}: {
  indexUrl: string
  studentId: number
}) {
  const { t } = useTranslation("grading")
  const { t: tm } = useTranslation("me")

  const [cards, setCards] = useState<ReportCardSummary[] | null>(null)

  useEffect(() => {
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset on lane/child switch
    setCards(null)
    apiFetch<{ data: ReportCardSummary[] }>(indexUrl)
      .then((res) => !cancelled && setCards(res.data))
      .catch(() => !cancelled && setCards([]))
    return () => {
      cancelled = true
    }
  }, [indexUrl])

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between gap-2">
        <CardTitle className="text-base">{tm("results.title")}</CardTitle>
        {cards !== null && cards.length > 0 && (
          <Button variant="outline" size="sm" asChild>
            <Link href={`/print/transcript/${studentId}`} target="_blank">
              <ScrollText className="size-3.5" />
              {t("reportCards.transcript")}
            </Link>
          </Button>
        )}
      </CardHeader>
      <CardContent>
        {cards === null ? (
          <Skeleton className="h-20 w-full rounded-xl" />
        ) : cards.length === 0 ? (
          <p className="text-muted-foreground text-sm">{tm("results.empty")}</p>
        ) : (
          <ul className="space-y-2">
            {cards.map((card) => (
              <li
                key={card.term_id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2.5 text-sm"
              >
                <div className="min-w-0">
                  <p className="font-medium">
                    {card.term_name}
                    {card.academic_year ? (
                      <span className="text-muted-foreground font-normal">
                        {" "}
                        · {card.academic_year}
                      </span>
                    ) : null}
                  </p>
                  <p className="text-muted-foreground text-xs">
                    {t("reportCards.average")}: {card.average ?? "—"}
                    {card.rank !== null && card.rank_of !== null
                      ? ` · ${t("reportCards.rankOf", { rank: card.rank, of: card.rank_of })}`
                      : ""}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {card.letter && (
                    <Badge variant={card.is_passing === false ? "destructive" : "secondary"}>
                      {card.letter}
                    </Badge>
                  )}
                  <Button variant="ghost" size="sm" asChild>
                    <Link
                      href={`/print/report-card/${studentId}?term_id=${card.term_id}`}
                      target="_blank"
                    >
                      <FileBadge className="size-3.5" />
                      {t("reportCards.view")}
                    </Link>
                  </Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}
