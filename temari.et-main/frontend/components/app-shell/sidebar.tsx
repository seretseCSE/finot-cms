"use client"

import Link from "next/link"
import { usePathname } from "next/navigation"
import { ChevronDown, ChevronsUpDown, Globe, LogOut, PanelLeft, Settings } from "lucide-react"
import { useEffect, useState } from "react"

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
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { ThemeToggle } from "@/components/ui/theme-toggle"
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip"
import { useAuth } from "@/lib/auth/auth-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { LOCALES, useLocale, useTranslation } from "@/lib/i18n"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { cn } from "@/lib/utils"

import { GlobalSearchTrigger } from "./global-search"
import { NotificationBell } from "./notification-bell"
import { hasStaffMembership, isNavActive, isRelationshipOnly, visibleSections } from "./nav-config"
import { useWorkspaceSurface } from "./use-workspace-surface"
import { WorkspaceSwitcher } from "./workspace-switcher"

const LOCALE_LABELS: Record<string, string> = { en: "EN", am: "አማ", om: "OM" }
const NAV_COLLAPSE_KEY = "nav_collapsed_sections"

interface SidebarProps {
  collapsed?: boolean
  onToggle?: () => void
}

export function Sidebar({ collapsed = false, onToggle }: SidebarProps) {
  const pathname = usePathname()
  const { user, logout } = useAuth()
  const { locale, setLocale } = useLocale()
  const { t } = useTranslation("common")
  // Nav reflects the role(s) the user holds in their ACTIVE school/branch
  // context, not the union of every role — see useEffectivePermissions.
  const permissions = useEffectivePermissions()
  // …and only the ACTIVE workspace's lane: a director-who-is-also-a-parent
  // sees the staff nav here and the family nav only inside "My family".
  const surface = useWorkspaceSurface()
  const sections = visibleSections(
    permissions,
    {
      isParent: user?.is_parent,
      isStudent: user?.is_student,
      isTutor: user?.is_tutor,
      relationshipOnly: isRelationshipOnly(user),
      isStaff: hasStaffMembership(user),
    },
    surface,
  )
  const [showLogoutDialog, setShowLogoutDialog] = useState(false)
  const allHrefs = sections.flatMap((section) => section.items.map((item) => item.href))

  // Collapsible domain groups: heavy roles (admins) fold away what they don't
  // use. State persists per-device; the group holding the active route always
  // stays open so the current page is never hidden behind a collapsed header.
  const [collapsedSections, setCollapsedSections] = useState<Set<string>>(new Set())
  useEffect(() => {
    const stored = localStorage.getItem(NAV_COLLAPSE_KEY)
    if (stored) {
      try {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- client-only storage hydration
        setCollapsedSections(new Set(JSON.parse(stored) as string[]))
      } catch {
        /* ignore corrupt value */
      }
    }
  }, [])
  function toggleSection(labelKey: string) {
    setCollapsedSections((prev) => {
      const next = new Set(prev)
      if (next.has(labelKey)) next.delete(labelKey)
      else next.add(labelKey)
      localStorage.setItem(NAV_COLLAPSE_KEY, JSON.stringify([...next]))
      return next
    })
  }

  /** Profile dropdown body — shared by the expanded row and collapsed avatar. */
  const profileMenu = (
    <DropdownMenuContent
      side={collapsed ? "right" : "top"}
      align={collapsed ? "end" : "start"}
      sideOffset={8}
      className="w-64 rounded-xl p-1.5 shadow-lg"
    >
      {/* Identity header */}
      <div className="flex items-center gap-2.5 px-2 py-2">
        <PersonAvatar
          name={user?.name ?? "?"}
          photoUrl={user?.avatar_url}
          className="size-9 rounded-lg text-xs"
        />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold leading-tight">{user?.name}</p>
          <p className="text-muted-foreground truncate text-[11px] leading-tight">{user?.phone}</p>
        </div>
      </div>

      <DropdownMenuSeparator />

      <DropdownMenuItem asChild className="cursor-pointer rounded-lg px-2 py-2">
        <Link href="/settings">
          <Settings className="text-muted-foreground size-4" />
          {t("nav.settings")}
        </Link>
      </DropdownMenuItem>

      <DropdownMenuSeparator />

      {/* Appearance + language: plain rows, not menu items — the menu must
          stay open while the user flips through options. */}
      <div className="flex items-center justify-between gap-2.5 rounded-lg px-2 py-1.5">
        <span className="text-muted-foreground min-w-0 truncate text-xs">{t("appearance")}</span>
        <ThemeToggle />
      </div>
      <div className="flex items-center gap-2.5 rounded-lg px-2 py-1.5">
        <Globe className="text-muted-foreground size-3.5 shrink-0" />
        <span className="text-muted-foreground min-w-0 flex-1 truncate text-xs">
          {t("language")}
        </span>
        <div className="border-border/50 bg-muted/40 inline-flex shrink-0 rounded-md border p-0.5">
          {LOCALES.map((code) => (
            <button
              key={code}
              onClick={() => setLocale(code)}
              aria-pressed={locale === code}
              className={cn(
                "min-w-[2.25rem] rounded-[5px] px-2 py-1 text-[11px] font-semibold tracking-wide transition-all",
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

      <DropdownMenuSeparator />

      <DropdownMenuItem
        onClick={() => setShowLogoutDialog(true)}
        className="text-destructive focus:text-destructive focus:bg-destructive/10 cursor-pointer rounded-lg px-2 py-2"
      >
        <LogOut className="size-4" />
        {t("nav.logout")}
      </DropdownMenuItem>
    </DropdownMenuContent>
  )

  return (
    <>
      <TooltipProvider delayDuration={0}>
        <aside
          className={cn(
            "bg-sidebar text-sidebar-foreground sticky top-0 hidden h-svh shrink-0 flex-col border-r md:flex transition-[width] duration-200 ease-out",
            collapsed ? "w-16" : "w-64",
          )}
        >
          {/* Header: workspace switcher + collapse toggle, nothing else */}
          <div
            className={cn(
              "border-sidebar-border/80 border-b",
              collapsed ? "flex flex-col items-center gap-1 p-2" : "px-3 py-1",
            )}
          >
            {collapsed ? (
              <Tooltip>
                <TooltipTrigger asChild>
                  <button
                    onClick={onToggle}
                    className="text-muted-foreground hover:text-foreground hover:bg-sidebar-accent flex size-9 items-center justify-center rounded-lg transition-colors"
                    aria-label="Expand sidebar"
                  >
                    <PanelLeft className="size-4" />
                  </button>
                </TooltipTrigger>
                <TooltipContent side="right">Expand</TooltipContent>
              </Tooltip>
            ) : (
              <div className="flex items-center gap-1">
                <div className="min-w-0 flex-1">
                  <WorkspaceSwitcher />
                </div>
                {onToggle && (
                  <button
                    onClick={onToggle}
                    className="text-muted-foreground hover:text-foreground hover:bg-sidebar-accent shrink-0 rounded-lg p-1.5 transition-colors"
                    aria-label="Collapse sidebar"
                  >
                    <PanelLeft className="size-4" />
                  </button>
                )}
              </div>
            )}
          </div>

          {/* Global search (⌘K) + notifications */}
          {collapsed ? (
            <div className="flex flex-col items-center gap-1 px-2 pt-2.5">
              <Tooltip>
                <TooltipTrigger asChild>
                  <GlobalSearchTrigger
                    variant="icon"
                    className="size-9 rounded-lg text-sidebar-foreground/90 hover:bg-sidebar-accent"
                  />
                </TooltipTrigger>
                <TooltipContent side="right">{t("search.title")}</TooltipContent>
              </Tooltip>
              <NotificationBell className="text-sidebar-foreground/90" />
            </div>
          ) : (
            <div className="flex items-center gap-1.5 px-3 pt-2.5">
              <div className="min-w-0 flex-1">
                <GlobalSearchTrigger />
              </div>
              <NotificationBell className="shrink-0 text-sidebar-foreground/90" />
            </div>
          )}

          {/* Nav sections */}
          <nav className="flex-1 overflow-y-auto px-2 py-3">
            {sections.map((section, index) => {
              const hasActive = section.items.some((item) =>
                isNavActive(item.href, pathname, allHrefs),
              )
              // A group is open when: the rail is icon-only (always show), it's
              // a pinned small group, it holds the active route, or the user
              // hasn't collapsed it.
              const collapsible = !collapsed && !section.pinned
              const open =
                collapsed || section.pinned || hasActive || !collapsedSections.has(section.labelKey)
              return (
              <div key={section.labelKey} className={cn(index > 0 && "mt-4")}>
                {!collapsed &&
                  (collapsible ? (
                    <button
                      type="button"
                      onClick={() => toggleSection(section.labelKey)}
                      aria-expanded={open}
                      className="text-muted-foreground/80 hover:text-foreground group mb-1 flex w-full items-center justify-between rounded-md px-2 py-1 text-[10px] font-semibold uppercase tracking-wider transition-colors"
                    >
                      <span className="truncate">{t(section.labelKey)}</span>
                      <ChevronDown
                        className={cn(
                          "size-3.5 shrink-0 opacity-50 transition-transform duration-200 group-hover:opacity-100",
                          !open && "-rotate-90",
                        )}
                      />
                    </button>
                  ) : (
                    <p className="text-muted-foreground/80 mb-1 px-2 text-[10px] font-semibold uppercase tracking-wider">
                      {t(section.labelKey)}
                    </p>
                  ))}
                {open && (
                <div className="space-y-0.5">
                  {section.items.map((item) => {
                    const active = isNavActive(item.href, pathname, allHrefs)
                    const Icon = item.icon
                    return (
                      <Tooltip key={item.key}>
                        <TooltipTrigger asChild>
                          <Link
                            href={item.href}
                            aria-current={active ? "page" : undefined}
                            className={cn(
                              "relative flex h-9 items-center gap-3 rounded-lg px-3 text-sm transition-colors duration-150",
                              collapsed && "justify-center px-0",
                              active
                                ? "bg-primary/10 text-primary font-medium"
                                : "text-sidebar-foreground/90 hover:bg-sidebar-accent active:bg-sidebar-accent/70",
                            )}
                          >
                            {active && !collapsed && (
                              <span className="bg-primary absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-full" />
                            )}
                            <Icon
                              className={cn(
                                "size-4 shrink-0",
                                active ? "text-primary" : "opacity-70",
                              )}
                              strokeWidth={active ? 2.25 : 2}
                            />
                            {!collapsed && t(`nav.${item.key}`)}
                          </Link>
                        </TooltipTrigger>
                        {collapsed && (
                          <TooltipContent side="right">{t(`nav.${item.key}`)}</TooltipContent>
                        )}
                      </Tooltip>
                    )
                  })}
                </div>
                )}
              </div>
              )
            })}
          </nav>

          {/* Footer: just the profile — everything else lives in its dropdown */}
          <div className="border-sidebar-border/80 border-t p-2.5">
            {collapsed ? (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button
                    className="mx-auto flex size-9 items-center justify-center rounded-lg transition-opacity hover:opacity-85"
                    aria-label={user?.name ?? t("nav.settings")}
                  >
                    <PersonAvatar
                      name={user?.name ?? "?"}
                      photoUrl={user?.avatar_url}
                      className="size-9 rounded-lg text-xs"
                    />
                  </button>
                </DropdownMenuTrigger>
                {profileMenu}
              </DropdownMenu>
            ) : (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button
                    className={cn(
                      "border-sidebar-border/80 bg-sidebar-accent/40 hover:bg-sidebar-accent/70 flex w-full items-center gap-2.5 rounded-xl border px-2.5 py-2 text-left transition-colors",
                      "data-[state=open]:bg-sidebar-accent/80",
                    )}
                  >
                    <PersonAvatar
                      name={user?.name ?? "?"}
                      photoUrl={user?.avatar_url}
                      className="size-8 rounded-lg text-[11px]"
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium leading-tight">
                        {user?.name}
                      </span>
                      <span className="text-muted-foreground block truncate text-[11px] leading-tight">
                        {user?.phone}
                      </span>
                    </span>
                    <ChevronsUpDown className="text-muted-foreground/60 size-3.5 shrink-0" />
                  </button>
                </DropdownMenuTrigger>
                {profileMenu}
              </DropdownMenu>
            )}
          </div>
        </aside>
      </TooltipProvider>

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
