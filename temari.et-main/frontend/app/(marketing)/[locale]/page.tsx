import { notFound } from "next/navigation"

import { HomePage } from "@/components/marketing/pages/home"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"
import type { MarketingLocale } from "@/lib/marketing/site"

/** Localized marketing home: /am and /om (English lives at the root). */

const LOCALES: MarketingLocale[] = ["am", "om"]

export const dynamicParams = false

export function generateStaticParams() {
  return LOCALES.map((locale) => ({ locale }))
}

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params
  if (!LOCALES.includes(locale as MarketingLocale)) notFound()
  const dict = getMarketingDict(locale as MarketingLocale)
  return marketingMetadata({ locale: locale as MarketingLocale, path: "", ...dict.home.meta })
}

export default async function Page({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params
  if (!LOCALES.includes(locale as MarketingLocale)) notFound()
  const typedLocale = locale as MarketingLocale
  const dict = getMarketingDict(typedLocale)
  return (
    <MarketingShell locale={typedLocale} dict={dict} path="">
      <HomePage locale={typedLocale} dict={dict} />
    </MarketingShell>
  )
}
