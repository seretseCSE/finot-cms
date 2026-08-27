"use client"

import { useSyncExternalStore } from "react"

import {
  DEFAULT_CALENDAR_PREFS,
  getCalendarPrefs,
  subscribeCalendar,
  type CalendarPrefs,
} from "@/lib/dates"

/**
 * The active workspace's date/time display prefs, reactive. Formatters in
 * lib/dates.ts already read the same store — use this hook when a component
 * needs to BRANCH on the mode (e.g. the DatePicker choosing its grid), or to
 * re-render when the workspace flips calendars.
 */
export function useCalendar(): CalendarPrefs {
  return useSyncExternalStore(
    subscribeCalendar,
    getCalendarPrefs,
    () => DEFAULT_CALENDAR_PREFS,
  )
}
