import Link from "next/link"
import { ArrowRight, Mail, MapPin, Phone } from "lucide-react"

import { HeroBackdrop } from "@/components/marketing/backdrop"
import { Reveal } from "@/components/marketing/reveal"
import { Section } from "@/components/marketing/section"
import { Button } from "@/components/ui/button"
import type { MarketingDict } from "@/lib/marketing/content"
import { JsonLd, organizationJsonLd } from "@/lib/marketing/seo"
import type { MarketingLocale } from "@/lib/marketing/site"

export function ContactPage(props: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  const { dict } = props
  const page = dict.contact
  const icons = [Phone, Phone, Mail, MapPin]

  return (
    <>
      <JsonLd data={organizationJsonLd()} />

      <Section className="relative overflow-hidden pb-10 md:pb-14">
        <HeroBackdrop />
        <div className="relative mx-auto max-w-3xl">
          <h1 className="text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {page.hero.headline}
          </h1>
          <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted-foreground">
            {page.hero.sub}
          </p>
        </div>
      </Section>

      <Section className="pt-0 md:pt-0">
        <div className="mx-auto grid max-w-3xl gap-4 sm:grid-cols-2">
          {page.channels.map((channel, i) => {
            const Icon = icons[i] ?? Mail
            const inner = (
              <>
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  <Icon className="size-5" strokeWidth={1.75} />
                </span>
                <span className="min-w-0">
                  <span className="block font-semibold">{channel.title}</span>
                  <span className="mt-0.5 block text-xs text-muted-foreground">
                    {channel.body}
                  </span>
                  <span className="mt-2 block text-sm font-medium break-all text-primary">
                    {channel.value}
                  </span>
                </span>
              </>
            )
            return channel.href ? (
              <a
                key={channel.value}
                href={channel.href}
                className="flex items-start gap-4 rounded-2xl border bg-card p-6 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
              >
                {inner}
              </a>
            ) : (
              <div
                key={channel.value}
                className="flex items-start gap-4 rounded-2xl border bg-card p-6 shadow-xs"
              >
                {inner}
              </div>
            )
          })}
        </div>
      </Section>

      <Section className="pt-0 md:pt-0">
        <Reveal>
          <div className="mx-auto max-w-3xl rounded-3xl border border-primary/15 bg-primary/5 p-8 md:p-10">
            <h2 className="text-2xl font-semibold tracking-tight text-balance">
              {page.schools.title}
            </h2>
            <p className="mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground">
              {page.schools.body}
            </p>
            <Button asChild size="lg" className="mt-6 px-7">
              <Link href="/signup">
                {page.schools.cta}
                <ArrowRight data-icon="inline-end" />
              </Link>
            </Button>
          </div>
        </Reveal>
      </Section>
    </>
  )
}
