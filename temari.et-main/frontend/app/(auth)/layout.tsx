"use client"

import Link from "next/link"
import { CalendarCheck, MessageSquareText, ScrollText, Wallet } from "lucide-react"

import { LanguageSwitcher } from "@/components/app-shell/language-switcher"
import { LogoMark } from "@/components/ui/logo"
import { useTranslation } from "@/lib/i18n"

/**
 * Auth shell (DESIGN.md §8): one immersive brand-hero scene at every viewport,
 * matching the marketing site's visual language (blueprint grid, glass tiles,
 * staggered entrance). The identity layer is a giant ተ watermark; the form
 * floats in an elevated bg-card panel so both themes read correctly inside it.
 * White/emerald literals are the sanctioned brand-hero exception (DESIGN.md §3).
 */
export default function AuthLayout({ children }: { children: React.ReactNode }) {
  const { t } = useTranslation("auth")

  const features = [
    { icon: CalendarCheck, label: t("brand.f1") },
    { icon: Wallet, label: t("brand.f2") },
    { icon: MessageSquareText, label: t("brand.f3") },
    { icon: ScrollText, label: t("brand.f4") },
  ]

  return (
    <div className="brand-hero relative flex min-h-dvh flex-col overflow-hidden">
      {/* Blueprint grid — same texture as the marketing site's dark panels */}
      <div aria-hidden className="mk-grid-light pointer-events-none absolute inset-0" />

      {/* Giant ተ watermark bleeding off the corner */}
      <span
        aria-hidden
        className="font-ethiopic pointer-events-none absolute -right-14 -bottom-28 text-[24rem] leading-none font-bold text-white/[0.05] select-none md:-right-8 md:-bottom-48 md:text-[40rem]"
      >
        ተ
      </span>

      {/* Top bar — the logo leads back to the public website */}
      <header className="relative z-10 flex items-center justify-between px-5 pt-5 pb-2 md:px-10 md:pt-6">
        <Link
          href="/"
          className="flex items-center gap-2.5 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-white/40"
        >
          <LogoMark size="sm" className="ring-1 ring-white/20" />
          <span className="font-display text-lg font-bold tracking-tight text-white">
            Temari<span className="text-emerald-300">.et</span>
          </span>
        </Link>
        <LanguageSwitcher variant="ghost-light" />
      </header>

      {/* Scene */}
      <div className="relative z-10 mx-auto flex w-full max-w-6xl flex-1 items-center px-5 py-8 md:px-10">
        <div className="grid w-full items-center gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-16">
          {/* Brand statement — desktop only; the card carries mobile */}
          <div className="hidden lg:block">
            <p className="mk-fade-up font-ethiopic inline-flex items-center rounded-full border border-white/15 bg-white/[0.06] px-4 py-1.5 text-sm font-medium tracking-wide text-emerald-300/90">
              ተማሪ · ለተማሪ
            </p>
            <h1
              className="mk-fade-up font-display mt-6 text-5xl leading-[1.06] font-bold tracking-tight text-white xl:text-6xl"
              style={{ animationDelay: "80ms" }}
            >
              {t("brand.headline1")}
              <br />
              <span className="text-emerald-300">{t("brand.headline2")}</span>
            </h1>
            <p
              className="mk-fade-up mt-5 max-w-md text-[15px] leading-relaxed text-white/60"
              style={{ animationDelay: "160ms" }}
            >
              {t("brand.sub")}
            </p>

            <ul className="mk-fade-up mt-9 grid max-w-lg grid-cols-2 gap-3" style={{ animationDelay: "240ms" }}>
              {features.map(({ icon: Icon, label }) => (
                <li
                  key={label}
                  className="flex items-start gap-2.5 rounded-2xl border border-white/10 bg-white/[0.06] p-3.5 text-[13px] leading-snug text-white/80 backdrop-blur transition-colors duration-200 hover:bg-white/10"
                >
                  <Icon className="mt-px size-4 shrink-0 text-emerald-300" strokeWidth={1.75} />
                  {label}
                </li>
              ))}
            </ul>
          </div>

          {/* Floating form card */}
          <div className="mk-fade-up w-full lg:justify-self-end" style={{ animationDelay: "120ms" }}>
            <p className="font-ethiopic mb-4 text-center text-xs font-medium tracking-wide text-emerald-300/80 lg:hidden">
              ተማሪ · ለተማሪ
            </p>
            <div className="bg-card mx-auto w-full max-w-md rounded-3xl p-6 shadow-xl ring-1 ring-white/10 sm:p-8">
              {children}
            </div>
          </div>
        </div>
      </div>

      <footer className="relative z-10 pb-6 text-center text-xs text-white/35">
        {t("brand.company")}
      </footer>
    </div>
  )
}
