/**
 * Marketing site configuration. The marketing pages are statically generated
 * per locale (`/` = English canonical, `/am/...`, `/om/...`) with their own
 * server-side dictionaries — separate from the app's client i18n so crawlers
 * see fully rendered HTML in every language.
 */

export const SITE_URL = "https://temari.et"

export const MARKETING_LOCALES = ["en", "am", "om"] as const
export type MarketingLocale = (typeof MARKETING_LOCALES)[number]

export const LOCALE_LABELS: Record<MarketingLocale, string> = {
  en: "English",
  am: "አማርኛ",
  om: "Afaan Oromoo",
}

/** BCP-47 tags for hreflang / html lang. */
export const LOCALE_TAGS: Record<MarketingLocale, string> = {
  en: "en",
  am: "am",
  om: "om",
}

export const FEATURE_SLUGS = [
  "student-management",
  "attendance",
  "id-cards",
  "fees",
  "grading",
  "lms",
  "courses",
  "timetable",
  "communication",
  "hr-payroll",
  "inventory",
] as const
export type FeatureSlug = (typeof FEATURE_SLUGS)[number]

export const AUDIENCE_SLUGS = [
  "schools",
  "teachers",
  "parents",
  "students",
] as const
export type AudienceSlug = (typeof AUDIENCE_SLUGS)[number]

/** Marketing paths that exist in every locale (English also gets /privacy + /terms). */
export const LOCALIZED_PATHS: string[] = [
  "",
  "/features",
  ...FEATURE_SLUGS.map((slug) => `/features/${slug}`),
  "/exam-prep",
  "/pricing",
  ...AUDIENCE_SLUGS.map((slug) => `/for/${slug}`),
  "/about",
  "/contact",
  "/faq",
]

export const EN_ONLY_PATHS: string[] = ["/privacy", "/terms"]

/** Prefix a marketing path with the locale segment ("" stays the en root). */
export function localePath(locale: MarketingLocale, path: string): string {
  const clean = path === "/" ? "" : path
  if (locale === "en") return clean === "" ? "/" : clean
  return `/${locale}${clean}`
}

/** Absolute URL for a locale + path. */
export function localeUrl(locale: MarketingLocale, path: string): string {
  return `${SITE_URL}${localePath(locale, path)}`
}
