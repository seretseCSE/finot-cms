import type { MetadataRoute } from "next"

import {
  EN_ONLY_PATHS,
  LOCALE_TAGS,
  LOCALIZED_PATHS,
  MARKETING_LOCALES,
  localeUrl,
} from "@/lib/marketing/site"

export default function sitemap(): MetadataRoute.Sitemap {
  const entries: MetadataRoute.Sitemap = []

  for (const path of LOCALIZED_PATHS) {
    const languages: Record<string, string> = {}
    for (const locale of MARKETING_LOCALES) {
      languages[LOCALE_TAGS[locale]] = localeUrl(locale, path)
    }
    for (const locale of MARKETING_LOCALES) {
      entries.push({
        url: localeUrl(locale, path),
        changeFrequency: "weekly",
        priority: path === "" ? 1 : path.split("/").length > 2 ? 0.7 : 0.8,
        alternates: { languages },
      })
    }
  }

  for (const path of EN_ONLY_PATHS) {
    entries.push({
      url: localeUrl("en", path),
      changeFrequency: "monthly",
      priority: 0.3,
    })
  }

  return entries
}
