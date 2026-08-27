import { Section } from "@/components/marketing/section"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

export const metadata = marketingMetadata({
  locale: "en",
  path: "/privacy",
  title: "Privacy policy",
  description:
    "How Temari.et collects, uses and protects data for schools, students, parents and staff.",
  enOnly: true,
})

const sections: { title: string; body: string[] }[] = [
  {
    title: "Who we are",
    body: [
      "Temari.et is operated by the Temari team in Addis Ababa, Ethiopia. We provide a school management platform used by schools, their staff, students and guardians, and a national exam preparation service open to individual learners.",
    ],
  },
  {
    title: "Data we process",
    body: [
      "For school accounts: student records (names, dates of birth, guardians, enrollment history, attendance, assessment results, fee and payment records, documents the school uploads), employee records (HR files, positions, payroll) and account information (phone number, preferred language).",
      "For individual exam prep accounts: your phone number, preferred language, grade and practice history.",
      "National ID (Fayda) numbers, where a school records them, are stored as irreversible hashes, never in plain text.",
    ],
  },
  {
    title: "Why we process it",
    body: [
      "To run the school's operations on the school's behalf: registers, attendance, report cards, fee management and communication with families. The school decides what is recorded; Temari processes it to provide the service.",
      "To deliver notifications the school or platform sends you, by in-app feed, SMS or email, in your chosen language. You can mute non-critical categories in your settings.",
      "To operate and improve the exam preparation service, including adapting practice to your history.",
    ],
  },
  {
    title: "Who can see your data",
    body: [
      "Access is strictly scoped. A school sees only its own records, within its own branches. Guardians see only the children linked to them, with permissions the school controls. Students see their own records. Other schools see nothing; when a student transfers, the former school keeps a frozen archive of its own era only.",
      "We never sell personal data. We share it only with processors needed to run the service (such as SMS delivery and cloud infrastructure providers) under confidentiality obligations, or where the law requires.",
    ],
  },
  {
    title: "Payments",
    body: [
      "School fees are paid directly to the school's own bank or wallet accounts. Temari records and verifies payment references but does not hold family money and takes no share of school fees. Temari's own subscriptions are collected through licensed payment gateways.",
    ],
  },
  {
    title: "Storage and security",
    body: [
      "Data is stored on secured infrastructure with access controls, encryption in transit and audit logging. Private files are served only through signed, expiring links. Documents carry QR verification that confirms authenticity without exposing private contents.",
    ],
  },
  {
    title: "Your choices",
    body: [
      "You can change your language and notification preferences at any time in the app. For corrections to school records, contact your school, which controls its own register. For questions about this policy or your data, contact info@temari.et.",
    ],
  },
]

export default function Page() {
  return (
    <MarketingShell locale="en" dict={dict} path="/privacy">
      <Section>
        <div className="mx-auto max-w-3xl">
          <h1 className="text-4xl font-semibold tracking-tight">Privacy policy</h1>
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
