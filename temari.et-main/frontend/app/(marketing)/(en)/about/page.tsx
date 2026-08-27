import { AboutPage } from "@/components/marketing/pages/about"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({ locale: "en", path: "/about", ...dict.about.meta })

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/about">
      <AboutPage locale="en" dict={dict} />
    </MarketingShell>
  )
}
