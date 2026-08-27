"use client"

import { Check, CheckCheck, Clock, ExternalLink, Megaphone, Pencil, SendHorizontal, UserRound, Users, X } from "lucide-react"
import * as React from "react"
import { toast } from "sonner"

import { useChatBase } from "@/components/chat/use-chat"
import { Button } from "@/components/ui/button"
import { Textarea } from "@/components/ui/textarea"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/**
 * The AI → Messages handoff card (the `send_message` block). The model only
 * ever DRAFTS: this card shows the recipient + text, lets the user edit, and
 * the user's own tap sends through the normal chat endpoints — recipient
 * reachability and the communication-book approval gate are re-validated
 * server-side, so a hallucinated id simply errors. Sends are idempotent via
 * a content-derived client_uuid, and the sent state is remembered locally so
 * a re-rendered (or reloaded) block never double-sends.
 */

export interface AiSendBlock {
  to: {
    kind: "family" | "staff" | "channel"
    student_id?: number
    user_id?: number
    conversation_id?: number
    label?: string
  }
  body: string
}

export function parseSendBlock(json: string): AiSendBlock | null {
  try {
    const raw = JSON.parse(json) as AiSendBlock
    const to = raw.to
    const body = typeof raw.body === "string" ? raw.body.trim() : ""
    if (!to || typeof to !== "object" || body === "" || body.length > 5000) return null

    const id = (v: unknown): number | undefined =>
      typeof v === "number" && Number.isInteger(v) && v > 0 ? v : undefined

    const studentId = id(to.student_id)
    const userId = id(to.user_id)
    const conversationId = id(to.conversation_id)

    if (to.kind === "family" && studentId === undefined) return null
    if (to.kind === "staff" && userId === undefined) return null
    if (to.kind === "channel" && conversationId === undefined) return null
    if (to.kind !== "family" && to.kind !== "staff" && to.kind !== "channel") return null

    return {
      to: {
        kind: to.kind,
        student_id: studentId,
        user_id: userId,
        conversation_id: conversationId,
        label: typeof to.label === "string" ? to.label.trim().slice(0, 120) : undefined,
      },
      body,
    }
  } catch {
    return null
  }
}

/** Deterministic RFC-4122-shaped uuid from the block content — the chat
 *  engine's client_uuid dedupe then absorbs any double tap or replay. */
function stableUuid(seed: string): string {
  let h1 = 0x811c9dc5
  let h2 = 0x01000193
  let h3 = 0xdeadbeef
  let h4 = 0xcafebabe
  for (let i = 0; i < seed.length; i++) {
    const c = seed.charCodeAt(i)
    h1 = Math.imul(h1 ^ c, 2654435761)
    h2 = Math.imul(h2 ^ c, 1597334677)
    h3 = Math.imul(h3 ^ c, 2246822519)
    h4 = Math.imul(h4 ^ c, 3266489917)
  }
  const hex = (n: number) => (n >>> 0).toString(16).padStart(8, "0")
  const s = hex(h1) + hex(h2) + hex(h3) + hex(h4)
  return `${s.slice(0, 8)}-${s.slice(8, 12)}-4${s.slice(13, 16)}-8${s.slice(17, 20)}-${s.slice(20, 32)}`
}

type SentState = { conversationId: number; pending: boolean }

function rememberSent(uuid: string, state: SentState) {
  try {
    window.localStorage.setItem(`ai-sent:${uuid}`, JSON.stringify(state))
  } catch {
    // Storage full/blocked — the server-side client_uuid dedupe still holds.
  }
}

function recallSent(uuid: string): SentState | null {
  try {
    const raw = window.localStorage.getItem(`ai-sent:${uuid}`)
    if (!raw) return null
    const parsed = JSON.parse(raw) as SentState
    return typeof parsed.conversationId === "number" ? parsed : null
  } catch {
    return null
  }
}

export function AiSendMessageCard({ block }: { block: AiSendBlock }) {
  const { t } = useTranslation("ai")
  const base = useChatBase()

  const uuid = React.useMemo(
    () => stableUuid(`${block.to.kind}:${block.to.student_id ?? ""}:${block.to.user_id ?? ""}:${block.to.conversation_id ?? ""}:${block.body}`),
    [block],
  )

  const [body, setBody] = React.useState(block.body)
  const [editing, setEditing] = React.useState(false)
  const [busy, setBusy] = React.useState(false)
  // The block re-renders forever inside the stored AI message — restore the
  // remembered sent state so the card never offers a re-send after reload.
  const [sent, setSent] = React.useState<SentState | null>(() =>
    typeof window === "undefined" ? null : recallSent(uuid),
  )
  const [expanded, setExpanded] = React.useState(false)

  const Icon = block.to.kind === "family" ? Users : block.to.kind === "channel" ? Megaphone : UserRound

  const send = async () => {
    if (busy || sent) return
    setBusy(true)
    try {
      let conversationId = block.to.conversation_id ?? null

      if (block.to.kind !== "channel") {
        const res = await apiFetch<{ data: { id: number } }>(`${base}/conversations`, {
          method: "POST",
          body: {
            kind: "direct",
            ...(block.to.student_id ? { student_id: block.to.student_id } : {}),
            ...(block.to.kind === "staff" && block.to.user_id ? { user_id: block.to.user_id } : {}),
          },
        })
        conversationId = res.data.id
      }

      const message = await apiFetch<{ data: { status?: string } }>(
        `${base}/conversations/${conversationId}/messages`,
        { method: "POST", body: { body: body.trim(), client_uuid: uuid } },
      )

      const state: SentState = {
        conversationId: conversationId as number,
        pending: message.data.status === "pending",
      }
      rememberSent(uuid, state)
      setSent(state)
      setEditing(false)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : t("sendCard.failed"))
    } finally {
      setBusy(false)
    }
  }

  const long = body.length > 480
  const kindLabel = t(`sendCard.kind.${block.to.kind}`)

  return (
    <div className="my-3 overflow-hidden rounded-2xl border bg-card shadow-xs">
      {/* Recipient */}
      <div className="flex items-center gap-2.5 border-b bg-muted/40 px-3.5 py-2.5">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
          <Icon className="size-4" aria-hidden />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-medium">{block.to.label ?? kindLabel}</span>
          <span className="block truncate text-xs text-muted-foreground">
            {sent ? (sent.pending ? t("sendCard.pendingNote") : t("sendCard.sentNote")) : kindLabel}
          </span>
        </span>
        {sent ? (
          <span
            className={cn(
              "inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium",
              sent.pending ? "bg-amber-500/15 text-amber-700 dark:text-amber-400" : "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400",
            )}
          >
            {sent.pending ? <Clock className="size-3.5" aria-hidden /> : <CheckCheck className="size-3.5" aria-hidden />}
            {sent.pending ? t("sendCard.pending") : t("sendCard.sent")}
          </span>
        ) : (
          <Button
            variant="ghost"
            size="icon"
            className="size-8 rounded-full"
            onClick={() => setEditing((v) => !v)}
            title={editing ? t("sendCard.cancelEdit") : t("sendCard.edit")}
            aria-label={editing ? t("sendCard.cancelEdit") : t("sendCard.edit")}
          >
            {editing ? <X className="size-4" /> : <Pencil className="size-4" />}
          </Button>
        )}
      </div>

      {/* Body */}
      <div className="px-3.5 py-3">
        {editing ? (
          <Textarea
            value={body}
            onChange={(e) => setBody(e.target.value)}
            maxLength={5000}
            rows={Math.min(14, Math.max(5, body.split("\n").length + 1))}
            className="text-sm"
            aria-label={t("sendCard.bodyLabel")}
          />
        ) : (
          <>
            <p
              className={cn(
                "text-sm whitespace-pre-wrap",
                long && !expanded && "line-clamp-6",
                sent && "text-muted-foreground",
              )}
            >
              {body}
            </p>
            {long && (
              <button
                type="button"
                className="mt-1 text-xs font-medium text-primary"
                onClick={() => setExpanded((v) => !v)}
              >
                {expanded ? t("sendCard.showLess") : t("sendCard.showMore")}
              </button>
            )}
          </>
        )}
      </div>

      {/* Action */}
      <div className="flex items-center gap-2 border-t px-3.5 py-2.5">
        {sent ? (
          <a
            href={`/messages?c=${sent.conversationId}`}
            className="inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-primary"
          >
            <ExternalLink className="size-4" aria-hidden />
            {t("sendCard.open")}
          </a>
        ) : (
          <>
            <Button
              className="h-10 flex-1 rounded-full"
              onClick={() => void send()}
              loading={busy}
              disabled={body.trim() === ""}
            >
              {!busy && <SendHorizontal className="size-4" aria-hidden />}
              {t("sendCard.send")}
            </Button>
            {editing && (
              <Button
                variant="outline"
                className="h-10 rounded-full"
                disabled={busy}
                onClick={() => {
                  setBody(block.body)
                  setEditing(false)
                }}
              >
                {t("sendCard.reset")}
              </Button>
            )}
          </>
        )}
        {sent && !sent.pending && <Check className="ms-auto size-4 text-emerald-600" aria-hidden />}
      </div>
    </div>
  )
}
