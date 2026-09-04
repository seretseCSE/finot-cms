import Link from "next/link"
import { ArrowRight, Gift } from "lucide-react"

import { MarketingFooter } from "@/components/marketing/footer"
import { MarketingNav } from "@/components/marketing/nav"
import { SetHtmlLang } from "@/components/marketing/set-html-lang"
import type { MarketingDict } from "@/lib/marketing/content"
import {
  LOCALE_TAGS,
  localePath,
  type MarketingLocale,
} from "@/lib/marketing/site"

/** Shared chrome for every marketing page: offer banner + nav on top, footer below. */
export function MarketingShell({
  locale,
  dict,
  path,
  children,
}: {
  locale: MarketingLocale
  dict: MarketingDict
  /** Locale-less path of the current page (for language switching). */
  path: string
  children: React.ReactNode
}) {
  return (
    <div className="flex min-h-[100dvh] flex-col">
      <SetHtmlLang lang={LOCALE_TAGS[locale]} />
      {/* The free-semester offer rides above the nav on every page. */}
      <Link
        href={localePath(locale, "/pricing")}
        className="brand-hero group flex items-center justify-center gap-2 px-4 py-2 text-center text-[13px] font-medium text-white"
      >
        <Gift className="size-3.5 shrink-0 text-emerald-300" />
        <span className="truncate">{dict.announcement.text}</span>
        <span className="hidden shrink-0 items-center gap-1 font-semibold text-emerald-300 sm:inline-flex">
          {dict.announcement.cta}
          <ArrowRight className="size-3.5 transition-transform duration-200 group-hover:translate-x-0.5" />
        </span>
      </Link>
      <MarketingNav locale={locale} dict={dict} path={path} />
      <main className="flex-1">{children}</main>
      <MarketingFooter locale={locale} dict={dict} path={path} />
    </div>
  )
}
