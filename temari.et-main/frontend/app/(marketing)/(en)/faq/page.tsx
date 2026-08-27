import { FaqPage } from "@/components/marketing/pages/faq"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({ locale: "en", path: "/faq", ...dict.faq.meta })

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/faq">
      <FaqPage locale="en" dict={dict} />
    </MarketingShell>
  )
}
