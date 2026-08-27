"use client"

import {
  BookOpen,
  Boxes,
  Tag,
  Briefcase,
  ClipboardList,
  Clock,
  FileQuestion,
  FolderOpen,
  GraduationCap,
  HandCoins,
  Landmark,
  LayoutGrid,
  Library,
  Receipt,
  Search,
  UserRound,
  Users,
  MessagesSquare,
} from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useRef, useState } from "react"

import {
  CommandDialog,
  CommandEmpty,
  CommandFooter,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandKbd,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from "@/components/ui/command"
import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

import { hasStaffMembership, isRelationshipOnly, visibleSections } from "./nav-config"
import { useWorkspaceSurface } from "./use-workspace-surface"

const OPEN_EVENT = "temari:global-search"

/** Any trigger (sidebar pill, mobile icon, ⌘K) opens the one palette. */
export function openGlobalSearch() {
  window.dispatchEvent(new Event(OPEN_EVENT))
}

interface SearchHit {
  id: number
  label: string
  sublabel: string | null
  /** Invoice hits carry their student so the palette can land on the fees tab. */
  student_id?: number | null
}

type SearchGroup =
  | "students"
  | "employees"
  | "parents"
  | "users"
  | "sections"
  | "invoices"
  | "payments"
  | "accounts"
  | "exams"
  | "lms_assignments"
  | "courses"
  | "materials"
  | "question_banks"
  | "inventory_items"
  | "assets"
  | "conversations"

type SearchGroups = Partial<Record<SearchGroup, SearchHit[]>>

const GROUP_META = {
  students: { icon: GraduationCap, href: (hit: SearchHit) => `/students/${hit.id}` },
  employees: { icon: Briefcase, href: (hit: SearchHit) => `/employees/${hit.id}` },
  parents: { icon: UserRound, href: (hit: SearchHit) => `/parents?q=${encodeURIComponent(hit.label)}` },
  users: { icon: Users, href: (hit: SearchHit) => `/users?q=${encodeURIComponent(hit.label)}` },
  sections: { icon: LayoutGrid, href: () => "/sections" },
  invoices: {
    icon: Receipt,
    href: (hit: SearchHit) =>
      hit.student_id ? `/students/${hit.student_id}?tab=fees` : "/invoices",
  },
  payments: {
    icon: HandCoins,
    href: (hit: SearchHit) =>
      hit.student_id ? `/students/${hit.student_id}?tab=fees` : "/invoices",
  },
  accounts: { icon: Landmark, href: (hit: SearchHit) => `/payment-accounts/${hit.id}` },
  exams: { icon: ClipboardList, href: (hit: SearchHit) => `/lms/exams/${hit.id}` },
  lms_assignments: { icon: FileQuestion, href: (hit: SearchHit) => `/lms/assignments/${hit.id}` },
  courses: { icon: BookOpen, href: (hit: SearchHit) => `/lms/courses/${hit.id}` },
  materials: { icon: FolderOpen, href: (hit: SearchHit) => `/lms/materials?q=${encodeURIComponent(hit.label)}` },
  question_banks: { icon: Library, href: (hit: SearchHit) => `/lms/question-banks/${hit.id}` },
  inventory_items: { icon: Boxes, href: (hit: SearchHit) => `/inventory?tab=items&q=${encodeURIComponent(hit.label)}` },
  // The tag is the first token of the label ("X7K2QF · Projector") — the
  // inventory page opens that unit's sheet directly from the param.
  assets: { icon: Tag, href: (hit: SearchHit) => `/inventory?tab=items&asset_tag=${encodeURIComponent(hit.label.split(" · ")[0])}` },
  conversations: { icon: MessagesSquare, href: (hit: SearchHit) => `/messages?c=${hit.id}` },
} as const

/** Last places the user jumped to from the palette (shown before typing). */
interface RecentHit {
  group: SearchGroup
  label: string
  sublabel: string | null
  href: string
}

const RECENTS_KEY = "temari:search-recents"
const RECENTS_MAX = 6

function readRecents(): RecentHit[] {
  try {
    return JSON.parse(window.localStorage.getItem(RECENTS_KEY) ?? "[]") as RecentHit[]
  } catch {
    return []
  }
}

function pushRecent(entry: RecentHit) {
  const next = [entry, ...readRecents().filter((r) => r.href !== entry.href)].slice(0, RECENTS_MAX)
  window.localStorage.setItem(RECENTS_KEY, JSON.stringify(next))
}

/**
 * The ⌘K palette: page shortcuts (permission-aware, same catalog as the nav)
 * plus a live people/section search across the active workspace. Mount once
 * in the shell; open from any trigger via `openGlobalSearch()`.
 */
export function GlobalSearch() {
  const { t } = useTranslation("common")
  const { user } = useAuth()
  const permissions = useEffectivePermissions()
  const surface = useWorkspaceSurface()
  const router = useRouter()

  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState("")
  const [results, setResults] = useState<SearchGroups>({})
  const [searching, setSearching] = useState(false)
  const [recents, setRecents] = useState<RecentHit[]>([])
  const debounce = useRef<ReturnType<typeof setTimeout> | null>(null)
  const inFlight = useRef<AbortController | null>(null)

  // ⌘K / Ctrl+K + the shared open event.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === "k" && (e.metaKey || e.ctrlKey)) {
        e.preventDefault()
        setOpen((prev) => !prev)
      }
    }
    function onOpen() {
      setOpen(true)
    }
    window.addEventListener("keydown", onKey)
    window.addEventListener(OPEN_EVENT, onOpen)
    return () => {
      window.removeEventListener("keydown", onKey)
      window.removeEventListener(OPEN_EVENT, onOpen)
    }
  }, [])

  useEffect(() => {
    if (!open) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset transient palette state on close
      setQuery("")
      setResults({})
      inFlight.current?.abort()
    } else {
      setRecents(readRecents())
    }
  }, [open])

  // Record search + recents are STAFF-workspace features: the /search
  // endpoint serves staff-scoped records (students, employees, invoices…),
  // which must never surface inside the family/tutoring workspace — there
  // the palette offers that workspace's page shortcuts only.
  const staffSearch = surface === "staff" && hasStaffMembership(user)

  // Debounced fan-out search (≥ 2 characters). Superseded requests are
  // aborted so a slow early response can never overwrite a newer one; stale
  // results stay on screen while the next batch loads (no flicker).
  useEffect(() => {
    if (debounce.current) clearTimeout(debounce.current)
    const trimmed = query.trim()
    if (!staffSearch || trimmed.length < 2) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- clear stale results below the threshold
      setResults({})
      setSearching(false)
      inFlight.current?.abort()
      return
    }

    setSearching(true)
    debounce.current = setTimeout(() => {
      inFlight.current?.abort()
      const controller = new AbortController()
      inFlight.current = controller
      apiFetch<{ data: SearchGroups }>(`/search?query=${encodeURIComponent(trimmed)}`, {
        signal: controller.signal,
      })
        .then((res) => {
          setResults(res.data)
          setSearching(false)
        })
        .catch(() => {
          if (controller.signal.aborted) return
          setResults({})
          setSearching(false)
        })
    }, 250)
    return () => {
      if (debounce.current) clearTimeout(debounce.current)
    }
  }, [query, staffSearch])

  // Page shortcuts: the nav catalog of the ACTIVE workspace surface only —
  // the palette mirrors the sidebar, so a dual-hat director never sees the
  // family lane's pages from the school workspace. Deduped by href.
  const shortcuts = useMemo(() => {
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
    const seen = new Set<string>()
    const items = sections
      .flatMap((section) => section.items)
      .filter((item) => !seen.has(item.href) && (seen.add(item.href) || true))
    const needle = query.trim().toLowerCase()
    return items
      .filter((item) => !needle || t(`nav.${item.key}`).toLowerCase().includes(needle))
      .slice(0, needle ? 5 : 7)
  }, [permissions, user, surface, query, t])

  function go(href: string) {
    setOpen(false)
    router.push(href)
  }

  function goHit(group: SearchGroup, hit: SearchHit) {
    const href = GROUP_META[group].href(hit)
    pushRecent({ group, label: hit.label, sublabel: hit.sublabel, href })
    go(href)
  }

  const hasResults = Object.values(results).some((rows) => (rows?.length ?? 0) > 0)
  const idle = query.trim().length < 2

  return (
    <CommandDialog open={open} onOpenChange={setOpen} title={t("search.title")}>
      <CommandInput
        value={query}
        onValueChange={setQuery}
        placeholder={t("search.placeholder")}
        loading={searching}
        onClose={() => setOpen(false)}
        closeLabel={t("search.footer.close")}
        autoFocus
      />
      <CommandList>
        {query.trim().length >= 2 && !searching && !hasResults && shortcuts.length === 0 && (
          <CommandEmpty>{t("search.empty")}</CommandEmpty>
        )}

        {idle && staffSearch && recents.length > 0 && (
          <CommandGroup heading={t("search.recent")}>
            {recents.map((recent) => {
              const Icon = GROUP_META[recent.group]?.icon ?? Clock
              return (
                <CommandItem
                  key={`recent-${recent.href}`}
                  value={`recent-${recent.href}`}
                  onSelect={() => go(recent.href)}
                >
                  <Icon strokeWidth={1.75} />
                  <span className="min-w-0 flex-1 truncate">{recent.label}</span>
                  {recent.sublabel && <CommandShortcut>{recent.sublabel}</CommandShortcut>}
                </CommandItem>
              )
            })}
          </CommandGroup>
        )}

        {shortcuts.length > 0 && (
          <CommandGroup heading={t("search.pages")}>
            {shortcuts.map((item) => (
              <CommandItem key={item.key} value={`page-${item.key}`} onSelect={() => go(item.href)}>
                <item.icon strokeWidth={1.75} />
                {t(`nav.${item.key}`)}
              </CommandItem>
            ))}
          </CommandGroup>
        )}

        {shortcuts.length > 0 && hasResults && <CommandSeparator />}

        {(Object.keys(GROUP_META) as SearchGroup[]).map((group) => {
          const rows = results[group]
          if (!rows || rows.length === 0) return null
          const meta = GROUP_META[group]
          return (
            <CommandGroup key={group} heading={t(`search.groups.${group}`)}>
              {rows.map((hit) => (
                <CommandItem
                  key={`${group}-${hit.id}`}
                  value={`${group}-${hit.id}`}
                  onSelect={() => goHit(group, hit)}
                >
                  <meta.icon strokeWidth={1.75} />
                  <span className="min-w-0 flex-1 truncate">{hit.label}</span>
                  {hit.sublabel && <CommandShortcut>{hit.sublabel}</CommandShortcut>}
                </CommandItem>
              ))}
            </CommandGroup>
          )
        })}
      </CommandList>
      <CommandFooter>
        <span className="flex items-center gap-1">
          <CommandKbd>↑</CommandKbd>
          <CommandKbd>↓</CommandKbd>
          {t("search.footer.navigate")}
        </span>
        <span className="flex items-center gap-1">
          <CommandKbd>↵</CommandKbd>
          {t("search.footer.open")}
        </span>
        <span className="flex items-center gap-1">
          <CommandKbd>esc</CommandKbd>
          {t("search.footer.close")}
        </span>
      </CommandFooter>
    </CommandDialog>
  )
}

/** Sidebar / header buttons that open the palette. */
export function GlobalSearchTrigger({
  variant = "pill",
  className,
}: {
  variant?: "pill" | "icon"
  className?: string
}) {
  const { t } = useTranslation("common")

  if (variant === "icon") {
    return (
      <button
        type="button"
        onClick={openGlobalSearch}
        className={cn(
          "pressable touch-target flex size-10 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-accent hover:text-foreground",
          className,
        )}
        aria-label={t("search.title")}
      >
        <Search className="size-4.5" />
      </button>
    )
  }

  return (
    <button
      type="button"
      onClick={openGlobalSearch}
      className={cn(
        "pressable flex h-9 w-full items-center gap-2.5 rounded-lg border border-sidebar-border/80 bg-sidebar-accent/40 px-2.5 text-sm text-muted-foreground transition-colors hover:bg-sidebar-accent/70",
        className,
      )}
    >
      <Search className="size-4 shrink-0 opacity-70" />
      <span className="min-w-0 flex-1 truncate text-left text-[13px]">
        {t("search.title")}
      </span>
      <kbd className="hidden shrink-0 rounded-md border bg-background px-1.5 py-0.5 font-sans text-[10px] text-muted-foreground md:inline">
        ⌘K
      </kbd>
    </button>
  )
}
