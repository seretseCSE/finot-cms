import type { MetadataRoute } from "next"

import { SITE_URL } from "@/lib/marketing/site"

/** Marketing pages are crawlable; the authenticated app and auth flows are not. */
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: [
          "/dashboard",
          "/me",
          "/messages",
          "/notifications",
          "/settings",
          "/users",
          "/students",
          "/parents",
          "/employees",
          "/schools",
          "/branches",
          "/branch-settings",
          "/sections",
          "/semesters",
          "/academic",
          "/attendance",
          "/fees",
          "/invoices",
          "/finance",
          "/concessions",
          "/payment-accounts",
          "/payroll",
          "/hr",
          "/lms",
          "/marklists",
          "/lesson-plans",
          "/timetable",
          "/transfers",
          "/devices",
          "/catalogs",
          "/docs",
          "/unauthorized",
          "/login",
          "/signup",
          "/forgot-pin",
          "/reset-pin",
          "/set-password",
          "/deactivated",
          "/print",
          "/letters",
          "/receipts",
          "/rosters",
          "/transcripts",
          "/auth",
        ],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
  }
}
