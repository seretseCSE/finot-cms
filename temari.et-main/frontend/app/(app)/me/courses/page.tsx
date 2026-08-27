"use client"

import { CourseShelf } from "@/components/lms/course-shelf"
import { PageHeader } from "@/components/ui/page-header"
import { useTranslation } from "@/lib/i18n"

/** The learner's course shelf (students, parents' kids browsing, B2C users). */
export default function MyCoursesPage() {
  const { t } = useTranslation("lms")

  return (
    <div className="space-y-6">
      <PageHeader title={t("courses.myTitle")} description={t("courses.mySubtitle")} />
      <div className="px-4 md:px-8">
        <CourseShelf />
      </div>
    </div>
  )
}
