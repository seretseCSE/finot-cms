import { PublicTutorDirectory } from "@/components/tutoring/public-directory"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({
  locale: "en",
  path: "/tutors",
  title: "Find a Tutor in Ethiopia — Verified Private Tutors | Temari",
  description:
    "Hire verified Ethiopian tutors for every grade and subject — Mathematics, English, EUEE exam prep and more. Pay monthly, protected by Temari.et escrow.",
  enOnly: true,
})

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/tutors">
      <PublicTutorDirectory />
    </MarketingShell>
  )
}
