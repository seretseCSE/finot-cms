import Link from "next/link"
import { ArrowRight } from "lucide-react"

import { Button } from "@/components/ui/button"
import type { MarketingDict } from "@/lib/marketing/content"
import { localePath, type MarketingLocale } from "@/lib/marketing/site"

/** Closing call-to-action on the brand hero surface. */
export function CtaBand({
  locale,
  dict,
}: {
  locale: MarketingLocale
  dict: MarketingDict
}) {
  return (
    <section className="px-4 pb-20 md:px-8 md:pb-28">
      <div className="brand-hero relative mx-auto w-full max-w-6xl overflow-hidden rounded-[2rem] px-6 py-16 text-center text-white md:px-12 md:py-24">
        <div
          aria-hidden
          className="mk-grid-light pointer-events-none absolute inset-0"
        />
        <span
          aria-hidden
          className="pointer-events-none absolute -top-20 -left-8 hidden font-ethiopic text-[16rem] leading-none font-bold text-white/4 select-none lg:block"
        >
          ተ
        </span>
        <div className="relative">
          <h2 className="mx-auto max-w-2xl font-display text-3xl font-semibold tracking-tight text-balance md:text-5xl">
            {dict.ctaBand.headline}
          </h2>
          <p className="mx-auto mt-5 max-w-xl text-base leading-relaxed text-white/75 md:text-lg">
            {dict.ctaBand.sub}
          </p>
          <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button
              asChild
              size="lg"
              className="h-12 w-full bg-white px-8 text-base text-brand-ink shadow-lg hover:bg-white/90 sm:w-auto"
            >
              <Link href="/signup">
                {dict.ctaBand.primary}
                <ArrowRight data-icon="inline-end" />
              </Link>
            </Button>
            <Button
              asChild
              size="lg"
              variant="outline"
              className="h-12 w-full border-white/25 bg-transparent px-8 text-base text-white hover:bg-white/10 hover:text-white sm:w-auto dark:border-white/25 dark:bg-transparent dark:hover:bg-white/10"
            >
              <Link href={localePath(locale, "/contact")}>
                {dict.ctaBand.secondary}
              </Link>
            </Button>
          </div>
        </div>
      </div>
    </section>
  )
}
