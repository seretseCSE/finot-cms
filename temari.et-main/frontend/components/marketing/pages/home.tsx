import Link from "next/link"
import {
  ArrowRight,
  ArrowUpRight,
  Check,
  ClipboardCheck,
  History,
  MessageSquare,
  QrCode,
} from "lucide-react"

import { AppPreview } from "@/components/marketing/app-preview"
import { HeroBackdrop } from "@/components/marketing/backdrop"
import { BankStrip } from "@/components/marketing/bank-strip"
import { RedirectIfAuthed } from "@/components/marketing/redirect-if-authed"
import {
  MiniCard,
  MiniChat,
  MiniCourse,
  MiniGrades,
  MiniPayslip,
  MiniReceipt,
  MiniStock,
  MiniStudent,
  MiniTimetable,
  MiniWeek,
} from "@/components/marketing/bento-minis"
import { CtaBand } from "@/components/marketing/cta-band"
import { MobileCtaBar } from "@/components/marketing/mobile-cta"
import { ProductTour } from "@/components/marketing/product-tour"
import { FEATURE_ICONS } from "@/components/marketing/feature-icon"
import { Reveal } from "@/components/marketing/reveal"
import { SchoolMarquee } from "@/components/marketing/school-marquee"
import { Section, SectionHeader } from "@/components/marketing/section"
import { SmsPreview } from "@/components/marketing/sms-preview"
import { Testimonials } from "@/components/marketing/testimonials"
import { Button } from "@/components/ui/button"
import type { MarketingDict } from "@/lib/marketing/content"
import {
  JsonLd,
  organizationJsonLd,
  softwareAppJsonLd,
} from "@/lib/marketing/seo"
import {
  FEATURE_SLUGS,
  localePath,
  type FeatureSlug,
  type MarketingLocale,
} from "@/lib/marketing/site"
import { cn } from "@/lib/utils"

const BENTO_MINIS: Partial<Record<FeatureSlug, React.ReactNode>> = {
  "student-management": <MiniStudent />,
  attendance: <MiniWeek />,
  "id-cards": <MiniCard />,
  fees: <MiniReceipt />,
  grading: <MiniGrades />,
  courses: <MiniCourse />,
  timetable: <MiniTimetable />,
  communication: <MiniChat />,
  "hr-payroll": <MiniPayslip />,
  inventory: <MiniStock />,
}

export function HomePage({
  locale,
  dict,
}: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  const home = dict.home
  const trustIcons = [QrCode, History, ClipboardCheck]

  return (
    <>
      <RedirectIfAuthed />
      <JsonLd data={organizationJsonLd()} />
      <JsonLd data={softwareAppJsonLd()} />

      {/* ── Hero: staggered copy + floating product surfaces ─────────────── */}
      <section className="relative overflow-hidden px-4 pt-16 pb-14 md:px-8 md:pt-24 md:pb-20">
        <HeroBackdrop watermark="ተ" />
        <div className="relative mx-auto grid w-full max-w-6xl items-center gap-14 lg:grid-cols-[1.05fr_0.95fr]">
          <div>
            <p className="mk-fade-up inline-flex items-center rounded-full border border-primary/25 bg-primary/5 px-4 py-1.5 font-ethiopic text-sm font-semibold tracking-wide text-primary">
              {home.hero.badge}
            </p>
            <h1
              className="mk-fade-up mt-6 text-[2.6rem]/[1.06] font-semibold tracking-tight text-balance md:text-[3.4rem]/[1.05] lg:text-[3.8rem]/[1.05]"
              style={{ animationDelay: "80ms" }}
            >
              {home.hero.headline}
              <span className="mk-gradient-text mt-1 block pb-1.5">
                {home.hero.headline2}
              </span>
            </h1>
            <p
              className="mk-fade-up mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground"
              style={{ animationDelay: "160ms" }}
            >
              {home.hero.sub}
            </p>
            <div
              className="mk-fade-up mt-9 flex flex-col gap-3 sm:flex-row"
              style={{ animationDelay: "240ms" }}
            >
              <Button
                asChild
                size="lg"
                className="h-12 px-8 text-base shadow-md"
              >
                <Link href="/signup">
                  {home.hero.primary}
                  <ArrowRight data-icon="inline-end" />
                </Link>
              </Button>
              <Button
                asChild
                size="lg"
                variant="outline"
                className="h-12 px-8 text-base"
              >
                <Link href={localePath(locale, "/contact")}>
                  {home.hero.secondary}
                </Link>
              </Button>
            </div>
            <p
              className="mk-fade-up mt-5 text-sm font-medium text-muted-foreground"
              style={{ animationDelay: "320ms" }}
            >
              {home.hero.note}
            </p>
          </div>

          <div
            className="mk-fade-up relative mx-auto lg:mr-2"
            style={{ animationDelay: "200ms" }}
          >
            <div
              aria-hidden
              className="absolute -inset-10 rounded-full bg-primary/15 blur-3xl"
            />
            <AppPreview className="relative rotate-1 transition-transform duration-300 hover:rotate-0" />

            {/* Floating product moments (sample data) */}
            <div className="mk-float absolute top-32 -left-16 hidden items-center gap-3 rounded-2xl border bg-card/95 p-3 pr-4 shadow-lg backdrop-blur md:flex lg:-left-40">
              <span className="flex size-9 items-center justify-center rounded-xl bg-info/10 text-info">
                <MessageSquare className="size-4" />
              </span>
              <span>
                <span className="block text-xs font-semibold">
                  Absence SMS sent
                </span>
                <span className="block font-mono text-[10px] text-muted-foreground tabular-nums">
                  Guardian · 8:42
                </span>
              </span>
            </div>
            <div
              className="mk-float absolute -bottom-5 -left-4 hidden items-center gap-3 rounded-2xl border bg-card/95 p-3 pr-4 shadow-lg backdrop-blur md:flex lg:-left-12"
              style={{ animationDelay: "2.2s" }}
            >
              <span className="flex size-9 items-center justify-center rounded-xl bg-success/10 text-success">
                <QrCode className="size-4" />
              </span>
              <span>
                <span className="block text-xs font-semibold">
                  Receipt verified
                </span>
                <span className="block font-mono text-[10px] text-muted-foreground tabular-nums">
                  temari.et/verify/R7K2
                </span>
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* ── Audience router: one door per person around the school ───────── */}
      <Section className="pt-2 pb-4 md:pt-4 md:pb-6">
        <Reveal>
          <SectionHeader
            headline={home.audiences.headline}
            sub={home.audiences.sub}
            center
          />
        </Reveal>
        <div className="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-3">
          {home.audiences.items.map((item, i) => (
            <Reveal key={item.href} delay={(i % 3) * 60}>
              <Link
                href={
                  item.href.startsWith("/tutors")
                    ? item.href
                    : localePath(locale, item.href)
                }
                className="group flex h-full flex-col rounded-2xl border bg-card p-4 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:border-primary/30 hover:shadow-lg md:p-6"
              >
                <span className="flex items-center justify-between gap-2">
                  <span className="font-semibold text-balance md:text-lg">
                    {item.title}
                  </span>
                  <ArrowUpRight className="size-4 shrink-0 text-primary opacity-0 transition-all duration-200 group-hover:translate-x-0.5 group-hover:opacity-100" />
                </span>
                <span className="mt-1.5 hidden text-sm leading-relaxed text-muted-foreground sm:block">
                  {item.body}
                </span>
              </Link>
            </Reveal>
          ))}
        </div>
      </Section>

      {/* ── Placeholder school roster (marquee) ──────────────────────────── */}
      <Section className="py-10 md:py-12">
        <Reveal>
          <SchoolMarquee title={home.schools.title} />
        </Reveal>
      </Section>

      {/* ── Fact strip ───────────────────────────────────────────────────── */}
      <Section className="py-10 md:py-14">
        <Reveal>
          <dl className="grid grid-cols-2 gap-y-10 rounded-3xl border bg-card px-6 py-10 shadow-xs md:grid-cols-4 md:divide-x md:divide-border md:py-12">
            {home.stats.map((stat) => (
              <div key={stat.label} className="px-4 text-center">
                <dd className="mk-gradient-text font-display text-5xl font-bold tracking-tight md:text-6xl">
                  {stat.value}
                </dd>
                <dt className="mt-2 text-sm font-medium text-muted-foreground">
                  {stat.label}
                </dt>
              </div>
            ))}
          </dl>
        </Reveal>
      </Section>

      {/* ── Feature bento with product vignettes ─────────────────────────── */}
      <Section>
        <Reveal>
          <SectionHeader
            headline={home.features.headline}
            sub={home.features.sub}
            center
          />
        </Reveal>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {FEATURE_SLUGS.map((slug, i) => {
            const feature = dict.features[slug]
            const Icon = FEATURE_ICONS[slug]
            // 4-col bento rhythm with no trailing gap for 11 cards
            // (12 column units): [2,1,1] [1,1,1,1] [1,1,1,1].
            const wide = i === 0
            const tinted = i === 0 || i === 8
            return (
              <Reveal
                key={slug}
                delay={(i % 4) * 60}
                className={cn(wide && "sm:col-span-2")}
              >
                <Link
                  href={localePath(locale, `/features/${slug}`)}
                  className={cn(
                    "group relative flex h-full flex-col rounded-3xl border p-6 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:shadow-lg md:p-7",
                    tinted ? "border-primary/20 bg-primary/5" : "bg-card"
                  )}
                >
                  <ArrowUpRight className="absolute top-6 right-6 size-4 text-primary opacity-0 transition-all duration-200 group-hover:translate-x-0.5 group-hover:opacity-100" />
                  <span
                    className={cn(
                      "flex size-10 items-center justify-center rounded-xl",
                      tinted
                        ? "brand-tile text-white"
                        : "bg-accent text-accent-foreground"
                    )}
                  >
                    <Icon className="size-5" strokeWidth={1.75} />
                  </span>
                  <p className="mt-4 font-semibold">{feature.name}</p>
                  <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                    {feature.tagline}
                  </p>
                  {BENTO_MINIS[slug] && (
                    <div className="mt-auto">{BENTO_MINIS[slug]}</div>
                  )}
                </Link>
              </Reveal>
            )
          })}
        </div>
      </Section>

      {/* ── Real product screenshots ─────────────────────────────────────── */}
      {/* <ProductTour dict={dict} /> */}

      {/* ── Parents: SMS preview + points ────────────────────────────────── */}
      <Section className="overflow-hidden bg-muted/40">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <Reveal className="order-2 flex justify-center lg:order-1">
            <div className="relative">
              <div
                aria-hidden
                className="absolute -inset-8 rounded-full bg-info/10 blur-3xl"
              />
              <SmsPreview className="relative -rotate-1 transition-transform duration-300 hover:rotate-0" />
            </div>
          </Reveal>
          <Reveal className="order-1 lg:order-2">
            <SectionHeader
              headline={home.parents.headline}
              sub={home.parents.sub}
              className="mb-8"
            />
            <div className="space-y-6">
              {home.parents.points.map((point) => (
                <div
                  key={point.title}
                  className="border-l-2 border-primary/30 pl-5"
                >
                  <p className="font-semibold">{point.title}</p>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                    {point.body}
                  </p>
                </div>
              ))}
            </div>
          </Reveal>
        </div>
      </Section>

      {/* ── Built for Ethiopia: dark brand panel, glass tiles ────────────── */}
      <Section>
        <Reveal>
          <div className="brand-hero relative overflow-hidden rounded-[2rem] px-6 py-14 text-white md:px-14 md:py-16">
            <div
              aria-hidden
              className="mk-grid-light pointer-events-none absolute inset-0"
            />
            <span
              aria-hidden
              className="pointer-events-none absolute -right-10 -bottom-24 hidden font-ethiopic text-[18rem] leading-none font-bold text-white/4 select-none lg:block"
            >
              ኢ
            </span>
            <div className="relative">
              <div className="max-w-2xl">
                <h2 className="font-display text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                  {home.ethiopia.headline}
                </h2>
                <p className="mt-3 text-white/70">{home.ethiopia.sub}</p>
              </div>
              <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {home.ethiopia.items.map((item) => (
                  <div
                    key={item.title}
                    className="rounded-2xl border border-white/10 bg-white/6 p-6 backdrop-blur transition-colors duration-200 hover:bg-white/10"
                  >
                    <p className="font-display text-lg font-semibold text-emerald-300">
                      {item.title}
                    </p>
                    <p className="mt-2 text-sm leading-relaxed text-white/70">
                      {item.body}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </Reveal>
      </Section>

      {/* ── Testimonials (placeholder copy) ──────────────────────────────── */}
      <Testimonials dict={dict} />

      {/* ── Exam prep ────────────────────────────────────────────────────── */}
      <Section className="pt-0 md:pt-0">
        <Reveal>
          <div className="overflow-hidden rounded-[2rem] border bg-card shadow-xs lg:grid lg:grid-cols-[1.1fr_0.9fr]">
            <div className="p-8 md:p-12">
              <h2 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                {home.examPrep.headline}
              </h2>
              <p className="mt-4 max-w-xl leading-relaxed text-muted-foreground">
                {home.examPrep.sub}
              </p>
              <ul className="mt-6 space-y-3.5">
                {home.examPrep.points.map((point) => (
                  <li key={point} className="flex items-start gap-3 text-sm">
                    <span className="mt-[-1px] flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                      <Check className="size-3" />
                    </span>
                    {point}
                  </li>
                ))}
              </ul>
              <Button asChild size="lg" className="mt-8 px-7">
                <Link href={localePath(locale, "/exam-prep")}>
                  {home.examPrep.cta}
                  <ArrowRight data-icon="inline-end" />
                </Link>
              </Button>
            </div>
            <div className="brand-hero relative hidden items-center justify-center overflow-hidden p-12 lg:flex">
              <div
                aria-hidden
                className="mk-grid-light pointer-events-none absolute inset-0"
              />
              <div className="relative grid gap-4">
                {["6", "8", "12"].map((grade, i) => (
                  <div
                    key={grade}
                    className={cn(
                      "flex items-center gap-4 rounded-2xl border border-white/10 bg-white/8 px-6 py-4 text-white backdrop-blur",
                      i === 1 && "translate-x-10"
                    )}
                  >
                    <span className="font-display text-3xl font-bold">
                      {grade}
                    </span>
                    <span className="text-sm text-white/75">
                      {dict.examPrep.grades[i]?.title}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </Reveal>
      </Section>

      {/* ── Trust ────────────────────────────────────────────────────────── */}
      <Section>
        <Reveal>
          <SectionHeader
            headline={home.trust.headline}
            sub={home.trust.sub}
            center
          />
        </Reveal>
        <div className="grid gap-4 md:grid-cols-3">
          {home.trust.items.map((item, i) => {
            const Icon = trustIcons[i] ?? QrCode
            return (
              <Reveal key={item.title} delay={i * 60}>
                <div className="group h-full rounded-3xl border bg-card p-7 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
                  <span className="group-hover:brand-tile flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary transition-colors duration-200 group-hover:text-white">
                    <Icon className="size-5" strokeWidth={1.75} />
                  </span>
                  <p className="mt-4 font-semibold">{item.title}</p>
                  <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                    {item.body}
                  </p>
                </div>
              </Reveal>
            )
          })}
        </div>
      </Section>

      {/* ── Pricing teaser + payment channels ────────────────────────────── */}
      <Section className="pt-0 md:pt-0">
        <Reveal>
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
              {home.pricing.headline}
            </h2>
            <p className="mt-3 text-muted-foreground">{home.pricing.sub}</p>
            <p className="mk-gradient-text mt-7 font-display text-6xl font-bold tracking-tight md:text-7xl">
              {home.pricing.price}
            </p>
            <p className="mt-2 text-sm font-medium text-muted-foreground">
              {home.pricing.unit}
            </p>
            <p className="mx-auto mt-4 max-w-md text-sm leading-relaxed text-muted-foreground">
              {home.pricing.note}
            </p>
            <Button asChild variant="outline" size="lg" className="mt-8 px-7">
              <Link href={localePath(locale, "/pricing")}>
                {home.pricing.cta}
              </Link>
            </Button>
          </div>
        </Reveal>
        <Reveal>
          <BankStrip title={home.banks.title} className="mt-16 md:mt-20" />
        </Reveal>
      </Section>

      <CtaBand locale={locale} dict={dict} />

      {/* App-like sticky CTA on mobile — appears after the hero scrolls away */}
      <MobileCtaBar getStarted={home.hero.primary} signIn={dict.nav.signIn} />
    </>
  )
}
