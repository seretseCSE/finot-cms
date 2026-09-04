import { Section } from "@/components/marketing/section"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({
  locale: "en",
  path: "/terms",
  title: "Terms of service",
  description: "The terms that govern use of the Temari.et platform by schools, families and learners.",
  enOnly: true,
})

const sections: { title: string; body: string[] }[] = [
  {
    title: "The service",
    body: [
      "Temari.et, operated from Addis Ababa, Ethiopia, provides a school management platform and a national exam preparation service. By creating an account or using the platform you agree to these terms.",
    ],
  },
  {
    title: "Accounts",
    body: [
      "You are responsible for keeping your credentials confidential. Staff access is granted and revoked by the school. Guardian access derives from the guardian links a school maintains. Accounts used for fraud, harassment or attempts to access other schools' data will be suspended.",
    ],
  },
  {
    title: "The school's data",
    body: [
      "Schools own the records they keep on the platform. Temari processes them to provide the service and does not use them for any other purpose. The school is responsible for the accuracy of its register and for obtaining any consent required to record student and guardian information.",
    ],
  },
  {
    title: "Fees and payments",
    body: [
      "The core platform fee is 200 ETB per student per year, paid by the parent at registration. Optional services (School Plan, AI Exam Prep, NFC hardware) are priced separately and can be declined without losing core features.",
      "School fees are paid by families directly to the school. Temari verifies and receipts these payments but is not a party to them, holds no family money and takes no percentage of school fees.",
    ],
  },
  {
    title: "Acceptable use",
    body: [
      "Do not attempt to breach access controls, scrape other users' data, resell the service without written agreement, or use the messaging system for spam. Exam prep content is for personal study; redistribution of question banks is not permitted.",
    ],
  },
  {
    title: "Availability and changes",
    body: [
      "We aim for high availability but the service is provided as is; features may evolve. Material changes to these terms will be announced in the app before they take effect.",
    ],
  },
  {
    title: "Contact",
    body: ["Questions about these terms: info@temari.et."],
  },
]

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/terms">
      <Section>
        <div className="mx-auto max-w-3xl">
          <h1 className="text-4xl font-semibold tracking-tight">Terms of service</h1>
          <p className="text-muted-foreground mt-3 text-sm">Last updated: July 2026</p>
          <div className="mt-10 space-y-10">
            {sections.map((section) => (
              <div key={section.title}>
                <h2 className="text-xl font-semibold tracking-tight">{section.title}</h2>
                {section.body.map((paragraph) => (
                  <p
                    key={paragraph.slice(0, 32)}
                    className="text-muted-foreground mt-3 text-sm leading-relaxed"
                  >
                    {paragraph}
                  </p>
                ))}
              </div>
            ))}
          </div>
        </div>
      </Section>
    </MarketingShell>
  )
}
