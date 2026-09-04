"use client"

import { Monitor, Moon, Sun } from "lucide-react"
import { useTheme } from "next-themes"
import { useSyncExternalStore } from "react"

import { cn } from "@/lib/utils"

const subscribeNoop = () => () => {}
const getTrue = () => true
const getFalse = () => false

const OPTIONS = [
  { value: "light", icon: Sun, label: "Light" },
  { value: "system", icon: Monitor, label: "System" },
  { value: "dark", icon: Moon, label: "Dark" },
] as const

/** Segmented light/system/dark control used in the sidebar footer + mobile menu. */
export function ThemeToggle({ className }: { className?: string }) {
  const { theme, setTheme } = useTheme()

  // Theme is unknown until hydration; render an inactive state on the server.
  const mounted = useSyncExternalStore(subscribeNoop, getTrue, getFalse)

  return (
    <div
      role="radiogroup"
      aria-label="Theme"
      className={cn("border-border/60 bg-muted/40 inline-flex rounded-lg border p-0.5", className)}
    >
      {OPTIONS.map(({ value, icon: Icon, label }) => {
        const active = mounted && theme === value
        return (
          <button
            key={value}
            role="radio"
            aria-checked={active}
            aria-label={label}
            onClick={() => setTheme(value)}
            className={cn(
              "flex h-7 min-w-9 items-center justify-center rounded-[6px] transition-all duration-150",
              active
                ? "bg-background text-foreground shadow-xs"
                : "text-muted-foreground hover:text-foreground",
            )}
          >
            <Icon className="size-3.5" />
          </button>
        )
      })}
    </div>
  )
}
