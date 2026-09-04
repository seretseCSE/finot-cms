import { PricingPage } from "@/components/marketing/pages/pricing"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({ locale: "en", path: "/pricing", ...dict.pricing.meta })

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/pricing">
      <PricingPage locale="en" dict={dict} />
    </MarketingShell>
  )
}
