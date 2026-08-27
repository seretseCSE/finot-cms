"use client"

import Link from "next/link"
import { useState } from "react"
import { Check, Globe, Menu, X } from "lucide-react"

import { Logo } from "@/components/ui/logo"
import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useAuth } from "@/lib/auth/auth-context"
import type { MarketingDict } from "@/lib/marketing/content"
import {
  LOCALE_LABELS,
  MARKETING_LOCALES,
  localePath,
  type MarketingLocale,
} from "@/lib/marketing/site"
import { cn } from "@/lib/utils"

/**
 * Marketing top nav: sticky, blurred, one line on desktop. The CTA is
 * auth-aware — a signed-in visitor gets "Open app" instead of sign-in.
 */
export function MarketingNav({
  locale,
  dict,
  path,
}: {
  locale: MarketingLocale
  dict: MarketingDict
  /** Locale-less path of the current page, used by the language switcher. */
  path: string
}) {
  const { user } = useAuth()
  const [open, setOpen] = useState(false)

  const links = [
    { label: dict.nav.features, href: localePath(locale, "/features") },
    { label: dict.nav.examPrep, href: localePath(locale, "/exam-prep") },
    // The tutor directory lives outside the localized marketing tree.
    { label: dict.nav.tutors, href: "/tutors" },
    { label: dict.nav.pricing, href: localePath(locale, "/pricing") },
    { label: dict.nav.about, href: localePath(locale, "/about") },
  ]

  return (
    <header className="sticky top-0 z-40 border-b bg-background/90 backdrop-blur-xl">
      <nav className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 md:px-8">
        <Link
          href={localePath(locale, "")}
          className="rounded-lg outline-none focus-visible:ring-2"
          aria-label="Temari.et"
        >
          <Logo size="sm" />
        </Link>

        <div className="hidden items-center gap-1 md:flex">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="rounded-full px-3.5 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            >
              {link.label}
            </Link>
          ))}
        </div>

        <div className="flex items-center gap-2">
          <LanguageMenu locale={locale} path={path} label={dict.nav.language} />
          {user ? (
            <Button asChild size="sm" className="h-9 px-4">
              <Link href="/dashboard">{dict.nav.openApp}</Link>
            </Button>
          ) : (
            <>
              <Button
                asChild
                variant="ghost"
                size="sm"
                className="hidden h-9 px-4 md:inline-flex"
              >
                <Link href="/login">{dict.nav.signIn}</Link>
              </Button>
              <Button asChild size="sm" className="h-9 px-4">
                <Link href="/signup">{dict.nav.getStarted}</Link>
              </Button>
            </>
          )}
          <Button
            variant="ghost"
            size="icon-sm"
            className="md:hidden"
            aria-label={dict.nav.menu}
            aria-expanded={open}
            onClick={() => setOpen((v) => !v)}
          >
            {open ? <X /> : <Menu />}
          </Button>
        </div>
      </nav>

      {/* Mobile menu panel */}
      <div
        className={cn(
          "overflow-hidden border-b transition-[max-height,opacity] duration-300 [transition-timing-function:var(--ease-out-quart)] md:hidden",
          open ? "max-h-96 opacity-100" : "max-h-0 border-b-0 opacity-0"
        )}
      >
        <div className="space-y-1 px-4 py-3">
          {links.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              onClick={() => setOpen(false)}
              className="block rounded-xl px-3 py-3 text-base font-medium text-foreground hover:bg-muted"
            >
              {link.label}
            </Link>
          ))}
          {!user && (
            <Link
              href="/login"
              onClick={() => setOpen(false)}
              className="block rounded-xl px-3 py-3 text-base font-medium text-foreground hover:bg-muted"
            >
              {dict.nav.signIn}
            </Link>
          )}
        </div>
      </div>
    </header>
  )
}

function LanguageMenu({
  locale,
  path,
  label,
}: {
  locale: MarketingLocale
  path: string
  label: string
}) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon-sm" aria-label={label} title={label}>
          <Globe />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {MARKETING_LOCALES.map((l) => (
          <DropdownMenuItem key={l} asChild>
            <Link
              href={localePath(l, path)}
              hrefLang={l}
              className="flex items-center gap-2"
            >
              <span className="flex-1">{LOCALE_LABELS[l]}</span>
              {l === locale && <Check className="size-4 text-primary" />}
            </Link>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
