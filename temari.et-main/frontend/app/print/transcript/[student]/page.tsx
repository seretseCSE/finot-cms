"use client"

import { useParams } from "next/navigation"
import { useEffect, useMemo, useState } from "react"

import { TranscriptArticle } from "@/components/grading/transcript-article"
import { TranscriptYearPicker } from "@/components/grading/transcript-year-picker"
import {
  DocumentDownloadButton,
  DocumentPrintButton,
} from "@/components/ui/document-download-button"
import { Logo } from "@/components/ui/logo"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Transcript } from "@/lib/types"
import { useDocumentDownload } from "@/lib/use-document"

/**
 * The multi-year transcript page — built solely from frozen term results, so
 * it always matches the report cards that were issued. Ships with the
 * years-covered picker (default = the COMPLETE record; a subset renders a
 * partial transcript, stamped as such). Download/Print use the official
 * backend PDF; the official document is ensured in the background so the
 * on-sheet QR (which opens the public transcript page) is ready by print time.
 */
export default function TranscriptPrintPage() {
  const { t } = useTranslation("grading")
  const params = useParams<{ student: string }>()
  const { ensure } = useDocumentDownload()

  const [transcript, setTranscript] = useState<Transcript | null>(null)
  const [failed, setFailed] = useState(false)
  // null = all years (the default, full record).
  const [yearIds, setYearIds] = useState<number[] | null>(null)
  const [publicToken, setPublicToken] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    // One URL for every lane: staff endpoint first, then the parent link,
    // then the student's own record (ADR-012 — the backend decides access).
    const lanes = [
      `/reports/students/${params.student}/transcript`,
      `/me/children/${params.student}/transcript`,
      `/me/student/transcript`,
    ]

    void (async () => {
      for (const url of lanes) {
        try {
          const res = await apiFetch<{ data: Transcript }>(url)
          if (cancelled) return
          setTranscript(res.data)
          return
        } catch {
          // Try the next lane.
        }
      }
      if (!cancelled) setFailed(true)
    })()

    return () => {
      cancelled = true
    }
  }, [params.student])

  // Ensure the OFFICIAL document exists for the current year selection —
  // this pre-warms the PDF and yields the public token the QR encodes.
  // Content-hash caching makes repeat selections instant.
  useEffect(() => {
    if (transcript === null) return
    let cancelled = false
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset while the new document resolves
    setPublicToken(null)
    void ensure(
      "transcript",
      Number(params.student),
      yearIds === null ? undefined : { academic_year_ids: yearIds },
    ).then((doc) => {
      if (!cancelled && doc) setPublicToken(doc.public_token)
    })
    return () => {
      cancelled = true
    }
  }, [transcript, yearIds, params.student, ensure])

  // Year narrowing is pure filtering over the loaded record — the article
  // recomputes the partial stamp from years vs available_years.
  const display = useMemo(() => {
    if (transcript === null || yearIds === null) return transcript
    return {
      ...transcript,
      years: transcript.years.filter((y) => yearIds.includes(y.academic_year_id)),
    }
  }, [transcript, yearIds])

  const documentParams =
    yearIds === null ? undefined : { academic_year_ids: yearIds }

  return (
    <div className="bg-muted/30 min-h-svh px-4 py-8 md:py-12 print:bg-white print:p-0">
      <div className="mx-auto max-w-5xl space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
          <Logo />
          {transcript && (
            <div className="flex flex-wrap items-center gap-2">
              <TranscriptYearPicker
                options={(transcript.available_years ?? []).map((year) => ({
                  id: year.academic_year_id,
                  label: t("transcript.yearOption", {
                    year: year.academic_year ?? "—",
                    grade: year.grade_level ?? "—",
                    terms: year.terms_count,
                  }),
                }))}
                value={yearIds}
                onChange={setYearIds}
              />
              <DocumentDownloadButton
                type="transcript"
                subjectId={Number(params.student)}
                params={documentParams}
              />
              <DocumentPrintButton
                type="transcript"
                subjectId={Number(params.student)}
                params={documentParams}
              />
            </div>
          )}
        </div>

        {display === null ? (
          failed ? (
            <div className="bg-card text-muted-foreground rounded-2xl border border-dashed px-6 py-16 text-center text-sm">
              {t("transcript.empty")}
            </div>
          ) : (
            <Skeleton className="h-[36rem] rounded-2xl" />
          )
        ) : (
          <TranscriptArticle transcript={display} publicToken={publicToken} />
        )}
      </div>
    </div>
  )
}
