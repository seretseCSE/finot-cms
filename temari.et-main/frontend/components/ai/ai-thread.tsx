"use client"

import {
  ArrowDown,
  ArrowLeft,
  Check,
  Copy,
  RefreshCw,
  Sparkles,
  ThumbsDown,
  ThumbsUp,
} from "lucide-react"
import { useCallback, useEffect, useRef, useState } from "react"
import { toast } from "sonner"

import { AiComposer } from "@/components/ai/ai-composer"
import { AiMarkdown } from "@/components/ai/ai-markdown"
import { AiMessageAttachments } from "@/components/ai/ai-attachment"
import { UpgradeDialog } from "@/components/ai/upgrade-dialog"
import { Button } from "@/components/ui/button"
import { apiFetch, ApiError } from "@/lib/api"
import { streamAiMessage, streamAiRegenerate } from "@/lib/ai"
import { useTranslation } from "@/lib/i18n"
import type { AiChatMessage, AiConversationSummary, AiEntitlement, AiMessageAttachment } from "@/lib/types"
import { cn } from "@/lib/utils"

/** The first message typed on the home screen, sent once the session opens. */
export interface AiInitialMessage {
  conversationId: number
  text: string
  files: File[]
}

/** Distance (px) from the bottom within which autoscroll stays engaged. */
const PIN_THRESHOLD = 96

/**
 * One AI conversation, full-bleed (no card chrome): slim header, centered
 * transcript, ChatGPT-style floating composer. Streams the answer, shows
 * sent attachments, supports regenerate, and handles quota/upgrade
 * surfaces and per-message feedback.
 */
export function AiThread({
  conversation,
  entitlement,
  maxAttachments,
  initial,
  onInitialConsumed,
  onBack,
  onChanged,
  onQuotaChanged,
}: {
  conversation: AiConversationSummary
  entitlement: AiEntitlement | null
  maxAttachments: number
  initial?: AiInitialMessage | null
  onInitialConsumed?: () => void
  onBack?: () => void
  onChanged: () => void
  onQuotaChanged: () => void
}) {
  const { t } = useTranslation("ai")
  const [messages, setMessages] = useState<AiChatMessage[]>([])
  const [loading, setLoading] = useState(true)
  const [input, setInput] = useState("")
  const [streaming, setStreaming] = useState(false)
  const [working, setWorking] = useState(false)
  const [upgradeOpen, setUpgradeOpen] = useState(false)
  const [feedback, setFeedback] = useState<Record<string, "up" | "down">>({})
  const [copied, setCopied] = useState<string | null>(null)
  const [pinned, setPinned] = useState(true)
  const abortRef = useRef<AbortController | null>(null)
  const scrollRef = useRef<HTMLDivElement>(null)
  const pinnedRef = useRef(true)
  const initialSentRef = useRef(false)

  const isFamilyLane = conversation.surface === "family"
  const outOfQuota = entitlement !== null && entitlement.remaining !== null && entitlement.remaining <= 0

  // Load the transcript once per mount — the parent remounts this component
  // per conversation (key={id}), so initial state IS the reset.
  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: { messages: AiChatMessage[] } }>(`/ai/conversations/${conversation.id}/messages`)
      .then((res) => {
        if (!cancelled) setMessages(res.data.messages)
      })
      .catch(() => undefined)
      .finally(() => {
        if (!cancelled) setLoading(false)
      })
    return () => {
      cancelled = true
      abortRef.current?.abort()
    }
  }, [conversation.id])

  const scrollToBottom = useCallback((force = false) => {
    if (!force && !pinnedRef.current) return
    requestAnimationFrame(() => {
      scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight })
    })
  }, [])

  const handleScroll = useCallback(() => {
    const el = scrollRef.current
    if (!el) return
    const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < PIN_THRESHOLD
    pinnedRef.current = nearBottom
    setPinned(nearBottom)
  }, [])

  useEffect(() => scrollToBottom(), [messages.length, streaming, scrollToBottom])

  const resync = useCallback(() => {
    void apiFetch<{ data: { messages: AiChatMessage[] } }>(`/ai/conversations/${conversation.id}/messages`)
      .then((res) => setMessages(res.data.messages))
      .catch(() => undefined)
  }, [conversation.id])

  /** Shared streaming runner for send + regenerate. */
  const runStream = useCallback(
    async (
      assistantId: string,
      stream: (handlers: Parameters<typeof streamAiMessage>[3], signal: AbortSignal) => Promise<void>,
      onQuotaError: () => void,
    ) => {
      setStreaming(true)
      setWorking(false)
      pinnedRef.current = true
      setPinned(true)
      scrollToBottom(true)

      const controller = new AbortController()
      abortRef.current = controller

      try {
        await stream(
          {
            onDelta: (delta) => {
              setWorking(false)
              setMessages((prev) =>
                prev.map((m) => (m.id === assistantId ? { ...m, content: m.content + delta } : m)),
              )
              scrollToBottom()
            },
            onToolActivity: () => setWorking(true),
          },
          controller.signal,
        )

        // Re-sync quietly: real message ids (for feedback), stored
        // attachments and the auto title.
        resync()
        onChanged()
        onQuotaChanged()
      } catch (error) {
        if (controller.signal.aborted) {
          // Stopped by the user — keep whatever streamed in.
        } else if (error instanceof ApiError && (error.status === 402 || error.status === 429)) {
          onQuotaError()
          if (isFamilyLane) setUpgradeOpen(true)
          else toast.error(t("quota.limitReached"))
          onQuotaChanged()
        } else {
          setMessages((prev) =>
            prev.map((m) =>
              m.id === assistantId && m.content === ""
                ? { ...m, content: t("thread.error") }
                : m,
            ),
          )
        }
      } finally {
        setStreaming(false)
        setWorking(false)
        setMessages((prev) => prev.map((m) => (m.streaming ? { ...m, streaming: false } : m)))
        abortRef.current = null
      }
    },
    [isFamilyLane, onChanged, onQuotaChanged, resync, scrollToBottom, t],
  )

  const send = useCallback(
    async (text: string, sentFiles: File[]) => {
      const content = text.trim()
      if (content === "" || streaming) return

      setInput("")

      const localAttachments: AiMessageAttachment[] = sentFiles.map((file, index) => ({
        index,
        name: file.name,
        mime: file.type || null,
        kind: file.type.startsWith("image/") ? "image" : "file",
        localUrl: file.type.startsWith("image/") ? URL.createObjectURL(file) : "",
      }))

      const userMessage: AiChatMessage = {
        id: `local-u-${Date.now()}`,
        role: "user",
        content,
        attachments: localAttachments,
      }
      const assistantId = `local-a-${Date.now()}`
      setMessages((prev) => [...prev, userMessage, { id: assistantId, role: "assistant", content: "", streaming: true }])

      await runStream(
        assistantId,
        (handlers, signal) => streamAiMessage(conversation.id, content, sentFiles, handlers, signal),
        () => {
          setMessages((prev) => prev.filter((m) => m.id !== assistantId && m.id !== userMessage.id))
          setInput(content)
        },
      )
    },
    [conversation.id, runStream, streaming],
  )

  /** Swap the last answer for a fresh one (server drops the old exchange). */
  const regenerate = useCallback(async () => {
    if (streaming) return

    const assistantId = `local-a-${Date.now()}`
    setMessages((prev) => {
      const next = [...prev]
      // Replace the trailing assistant turn with a fresh streaming shell.
      while (next.length > 0 && next[next.length - 1].role === "assistant") next.pop()
      return [...next, { id: assistantId, role: "assistant", content: "", streaming: true }]
    })

    await runStream(
      assistantId,
      (handlers, signal) => streamAiRegenerate(conversation.id, handlers, signal),
      () => resync(),
    )
  }, [conversation.id, resync, runStream, streaming])

  // The home screen's first message: fire it as soon as the transcript is in
  // (the guard ref keeps StrictMode's double effect from sending twice).
  useEffect(() => {
    if (loading || initialSentRef.current) return
    if (!initial || initial.conversationId !== conversation.id) return
    initialSentRef.current = true
    onInitialConsumed?.()
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-shot kickoff of the home screen's queued message
    void send(initial.text, initial.files)
  }, [loading, initial, conversation.id, onInitialConsumed, send])

  const stop = () => abortRef.current?.abort()

  const sendFeedback = (messageId: string, rating: "up" | "down") => {
    if (messageId.startsWith("local-")) return
    setFeedback((prev) => ({ ...prev, [messageId]: rating }))
    void apiFetch("/ai/feedback", { method: "POST", body: { message_id: messageId, rating } })
      .then(() => toast.success(t("thread.feedbackThanks")))
      .catch(() => undefined)
  }

  const copyMessage = (message: AiChatMessage) => {
    void navigator.clipboard.writeText(message.content).then(() => {
      setCopied(message.id)
      window.setTimeout(() => setCopied(null), 1500)
    })
  }

  const lastAssistantId = [...messages].reverse().find((m) => m.role === "assistant")?.id

  return (
    <div className="flex h-full min-h-0 flex-col">
      {/* Header */}
      <div className="flex md:hidden items-center gap-2 border-b px-2 py-2 md:px-4">
        {onBack && (
          <Button variant="ghost" size="icon" className="size-9 rounded-full" onClick={onBack} aria-label={t("thread.back")} title={t("thread.back")}>
            <ArrowLeft className="size-5" />
          </Button>
        )}
        <Sparkles className="size-4 shrink-0 text-primary" />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">{conversation.title}</p>
        </div>
      </div>

      {/* Transcript */}
      <div className="relative min-h-0 flex-1">
        <div ref={scrollRef} onScroll={handleScroll} className="h-full overflow-y-auto">
          {loading ? null : messages.length === 0 && !streaming ? (
            <div className="flex h-full flex-col items-center justify-center gap-4 px-4 text-center">
              <div className="flex size-12 items-center justify-center rounded-2xl bg-primary/10">
                <Sparkles className="size-6 text-primary" />
              </div>
              <p className="font-display text-lg font-semibold tracking-tight">{t("thread.welcome")}</p>
              <div className="flex max-w-lg flex-wrap justify-center gap-2">
                {[0, 1, 2, 3].map((i) => {
                  const suggestion = t(`suggestions.${conversation.lane}.${i}`)
                  if (suggestion.startsWith("suggestions.")) return null
                  return (
                    <button
                      key={i}
                      type="button"
                      onClick={() => void send(suggestion, [])}
                      className="pressable rounded-full border bg-card px-3.5 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    >
                      {suggestion}
                    </button>
                  )
                })}
              </div>
            </div>
          ) : (
            <div className="mx-auto w-full max-w-3xl space-y-5 px-4 py-6 md:px-6">
              {messages.map((message) =>
                message.role === "user" ? (
                  <div key={message.id} className="group flex flex-col items-end gap-1.5">
                    {(message.attachments?.length ?? 0) > 0 && (
                      <AiMessageAttachments
                        conversationId={conversation.id}
                        messageId={message.id}
                        attachments={message.attachments ?? []}
                      />
                    )}
                    <div className="max-w-[85%] rounded-3xl rounded-br-lg bg-primary px-4 py-2.5 text-sm whitespace-pre-wrap text-primary-foreground">
                      {message.content}
                    </div>
                    <div className="flex items-center opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                      <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() => copyMessage(message)}
                        title={t("thread.copy")}
                        aria-label={t("thread.copy")}
                      >
                        {copied === message.id ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                      </Button>
                    </div>
                  </div>
                ) : (
                  <div key={message.id} className="group flex flex-col gap-1">
                    {message.content === "" && message.streaming ? (
                      <p className="animate-pulse text-sm text-muted-foreground">
                        {working ? t("thread.working") : t("thread.thinking")}
                      </p>
                    ) : (
                      <AiMarkdown
                        content={message.content}
                        onChoice={(text) => void send(text, [])}
                        choicesDisabled={streaming || outOfQuota}
                      />
                    )}
                    {!message.streaming && message.content !== "" && (
                      <div className="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="size-7"
                          onClick={() => copyMessage(message)}
                          title={t("thread.copy")}
                          aria-label={t("thread.copy")}
                        >
                          {copied === message.id ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                        </Button>
                        {!message.id.startsWith("local-") && (
                          <>
                            <Button
                              variant="ghost"
                              size="icon"
                              className={cn("size-7", feedback[message.id] === "up" && "text-primary")}
                              onClick={() => sendFeedback(message.id, "up")}
                              title={t("thread.feedbackUp")}
                              aria-label={t("thread.feedbackUp")}
                            >
                              <ThumbsUp className="size-3.5" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              className={cn("size-7", feedback[message.id] === "down" && "text-destructive")}
                              onClick={() => sendFeedback(message.id, "down")}
                              title={t("thread.feedbackDown")}
                              aria-label={t("thread.feedbackDown")}
                            >
                              <ThumbsDown className="size-3.5" />
                            </Button>
                          </>
                        )}
                        {message.id === lastAssistantId && !streaming && !outOfQuota && (
                          <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            onClick={() => void regenerate()}
                            title={t("thread.regenerate")}
                            aria-label={t("thread.regenerate")}
                          >
                            <RefreshCw className="size-3.5" />
                          </Button>
                        )}
                      </div>
                    )}
                  </div>
                ),
              )}
            </div>
          )}
        </div>

        {/* Jump back down while reading older turns */}
        {!pinned && (
          <Button
            variant="secondary"
            size="icon"
            className="absolute bottom-3 left-1/2 size-9 -translate-x-1/2 rounded-full border shadow-md"
            onClick={() => {
              pinnedRef.current = true
              setPinned(true)
              scrollToBottom(true)
            }}
            title={t("thread.jumpToLatest")}
            aria-label={t("thread.jumpToLatest")}
          >
            <ArrowDown className="size-4.5" />
          </Button>
        )}
      </div>

      {/* Composer */}
      <div className="mx-auto w-full max-w-3xl px-3 pb-2 md:px-6 md:pb-4">
        {outOfQuota && (
          <div className="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-2xl border bg-muted/50 px-4 py-2.5">
            <p className="text-sm">
              {entitlement?.plan === "free" ? t("quota.limitReachedFree") : t("quota.limitReached")}
              {!isFamilyLane && entitlement?.plan === "staff_free" && (
                <span className="block text-xs text-muted-foreground">{t("quota.schoolPlanNote")}</span>
              )}
            </p>
            {isFamilyLane && (
              <Button size="sm" onClick={() => setUpgradeOpen(true)}>
                <Sparkles className="size-4" /> {t("quota.upgradeCta")}
              </Button>
            )}
          </div>
        )}

        <AiComposer
          value={input}
          onChange={setInput}
          onSend={(text, files) => void send(text, files)}
          streaming={streaming}
          onStop={stop}
          disabled={outOfQuota}
          maxAttachments={maxAttachments}
          hint={
            entitlement !== null &&
            entitlement.remaining !== null &&
            entitlement.remaining <= 10 &&
            !outOfQuota
              ? t("quota.remainingToday", { count: entitlement.remaining })
              : undefined
          }
        />
        <p className="mt-1.5 text-center text-[11px] text-muted-foreground">{t("thread.disclaimer")}</p>
      </div>

      <UpgradeDialog open={upgradeOpen} onOpenChange={setUpgradeOpen} />
    </div>
  )
}
