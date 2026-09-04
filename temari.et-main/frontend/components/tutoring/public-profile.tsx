"use client"

import { BadgeCheck, Clock, GraduationCap, Languages, MapPin, Star, Users, Zap } from "lucide-react"
import Link from "next/link"
import { useRouter } from "next/navigation"
import { useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { PersonAvatar } from "@/components/ui/person-avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { EmptyState } from "@/components/ui/empty-state"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
  ResponsiveSheet,
  ResponsiveSheetBody,
  ResponsiveSheetContent,
  ResponsiveSheetFooter,
  ResponsiveSheetHeader,
  ResponsiveSheetTitle,
} from "@/components/ui/responsive-sheet"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch, getToken } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import { cn, formatETB } from "@/lib/utils"

const TEXTAREA_CLASS =
  "w-full resize-none rounded-xl border border-input/70 bg-muted/30 px-3.5 py-2.5 text-base outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 md:text-sm"

interface Review {
  rating: number
  comment: string | null
  reviewer: string | null
  created_at: string | null
}

interface PublicProfile {
  slug: string
  name: string | null
  avatar_url: string | null
  headline: string | null
  bio: string | null
  video_url: string | null
  hourly_rate: string
  additional_child_rate: string | null
  mode: string
  city: string | null
  sub_city: string | null
  languages: string[]
  education_level: string | null
  experience_years: number | null
  rating_avg: string | null
  rating_count: number
  hours_taught: string
  students_count: number
  boosted: boolean
  subjects: { subject_id: number; name: string | null; grade_sorts: number[] }[]
  reviews: Review[]
}

interface Child {
  student_id: number
  full_name: string | null
}

/** One tutor's public storefront + the authenticated hire-request sheet. */
export function PublicTutorProfile({ slug }: { slug: string }) {
  const { t } = useTranslation("tutoring")
  const { t: tc } = useTranslation("common")
  const router = useRouter()

  const [loading, setLoading] = useState(true)
  const [profile, setProfile] = useState<PublicProfile | null>(null)

  // Request sheet.
  const [requestOpen, setRequestOpen] = useState(false)
  const [children, setChildren] = useState<Child[] | null>(null)
  const [learner, setLearner] = useState("self")
  const [subjectIds, setSubjectIds] = useState<number[]>([])
  const [gradeLabel, setGradeLabel] = useState("")
  const [message, setMessage] = useState("")
  const [mode, setMode] = useState("online")
  const [sessionsPerWeek, setSessionsPerWeek] = useState("2")
  const [hoursPerSession, setHoursPerSession] = useState("1")
  const [sending, setSending] = useState(false)

  useEffect(() => {
    let cancelled = false
    void (async () => {
      try {
        const res = await apiFetch<{ data: PublicProfile }>(`/public/tutors/${slug}`)
        if (!cancelled) {
          setProfile(res.data)
          setMode(res.data.mode === "in_person" ? "in_person" : "online")
        }
      } catch {
        if (!cancelled) setProfile(null)
      } finally {
        if (!cancelled) setLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [slug])

  async function openRequest() {
    if (!getToken()) {
      router.push(`/login?next=/tutors/${slug}`)
      return
    }
    setRequestOpen(true)
    if (children === null) {
      try {
        const res = await apiFetch<{ data: Child[] }>("/me/children")
        setChildren(res.data)
      } catch {
        setChildren([])
      }
    }
  }

  const estimate = useMemo(() => {
    if (!profile) return 0
    return Math.round(Number(sessionsPerWeek) * Number(hoursPerSession) * 4 * Number(profile.hourly_rate))
  }, [profile, sessionsPerWeek, hoursPerSession])

  async function sendRequest() {
    setSending(true)
    try {
      await apiFetch("/me/tutoring/requests", {
        method: "POST",
        body: JSON.stringify({
          tutor_slug: slug,
          student_id: learner === "self" ? null : Number(learner),
          subject_ids: subjectIds,
          grade_label: gradeLabel || null,
          message: message || null,
          mode,
          sessions_per_week: Number(sessionsPerWeek),
          hours_per_session: Number(hoursPerSession),
        }),
      })
      toast.success(t("request.sent"))
      setRequestOpen(false)
      router.push("/me/tutoring")
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : "")
    } finally {
      setSending(false)
    }
  }

  if (loading) {
    return (
      <div className="mx-auto w-full max-w-4xl space-y-4 px-4 py-8 md:px-8">
        <Skeleton className="h-40 rounded-2xl" />
        <Skeleton className="h-64 rounded-2xl" />
      </div>
    )
  }

  if (!profile) {
    return (
      <div className="mx-auto w-full max-w-4xl px-4 py-16 md:px-8">
        <EmptyState
          icon={GraduationCap}
          title={t("profile.notFound")}
          description=""
          action={
            <Button asChild variant="outline">
              <Link href="/tutors">{t("dir.title")}</Link>
            </Button>
          }
        />
      </div>
    )
  }

  return (
    <div className="mx-auto w-full max-w-4xl space-y-6 px-4 py-8 md:px-8">
      {/* Hero */}
      <div className="flex flex-col gap-4 rounded-2xl border bg-card p-6 shadow-xs md:flex-row md:items-center">
        <PersonAvatar className="size-20 md:size-24" photoUrl={profile.avatar_url} name={profile.name ?? "?"} />
        <div className="min-w-0 flex-1 space-y-1.5">
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="font-display text-2xl font-semibold tracking-tight">{profile.name}</h1>
            <Badge className="gap-1 bg-primary/10 text-primary hover:bg-primary/10">
              <BadgeCheck className="size-3.5" strokeWidth={2} />
              {t("dir.verified")}
            </Badge>
            {profile.boosted && (
              <Badge className="gap-1 bg-warning/10 text-warning hover:bg-warning/10">
                <Zap className="size-3.5" strokeWidth={2} />
                {t("dir.featured")}
              </Badge>
            )}
          </div>
          {profile.headline && <p className="text-muted-foreground">{profile.headline}</p>}
          <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
            <span className="flex items-center gap-1">
              <Star className="size-4 fill-warning text-warning" strokeWidth={0} />
              {profile.rating_avg !== null
                ? `${Number(profile.rating_avg).toFixed(1)} · ${t("dir.reviewsCount", { count: profile.rating_count })}`
                : t("dir.noReviews")}
            </span>
            <span className="flex items-center gap-1">
              <Clock className="size-4" strokeWidth={2} />
              {t("dir.hoursTaught", { hours: profile.hours_taught })}
            </span>
            <span className="flex items-center gap-1">
              <Users className="size-4" strokeWidth={2} />
              {t("dir.studentsCount", { count: profile.students_count })}
            </span>
            {profile.city && (
              <span className="flex items-center gap-1">
                <MapPin className="size-4" strokeWidth={2} />
                {[profile.city, profile.sub_city].filter(Boolean).join(", ")}
              </span>
            )}
          </div>
        </div>
        <div className="shrink-0 space-y-2 text-center md:text-right">
          <p className="font-mono text-2xl font-semibold tabular-nums">
            {formatETB(profile.hourly_rate)}
            <span className="text-sm font-normal text-muted-foreground">/hr</span>
          </p>
          {profile.additional_child_rate && (
            <p className="text-xs text-muted-foreground">
              {t("profile.additionalChild")}: {formatETB(profile.additional_child_rate)}
            </p>
          )}
          <Button className="w-full md:w-auto" onClick={openRequest}>
            {t("profile.requestTutor")}
          </Button>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <div className="space-y-6 md:col-span-2">
          {profile.bio && (
            <section className="space-y-2 rounded-2xl border bg-card p-5 shadow-xs">
              <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {t("profile.about")}
              </h2>
              <p className="text-sm leading-relaxed whitespace-pre-line">{profile.bio}</p>
            </section>
          )}

          <section className="space-y-3 rounded-2xl border bg-card p-5 shadow-xs">
            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("profile.reviews")}
            </h2>
            {profile.reviews.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t("dir.noReviews")}</p>
            ) : (
              profile.reviews.map((review, index) => (
                <div key={index} className={cn("space-y-1", index > 0 && "border-t pt-3")}>
                  <div className="flex items-center gap-2">
                    <span className="flex">
                      {[1, 2, 3, 4, 5].map((value) => (
                        <Star
                          key={value}
                          className={cn(
                            "size-3.5",
                            value <= review.rating ? "fill-warning text-warning" : "text-muted-foreground/30",
                          )}
                          strokeWidth={0}
                        />
                      ))}
                    </span>
                    <span className="text-xs text-muted-foreground">
                      {review.reviewer} · {review.created_at}
                    </span>
                  </div>
                  {review.comment && <p className="text-sm">{review.comment}</p>}
                </div>
              ))
            )}
          </section>
        </div>

        <div className="space-y-6">
          <section className="space-y-3 rounded-2xl border bg-card p-5 shadow-xs">
            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("profile.subjects")}
            </h2>
            <div className="flex flex-wrap gap-1.5">
              {profile.subjects.map((subject) => (
                <Badge key={subject.subject_id} variant="secondary">
                  {subject.name}
                </Badge>
              ))}
            </div>
          </section>

          <section className="space-y-3 rounded-2xl border bg-card p-5 shadow-xs text-sm">
            <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {t("profile.education")}
            </h2>
            {profile.education_level && <p>{profile.education_level}</p>}
            {profile.experience_years !== null && (
              <p className="text-muted-foreground">
                {t("profile.experience", { years: profile.experience_years })}
              </p>
            )}
            <div className="flex items-center gap-1.5 text-muted-foreground">
              <Languages className="size-4" strokeWidth={2} />
              {profile.languages.join(" · ").toUpperCase()}
            </div>
            <Badge variant="outline">{t(`mode.${profile.mode}`)}</Badge>
          </section>
        </div>
      </div>

      {/* Hire request sheet */}
      <ResponsiveSheet open={requestOpen} onOpenChange={(open) => !open && !sending && setRequestOpen(false)}>
        <ResponsiveSheetContent>
          <ResponsiveSheetHeader>
            <ResponsiveSheetTitle>{t("request.title")}</ResponsiveSheetTitle>
          </ResponsiveSheetHeader>
          <ResponsiveSheetBody className="space-y-4">
            <div className="space-y-2">
              <Label>{t("request.whoFor")}</Label>
              <Select value={learner} onValueChange={setLearner}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="self">{t("request.myself")}</SelectItem>
                  {(children ?? []).map((child) => (
                    <SelectItem key={child.student_id} value={String(child.student_id)}>
                      {child.full_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label>{t("request.subjects")}</Label>
              <div className="flex flex-wrap gap-1.5">
                {profile.subjects.map((subject) => {
                  const active = subjectIds.includes(subject.subject_id)
                  return (
                    <button
                      key={subject.subject_id}
                      type="button"
                      onClick={() =>
                        setSubjectIds((prev) =>
                          active ? prev.filter((id) => id !== subject.subject_id) : [...prev, subject.subject_id],
                        )
                      }
                      className={cn(
                        "touch-target rounded-full border px-3 py-1.5 text-sm font-medium transition-colors",
                        active
                          ? "border-primary bg-primary/10 text-primary"
                          : "bg-muted/30 text-muted-foreground hover:bg-accent/50",
                      )}
                    >
                      {subject.name}
                    </button>
                  )
                })}
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label htmlFor="req-grade">{t("request.gradeLabel")}</Label>
                <Input id="req-grade" value={gradeLabel} onChange={(e) => setGradeLabel(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>{t("dir.modeLabel")}</Label>
                <Select value={mode} onValueChange={setMode}>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {(profile.mode === "online" || profile.mode === "both") && (
                      <SelectItem value="online">{t("mode.online")}</SelectItem>
                    )}
                    {(profile.mode === "in_person" || profile.mode === "both") && (
                      <SelectItem value="in_person">{t("mode.in_person")}</SelectItem>
                    )}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="req-sessions">{t("request.sessionsPerWeek")}</Label>
                <Input
                  id="req-sessions"
                  type="number"
                  min={1}
                  max={7}
                  className="no-spinner"
                  value={sessionsPerWeek}
                  onChange={(e) => setSessionsPerWeek(e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="req-hours">{t("request.hoursPerSession")}</Label>
                <Input
                  id="req-hours"
                  type="number"
                  min={0.5}
                  max={4}
                  step={0.5}
                  className="no-spinner"
                  value={hoursPerSession}
                  onChange={(e) => setHoursPerSession(e.target.value)}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="req-message">{t("request.message")}</Label>
              <textarea
                id="req-message"
                rows={3}
                className={TEXTAREA_CLASS}
                placeholder={t("request.messagePlaceholder")}
                value={message}
                onChange={(e) => setMessage(e.target.value)}
              />
            </div>

            <div className="rounded-2xl border bg-muted/30 p-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{t("request.monthlyEstimate")}</span>
                <span className="font-mono text-lg font-semibold tabular-nums">{formatETB(estimate)}</span>
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                {t("request.monthlyEstimateHint", {
                  sessions: sessionsPerWeek,
                  hours: hoursPerSession,
                  rate: profile.hourly_rate,
                })}
              </p>
            </div>
          </ResponsiveSheetBody>
          <ResponsiveSheetFooter>
            <Button variant="outline" className="h-11 flex-1" disabled={sending} onClick={() => setRequestOpen(false)}>
              {tc("actions.cancel")}
            </Button>
            <Button
              className="h-11 flex-1"
              loading={sending}
              disabled={subjectIds.length === 0}
              onClick={sendRequest}
            >
              {t("request.send")}
            </Button>
          </ResponsiveSheetFooter>
        </ResponsiveSheetContent>
      </ResponsiveSheet>
    </div>
  )
}
