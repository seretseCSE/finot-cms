"use client"

import { Paperclip, X } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { formatFileSize } from "@/lib/files"
import { useTranslation } from "@/lib/i18n"

/** A picked-but-not-yet-uploaded file with an editable display name (no extension). */
export interface PendingFile {
  file: File
  name: string
}

/** Strip the extension — the editable part of a filename. */
export function baseName(filename: string): string {
  return filename.replace(/\.[^.]+$/, "")
}

export function toPendingFiles(list: FileList | File[] | null): PendingFile[] {
  return Array.from(list ?? []).map((file) => ({ file, name: baseName(file.name) }))
}

/**
 * The File that actually uploads: the edited name + the original extension
 * (kept so server-side mime/extension rules keep working). Renaming happens
 * client-side — `getClientOriginalName()` picks it up with no API change.
 */
export function renamedFile({ file, name }: PendingFile): File {
  const dot = file.name.lastIndexOf(".")
  const ext = dot > 0 ? file.name.slice(dot) : ""
  const base = name.trim() || baseName(file.name)
  const finalName = `${base}${ext}`
  return finalName === file.name
    ? file
    : new File([file], finalName, { type: file.type, lastModified: file.lastModified })
}

/**
 * Rows of freshly picked files, each with a rename field (the platform
 * standard: every upload is renameable before it's sent).
 */
export function PendingFileList({
  items,
  onRename,
  onRemove,
}: {
  items: PendingFile[]
  onRename: (index: number, name: string) => void
  onRemove: (index: number) => void
}) {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")

  if (items.length === 0) return null

  return (
    <>
      {items.map((entry, index) => {
        const dot = entry.file.name.lastIndexOf(".")
        const ext = dot > 0 ? entry.file.name.slice(dot) : ""
        return (
          <div
            key={index}
            className="flex items-center gap-2 rounded-xl border border-dashed px-3 py-2 text-sm"
          >
            <Paperclip className="size-3.5 shrink-0 text-muted-foreground" />
            <Input
              value={entry.name}
              onChange={(e) => onRename(index, e.target.value)}
              placeholder={t("assignments.fileName")}
              aria-label={t("assignments.fileName")}
              className="h-8 min-w-0 flex-1 border-0 bg-transparent px-1 shadow-none focus-visible:ring-0"
            />
            <span className="shrink-0 text-xs text-muted-foreground">
              {ext}
              {entry.file.size ? ` · ${formatFileSize(entry.file.size)}` : ""}
            </span>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-7 shrink-0 text-muted-foreground"
              aria-label={tc("actions.delete")}
              onClick={() => onRemove(index)}
            >
              <X className="size-3.5" />
            </Button>
          </div>
        )
      })}
    </>
  )
}
