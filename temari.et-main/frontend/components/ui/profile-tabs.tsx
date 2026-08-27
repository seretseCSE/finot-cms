"use client"

import { usePathname, useRouter, useSearchParams } from "next/navigation"
import { useCallback, useEffect, useState } from "react"
import type { LucideIcon } from "lucide-react"

import { cn } from "@/lib/utils"

export interface ProfileTab<K extends string = string> {
  key: K
  label: string
  icon?: LucideIcon
}

/**
 * URL-synced tab state for profile/settings pages: reads ?tab= on load and
 * client-side navigation (deep links, back button), writes it back with
 * router.replace so switching tabs never pollutes history or scrolls.
 * Unknown values fall back to the first key.
 */
export function useProfileTabs<K extends string>(keys: readonly K[], defaultKey: K) {
  const router = useRouter()
  const pathname = usePathname()
  const searchParams = useSearchParams()
  const requested = searchParams.get("tab")
  const [tab, setTabState] = useState<K>(defaultKey)

  useEffect(() => {
    if (requested !== null && keys.includes(requested as K)) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- URL → state sync
      setTabState(requested as K)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- keys is a stable literal
  }, [requested])

  const setTab = useCallback(
    (next: K) => {
      setTabState(next)
      const params = new URLSearchParams(searchParams.toString())
      if (next === defaultKey) params.delete("tab")
      else params.set("tab", next)
      const query = params.toString()
      router.replace(query ? `${pathname}?${query}` : pathname, { scroll: false })
    },
    [router, pathname, searchParams, defaultKey],
  )

  return [tab, setTab] as const
}

/**
 * The scrollable pill tab bar (same look as the student profile): native-app
 * feel on mobile — swipeable, ≥44px touch targets, no wrap.
 */
export function ProfileTabBar<K extends string>({
  tabs,
  value,
  onChange,
  className,
}: {
  tabs: readonly ProfileTab<K>[]
  value: K
  onChange: (key: K) => void
  className?: string
}) {
  return (
    <div
      role="tablist"
      className={cn("scrollbar-none flex gap-1.5 overflow-x-auto pb-1", className)}
    >
      {tabs.map(({ key, label, icon: Icon }) => (
        <button
          key={key}
          type="button"
          role="tab"
          aria-selected={value === key}
          onClick={() => onChange(key)}
          className={cn(
            "flex h-10 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-medium transition-colors",
            value === key
              ? "bg-primary text-primary-foreground"
              : "bg-muted text-muted-foreground hover:bg-muted/70",
          )}
        >
          {Icon && <Icon className="size-4" />}
          {label}
        </button>
      ))}
    </div>
  )
}
