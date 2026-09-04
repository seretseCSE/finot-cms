"use client"

import { useSearchParams } from "next/navigation"
import { Suspense, useEffect, useMemo, useState } from "react"

import { TranscriptArticle } from "@/components/grading/transcript-article"
import {
  DocumentDownloadButton,
  DocumentPrintButton,
} from "@/components/ui/document-download-button"
import { Logo } from "@/components/ui/logo"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { Transcript } from "@/lib/types"

/** Backend cap per batch call — and per batch PDF (one render, one page each). */
const CHUNK = 60
/** Hard cap per print run — keeps the URL and the DOM sane. */
const MAX_IDS = CHUNK

/**
 * Bulk transcript preview: /print/transcripts?year_id=12&ids=1,2,3 shows one
 * sheet per student on screen, and Download/Print hand over to the OFFICIAL
 * backend PDF (`transcript_batch` — the same sheet as a single transcript,
 * one per page). The browser never prints this web page. Data comes from the
 * batch endpoint; access follows the grading dual lane (supervisors, or
 * homeroom teachers for their own class). An optional `years=` narrows every
 * sheet to those academic years (partial transcripts, stamped as such).
 */
function BulkTranscripts() {
  const { t } = useTranslation("grading")
  const searchParams = useSearchParams()
  const yearId = searchParams.get("year_id")
  const idsParam = searchParams.get("ids") ?? ""
  const yearsParam = searchParams.get("years") ?? ""

  const [transcripts, setTranscripts] = useState<Transcript[] | null>(null)
  const [progress, setProgress] = useState({ loaded: 0, total: 0 })
  const [failed, setFailed] = useState(false)

  // The exact batch the PDF must carry — same ids, same order, same narrowing.
  const documentParams = useMemo(() => {
    const ids = [
      ...new Set(idsParam.split(",").map((v) => Number(v)).filter((v) => v > 0)),
    ].slice(0, MAX_IDS)
    const years = yearsParam
      .split(",")
      .map((v) => Number(v))
      .filter((v) => v > 0)

    return {
      academic_year_id: Number(yearId),
      student_ids: ids,
      ...(years.length > 0 ? { academic_year_ids: years } : {}),
    }
  }, [yearId, idsParam, yearsParam])

  useEffect(() => {
    const ids = [...new Set(idsParam.split(",").map((v) => Number(v)).filter((v) => v > 0))].slice(
      0,
      MAX_IDS,
    )
    /* eslint-disable react-hooks/set-state-in-effect -- reset for the new URL params */
    if (!yearId || ids.length === 0) {
      setFailed(true)
      return
    }

    let cancelled = false
    setTranscripts(null)
    setFailed(false)
    setProgress({ loaded: 0, total: ids.length })
    /* eslint-enable react-hooks/set-state-in-effect */

    const yearsQuery = yearsParam
      .split(",")
      .map((v) => Number(v))
      .filter((v) => v > 0)
      .map((v) => `&academic_year_ids[]=${v}`)
      .join("")

    void (async () => {
      const all: Transcript[] = []
      try {
        for (let i = 0; i < ids.length; i += CHUNK) {
          const chunk = ids.slice(i, i + CHUNK)
          const params = chunk.map((id) => `student_ids[]=${id}`).join("&")
          const res = await apiFetch<{ data: Transcript[] }>(
            `/reports/transcripts?academic_year_id=${yearId}&${params}${yearsQuery}`,
          )
          if (cancelled) return
          all.push(...res.data)
          setProgress({ loaded: all.length, total: ids.length })
        }
        if (!cancelled) setTranscripts(all)
      } catch {
        // An official document batch is all-or-nothing — no partial prints.
        if (!cancelled) setFailed(true)
      }
    })()

    return () => {
      cancelled = true
    }
  }, [yearId, idsParam, yearsParam])

  return (
    <div className="bg-muted/30 min-h-svh px-4 py-8 md:py-12 print:bg-white print:p-0">
      <div className="mx-auto max-w-5xl space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
          <Logo />
          {transcripts !== null && (
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-muted-foreground text-xs">
                {t("transcripts.loadedCount", { count: transcripts.length })}
              </span>
              <DocumentDownloadButton type="transcript_batch" params={documentParams} />
              <DocumentPrintButton type="transcript_batch" params={documentParams} />
            </div>
          )}
        </div>

        {failed ? (
          <div className="bg-card text-muted-foreground rounded-2xl border border-dashed px-6 py-16 text-center text-sm print:hidden">
            {t("transcript.empty")}
          </div>
        ) : transcripts === null ? (
          <div className="space-y-4">
            <p className="text-muted-foreground text-center text-xs print:hidden">
              {t("transcripts.printProgress", {
                loaded: progress.loaded,
                total: progress.total,
              })}
            </p>
            <Skeleton className="h-[36rem] rounded-2xl" />
          </div>
        ) : (
          transcripts.map((transcript) => (
            <div
              key={transcript.student.id}
              className="break-after-page last:break-after-auto"
            >
              <TranscriptArticle transcript={transcript} />
            </div>
          ))
        )}
      </div>
    </div>
  )
}

export default function BulkTranscriptsPage() {
  return (
    <Suspense fallback={null}>
      <BulkTranscripts />
    </Suspense>
  )
}
