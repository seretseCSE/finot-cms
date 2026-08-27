import { notFound } from "next/navigation"

import { AudiencePage } from "@/components/marketing/pages/audience"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"
import { AUDIENCE_SLUGS, type AudienceSlug } from "@/lib/marketing/site"

export const dynamicParams = false

export function generateStaticParams() {
  return AUDIENCE_SLUGS.map((audience) => ({ audience }))
}

export async function generateMetadata({ params }: { params: Promise<{ audience: string }> }) {
  const { audience } = await params
  const dict = getMarketingDict("en")
  const page = dict.audiences[audience as AudienceSlug]
  if (!page) notFound()
  return marketingMetadata({ locale: "en", path: `/for/${audience}`, ...page.meta })
}

export default async function Page({ params }: { params: Promise<{ audience: string }> }) {
  const { audience } = await params
  if (!AUDIENCE_SLUGS.includes(audience as AudienceSlug)) notFound()
  const dict = getMarketingDict("en")
  return (
    <MarketingShell locale="en" dict={dict} path={`/for/${audience}`}>
      <AudiencePage locale="en" dict={dict} audience={audience as AudienceSlug} />
    </MarketingShell>
  )
}
