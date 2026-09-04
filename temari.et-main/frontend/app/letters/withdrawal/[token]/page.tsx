"use client"

import { ShieldCheck } from "lucide-react"
import { useParams } from "next/navigation"
import { useEffect, useState } from "react"

import { WithdrawalLetterArticle } from "@/components/transfers/withdrawal-letter-article"
import { Logo } from "@/components/ui/logo"
import { PublicDocumentActions } from "@/components/ui/public-document-actions"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { WithdrawalLetter } from "@/lib/types"

/**
 * PUBLIC withdrawal-letter verification page — what the QR code on a printed
 * clearance letter opens. No login: the unguessable token in the URL is the
 * only key.
 */
export default function PublicWithdrawalLetterPage() {
  const { t } = useTranslation("transfers")
  const params = useParams<{ token: string }>()

  const [letter, setLetter] = useState<WithdrawalLetter | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: WithdrawalLetter }>(`/public/withdrawal-letters/${params.token}`, {
      anonymous: true,
    })
      .then((res) => !cancelled && setLetter(res.data))
      .catch(() => !cancelled && setFailed(true))
    return () => {
      cancelled = true
    }
  }, [params.token])

  return (
    <div className="min-h-svh bg-muted/30 px-4 py-8 md:py-12">
      <div className="mx-auto max-w-2xl space-y-4">
        <div className="flex items-center justify-between gap-3 print:hidden">
          <Logo />
          {letter && (
            <PublicDocumentActions
              downloadUrl={letter.download_url}
              viewUrl={letter.view_url}
              fallbackLabel={t("letter.print")}
            />
          )}
        </div>

        {letter !== null && (
          <p className="flex items-center gap-1.5 text-xs font-medium text-success print:hidden">
            <ShieldCheck className="size-4" />
            {t("letter.verified")}
          </p>
        )}

        {letter === null ? (
          failed ? (
            <div className="rounded-2xl border border-dashed bg-card px-6 py-16 text-center text-sm text-muted-foreground">
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
