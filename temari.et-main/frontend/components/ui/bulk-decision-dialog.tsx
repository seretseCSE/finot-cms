"use client"

import { useEffect, useState, type ReactNode } from "react"
import { toast } from "sonner"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { useTranslation } from "@/lib/i18n"

/**
 * Approve or reject a whole selection in one decision — the shared dialog for
 * every review queue (leave, absence excuses, fee concessions, expenses,
 * transfers). A rejection always says why, so the note is required there and
 * optional on approval, exactly like the single-row dialogs.
 *
 * `extra` is the per-queue slot: the balance override on leave, "apply to open
 * invoices" on concessions. Everything else is identical across queues, which
 * is the point — a reviewer learns the sweep once.
 */
export function BulkDecisionDialog({
  open,
  onOpenChange,
  mode,
  title,
  description,
  noteLabel,
  notePlaceholder,
  confirmLabel,
  extra,
  onConfirm,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  mode: "approve" | "reject"
  title: string
  description: string
  noteLabel: string
  notePlaceholder: string
  confirmLabel: string
  /** Per-queue controls rendered under the note. */
  extra?: ReactNode
  onConfirm: (note: string) => Promise<void>
}) {
  const { t: tc } = useTranslation("common")
  const [note, setNote] = useState("")
  const [submitting, setSubmitting] = useState(false)

  // Never carry one decision's note into the next selection.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- clear the form each time it opens
    if (open) setNote("")
  }, [open])

  async function submit() {
    if (mode === "reject" && !note.trim()) {
      toast.error(notePlaceholder)

      return
    }
    setSubmitting(true)
    try {
      await onConfirm(note.trim())
      onOpenChange(false)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          <div className="space-y-1.5">
            <label className="text-sm font-medium" htmlFor="bulk-decision-note">
              {noteLabel}
            </label>
            <textarea
              id="bulk-decision-note"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={3}
              placeholder={notePlaceholder}
              className="border-input/70 bg-muted/30 placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full resize-none rounded-xl border px-3.5 py-2.5 text-base outline-none focus-visible:ring-3 md:text-sm"
            />
          </div>
          {extra}
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            className="h-11 w-full sm:w-auto"
            disabled={submitting}
            onClick={() => onOpenChange(false)}
          >
            {tc("actions.cancel")}
          </Button>
          <Button
            onClick={submit}
            loading={submitting}
            variant={mode === "reject" ? "destructive" : "default"}
            className="h-11 w-full sm:w-auto"
          >
            {confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
