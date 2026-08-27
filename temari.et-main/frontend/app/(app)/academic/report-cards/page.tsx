import { redirect } from "next/navigation"

/**
 * The report-card register merged into the rosters hub (July 2026): the
 * roster already carries the semester + yearly views, so report-card
 * printing, conduct entry and the extra-assessment checklist live there now.
 * Old bookmarks and deep links land safely.
 */
export default function ReportCardsRedirect() {
  redirect("/academic/rosters")
}
