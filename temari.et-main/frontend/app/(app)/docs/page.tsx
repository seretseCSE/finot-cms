"use client"

import { useMemo, useRef, useState } from "react"
import Link from "next/link"
import { ArrowLeft, ArrowUpRight, ChevronDown, ChevronRight, Lightbulb, Rocket, Search } from "lucide-react"

import {
  visibleDocSections,
  quickstartForRoles,
  type DocArticle,
  type DocSection,
  type Quickstart,
} from "@/components/docs/content"
import { useWorkspaceSurface } from "@/components/app-shell/use-workspace-surface"
import { DocFigure } from "@/components/docs/doc-figures"
import { EmptyState } from "@/components/ui/empty-state"
import { PageHeader } from "@/components/ui/page-header"
import { Button } from "@/components/ui/button"
import { useAuth } from "@/lib/auth/auth-context"
import { useSchoolContext } from "@/lib/auth/school-context"
import { useEffectivePermissions } from "@/lib/auth/use-effective-permissions"
import { useTranslation } from "@/lib/i18n"
import { cn } from "@/lib/utils"

/** The quick start matching the user's role in the ACTIVE workspace. */
function useQuickstart(): Quickstart {
  const { user } = useAuth()
  const { active, isPlatform } = useSchoolContext()
  const surface = useWorkspaceSurface()

  return useMemo(() => {
    if (!user) return quickstartForRoles([], false)
    // Personal workspaces (family/tutoring) get the default quick start —
    // never a staff role's onboarding steps.
    if (surface !== "staff") return quickstartForRoles([], false)
    if (isPlatform) return quickstartForRoles([], true)

    const roles: string[] = []
    for (const m of user.memberships ?? []) {
      if (!m.is_active) continue
      const appliesToBranch = m.branch_id !== null && m.branch_id === active.branchId
      const appliesToSchool =
        m.branch_id === null && m.school_id !== null && m.school_id === active.schoolId
      if (appliesToBranch || appliesToSchool) roles.push(m.role)
    }
    // Before a concrete context resolves, fall back to the global role union.
    if (roles.length === 0) roles.push(...user.roles)

    return quickstartForRoles(roles, false)
  }, [user, active, isPlatform, surface])
}

interface SearchHit {
  section: DocSection
  article: DocArticle
  title: string
  summary: string
}

export default function DocsPage() {
  const { t } = useTranslation("docs")
  const permissions = useEffectivePermissions()
  const quickstart = useQuickstart()
  const { user } = useAuth()
  const surface = useWorkspaceSurface()

  const sections = useMemo(
    () =>
      visibleDocSections(
        permissions,
        {
          isParent: user?.is_parent,
          isStudent: user?.is_student,
          isTutor: user?.is_tutor,
        },
        surface,
      ),
    [permissions, user?.is_parent, user?.is_student, user?.is_tutor, surface],
  )

  const [query, setQuery] = useState("")
  const [selectedKey, setSelectedKey] = useState<string | null>(null)
  const [openArticle, setOpenArticle] = useState<string | null>(null)
  const contentRef = useRef<HTMLDivElement>(null)

  // A stale key (e.g. after a workspace switch hides the section) simply
  // resolves to null and falls back to the overview.
  const selected = sections.find((s) => s.key === selectedKey) ?? null

  // Searches across every guide the user can see (title + summary + steps).
  const hits: SearchHit[] = useMemo(() => {
    const needle = query.trim().toLowerCase()
    if (!needle) return []
    const results: SearchHit[] = []
    for (const section of sections) {
      for (const article of section.articles) {
        const base = `sections.${section.key}.articles.${article.key}`
        const title = t(`${base}.title`)
        const summary = t(`${base}.summary`)
        const steps = Array.from({ length: article.steps }, (_, i) => t(`${base}.step${i + 1}`))
        const haystack = [title, summary, t(`sections.${section.key}.title`), ...steps]
          .join(" ")
          .toLowerCase()
        if (haystack.includes(needle)) results.push({ section, article, title, summary })
      }
    }
    return results
  }, [query, sections, t])

  function openGuide(sectionKey: string, articleKey: string | null) {
    setSelectedKey(sectionKey)
    setOpenArticle(articleKey)
    setQuery("")
    contentRef.current?.scrollIntoView({ block: "start", behavior: "smooth" })
  }

  const searching = query.trim().length > 0

  return (
    <div className="space-y-6">
      <PageHeader title={t("title")} description={t("subtitle")} />

      <div className="page-gutter" ref={contentRef}>
        <div className="mx-auto max-w-5xl space-y-6">
          {/* Search */}
          <div className="relative">
            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2" />
            <input
              type="search"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={t("search.placeholder")}
              className={cn(
                "bg-card h-12 w-full rounded-full border pr-4 pl-11 text-base shadow-xs outline-none md:text-sm",
                "focus-visible:border-ring focus-visible:ring-ring/50 transition-shadow focus-visible:ring-[3px]",
              )}
            />
          </div>

          {searching ? (
            <SearchResults hits={hits} onOpen={openGuide} />
          ) : (
            <div className="lg:grid lg:grid-cols-[230px_minmax(0,1fr)] lg:items-start lg:gap-8">
              {/* Desktop rail */}
              <nav className="sticky top-20 hidden space-y-0.5 lg:block">
                <RailItem
                  active={!selected}
                  onClick={() => {
                    setSelectedKey(null)
                    setOpenArticle(null)
                  }}
                >
                  <Rocket className="size-4" strokeWidth={2} />
                  {t("quickstart.heading")}
                </RailItem>
                {sections.map((section) => (
                  <RailItem
                    key={section.key}
                    active={selected?.key === section.key}
                    onClick={() => openGuide(section.key, null)}
                  >
                    <section.icon className="size-4" strokeWidth={2} />
                    {t(`sections.${section.key}.title`)}
                  </RailItem>
                ))}
              </nav>

              <div className="min-w-0">
                {selected ? (
                  <SectionDetail
                    section={selected}
                    openArticle={openArticle}
                    onToggleArticle={(key) => setOpenArticle((cur) => (cur === key ? null : key))}
                    onBack={() => {
                      setSelectedKey(null)
                      setOpenArticle(null)
                    }}
                  />
                ) : (
                  <Overview quickstart={quickstart} sections={sections} onOpen={openGuide} />
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Overview: quick start + topic grid                                  */
/* ------------------------------------------------------------------ */

function Overview({
  quickstart,
  sections,
  onOpen,
}: {
  quickstart: Quickstart
  sections: DocSection[]
  onOpen: (sectionKey: string, articleKey: string | null) => void
}) {
  const { t } = useTranslation("docs")

  // Keep the i18n item index even when a target section is hidden by role.
  const items = quickstart.items
    .map((item, i) => ({ ...item, label: t(`quickstart.${quickstart.key}.item${i + 1}`) }))
    .filter((item) => sections.some((s) => s.key === item.section))

  return (
    <div className="space-y-6">
      {items.length > 0 && (
        <section className="bg-card rounded-2xl border p-5 shadow-xs md:p-6">
          <div className="flex items-start gap-3.5">
            <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
              <Rocket className="size-5" strokeWidth={1.75} />
            </span>
            <div className="min-w-0">
              <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                {t("quickstart.heading")}
              </p>
              <h2 className="font-display mt-0.5 text-lg font-semibold tracking-tight">
                {t(`quickstart.${quickstart.key}.title`)}
              </h2>
              <p className="text-muted-foreground mt-1 max-w-2xl text-sm">
                {t(`quickstart.${quickstart.key}.intro`)}
              </p>
            </div>
          </div>
          <ol className="mt-4 space-y-1">
            {items.map((item, i) => (
              <li key={item.section}>
                <button
                  type="button"
                  onClick={() => onOpen(item.section, null)}
                  className="hover:bg-accent pressable touch-target flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors"
                >
                  <span className="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums">
                    {i + 1}
                  </span>
                  <span className="min-w-0 flex-1 truncate text-sm font-medium">{item.label}</span>
                  <ChevronRight className="text-muted-foreground size-4 shrink-0" />
                </button>
              </li>
            ))}
          </ol>
        </section>
      )}

      <section>
        <h2 className="text-muted-foreground mb-3 text-xs font-semibold tracking-wide uppercase">
          {t("home.heading")}
        </h2>
        <div className="grid gap-3 sm:grid-cols-2">
          {sections.map((section) => (
            <button
              key={section.key}
              type="button"
              onClick={() => onOpen(section.key, null)}
              className="bg-card hover:bg-accent/50 pressable group rounded-2xl border p-4 text-left shadow-xs transition-colors"
            >
              <div className="flex items-start gap-3.5">
                <span className="bg-accent text-foreground group-hover:bg-primary/10 group-hover:text-primary flex size-10 shrink-0 items-center justify-center rounded-xl transition-colors">
                  <section.icon className="size-5" strokeWidth={1.75} />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">
                    {t(`sections.${section.key}.title`)}
                  </p>
                  <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">
                    {t(`sections.${section.key}.tagline`)}
                  </p>
                  <p className="text-muted-foreground mt-2 text-[11px] font-medium">
                    {t("home.guides", { count: section.articles.length })}
                  </p>
                </div>
                <ChevronRight className="text-muted-foreground mt-1 size-4 shrink-0" />
              </div>
            </button>
          ))}
        </div>
      </section>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/* Section detail: articles as expandable guides                       */
/* ------------------------------------------------------------------ */

function SectionDetail({
  section,
  openArticle,
  onToggleArticle,
  onBack,
}: {
  section: DocSection
  openArticle: string | null
  onToggleArticle: (key: string) => void
  onBack: () => void
}) {
  const { t } = useTranslation("docs")

  return (
    <div className="space-y-4">
      <button
        type="button"
        onClick={onBack}
        className="text-muted-foreground hover:text-foreground touch-target -ml-1 inline-flex items-center gap-1.5 rounded-full px-1 text-sm font-medium transition-colors lg:hidden"
      >
        <ArrowLeft className="size-4" />
        {t("detail.back")}
      </button>

      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-3.5">
          <span className="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
            <section.icon className="size-5" strokeWidth={1.75} />
          </span>
          <div className="min-w-0">
            <h2 className="font-display text-lg font-semibold tracking-tight">
              {t(`sections.${section.key}.title`)}
            </h2>
            <p className="text-muted-foreground text-sm">{t(`sections.${section.key}.tagline`)}</p>
          </div>
        </div>
        {section.href && (
          <Button asChild variant="outline" size="sm">
            <Link href={section.href}>
              {t("detail.openPage")}
              <ArrowUpRight className="size-3.5" />
            </Link>
          </Button>
        )}
      </div>

      <div className="space-y-3">
        {section.articles.map((article) => (
          <ArticleCard
            key={article.key}
            section={section}
            article={article}
            open={openArticle === article.key}
            onToggle={() => onToggleArticle(article.key)}
          />
        ))}
      </div>
    </div>
  )
}

function ArticleCard({
  section,
  article,
  open,
  onToggle,
}: {
  section: DocSection
  article: DocArticle
  open: boolean
  onToggle: () => void
}) {
  const { t } = useTranslation("docs")
  const base = `sections.${section.key}.articles.${article.key}`

  return (
    <article className="bg-card overflow-hidden rounded-2xl border shadow-xs">
      <button
        type="button"
        onClick={onToggle}
        aria-expanded={open}
        className="hover:bg-accent/50 touch-target flex w-full items-center gap-3 px-4 py-3.5 text-left transition-colors md:px-5"
      >
        <span className="min-w-0 flex-1">
          <span className="block truncate text-sm font-semibold">{t(`${base}.title`)}</span>
          {!open && (
            <span className="text-muted-foreground mt-0.5 line-clamp-1 block text-xs">
              {t(`${base}.summary`)}
            </span>
          )}
        </span>
        <ChevronDown
          className={cn(
            "text-muted-foreground size-4 shrink-0 transition-transform duration-200",
            open && "rotate-180",
          )}
        />
      </button>

      {open && (
        <div className="space-y-4 border-t px-4 py-4 md:px-5">
          <p className="text-muted-foreground max-w-2xl text-sm">{t(`${base}.summary`)}</p>

          <ol className="space-y-3">
            {Array.from({ length: article.steps }, (_, i) => (
              <li key={i} className="flex gap-3">
                <span className="bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums">
                  {i + 1}
                </span>
                <p className="max-w-2xl pt-0.5 text-sm">{t(`${base}.step${i + 1}`)}</p>
              </li>
            ))}
          </ol>

          {article.tip && (
            <div className="bg-info/10 flex max-w-2xl gap-2.5 rounded-xl p-3">
              <Lightbulb className="text-info mt-0.5 size-4 shrink-0" strokeWidth={2} />
              <p className="text-sm">
                <span className="text-info font-semibold">{t("detail.tip")}: </span>
                {t(`${base}.tip`)}
              </p>
            </div>
          )}

          {article.figure && (
            <div className="max-w-md">
              <DocFigure id={article.figure} />
            </div>
          )}
        </div>
      )}
    </article>
  )
}

/* ------------------------------------------------------------------ */
/* Search results                                                      */
/* ------------------------------------------------------------------ */

function SearchResults({
  hits,
  onOpen,
}: {
  hits: SearchHit[]
  onOpen: (sectionKey: string, articleKey: string) => void
}) {
  const { t } = useTranslation("docs")

  if (hits.length === 0) {
    return (
      <EmptyState icon={Search} title={t("search.empty")} description={t("search.emptyHint")} />
    )
  }

  return (
    <div className="space-y-3">
      <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
        {t("search.results", { count: hits.length })}
      </p>
      <div className="space-y-2">
        {hits.map((hit) => (
          <button
            key={`${hit.section.key}.${hit.article.key}`}
            type="button"
            onClick={() => onOpen(hit.section.key, hit.article.key)}
            className="bg-card hover:bg-accent/50 pressable flex w-full items-start gap-3.5 rounded-2xl border p-4 text-left shadow-xs transition-colors"
          >
            <span className="bg-accent flex size-9 shrink-0 items-center justify-center rounded-xl">
              <hit.section.icon className="size-4.5" strokeWidth={1.75} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-semibold">{hit.title}</span>
              <span className="text-muted-foreground mt-0.5 line-clamp-2 block text-xs">
                {hit.summary}
              </span>
              <span className="text-primary mt-1.5 block text-[11px] font-medium">
                {t(`sections.${hit.section.key}.title`)}
              </span>
            </span>
            <ChevronRight className="text-muted-foreground mt-1 size-4 shrink-0" />
          </button>
        ))}
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */

function RailItem({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-current={active ? "true" : undefined}
      className={cn(
        "flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-sm font-medium transition-colors",
        active
          ? "bg-primary/10 text-primary"
          : "text-muted-foreground hover:bg-accent hover:text-foreground",
      )}
    >
      {children}
    </button>
  )
}
