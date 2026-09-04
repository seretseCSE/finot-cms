"use client"

import { RotateCcw } from "lucide-react"
import { useEffect } from "react"

import { Button } from "@/components/ui/button"
import { LogoMark } from "@/components/ui/logo"
import { captureError } from "@/lib/analytics"

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
  useEffect(() => {
    console.error(error)
    captureError(error, { boundary: "app", digest: error.digest })
  }, [error])

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-6 px-6 text-center">
      <LogoMark size="lg" />
      <div className="space-y-2">
        <h1 className="font-display text-xl font-semibold tracking-tight">Something broke</h1>
        <p className="text-muted-foreground mx-auto max-w-sm text-sm leading-relaxed">
          The page hit an unexpected error. Your data is safe — try again, and contact support if
          it keeps happening.
        </p>
        {error.digest && (
          <p className="text-muted-foreground/60 font-mono text-xs">Ref: {error.digest}</p>
        )}
      </div>
      <Button onClick={reset} className="h-11 gap-2 px-6">
        <RotateCcw className="size-4" />
        Try again
      </Button>
    </div>
  )
}
