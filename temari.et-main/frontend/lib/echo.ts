"use client"

import Echo from "laravel-echo"
import Pusher from "pusher-js"

import { getToken } from "@/lib/api"

/**
 * Reverb websocket client — a latency UPGRADE, never the source of truth:
 * every consumer keeps polling its HTTP endpoint as the reliability floor
 * (3G drops sockets constantly), and socket payloads share the exact HTTP
 * shapes (backend ChatPresenter). Channel auth posts the Sanctum bearer
 * token to /api/broadcasting/auth.
 *
 * Lazily created on first use; null when the env isn't configured so the
 * app degrades to polling silently.
 */

declare global {
  interface Window {
    Pusher?: typeof Pusher
  }
}

type EchoClient = Echo<"reverb">

let echo: EchoClient | null = null
let initialized = false

export function getEcho(): EchoClient | null {
  if (typeof window === "undefined") return null
  if (initialized) return echo

  initialized = true

  const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY
  if (!key) return null

  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api/v1"
  const authEndpoint = `${apiUrl.replace(/\/v1\/?$/, "")}/broadcasting/auth`

  window.Pusher = Pusher

  echo = new Echo({
    broadcaster: "reverb",
    key,
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? "localhost",
    wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080),
    wssPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 443),
    forceTLS: (process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "http") === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint,
    // The token can rotate (login/logout) — resolve it per auth request.
    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        // eslint-disable-next-line @typescript-eslint/no-explicit-any -- pusher's ChannelAuthorizationCallback
        callback: (error: Error | null, data: any) => void,
      ) => {
        fetch(authEndpoint, {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            Authorization: `Bearer ${getToken() ?? ""}`,
          },
          body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
        })
          .then(async (response) => {
            if (!response.ok) throw new Error(`Auth failed (${response.status})`)
            callback(null, await response.json())
          })
          .catch((error: Error) => callback(error, null))
      },
    }),
  })

  return echo
}

/** Tear down on logout so the next session re-authorizes cleanly. */
export function disconnectEcho() {
  echo?.disconnect()
  echo = null
  initialized = false
}
