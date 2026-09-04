"use client"

import { useParams, useSearchParams } from "next/navigation"
import { Suspense, useEffect, useState } from "react"

import { ReportCardArticle } from "@/components/grading/report-card-article"
import {
  DocumentDownloadButton,
  DocumentPrintButton,
} from "@/components/ui/document-download-button"
import { Logo } from "@/components/ui/logo"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { ReportCard } from "@/lib/types"

/**
 * Staff print view of the OFFICIAL (frozen) report card — opened from the
 * report-cards register in a new tab, outside the app shell so it prints
 * clean. Requires a logged-in staff session (StudentPolicy@view).
 */
function ReportCardPrintPage() {
  const { t } = useTranslation("grading")
  const params = useParams<{ student: string }>()
  const search = useSearchParams()
  const termId = search.get("term_id")

  const [card, setCard] = useState<ReportCard | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    if (!termId) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- URL param validation on mount
      setFailed(true)
      return
    }
    let cancelled = false

    // One URL for every lane: staff endpoint first, then the parent link,
    // then the student's own record (ADR-012 — the backend decides access).
    const lanes = [
      `/reports/students/${params.student}/report-card?term_id=${termId}`,
      `/me/children/${params.student}/report-card?term_id=${termId}`,
      `/me/student/report-card?term_id=${termId}`,
    ]

    void (async () => {
      for (const url of lanes) {
        try {
          const res = await apiFetch<{ data: ReportCard | null }>(url)
          if (cancelled) return
          if (res.data !== null) {
            setCard(res.data)
            return
          }
        } catch {
          // Try the next lane.
        }
      }
      if (!cancelled) setFailed(true)
    })()

    return () => {
      cancelled = true
    }
  }, [params.student, termId])

  return (
    <div className="bg-muted/30 min-h-svh px-4 py-8 md:py-12 print:bg-white print:p-0">
      <div className="mx-auto max-w-2xl space-y-4">
        <div className="flex items-center justify-between gap-3 print:hidden">
          <Logo />
          {card && (
            <div className="flex items-center gap-2">
              {/* Families use the same official-PDF lane as staff (ADR-012). */}
              <DocumentDownloadButton
                type="report_card"
                subjectId={Number(params.student)}
                params={{ term_id: Number(termId) }}
              />
              <DocumentPrintButton
                type="report_card"
                subjectId={Number(params.student)}
                params={{ term_id: Number(termId) }}
              />
            </div>
          )}
        </div>

        {card === null ? (
          failed ? (
            <div className="bg-card text-muted-foreground rounded-2xl border border-dashed px-6 py-16 text-center text-sm">
              {t("reportCards.notPublished")}
            </div>
          ) : (
            <Skeleton className="h-[36rem] rounded-2xl" />
          )
        ) : (
          <ReportCardArticle card={card} />
        )}
      </div>
    </div>
  )
}

export default function Page() {
  return (
    <Suspense fallback={null}>
      <ReportCardPrintPage />
    </Suspense>
  )
}
