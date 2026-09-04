"use client"

import { Check, Copy } from "lucide-react"
import { useRef, useState } from "react"

import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

export async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    // Clipboard API needs a secure context — fall back to the legacy path.
    try {
      const textarea = document.createElement("textarea")
      textarea.value = text
      textarea.style.position = "fixed"
      textarea.style.opacity = "0"
      document.body.appendChild(textarea)
      textarea.select()
      const ok = document.execCommand("copy")
      textarea.remove()
      return ok
    } catch {
      return false
    }
  }
}

/**
 * The one way a person's public ID code is rendered anywhere in the app:
 * a mono pill that copies itself on tap. Safe inside clickable table rows
 * and links — the click never bubbles.
 */
export function CopyableId({
  value,
  className,
  fallback = null,
}: {
  value: string | null | undefined
  className?: string
  /** Rendered when there is no id (e.g. "—" in table cells). */
  fallback?: React.ReactNode
}) {
  const { t } = useTranslation("common")
  const [copied, setCopied] = useState(false)
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null)

  if (!value) return <>{fallback}</>

  return (
    <button
      type="button"
      title={t("actions.copy")}
      aria-label={`${t("actions.copy")} ${value}`}
      onClick={async (e) => {
        // Rows and cards using this are themselves clickable.
        e.stopPropagation()
        e.preventDefault()
        if (!(await copyText(value))) return
        setCopied(true)
        if (timer.current) clearTimeout(timer.current)
        timer.current = setTimeout(() => setCopied(false), 1500)
      }}
      className={cn(
        "group/copy inline-flex max-w-full cursor-pointer items-center gap-1 rounded-full bg-muted px-2 py-0.5 font-mono text-[10px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground",
        copied && "bg-success/10 text-success hover:bg-success/10 hover:text-success",
        className,
      )}
    >
      <span className="truncate">{copied ? t("actions.copied") : value}</span>
      {copied ? (
        <Check className="size-3 shrink-0" />
      ) : (
        <Copy className="size-3 shrink-0 opacity-50 transition-opacity group-hover/copy:opacity-100" />
      )}
    </button>
  )
}
