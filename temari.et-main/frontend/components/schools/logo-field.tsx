"use client"

import { ImagePlus, X } from "lucide-react"
import { useEffect, useRef, useState } from "react"

import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * Logo picker for the school create/edit sheets (platform staff only — the
 * dialogs themselves are already platform-gated). Holds the picked File in
 * memory with a live preview; the caller uploads it after the school row
 * exists. `currentUrl` shows the existing logo on edit.
 */
export function LogoField({
  currentUrl,
  onChange,
}: {
  currentUrl?: string | null
  onChange: (file: File | null) => void
}) {
  const { t } = useTranslation("schools")
  const inputRef = useRef<HTMLInputElement>(null)
  const [preview, setPreview] = useState<string | null>(null)

  // Object URLs leak without an explicit revoke.
  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview)
  }, [preview])

  function pick(file: File | null) {
    if (preview) URL.revokeObjectURL(preview)
    setPreview(file ? URL.createObjectURL(file) : null)
    onChange(file)
  }

  const shown = preview ?? currentUrl ?? null

  // The tile accepts a dragged image as well as a click.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: "image/*",
    onFiles: ([file]) => pick(file),
  })

  return (
    <div
      {...dropProps}
      className={cn("flex items-center gap-3 rounded-2xl", dragOver && DROP_ACTIVE)}
    >
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        className="bg-muted/40 hover:bg-muted flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-dashed transition-colors"
        aria-label={t("logo.upload")}
      >
        {shown ? (
          // eslint-disable-next-line @next/next/no-img-element -- local preview / signed URL
          <img src={shown} alt="" className="size-full object-contain" />
        ) : (
          <ImagePlus className="text-muted-foreground size-5" />
        )}
      </button>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium">{t("logo.fieldLabel")}</p>
        <p className="text-muted-foreground text-xs">{t("logo.fieldHint")}</p>
      </div>
      {preview && (
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="size-8"
          onClick={() => pick(null)}
          aria-label={t("logo.remove")}
        >
          <X className="size-4" />
        </Button>
      )}
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(e) => {
          takeFiles(e.target.files)
          e.target.value = ""
        }}
      />
    </div>
  )
}
