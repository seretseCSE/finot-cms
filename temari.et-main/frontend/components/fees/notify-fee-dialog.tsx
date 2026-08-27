"use client"

import { ArrowLeft, Bell, Loader2, Mail, MessageSquare, Send, Users } from "lucide-react"
import { useEffect, useState } from "react"
import { toast } from "sonner"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Switch } from "@/components/ui/switch"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { FeeStructure } from "@/lib/types"

interface NotifyPreview {
  invoices: number
  parents: { recipients: number; sms: number; email: number }
  students: { recipients: number; sms: number; email: number }
}

interface Props {
  fee: FeeStructure | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * On-demand payment notices for a fee that was billed while notifications
 * were off: pick the audience (parents / students), then confirm against the
 * exact recipient counts per channel before anything goes out.
 */
export function NotifyFeeDialog({ fee, open, onOpenChange }: Props) {
  const { t } = useTranslation("fees")
  const { t: tc } = useTranslation("common")

  const [parents, setParents] = useState(true)
  const [students, setStudents] = useState(false)
  const [preview, setPreview] = useState<NotifyPreview | null>(null)
  const [loading, setLoading] = useState(false)
  const [sending, setSending] = useState(false)

  useEffect(() => {
    if (!open) return
    const timer = setTimeout(() => {
      setParents(true)
      setStudents(false)
      setPreview(null)
    }, 0)
    return () => clearTimeout(timer)
  }, [open])

  async function loadPreview() {
    if (!fee) return
    setLoading(true)
    try {
      const res = await apiFetch<{ data: NotifyPreview }>(
        `/fee-structures/${fee.id}/notify-preview?parents=${parents ? 1 : 0}&students=${students ? 1 : 0}`,
      )
      setPreview(res.data)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setLoading(false)
    }
  }

  async function send() {
    if (!fee) return
    setSending(true)
    try {
      await apiFetch(`/fee-structures/${fee.id}/notify`, {
        method: "POST",
        body: { parents, students },
      })
      toast.success(t("notify.sent"))
      onOpenChange(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setSending(false)
    }
  }

  const confirming = preview !== null

  function audienceRow(
    label: string,
    counts: { recipients: number; sms: number; email: number },
  ) {
    return (
      <div className="flex items-center justify-between gap-3 rounded-xl border p-3">
        <div className="flex items-center gap-2.5">
          <span className="flex size-8 items-center justify-center rounded-lg bg-accent">
            <Users className="size-4" />
          </span>
          <div>
            <p className="text-sm font-medium">{label}</p>
            <p className="text-xs text-muted-foreground">
              {t("notify.recipients", { count: counts.recipients })}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-1.5">
          <Badge variant="secondary" className="gap-1 tabular-nums">
            <MessageSquare className="size-3" />
            {counts.sms}
          </Badge>
          <Badge variant="secondary" className="gap-1 tabular-nums">
            <Mail className="size-3" />
            {counts.email}
          </Badge>
        </div>
      </div>
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Bell className="size-4.5" />
            {t("notify.title")}
          </DialogTitle>
          <DialogDescription>
            {confirming
              ? t("notify.confirmHint")
              : t("notify.hint", { name: fee?.name ?? "" })}
          </DialogDescription>
        </DialogHeader>

        {!confirming ? (
          <div className="space-y-3">
            <div className="flex items-center justify-between rounded-xl border p-3.5">
              <div className="space-y-0.5 pr-4">
                <p className="text-sm font-medium">{t("notify.toParents")}</p>
                <p className="text-xs text-muted-foreground">{t("notify.toParentsHint")}</p>
              </div>
              <Switch checked={parents} onCheckedChange={setParents} />
            </div>
            <div className="flex items-center justify-between rounded-xl border p-3.5">
              <div className="space-y-0.5 pr-4">
                <p className="text-sm font-medium">{t("notify.toStudents")}</p>
                <p className="text-xs text-muted-foreground">{t("notify.toStudentsHint")}</p>
              </div>
              <Switch checked={students} onCheckedChange={setStudents} />
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            {/* What is being announced */}
            <div className="rounded-xl border bg-muted/20 p-3 text-sm">
              <p className="font-medium">{fee?.name}</p>
              <p className="text-xs text-muted-foreground">
                {Number(fee?.amount ?? 0).toLocaleString()} ETB
                {fee?.due_on ? ` · ${t("invoices.dueDate")}: ${fee.due_on}` : ""}
                {" · "}
                {t("notify.openInvoices", { count: preview.invoices })}
              </p>
            </div>

            {parents && audienceRow(t("notify.toParents"), preview.parents)}
            {students && audienceRow(t("notify.toStudents"), preview.students)}

            {preview.invoices === 0 ||
            (preview.parents.recipients === 0 && preview.students.recipients === 0) ? (
              <p className="rounded-lg bg-warning/10 px-3 py-2 text-xs text-warning">
                {t("notify.noRecipients")}
              </p>
            ) : (
              <p className="text-xs text-muted-foreground">{t("notify.channelsHint")}</p>
            )}
          </div>
        )}

        <DialogFooter>
          {!confirming ? (
            <>
              <Button variant="outline" onClick={() => onOpenChange(false)}>
                {tc("actions.cancel")}
              </Button>
              <Button onClick={loadPreview} disabled={loading || (!parents && !students)}>
                {loading ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                {tc("actions.continue")}
              </Button>
            </>
          ) : (
            <>
              <Button variant="outline" onClick={() => setPreview(null)} disabled={sending}>
                <ArrowLeft className="size-4" />
                {tc("actions.back")}
              </Button>
              <Button
                onClick={send}
                disabled={
                  sending ||
                  preview.invoices === 0 ||
                  (preview.parents.recipients === 0 && preview.students.recipients === 0)
                }
              >
                {sending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                {t("notify.confirmSend")}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
