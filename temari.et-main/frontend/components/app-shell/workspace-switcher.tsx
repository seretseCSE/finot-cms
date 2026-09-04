"use client"

import {
  Baby,
  BookOpen,
  Building2,
  Check,
  ChevronDown,
  GraduationCap,
  Heart,
  type LucideIcon,
} from "lucide-react"
import { usePathname, useRouter } from "next/navigation"

import { Button } from "@/components/ui/button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useAuth } from "@/lib/auth/auth-context"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useTranslation } from "@/lib/i18n"
import type { ContextOption } from "@/lib/types"
import { cn } from "@/lib/utils"

import { useWorkspaceSurface } from "./use-workspace-surface"

const AVATAR_COLOURS = [
  "bg-blue-500",
  "bg-violet-500",
  "bg-emerald-500",
  "bg-orange-500",
  "bg-rose-500",
  "bg-cyan-500",
  "bg-amber-500",
  "bg-indigo-500",
]

function WorkspaceAvatar({
  name,
  logoUrl,
  size = "md",
}: {
  name: string
  logoUrl?: string | null
  size?: "sm" | "md"
}) {
  const letter = name?.charAt(0)?.toUpperCase() ?? "?"
  const idx = name
    ? [...name].reduce((acc, c) => acc + c.charCodeAt(0), 0) % AVATAR_COLOURS.length
    : 0

  if (logoUrl) {
    return (
      // eslint-disable-next-line @next/next/no-img-element -- signed R2 URL
      <img
        src={logoUrl}
        alt=""
        className={cn(
          "bg-card shrink-0 rounded-full border object-contain",
          size === "sm" ? "size-7" : "size-9",
        )}
      />
    )
  }

  return (
    <span
      className={cn(
        "flex shrink-0 items-center justify-center rounded-full font-semibold text-white",
        AVATAR_COLOURS[idx],
        size === "sm" ? "size-7 text-[11px]" : "size-9 text-sm",
      )}
    >
      {letter}
    </span>
  )
}

/**
 * Workspace (school / branch) switcher modelled on the check.et pattern: a
 * dropdown grouped by school, with role badges and a radio-style indicator.
 * Used in both the desktop sidebar header and the mobile workspace bar.
 */
export function WorkspaceSwitcher({
  className,
  compact = false,
}: {
  className?: string
  compact?: boolean
}) {
  const { user } = useAuth()
  const { options, activeOption, switchTo } = useSchoolContext()
  const { t } = useTranslation("common")
  const pathname = usePathname()
  const router = useRouter()

  // Relationship HATS (ADR-012): being a parent/student/tutor is not a
  // permission context, so these never touch the stored school workspace —
  // they navigate into their own lane (/me, /tutoring), and the nav renders
  // ONLY that lane there. Children are switched INSIDE /me (ChildTabs).
  const surface = useWorkspaceSurface()
  const hats: { id: string; href: string; label: string; icon: LucideIcon }[] = [
    ...(user?.is_parent
      ? [{ id: "hat-family", href: "/me/children", label: t("context.myFamily"), icon: Baby }]
      : []),
    ...(user?.is_student
      ? [{ id: "hat-learning", href: "/me/student", label: t("nav.myLearning"), icon: BookOpen }]
      : []),
    ...(user?.is_tutor
      ? [
          {
            id: "hat-tutor",
            href: "/tutoring",
            label: t("sections.tutoring"),
            icon: GraduationCap,
          },
        ]
      : []),
  ]
  // The highlighted hat follows the ACTIVE surface (sticky across shared
  // routes like /messages), not just the literal pathname.
  const activeHat =
    surface === "tutor"
      ? (hats.find((hat) => hat.id === "hat-tutor") ?? null)
      : surface === "family"
        ? pathname?.startsWith("/me/student")
          ? (hats.find((hat) => hat.id === "hat-learning") ??
            hats.find((hat) => hat.id !== "hat-tutor") ??
            null)
          : (hats.find((hat) => hat.id === "hat-family") ??
            hats.find((hat) => hat.id !== "hat-tutor") ??
            null)
        : null

  const title = (option: ContextOption): string => {
    if (option.schoolId === null) return t("context.platform")
    return option.branchName ?? t("context.allBranches")
  }
  const subtitle = (option: ContextOption | null): string => {
    if (!option) return t("selectContext")
    if (option.schoolId === null) return t("appName")
    return option.schoolName ?? t("context.allBranches")
  }

  const roleFor = (option: ContextOption): string | null => {
    const membership = user?.memberships?.find(
      (m) =>
        m.is_active &&
        (option.branchId !== null
          ? m.branch_id === option.branchId
          : option.schoolId !== null
            ? m.scope === "school" && m.school_id === option.schoolId
            : m.scope === "platform"),
    )
    return membership?.role_label ?? null
  }

  // Group selectable contexts by their school (the "workspace").
  const grouped = options.reduce<Record<string, ContextOption[]>>((acc, option) => {
    const key = option.schoolName ?? t("appName")
    acc[key] = acc[key] ? [...acc[key], option] : [option]
    return acc
  }, {})

  const avatarName = activeHat
    ? activeHat.label
    : (activeOption?.schoolName ?? (activeOption ? title(activeOption) : "?"))
  const triggerTitle = activeHat
    ? activeHat.label
    : activeOption
      ? title(activeOption)
      : t("selectContext")
  const triggerSubtitle = activeHat ? (user?.name ?? t("appName")) : subtitle(activeOption)

  // Single context and no relationship hats: a static card, no dropdown.
  if (options.length + hats.length <= 1) {
    return (
      <div
        className={cn(
          "flex w-full items-center gap-2.5 rounded-xl px-2 py-2",
          compact ? "gap-2 px-1.5 py-1.5" : "",
          className,
        )}
      >
        <WorkspaceAvatar
          name={avatarName}
          logoUrl={activeHat ? null : activeOption?.schoolLogoUrl}
          size="sm"
        />
        <span className="flex min-w-0 flex-1 flex-col gap-0.5 leading-none">
          <span
            className={cn(
              "text-sidebar-foreground truncate font-semibold leading-none",
              compact ? "text-[12px]" : "text-[13px]",
            )}
          >
            {triggerTitle}
          </span>
          <span
            className={cn(
              "text-muted-foreground truncate leading-none",
              compact ? "text-[10px]" : "text-[11px]",
            )}
          >
            {triggerSubtitle}
          </span>
        </span>
      </div>
    )
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          className={cn(
            "group h-auto w-full justify-start gap-2.5 rounded-md border border-transparent px-2 py-1 text-left font-normal",
            "hover:border-border/60 hover:bg-sidebar-accent/60",
            "data-[state=open]:border-border/60 data-[state=open]:bg-sidebar-accent/80",
            "transition-all duration-150",
            compact && "gap-2 px-1.5 py-1.5",
            className,
          )}
        >
          <WorkspaceAvatar
            name={avatarName}
            logoUrl={activeHat ? null : activeOption?.schoolLogoUrl}
            size="sm"
          />
          <span className="flex min-w-0 flex-1 flex-col gap-0.5 leading-none">
            <span
              className={cn(
                "text-sidebar-foreground truncate font-semibold leading-none",
                compact ? "text-[12px]" : "text-[13px]",
              )}
            >
              {triggerTitle}
            </span>
            <span
              className={cn(
                "text-muted-foreground truncate leading-none",
                compact ? "text-[10px]" : "text-[11px]",
              )}
            >
              {triggerSubtitle}
            </span>
          </span>
          <ChevronDown
            className={cn(
              "text-muted-foreground/60 shrink-0 transition-transform duration-150 group-data-[state=open]:rotate-180",
              compact ? "size-3" : "size-3.5",
            )}
          />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" sideOffset={6} className="w-72 rounded-xl p-1.5 shadow-lg">
        <p className="text-muted-foreground/60 px-2 pb-1.5 pt-0.5 text-[10px] font-semibold uppercase tracking-widest">
          {t("switchContext")}
        </p>

        <div className="space-y-0.5">
          {hats.length > 0 && (
            <div>
              <div className="flex items-center gap-2 px-2 py-1.5">
                <Heart className="text-muted-foreground/50 size-3 shrink-0" />
                <span className="text-muted-foreground truncate text-[11px] font-medium">
                  {t("context.personal")}
                </span>
              </div>
              {hats.map((hat) => {
                const active = activeHat?.id === hat.id
                const HatIcon = hat.icon
                return (
                  <DropdownMenuItem
                    key={hat.id}
                    onClick={() => router.push(hat.href)}
                    className={cn(
                      "group/item mx-0 flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2 text-sm",
                      active
                        ? "bg-primary/10 text-primary focus:bg-primary/15 focus:text-primary"
                        : "text-sidebar-foreground focus:bg-sidebar-accent",
                    )}
                  >
                    <span
                      className={cn(
                        "flex size-4 shrink-0 items-center justify-center rounded-full",
                        active ? "bg-primary/20" : "bg-muted/60 group-hover/item:bg-muted",
                      )}
                    >
                      {active && <Check className="text-primary size-2.5" />}
                    </span>
                    <span className="flex-1 truncate font-medium leading-none">{hat.label}</span>
                    <HatIcon className="text-muted-foreground/60 size-3.5 shrink-0" />
                  </DropdownMenuItem>
                )
              })}
              {options.length > 0 && <DropdownMenuSeparator className="my-1.5" />}
            </div>
          )}
          {Object.entries(grouped).map(([schoolName, items], gi) => (
            <div key={schoolName}>
              {gi > 0 && <DropdownMenuSeparator className="my-1.5" />}

              <div className="flex items-center gap-2 px-2 py-1.5">
                <Building2 className="text-muted-foreground/50 size-3 shrink-0" />
                <span className="text-muted-foreground truncate text-[11px] font-medium">
                  {schoolName}
                </span>
              </div>

              {items.map((option) => {
                // ONE workspace is active at a time: while a personal hat
                // (family/tutoring) is active, the school context stays
                // stored for the return trip but must not read as selected.
                const active = activeHat === null && activeOption?.id === option.id
                const role = roleFor(option)
                return (
                  <DropdownMenuItem
                    key={option.id}
                    onClick={() => switchTo(option)}
                    className={cn(
                      "group/item mx-0 flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2 text-sm",
                      active
                        ? "bg-primary/10 text-primary focus:bg-primary/15 focus:text-primary"
                        : "text-sidebar-foreground focus:bg-sidebar-accent",
                    )}
                  >
                    <span
                      className={cn(
                        "flex size-4 shrink-0 items-center justify-center rounded-full",
                        active ? "bg-primary/20" : "bg-muted/60 group-hover/item:bg-muted",
                      )}
                    >
                      {active && <Check className="text-primary size-2.5" />}
                    </span>
                    <span className="flex-1 truncate font-medium leading-none">{title(option)}</span>
                    {role && (
                      <span
                        className={cn(
                          "shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium capitalize",
                          active ? "bg-primary/10 text-primary" : "bg-muted text-muted-foreground",
                        )}
                      >
                        {role}
                      </span>
                    )}
                  </DropdownMenuItem>
                )
              })}
            </div>
          ))}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
