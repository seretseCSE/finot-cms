import Link from "next/link"
import { ArrowRight } from "lucide-react"

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
  type AudienceSlug,
  type MarketingLocale,
} from "@/lib/marketing/site"

export function AudiencePage({
  locale,
  dict,
  audience,
}: {
  locale: MarketingLocale
  dict: MarketingDict
  audience: AudienceSlug
}) {
  const page = dict.audiences[audience]

  return (
    <>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: "Temari.et", url: localeUrl(locale, "") },
          { name: page.name, url: localeUrl(locale, `/for/${audience}`) },
        ])}
      />

      <Section className="relative overflow-hidden pb-10 md:pb-14">
        <HeroBackdrop />
        <div className="relative mx-auto max-w-3xl">
          <p className="inline-flex items-center rounded-full border border-primary/25 bg-primary/5 px-4 py-1.5 text-sm font-semibold text-primary">
            {page.name}
          </p>
          <h1 className="mt-3 text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {page.hero.headline}
          </h1>
          <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted-foreground">
            {page.hero.sub}
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
          {page.points.map((point, i) => (
            <Reveal key={point.title} delay={(i % 2) * 60}>
              <div className="h-full rounded-3xl border bg-card p-6 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:shadow-lg md:p-7">
                <p className="font-semibold">{point.title}</p>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                  {point.body}
                </p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="pt-0 md:pt-0">
        <Reveal>
          <p className="mb-5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {page.featuresTitle}
          </p>
          <div className="flex flex-wrap gap-2.5">
            {page.featureLinks.map((slug) => {
              const feature = dict.features[slug]
              const Icon = FEATURE_ICONS[slug]
              return (
                <Link
                  key={slug}
                  href={localePath(locale, `/features/${slug}`)}
                  className="inline-flex items-center gap-2 rounded-full border bg-card px-4 py-2.5 text-sm font-medium transition-colors hover:bg-muted"
                >
                  <Icon className="size-4 text-primary" strokeWidth={2} />
                  {feature.name}
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
