"use client"

import Link from "next/link"
import { useEffect, useState } from "react"
import { ArrowRight } from "lucide-react"

import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

/**
 * App-like sticky action bar on mobile: Sign in + Get started. Hidden while
 * the hero (which carries its own CTAs) is still on screen, so the same
 * button never shows twice at once.
 */
export function MobileCtaBar({
  getStarted,
  signIn,
}: {
  getStarted: string
  signIn: string
}) {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > 560)
    onScroll()
    window.addEventListener("scroll", onScroll, { passive: true })
    return () => window.removeEventListener("scroll", onScroll)
  }, [])

  return (
    <div
      className={cn(
        "pb-safe fixed inset-x-4 bottom-3 z-30 flex gap-2 transition-all duration-300 md:hidden",
        visible
          ? "translate-y-0 opacity-100"
          : "pointer-events-none translate-y-6 opacity-0"
      )}
    >
      <Button
        asChild
        size="lg"
        variant="outline"
        className="h-12 flex-[0.8] bg-background/95 text-base shadow-xl backdrop-blur"
      >
        <Link href="/login">{signIn}</Link>
      </Button>
      <Button asChild size="lg" className="h-12 flex-1 text-base shadow-xl">
        <Link href="/signup">
          {getStarted}
          <ArrowRight data-icon="inline-end" />
        </Link>
      </Button>
    </div>
  )
}
