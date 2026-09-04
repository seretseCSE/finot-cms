"use client"

import { Megaphone, Mic, NotebookText, Paperclip, Play, Send, Square, X } from "lucide-react"
import { useEffect, useMemo, useRef, useState } from "react"
import { toast } from "sonner"

import { AttachmentIcon } from "@/components/ui/attachment"
import { Button } from "@/components/ui/button"
import { DROP_ACTIVE, useFileDrop } from "@/components/ui/dropzone"
import { Input } from "@/components/ui/input"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { Switch } from "@/components/ui/switch"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { fileKind, formatFileSize } from "@/lib/files"
import type { ChatAttachment, ChatConversation, ChatMessage, ChatTemplatePick } from "@/lib/types"
import { cn } from "@/lib/utils"

import { uploadChatFile, useChatBase } from "./use-chat"

interface PendingFile {
  file: File
  name: string
  uploading: boolean
  /** Local object URL for image/video files so we can show a thumbnail before upload. */
  previewUrl?: string
}

/**
 * The chat composer: autosizing textarea, attachments with rename-before-send
 * (the platform upload standard), @-mention autocomplete over the member
 * list, a hold-free tap-to-record voice note (Telegram-style — the
 * low-literacy channel that matters most in Ethiopia), reply context, and
 * the emergency toggle for chat.announce holders in announcement channels.
 */
export function Composer({
  conversation,
  replyTo,
  onCancelReply,
  onSend,
  canEmergency,
}: {
  conversation: ChatConversation
  replyTo: ChatMessage | null
  onCancelReply: () => void
  onSend: (payload: {
    body?: string
    attachments?: ChatAttachment[]
    kind?: "text" | "voice"
    reply_to_id?: number
    emergency?: boolean
  }) => Promise<void>
  canEmergency: boolean
}) {
  const { t } = useTranslation("chat")
  const base = useChatBase()
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [body, setBody] = useState("")
  const [pendingFiles, setPendingFiles] = useState<PendingFile[]>([])
  // Mirror the latest pending files for the unmount-only cleanup effect
  // (revoking object URLs) — synced in an effect, never mutated during render.
  const pendingFilesRef = useRef<PendingFile[]>([])
  useEffect(() => {
    pendingFilesRef.current = pendingFiles
  }, [pendingFiles])
  const [sending, setSending] = useState(false)
  const [emergency, setEmergency] = useState(false)

  // @-mention state: the token being typed and its position.
  const [mentionQuery, setMentionQuery] = useState<string | null>(null)
  const mentionsRef = useRef<Map<string, number>>(new Map())

  // School preset messages (staff lane only) — fetched lazily on first open,
  // placeholder-resolved server-side in the family's language.
  const isStaffLane = base === "/chat"
  const [templatesOpen, setTemplatesOpen] = useState(false)
  const [templates, setTemplates] = useState<ChatTemplatePick[] | null>(null)
  const [templatesRequired, setTemplatesRequired] = useState(false)

  useEffect(() => {
    // A different thread resolves to different text — refetch on next open.
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset per conversation
    setTemplates(null)
    setTemplatesOpen(false)
  }, [conversation.id])

  function toggleTemplates() {
    setTemplatesOpen((open) => !open)
    if (templates !== null) return
    apiFetch<{ data: ChatTemplatePick[]; meta: { required: boolean } }>(
      `/chat/templates?conversation_id=${conversation.id}`,
    )
      .then((res) => {
        setTemplates(res.data)
        setTemplatesRequired(res.meta.required)
      })
      .catch(() => setTemplates([]))
  }

  /** Insert a preset's resolved text; the effect below refocuses + resizes. */
  const [templatePickTick, setTemplatePickTick] = useState(0)
  const pickTemplate = (template: ChatTemplatePick) => {
    setBody(template.resolved_body)
    setTemplatesOpen(false)
    setTemplatePickTick((tick) => tick + 1)
  }

  useEffect(() => {
    if (templatePickTick === 0) return
    textareaRef.current?.focus()
    const el = textareaRef.current
    if (el) {
      el.style.height = "auto"
      el.style.height = `${Math.min(el.scrollHeight, 160)}px`
    }
  }, [templatePickTick])

  // Voice recording.
  const [recording, setRecording] = useState(false)
  const [recordSeconds, setRecordSeconds] = useState(0)
  const recorderRef = useRef<MediaRecorder | null>(null)
  const chunksRef = useRef<Blob[]>([])

  const mentionCandidates = useMemo(() => {
    if (mentionQuery === null || conversation.kind === "direct") return []
    const q = mentionQuery.toLowerCase()
    return (conversation.members ?? [])
      .filter((m) => !m.left && m.name.toLowerCase().includes(q))
      .slice(0, 6)
  }, [mentionQuery, conversation.members, conversation.kind])

  useEffect(() => {
    if (replyTo) textareaRef.current?.focus()
  }, [replyTo])

  function autosize() {
    const el = textareaRef.current
    if (!el) return
    el.style.height = "auto"
    el.style.height = `${Math.min(el.scrollHeight, 160)}px`
  }

  function handleBodyChange(value: string) {
    setBody(value)
    const el = textareaRef.current
    const caret = el?.selectionStart ?? value.length
    const upToCaret = value.slice(0, caret)
    const match = /(?:^|\s)@([\p{L}\p{N} ]{0,24})$/u.exec(upToCaret)
    setMentionQuery(match ? match[1] : null)
  }

  function pickMention(member: { id: number; name: string }) {
    const el = textareaRef.current
    const caret = el?.selectionStart ?? body.length
    const upToCaret = body.slice(0, caret)
    const rest = body.slice(caret)
    const replaced = upToCaret.replace(/@([\p{L}\p{N} ]{0,24})$/u, `@${member.name} `)
    mentionsRef.current.set(member.name, member.id)
    setBody(replaced + rest)
    setMentionQuery(null)
    el?.focus()
  }

  /** Convert display mentions (@Name) into wire tokens (@[user:id]). */
  function encodeMentions(text: string): string {
    let out = text
    for (const [name, id] of mentionsRef.current) {
      out = out.split(`@${name}`).join(`@[user:${id}]`)
    }
    return out
  }

  function addFiles(list: File[]) {
    const incoming = list.slice(0, 6 - pendingFiles.length)
    setPendingFiles((current) => [
      ...current,
      ...incoming.map((file) => {
        const kind = fileKind(file.type)
        const previewUrl =
          kind === "image" || kind === "video" ? URL.createObjectURL(file) : undefined
        return { file, name: file.name, uploading: false, previewUrl }
      }),
    ])
  }

  // Files dropped anywhere on the composer attach exactly like picked ones —
  // each keeps its rename row before the message is sent.
  const { dragOver, dropProps, takeFiles } = useFileDrop({
    multiple: true,
    disabled: recording,
    onFiles: addFiles,
  })

  function removePending(index: number) {
    setPendingFiles((current) => {
      const removed = current[index]
      if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl)
      return current.filter((_, i) => i !== index)
    })
  }

  // Release any object URLs still held when the composer unmounts.
  useEffect(() => {
    return () => {
      pendingFilesRef.current.forEach((f) => {
        if (f.previewUrl) URL.revokeObjectURL(f.previewUrl)
      })
    }
  }, [])

  async function startRecording() {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const recorder = new MediaRecorder(stream)
      chunksRef.current = []
      recorder.ondataavailable = (event) => chunksRef.current.push(event.data)
      recorder.start()
      recorderRef.current = recorder
      setRecording(true)
      setRecordSeconds(0)
    } catch {
      toast.error(t("composer.micDenied"))
    }
  }

  useEffect(() => {
    if (!recording) return
    const timer = window.setInterval(() => setRecordSeconds((s) => s + 1), 1000)
    return () => window.clearInterval(timer)
  }, [recording])

  async function stopRecording(sendIt: boolean) {
    const recorder = recorderRef.current
    if (!recorder) return
    setRecording(false)

    const stopped = new Promise<void>((resolve) => {
      recorder.onstop = () => resolve()
    })
    recorder.stop()
    recorder.stream.getTracks().forEach((track) => track.stop())
    await stopped
    recorderRef.current = null

    if (!sendIt || recordSeconds < 1) return

    const blob = new Blob(chunksRef.current, { type: recorder.mimeType || "audio/webm" })
    const extension = blob.type.includes("ogg") ? "ogg" : "webm"
    const file = new File([blob], `voice-${Date.now()}.${extension}`, { type: blob.type })

    setSending(true)
    try {
      const uploaded = await uploadChatFile(base, file, file.name)
      await onSend({
        kind: "voice",
        attachments: [{ ...uploaded, duration: recordSeconds }],
        reply_to_id: replyTo?.id,
      })
      onCancelReply()
    } catch {
      toast.error(t("thread.sendFailed"))
    } finally {
      setSending(false)
    }
  }

  async function submit() {
    const trimmed = body.trim()
    if (sending || (!trimmed && pendingFiles.length === 0)) return

    setSending(true)
    try {
      let attachments: ChatAttachment[] | undefined
      if (pendingFiles.length > 0) {
        setPendingFiles((current) => current.map((f) => ({ ...f, uploading: true })))
        attachments = await Promise.all(
          pendingFiles.map(async (pending) => await uploadChatFile(base, pending.file, pending.name)),
        )
      }

      await onSend({
        body: trimmed ? encodeMentions(trimmed) : undefined,
        attachments,
        reply_to_id: replyTo?.id,
        emergency: emergency || undefined,
      })

      setBody("")
      pendingFiles.forEach((f) => f.previewUrl && URL.revokeObjectURL(f.previewUrl))
      setPendingFiles([])
      setEmergency(false)
      mentionsRef.current.clear()
      onCancelReply()
      requestAnimationFrame(autosize)
    } catch {
      toast.error(t("thread.sendFailed"))
    } finally {
      setSending(false)
      textareaRef.current?.focus()
    }
  }

  return (
    <div
      {...dropProps}
      className={cn(
        "relative border-t bg-card/80 px-3 pb-3 pt-2 backdrop-blur-xl md:rounded-b-2xl",
        dragOver && DROP_ACTIVE,
      )}
    >
      {/* Mention autocomplete */}
      {mentionCandidates.length > 0 && (
        <div className="absolute bottom-full left-3 z-10 mb-1 w-64 overflow-hidden rounded-xl border bg-popover shadow-md">
          {mentionCandidates.map((member) => (
            <button
              key={member.id}
              type="button"
              className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
              onClick={() => pickMention(member)}
            >
              <PersonAvatar name={member.name} photoUrl={member.avatar_url} className="size-6 text-[9px]" />
              <span className="truncate">{member.name}</span>
            </button>
          ))}
        </div>
      )}

      {/* Preset messages — one tap inserts the school's wording in the
          family's language; strict schools require it. */}
      {templatesOpen && (
        <div className="absolute bottom-full left-3 right-3 z-10 mb-1 max-h-72 overflow-y-auto rounded-xl border bg-popover shadow-md">
          {templatesRequired && (
            <p className="text-warning border-b px-3 py-2 text-[11px] font-medium">
              {t("templates.requiredNotice")}
            </p>
          )}
          {templates === null ? (
            <p className="text-muted-foreground px-3 py-4 text-center text-xs">
              {t("templates.loading")}
            </p>
          ) : templates.length === 0 ? (
            <p className="text-muted-foreground px-3 py-4 text-center text-xs">
              {t("templates.empty")}
            </p>
          ) : (
            Object.entries(
              templates.reduce<Record<string, ChatTemplatePick[]>>((groups, template) => {
                ;(groups[template.category] ??= []).push(template)
                return groups
              }, {}),
            ).map(([category, group]) => (
              <div key={category}>
                <p className="text-muted-foreground bg-muted/40 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide">
                  {t(`templates.categories.${category}`)}
                </p>
                {group.map((template) => (
                  <button
                    key={template.id}
                    type="button"
                    className="hover:bg-accent block w-full px-3 py-2 text-left"
                    onClick={() => pickTemplate(template)}
                  >
                    <span className="block text-xs font-medium">{template.name}</span>
                    <span className="text-muted-foreground line-clamp-2 block text-xs">
                      {template.resolved_body}
                    </span>
                  </button>
                ))}
              </div>
            ))
          )}
        </div>
      )}

      {replyTo && (
        <div className="mb-2 flex items-start gap-2 rounded-xl border-l-2 border-primary bg-muted/60 px-3 py-1.5">
          <div className="min-w-0 flex-1 text-xs">
            <span className="font-medium text-primary">{replyTo.author?.name}</span>
            <p className="line-clamp-1 text-muted-foreground">
              {replyTo.body || t("thread.attachmentPlaceholder")}
            </p>
          </div>
          <Button variant="ghost" size="icon" className="size-6 rounded-full" onClick={onCancelReply} aria-label={t("actions.cancel")} title={t("actions.cancel")}>
            <X className="size-3.5" />
          </Button>
        </div>
      )}

      {/* Pending attachments — rename before sending (platform standard). */}
      {pendingFiles.length > 0 && (
        <div className="mb-2 flex flex-col gap-1.5">
          {pendingFiles.map((pending, index) => (
            <div key={index} className="flex items-center gap-2 rounded-xl border bg-background px-2.5 py-1.5">
              <PendingThumb pending={pending} />
              <Input
                value={pending.name}
                onChange={(event) =>
                  setPendingFiles((current) =>
                    current.map((f, i) => (i === index ? { ...f, name: event.target.value } : f)),
                  )
                }
                disabled={pending.uploading}
                className="h-8 flex-1 rounded-lg text-xs"
                aria-label={t("composer.renameFile")}
              />
              <span className="shrink-0 text-[11px] text-muted-foreground">{formatFileSize(pending.file.size)}</span>
              <Button
                variant="ghost"
                size="icon"
                className="size-7 rounded-full"
                loading={pending.uploading}
                onClick={() => removePending(index)}
                aria-label={t("actions.remove")}
                title={t("actions.remove")}
              >
                <X className="size-3.5" />
              </Button>
            </div>
          ))}
        </div>
      )}

      {canEmergency && (
        <label
          className={cn(
            "mb-2 flex items-center justify-between gap-3 rounded-xl border px-3 py-2 transition-colors",
            emergency ? "border-primary/40 bg-primary/5" : "bg-muted/30",
          )}
        >
          <span className="min-w-0 flex items-center gap-2">
            <span className={cn("flex items-center text-xs font-medium gap-1", emergency ? "text-primary" : "text-foreground")}>
              <Megaphone className="size-3.5 shrink-0" /> {t("composer.sms")}
            </span>
            <span className="text-[11px] text-muted-foreground">
              {pendingFiles.length > 0 ? t("composer.smsHintMedia") : t("composer.smsHint")}
            </span>
          </span>
     
          <Switch checked={emergency} onCheckedChange={setEmergency} aria-label={t("composer.sms")} />
        </label>
      )}

      <div className="flex items-end gap-1.5">
        <input
          ref={fileInputRef}
          type="file"
          multiple
          hidden
          onChange={(event) => {
            takeFiles(event.target.files)
            event.target.value = ""
          }}
        />

        {recording ? (
          <div className="flex h-11 flex-1 items-center gap-3 rounded-2xl border border-destructive/40 bg-destructive/5 px-4">
            <span className="size-2 animate-pulse rounded-full bg-destructive" />
            <span className="flex-1 text-sm tabular-nums text-destructive">
              {t("composer.recording")} · {formatDuration(recordSeconds)}
            </span>
            <Button variant="ghost" size="icon" className="size-8 rounded-full" onClick={() => void stopRecording(false)} aria-label={t("actions.cancel")} title={t("actions.cancel")}>
              <X className="size-4" />
            </Button>
          </div>
        ) : (
          <>
            {isStaffLane && (
              <Button
                variant="ghost"
                size="icon"
                className={cn(
                  "size-11 shrink-0 rounded-full text-muted-foreground",
                  templatesOpen && "bg-accent text-foreground",
                )}
                onClick={toggleTemplates}
                aria-label={t("templates.pick")}
                title={t("templates.pick")}
              >
                <NotebookText className="size-5" />
              </Button>
            )}
            <Button
              variant="ghost"
              size="icon"
              className="size-11 shrink-0 rounded-full text-muted-foreground"
              onClick={() => fileInputRef.current?.click()}
              loading={sending} disabled={pendingFiles.length >= 6}
              aria-label={t("composer.attach")}
              title={t("composer.attach")}
            >
              <Paperclip className="size-5" />
            </Button>

            <textarea
              ref={textareaRef}
              value={body}
              rows={1}
              placeholder={t("composer.placeholder")}
              onChange={(event) => {
                handleBodyChange(event.target.value)
                autosize()
              }}
              onKeyDown={(event) => {
                if (event.key === "Enter" && !event.shiftKey && window.matchMedia("(min-width: 768px)").matches) {
                  event.preventDefault()
                  void submit()
                }
              }}
              className={cn(
                "max-h-40 min-h-11 flex-1 resize-none rounded-2xl border bg-muted/30 px-4 py-2.5 text-base outline-none",
                "placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30 md:text-sm",
              )}
            />
          </>
        )}

        {recording ? (
          <Button
            size="icon"
            className="size-11 shrink-0 rounded-full"
            onClick={() => void stopRecording(true)}
            loading={sending}
            aria-label={t("composer.sendVoice")}
            title={t("composer.sendVoice")}
          >
            <Square className="size-4 fill-current" />
          </Button>
        ) : body.trim() || pendingFiles.length > 0 ? (
          <Button
            size="icon"
            className="size-11 shrink-0 rounded-full"
            onClick={() => void submit()}
            loading={sending}
            aria-label={t("composer.send")}
            title={t("composer.send")}
          >
            <Send className="size-4.5" />
          </Button>
        ) : (
          <Button
            variant="ghost"
            size="icon"
            className="size-11 shrink-0 rounded-full text-muted-foreground"
            onClick={() => void startRecording()}
            loading={sending}
            aria-label={t("composer.record")}
            title={t("composer.record")}
          >
            <Mic className="size-5" />
          </Button>
        )}
      </div>
    </div>
  )
}

/** Image/video pending files show their local thumbnail; everything else a tinted kind icon. */
function PendingThumb({ pending }: { pending: PendingFile }) {
  const kind = fileKind(pending.file.type)

  if (pending.previewUrl && kind === "image") {
    return (
      // eslint-disable-next-line @next/next/no-img-element -- local object URL, no CDN needed
      <img
        src={pending.previewUrl}
        alt=""
        className="size-8 shrink-0 rounded-lg border object-cover"
      />
    )
  }

  if (pending.previewUrl && kind === "video") {
    return (
      <div className="relative size-8 shrink-0 overflow-hidden rounded-lg border bg-black">
        <video src={pending.previewUrl} preload="metadata" muted playsInline className="size-full object-cover" />
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <Play className="size-3 fill-white text-white drop-shadow-sm" />
        </span>
      </div>
    )
  }

  return <AttachmentIcon mimeType={pending.file.type} className="size-8" />
}

function formatDuration(seconds: number): string {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}:${String(s).padStart(2, "0")}`
}
