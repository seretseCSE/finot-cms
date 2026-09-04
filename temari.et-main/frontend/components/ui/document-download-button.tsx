"use client"

import { FileDown, Loader2, Printer } from "lucide-react"

import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"
import { useDocumentDownload } from "@/lib/use-document"

/**
 * "Print" for official documents: opens the BACKEND-rendered PDF in a new
 * tab so the browser's PDF viewer prints it pixel-perfect — never the web
 * page itself. Instant when the PDF is already cached.
 */
export function DocumentPrintButton({
  type,
  subjectId,
  params,
  label,
  size = "sm",
  variant = "outline",
  className,
}: {
  type: string
  subjectId?: number
  params?: Record<string, unknown>
  label?: string
  size?: "sm" | "default"
  variant?: "outline" | "default" | "ghost"
  className?: string
}) {
  const { t } = useTranslation("common")
  const { print, generating } = useDocumentDownload()

  return (
    <Button
      variant={variant}
      size={size}
      className={className}
      disabled={generating}
      onClick={() => print(type, subjectId, params)}
    >
      {generating ? (
        <Loader2 className="size-4 animate-spin" />
      ) : (
        <Printer className="size-4" />
      )}
      {generating ? t("documents.generatingShort") : (label ?? t("documents.printPdf"))}
    </Button>
  )
}

/**
 * "Download PDF" for official documents (receipts, letters, transcripts,
 * statements, payslips). Instant when the PDF is already cached; otherwise it
 * shows "Generating — ready in a few seconds" and auto-downloads when done.
 */
export function DocumentDownloadButton({
  type,
  subjectId,
  params,
  label,
  size = "sm",
  variant = "outline",
  className,
}: {
  type: string
  subjectId?: number
  params?: Record<string, unknown>
  label?: string
  size?: "sm" | "default"
  variant?: "outline" | "default" | "ghost"
  className?: string
}) {
  const { t } = useTranslation("common")
  const { download, generating } = useDocumentDownload()

  return (
    <Button
      variant={variant}
      size={size}
      className={className}
      disabled={generating}
      onClick={() => download(type, subjectId, params)}
    >
      {generating ? (
        <Loader2 className="size-4 animate-spin" />
      ) : (
        <FileDown className="size-4" />
      )}
      {generating ? t("documents.generatingShort") : (label ?? t("documents.downloadPdf"))}
    </Button>
  )
}
