"use client"

import { BadgeCheck, GraduationCap, MapPin, Search, Star, Zap } from "lucide-react"
import Link from "next/link"
import { useCallback, useEffect, useRef, useState } from "react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { PersonAvatar } from "@/components/ui/person-avatar"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn, formatETB } from "@/lib/utils"

export interface PublicTutor {
  slug: string
  name: string | null
  avatar_url: string | null
  headline: string | null
  hourly_rate: string
  mode: string
  city: string | null
  sub_city: string | null
  languages: string[]
  experience_years: number | null
  rating_avg: string | null
  rating_count: number
  hours_taught: string
  students_count: number
  boosted: boolean
  verified: boolean
  subjects: { subject_id: number; name: string | null; grade_sorts: number[] }[]
}

interface Meta {
  subjects: { id: number; name: string }[]
  cities: string[]
}

interface Paginated {
  data: PublicTutor[]
  next_page_url: string | null
  current_page: number
}

const ALL = "__all__"

/**
 * The Upwork-style public tutor storefront: search + filters over approved
 * tutors, boosted-first ranking, app-like cards. Works logged-out — hiring
 * routes through the app.
 */
export function PublicTutorDirectory() {
  const { t } = useTranslation("tutoring")

  const [meta, setMeta] = useState<Meta | null>(null)
  const [tutors, setTutors] = useState<PublicTutor[]>([])
  const [loading, setLoading] = useState(true)
  const [loadingMore, setLoadingMore] = useState(false)
  const [nextPage, setNextPage] = useState<number | null>(null)

  const [search, setSearch] = useState("")
  const [subjectId, setSubjectId] = useState(ALL)
  const [city, setCity] = useState(ALL)
  const [mode, setMode] = useState(ALL)
  const [sort, setSort] = useState("recommended")
  const debounce = useRef<ReturnType<typeof setTimeout> | null>(null)

  const buildQuery = useCallback(
    (page: number) => {
      const params = new URLSearchParams()
      params.set("per_page", "12")
      params.set("page", String(page))
      if (search.trim()) params.set("search", search.trim())
      if (subjectId !== ALL) params.set("subject_id", subjectId)
      if (city !== ALL) params.set("city", city)
      if (mode !== ALL) params.set("mode", mode)
      if (sort !== "recommended") params.set("sort", sort)
      return params.toString()
    },
    [search, subjectId, city, mode, sort],
  )

  const load = useCallback(
    async (page: number, append: boolean) => {
      if (append) setLoadingMore(true)
      else setLoading(true)
      try {
        const res = await apiFetch<Paginated>(`/public/tutors?${buildQuery(page)}`)
        setTutors((prev) => (append ? [...prev, ...res.data] : res.data))
        setNextPage(res.next_page_url !== null ? res.current_page + 1 : null)
      } catch {
        if (!append) setTutors([])
      } finally {
        setLoading(false)
        setLoadingMore(false)
      }
    },
    [buildQuery],
  )

  useEffect(() => {
    let cancelled = false
    void (async () => {
      try {
        const res = await apiFetch<{ data: Meta }>("/public/tutors/meta")
        if (!cancelled) setMeta(res.data)
      } catch {
        // filters degrade gracefully
      }
    })()
    return () => {
      cancelled = true
    }
  }, [])

  // Debounced reload on any filter change.
  useEffect(() => {
    if (debounce.current) clearTimeout(debounce.current)
    debounce.current = setTimeout(() => void load(1, false), search ? 350 : 0)
    return () => {
      if (debounce.current) clearTimeout(debounce.current)
    }
  }, [load, search])

  return (
    <div className="mx-auto w-full max-w-6xl space-y-6 px-4 py-8 md:px-8">
      <div className="space-y-2 text-center">
        <h1 className="font-display text-3xl font-semibold tracking-tight md:text-4xl">{t("dir.title")}</h1>
        <p className="mx-auto max-w-2xl text-muted-foreground">{t("dir.subtitle")}</p>
      </div>

      {/* Toolbar */}
      <div className="flex flex-col gap-2 md:flex-row md:items-center">
        <div className="relative flex-1">
          <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" strokeWidth={2} />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("dir.searchPlaceholder")}
            className="h-12 rounded-full pl-10"
          />
        </div>
        <div className="flex flex-wrap gap-2">
          <Select value={subjectId} onValueChange={setSubjectId}>
            <SelectTrigger className="rounded-full">
              <SelectValue placeholder={t("dir.subject")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>{t("dir.allSubjects")}</SelectItem>
              {(meta?.subjects ?? []).map((subject) => (
                <SelectItem key={subject.id} value={String(subject.id)}>
                  {subject.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={city} onValueChange={setCity}>
            <SelectTrigger className="rounded-full">
              <SelectValue placeholder={t("dir.city")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>{t("dir.allCities")}</SelectItem>
              {(meta?.cities ?? []).map((option) => (
                <SelectItem key={option} value={option}>
                  {option}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select value={mode} onValueChange={setMode}>
            <SelectTrigger className="rounded-full">
              <SelectValue placeholder={t("dir.modeLabel")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>{t("dir.anyMode")}</SelectItem>
              <SelectItem value="online">{t("mode.online")}</SelectItem>
              <SelectItem value="in_person">{t("mode.in_person")}</SelectItem>
            </SelectContent>
          </Select>
          <Select value={sort} onValueChange={setSort}>
            <SelectTrigger className="rounded-full">
              <SelectValue placeholder={t("dir.sort")} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="recommended">{t("dir.sortRecommended")}</SelectItem>
              <SelectItem value="rating">{t("dir.sortRating")}</SelectItem>
              <SelectItem value="price_low">{t("dir.sortPriceLow")}</SelectItem>
              <SelectItem value="price_high">{t("dir.sortPriceHigh")}</SelectItem>
              <SelectItem value="experience">{t("dir.sortExperience")}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Results */}
      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {[0, 1, 2, 3, 4, 5].map((i) => (
            <Skeleton key={i} className="h-56 rounded-2xl" />
          ))}
        </div>
      ) : tutors.length === 0 ? (
        <EmptyState icon={GraduationCap} title={t("dir.empty")} description={t("dir.emptyDesc")} />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {tutors.map((tutor) => (
              <TutorCard key={tutor.slug} tutor={tutor} />
            ))}
          </div>
          {nextPage !== null && (
            <div className="flex justify-center">
              <Button variant="outline" loading={loadingMore} onClick={() => void load(nextPage, true)}>
                {t("dir.loadMore")}
              </Button>
            </div>
          )}
        </>
      )}

      {/* Become a tutor */}
      <div className="rounded-2xl border bg-card p-6 text-center shadow-xs md:p-8">
        <h2 className="font-display text-xl font-semibold">{t("dir.becomeTutor")}</h2>
        <p className="mx-auto mt-1 max-w-xl text-sm text-muted-foreground">{t("dir.becomeTutorDesc")}</p>
        <Button asChild className="mt-4">
          <Link href="/tutoring/apply">{t("dir.applyNow")}</Link>
        </Button>
      </div>
    </div>
  )
}

export function TutorCard({ tutor }: { tutor: PublicTutor }) {
  const { t } = useTranslation("tutoring")

  return (
    <Link
      href={`/tutors/${tutor.slug}`}
      className={cn(
        "pressable group flex flex-col gap-3 rounded-2xl border bg-card p-5 shadow-xs transition-all hover:border-primary/30 hover:shadow-sm",
        tutor.boosted && "border-primary/40",
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="flex min-w-0 items-center gap-3">
          <PersonAvatar className="size-12" photoUrl={tutor.avatar_url} name={tutor.name ?? "?"} />
          <div className="min-w-0">
            <p className="truncate font-medium">{tutor.name}</p>
            <p className="flex items-center gap-1 text-xs text-muted-foreground">
              <BadgeCheck className="size-3.5 text-primary" strokeWidth={2} />
              {t("dir.verified")}
            </p>
          </div>
        </div>
        {tutor.boosted && (
          <Badge className="shrink-0 gap-1 bg-primary/10 text-primary hover:bg-primary/10">
            <Zap className="size-3" strokeWidth={2} />
            {t("dir.featured")}
          </Badge>
        )}
      </div>

      {tutor.headline && <p className="line-clamp-2 text-sm text-muted-foreground">{tutor.headline}</p>}

      <div className="flex flex-wrap gap-1.5">
        {tutor.subjects.slice(0, 3).map((subject) => (
          <Badge key={subject.subject_id} variant="secondary" className="text-xs">
            {subject.name}
          </Badge>
        ))}
        {tutor.subjects.length > 3 && (
          <Badge variant="secondary" className="text-xs">
            +{tutor.subjects.length - 3}
          </Badge>
        )}
      </div>

      <div className="mt-auto flex items-center justify-between border-t pt-3">
        <div className="flex items-center gap-3 text-xs text-muted-foreground">
          <span className="flex items-center gap-1">
            <Star className="size-3.5 fill-warning text-warning" strokeWidth={0} />
            {tutor.rating_avg !== null
              ? `${Number(tutor.rating_avg).toFixed(1)} (${tutor.rating_count})`
              : t("dir.noReviews")}
          </span>
          {tutor.city && (
            <span className="flex items-center gap-1">
              <MapPin className="size-3.5" strokeWidth={2} />
              {tutor.city}
            </span>
          )}
        </div>
        <p className="font-mono text-sm font-semibold tabular-nums">
          {formatETB(tutor.hourly_rate)}
          <span className="text-xs font-normal text-muted-foreground">/hr</span>
        </p>
      </div>
    </Link>
  )
}
