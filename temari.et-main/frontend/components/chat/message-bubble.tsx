"use client"

import { Check, Clock3, Copy, Forward, ListChecks, type LucideIcon, Link2, Megaphone, Pencil, Pin, PinOff, Play, Reply, ShieldAlert, SmilePlus, Trash2, TriangleAlert, X } from "lucide-react"
import { useMemo, useState } from "react"
import { toast } from "sonner"

import { AttachmentMedia } from "@/components/ui/attachment"
import { Button } from "@/components/ui/button"
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from "@/components/ui/context-menu"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { useTranslation } from "@/lib/i18n"
import { fileKind, formatFileSize, type MediaFile } from "@/lib/files"
import type { ChatConversation, ChatMessage } from "@/lib/types"
import { cn } from "@/lib/utils"
import { fmtTime } from "@/lib/dates"

/**
 * The one reaction set — shown identically in the hover popover AND the
 * right-click menu (same grid, same emojis). 40 options, well past the 24
 * minimum, laid out 8 per row.
 */
export const REACTION_EMOJIS = [
  "👍", "👎", "❤️", "🔥", "🎉", "👏", "😂", "🤣",
  "😊", "😍", "🥰", "😘", "😮", "😲", "🤯", "😢",
  "😭", "😅", "😉", "🙂", "😡", "🤔", "🙏", "💯",
  "✅", "❌", "👀", "🥳", "😎", "🤝", "💪", "🙌",
  "👌", "✍️", "📌", "⭐", "💡", "⚡", "🎈", "🌟",
]

/**
 * One message row (shadcn message/bubble anatomy): avatar + sender header
 * for group starts, the bubble surface with attachments/voice/reply preview,
 * overlapping reactions on the bubble edge, hover/tap actions, and the
 * communication-book states (pending shimmer, rejected with the director's
 * note) visible to the author only.
 */
export function MessageBubble({
  message,
  conversation,
  own,
  groupedWithPrevious,
  seen,
  onReply,
  onReact,
  onEdit,
  onRemove,
  onPin,
  onForward,
  onSelect,
  onApprove,
  onReturn,
  onPreview,
  onJumpTo,
  canModerate,
  canPin,
  selecting = false,
  selected = false,
  onToggleSelect,
  showAuthor = false,
  viewerId,
}: {
  message: ChatMessage
  conversation: ChatConversation
  own: boolean
  groupedWithPrevious: boolean
  /** Direct/group threads: everyone else has read up to here. */
  seen?: boolean
  onReply?: (message: ChatMessage) => void
  onReact?: (message: ChatMessage, emoji: string) => void
  onEdit?: (message: ChatMessage) => void
  onRemove?: (message: ChatMessage) => void
  /** Pin/unpin toggle — shown only where the viewer may manage pins. */
  onPin?: (message: ChatMessage) => void
  /** Forward this one message (opens the target picker). */
  onForward?: (message: ChatMessage) => void
  /** Enter multi-select mode with this message pre-selected. */
  onSelect?: (message: ChatMessage) => void
  /** Moderators decide pending messages right on the bubble. */
  onApprove?: (message: ChatMessage) => void
  onReturn?: (message: ChatMessage) => void
  /** Opens the thread's shared MediaPreview lightbox at the tapped file. */
  onPreview?: (files: MediaFile[], index: number) => void
  /** Scrolls the thread to the quoted message when the reply preview is tapped. */
  onJumpTo?: (messageId: number) => void
  canModerate?: boolean
  canPin?: boolean
  /** Selection mode: the whole row toggles selection, per-message menus hide. */
  selecting?: boolean
  selected?: boolean
  onToggleSelect?: (message: ChatMessage) => void
  /** Always show avatar + author name, even in direct threads (approvals queue). */
  showAuthor?: boolean
  /** Current user id — highlights the reaction pills they are part of. */
  viewerId?: number
}) {
  const { t } = useTranslation("chat")
  const [reactionsOpen, setReactionsOpen] = useState(false)

  const withAuthor = !own && (showAuthor || conversation.kind !== "direct")
  const showHeader = withAuthor && !groupedWithPrevious
  const canAct = !message.removed && !message.sending && message.status === "sent"
  const editable = own && canAct && minutesSince(message.created_at) < 15
  const removable = canAct && (own || canModerate)

  const body = useMemo(
    () => renderMentions(message.body, conversation),
    [message.body, conversation],
  )

  const forwarded = (message.meta?.forwarded ?? null) as { from?: string | null; origin?: string | null } | null

  async function copyToClipboard(text: string, okKey: string) {
    try {
      await navigator.clipboard.writeText(text)
      toast.success(t(okKey))
    } catch {
      toast.error(t("actions.copyFailed"))
    }
  }

  // The shared action model — one source of truth for the hover toolbar AND
  // the right-click menu (Telegram-style). `hover` marks the icons compact
  // enough to sit in the on-bubble toolbar; everything shows in the menu.
  const actions = useMemo<BubbleAction[]>(() => {
    const list: BubbleAction[] = []
    if (onReply && canAct) {
      list.push({ key: "reply", label: t("actions.reply"), icon: Reply, hover: true, run: () => onReply(message) })
    }
    if (canAct && message.body) {
      list.push({
        key: "copy",
        label: t("actions.copy"),
        icon: Copy,
        hover: true,
        run: () => void copyToClipboard(plainText(message.body, conversation), "actions.copied"),
      })
    }
    if (canAct) {
      list.push({
        key: "copyLink",
        label: t("actions.copyLink"),
        icon: Link2,
        hover: false,
        run: () =>
          void copyToClipboard(
            `${window.location.origin}/messages?c=${message.conversation_id}`,
            "actions.linkCopied",
          ),
      })
    }
    if (canAct && onForward) {
      list.push({ key: "forward", label: t("actions.forward"), icon: Forward, hover: true, run: () => onForward(message) })
    }
    if (canAct && canPin && onPin) {
      list.push({
        key: "pin",
        label: message.pinned ? t("actions.unpin") : t("actions.pin"),
        icon: message.pinned ? PinOff : Pin,
        hover: false,
        run: () => onPin(message),
      })
    }
    if (canAct && onSelect) {
      list.push({ key: "select", label: t("actions.select"), icon: ListChecks, hover: false, run: () => onSelect(message) })
    }
    if (editable && onEdit) {
      list.push({ key: "edit", label: t("actions.edit"), icon: Pencil, hover: true, run: () => onEdit(message) })
    }
    if (removable && onRemove) {
      list.push({ key: "remove", label: t("actions.remove"), icon: Trash2, hover: true, variant: "destructive", run: () => onRemove(message) })
    }
    return list
    // eslint-disable-next-line react-hooks/exhaustive-deps -- handlers/flags are stable per render
  }, [message, conversation, canAct, editable, removable, canPin, onReply, onEdit, onRemove, onPin, onForward, onSelect])

  const quickReact = canAct && onReact ? (emoji: string) => onReact(message, emoji) : undefined
  // Per-message hover + right-click menus stand down while multi-selecting.
  const hasMenu = !selecting && (actions.length > 0 || quickReact !== undefined)

  // Everything except inline audio players opens in the shared lightbox;
  // one gallery per message so swiping moves between this message's files.
  const previewFiles = useMemo<MediaFile[]>(
    () =>
      message.kind === "voice"
        ? []
        : message.attachments
            .filter((file) => file.url && fileKind(file.mime_type) !== "audio")
            .map((file) => ({
              name: file.name,
              url: file.url ?? null,
              mime_type: file.mime_type,
              size: file.size,
            })),
    [message.kind, message.attachments],
  )

  function openFile(file: { name: string; url?: string | null }) {
    const at = previewFiles.findIndex((f) => f.url === file.url)
    if (onPreview && at !== -1) onPreview(previewFiles, at)
    else if (file.url) window.open(file.url, "_blank", "noopener")
  }

  // Images and videos render as a media block (album grid for 2+); everything
  // else as tiles/players below it. A pure media message drops the text padding
  // and overlays its timestamp on the picture — Telegram-style.
  const mediaFiles =
    message.kind === "voice"
      ? []
      : message.attachments.filter(
          (file) => file.url && ["image", "video"].includes(fileKind(file.mime_type)),
        )
  const otherFiles = message.attachments.filter((file) => !mediaFiles.includes(file))
  const mediaOnly =
    !message.removed && !body && !message.reply_to && mediaFiles.length > 0 && otherFiles.length === 0

  if (message.kind === "system") {
    return <SystemMarker message={message} />
  }

  const selectable = selecting && !message.sending && message.id > 0

  return (
    <div
      className={cn(
        "group relative flex w-full gap-2.5 rounded-xl",
        own ? "justify-end" : "justify-start",
        groupedWithPrevious ? "mt-1" : "mt-4",
        selecting && "cursor-pointer px-1 py-0.5 -mx-1 transition-colors",
        selected && "bg-primary/10",
      )}
      onClick={selectable ? () => onToggleSelect?.(message) : undefined}
    >
      {/* Selection mode: a full-row hit target + a leading check circle. */}
      {selecting && (
        <>
          <span
            className={cn(
              "flex size-5 shrink-0 items-center justify-center self-center rounded-full border transition-colors",
              selected ? "border-primary bg-primary text-primary-foreground" : "border-muted-foreground/40",
            )}
          >
            {selected && <Check className="size-3" strokeWidth={3} />}
          </span>
          {selectable && <span className="absolute inset-0 z-20" aria-hidden />}
        </>
      )}

      {withAuthor && (
        <div className="w-8 shrink-0 self-end">
          {!groupedWithPrevious && (
            <PersonAvatar name={message.author?.name ?? "?"} photoUrl={message.author?.avatar_url} />
          )}
        </div>
      )}

      <div className={cn("flex max-w-[80%] flex-col md:max-w-[65%]", own ? "items-end" : "items-start")}>
        {showHeader && (
          <span className="mb-1 px-1 text-xs font-medium text-muted-foreground">
            {message.author?.name}
          </span>
        )}

          <BubbleMenu enabled={hasMenu} quickReact={quickReact} actions={actions}>
          <div
            className={cn(
              "relative rounded-2xl text-sm leading-relaxed break-words",
              mediaOnly ? "overflow-hidden p-1" : "px-3.5 py-2",
              // Own bubbles wear a soft brand tint (DESIGN.md: bg-primary/10 is
              // the branded surface) — readable dark text, not a wall of green.
              own
                ? "rounded-br-md bg-primary/10 text-foreground dark:bg-primary/15"
                : "rounded-bl-md bg-muted text-foreground",
              own &&
                message.status === "sent" &&
                !message.removed &&
                "ring-1 ring-inset ring-primary/15 dark:ring-primary/25",
              message.status === "pending" && "border border-dashed border-warning/60 bg-warning/10 text-foreground",
              message.status === "rejected" && "border border-dashed border-destructive/50 bg-destructive/5 text-foreground",
              message.sending && "opacity-70",
              message.failed && "border border-destructive/60",
              message.removed && "italic text-muted-foreground bg-muted/50",
            )}
          >
            {forwarded && !message.removed && (
              <span className="mb-1 flex items-center gap-1 text-[11px] font-medium text-primary/80">
                <Forward className="size-3 shrink-0" />
                {t("thread.forwardedFrom", { name: forwarded.from || t("thread.member") })}
              </span>
            )}

            {message.reply_to && !message.removed && (
              <button
                type="button"
                onClick={onJumpTo ? () => onJumpTo(message.reply_to!.id) : undefined}
                className={cn(
                  "mb-1.5 block w-full rounded-lg border-l-2 border-primary/50 bg-background/60 px-2.5 py-1.5 text-left text-xs text-muted-foreground",
                  onJumpTo && "cursor-pointer transition-colors hover:bg-background",
                )}
              >
                <span className="block font-medium">{message.reply_to.author_name ?? t("thread.member")}</span>
                <span className="line-clamp-2 block">{message.reply_to.body || t("thread.attachmentPlaceholder")}</span>
              </button>
            )}

            {message.removed ? (
              <span>{t("thread.removed")}</span>
            ) : (
              <>
                {mediaFiles.length === 1 ? (
                  <MediaThumb
                    file={mediaFiles[0]}
                    onClick={() => openFile(mediaFiles[0])}
                    className={cn(
                      mediaOnly ? "rounded-[13px]" : "rounded-xl border",
                      (body || otherFiles.length > 0) && "mb-1.5",
                    )}
                  />
                ) : mediaFiles.length > 1 ? (
                  <div
                    className={cn(
                      "grid w-64 max-w-full grid-cols-2 gap-0.5 overflow-hidden",
                      mediaOnly ? "rounded-[13px]" : "rounded-xl",
                      (body || otherFiles.length > 0) && "mb-1.5",
                    )}
                  >
                    {mediaFiles.map((file, index) => (
                      <MediaThumb
                        key={index}
                        file={file}
                        cover
                        onClick={() => openFile(file)}
                        className={cn(
                          "aspect-square w-full",
                          // Odd album: the first item goes wide (Telegram layout).
                          mediaFiles.length % 2 === 1 && index === 0 && "col-span-2 aspect-[2/1]",
                        )}
                      />
                    ))}
                  </div>
                ) : null}

                {otherFiles.length > 0 && (
                  <div className={cn("flex flex-col gap-1.5", body && "mb-1.5")}>
                    {otherFiles.map((file, index) =>
                      message.kind === "voice" || fileKind(file.mime_type) === "audio" ? (
                        <audio key={index} controls preload="none" src={file.url ?? undefined} className="h-10 w-56 max-w-full" />
                      ) : (
                        <button
                          key={index}
                          type="button"
                          disabled={!file.url}
                          onClick={() => openFile(file)}
                          className={cn(
                            "pressable flex items-center gap-2.5 rounded-xl border px-2.5 py-2 text-left",
                            own ? "border-primary/20 bg-background/60" : "bg-card",
                          )}
                        >
                          <AttachmentMedia file={{ name: file.name, url: file.url ?? null, mime_type: file.mime_type ?? null }} className="size-9" />
                          <span className="min-w-0">
                            <span className="block truncate text-xs font-medium">{file.name}</span>
                            <span className="block text-[11px] text-muted-foreground">
                              {formatFileSize(file.size)}
                            </span>
                          </span>
                        </button>
                      ),
                    )}
                  </div>
                )}
                {body}
              </>
            )}

            <span
              className={cn(
                "flex items-center justify-end gap-1 text-[10px] tabular-nums",
                mediaOnly
                  ? "absolute right-2.5 bottom-2.5 z-10 rounded-full bg-black/50 px-2 py-0.5 text-white"
                  : "mt-0.5 text-muted-foreground",
              )}
            >
              {message.pinned && (
                <Pin
                  className={cn("size-3", mediaOnly ? "text-white" : "text-muted-foreground")}
                  aria-label={t("thread.pinned")}
                />
              )}
              {message.meta?.emergency ? (
                <span
                  className={cn("flex items-center gap-0.5 font-medium", mediaOnly ? "text-white" : "text-primary")}
                  title={t("thread.sentAsSms")}
                >
                  <Megaphone className="size-3" /> {t("thread.smsBadge")}
                </span>
              ) : null}
              {message.edited_at && <span>{t("thread.edited")}</span>}
              <span>{timeOf(message.created_at)}</span>
              {own && message.sending && <Clock3 className="size-3" />}
              {own && !message.sending && message.status === "sent" && (
                <Check className={cn("size-3", seen && "text-info")} strokeWidth={3} />
              )}
            </span>
          </div>
          </BubbleMenu>

          {/* Reactions sit in normal flow, pulled up over the bubble edge —
              they reserve their own height and can never cover the next row. */}
          {message.reactions.length > 0 && (
            <div className={cn("z-10 -mt-2 flex flex-wrap gap-0.5", own ? "justify-end pr-1.5" : "pl-1.5")}>
              {message.reactions.slice(0, 4).map((reaction) => (
                <button
                  key={reaction.emoji}
                  type="button"
                  onClick={() => onReact?.(message, reaction.emoji)}
                  className={cn(
                    // Opaque surfaces only — the pills overlap the bubble edge,
                    // so a translucent background reads as a broken artifact.
                    "pressable flex items-center gap-0.5 rounded-full border bg-card px-1.5 py-px text-xs shadow-2xs",
                    viewerId !== undefined &&
                      reaction.user_ids.includes(viewerId) &&
                      "border-primary bg-card",
                  )}
                >
                  <span>{reaction.emoji}</span>
                  {reaction.count > 1 && <span className="text-[10px] text-muted-foreground">{reaction.count}</span>}
                </button>
              ))}
            </div>
          )}

        {/* Communication-book states. Moderators decide right here; the
            author sees the waiting indicator. */}
        {message.status === "pending" && canModerate && !own && onApprove && onReturn ? (
          <div className="mt-1.5 flex gap-1.5">
            <Button size="sm" className="h-7 rounded-full px-3 text-xs" onClick={() => onApprove(message)}>
              <Check className="size-3" /> {t("approval.approve")}
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-7 rounded-full px-3 text-xs text-destructive"
              onClick={() => onReturn(message)}
            >
              <X className="size-3" /> {t("approval.reject")}
            </Button>
          </div>
        ) : message.status === "pending" ? (
          <span className="mt-1 flex items-center gap-1 px-1 text-[11px] text-warning">
            <ShieldAlert className="size-3" /> {t("approval.waiting")}
          </span>
        ) : null}
        {message.status === "rejected" && (
          <span className="mt-1 flex items-center gap-1 px-1 text-[11px] text-destructive">
            <TriangleAlert className="size-3" />
            {message.review_note ? t("approval.rejectedWithNote", { note: message.review_note }) : t("approval.rejected")}
          </span>
        )}
        {message.failed && (
          <span className="mt-1 px-1 text-[11px] text-destructive">{t("thread.sendFailed")}</span>
        )}
      </div>

      {/* Hover / tap actions — the same set the right-click menu offers. */}
      {hasMenu && (
        <div
          className={cn(
            "flex items-end gap-0.5 pb-1 opacity-0 transition-opacity group-hover:opacity-100",
            reactionsOpen && "opacity-100",
            own ? "order-first" : "",
          )}
        >
          {quickReact && (
            <Popover open={reactionsOpen} onOpenChange={setReactionsOpen}>
              <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className="size-7 rounded-full" aria-label={t("actions.react")} title={t("actions.react")}>
                  <SmilePlus className="size-3.5" />
                </Button>
              </PopoverTrigger>
              <PopoverContent side="top" className="w-auto rounded-2xl p-1.5">
                <ReactionPicker
                  onPick={(emoji) => {
                    quickReact(emoji)
                    setReactionsOpen(false)
                  }}
                />
              </PopoverContent>
            </Popover>
          )}
          {actions
            .filter((action) => action.hover)
            .map((action) => (
              <Button
                key={action.key}
                variant="ghost"
                size="icon"
                className={cn("size-7 rounded-full", action.variant === "destructive" && "text-destructive")}
                aria-label={action.label}
                title={action.label}
                onClick={action.run}
              >
                <action.icon className="size-3.5" />
              </Button>
            ))}
        </div>
      )}
    </div>
  )
}

/** The shared reaction grid — the SAME picker used by hover and right-click. */
function ReactionPicker({ onPick }: { onPick: (emoji: string) => void }) {
  return (
    <div className="grid max-h-52 w-64 grid-cols-8 gap-0.5 overflow-y-auto overscroll-contain">
      {REACTION_EMOJIS.map((emoji) => (
        <button
          key={emoji}
          type="button"
          className="pressable flex items-center justify-center rounded-lg p-1 text-xl hover:bg-accent"
          onClick={() => onPick(emoji)}
        >
          {emoji}
        </button>
      ))}
    </div>
  )
}

interface BubbleAction {
  key: string
  label: string
  icon: LucideIcon
  /** Compact enough to also appear in the on-bubble hover toolbar. */
  hover: boolean
  variant?: "default" | "destructive"
  run: () => void
}

/**
 * Right-click surface for a message (Telegram-style): a quick-reaction emoji
 * strip on top, then the labelled actions. Falls back to the bare bubble when
 * there is nothing to offer (removed / sending / no permissions).
 */
function BubbleMenu({
  enabled,
  quickReact,
  actions,
  children,
}: {
  enabled: boolean
  quickReact?: (emoji: string) => void
  actions: BubbleAction[]
  children: React.ReactNode
}) {
  const { t } = useTranslation("chat")

  if (!enabled) return children

  return (
    <ContextMenu>
      <ContextMenuTrigger asChild>{children}</ContextMenuTrigger>
      <ContextMenuContent className="w-64">
        {quickReact && (
          <div
            className="mb-1 grid max-h-52 grid-cols-8 gap-0.5 overflow-y-auto overscroll-contain"
            aria-label={t("actions.react")}
          >
            {REACTION_EMOJIS.map((emoji) => (
              <ContextMenuItem
                key={emoji}
                onSelect={() => quickReact(emoji)}
                className="justify-center rounded-lg p-1! text-xl"
              >
                {emoji}
              </ContextMenuItem>
            ))}
          </div>
        )}
        {quickReact && actions.length > 0 && <ContextMenuSeparator />}
        {actions.map((action) => (
          <ContextMenuItem key={action.key} variant={action.variant} onSelect={action.run}>
            <action.icon />
            <span>{action.label}</span>
          </ContextMenuItem>
        ))}
      </ContextMenuContent>
    </ContextMenu>
  )
}

/** Message text with mention tokens rendered readable (@Name) for clipboard copy. */
function plainText(body: string | null, conversation: ChatConversation): string {
  if (!body) return ""
  return body.replace(/@\[user:(\d+)\]/g, (_, id: string) => {
    const member = conversation.members?.find((m) => m.id === Number(id))
    return `@${member?.name ?? ""}`
  })
}

/**
 * One media-block cell: images render as plain thumbnails, videos as a
 * first-frame poster (preload="metadata") with a play badge — both open the
 * thread's shared MediaPreview lightbox, which plays the video inline.
 */
function MediaThumb({
  file,
  cover = false,
  onClick,
  className,
}: {
  file: { name: string; url?: string | null; mime_type?: string | null }
  /** Album-grid cell — fill the cell instead of natural media sizing. */
  cover?: boolean
  onClick: () => void
  className?: string
}) {
  if (fileKind(file.mime_type) === "video") {
    return (
      <button
        type="button"
        onClick={onClick}
        className={cn(
          "relative block max-w-full cursor-pointer overflow-hidden bg-black",
          !cover && "w-fit",
          className,
        )}
      >
        <video
          src={file.url ?? undefined}
          preload="metadata"
          muted
          playsInline
          className={cn(
            "pointer-events-none",
            cover ? "size-full object-cover" : "max-h-80 w-auto max-w-full",
          )}
        />
        <span className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <span className="flex size-11 items-center justify-center rounded-full bg-black/50">
            <Play className="size-5 fill-white text-white" />
          </span>
        </span>
      </button>
    )
  }
  return (
    // eslint-disable-next-line @next/next/no-img-element -- signed URL
    <img
      src={file.url!}
      alt={file.name}
      loading="lazy"
      onClick={onClick}
      className={cn(
        "cursor-pointer",
        cover ? "object-cover" : "max-h-80 w-auto max-w-full",
        className,
      )}
    />
  )
}

/** Inline conversation marker (shadcn marker anatomy) for system rows. */
function SystemMarker({ message }: { message: ChatMessage }) {
  const { t } = useTranslation("chat")
  const event = String(message.meta?.event ?? "")
  const params = (message.meta?.params ?? {}) as Record<string, string>

  return (
    <div className="my-3 flex items-center gap-3">
      <span className="h-px flex-1 bg-border" />
      <span className="max-w-[80%] rounded-full border bg-muted/50 px-3 py-1 text-center text-[11px] text-muted-foreground">
        {t(`system.${event}`, params)}
      </span>
      <span className="h-px flex-1 bg-border" />
    </div>
  )
}

/** Date separator between message days (Marker separator variant). */
export function DateMarker({ label }: { label: string }) {
  return (
    <div className="my-4 flex items-center gap-3">
      <span className="h-px flex-1 bg-border" />
      <span className="rounded-full bg-muted px-3 py-1 text-[11px] font-medium text-muted-foreground">{label}</span>
      <span className="h-px flex-1 bg-border" />
    </div>
  )
}

/** Replace @[user:123] tokens with styled mention chips (name from members). */
function renderMentions(body: string | null, conversation: ChatConversation): React.ReactNode {
  if (!body) return null

  const parts = body.split(/(@\[user:\d+\])/g)

  return (
    <span className="whitespace-pre-wrap">
      {parts.map((part, index) => {
        const match = /^@\[user:(\d+)\]$/.exec(part)
        if (!match) return <span key={index}>{linkify(part, index)}</span>
        const member = conversation.members?.find((m) => m.id === Number(match[1]))
        return (
          <span key={index} className="rounded bg-info/15 px-1 font-medium text-info">
            @{member?.name ?? "…"}
          </span>
        )
      })}
    </span>
  )
}

// URL / email / Ethiopian-phone matcher. Trailing sentence punctuation is
// trimmed off the match so "see https://x.et." doesn't swallow the period.
const LINKIFY_RE =
  /(https?:\/\/[^\s]+|www\.[^\s]+)|([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})|((?:\+251|0)\d{8,9})/g

/**
 * Turn URLs, emails and phone numbers in a plain-text run into tappable links
 * (opens externally / mail / dialer). Clicks stop propagation so tapping a
 * link never triggers the bubble's own tap actions. Bodies are always plain
 * text — we never render stored HTML.
 */
function linkify(text: string, keyBase: number): React.ReactNode {
  if (!text) return text

  const nodes: React.ReactNode[] = []
  let last = 0
  let n = 0
  LINKIFY_RE.lastIndex = 0
  let m: RegExpExecArray | null
  while ((m = LINKIFY_RE.exec(text)) !== null) {
    let matched = m[0]
    // Pull trailing punctuation back out of the link.
    const trailing = matched.match(/[.,;:!?)\]]+$/)
    if (trailing && !m[3]) matched = matched.slice(0, matched.length - trailing[0].length)
    const start = m.index
    const end = start + matched.length

    if (start > last) nodes.push(text.slice(last, start))

    const href = m[1]
      ? matched.startsWith("http")
        ? matched
        : `https://${matched}`
      : m[2]
        ? `mailto:${matched}`
        : `tel:${matched}`
    const external = Boolean(m[1])

    nodes.push(
      <a
        key={`lnk-${keyBase}-${n}`}
        href={href}
        target={external ? "_blank" : undefined}
        rel={external ? "noopener noreferrer" : undefined}
        onClick={(event) => event.stopPropagation()}
        className="break-all underline underline-offset-2"
      >
        {matched}
      </a>,
    )

    last = end
    LINKIFY_RE.lastIndex = end
    n++
  }

  if (nodes.length === 0) return text
  if (last < text.length) nodes.push(text.slice(last))
  return nodes
}

function timeOf(iso: string): string {
  return fmtTime(iso)
}

function minutesSince(iso: string): number {
  return (Date.now() - new Date(iso).getTime()) / 60_000
}
