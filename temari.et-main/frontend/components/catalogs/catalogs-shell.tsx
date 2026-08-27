"use client"

import {
  BellRing,
  BookMarked,
  HeartPulse,
  Landmark,
  Layers,
  MapPinned,
  type LucideIcon,
} from "lucide-react"
import Link from "next/link"
import { usePathname } from "next/navigation"
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
} from "react"

import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { CatalogOverview } from "@/lib/types"
import { cn } from "@/lib/utils"

/**
 * The platform catalog registry: one entry per seed catalog managed in the
 * studio. Counts/attention come from /catalogs/overview so the nav, the hub
 * cards and the mobile rail all read the same numbers.
 */
export interface CatalogItem {
  key: "subjects" | "gradeLevels" | "banks" | "healthConditions" | "directory" | "notifications"
  href: string
  icon: LucideIcon
  count: (o: CatalogOverview) => number
  /** Rows needing platform attention (unverified, inactive…) — shows a chip. */
  attention?: (o: CatalogOverview) => number
}

export const CATALOG_ITEMS: CatalogItem[] = [
  {
    key: "subjects",
    href: "/catalogs/subjects",
    icon: BookMarked,
    count: (o) => o.subjects.total,
    attention: (o) => o.subjects.inactive,
  },
  {
    key: "gradeLevels",
    href: "/catalogs/grade-levels",
    icon: Layers,
    count: (o) => o.grade_levels.total,
  },
  {
    key: "banks",
    href: "/catalogs/banks",
    icon: Landmark,
    count: (o) => o.banks.total,
    attention: (o) => o.banks.inactive,
  },
  {
    key: "healthConditions",
    href: "/catalogs/health-conditions",
    icon: HeartPulse,
    count: (o) => o.health_conditions.total,
    attention: (o) => o.health_conditions.inactive,
  },
  {
    key: "directory",
    href: "/catalogs/directory",
    icon: MapPinned,
    count: (o) => o.school_directory.total,
    attention: (o) => o.school_directory.unverified,
  },
  {
    key: "notifications",
    href: "/catalogs/notifications",
    icon: BellRing,
    count: (o) => o.notification_events?.total ?? 0,
  },
]

// ── Overview context (one fetch shared by nav + hub) ────────────────────────

interface CatalogsContextValue {
  overview: CatalogOverview | null
  refreshOverview: () => void
}

const CatalogsContext = createContext<CatalogsContextValue>({
  overview: null,
  refreshOverview: () => {},
})

export const useCatalogs = () => useContext(CatalogsContext)

export function CatalogsProvider({ children }: { children: React.ReactNode }) {
  const [overview, setOverview] = useState<CatalogOverview | null>(null)

  const refreshOverview = useCallback(() => {
    apiFetch<{ data: CatalogOverview }>("/catalogs/overview")
      .then((res) => setOverview(res.data))
      .catch(() => {})
  }, [])

  useEffect(() => {
    refreshOverview()
  }, [refreshOverview])

  return (
    <CatalogsContext.Provider value={{ overview, refreshOverview }}>
      {children}
    </CatalogsContext.Provider>
  )
}

// ── Nested sidebar (desktop) ────────────────────────────────────────────────

export function CatalogSideNav() {
  const { t } = useTranslation("catalogs")
  const pathname = usePathname()
  const { overview } = useCatalogs()

  return (
    <nav aria-label={t("title")} className="space-y-1">
      <p className="px-3 pb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {t("navLabel")}
      </p>
      {CATALOG_ITEMS.map((item) => {
        const active = pathname.startsWith(item.href)
        const attention = overview && item.attention ? item.attention(overview) : 0
        return (
          <Link
            key={item.key}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={cn(
              "pressable flex min-h-10 items-center gap-2.5 rounded-xl px-3 text-sm font-medium transition-colors",
              active
                ? "bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-muted hover:text-foreground",
            )}
          >
            <item.icon className="size-4 shrink-0" strokeWidth={2} />
            <span className="min-w-0 flex-1 truncate">{t(`items.${item.key}.title`)}</span>
            {attention > 0 && (
              <span className="size-1.5 shrink-0 rounded-full bg-warning" aria-hidden />
            )}
            {overview && (
              <span className="text-xs tabular-nums text-muted-foreground">
                {item.count(overview)}
              </span>
            )}
          </Link>
        )
      })}
    </nav>
  )
}

// ── Catalog switcher rail (mobile + tablet) ─────────────────────────────────

export function CatalogPillNav() {
  const { t } = useTranslation("catalogs")
  const pathname = usePathname()
  const { overview } = useCatalogs()

  return (
    <div className="scrollbar-none flex gap-2 overflow-x-auto px-4 md:px-8">
      {CATALOG_ITEMS.map((item) => {
        const active = pathname.startsWith(item.href)
        const attention = overview && item.attention ? item.attention(overview) : 0
        return (
          <Link
            key={item.key}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={cn(
              "pressable inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-xs font-medium transition-colors",
              active
                ? "border-primary/40 bg-primary/10 text-primary"
                : "text-muted-foreground hover:bg-muted",
            )}
          >
            <item.icon className="size-3.5" strokeWidth={2} />
            {t(`items.${item.key}.title`)}
            {attention > 0 && (
              <span className="size-1.5 rounded-full bg-warning" aria-hidden />
            )}
          </Link>
        )
      })}
    </div>
  )
}
