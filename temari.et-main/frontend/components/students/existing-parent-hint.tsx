"use client"

import { UserCheck } from "lucide-react"
import { useEffect, useState } from "react"

import { Button } from "@/components/ui/button"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { GuardianSearchResult } from "@/lib/types"

/** Complete Ethiopian mobile (local 09/07 or +251) — worth a lookup. */
function isCompletePhone(phone: string): boolean {
  const digits = phone.replace(/[\s\-()]/g, "")
  return /^0[97]\d{8}$/.test(digits) || /^(\+?251)[97]\d{8}$/.test(digits)
}

/**
 * Debounced duplicate lookup for "new guardian" forms: once a complete phone
 * is typed, ask /guardians/search whether a parent already owns it. Returns
 * the match so the form can offer "use the existing parent" instead of
 * letting the operator create a lookalike record.
 */
export function useExistingParentMatch(phone: string | null | undefined) {
  const [match, setMatch] = useState<GuardianSearchResult | null>(null)

  useEffect(() => {
    const value = (phone ?? "").trim()
    const complete = value !== "" && isCompletePhone(value)

    let cancelled = false
    const timer = setTimeout(
      () => {
        if (!complete) {
          setMatch(null)
          return
        }
        apiFetch<{ data: GuardianSearchResult[] }>(
          `/guardians/search?q=${encodeURIComponent(value)}`,
        )
          .then((res) => {
            if (!cancelled) setMatch(res.data[0] ?? null)
          })
          .catch(() => {
            if (!cancelled) setMatch(null)
          })
      },
      complete ? 450 : 0,
    )

    return () => {
      cancelled = true
      clearTimeout(timer)
    }
  }, [phone])

  return match
}

/**
 * The prompt itself — an info banner under the phone field naming the
 * existing parent, with one tap to attach them instead.
 */
export function ExistingParentHint({
  match,
  onUse,
}: {
  match: GuardianSearchResult | null
  onUse: (match: GuardianSearchResult) => void
}) {
  const { t } = useTranslation("students")

  if (!match) return null

  return (
    <div className="flex flex-wrap items-center gap-3 rounded-xl border border-info/30 bg-info/10 p-3">
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-info/15 text-info">
        <UserCheck className="size-4.5" />
      </span>
      <div className="min-w-0 flex-1 space-y-0.5">
        <p className="text-sm font-medium">
          {t("existingParent.found", { name: match.name ?? "—" })}
        </p>
        <p className="text-xs text-muted-foreground">
          {[match.public_id, match.phone].filter(Boolean).join(" · ")}
          {match.children_count > 0
            ? ` · ${t("existingParent.children", { count: match.children_count })}`
            : ""}
        </p>
      </div>
      <Button
        type="button"
        size="sm"
        className="h-9 shrink-0 rounded-full"
        onClick={() => onUse(match)}
      >
        {t("existingParent.use")}
      </Button>
    </div>
  )
}
