import Link from "next/link"

import { Logo } from "@/components/ui/logo"
import type { MarketingDict } from "@/lib/marketing/content"
import {
  LOCALE_LABELS,
  MARKETING_LOCALES,
  localePath,
  type MarketingLocale,
} from "@/lib/marketing/site"

export function MarketingFooter({
  locale,
  dict,
  path,
}: {
  locale: MarketingLocale
  dict: MarketingDict
  path: string
}) {
  const year = new Date().getFullYear()
  const columns = [
    { title: dict.footer.product, links: dict.footer.columns.product },
    { title: dict.footer.audiences, links: dict.footer.columns.audiences },
    { title: dict.footer.company, links: dict.footer.columns.company },
  ]

  return (
    <footer className="border-t">
      <div className="mx-auto w-full max-w-6xl px-4 py-12 md:px-8 md:py-16">
        <div className="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
          <div className="max-w-xs">
            <Logo size="sm" />
            <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
              {dict.footer.tagline}
            </p>
          </div>
          {columns.map((col) => (
            <div key={col.title}>
              <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {col.title}
              </p>
              <ul className="mt-4 space-y-2.5">
                {col.links.map((link) => {
                  // Pages outside the localized marketing tree keep their raw path.
                  const enOnly =
                    link.path === "/privacy" ||
                    link.path === "/terms" ||
                    link.path === "/tutors"
                  return (
                    <li key={link.path}>
                      <Link
                        href={
                          enOnly ? link.path : localePath(locale, link.path)
                        }
                        className="text-sm text-foreground/80 transition-colors hover:text-foreground"
                      >
                        {link.label}
                      </Link>
                    </li>
                  )
                })}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-12 flex flex-col gap-4 border-t pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
          <p className="text-muted-foreground">
            © {year} {dict.footer.copyright} · {dict.footer.madeIn}
          </p>
          <nav className="flex items-center gap-3" aria-label="Languages">
            {MARKETING_LOCALES.map((l) => (
              <Link
                key={l}
                href={localePath(l, path)}
                hrefLang={l}
                className={
                  l === locale
                    ? "font-medium text-foreground"
                    : "text-muted-foreground transition-colors hover:text-foreground"
                }
              >
                {LOCALE_LABELS[l]}
              </Link>
            ))}
          </nav>
        </div>
      </div>
    </footer>
  )
}
