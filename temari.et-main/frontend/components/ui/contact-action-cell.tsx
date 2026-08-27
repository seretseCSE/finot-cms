"use client"

import { Copy, Hash, Mail, MessageCircleMore, Phone, Share2, UserRound } from "lucide-react"
import * as React from "react"
import type { ReactNode } from "react"
import { toast } from "sonner"

import { useChatLauncher, type ChatLaunchTarget } from "@/components/chat/chat-launcher"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

type ContactKind = "phone" | "email" | "value"

type ContactActionCellProps = {
  /** Phone number, email address, or generic value (bank account, receipt
   *  number…) shown in the cell and used for Call / Email / Copy / Share. */
  value: string | null | undefined
  kind?: ContactKind
  /** Optional display name shown in the popover header. */
  name?: string | null
  /** In-app chat target for this person — adds a Message action to the
   *  popover (staff lane only; hidden when the viewer can't chat). */
  chat?: ChatLaunchTarget
  /** When set, replaces the default mono value trigger (e.g. name + phone stack). */
  children?: ReactNode
  className?: string
  /** Extra classes on the trigger button when using the default value display. */
  triggerClassName?: string
}

/** Web Share support is a client capability — resolve after mount so SSR and
 *  the first client render agree (no hydration mismatch). */
function useCanShare(): boolean {
  const [canShare, setCanShare] = React.useState(false)
  React.useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- capability check must run after mount
    setCanShare(typeof navigator !== "undefined" && typeof navigator.share === "function")
  }, [])
  return canShare
}

/**
 * Tap-to-act cell for phones, emails and other copyable values in tables.
 * Shows the value inline; click opens a popover with Call/Email + Copy +
 * Share (Share only where the Web Share API exists — i.e. mobile).
 * `kind="value"` drops the Call/Email action for things like bank accounts
 * and receipt numbers.
 */
export function ContactActionCell({
  value,
  kind = "phone",
  name,
  chat,
  children,
  className,
  triggerClassName,
}: ContactActionCellProps) {
  const { t } = useTranslation("common")
  const canShare = useCanShare()
  const launcher = useChatLauncher()
  const [open, setOpen] = React.useState(false)
  const showChat = chat !== undefined && launcher.canTarget(chat)

  if (!value) {
    return <span className={cn("text-sm text-muted-foreground", className)}>—</span>
  }

  const isPhone = kind === "phone"
  const isEmail = kind === "email"
  const href = isPhone ? `tel:${value}` : isEmail ? `mailto:${value}` : null
  const primaryLabel = isPhone ? t("contactAction.call") : t("contactAction.email")
  const PrimaryIcon = isPhone ? Phone : Mail
  const HeaderIcon = kind === "value" ? Hash : UserRound
  const copiedMessage = isPhone
    ? t("contactAction.phoneCopied")
    : isEmail
      ? t("contactAction.emailCopied")
      : t("contactAction.valueCopied")
  const ariaLabel = isPhone
    ? t("contactAction.phoneActions")
    : isEmail
      ? t("contactAction.emailActions")
      : t("contactAction.valueActions")

  const buttonCount = (href ? 1 : 0) + 1 + (canShare ? 1 : 0)
  /** Three across in a 15rem popover leaves ~70px a tile — not enough for an
   *  inline icon + label in any of the three languages. Stack the icon over the
   *  label instead, so neither the glyph nor the word ever gets squeezed. */
  const stacked = buttonCount === 3
  const actionClass = cn(
    "pressable flex items-center justify-center rounded-lg font-medium transition-colors",
    stacked ? "min-h-16 flex-col gap-1 px-1.5 text-xs" : "min-h-11 gap-1.5 px-3 text-sm",
  )

  const trigger = children ?? (
    <span
      className={cn(
        "font-mono text-sm tabular-nums",
        isEmail ? "normal-case" : undefined,
        triggerClassName,
      )}
    >
      {value}
    </span>
  )

  async function share() {
    try {
      await navigator.share({ text: name ? `${name}: ${value}` : String(value) })
    } catch {
      // User dismissed the share sheet — nothing to report.
    }
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          className={cn(
            "pressable -mx-2 -my-1 rounded-lg px-2 py-1 text-left transition-colors hover:bg-accent",
            className,
          )}
          onClick={(e) => e.stopPropagation()}
          aria-label={ariaLabel}
        >
          {trigger}
        </button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        className="w-60 p-2"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center gap-2.5 px-2 pt-1 pb-2">
          <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent">
            <HeaderIcon className="size-4 text-muted-foreground" />
          </div>
          <div className="min-w-0">
            {name ? (
              <p className="truncate text-sm font-medium">{name}</p>
            ) : null}
            <p
              className={cn(
                "truncate text-xs text-muted-foreground",
                !isEmail && "font-mono tabular-nums",
                !name && "text-sm font-medium text-foreground",
              )}
            >
              {value}
            </p>
          </div>
        </div>
        <div
          className={cn(
            "grid gap-1.5",
            buttonCount === 3 ? "grid-cols-3" : buttonCount === 2 ? "grid-cols-2" : "grid-cols-1",
          )}
        >
          {href && (
            <a href={href} className={cn(actionClass, "bg-primary/10 text-primary hover:bg-primary/15")}>
              <PrimaryIcon className="size-4 shrink-0" />
              <span className="max-w-full truncate">{primaryLabel}</span>
            </a>
          )}
          <button
            type="button"
            className={cn(actionClass, "bg-muted hover:bg-accent")}
            onClick={async () => {
              await navigator.clipboard.writeText(value)
              toast.success(copiedMessage)
            }}
          >
            <Copy className="size-4 shrink-0" />
            <span className="max-w-full truncate">{t("contactAction.copy")}</span>
          </button>
          {canShare && (
            <button type="button" className={cn(actionClass, "bg-muted hover:bg-accent")} onClick={share}>
              <Share2 className="size-4 shrink-0" />
              <span className="max-w-full truncate">{t("contactAction.share")}</span>
            </button>
          )}
        </div>
        {showChat && (
          <button
            type="button"
            className="pressable mt-1.5 flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-primary px-3 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
            onClick={() => {
              setOpen(false)
              void launcher.openChat(chat)
            }}
          >
            <MessageCircleMore className="size-4 shrink-0" />
            <span className="truncate">{t("contactAction.chat")}</span>
          </button>
        )}
      </PopoverContent>
    </Popover>
  )
}
