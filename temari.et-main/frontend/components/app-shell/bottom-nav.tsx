"use client"

import Link from "next/link"
import { usePathname } from "next/navigation"
import { Globe, LifeBuoy, LogOut, Menu, Settings, type LucideIcon } from "lucide-react"
import { useMemo, useState } from "react"

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import {
  Drawer,
  DrawerBody,
  DrawerClose,
  DrawerContent,
  DrawerTitle,
  DrawerTrigger,
} from "@/components/ui/drawer"
import { ThemeToggle } from "@/components/ui/theme-toggle"
import { useAuth } from "@/lib/auth/auth-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { LOCALES, useLocale, useTranslation } from "@/lib/i18n"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { cn } from "@/lib/utils"

import {
  hasStaffMembership,
  isNavActive,
  isRelationshipOnly,
  type NavItem,
  visibleSections,
} from "./nav-config"
import { useWorkspaceSurface } from "./use-workspace-surface"

const LOCALE_LABELS: Record<string, string> = { en: "EN", am: "አማ", om: "OM" }

/** Primary tabs shown directly in the bar; the rest move into the Menu drawer. */
const MAX_TABS = 4

const tabClass =
  "flex h-[3.4rem] flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition-colors duration-150 active:scale-95"

/** One launcher tile in the Menu drawer grid (banking-app style). */
function MenuTile({
  href,
  icon: Icon,
  label,
  active,
}: {
  href: string
  icon: LucideIcon
  label: string
  active?: boolean
}) {
  return (
    <DrawerClose asChild>
      <Link
        href={href}
        aria-current={active ? "page" : undefined}
        className="flex flex-col items-center gap-1.5 rounded-2xl px-1 py-2 text-center transition-transform active:scale-95"
      >
        <span
          className={cn(
            "flex size-11 items-center justify-center rounded-2xl border transition-colors",
            active
              ? "border-primary/25 bg-primary/12 text-primary"
              : "border-border/60 bg-muted/40 text-muted-foreground",
          )}
        >
          <Icon className="size-5" />
        </span>
        <span
          className={cn(
            "line-clamp-2 w-full text-[11px] font-medium leading-tight",
            active ? "text-primary" : "text-foreground/80",
          )}
        >
          {label}
        </span>
      </Link>
    </DrawerClose>
  )
}

export function BottomNav() {
  const pathname = usePathname()
  const { user, logout } = useAuth()
  const { locale, setLocale } = useLocale()
  const { t } = useTranslation("common")
  const [menuOpen, setMenuOpen] = useState(false)
  const [showLogoutDialog, setShowLogoutDialog] = useState(false)

  // Context-aware, same as the desktop sidebar (see useEffectivePermissions):
  // the active workspace's permissions AND its surface (staff/family/tutor).
  const permissions = useEffectivePermissions()
  const surface = useWorkspaceSurface()
  const sections = useMemo(
    () =>
      visibleSections(
        permissions,
        {
          isParent: user?.is_parent,
          isStudent: user?.is_student,
          isTutor: user?.is_tutor,
          relationshipOnly: isRelationshipOnly(user),
          isStaff: hasStaffMembership(user),
        },
        surface,
      ),
    [permissions, user, surface],
  )
  // Dedupe by href: a surface can still carry the same page twice (e.g. a
  // student-parent's /me/courses) — one flattened, keyed tab list must show
  // it once.
  const seen = new Set<string>()
  const items: NavItem[] = sections
    .flatMap((s) => s.items)
    .filter((item) => !seen.has(item.href) && (seen.add(item.href) || true))
  const allHrefs = items.map((item) => item.href)
  const isActive = (href: string) => isNavActive(href, pathname, allHrefs)

  // First 4 items = the tab bar; everything else stays grouped by section in
  // the Menu drawer, so a heavy role gets a scannable launcher instead of one
  // long undifferentiated list.
  const primary = items.slice(0, MAX_TABS)
  const primaryHrefs = new Set(primary.map((item) => item.href))
  const overflowSections = sections
    .map((s) => ({
      labelKey: s.labelKey,
      items: s.items.filter((item) => !primaryHrefs.has(item.href)),
    }))
    .filter((s) => s.items.length > 0)
  const menuActive =
    menuOpen ||
    overflowSections.some((s) => s.items.some((item) => isActive(item.href)))

  return (
    <>
      <nav className="fixed inset-x-0 bottom-0 z-40 md:hidden" aria-label={t("menu")}>
        <div className="bg-background/95 supports-[backdrop-filter]:bg-background/80 border-t pb-[max(0.375rem,env(safe-area-inset-bottom))] pt-1 shadow-[0_-12px_40px_-24px_rgba(0,0,0,0.45)] backdrop-blur-2xl">
          <div className="mx-auto grid max-w-lg grid-cols-5 px-1">
            {primary.map((item) => {
              const Icon = item.icon
              const active = isActive(item.href)
              return (
                <Link
                  key={item.key}
                  href={item.href}
                  aria-current={active ? "page" : undefined}
                  className={cn(tabClass, active ? "text-primary" : "text-muted-foreground")}
                >
                  <span
                    className={cn(
                      "flex h-7 w-12 items-center justify-center rounded-full transition-colors",
                      active && "bg-primary/12",
                    )}
                  >
                    <Icon className="size-5" strokeWidth={active ? 2.5 : 2} />
                  </span>
                  <span className="max-w-[4.5rem] truncate">{t(`nav.${item.key}`)}</span>
                </Link>
              )
            })}

            <Drawer open={menuOpen} onOpenChange={setMenuOpen}>
              <DrawerTrigger asChild>
                <button
                  type="button"
                  className={cn(tabClass, menuActive ? "text-primary" : "text-muted-foreground")}
                >
                  <span
                    className={cn(
                      "flex h-7 w-12 items-center justify-center rounded-full transition-colors",
                      menuActive && "bg-primary/12",
                    )}
                  >
                    <Menu className="size-5" strokeWidth={menuActive ? 2.5 : 2} />
                  </span>
                  <span>{t("menu")}</span>
                </button>
              </DrawerTrigger>

              <DrawerContent className="max-h-[88dvh]" aria-describedby={undefined}>
                <DrawerTitle className="sr-only">{t("menu")}</DrawerTitle>

                {/* Profile — pinned above the scroll area so identity + sign
                    out stay reachable no matter how long the menu gets. */}
                <div className="shrink-0 px-4 pb-3 pt-1">
                  <div className="border-border/60 bg-muted/30 flex items-center gap-3 rounded-2xl border p-3">
                    <PersonAvatar
                      name={user?.name ?? "?"}
                      photoUrl={user?.avatar_url}
                      className="size-11 rounded-xl text-sm"
                    />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-base font-semibold">{user?.name}</p>
                      <p className="text-muted-foreground truncate text-sm">{user?.phone}</p>
                    </div>
                    <button
                      onClick={() => {
                        setMenuOpen(false)
                        setShowLogoutDialog(true)
                      }}
                      className="border-border/70 text-muted-foreground hover:border-destructive/30 hover:bg-destructive/10 hover:text-destructive flex size-10 shrink-0 items-center justify-center rounded-xl border transition-colors"
                      aria-label={t("nav.logout")}
                    >
                      <LogOut className="size-4" />
                    </button>
                  </div>
                </div>

                {/* Everything else lives in ONE scroll area (flex-1 min-h-0 via
                    DrawerBody) — nothing is ever clipped below the fold. */}
                <DrawerBody className="overscroll-contain px-3 pt-0 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                  {/* Nav launcher — grouped icon-tile grid, app style. */}
                  <div className="space-y-4">
                    {overflowSections.map((section) => (
                      <div key={section.labelKey}>
                        <p className="text-muted-foreground/70 mb-1.5 px-1.5 text-[10px] font-semibold uppercase tracking-wider">
                          {t(section.labelKey)}
                        </p>
                        <div className="grid grid-cols-4 gap-x-1 gap-y-1.5">
                          {section.items.map((item) => (
                            <MenuTile
                              key={item.key + item.href}
                              href={item.href}
                              icon={item.icon}
                              label={t(`nav.${item.key}`)}
                              active={isActive(item.href)}
                            />
                          ))}
                        </div>
                      </div>
                    ))}

                    {/* Account: settings + help ride the same grid language. */}
                    <div>
                      <p className="text-muted-foreground/70 mb-1.5 px-1.5 text-[10px] font-semibold uppercase tracking-wider">
                        {t("account")}
                      </p>
                      <div className="grid grid-cols-4 gap-x-1 gap-y-1.5">
                        <MenuTile
                          href="/settings"
                          icon={Settings}
                          label={t("nav.settings")}
                          active={isActive("/settings")}
                        />
                        <MenuTile href="/docs" icon={LifeBuoy} label={t("help.cta")} />
                      </div>
                    </div>
                  </div>

                  {/* Appearance */}
                  <div className="border-border/50 bg-muted/20 mt-4 flex items-center gap-2.5 rounded-xl border px-3 py-3">
                    <span className="text-muted-foreground min-w-0 flex-1 truncate text-xs font-medium">
                      {t("appearance")}
                    </span>
                    <ThemeToggle />
                  </div>

                  {/* Language */}
                  <div className="border-border/50 bg-muted/20 mt-2 flex items-center gap-2.5 rounded-xl border px-3 py-3">
                    <Globe className="text-muted-foreground size-4 shrink-0" />
                    <span className="text-muted-foreground min-w-0 flex-1 truncate text-xs font-medium">
                      {t("language")}
                    </span>
                    <div className="border-border/50 bg-muted/40 inline-flex shrink-0 rounded-lg border p-0.5">
                      {LOCALES.map((code) => (
                        <button
                          key={code}
                          onClick={() => setLocale(code)}
                          aria-pressed={locale === code}
                          className={cn(
                            "min-w-[2.75rem] rounded-md px-3 py-1.5 text-xs font-semibold tracking-wide transition-all",
                            locale === code
                              ? "bg-background text-foreground shadow-sm"
                              : "text-muted-foreground hover:text-foreground",
                          )}
                        >
                          {LOCALE_LABELS[code] ?? code.toUpperCase()}
                        </button>
                      ))}
                    </div>
                  </div>
                </DrawerBody>
              </DrawerContent>
            </Drawer>
          </div>
        </div>
      </nav>

      <AlertDialog open={showLogoutDialog} onOpenChange={setShowLogoutDialog}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t("nav.logoutConfirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription>{t("nav.logoutConfirmDesc")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t("actions.cancel")}</AlertDialogCancel>
            <AlertDialogAction onClick={() => logout()} variant="destructive">
              {t("nav.logout")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
