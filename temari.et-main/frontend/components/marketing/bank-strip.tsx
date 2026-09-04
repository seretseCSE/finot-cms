import Image from "next/image"

import { cn } from "@/lib/utils"

const CHANNELS = [
  { slug: "telebirr", name: "Telebirr" },
  { slug: "cbebirr", name: "CBE Birr" },
  { slug: "cbe", name: "Commercial Bank of Ethiopia" },
  { slug: "mpesa", name: "M-PESA" },
  { slug: "awash", name: "Awash Bank" },
  { slug: "boa", name: "Bank of Abyssinia" },
  { slug: "dashen", name: "Dashen Bank" },
  { slug: "amhara", name: "Amhara Bank" },
  { slug: "zemen", name: "Zemen Bank" },
  { slug: "siinqee", name: "Siinqee Bank" },
]

/**
 * Payment channels the platform actually verifies — real logos, logo-only.
 * Chips keep colored marks readable in dark mode.
 */
export function BankStrip({
  title,
  className,
}: {
  title: string
  className?: string
}) {
  return (
    <div className={cn("text-center", className)}>
      <p className="text-sm font-medium text-muted-foreground">{title}</p>
      <ul className="mt-6 flex flex-wrap items-center justify-center gap-2.5">
        {CHANNELS.map((channel) => (
          <li
            key={channel.slug}
            className="flex h-12 items-center rounded-xl border bg-card px-4 shadow-2xs dark:border-transparent dark:bg-white/95"
          >
            <Image
              src={`/images/banks/${channel.slug}.svg`}
              alt={channel.name}
              width={84}
              height={24}
              className="h-6 w-auto max-w-24 object-contain"
            />
          </li>
        ))}
      </ul>
    </div>
  )
}
