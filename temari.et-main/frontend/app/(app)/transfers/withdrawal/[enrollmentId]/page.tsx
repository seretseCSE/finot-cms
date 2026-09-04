"use client"

import { useParams } from "next/navigation"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { WithdrawalLetterArticle } from "@/components/transfers/withdrawal-letter-article"
import {
  DocumentDownloadButton,
  DocumentPrintButton,
} from "@/components/ui/document-download-button"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { WithdrawalLetter } from "@/lib/types"

/**
 * The printable withdrawal clearance letter — issued when a student leaves
 * the school or moves to a school outside Temari. Carries a QR code that
 * opens the public verification copy.
 */
export default function WithdrawalLetterPage() {
  const { t } = useTranslation("transfers")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ enrollmentId: string }>()

  const [letter, setLetter] = useState<WithdrawalLetter | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: WithdrawalLetter }>(`/enrollments/${params.enrollmentId}/withdrawal-letter`)
      .then((res) => !cancelled && setLetter(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
        setFailed(true)
      })
    return () => {
      cancelled = true
    }
  }, [params.enrollmentId, tc])

  return (
    <div className="space-y-6">
      <PageHeader
        title={t("withdrawal.letterTitle")}
        backHref="/students"
        actions={
          letter ? (
            <div className="flex items-center gap-2">
              <DocumentDownloadButton
                type="withdrawal_letter"
                subjectId={letter.id}
              />
              <DocumentPrintButton
                type="withdrawal_letter"
                subjectId={letter.id}
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
              {t("letter.notFound")}
            </div>
          ) : (
            <Skeleton className="h-[32rem] rounded-2xl" />
          )
        ) : (
          <WithdrawalLetterArticle letter={letter} />
        )}
      </div>
    </div>
  )
}
