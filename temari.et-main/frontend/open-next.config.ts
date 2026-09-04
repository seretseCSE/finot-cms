import { defineCloudflareConfig } from "@opennextjs/cloudflare"
import staticAssetsIncrementalCache from "@opennextjs/cloudflare/overrides/incremental-cache/static-assets-incremental-cache"

// The dashboard is fully dynamic, but the marketing site prerenders pages under
// dynamic segments (/am, /om, /for/[audience], /features/[slug]…). OpenNext serves
// those through the incremental cache — with the default dummy cache they 404 on
// Workers. Static-assets cache is read-only (no ISR revalidation), which is all
// SSG needs; switch to an R2 cache only if revalidating ISR is ever introduced.
export default defineCloudflareConfig({
  incrementalCache: staticAssetsIncrementalCache,
})
