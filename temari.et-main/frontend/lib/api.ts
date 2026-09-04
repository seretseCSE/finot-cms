import { beginPendingAction } from "@/lib/pending-actions"
import { clearSharedData, notifyMutation } from "@/lib/shared-data"

export const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1"

const TOKEN_KEY = "temari_token"
const SCHOOL_KEY = "temari_active_school"
const BRANCH_KEY = "temari_active_branch"

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  /** Application-level error code (e.g. account_inactive, account_banned). */
  code: string | null
  /** The full response body — structured context (e.g. timetable conflicts). */
  payload: Record<string, unknown>

  constructor(
    message: string,
    status: number,
    errors: Record<string, string[]> = {},
    code: string | null = null,
    payload: Record<string, unknown> = {},
  ) {
    super(message)
    this.name = "ApiError"
    this.status = status
    this.errors = errors
    this.code = code
    this.payload = payload
  }
}

/** Codes returned when a user's global account access has been withdrawn. */
export const DEACTIVATION_CODES = ["account_inactive", "account_banned"] as const

export function getToken(): string | null {
  if (typeof window === "undefined") return null
  return window.localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null) {
  if (typeof window === "undefined") return
  // The session identity changed — cached reference data must never cross
  // from one account (or a signed-out state) into the next.
  clearSharedData()
  if (token) {
    window.localStorage.setItem(TOKEN_KEY, token)
  } else {
    window.localStorage.removeItem(TOKEN_KEY)
  }
}

export function getActiveContext() {
  if (typeof window === "undefined") return { schoolId: null, branchId: null }
  return {
    schoolId: window.localStorage.getItem(SCHOOL_KEY),
    branchId: window.localStorage.getItem(BRANCH_KEY),
  }
}

export function setActiveContext(schoolId: number | null, branchId: number | null) {
  if (typeof window === "undefined") return
  if (schoolId) window.localStorage.setItem(SCHOOL_KEY, String(schoolId))
  else window.localStorage.removeItem(SCHOOL_KEY)
  if (branchId) window.localStorage.setItem(BRANCH_KEY, String(branchId))
  else window.localStorage.removeItem(BRANCH_KEY)
}

interface RequestOptions {
  method?: string
  body?: unknown
  /** Skip attaching the auth token (for login/set-password). */
  anonymous?: boolean
  /**
   * i18n key (common domain) naming this mutation for the global
   * pending-actions guard. Mutating requests default to a generic
   * saving/deleting/uploading label — pass a key for a more specific one.
   */
  pendingKey?: string
  /** Cancel the request (type-ahead search superseding an in-flight call). */
  signal?: AbortSignal
}

const MUTATING_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"])

export async function apiFetch<T>(path: string, options: RequestOptions = {}): Promise<T> {
  // FormData bodies (file uploads) set their own multipart boundary — never
  // force a JSON content type on them.
  const isFormData = typeof FormData !== "undefined" && options.body instanceof FormData

  const headers: Record<string, string> = {
    Accept: "application/json",
    ...(isFormData ? {} : { "Content-Type": "application/json" }),
  }

  if (!options.anonymous) {
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`

    const { schoolId, branchId } = getActiveContext()
    if (schoolId) headers["X-School-Id"] = schoolId
    if (branchId) headers["X-Branch-Id"] = branchId
  }

  // Every mutation registers with the pending-actions guard so closing the
  // tab or navigating away mid-save warns the user (with the action named).
  const method = (options.method ?? "GET").toUpperCase()
  const done = MUTATING_METHODS.has(method)
    ? beginPendingAction(
        options.pendingKey ??
          (isFormData
            ? "pending.actions.uploading"
            : method === "DELETE"
              ? "pending.actions.deleting"
              : "pending.actions.saving"),
      )
    : null

  let response: Response
  try {
    response = await fetch(`${API_URL}${path}`, {
      method,
      headers,
      signal: options.signal,
      body:
        options.body === undefined
          ? undefined
          : isFormData
            ? (options.body as FormData)
            : JSON.stringify(options.body),
    })
  } finally {
    done?.()
  }

  // A successful mutation makes cached reference data (branches, years,
  // terms…) stale everywhere — tell the shared store so open screens refresh.
  if (response.ok && MUTATING_METHODS.has(method) && !options.anonymous) {
    notifyMutation(path)
  }

  if (response.status === 204) {
    return undefined as T
  }

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    const error = new ApiError(
      payload.message ?? "Something went wrong.",
      response.status,
      payload.errors ?? {},
      payload.code ?? null,
      payload,
    )

    // Global handling: a withdrawn account should be bounced everywhere.
    if (
      typeof window !== "undefined" &&
      error.code !== null &&
      (DEACTIVATION_CODES as readonly string[]).includes(error.code)
    ) {
      onDeactivated?.(error.code)
    }

    // A 5xx is the backend failing a real user — record it so error rates
    // per endpoint show up in analytics (4xx are expected app flow: kept out).
    if (typeof window !== "undefined" && response.status >= 500) {
      import("@/lib/analytics").then(({ track }) =>
        track("api_error", {
          path: path.split("?")[0],
          method,
          status: response.status,
        }),
      )
    }

    throw error
  }

  return payload as T
}

/** Registered by the auth layer to react to a mid-session account withdrawal. */
let onDeactivated: ((code: string) => void) | null = null

export function setDeactivationHandler(handler: ((code: string) => void) | null) {
  onDeactivated = handler
}
