"use client"

import { useEffect } from "react"

import { captureError } from "@/lib/analytics"

/**
 * Last-resort boundary: catches crashes in the root layout itself, which
 * app/error.tsx never sees. Replaces the whole document, so it renders its
 * own <html>/<body> with inline styles (globals.css may not have loaded).
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
  useEffect(() => {
    console.error(error)
    captureError(error, { boundary: "global", digest: error.digest })
  }, [error])

  return (
    <html lang="en">
      <body
        style={{
          minHeight: "100dvh",
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          justifyContent: "center",
          gap: "16px",
          padding: "24px",
          textAlign: "center",
          fontFamily: "system-ui, sans-serif",
          background: "#f8f8f5",
          color: "#1c2420",
        }}
      >
        <h1 style={{ fontSize: "20px", fontWeight: 600, margin: 0 }}>Something broke</h1>
        <p style={{ maxWidth: "360px", fontSize: "14px", lineHeight: 1.6, margin: 0, color: "#5c6660" }}>
          The app hit an unexpected error. Your data is safe — try again, and contact support if it
          keeps happening.
        </p>
        {error.digest && (
          <p style={{ fontSize: "12px", fontFamily: "monospace", color: "#9aa39d", margin: 0 }}>
            Ref: {error.digest}
          </p>
        )}
        <button
          onClick={reset}
          style={{
            padding: "12px 24px",
            borderRadius: "10px",
            border: "none",
            background: "#1c2420",
            color: "#fff",
            fontSize: "14px",
            fontWeight: 500,
            cursor: "pointer",
          }}
        >
          Try again
        </button>
      </body>
    </html>
  )
}
