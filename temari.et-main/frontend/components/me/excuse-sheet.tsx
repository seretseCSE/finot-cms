"use client"

import { Paperclip } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import {
  PendingFileList,
  renamedFile,
  toPendingFiles,
  type PendingFile,
} from "@/components/lms/pending-files"
import { Button } from "@/components/ui/button"
import { DatePicker } from "@/components/ui/date-picker"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetDescription,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const EXCUSE_FILE_ACCEPT = ".pdf,.jpg,.jpeg,.png,.webp"
const EXCUSE_MAX_BYTES = 10 * 1024 * 1024

/**
 * Parent files an absence excuse for a child: date range + reason + optional
 * proof document (renameable before sending, per the upload standard). Lands
 * as PENDING in the branch's review queue.
 */
export function ExcuseSheet({
  studentId,
  open,
  onOpenChange,
  onFiled,
}: {
  studentId: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
  onFiled: () => void
}) {
  const { t } = useTranslation("me")
  const { t: tc } = useTranslation("common")

  const [startsOn, setStartsOn] = useState("")
  const [endsOn, setEndsOn] = useState("")
  const [reason, setReason] = useState("")
  const [files, setFiles] = useState<PendingFile[]>([])
  const [submitting, setSubmitting] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)

  // One proof file, picked or dropped — renameable before it is sent.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    accept: EXCUSE_FILE_ACCEPT,
    maxSize: EXCUSE_MAX_BYTES,
    onFiles: (picked) => setFiles(toPendingFiles(picked)),
  })

  useEffect(() => {
    if (!open) return
    const timer = setTimeout(() => {
      setStartsOn("")
      setEndsOn("")
      setReason("")
      setFiles([])
    }, 0)
    return () => clearTimeout(timer)
  }, [open])

  const canSubmit =
    startsOn !== "" && endsOn !== "" && endsOn >= startsOn && reason.trim().length > 0

  async function submit() {
    if (studentId === null || !canSubmit) return
    setSubmitting(true)
    try {
      const body = new FormData()
      body.append("starts_on", startsOn)
      body.append("ends_on", endsOn)
      body.append("reason", reason.trim())
      if (files[0]) body.append("attachment", renamedFile(files[0]))

      await apiFetch(`/me/children/${studentId}/absence-excuses`, { method: "POST", body })
      toast.success(t("excuses.filed"))
      onFiled()
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <ResponsiveSheet open={open} onOpenChange={onOpenChange}>
      <ResponsiveSheetContent>
        <ResponsiveSheetHeader>
          <ResponsiveSheetTitle>{t("excuses.newTitle")}</ResponsiveSheetTitle>
          <ResponsiveSheetDescription>{t("excuses.newDesc")}</ResponsiveSheetDescription>
        </ResponsiveSheetHeader>

        <ResponsiveSheetBody className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label>{t("excuses.from")}</Label>
              <DatePicker value={startsOn} onChange={setStartsOn} />
            </div>
            <div className="space-y-1.5">
              <Label>{t("excuses.to")}</Label>
              <DatePicker value={endsOn} onChange={setEndsOn} min={startsOn || undefined} />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label>{t("excuses.reason")}</Label>
            <Textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={4}
              maxLength={1000}
              placeholder={t("excuses.reasonPlaceholder")}
            />
          </div>

          <div
            {...dropProps}
            className={cn("space-y-1.5 rounded-2xl", dragOver && DROP_ACTIVE)}
          >
            <Label>{t("excuses.attachment")}</Label>
            <input
              ref={fileInput}
              type="file"
              accept={EXCUSE_FILE_ACCEPT}
              className="hidden"
              onChange={(e) => {
                takeFiles(e.target.files)
                e.target.value = ""
              }}
            />
            {files.length === 0 ? (
              <Button
                type="button"
                variant="outline"
                className="w-full justify-start"
                onClick={() => fileInput.current?.click()}
              >
                <Paperclip className="size-4" />
                {t("excuses.attach")}
              </Button>
            ) : (
              <PendingFileList
                items={files}
                onRename={(index, name) =>
                  setFiles((prev) => prev.map((f, i) => (i === index ? { ...f, name } : f)))
                }
                onRemove={() => setFiles([])}
              />
            )}
            <p className="text-xs text-muted-foreground">{t("excuses.attachHint")}</p>
          </div>
        </ResponsiveSheetBody>

        <ResponsiveSheetFooter>
          <Button
            type="button"
            variant="outline"
            className="h-11 flex-1"
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            type="button"
            className="h-11 flex-1"
            onClick={submit}
            loading={submitting} disabled={!canSubmit}
          >
            {submitting ? tc("actions.saving") : t("excuses.submit")}
          </Button>
        </ResponsiveSheetFooter>
      </ResponsiveSheetContent>
    </ResponsiveSheet>
  )
}
