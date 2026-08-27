"use client"

import { useEffect } from "react"

/**
 * The root layout renders <html lang="en">; localized marketing pages correct
 * it client-side (crawlers rely on hreflang + content language, so this is a
 * progressive enhancement for screen readers and translation tools).
 */
export function SetHtmlLang({ lang }: { lang: string }) {
  useEffect(() => {
    const previous = document.documentElement.lang
    document.documentElement.lang = lang
    return () => {
      document.documentElement.lang = previous
    }
  }, [lang])
  return null
}
