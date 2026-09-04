import Link from "next/link"
import { ArrowRight, Languages, Route, Signal, Sparkles } from "lucide-react"

import { Reveal } from "@/components/marketing/reveal"
import { Section, SectionHeader } from "@/components/marketing/section"
import { Button } from "@/components/ui/button"
import type { MarketingDict } from "@/lib/marketing/content"
import { JsonLd, breadcrumbJsonLd } from "@/lib/marketing/seo"
import { localeUrl, type MarketingLocale } from "@/lib/marketing/site"

export function ExamPrepPage({
  locale,
  dict,
}: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  const page = dict.examPrep
  const aiIcons = [Languages, Route, Signal]

  return (
    <>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: "Temari.et", url: localeUrl(locale, "") },
          { name: dict.nav.examPrep, url: localeUrl(locale, "/exam-prep") },
        ])}
      />

      {/* Hero on the brand surface — exam prep is its own consumer product */}
      <section className="px-4 pt-6 md:px-8">
        <div className="brand-hero relative mx-auto w-full max-w-6xl overflow-hidden rounded-[2rem] px-6 py-16 text-center text-white md:px-12 md:py-24">
          <div
            aria-hidden
            className="mk-grid-light pointer-events-none absolute inset-0"
          />
          <span
            aria-hidden
            className="pointer-events-none absolute -right-8 -bottom-20 hidden font-ethiopic text-[15rem] leading-none font-bold text-white/4 select-none lg:block"
          >
            ፲፪
          </span>
          <p className="relative text-sm font-semibold tracking-wide text-emerald-300">
            {page.hero.badge}
          </p>
          <h1 className="mx-auto mt-4 max-w-3xl font-display text-4xl font-semibold tracking-tight text-balance md:text-5xl">
            {page.hero.headline}
          </h1>
          <p className="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-white/75">
            {page.hero.sub}
          </p>
          <Button
            asChild
            size="lg"
            className="mt-8 bg-white px-7 text-brand-ink hover:bg-white/90"
          >
            <Link href="/signup">
              {page.hero.primary}
              <ArrowRight data-icon="inline-end" />
            </Link>
          </Button>
        </div>
      </section>

      {/* Grade tracks */}
      <Section>
        <div className="grid gap-4 md:grid-cols-3">
          {page.grades.map((grade, i) => (
            <Reveal key={grade.grade} delay={i * 60}>
              <div className="h-full rounded-2xl border bg-card p-7 shadow-xs">
                <p className="font-display text-4xl font-bold tracking-tight text-primary">
                  {grade.grade}
                </p>
                <p className="mt-3 font-semibold">{grade.title}</p>
                <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                  {grade.body}
                </p>
              </div>
            </Reveal>
          ))}
        </div>
      </Section>

      {/* How it works */}
      <Section className="bg-muted/40">
        <Reveal>
          <SectionHeader headline={page.how.headline} center />
        </Reveal>
        <ol className="grid gap-8 md:grid-cols-3">
          {page.how.steps.map((step, i) => (
            <Reveal key={step.title} delay={i * 80}>
              <li className="text-center">
                <span className="mx-auto flex size-10 items-center justify-center rounded-full bg-primary font-display text-lg font-semibold text-primary-foreground">
                  {i + 1}
                </span>
                <p className="mt-4 font-semibold">{step.title}</p>
                <p className="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-muted-foreground">
                  {step.body}
                </p>
              </li>
            </Reveal>
          ))}
        </ol>
      </Section>

      {/* AI tutor */}
      <Section>
        <Reveal>
          <div className="mb-10 flex items-start gap-4 md:mb-14">
            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <Sparkles className="size-5" strokeWidth={1.75} />
            </span>
            <div>
              <h2 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                {page.ai.headline}
              </h2>
              <p className="mt-3 max-w-2xl leading-relaxed text-muted-foreground">
                {page.ai.sub}
              </p>
            </div>
          </div>
        </Reveal>
        <div className="grid gap-4 md:grid-cols-3">
          {page.ai.points.map((point, i) => {
            const Icon = aiIcons[i] ?? Sparkles
            return (
              <Reveal key={point.title} delay={i * 60}>
                <div className="h-full rounded-2xl border bg-card p-6 shadow-xs">
                  <Icon className="size-5 text-primary" strokeWidth={1.75} />
                  <p className="mt-3 font-semibold">{point.title}</p>
                  <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                    {point.body}
                  </p>
                </div>
              </Reveal>
            )
          })}
        </div>
      </Section>

      {/* Pricing note + CTA */}
      <Section className="pt-0 md:pt-0">
        <Reveal>
          <div className="mx-auto max-w-3xl rounded-3xl border border-primary/15 bg-primary/5 p-8 text-center md:p-10">
            <h2 className="text-2xl font-semibold tracking-tight text-balance">
              {page.pricingNote.title}
            </h2>
            <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground">
              {page.pricingNote.body}
            </p>
            <Button asChild size="lg" className="mt-6 px-7">
              <Link href="/signup">
                {dict.common.startPracticing}
                <ArrowRight data-icon="inline-end" />
              </Link>
            </Button>
          </div>
        </Reveal>
      </Section>
    </>
  )
}
