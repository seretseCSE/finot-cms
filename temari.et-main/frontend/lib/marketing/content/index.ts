import type { MarketingLocale } from "@/lib/marketing/site"

import { am } from "./am"
import { en } from "./en"
import { om } from "./om"
import type { MarketingDict } from "./types"

const dicts: Record<MarketingLocale, MarketingDict> = { en, am, om }

export function getMarketingDict(locale: MarketingLocale): MarketingDict {
  return dicts[locale] ?? en
}

export type { MarketingDict } from "./types"
