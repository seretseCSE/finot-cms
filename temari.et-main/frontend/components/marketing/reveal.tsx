"use client"

import { useEffect, useRef } from "react"

import { cn } from "@/lib/utils"

/**
 * Scroll reveal for below-the-fold marketing sections: fades/slides content in
 * the first time it enters the viewport. Content is always present in the
 * server HTML (SEO-safe); reduced-motion users get it instantly. The class
 * swap is direct DOM work (no state) so the reveal never re-renders React.
 */
export function Reveal({
  children,
  className,
  delay = 0,
}: {
  children: React.ReactNode
  className?: string
  /** Stagger offset in ms for grids. */
  delay?: number
}) {
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    const show = () => {
      el.classList.remove("opacity-0", "translate-y-5")
    }
    if (
      typeof IntersectionObserver === "undefined" ||
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
      show()
      return
    }
    const io = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting) {
          show()
          io.disconnect()
        }
      },
      { threshold: 0.12, rootMargin: "0px 0px -32px 0px" }
    )
    io.observe(el)
    return () => io.disconnect()
  }, [])

  return (
    <div
      ref={ref}
      style={delay ? { transitionDelay: `${delay}ms` } : undefined}
      className={cn(
        "translate-y-5 opacity-0 transition-[opacity,transform] duration-700 [transition-timing-function:var(--ease-out-quart)]",
        className
      )}
    >
      {children}
    </div>
  )
}
