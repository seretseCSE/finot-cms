"use client"

import { FileText, Paperclip, Trash2 } from "lucide-react"
import { useRef } from "react"

import { DocumentCategorySelect } from "@/components/students/document-category"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, DropHint, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

import type { DraftDocument } from "./schema"

export const ACCEPTED_FILES = ".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
export const MAX_FILE_BYTES = 10 * 1024 * 1024

interface Props {
  documents: DraftDocument[]
  onDocumentsChange: (docs: DraftDocument[]) => void
}

/** Documents are staged locally and uploaded after the student is saved. */
export function StepDocuments({ documents, onDocumentsChange }: Props) {
  const { t } = useTranslation("students")
  const { t: tc } = useTranslation("common")
  const fileInput = useRef<HTMLInputElement>(null)

  // Picked or dropped, every document keeps its rename + category row.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: ACCEPTED_FILES,
    maxSize: MAX_FILE_BYTES,
    multiple: true,
    onFiles: (files) =>
      onDocumentsChange([
        ...documents,
        ...files.map((file) => ({
          id: crypto.randomUUID(),
          name: file.name.replace(/\.[^.]+$/, ""),
          category: "",
          file,
        })),
      ]),
  })

  return (
    <section {...dropProps} className={cn("space-y-3 rounded-2xl", dragOver && DROP_ACTIVE)}>
      <p className="text-xs text-muted-foreground">{t("wizard.documentsHint")}</p>

      <div className="space-y-2">
        {documents.map((doc) => (
          <div key={doc.id} className="flex items-start gap-3 rounded-xl border px-3 py-2">
            <FileText className="mt-2.5 size-4 shrink-0 text-muted-foreground" />
            <div className="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-2">
              <Input
                value={doc.name}
                onChange={(e) =>
                  onDocumentsChange(
                    documents.map((d) => (d.id === doc.id ? { ...d, name: e.target.value } : d)),
                  )
                }
                className="h-9"
                placeholder={t("wizard.documentNamePlaceholder")}
              />
              <DocumentCategorySelect
                value={doc.category}
                onChange={(category) =>
                  onDocumentsChange(
                    documents.map((d) => (d.id === doc.id ? { ...d, category } : d)),
                  )
                }
              />
              <span className="truncate text-xs text-muted-foreground sm:col-span-2">
                {doc.file.name}
              </span>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="size-8 shrink-0 rounded-full text-destructive"
              onClick={() => onDocumentsChange(documents.filter((d) => d.id !== doc.id))}
              aria-label={tc("actions.delete")}
            >
              <Trash2 className="size-4" />
            </Button>
          </div>
        ))}
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          variant="outline"
          className="h-10 rounded-full"
          onClick={() => fileInput.current?.click()}
        >
          <Paperclip className="size-4" />
          {t("wizard.attachDocument")}
        </Button>
        <DropHint />
      </div>
      <input
        ref={fileInput}
        type="file"
        accept={ACCEPTED_FILES}
        multiple
        className="hidden"
        onChange={(e) => {
          takeFiles(e.target.files)
          e.target.value = ""
        }}
      />
    </section>
  )
}
