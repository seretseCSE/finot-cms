"use client"

import { FileDown, ShieldCheck, ShieldX } from "lucide-react"
import { useParams } from "next/navigation"
import { useEffect, useState } from "react"

import { Button } from "@/components/ui/button"
import { Logo } from "@/components/ui/logo"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

interface VerifyPayload {
  type: string
  status: "valid" | "revoked"
  issued_on: string | null
  summary: Record<string, string | null>
  download_url: string | null
}

/**
 * PUBLIC document verification — what the QR on any backend-generated
 * official PDF (transcript, report card, statement, payslip, …) opens.
 * Proves authenticity with a minimal summary; never marks or salaries.
 */
export default function VerifyDocumentPage() {
  const { t } = useTranslation("common")
  const params = useParams<{ token: string }>()

  const [payload, setPayload] = useState<VerifyPayload | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: VerifyPayload }>(`/public/documents/${params.token}`, {
      anonymous: true,
    })
      .then((res) => !cancelled && setPayload(res.data))
      .catch(() => !cancelled && setFailed(true))
    return () => {
      cancelled = true
    }
  }, [params.token])

  const valid = payload?.status === "valid"

  return (
    <div className="min-h-svh bg-muted/30 px-4 py-8 md:py-12">
      <div className="mx-auto max-w-lg space-y-4">
        <Logo />

        {payload === null ? (
          failed ? (
            <div className="rounded-2xl border border-dashed bg-card px-6 py-16 text-center text-sm text-muted-foreground">
              {t("documents.verifyNotFound")}
            </div>
          ) : (
            <Skeleton className="h-72 rounded-2xl" />
          )
        ) : (
          <section className="overflow-hidden rounded-2xl border bg-card shadow-xs">
            <div
              className={cn(
                "flex items-center gap-3 px-5 py-4",
                valid ? "bg-success/10" : "bg-destructive/10"
              )}
            >
              {valid ? (
                <ShieldCheck className="size-6 text-success" strokeWidth={1.75} />
              ) : (
                <ShieldX className="size-6 text-destructive" strokeWidth={1.75} />
              )}
              <div>
                <p
                  className={cn(
                    "text-sm font-semibold",
                    valid ? "text-success" : "text-destructive"
                  )}
                >
                  {t(valid ? "documents.verifyValid" : "documents.verifyRevoked")}
                </p>
                <p className="text-xs text-muted-foreground">
                  {t(`documents.types.${payload.type}`)}
                  {payload.issued_on ? ` · ${payload.issued_on}` : ""}
                </p>
              </div>
            </div>

            <dl className="divide-y px-5 py-2">
              {Object.entries(payload.summary)
                .filter(([, value]) => value)
                .map(([key, value]) => (
                  <div
                    key={key}
                    className="flex items-baseline justify-between gap-4 py-2.5 text-sm"
                  >
                    <dt className="text-muted-foreground">
                      {(() => {
                        const label = t(`documents.fields.${key}`)
                        return label.startsWith("documents.")
                          ? key.replace(/_/g, " ")
                          : label
                      })()}
                    </dt>
                    <dd className="text-right font-medium">{value}</dd>
                  </div>
                ))}
            </dl>

            {payload.download_url && (
              <div className="border-t p-4">
                <Button asChild className="w-full">
                  <a href={payload.download_url}>
                    <FileDown className="size-4" />
                    {t("documents.downloadPdf")}
                  </a>
                </Button>
              </div>
            )}
          </section>
        )}

        <p className="text-center text-xs text-muted-foreground">
          {t("documents.verifyFooter")}
        </p>
      </div>
    </div>
  )
}
