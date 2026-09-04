export interface Country {
  num_code: string
  alpha_2_code: string
  alpha_3_code: string
  en_short_name: string
  nationality: string
}

let cached: Country[] | null = null

export async function loadCountries(): Promise<Country[]> {
  if (cached) return cached
  const res = await fetch("/data/countries.json")
  cached = await res.json()
  return cached!
}
