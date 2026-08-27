"use client"

import { ChevronRight } from "lucide-react"
import Link from "next/link"

import { CATALOG_ITEMS, useCatalogs } from "@/components/catalogs/catalogs-shell"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { useTranslation } from "@/lib/i18n"

/**
 * The catalog studio hub: one tile per seed catalog with live counts and
 * attention chips. On mobile this list IS the navigation (app-style rows);
 * on desktop it doubles as an overview beside the nested sidebar.
 */
export default function CatalogsHubPage() {
  const { t } = useTranslation("catalogs")
  const { overview } = useCatalogs()

  return (
    <div className="space-y-6">
      <PageHeader title={t("title")} description={t("subtitle")} />

      <div className="page-gutter grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {CATALOG_ITEMS.map((item) => {
          const attention = overview && item.attention ? item.attention(overview) : 0
          return (
            <Link
              key={item.key}
              href={item.href}
              className="group pressable flex items-center gap-4 rounded-2xl border bg-card p-4 shadow-xs transition-colors hover:border-primary/30"
            >
              <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground transition-colors group-hover:bg-primary/10 group-hover:text-primary">
                <item.icon className="size-5" strokeWidth={1.75} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="flex items-center gap-2">
                  <span className="truncate font-display text-sm font-semibold">
                    {t(`items.${item.key}.title`)}
                  </span>
                  {overview ? (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground">
                      {item.count(overview)}
                    </span>
                  ) : (
                    <Skeleton className="h-4 w-8 rounded-full" />
                  )}
                </span>
                <span className="mt-0.5 line-clamp-2 block text-xs leading-relaxed text-muted-foreground">
                  {t(`items.${item.key}.description`)}
                </span>
                {attention > 0 && (
                  <span className="mt-1.5 inline-flex items-center gap-1 rounded-full bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">
                    {t(`items.${item.key}.attention`, { count: attention })}
                  </span>
                )}
              </span>
              <ChevronRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
            </Link>
          )
        })}
      </div>
    </div>
  )
}
