"use client"

import { FileDown, Printer } from "lucide-react"

import { Button } from "@/components/ui/button"
import { useTranslation } from "@/lib/i18n"

/**
 * Download / Print for a PUBLIC token page (receipt, letters, transcript,
 * roster). Both buttons hand over to the OFFICIAL backend-rendered PDF —
 * downloading saves it, printing opens it inline so the browser's PDF viewer
 * prints the real document instead of the web page around it.
 *
 * `fallbackLabel` covers records issued before the PDF existed (or a document
 * still rendering): the page's own print stays available rather than leaving
 * the visitor with no way to put the paper in their hand.
 */
export function PublicDocumentActions({
  downloadUrl,
  viewUrl,
  fallbackLabel,
}: {
  downloadUrl?: string | null
  viewUrl?: string | null
  fallbackLabel?: string
}) {
  const { t } = useTranslation("common")
  const inlineUrl = viewUrl ?? downloadUrl

  if (!downloadUrl && !inlineUrl) {
    // Deliberate last resort: this branch only runs when the record has NO generated
    // PDF at all (issued before the document pipeline existed, or still rendering).
    // Printing the page is worse than the official PDF, but far better than leaving
    // the visitor with no way to put the paper in their hand.
    // eslint-disable-next-line no-restricted-syntax -- documented fallback, see above
    const printPage = () => window.print()

    return (
      <Button variant="outline" size="sm" onClick={printPage}>
        <Printer className="size-4" />
        {fallbackLabel ?? t("documents.printPdf")}
      </Button>
    )
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      {downloadUrl && (
        <Button variant="outline" size="sm" asChild>
          <a href={downloadUrl}>
            <FileDown className="size-4" />
            {t("documents.downloadPdf")}
          </a>
        </Button>
      )}
      {inlineUrl && (
        <Button variant="outline" size="sm" asChild>
          <a href={inlineUrl} target="_blank" rel="noreferrer">
            <Printer className="size-4" />
            {t("documents.printPdf")}
          </a>
        </Button>
      )}
    </div>
  )
}
