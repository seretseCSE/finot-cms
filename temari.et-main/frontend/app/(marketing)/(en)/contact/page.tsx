import { ContactPage } from "@/components/marketing/pages/contact"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({ locale: "en", path: "/contact", ...dict.contact.meta })

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/contact">
      <ContactPage locale="en" dict={dict} />
    </MarketingShell>
  )
}
