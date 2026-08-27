"use client"

import { useState } from "react"

import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { useTranslation } from "@/lib/i18n"

/**
 * The decline decision — a reason is REQUIRED (it is recorded on the plan
 * and notified to the teacher; unexplained declines help nobody).
 */
export function DeclineDialog({
  open,
  onOpenChange,
  onDecline,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  onDecline: (reason: string) => Promise<void>
}) {
  const { t } = useTranslation("lessonPlans")
  const { t: tc } = useTranslation("common")
  const [reason, setReason] = useState("")
  const [working, setWorking] = useState(false)

  async function decline() {
    if (!reason.trim()) return
    setWorking(true)
    try {
      await onDecline(reason.trim())
      setReason("")
      onOpenChange(false)
    } finally {
      setWorking(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t("plan.declineTitle")}</DialogTitle>
          <DialogDescription>{t("plan.declineDescription")}</DialogDescription>
        </DialogHeader>
        <div className="space-y-2">
          <Label htmlFor="decline-reason">{t("plan.declineReason")}</Label>
          <Textarea
            id="decline-reason"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder={t("plan.declineReasonPlaceholder")}
            rows={3}
            className="text-base md:text-sm"
            autoFocus
          />
        </div>
        <DialogFooter>
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
            variant="destructive"
            className="h-11 flex-1"
            loading={working}
            disabled={!reason.trim()}
            onClick={decline}
          >
            {t("plan.decline")}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
