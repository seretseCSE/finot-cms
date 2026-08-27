import { Suspense } from "react"

import { AppShell } from "@/components/app-shell/app-shell"

export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <AppShell>
      {/* Pages read deep-link params (?tab=, ?q=) via useSearchParams, which
          needs a Suspense boundary above it for static prerendering. */}
      <Suspense>{children}</Suspense>
    </AppShell>
  )
}
