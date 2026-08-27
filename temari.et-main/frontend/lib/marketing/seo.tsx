import type { Metadata } from "next"

import {
  LOCALE_TAGS,
  MARKETING_LOCALES,
  SITE_URL,
  localeUrl,
  type MarketingLocale,
} from "@/lib/marketing/site"

/**
 * Build the full Metadata for a marketing page: canonical URL, hreflang
 * alternates across the three locales (x-default = English) and OpenGraph /
 * Twitter cards. `path` is the locale-less path ("" for home).
 */
export function marketingMetadata({
  locale,
  path,
  title,
  description,
  enOnly = false,
}: {
  locale: MarketingLocale
  path: string
  title: string
  description: string
  enOnly?: boolean
}): Metadata {
  const canonical = localeUrl(locale, path)
  const languages: Record<string, string> = {}
  if (!enOnly) {
    for (const l of MARKETING_LOCALES) {
      languages[LOCALE_TAGS[l]] = localeUrl(l, path)
    }
    languages["x-default"] = localeUrl("en", path)
  }

  return {
    // Absolute: marketing titles are crafted per page; skip the app's "· Temari" template.
    title: { absolute: title },
    description,
    alternates: {
      canonical,
      ...(enOnly ? {} : { languages }),
    },
    openGraph: {
      title,
      description,
      url: canonical,
      siteName: "Temari.et",
      type: "website",
      locale: LOCALE_TAGS[locale],
      images: [
        {
          url: `${SITE_URL}/og.png`,
          width: 1200,
          height: 630,
          alt: "Temari.et",
        },
      ],
    },
    twitter: {
      card: "summary_large_image",
      title,
      description,
      images: [`${SITE_URL}/og.png`],
    },
  }
}

/** Render a JSON-LD script tag. Data must be a plain serializable object. */
export function JsonLd({ data }: { data: Record<string, unknown> }) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }}
    />
  )
}

export function organizationJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: "Temari.et",
    url: SITE_URL,
    logo: `${SITE_URL}/og.png`,
    email: "info@temari.et",
    address: {
      "@type": "PostalAddress",
      addressLocality: "Addis Ababa",
      addressCountry: "ET",
    },
    contactPoint: {
      "@type": "ContactPoint",
      email: "info@temari.et",
      contactType: "sales",
      availableLanguage: ["en", "am", "om"],
    },
  }
}

export function softwareAppJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "SoftwareApplication",
    name: "Temari.et",
    applicationCategory: "EducationalApplication",
    operatingSystem: "Web",
    description:
      "School management platform for Ethiopia: student records, attendance with smart ID cards, fees, continuous assessment, report cards, exams, courses, timetables, HR, payroll, inventory and SMS to parents — plus Grade 6, 8 and 12 national exam prep and a tutor marketplace. In Amharic, Afaan Oromo and English.",
    url: SITE_URL,
    inLanguage: ["en", "am", "om"],
    featureList: [
      "Student records and enrollment",
      "Attendance with smart ID cards and SMS alerts",
      "School fee invoicing, verification and QR receipts",
      "Continuous assessment, report cards and transcripts",
      "Online exams, assignments and question banks",
      "Courses and learning materials",
      "Automatic clash-free timetables and lesson plans",
      "HR, leave and Ethiopian payroll",
      "Inventory, assets and textbook lending",
      "Parent and student portals in three languages",
      "Grade 6, 8 and 12 national exam prep",
      "Tutor marketplace",
    ],
    offers: {
      "@type": "Offer",
      price: "200",
      priceCurrency: "ETB",
      description:
        "Core platform, per student per year, paid by the parent at registration.",
    },
    provider: {
      "@type": "Organization",
      name: "Temari.et",
      address: {
        "@type": "PostalAddress",
        addressLocality: "Addis Ababa",
        addressCountry: "ET",
      },
    },
  }
}

export function faqJsonLd(items: { q: string; a: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: items.map((item) => ({
      "@type": "Question",
      name: item.q,
      acceptedAnswer: { "@type": "Answer", text: item.a },
    })),
  }
}

export function breadcrumbJsonLd(items: { name: string; url: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: item.name,
      item: item.url,
    })),
  }
}
