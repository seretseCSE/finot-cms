import { HeroBackdrop } from "@/components/marketing/backdrop"
import { FaqList } from "@/components/marketing/faq-list"
import { CtaBand } from "@/components/marketing/cta-band"
import { Reveal } from "@/components/marketing/reveal"
import { Section } from "@/components/marketing/section"
import type { MarketingDict } from "@/lib/marketing/content"
import { JsonLd, faqJsonLd } from "@/lib/marketing/seo"
import type { MarketingLocale } from "@/lib/marketing/site"

export function FaqPage({
  locale,
  dict,
}: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  const page = dict.faq
  const allItems = page.groups.flatMap((group) => group.items)

  return (
    <>
      <JsonLd data={faqJsonLd(allItems)} />

      <Section className="relative overflow-hidden pb-8 md:pb-10">
        <HeroBackdrop />
        <div className="relative mx-auto max-w-3xl">
          <h1 className="text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {page.hero.headline}
          </h1>
          <p className="mt-4 text-lg text-muted-foreground">{page.hero.sub}</p>
        </div>
      </Section>

      <Section className="pt-0 md:pt-0">
        <div className="mx-auto max-w-3xl space-y-10">
          {page.groups.map((group) => (
            <Reveal key={group.title}>
              <div>
                <h2 className="mb-4 text-xl font-semibold tracking-tight">
                  {group.title}
                </h2>
                <FaqList items={group.items} />
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      <CtaBand locale={locale} dict={dict} />
    </>
  )
}
