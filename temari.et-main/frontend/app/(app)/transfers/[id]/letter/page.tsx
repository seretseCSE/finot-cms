"use client"

import { useParams } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { TransferLetterArticle } from "@/components/transfers/letter-article"
import {
  DocumentDownloadButton,
  DocumentPrintButton,
} from "@/components/ui/document-download-button"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { TransferLetter } from "@/lib/types"

/**
 * The printable transfer letter — the digital replacement for the paper
 * የመልቀቂያ ደብዳቤ. Carries a QR code that opens the public verification copy.
 */
export default function TransferLetterPage() {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()

  const [letter, setLetter] = useState<TransferLetter | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: TransferLetter }>(`/transfer-requests/${params.id}/letter`)
      .then((res) => !cancelled && setLetter(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setFailed(true)
      })
    return () => {
      cancelled = true
    }
  }, [params.id, tc])

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("letter.title")}
        backHref="/transfers"
        actions={
          letter ? (
            <div className="flex items-center gap-2">
              <DocumentDownloadButton
                type="transfer_letter"
                subjectId={Number(params.id)}
              />
              <DocumentPrintButton
                type="transfer_letter"
                subjectId={Number(params.id)}
                variant="default"
                label={t("letter.print")}
              />
            </div>
          ) : undefined
        }
      />

      <div className="page-gutter">
        {letter === null ? (
          failed ? (
            <div className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
              {tc("errors.generic")}
            </div>
          ) : (
            <Skeleton className="mx-auto h-[32rem] max-w-2xl rounded-2xl" />
          )
        ) : (
          <TransferLetterArticle letter={letter} />
        )}
      </div>
    </div>
  )
}
