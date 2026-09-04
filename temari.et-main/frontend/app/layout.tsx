import type { Metadata, Viewport } from "next"
import { Geist, Geist_Mono, Noto_Sans_Ethiopic, Outfit } from "next/font/google"

import "./globals.css"
import { Toaster } from "@/components/ui/sonner"
import { ThemeProvider } from "@/components/theme-provider"
import { AuthProvider } from "@/lib/auth/auth-context"
import { I18nProvider } from "@/lib/i18n"
import { cn } from "@/lib/utils"

// Type system (DESIGN.md §2): Outfit for display/headings, Geist for UI text,
// Geist Mono for tabular data, Noto Sans Ethiopic for Amharic fallback.
const geist = Geist({ subsets: ["latin"], variable: "--font-sans" })

const outfit = Outfit({
  subsets: ["latin"],
  variable: "--font-display",
  weight: ["400", "500", "600", "700"],
})

const fontMono = Geist_Mono({
  subsets: ["latin"],
  variable: "--font-mono",
})

const ethiopic = Noto_Sans_Ethiopic({
  subsets: ["ethiopic"],
  variable: "--font-ethiopic",
})

export const metadata: Metadata = {
  metadataBase: new URL("https://temari.et"),
  title: {
    default: "Temari — Ethiopian Education Platform",
    template: "%s · Temari",
  },
  description:
    "School management, attendance, fees, continuous assessment and exam prep for Ethiopian schools — built for low bandwidth and mobile.",
  applicationName: "Temari",
  appleWebApp: { capable: true, statusBarStyle: "default", title: "Temari" },
}

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#f8f8f5" },
    { media: "(prefers-color-scheme: dark)", color: "#161d19" },
  ],
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html
      lang="en"
      suppressHydrationWarning
      className={cn(
        "antialiased",
        "font-sans",
        geist.variable,
        outfit.variable,
        fontMono.variable,
        ethiopic.variable,
      )}
    >
      <body>
        <ThemeProvider attribute="class" defaultTheme="light" enableSystem disableTransitionOnChange>
          <I18nProvider>
            <AuthProvider>{children}</AuthProvider>
          </I18nProvider>
          <Toaster richColors position="top-center" closeButton />
        </ThemeProvider>
      </body>
    </html>
  )
}
