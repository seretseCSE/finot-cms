"use client"

import { useCallback, useRef, useState } from "react"
import { toast } from "sonner"

import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"

export interface DocumentStatus {
  id: number
  type: string
  status: "queued" | "processing" | "ready" | "failed"
  public_token: string
  url: string | null
  /** Inline-disposition variant — displays in the tab instead of downloading. */
  view_url?: string | null
  error: string | null
}

const POLL_MS = 1500
const TIMEOUT_MS = 90_000

/**
 * Official-PDF lane. POST /documents answers instantly when the stored PDF is
 * still current; otherwise the backend renders it (seconds) while we poll and
 * keep the user informed — never a frozen button, never a broken browser
 * print dialog.
 *
 * - `download` saves the PDF (navigates to the signed URL).
 * - `print` opens the PDF in a new tab so the browser's PDF viewer prints it
 *   pixel-perfect — always the official document, never the web page. The tab
 *   is opened ONLY once the PDF is ready — never a blank tab that later flips
 *   to a download, which reads as broken. The caller shows a loading
 *   indicator via `generating` while we wait.
 * - `ensure` resolves the ready document silently (e.g. to obtain the public
 *   token for an on-screen QR) without navigating anywhere.
 */
export function useDocumentDownload() {
  const { t } = useTranslation("common")
  const [generating, setGenerating] = useState(false)
  const active = useRef(false)

  /** Poll until the document is ready. Throws on failure/timeout. */
  const resolveReady = useCallback(
    async (
      type: string,
      subjectId?: number,
      params?: Record<string, unknown>,
      onPending?: () => void,
    ): Promise<DocumentStatus> => {
      let doc = (
        await apiFetch<{ data: DocumentStatus }>("/documents", {
          method: "POST",
          body: { type, subject_id: subjectId ?? null, params: params ?? {} },
        })
      ).data

      if (doc.status !== "ready") onPending?.()

      const started = Date.now()
      while (doc.status === "queued" || doc.status === "processing") {
        if (Date.now() - started > TIMEOUT_MS) {
          throw new Error(t("documents.timeout"))
        }
        await new Promise((resolve) => setTimeout(resolve, POLL_MS))
        doc = (await apiFetch<{ data: DocumentStatus }>(`/documents/${doc.id}`)).data
      }

      if (doc.status !== "ready" || !doc.url) {
        throw new Error(doc.error ?? t("documents.failed"))
      }
      return doc
    },
    [t],
  )

  /** Shared download/print flow — differs only in what happens with the URL. */
  const run = useCallback(
    async (
      mode: "download" | "print",
      type: string,
      subjectId?: number,
      params?: Record<string, unknown>,
    ) => {
      if (active.current) return
      active.current = true
      setGenerating(true)
      try {
        let pending = false
        // Wait with a loading indicator (`generating`) — no tab is opened
        // until the PDF actually exists, so the user never sees a blank tab
        // that later flips to a download.
        const doc = await resolveReady(type, subjectId, params, () => {
          pending = true
          toast.info(t("documents.generating"), { id: `doc-${type}` })
        })
        if (pending) toast.success(t("documents.ready"), { id: `doc-${type}` })
        if (mode === "print") {
          // Inline URL: the new tab SHOWS the PDF (print from the viewer).
          // The cached case resolves within the click's activation window, so
          // popup blockers stay out of the way; a slow render that outlives it
          // falls back to an actionable toast rather than a silent no-op.
          const viewUrl = (doc.view_url ?? doc.url) as string
          const tab = window.open(viewUrl, "_blank")
          if (!tab) {
            toast.error(t("documents.popupBlocked"), {
              id: `doc-${type}`,
              action: {
                label: t("documents.openDocument"),
                onClick: () => window.open(viewUrl, "_blank"),
              },
            })
          }
        } else {
          window.location.assign(doc.url as string)
        }
      } catch (error) {
        toast.error(
          error instanceof ApiError || error instanceof Error
            ? error.message
            : t("documents.failed"),
          { id: `doc-${type}` },
        )
      } finally {
        active.current = false
        setGenerating(false)
      }
    },
    [t, resolveReady],
  )

  const download = useCallback(
    (type: string, subjectId?: number, params?: Record<string, unknown>) =>
      run("download", type, subjectId, params),
    [run],
  )

  const print = useCallback(
    (type: string, subjectId?: number, params?: Record<string, unknown>) =>
      run("print", type, subjectId, params),
    [run],
  )

  /** Silent resolve (no toasts, no navigation). Null when it fails. */
  const ensure = useCallback(
    async (
      type: string,
      subjectId?: number,
      params?: Record<string, unknown>,
    ): Promise<DocumentStatus | null> => {
      try {
        return await resolveReady(type, subjectId, params)
      } catch {
        return null
      }
    },
    [resolveReady],
  )

  return { download, print, ensure, generating }
}
