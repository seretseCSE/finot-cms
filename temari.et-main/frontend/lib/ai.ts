import { API_URL, ApiError, getActiveContext, getToken } from "@/lib/api"

/**
 * The AI chat streaming client. The send endpoint answers with SSE in the
 * Vercel AI data protocol (`data: {"type":"text-delta",...}` … `data:
 * [DONE]`) — apiFetch is JSON-only, so this is the one sanctioned raw-fetch
 * exception, attaching the same auth + workspace headers.
 */
export interface AiStreamHandlers {
  /** Called per text token with the accumulated delta. */
  onDelta: (delta: string) => void
  /** A tool round-trip started/ended — drive the "working…" indicator. */
  onToolActivity?: () => void
}

export function aiAuthHeaders(): Record<string, string> {
  const headers: Record<string, string> = {}
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  const { schoolId, branchId } = getActiveContext()
  if (schoolId) headers["X-School-Id"] = schoolId
  if (branchId) headers["X-Branch-Id"] = branchId
  return headers
}

export async function streamAiMessage(
  conversationId: number,
  content: string,
  attachments: File[],
  handlers: AiStreamHandlers,
  signal?: AbortSignal,
): Promise<void> {
  const form = new FormData()
  form.append("content", content)
  for (const file of attachments) form.append("attachments[]", file)

  return streamAiRequest(`/ai/conversations/${conversationId}/messages`, form, handlers, signal)
}

/** Re-answer the last prompt — the server swaps the trailing exchange. */
export async function streamAiRegenerate(
  conversationId: number,
  handlers: AiStreamHandlers,
  signal?: AbortSignal,
): Promise<void> {
  return streamAiRequest(`/ai/conversations/${conversationId}/messages/regenerate`, null, handlers, signal)
}

async function streamAiRequest(
  path: string,
  body: FormData | null,
  handlers: AiStreamHandlers,
  signal?: AbortSignal,
): Promise<void> {
  const response = await fetch(`${API_URL}${path}`, {
    method: "POST",
    headers: { Accept: "text/event-stream", ...aiAuthHeaders() },
    body,
    signal,
  })

  if (!response.ok || !response.body) {
    let payload: Record<string, unknown> = {}
    try {
      payload = (await response.json()) as Record<string, unknown>
    } catch {
      // Non-JSON error body — keep the generic message.
    }
    throw new ApiError(
      typeof payload.message === "string" ? payload.message : `Request failed (${response.status})`,
      response.status,
      (payload.errors as Record<string, string[]>) ?? {},
      typeof payload.code === "string" ? payload.code : null,
      payload,
    )
  }

  const reader = response.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ""

  for (;;) {
    const { done, value } = await reader.read()
    if (done) break

    buffer += decoder.decode(value, { stream: true })

    // SSE frames are separated by a blank line.
    const frames = buffer.split("\n\n")
    buffer = frames.pop() ?? ""

    for (const frame of frames) {
      for (const line of frame.split("\n")) {
        if (!line.startsWith("data: ")) continue
        const data = line.slice(6)
        if (data === "[DONE]") return

        let event: { type?: string; delta?: string; errorText?: string }
        try {
          event = JSON.parse(data) as typeof event
        } catch {
          continue
        }

        if (event.type === "text-delta" && typeof event.delta === "string") {
          handlers.onDelta(event.delta)
        } else if (event.type === "error") {
          throw new ApiError(event.errorText ?? "AI error", 500)
        } else if (event.type?.startsWith("tool-")) {
          handlers.onToolActivity?.()
        }
      }
    }
  }
}
