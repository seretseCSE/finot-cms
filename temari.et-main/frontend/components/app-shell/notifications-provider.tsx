"use client"

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react"

import { apiFetch } from "@/lib/api"
import { useAuth } from "@/lib/auth/auth-context"

/**
 * One unread-count poller for the whole shell (the desktop bell and the
 * mobile bell both read it — never two timers). 3G-first: a count-only GET
 * every 60s while the tab is visible, an immediate refresh on focus, and
 * nothing at all while the tab is hidden. Mutations (mark read / read all)
 * update the count optimistically via bump().
 */

interface NotificationsContextValue {
  unread: number
  /** Re-fetch the count now (after mutations elsewhere). */
  refresh: () => void
  /** Optimistic local adjustment; negative to decrement, 0 resets to none. */
  bump: (delta: number | "clear") => void
}

const NotificationsContext = createContext<NotificationsContextValue>({
  unread: 0,
  refresh: () => {},
  bump: () => {},
})

export const useNotifications = () => useContext(NotificationsContext)

const POLL_MS = 60_000

export function NotificationsProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth()
  const [unread, setUnread] = useState(0)
  const inFlight = useRef(false)

  const refresh = useCallback(() => {
    if (inFlight.current || document.visibilityState === "hidden") return
    inFlight.current = true
    apiFetch<{ data: { unread: number } }>("/me/notifications/unread-count")
      .then((res) => setUnread(res.data.unread))
      .catch(() => {})
      .finally(() => {
        inFlight.current = false
      })
  }, [])

  const bump = useCallback((delta: number | "clear") => {
    setUnread((current) => (delta === "clear" ? 0 : Math.max(0, current + delta)))
  }, [])

  useEffect(() => {
    if (!user) return
    refresh()
    const timer = setInterval(refresh, POLL_MS)
    const onVisible = () => {
      if (document.visibilityState === "visible") refresh()
    }
    document.addEventListener("visibilitychange", onVisible)
    window.addEventListener("focus", onVisible)
    return () => {
      clearInterval(timer)
      document.removeEventListener("visibilitychange", onVisible)
      window.removeEventListener("focus", onVisible)
    }
  }, [user, refresh])

  return (
    <NotificationsContext.Provider value={{ unread, refresh, bump }}>
      {children}
    </NotificationsContext.Provider>
  )
}
