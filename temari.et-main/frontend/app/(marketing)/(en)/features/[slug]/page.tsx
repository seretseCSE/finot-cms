import { notFound } from "next/navigation"

import { FeatureDetailPage } from "@/components/marketing/pages/feature-detail"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"
import { FEATURE_SLUGS, type FeatureSlug } from "@/lib/marketing/site"

export const dynamicParams = false

export function generateStaticParams() {
  return FEATURE_SLUGS.map((slug) => ({ slug }))
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params
  const dict = getMarketingDict("en")
  const feature = dict.features[slug as FeatureSlug]
  if (!feature) notFound()
  return marketingMetadata({ locale: "en", path: `/features/${slug}`, ...feature.meta })
}

export default async function Page({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params
  if (!FEATURE_SLUGS.includes(slug as FeatureSlug)) notFound()
  const dict = getMarketingDict("en")
  return (
    <MarketingShell locale="en" dict={dict} path={`/features/${slug}`}>
      <FeatureDetailPage locale="en" dict={dict} slug={slug as FeatureSlug} />
    </MarketingShell>
  )
}
