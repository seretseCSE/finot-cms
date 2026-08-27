import Link from "next/link"
import { ArrowLeft, ArrowRight, Check } from "lucide-react"

import { HeroBackdrop } from "@/components/marketing/backdrop"
import { CtaBand } from "@/components/marketing/cta-band"
import { FEATURE_ICONS } from "@/components/marketing/feature-icon"
import { Reveal } from "@/components/marketing/reveal"
import { Section } from "@/components/marketing/section"
import { Button } from "@/components/ui/button"
import type { MarketingDict } from "@/lib/marketing/content"
import { JsonLd, breadcrumbJsonLd } from "@/lib/marketing/seo"
import {
  localePath,
  localeUrl,
  type FeatureSlug,
  type MarketingLocale,
} from "@/lib/marketing/site"

export function FeatureDetailPage({
  locale,
  dict,
  slug,
}: {
  locale: MarketingLocale
  dict: MarketingDict
  slug: FeatureSlug
}) {
  const feature = dict.features[slug]
  const Icon = FEATURE_ICONS[slug]

  return (
    <>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: "Temari.et", url: localeUrl(locale, "") },
          { name: dict.nav.features, url: localeUrl(locale, "/features") },
          { name: feature.name, url: localeUrl(locale, `/features/${slug}`) },
        ])}
      />

      <Section className="relative overflow-hidden pb-10 md:pb-14">
        <HeroBackdrop />
        <div className="relative mx-auto max-w-3xl">
          <Link
            href={localePath(locale, "/features")}
            className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
          >
            <ArrowLeft className="size-4" />
            {dict.common.allFeatures}
          </Link>
          <span className="brand-tile mt-8 flex size-13 items-center justify-center rounded-2xl text-white shadow-md">
            <Icon className="size-6" strokeWidth={1.75} />
          </span>
          <h1 className="mt-5 text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {feature.hero.headline}
          </h1>
          <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted-foreground">
            {feature.hero.sub}
          </p>
          <Button asChild size="lg" className="mt-8 px-7">
            <Link href="/signup">
              {dict.common.getStarted}
              <ArrowRight data-icon="inline-end" />
            </Link>
          </Button>
        </div>
      </Section>

      <Section className="pt-0 md:pt-0">
        <div className="grid gap-4 md:grid-cols-2">
          {feature.capabilities.map((cap, i) => (
            <Reveal key={cap.title} delay={(i % 2) * 60}>
              <div className="h-full rounded-3xl border bg-card p-6 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:shadow-lg md:p-7">
                <span className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                  <Check className="size-4" strokeWidth={2.25} />
                </span>
                <p className="mt-4 font-semibold">{cap.title}</p>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                  {cap.body}
                </p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      {feature.deepDive && (
        <Section className="pt-0 md:pt-0">
          <Reveal>
            <div className="rounded-3xl border bg-muted/40 p-8 md:p-10">
              <h2 className="text-xl font-semibold tracking-tight md:text-2xl">
                {feature.deepDive.title}
              </h2>
              <ul className="mt-6 grid gap-x-8 gap-y-4 md:grid-cols-2">
                {feature.deepDive.points.map((point) => (
                  <li
                    key={point}
                    className="flex items-start gap-3 text-sm leading-relaxed"
                  >
                    <Check className="mt-0.5 size-4 shrink-0 text-primary" />
                    {point}
                  </li>
                ))}
              </ul>
            </div>
          </Reveal>
        </Section>
      )}

      <Section className="pt-0 md:pt-0">
        <Reveal>
          <p className="mb-5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {dict.common.relatedFeatures}
          </p>
          <div className="flex flex-wrap gap-2.5">
            {feature.related.map((relatedSlug) => {
              const related = dict.features[relatedSlug]
              const RelatedIcon = FEATURE_ICONS[relatedSlug]
              return (
                <Link
                  key={relatedSlug}
                  href={localePath(locale, `/features/${relatedSlug}`)}
                  className="inline-flex items-center gap-2 rounded-full border bg-card px-4 py-2.5 text-sm font-medium transition-colors hover:bg-muted"
                >
                  <RelatedIcon
                    className="size-4 text-primary"
                    strokeWidth={2}
                  />
                  {related.name}
                </Link>
              )
            })}
          </div>
        </Reveal>
      </Section>

      <CtaBand locale={locale} dict={dict} />
    </>
  )
}
