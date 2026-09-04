"use client"

import { Suspense } from "react"

import { AiScreen } from "@/components/ai/ai-screen"

/**
 * Temari AI — the ChatGPT-style assistant surface (sessions + streaming
 * chat). All logic lives in components/ai; Suspense wraps useSearchParams.
 */
export default function AiPage() {
  return (
    <Suspense fallback={null}>
      <AiScreen />
    </Suspense>
  )
}
