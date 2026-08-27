"use client"

import { useParams, useRouter } from "next/navigation"
import { useState } from "react"

import { CourseEditor } from "@/components/lms/course-editor"
import { Skeleton } from "@/components/ui/skeleton"
import { useSchoolContext } from "@/lib/auth/school-context"

/**
 * Deep link into the course studio: /lms/courses/{id} simply opens the
 * full-screen editor over the list route (the studio owns the whole
 * experience — course, modules and lessons in one screen).
 */
export default function CourseDeepLinkPage() {
  const params = useParams<{ id: string }>()
  const router = useRouter()
  const { isPlatform } = useSchoolContext()
  const [open, setOpen] = useState(true)

  return (
    <div className="space-y-4 p-4 md:p-8">
      <Skeleton className="h-16 w-full rounded-2xl" />
      <Skeleton className="h-56 w-full rounded-2xl" />
      <CourseEditor
        courseId={Number(params.id)}
        platform={isPlatform}
        open={open}
        onOpenChange={(next) => {
          setOpen(next)
          if (!next) router.push("/lms/courses")
        }}
        onSaved={() => undefined}
      />
    </div>
  )
}
