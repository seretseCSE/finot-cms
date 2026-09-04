"use client"

import { GraduationCap, PlayCircle } from "lucide-react"
import { useRouter } from "next/navigation"
import { useEffect, useState } from "react"

import { Badge } from "@/components/ui/badge"
import { EmptyState } from "@/components/ui/empty-state"
import { Skeleton } from "@/components/ui/skeleton"
import { apiFetch } from "@/lib/api"
import { useTranslation } from "@/lib/i18n"
import type { MeCourse } from "@/lib/types"

/**
 * The learner's course shelf: cover cards with a progress ring, sorted by
 * recent activity. Shared by the student "My learning" page and the guest
 * exam-prep home.
 */
export function CourseShelf({ query = "" }: { query?: string }) {
  const { t } = useTranslation("lms")
  const router = useRouter()
  const [courses, setCourses] = useState<MeCourse[] | null>(null)

  useEffect(() => {
    let cancelled = false
    apiFetch<{ data: MeCourse[] }>(`/me/courses?per_page=100${query}`)
      .then((res) => !cancelled && setCourses(res.data))
      .catch(() => !cancelled && setCourses([]))
    return () => {
      cancelled = true
    }
  }, [query])

  if (courses === null) {
    return (
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {[0, 1, 2].map((i) => (
          <Skeleton key={i} className="h-36 rounded-2xl" />
        ))}
      </div>
    )
  }

  if (courses.length === 0) {
    return (
      <EmptyState
        icon={GraduationCap}
        title={t("courses.shelfEmpty")}
        description={t("courses.shelfEmptyDesc")}
      />
    )
  }

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {courses.map((course) => (
        <button
          key={course.id}
          type="button"
          onClick={() => router.push(`/me/courses/${course.id}`)}
          className="pressable overflow-hidden rounded-2xl border text-left transition-shadow hover:shadow-sm"
        >
          {course.cover_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- R2 signed URL
            <img src={course.cover_url} alt="" className="h-24 w-full object-cover" loading="lazy" />
          ) : (
            <div className="flex h-24 w-full items-center justify-center bg-primary/5">
              <GraduationCap className="size-8 text-primary/40" />
            </div>
          )}
          <div className="space-y-2 p-3.5">
            <div className="flex flex-wrap items-center gap-1.5">
              {course.subject_name && (
                <Badge variant="secondary" className="text-[10px]">
                  {course.subject_name}
                </Badge>
              )}
              {course.stream && (
                <Badge variant="outline" className="text-[10px]">
                  {t(`courses.stream${course.stream === "natural" ? "Natural" : "Social"}`)}
                </Badge>
              )}
            </div>
            <p className="line-clamp-2 text-sm font-semibold">{course.title}</p>
            <div className="space-y-1">
              <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                <div
                  className="h-full rounded-full bg-primary transition-[width]"
                  style={{ width: `${course.progress_percent}%` }}
                />
              </div>
              <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>
                  {t("courses.progressOf", {
                    done: course.completed_count,
                    total: course.lessons_count,
                  })}
                </span>
                <span className="inline-flex items-center gap-1 font-medium text-primary">
                  <PlayCircle className="size-3.5" />
                  {course.progress_percent > 0 ? t("courses.continue") : t("courses.start")}
                </span>
              </div>
            </div>
          </div>
        </button>
      ))}
    </div>
  )
}
