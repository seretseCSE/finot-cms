"use client"

import { useParams } from "next/navigation"

import { ExamPlayer } from "@/components/lms/exam-player"

/**
 * The fullscreen exam sitting (and, once submitted, the result review).
 * Rendered as a fixed overlay above the app shell — no nav, no distractions.
 */
export default function ExamAttemptPage() {
  const params = useParams<{ attemptId: string }>()

  return <ExamPlayer attemptId={Number(params.attemptId)} />
}
