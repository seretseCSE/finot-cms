import posthog from "posthog-js"

// PostHog bootstraps before any app code runs (Next instrumentation-client).
// No key (local dev) = fully disabled. Events reach PostHog through the
// same-origin /ingest rewrite (next.config.ts) so ad blockers and strict
// mobile browsers don't eat them.
//
// Privacy defaults for a school platform: no autocapture (tables render
// student names/marks — element text must never leave the app), no session
// recording, person profiles only for identified (signed-in) users. Insights
// come from explicit events: backend captures via App\Services\Analytics and
// frontend captures via lib/analytics.ts.
const key = process.env.NEXT_PUBLIC_POSTHOG_KEY

if (key) {
  posthog.init(key, {
    api_host: process.env.NEXT_PUBLIC_POSTHOG_HOST ?? "/ingest",
    ui_host: "https://us.posthog.com",
    defaults: "2025-05-24", // SPA pageview/pageleave on history changes
    capture_exceptions: true, // unhandled errors + rejections → error tracking
    autocapture: false,
    disable_session_recording: true,
    person_profiles: "identified_only",
  })
}
