"use client"

import { ShieldX } from "lucide-react"
import { useParams } from "next/navigation"
import { useEffect, useState } from "react"

import { TranscriptArticle } from "@/components/grading/transcript-article"
import { Logo } from "@/components/ui/logo"
import { PublicDocumentActions } from "@/components/ui/public-document-actions"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { PublicTranscript } from "@/lib/types"

/**
 * PUBLIC transcript page — what the QR on every official transcript opens.
 * No login: possession of the paper (its token) is possession of the record,
 * exactly like public receipts. Always renders the AUTHORITATIVE live data
 * with the year coverage the document was issued with; revoking the document
 * kills this page.
 */
export default function PublicTranscriptPage() {
  const { t } = useTranslation("grading")
  const params = useParams<{ token: string }>()

  const [payload, setPayload] = useState<PublicTranscript | null>(null)
  const [failed, setFailed] = useState<"missing" | "revoked" | null>(null)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: PublicTranscript }>(`/public/transcripts/${params.token}`, {
      anonymous: true,
    })
      .then((res) => !cancelled && setPayload(res.data))
      .catch((error) => {
        if (cancelled) return
        setFailed(error instanceof ApiError && error.status === 410 ? "revoked" : "missing")
      })
    return () => {
      cancelled = true
    }
  }, [params.token])

  return (
    <div className="bg-muted/30 min-h-svh px-4 py-8 md:py-12 print:bg-white print:p-0">
      <div className="mx-auto max-w-5xl space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
          <Logo />
          {payload?.download_url && (
            <PublicDocumentActions
              downloadUrl={payload.download_url}
              viewUrl={payload.view_url}
            />
          )}
        </div>

        {payload === null ? (
          failed !== null ? (
            <div className="bg-card rounded-2xl border border-dashed px-6 py-16 text-center">
              {failed === "revoked" && (
                <ShieldX className="text-destructive mx-auto mb-3 size-8" strokeWidth={1.75} />
              )}
              <p className="text-muted-foreground text-sm">
                {failed === "revoked"
                  ? t("transcript.publicRevoked")
                  : t("transcript.publicNotFound")}
              </p>
            </div>
          ) : (
            <Skeleton className="h-[36rem] rounded-2xl" />
          )
        ) : (
          <TranscriptArticle transcript={payload.transcript} publicToken={params.token} />
        )}
      </div>
    </div>
  )
}
