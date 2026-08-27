import type { NextConfig } from "next"
import { initOpenNextCloudflareForDev } from "@opennextjs/cloudflare"

const nextConfig: NextConfig = {
  // Next 16 holds a per-distDir dev lock — a second `next dev` (e.g. a
  // parallel agent session) needs its own dist dir to start at all.
  distDir: process.env.NEXT_DIST_DIR || undefined,
  // PostHog calls trailing-slash endpoints; Next must not redirect them.
  skipTrailingSlashRedirect: true,
  async rewrites() {
    // Same-origin analytics ingestion (instrumentation-client.ts) — keeps
    // events flowing through ad blockers and strict mobile browsers.
    return [
      {
        source: "/ingest/static/:path*",
        destination: "https://us-assets.i.posthog.com/static/:path*",
      },
      {
        source: "/ingest/:path*",
        destination: "https://us.i.posthog.com/:path*",
      },
    ]
  },
}

export default nextConfig

// Makes Cloudflare bindings/env available during `next dev` (no-op in production builds).
initOpenNextCloudflareForDev()
