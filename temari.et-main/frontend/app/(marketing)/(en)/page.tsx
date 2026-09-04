import { HomePage } from "@/components/marketing/pages/home"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({ locale: "en", path: "", ...dict.home.meta })

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="">
      <HomePage locale="en" dict={dict} />
    </MarketingShell>
  )
}
