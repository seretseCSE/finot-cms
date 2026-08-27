import Link from "next/link"

import { LogoMark } from "@/components/ui/logo"
import { Button } from "@/components/ui/button"

export default function NotFound() {
  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-6 px-6 text-center">
      <LogoMark size="lg" />
      <div className="space-y-2">
        <p className="font-display text-muted-foreground/60 text-6xl font-bold tracking-tight">
          404
        </p>
        <h1 className="font-display text-xl font-semibold tracking-tight">
          This page doesn&rsquo;t exist
        </h1>
        <p className="text-muted-foreground mx-auto max-w-sm text-sm leading-relaxed">
          The link may be outdated, or the page may have moved. Head back to your dashboard to
          continue.
        </p>
      </div>
      <Button asChild className="h-11 px-6">
        <Link href="/dashboard">Go to dashboard</Link>
      </Button>
    </div>
  )
}
