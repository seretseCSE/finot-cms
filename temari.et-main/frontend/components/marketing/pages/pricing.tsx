import Link from "next/link"
import { ArrowRight, Check, Gift } from "lucide-react"

import { HeroBackdrop } from "@/components/marketing/backdrop"
import { FaqList } from "@/components/marketing/faq-list"
import { Reveal } from "@/components/marketing/reveal"
import { Section, SectionHeader } from "@/components/marketing/section"
import { Button } from "@/components/ui/button"
import type { MarketingDict } from "@/lib/marketing/content"
import { JsonLd, breadcrumbJsonLd, faqJsonLd } from "@/lib/marketing/seo"
import {
  localePath,
  localeUrl,
  type MarketingLocale,
} from "@/lib/marketing/site"
import { cn } from "@/lib/utils"

export function PricingPage({
  locale,
  dict,
}: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  const page = dict.pricing

  return (
    <>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: "Temari.et", url: localeUrl(locale, "") },
          { name: dict.nav.pricing, url: localeUrl(locale, "/pricing") },
        ])}
      />
      <JsonLd data={faqJsonLd(page.faq)} />

      <Section className="relative overflow-hidden pb-8 md:pb-10">
        <HeroBackdrop watermark="ብ" />
        <div className="relative mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {page.hero.headline}
          </h1>
          <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-muted-foreground">
            {page.hero.sub}
          </p>
        </div>
      </Section>

      {/* The free-semester offer: try the whole platform before committing */}
      <Section className="pt-0 md:pt-0">
        <Reveal>
          <div className="brand-hero relative overflow-hidden rounded-[2rem] px-6 py-10 text-white md:px-12 md:py-12">
            <div
              aria-hidden
              className="mk-grid-light pointer-events-none absolute inset-0"
            />
            <div className="relative flex flex-col items-start gap-6 md:flex-row md:items-center md:justify-between">
              <div className="max-w-2xl">
                <p className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3.5 py-1 text-xs font-semibold tracking-wide text-emerald-300">
                  <Gift className="size-3.5" />
                  {page.freeSemester.badge}
                </p>
                <h2 className="mt-4 font-display text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                  {page.freeSemester.title}
                </h2>
                <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/75 md:text-base">
                  {page.freeSemester.body}
                </p>
              </div>
              <Button
                asChild
                size="lg"
                className="h-12 shrink-0 bg-white px-7 text-base font-semibold text-emerald-900 shadow-lg hover:bg-white/90"
              >
                <Link href="/signup">
                  {page.freeSemester.cta}
                  <ArrowRight data-icon="inline-end" />
                </Link>
              </Button>
            </div>
          </div>
        </Reveal>
      </Section>

      {/* Plans */}
      <Section className="pt-0 md:pt-0">
        <div className="grid items-stretch gap-4 lg:grid-cols-3">
          {page.plans.map((plan, i) => (
            <Reveal key={plan.name} delay={i * 60}>
              <div
                className={cn(
                  "relative flex h-full flex-col overflow-hidden rounded-3xl border p-7 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:shadow-lg md:p-8",
                  plan.highlighted
                    ? "border-primary/40 bg-card shadow-lg ring-2 ring-primary/20"
                    : "bg-card"
                )}
              >
                {plan.highlighted && (
                  <span
                    aria-hidden
                    className="absolute inset-x-0 top-0 h-1.5 bg-[linear-gradient(90deg,var(--primary),var(--success))]"
                  />
                )}
                <p className="font-semibold">{plan.name}</p>
                <p
                  className={cn(
                    "mt-4 font-display text-5xl font-bold tracking-tight",
                    plan.highlighted && "mk-gradient-text"
                  )}
                >
                  {plan.price}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {plan.unit}
                </p>
                {plan.perDay && (
                  <p className="mt-2.5 inline-flex w-fit items-center rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    {plan.perDay}
                  </p>
                )}
                <p className="mt-2 text-xs font-semibold text-primary">
                  {plan.payer}
                </p>
                <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
                  {plan.description}
                </p>
                <ul className="mt-6 space-y-3">
                  {plan.features.map((feature) => (
                    <li
                      key={feature}
                      className="flex items-start gap-2.5 text-sm leading-snug"
                    >
                      <Check className="mt-0.5 size-4 shrink-0 text-primary" />
                      {feature}
                    </li>
                  ))}
                </ul>
                <Button
                  asChild
                  variant={plan.highlighted ? "default" : "outline"}
                  size="lg"
                  className="mt-8 w-full"
                >
                  <Link
                    href={
                      plan.href.startsWith("/signup")
                        ? plan.href
                        : localePath(locale, plan.href)
                    }
                  >
                    {plan.cta}
                  </Link>
                </Button>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      {/* Pricing FAQ */}
      <Section>
        <Reveal>
          <SectionHeader headline={page.faqTitle} />
        </Reveal>
        <div className="mx-auto max-w-3xl">
          <FaqList items={page.faq} />
        </div>
      </Section>
    </>
  )
}
