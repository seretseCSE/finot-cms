"use client"

import {
  BookOpen,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  CirclePlay,
  Download,
  FileQuestion,
  Lock,
  Paperclip,
  Play,
} from "lucide-react"
import { useParams, useRouter } from "next/navigation"
import { useCallback, useEffect, useMemo, useState } from "react"
import { toast } from "sonner"

import { QuestionStem } from "@/components/lms/question-content"
import { formatFileSize } from "@/components/lms/shared"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { PageHeader } from "@/components/ui/page-header"
import { Skeleton } from "@/components/ui/skeleton"
import { ApiError, apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { MeCourseDetail, MeLessonContent } from "@/lib/types"

const LESSON_ICONS = {
  video: Play,
  reading: BookOpen,
  file: Paperclip,
  quiz: FileQuestion,
} as const

function youtubeId(url: string | null | undefined): string | null {
  if (!url) return null
  const match = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{6,})/)
  return match?.[1] ?? null
}

/**
 * The course player: progress header, chapter rail, one lesson at a time.
 * Mobile-first — the rail collapses and the lesson takes the screen; video
 * embeds are tap-to-load (3G), quiz lessons hand off to the exam player.
 */
export default function CoursePlayerPage() {
  const { t } = useTranslation("lms")
  const { t: tc } = useTranslation("common")
  const params = useParams<{ id: string }>()
  const router = useRouter()

  const [course, setCourse] = useState<MeCourseDetail | null>(null)
  const [activeLessonId, setActiveLessonId] = useState<number | null>(null)
  const [lesson, setLesson] = useState<MeLessonContent | null>(null)
  const [videoLoaded, setVideoLoaded] = useState(false)
  const [completing, setCompleting] = useState(false)
  const [startingQuiz, setStartingQuiz] = useState(false)

  const load = useCallback(
    (selectContinue = false) => {
      apiFetch<{ data: MeCourseDetail }>(`/me/courses/${params.id}`)
        .then((res) => {
          setCourse(res.data)
          if (selectContinue) {
            setActiveLessonId(
              res.data.continue_lesson_id ?? res.data.modules[0]?.lessons[0]?.id ?? null,
            )
          }
        })
        .catch((error) => {
          toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
          router.push("/me/courses")
        })
    },
    [params.id, router, tc],
  )

  useEffect(() => {
    load(true)
  }, [load])

  // Fetch the active lesson's content (also stamps it "started").
  useEffect(() => {
    if (activeLessonId === null) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset when nothing selected
      setLesson(null)
      return
    }
    let cancelled = false
    /* eslint-disable react-hooks/set-state-in-effect -- reset while loading */
    setLesson(null)
    setVideoLoaded(false)
    /* eslint-enable react-hooks/set-state-in-effect */
    apiFetch<{ data: MeLessonContent }>(`/me/lessons/${activeLessonId}`)
      .then((res) => !cancelled && setLesson(res.data))
      .catch((error) => {
        if (cancelled) return
        toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
      })
    return () => {
      cancelled = true
    }
  }, [activeLessonId, tc])

  const flat = useMemo(
    () => (course?.modules ?? []).flatMap((module) => module.lessons),
    [course],
  )
  const activeIndex = flat.findIndex((l) => l.id === activeLessonId)
  const activeMeta = activeIndex >= 0 ? flat[activeIndex] : null

  async function markComplete(goNext = true) {
    if (activeLessonId === null) return
    setCompleting(true)
    try {
      await apiFetch(`/me/lessons/${activeLessonId}/progress`, {
        method: "POST",
        body: { status: "completed" },
      })
      load()
      const next = flat[activeIndex + 1]
      if (goNext && next) setActiveLessonId(next.id)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setCompleting(false)
    }
  }

  async function openQuiz() {
    if (!lesson?.quiz_id) return
    setStartingQuiz(true)
    try {
      const res = await apiFetch<{ data: { attempt_id: number } }>(
        `/me/exams/${lesson.quiz_id}/start`,
        { method: "POST", body: {} },
      )
      router.push(`/me/exam/${res.data.attempt_id}`)
    } catch (error) {
      toast.error(error instanceof ApiError ? error.message : tc("errors.generic"))
    } finally {
      setStartingQuiz(false)
    }
  }

  if (course === null) {
    return (
      <div className="space-y-4 p-4 md:p-8">
        <Skeleton className="h-14 w-full rounded-2xl" />
        <Skeleton className="h-64 w-full rounded-2xl" />
      </div>
    )
  }

  const ytId = lesson?.type === "video" ? youtubeId(lesson.content.url) : null

  return (
    <div className="space-y-4">
      <PageHeader
        title={course.title}
        description={[course.subject_name].filter(Boolean).join(" · ")}
        backHref="/me/courses"
      />

      {/* progress bar */}
      <div className="space-y-1.5 px-4 md:px-8">
        <div className="h-2 overflow-hidden rounded-full bg-muted">
          <div
            className="h-full rounded-full bg-primary transition-[width]"
            style={{ width: `${course.progress_percent}%` }}
          />
        </div>
        <p className="text-xs text-muted-foreground">
          {t("courses.progressOf", { done: course.completed_count, total: course.lessons_count })} ·{" "}
          {course.progress_percent}%
        </p>
      </div>

      <div className="grid gap-4 px-4 md:grid-cols-[minmax(16rem,20rem)_1fr] md:px-8">
        {/* ── chapter rail ── */}
        <div className="order-2 space-y-3 md:order-1">
          {course.modules.map((module, moduleIndex) => (
            <div key={module.id} className="overflow-hidden rounded-2xl border">
              <p className="border-b bg-muted/40 px-3.5 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {moduleIndex + 1}. {module.title}
              </p>
              <div className="divide-y">
                {module.lessons.map((entry) => {
                  const Icon = entry.is_locked
                    ? Lock
                    : entry.status === "completed"
                      ? CheckCircle2
                      : (LESSON_ICONS[entry.type] ?? BookOpen)
                  return (
                    <button
                      key={entry.id}
                      type="button"
                      disabled={entry.is_locked}
                      onClick={() => setActiveLessonId(entry.id)}
                      className={`flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm transition-colors ${
                        entry.id === activeLessonId ? "bg-primary/5 font-medium" : "hover:bg-accent"
                      } ${entry.is_locked ? "opacity-50" : ""}`}
                    >
                      <Icon
                        className={`size-4 shrink-0 ${
                          entry.status === "completed" ? "text-success" : "text-muted-foreground"
                        }`}
                      />
                      <span className="min-w-0 flex-1 truncate">{entry.title}</span>
                      {entry.duration_minutes ? (
                        <span className="text-[10px] text-muted-foreground">
                          {t("learn.minutes", { count: entry.duration_minutes })}
                        </span>
                      ) : null}
                    </button>
                  )
                })}
              </div>
            </div>
          ))}
        </div>

        {/* ── lesson pane ── */}
        <div className="order-1 md:order-2">
          {activeLessonId === null ? (
            <p className="rounded-2xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
              {t("courses.pickLesson")}
            </p>
          ) : lesson === null ? (
            <Skeleton className="h-64 w-full rounded-2xl" />
          ) : (
            <div className="space-y-4 rounded-2xl border p-4 md:p-6">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{t(`courses.lessonTypes.${lesson.type}`)}</Badge>
                {activeMeta?.status === "completed" && (
                  <Badge variant="outline" className="border-transparent bg-success/10 text-success">
                    {t("courses.completed")}
                  </Badge>
                )}
              </div>
              <h2 className="text-lg font-semibold">{lesson.title}</h2>

              {lesson.type === "video" &&
                (ytId ? (
                  <div className="relative aspect-video overflow-hidden rounded-xl bg-black">
                    {videoLoaded ? (
                      <iframe
                        src={`https://www.youtube-nocookie.com/embed/${ytId}?autoplay=1`}
                        className="absolute inset-0 size-full"
                        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                        title={lesson.title}
                      />
                    ) : (
                      <button
                        type="button"
                        onClick={() => setVideoLoaded(true)}
                        className="group absolute inset-0"
                      >
                        {/* eslint-disable-next-line @next/next/no-img-element -- YouTube thumb */}
                        <img
                          src={`https://i.ytimg.com/vi/${ytId}/hqdefault.jpg`}
                          alt=""
                          className="size-full object-cover opacity-80"
                          loading="lazy"
                        />
                        <span className="absolute inset-0 flex items-center justify-center">
                          <CirclePlay className="size-14 text-white drop-shadow group-hover:scale-105" />
                        </span>
                      </button>
                    )}
                  </div>
                ) : lesson.content.url ? (
                  // eslint-disable-next-line jsx-a11y/media-has-caption -- teacher-supplied media
                  <video src={lesson.content.url} controls className="w-full rounded-xl" preload="none" />
                ) : null)}

              {lesson.type === "reading" && (
                <QuestionStem html={lesson.content.body ?? ""} className="text-sm leading-relaxed" />
              )}

              {lesson.type === "file" && (
                <a
                  href={lesson.content.url ?? "#"}
                  target="_blank"
                  rel="noreferrer"
                  className="pressable flex items-center gap-2 rounded-xl border px-3.5 py-3 text-sm hover:bg-accent"
                >
                  <Paperclip className="size-4 shrink-0 text-muted-foreground" />
                  <span className="min-w-0 flex-1 truncate">{lesson.content.name}</span>
                  <span className="text-xs text-muted-foreground">
                    {formatFileSize(lesson.content.size)}
                  </span>
                  <Download className="size-4 text-muted-foreground" />
                </a>
              )}

              {lesson.type === "quiz" && (
                <div className="space-y-3 rounded-xl bg-muted/40 p-4">
                  <p className="text-sm">{t("courses.quizLessonHint")}</p>
                  <Button
                    className="h-11 w-full sm:w-auto"
                    onClick={() => void openQuiz()}
                    loading={startingQuiz}
                  >
                    <FileQuestion className="size-4" /> {t("learn.start")}
                  </Button>
                </div>
              )}

              {/* prev / complete / next */}
              <div className="flex items-center justify-between gap-2 border-t pt-4">
                <Button
                  type="button"
                  variant="outline"
                  className="h-11"
                  disabled={activeIndex <= 0}
                  onClick={() => setActiveLessonId(flat[activeIndex - 1]?.id ?? null)}
                >
                  <ChevronLeft className="size-4" />
                  <span className="hidden sm:inline">{tc("attachment.previous")}</span>
                </Button>
                {activeMeta?.status !== "completed" && lesson.type !== "quiz" ? (
                  <Button
                    type="button"
                    className="h-11 flex-1 sm:flex-none"
                    onClick={() => void markComplete()}
                    loading={completing}
                  >
                    <CheckCircle2 className="size-4" /> {t("courses.markComplete")}
                  </Button>
                ) : lesson.type === "quiz" && activeMeta?.status !== "completed" ? (
                  <Button
                    type="button"
                    variant="outline"
                    className="h-11 flex-1 sm:flex-none"
                    onClick={() => void markComplete()}
                    loading={completing}
                  >
                    <CheckCircle2 className="size-4" /> {t("courses.markComplete")}
                  </Button>
                ) : null}
                <Button
                  type="button"
                  variant="outline"
                  className="h-11"
                  disabled={activeIndex >= flat.length - 1 || (flat[activeIndex + 1]?.is_locked ?? false)}
                  onClick={() => setActiveLessonId(flat[activeIndex + 1]?.id ?? null)}
                >
                  <span className="hidden sm:inline">{tc("attachment.next")}</span>
                  <ChevronRight className="size-4" />
                </Button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
