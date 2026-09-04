import type { Metadata } from "next"

import { PublicTutorProfile } from "@/components/tutoring/public-profile"
import { MarketingShell } from "@/components/marketing/shell"
import { getMarketingDict } from "@/lib/marketing/content"
import { marketingMetadata } from "@/lib/marketing/seo"

const dict = getMarketingDict("en")

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1"

interface Props {
  params: Promise<{ slug: string }>
}

/** Server-side profile fetch for SEO titles; the page itself is a client island. */
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params

  let title = "Tutor profile | Temari"
  let description =
    "A verified private tutor on Temari.et — monthly payments protected by escrow."

  try {
    const res = await fetch(`${API_URL}/public/tutors/${slug}`, { next: { revalidate: 300 } })
    if (res.ok) {
      const payload = (await res.json()) as {
        data: { name: string | null; headline: string | null; city: string | null }
      }
      const tutor = payload.data
      if (tutor.name) {
        title = `${tutor.name} — Private Tutor${tutor.city ? ` in ${tutor.city}` : ""} | Temari`
      }
      if (tutor.headline) description = tutor.headline
    }
  } catch {
    // fall back to the generic metadata
  }

  return marketingMetadata({
    locale: "en",
    path: `/tutors/${slug}`,
    title,
    description,
    enOnly: true,
  })
}

export default async function Page({ params }: Props) {
  const { slug } = await params

  return (
    <MarketingShell locale="en" dict={dict} path={`/tutors/${slug}`}>
      <PublicTutorProfile slug={slug} />
    </MarketingShell>
  )
}
