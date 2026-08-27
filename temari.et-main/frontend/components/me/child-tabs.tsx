"use client"

import { useEffect, useState } from "react"

import { PersonAvatar } from "@/components/ui/person-avatar"
import { apiFetch } from "@/lib/api"
import { setCalendarPrefs } from "@/lib/dates"
import type { CalendarMode, ClockMode } from "@/lib/types"
import { cn } from "@/lib/utils"

/** /me/children payload — relationship lane (ADR-012), never staff types. */
export interface ChildLink {
  student_id: number
  full_name: string
  public_id: string | null
  photo_url: string | null
  gender: string
  unpaid_invoices: number | null
  relationship: string
  is_primary: boolean
  permissions: {
    can_view_grades: boolean
    can_view_attendance: boolean
    can_pay_fees: boolean
  }
  current_enrollment: {
    school: string | null
    branch: string | null
    calendar_mode?: CalendarMode
    clock_mode?: ClockMode
    grade_level: string | null
    section: string | null
    academic_year: string | null
    status: string
    terms?: {
      id: number
      name: string
      sequence: number
      is_current: boolean
      status: string
    }[]
  } | null
}

const ACTIVE_CHILD_KEY = "temari_active_child"

/**
 * Children of the signed-in guardian + the active child selection, shared by
 * every /me page so switching a child carries across Family and Payments.
 */
export function useChildren(enabled: boolean) {
  const [children, setChildren] = useState<ChildLink[] | null>(null)
  const [activeChild, setActiveChildState] = useState<number | null>(null)

  useEffect(() => {
    if (!enabled) return
    let cancelled = false
    apiFetch<{ data: ChildLink[] }>("/me/children")
      .then((res) => {
        if (cancelled) return
        setChildren(res.data)
        const stored = Number(window.localStorage.getItem(ACTIVE_CHILD_KEY))
        const valid = res.data.find((c) => c.student_id === stored)
        setActiveChildState(valid?.student_id ?? res.data[0]?.student_id ?? null)
      })
      .catch(() => {
        if (!cancelled) setChildren([])
      })
    return () => {
      cancelled = true
    }
  }, [enabled])

  const setActiveChild = (studentId: number) => {
    window.localStorage.setItem(ACTIVE_CHILD_KEY, String(studentId))
    setActiveChildState(studentId)
  }

  const child = children?.find((c) => c.student_id === activeChild) ?? null

  // The family portal renders dates the way the ACTIVE child's school writes
  // them (branch override → school default → Ethiopian).
  useEffect(() => {
    if (!child?.current_enrollment) return
    setCalendarPrefs({
      calendar: child.current_enrollment.calendar_mode ?? "ethiopian",
      clock: child.current_enrollment.clock_mode ?? "ethiopian",
    })
  }, [child])

  return { children, child, activeChild, setActiveChild }
}

/** PowerSchool-style per-child tabs — the parent's second-level switcher. */
export function ChildTabs({
  items,
  activeChild,
  onChange,
}: {
  items: ChildLink[]
  activeChild: number | null
  onChange: (studentId: number) => void
}) {
  if (items.length <= 1) return null

  return (
    <div className="mt-4 flex gap-2 overflow-x-auto pb-1">
      {items.map((child) => (
        <button
          key={child.student_id}
          type="button"
          onClick={() => onChange(child.student_id)}
          className={cn(
            "flex h-10 shrink-0 items-center gap-2 rounded-full border px-3.5 text-sm font-medium transition-colors",
            activeChild === child.student_id
              ? "border-primary bg-primary/10 text-primary"
              : "bg-background text-muted-foreground hover:bg-muted",
          )}
        >
          <PersonAvatar name={child.full_name} photoUrl={child.photo_url} className="size-6 text-[9px]" />
          {child.full_name.split(" ")[0]}
        </button>
      ))}
    </div>
  )
}
