import Link from "next/link"
import { ArrowRight } from "lucide-react"

import { HeroBackdrop } from "@/components/marketing/backdrop"
import { CtaBand } from "@/components/marketing/cta-band"
import { FEATURE_ICONS } from "@/components/marketing/feature-icon"
import { Reveal } from "@/components/marketing/reveal"
import { Section } from "@/components/marketing/section"
import type { MarketingDict } from "@/lib/marketing/content"
import { JsonLd, breadcrumbJsonLd } from "@/lib/marketing/seo"
import {
  FEATURE_SLUGS,
  localeUrl,
  localePath,
  type MarketingLocale,
} from "@/lib/marketing/site"

export function FeaturesIndexPage({
  locale,
  dict,
}: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  return (
    <>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: "Temari.et", url: localeUrl(locale, "") },
          { name: dict.nav.features, url: localeUrl(locale, "/features") },
        ])}
      />
      <Section className="relative overflow-hidden pb-8 md:pb-10">
        <HeroBackdrop watermark="ት" />
        <div className="relative mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {dict.featuresIndex.hero.headline}
          </h1>
          <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-muted-foreground">
            {dict.featuresIndex.hero.sub}
          </p>
        </div>
      </Section>

      <Section className="pt-0 md:pt-0">
        <div className="grid gap-4 md:grid-cols-2">
          {FEATURE_SLUGS.map((slug, i) => {
            const feature = dict.features[slug]
            const Icon = FEATURE_ICONS[slug]
            return (
              <Reveal key={slug} delay={(i % 2) * 60}>
                <Link
                  href={localePath(locale, `/features/${slug}`)}
                  className="group flex h-full items-start gap-5 rounded-2xl border bg-card p-6 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md md:p-7"
                >
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <Icon className="size-5" strokeWidth={1.75} />
                  </span>
                  <span className="min-w-0">
                    <span className="flex items-center gap-2 font-semibold">
                      {feature.name}
                      <ArrowRight className="size-4 text-primary opacity-0 transition-all duration-200 group-hover:translate-x-0.5 group-hover:opacity-100" />
                    </span>
                    <span className="mt-1.5 block text-sm leading-relaxed text-muted-foreground">
                      {feature.hero.sub}
                    </span>
                  </span>
                </Link>
              </Reveal>
            )
          })}
        </div>
      </Section>

      <CtaBand locale={locale} dict={dict} />
    </>
  )
}
