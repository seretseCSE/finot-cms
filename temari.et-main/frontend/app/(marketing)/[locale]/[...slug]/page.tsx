import { notFound } from "next/navigation"

import { AboutPage } from "@/components/marketing/pages/about"
import { AudiencePage } from "@/components/marketing/pages/audience"
import { ContactPage } from "@/components/marketing/pages/contact"
import { ExamPrepPage } from "@/components/marketing/pages/exam-prep"
import { FaqPage } from "@/components/marketing/pages/faq"
import { FeatureDetailPage } from "@/components/marketing/pages/feature-detail"
import { FeaturesIndexPage } from "@/components/marketing/pages/features-index"
import { PricingPage } from "@/components/marketing/pages/pricing"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict, type MarketingDict } from "@/lib/marketing/content"
import type { PageMeta } from "@/lib/marketing/content/types"
import { marketingMetadata } from "@/lib/marketing/seo"
import {
  AUDIENCE_SLUGS,
  FEATURE_SLUGS,
  LOCALIZED_PATHS,
  type AudienceSlug,
  type FeatureSlug,
  type MarketingLocale,
} from "@/lib/marketing/site"

/**
 * All localized marketing pages besides the home: /am/features,
 * /om/pricing, /am/features/attendance, ... One statically generated
 * route per translated path (see LOCALIZED_PATHS).
 */

const LOCALES: MarketingLocale[] = ["am", "om"]

export const dynamicParams = false

export function generateStaticParams() {
  const params: { locale: string; slug: string[] }[] = []
  for (const locale of LOCALES) {
    for (const path of LOCALIZED_PATHS) {
      if (path === "") continue // home has its own route file
      params.push({ locale, slug: path.slice(1).split("/") })
    }
  }
  return params
}

function resolve(
  locale: MarketingLocale,
  slug: string[],
): { meta: PageMeta; render: (dict: MarketingDict) => React.ReactNode } | null {
  const dict = getMarketingDict(locale)
  const [head, second, third] = slug

  if (head === "features" && slug.length === 1) {
    return {
      meta: dict.featuresIndex.meta,
      render: (d) => <FeaturesIndexPage locale={locale} dict={d} />,
    }
  }
  if (head === "features" && slug.length === 2 && FEATURE_SLUGS.includes(second as FeatureSlug)) {
    const feature = second as FeatureSlug
    return {
      meta: dict.features[feature].meta,
      render: (d) => <FeatureDetailPage locale={locale} dict={d} slug={feature} />,
    }
  }
  if (head === "exam-prep" && slug.length === 1) {
    return { meta: dict.examPrep.meta, render: (d) => <ExamPrepPage locale={locale} dict={d} /> }
  }
  if (head === "pricing" && slug.length === 1) {
    return { meta: dict.pricing.meta, render: (d) => <PricingPage locale={locale} dict={d} /> }
  }
  if (head === "for" && slug.length === 2 && AUDIENCE_SLUGS.includes(second as AudienceSlug)) {
    const audience = second as AudienceSlug
    return {
      meta: dict.audiences[audience].meta,
      render: (d) => <AudiencePage locale={locale} dict={d} audience={audience} />,
    }
  }
  if (head === "about" && slug.length === 1) {
    return { meta: dict.about.meta, render: (d) => <AboutPage locale={locale} dict={d} /> }
  }
  if (head === "contact" && slug.length === 1) {
    return { meta: dict.contact.meta, render: (d) => <ContactPage locale={locale} dict={d} /> }
  }
  if (head === "faq" && slug.length === 1) {
    return { meta: dict.faq.meta, render: (d) => <FaqPage locale={locale} dict={d} /> }
  }
  void third
  return null
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string; slug: string[] }>
}) {
  const { locale, slug } = await params
  if (!LOCALES.includes(locale as MarketingLocale)) notFound()
  const resolved = resolve(locale as MarketingLocale, slug)
  if (!resolved) notFound()
  return marketingMetadata({
    locale: locale as MarketingLocale,
    path: `/${slug.join("/")}`,
    ...resolved.meta,
  })
}

export default async function Page({
  params,
}: {
  params: Promise<{ locale: string; slug: string[] }>
}) {
  const { locale, slug } = await params
  if (!LOCALES.includes(locale as MarketingLocale)) notFound()
  const typedLocale = locale as MarketingLocale
  const resolved = resolve(typedLocale, slug)
  if (!resolved) notFound()
  const dict = getMarketingDict(typedLocale)
  return (
    <MarketingShell locale={typedLocale} dict={dict} path={`/${slug.join("/")}`}>
      {resolved.render(dict)}
    </MarketingShell>
  )
}
