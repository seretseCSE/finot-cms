"use client"

import { Loader2 } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState, useSyncExternalStore } from "react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import { useTranslation } from "@/lib/i18n"
import {
  getPendingActions,
  getServerPendingActions,
  subscribePendingActions,
} from "@/lib/pending-actions"

/**
 * App-wide "work in progress" guard. While any mutation is in flight
 * (auto-save on the assignment grid, uploads, any form save):
 *
 *  - a floating pill shows what is being saved (aria-live, above the mobile
 *    tab bar);
 *  - closing/reloading the tab triggers the browser's native leave warning;
 *  - in-app navigation (sidebar, bottom nav, links) is intercepted with a
 *    dialog that NAMES the running actions so nothing is interrupted blindly.
 */
export function PendingGuard() {
  const { t } = useTranslation("common")
  const router = useRouter()
  const actions = useSyncExternalStore(
    subscribePendingActions,
    getPendingActions,
    getServerPendingActions,
  )
  const [blockedHref, setBlockedHref] = useState<string | null>(null)

  const busy = actions.length > 0

  // Label keys grouped with counts: "Saving changes ×3", "Uploading file".
  const grouped = useMemo(() => {
    const counts = new Map<string, number>()
    for (const action of actions) {
      counts.set(action.labelKey, (counts.get(action.labelKey) ?? 0) + 1)
    }
    return [...counts.entries()]
  }, [actions])

  // Tab close / reload — browsers only allow their generic message here.
  useEffect(() => {
    if (!busy) return
    const handler = (event: BeforeUnloadEvent) => {
      event.preventDefault()
      event.returnValue = ""
    }
    window.addEventListener("beforeunload", handler)
    return () => window.removeEventListener("beforeunload", handler)
  }, [busy])

  // In-app navigation: catch internal link clicks in the capture phase so we
  // can show OUR dialog (with the action names) before the route changes.
  useEffect(() => {
    if (!busy) return
    const handler = (event: MouseEvent) => {
      if (event.defaultPrevented || event.button !== 0) return
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return
      const anchor = (event.target as HTMLElement | null)?.closest?.("a[href]")
      if (!anchor) return
      const href = anchor.getAttribute("href")
      if (!href || !href.startsWith("/")) return
      if (anchor.getAttribute("target") === "_blank") return
      event.preventDefault()
      event.stopPropagation()
      setBlockedHref(href)
    }
    document.addEventListener("click", handler, true)
    return () => document.removeEventListener("click", handler, true)
  }, [busy])

  return (
    <>
      {/* Floating activity pill — sits above the mobile tab bar. */}
      <div
        aria-live="polite"
        className="pointer-events-none fixed right-4 bottom-24 z-50 md:bottom-6"
      >
        {busy && (
          <div className="flex items-center gap-2 rounded-full border bg-background/90 px-3.5 py-2 text-xs font-medium shadow-lg backdrop-blur-xl">
            <Loader2 className="size-3.5 animate-spin text-primary" />
            {actions.length === 1
              ? t(actions[0].labelKey)
              : t("pending.count", { count: actions.length })}
          </div>
        )}
      </div>

      <AlertDialog
        open={blockedHref !== null}
        onOpenChange={(open) => !open && setBlockedHref(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("pending.leaveTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("pending.leaveDescription")}</AlertDialogDescription>
          </AlertDialogHeader>
          {grouped.length > 0 && (
            <ul className="space-y-1.5 rounded-xl border border-warning/30 bg-warning/10 px-3 py-2.5 text-sm">
              {grouped.map(([labelKey, count]) => (
                <li key={labelKey} className="flex items-center gap-2">
                  <Loader2 className="size-3.5 shrink-0 animate-spin text-warning" />
                  <span className="min-w-0 flex-1 truncate">{t(labelKey)}</span>
                  {count > 1 && (
                    <span className="text-xs text-muted-foreground tabular-nums">×{count}</span>
                  )}
                </li>
              ))}
            </ul>
          )}
          <AlertDialogFooter>
            <AlertDialogCancel>{t("pending.stay")}</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                const href = blockedHref
                setBlockedHref(null)
                if (href) router.push(href)
              }}
            >
              {t("pending.leaveAnyway")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
