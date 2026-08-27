"use client"

import { ShieldAlert } from "lucide-react"
import Link from "next/link"

import { CatalogSideNav, CatalogsProvider } from "@/components/catalogs/catalogs-shell"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { useAuth } from "@/lib/auth/auth-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"

/**
 * The platform catalog studio: a nested area with its own secondary sidebar
 * (desktop) / pill rail (mobile) over the seed catalogs. Temari.et staff only
 * (`catalogs.manage`) — the backend re-checks every request; this guard is UX.
 */
export default function CatalogsLayout({ children }: { children: React.ReactNode }) {
  const { t } = useTranslation("catalogs")
  const { t: tc } = useTranslation("common")
  const { loading } = useAuth()
  const permissions = useEffectivePermissions()

  if (!loading && !permissions.includes("catalogs.manage")) {
    return (
      <EmptyState
        icon={ShieldAlert}
        title={tc("unauthorized.title")}
        description={tc("unauthorized.message")}
        action={
          <Button asChild variant="outline">
            <Link href="/dashboard">{tc("unauthorized.backToDashboard")}</Link>
          </Button>
        }
      />
    )
  }

  return (
    <CatalogsProvider>
      <div className="lg:flex lg:items-start">
        <aside className="sticky top-6 hidden w-60 shrink-0 pl-8 lg:block" aria-label={t("title")}>
          <CatalogSideNav />
        </aside>
        <div className="min-w-0 flex-1">{children}</div>
      </div>
    </CatalogsProvider>
  )
}
