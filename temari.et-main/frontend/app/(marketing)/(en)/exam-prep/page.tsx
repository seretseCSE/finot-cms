import { ExamPrepPage } from "@/components/marketing/pages/exam-prep"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({
  locale: "en",
  path: "/exam-prep",
  ...dict.examPrep.meta,
})

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/exam-prep">
      <ExamPrepPage locale="en" dict={dict} />
    </MarketingShell>
  )
}
