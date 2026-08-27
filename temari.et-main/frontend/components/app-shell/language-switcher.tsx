"use client"

import { LOCALES, useLocale } from "@/lib/i18n"
import { cn } from "@/lib/utils"

const LOCALE_LABELS: Record<string, string> = { en: "EN", am: "አማ", om: "OM" }

interface LanguageSwitcherProps {
  variant?: "default" | "ghost-light"
}

export function LanguageSwitcher({ variant = "default" }: LanguageSwitcherProps) {
  const { locale, setLocale } = useLocale()

  const isLight = variant === "ghost-light"

  return (
    <div
      className={cn(
        "flex items-center gap-1 rounded-full px-1 py-1",
        isLight ? "border border-white/30 bg-white/10 backdrop-blur-sm" : "border bg-background",
      )}
    >
      {LOCALES.map((code) => (
        <button
          key={code}
          onClick={() => setLocale(code)}
          className={cn(
            "rounded-full px-3 py-0.5 text-sm font-medium transition-colors",
            isLight
              ? locale === code
                ? "bg-white text-primary"
                : "text-white/80 hover:text-white"
              : locale === code
                ? "bg-foreground text-background"
                : "text-muted-foreground hover:text-foreground",
          )}
        >
          {LOCALE_LABELS[code] ?? code.toUpperCase()}
        </button>
      ))}
    </div>
  )
}
