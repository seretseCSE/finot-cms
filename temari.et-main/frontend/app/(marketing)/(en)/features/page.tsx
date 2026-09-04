import { FeaturesIndexPage } from "@/components/marketing/pages/features-index"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({
  locale: "en",
  path: "/features",
  ...dict.featuresIndex.meta,
})

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/features">
      <FeaturesIndexPage locale="en" dict={dict} />
    </MarketingShell>
  )
}
