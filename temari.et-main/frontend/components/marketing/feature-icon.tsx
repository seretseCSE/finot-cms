import {
  BookOpenText,
  Boxes,
  Briefcase,
  CalendarCheck,
  CalendarRange,
  GraduationCap,
  IdCard,
  Laptop,
  MessageSquare,
  Receipt,
  Users,
  type LucideIcon,
} from "lucide-react"

import type { FeatureSlug } from "@/lib/marketing/site"

export const FEATURE_ICONS: Record<FeatureSlug, LucideIcon> = {
  "student-management": Users,
  attendance: CalendarCheck,
  "id-cards": IdCard,
  fees: Receipt,
  grading: GraduationCap,
  lms: Laptop,
  courses: BookOpenText,
  timetable: CalendarRange,
  communication: MessageSquare,
  "hr-payroll": Briefcase,
  inventory: Boxes,
}
