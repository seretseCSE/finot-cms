"use client"

import { useRouter } from "next/navigation"
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react"

import {
  apiFetch,
  getActiveContext,
  getToken,
  setActiveContext,
  setDeactivationHandler,
  setToken,
} from "@/lib/api"
import { identifyUser, resetAnalytics } from "@/lib/analytics"
import { LOCALES, useLocale } from "@/lib/i18n"
import type { Membership, User } from "@/lib/types"

/** What a completed signup connected the verified phone to. */
export type SignupLinked = "parent" | "student" | "claim_pending" | "none"

export interface SignupPayload {
  phone: string
  otp: string
  password: string
  password_confirmation: string
  name?: string
  student_public_id?: string
  preferred_language?: string
}

interface AuthContextValue {
  user: User | null
  loading: boolean
  /** `identifier` = phone number or Temari student ID (server detects which). */
  login: (identifier: string, password: string) => Promise<void>
  forgotPassword: (identifier: string) => Promise<void>
  resetPassword: (identifier: string, otp: string, password: string, passwordConfirmation: string) => Promise<void>
  setPassword: (token: string, password: string, passwordConfirmation: string) => Promise<void>
  requestSignupOtp: (phone: string, preferredLanguage?: string) => Promise<void>
  signup: (payload: SignupPayload) => Promise<SignupLinked>
  logout: () => Promise<void>
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

interface AuthResponse {
  data: User
  meta: { token: string }
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter()
  const { adoptLocale } = useLocale()
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  // The account's preferred_language is the source of truth for a signed-in
  // user — it also drives the SMS/email the school sends. Adopt it whenever the
  // user (re)loads so the interface follows the account across devices, without
  // writing back (that would fight a just-made selection).
  const accountLocale = user?.preferred_language
  useEffect(() => {
    if (accountLocale && LOCALES.includes(accountLocale)) {
      adoptLocale(accountLocale)
    }
  }, [accountLocale, adoptLocale])

  // Analytics identity follows the session: every load/login ties this
  // browser to the account (same distinct id the backend captures with).
  useEffect(() => {
    if (user) identifyUser(user)
  }, [user])

  // Bounce the user to the deactivation screen if their access is withdrawn
  // mid-session (any request returns account_inactive / account_banned).
  useEffect(() => {
    setDeactivationHandler((code) => {
      setToken(null)
      setActiveContext(null, null)
      setUser(null)
      router.replace(`/deactivated?code=${code}`)
    })
    return () => setDeactivationHandler(null)
  }, [router])

  // Seed the active workspace. On a fresh authentication (`force`) we always
  // apply the user's primary membership. On session restore (page refresh) we
  // must respect the workspace the user previously selected — otherwise every
  // reload would silently bounce them back to their default workspace.
  const applyDefaultContext = useCallback((nextUser: User, force = false) => {
    if (!force) {
      const stored = getActiveContext()
      if (stored.schoolId || stored.branchId) return
    }
    const primary: Membership | undefined =
      nextUser.memberships?.find((m) => m.is_active) ?? nextUser.memberships?.[0]
    setActiveContext(primary?.school_id ?? null, primary?.branch_id ?? null)
  }, [])

  const refresh = useCallback(async () => {
    if (!getToken()) {
      setUser(null)
      setLoading(false)
      return
    }
    try {
      const response = await apiFetch<{ data: User }>("/auth/me")
      setUser(response.data)
      applyDefaultContext(response.data)
    } catch {
      setToken(null)
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [applyDefaultContext])

  useEffect(() => {
    // Load the current session from the backend on mount.
    // eslint-disable-next-line react-hooks/set-state-in-effect -- syncing session state with the API
    refresh()
  }, [refresh])

  const login = useCallback(
    // `identifier` is a phone number OR a Temari student ID — the backend
    // detects which (App\Support\LoginIdentifier).
    async (identifier: string, password: string) => {
      const response = await apiFetch<AuthResponse>("/auth/login", {
        method: "POST",
        body: { identifier, password },
        anonymous: true,
      })
      setToken(response.meta.token)
      setUser(response.data)
      applyDefaultContext(response.data, true)
    },
    [applyDefaultContext],
  )

  const forgotPassword = useCallback(async (identifier: string) => {
    await apiFetch("/auth/forgot-password", {
      method: "POST",
      body: { identifier },
      anonymous: true,
    })
  }, [])

  const resetPassword = useCallback(
    async (identifier: string, otp: string, password: string, passwordConfirmation: string) => {
      const response = await apiFetch<AuthResponse>("/auth/reset-password", {
        method: "POST",
        body: { identifier, otp, password, password_confirmation: passwordConfirmation },
        anonymous: true,
      })
      setToken(response.meta.token)
      setUser(response.data)
      applyDefaultContext(response.data, true)
    },
    [applyDefaultContext],
  )

  const setPassword = useCallback(
    async (token: string, password: string, passwordConfirmation: string) => {
      const response = await apiFetch<AuthResponse>("/auth/set-password", {
        method: "POST",
        body: { token, password, password_confirmation: passwordConfirmation },
        anonymous: true,
      })
      setToken(response.meta.token)
      setUser(response.data)
      applyDefaultContext(response.data, true)
    },
    [applyDefaultContext],
  )

  const requestSignupOtp = useCallback(async (phone: string, preferredLanguage?: string) => {
    await apiFetch("/auth/signup/request-otp", {
      method: "POST",
      body: { phone, preferred_language: preferredLanguage },
      anonymous: true,
    })
  }, [])

  const signup = useCallback(
    async (payload: SignupPayload): Promise<SignupLinked> => {
      const response = await apiFetch<AuthResponse & { meta: { token: string; linked?: SignupLinked } }>(
        "/auth/signup",
        { method: "POST", body: payload, anonymous: true },
      )
      setToken(response.meta.token)
      setUser(response.data)
      applyDefaultContext(response.data, true)
      return response.meta.linked ?? "none"
    },
    [applyDefaultContext],
  )

  const logout = useCallback(async () => {
    try {
      await apiFetch("/auth/logout", { method: "POST" })
    } catch {
      // ignore — clear locally regardless
    }
    setToken(null)
    setActiveContext(null, null)
    setUser(null)
    // A shared device must never attribute the next person's events here.
    resetAnalytics()
    // Drop the websocket so the next session re-authorizes its channels.
    const { disconnectEcho } = await import("@/lib/echo")
    disconnectEcho()
  }, [])

  const value = useMemo(
    () => ({ user, loading, login, forgotPassword, resetPassword, setPassword, requestSignupOtp, signup, logout, refresh }),
    [user, loading, login, forgotPassword, resetPassword, setPassword, requestSignupOtp, signup, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error("useAuth must be used within AuthProvider")
  return context
}
