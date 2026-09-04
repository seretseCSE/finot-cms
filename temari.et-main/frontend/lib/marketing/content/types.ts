import type {
  AudienceSlug,
  FeatureSlug,
  MarketingLocale,
} from "@/lib/marketing/site"

export interface PageMeta {
  title: string
  description: string
}

export interface FeatureContent {
  /** Short name for cards, nav and related links. */
  name: string
  /** One line for the features grid. */
  tagline: string
  meta: PageMeta
  hero: { headline: string; sub: string }
  capabilities: { title: string; body: string }[]
  /** Optional focused list block ("What you get"). */
  deepDive?: { title: string; points: string[] }
  related: FeatureSlug[]
}

export interface AudienceContent {
  name: string
  meta: PageMeta
  hero: { headline: string; sub: string }
  points: { title: string; body: string }[]
  featuresTitle: string
  featureLinks: FeatureSlug[]
}

export interface PricingPlan {
  name: string
  price: string
  unit: string
  /** Secondary billing line under the per-day price, e.g. "Billed as 200 ETB / year". */
  perDay?: string
  payer: string
  description: string
  features: string[]
  cta: string
  href: string
  highlighted?: boolean
}

export interface FaqItem {
  q: string
  a: string
}

export interface MarketingDict {
  locale: MarketingLocale
  /** Slim banner above the nav on every marketing page (links to /pricing). */
  announcement: { text: string; cta: string }
  nav: {
    features: string
    examPrep: string
    tutors: string
    pricing: string
    about: string
    signIn: string
    getStarted: string
    openApp: string
    menu: string
    language: string
  }
  footer: {
    tagline: string
    product: string
    audiences: string
    company: string
    columns: {
      product: { label: string; path: string }[]
      audiences: { label: string; path: string }[]
      company: { label: string; path: string }[]
    }
    copyright: string
    madeIn: string
  }
  common: {
    learnMore: string
    getStarted: string
    talkToUs: string
    seePricing: string
    allFeatures: string
    relatedFeatures: string
    startPracticing: string
  }
  ctaBand: { headline: string; sub: string; primary: string; secondary: string }
  home: {
    meta: PageMeta
    /** Audience router: "which one are you?" cards near the top of home. */
    audiences: {
      headline: string
      sub: string
      items: {
        title: string
        body: string
        /** Locale-less path; paths outside LOCALIZED_PATHS (e.g. /tutors) stay unprefixed. */
        href: string
      }[]
    }
    hero: {
      badge: string
      /** First headline line (plain ink). */
      headline: string
      /** Second headline line — rendered with the brand gradient. */
      headline2: string
      sub: string
      primary: string
      secondary: string
      /** Quiet trust line under the CTAs (per-day price · free semester). */
      note: string
    }
    banks: { title: string }
    schools: { title: string }
    stats: { value: string; label: string }[]
    testimonials: {
      headline: string
      sub: string
      items: { quote: string; name: string; role: string }[]
    }
    features: { headline: string; sub: string }
    /** Real product screenshots; items align 1:1 with TOUR_SHOTS in the component. */
    tour: {
      headline: string
      sub: string
      items: { title: string; body: string }[]
    }
    parents: {
      headline: string
      sub: string
      points: { title: string; body: string }[]
    }
    ethiopia: {
      headline: string
      sub: string
      items: { title: string; body: string }[]
    }
    examPrep: { headline: string; sub: string; points: string[]; cta: string }
    trust: {
      headline: string
      sub: string
      items: { title: string; body: string }[]
    }
    pricing: {
      headline: string
      sub: string
      price: string
      unit: string
      note: string
      cta: string
    }
  }
  featuresIndex: {
    meta: PageMeta
    hero: { headline: string; sub: string }
  }
  features: Record<FeatureSlug, FeatureContent>
  audiences: Record<AudienceSlug, AudienceContent>
  examPrep: {
    meta: PageMeta
    hero: { badge: string; headline: string; sub: string; primary: string }
    grades: { grade: string; title: string; body: string }[]
    how: { headline: string; steps: { title: string; body: string }[] }
    ai: {
      headline: string
      sub: string
      points: { title: string; body: string }[]
    }
    pricingNote: { title: string; body: string }
  }
  pricing: {
    meta: PageMeta
    hero: { headline: string; sub: string }
    /** The try-before-you-commit offer: one full semester free. */
    freeSemester: { badge: string; title: string; body: string; cta: string }
    plans: PricingPlan[]
    faqTitle: string
    faq: FaqItem[]
  }
  about: {
    meta: PageMeta
    hero: { headline: string; sub: string }
    story: string[]
    values: { title: string; body: string }[]
    factsTitle: string
    facts: { label: string; value: string }[]
  }
  contact: {
    meta: PageMeta
    hero: { headline: string; sub: string }
    channels: { title: string; body: string; value: string; href: string }[]
    schools: { title: string; body: string; cta: string }
  }
  faq: {
    meta: PageMeta
    hero: { headline: string; sub: string }
    groups: { title: string; items: FaqItem[] }[]
  }
}
