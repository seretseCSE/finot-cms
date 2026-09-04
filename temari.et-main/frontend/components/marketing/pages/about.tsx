import { HeroBackdrop } from "@/components/marketing/backdrop"
import { CtaBand } from "@/components/marketing/cta-band"
import { Reveal } from "@/components/marketing/reveal"
import { Section, SectionHeader } from "@/components/marketing/section"
import { LogoMark } from "@/components/ui/logo"
import type { MarketingDict } from "@/lib/marketing/content"
import { JsonLd, organizationJsonLd } from "@/lib/marketing/seo"
import type { MarketingLocale } from "@/lib/marketing/site"

export function AboutPage({
  locale,
  dict,
}: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  const page = dict.about

  return (
    <>
      <JsonLd data={organizationJsonLd()} />

      <Section className="relative overflow-hidden pb-10 md:pb-14">
        <HeroBackdrop watermark="ተ" />
        <div className="relative mx-auto max-w-3xl">
          <LogoMark size="lg" />
          <h1 className="mt-6 text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {page.hero.headline}
          </h1>
          <p className="mt-5 text-lg leading-relaxed text-muted-foreground">
            {page.hero.sub}
          </p>
        </div>
      </Section>

      <Section className="pt-0 md:pt-0">
        <div className="mx-auto max-w-3xl space-y-6">
          {page.story.map((paragraph) => (
            <Reveal key={paragraph.slice(0, 24)}>
              <p className="text-base leading-relaxed text-foreground/85 md:text-lg">
                {paragraph}
              </p>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section className="bg-muted/40">
        <div className="grid gap-4 md:grid-cols-3">
          {page.values.map((value, i) => (
            <Reveal key={value.title} delay={i * 60}>
              <div className="h-full rounded-2xl border bg-card p-7 shadow-xs">
                <p className="font-display text-lg font-semibold text-primary">
                  {value.title}
                </p>
                <p className="mt-2.5 text-sm leading-relaxed text-muted-foreground">
                  {value.body}
                </p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section>
        <Reveal>
          <SectionHeader headline={page.factsTitle} />
        </Reveal>
        <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
          {page.facts.map((fact) => (
            <Reveal key={fact.label}>
              <div>
                <dt className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                  {fact.label}
                </dt>
                <dd className="mt-1.5 font-medium">{fact.value}</dd>
              </div>
            </Reveal>
          ))}
        </dl>
      </Section>

      <CtaBand locale={locale} dict={dict} />
    </>
  )
}
