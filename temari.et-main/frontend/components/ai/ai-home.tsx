"use client"

import { Sparkles } from "lucide-react"
import { useState } from "react"

import { AiComposer } from "@/components/ai/ai-composer"
import { UpgradeDialog } from "@/components/ai/upgrade-dialog"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { Skeleton } from "@/components/ui/skeleton"
import { useTranslation } from "@/lib/i18n"
import type { AiAssistantInfo } from "@/lib/types"

/**
 * The ChatGPT-style home: greeting + suggestion chips + the composer,
 * visible immediately — no session exists yet. There is NOTHING to pick:
 * the workspace already decided the assistant (server-composed from every
 * capability the user holds there), so the user just types. Sending creates
 * the session and drops straight into the streaming thread.
 */
export function AiHome({
  assistant,
  loading,
  sending,
  userName,
  maxAttachments,
  desktop,
  onStart,
}: {
  assistant: AiAssistantInfo | null
  loading: boolean
  sending: boolean
  userName: string | null
  maxAttachments: number
  desktop: boolean
  onStart: (text: string, files: File[]) => void
}) {
  const { t } = useTranslation("ai")
  const [draft, setDraft] = useState("")
  const [upgradeOpen, setUpgradeOpen] = useState(false)

  if (loading) {
    return (
      <div className="flex h-full items-center justify-center px-4">
        <div className="w-full max-w-2xl space-y-4">
          <Skeleton className="mx-auto h-8 w-2/3 rounded-xl" />
          <Skeleton className="mx-auto h-4 w-1/2 rounded-lg" />
          <Skeleton className="h-28 w-full rounded-[1.625rem]" />
        </div>
      </div>
    )
  }

  if (assistant === null) {
    return (
      <div className="flex h-full items-center justify-center px-4">
        <EmptyState icon={Sparkles} title={t("noAccess.title")} description={t("noAccess.description")} compact />
      </div>
    )
  }

  const entitlement = assistant.entitlement
  const isFamily = assistant.surface === "family"
  const outOfQuota = entitlement.remaining !== null && entitlement.remaining <= 0

  // One chip per capability first (a director sees analytics + records +
  // finance side by side), then round-robin deeper — capped at 4.
  const suggestions: string[] = []
  for (let i = 0; i < 4 && suggestions.length < 4; i++) {
    for (const lane of assistant.lanes) {
      if (suggestions.length >= 4) break
      const suggestion = t(`suggestions.${lane}.${i}`)
      if (!suggestion.startsWith("suggestions.") && !suggestions.includes(suggestion)) {
        suggestions.push(suggestion)
      }
    }
  }

  return (
    <div className="flex h-full min-h-0 flex-col items-center justify-center gap-7 overflow-y-auto px-4 py-6 md:px-8">
      <div className="text-center">
        <div className="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl bg-primary/10">
          <Sparkles className="size-6 text-primary" />
        </div>
        <h2 className="font-display text-2xl font-semibold tracking-tight md:text-3xl">
          {userName ? t("greeting", { name: userName }) : t("thread.welcome")}
        </h2>
        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
          {t(`greetingHints.${assistant.surface}`)}
        </p>
      </div>

      {suggestions.length > 0 && (
        <div className="flex max-w-2xl flex-wrap justify-center gap-2">
          {suggestions.map((suggestion) => (
            <button
              key={suggestion}
              type="button"
              onClick={() => onStart(suggestion, [])}
              disabled={sending || outOfQuota}
              className="pressable rounded-full border bg-card px-3.5 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50"
            >
              {suggestion}
            </button>
          ))}
        </div>
      )}

      <div className="w-full max-w-2xl">
        {outOfQuota && (
          <div className="mb-2 flex flex-wrap items-center justify-between gap-2 rounded-2xl border bg-muted/50 px-4 py-2.5">
            <p className="text-sm">
              {entitlement.plan === "free" ? t("quota.limitReachedFree") : t("quota.limitReached")}
              {!isFamily && entitlement.plan === "staff_free" && (
                <span className="block text-xs text-muted-foreground">{t("quota.schoolPlanNote")}</span>
              )}
            </p>
            {isFamily && (
              <Button size="sm" onClick={() => setUpgradeOpen(true)}>
                <Sparkles className="size-4" /> {t("quota.upgradeCta")}
              </Button>
            )}
          </div>
        )}

        <AiComposer
          value={draft}
          onChange={setDraft}
          onSend={(text, files) => {
            setDraft("")
            onStart(text, files)
          }}
          sending={sending}
          disabled={outOfQuota}
          maxAttachments={maxAttachments}
          hint={
            entitlement.remaining !== null && entitlement.remaining <= 10 && !outOfQuota
              ? t("quota.remainingToday", { count: entitlement.remaining })
              : undefined
          }
          autoFocus={desktop}
        />
        <p className="mt-2 text-center text-[11px] text-muted-foreground">{t("thread.disclaimer")}</p>
      </div>

      <UpgradeDialog open={upgradeOpen} onOpenChange={setUpgradeOpen} />
    </div>
  )
}
