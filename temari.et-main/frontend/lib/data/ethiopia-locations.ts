export const REGIONS = [
  "Addis Ababa",
  "Afar",
  "Amhara",
  "Benishangul-Gumuz",
  "Central Ethiopia",
  "Dire Dawa",
  "Gambela",
  "Harari",
  "Oromia",
  "Sidama",
  "Somali",
  "South Ethiopia",
  "Southwest Ethiopia Peoples'",
  "Tigray",
] as const

export type Region = (typeof REGIONS)[number]

export const CITIES_BY_REGION: Record<string, string[]> = {
  "Addis Ababa": ["Addis Ababa"],
  Afar: ["Semera", "Asaita", "Dubti", "Gewane", "Logia", "Chifra", "Mille", "Awash"],
  Amhara: [
    "Bahir Dar",
    "Gondar",
    "Dessie",
    "Kombolcha",
    "Debre Birhan",
    "Debre Markos",
    "Woldia",
    "Lalibela",
    "Debre Tabor",
    "Finote Selam",
    "Injibara",
    "Nifas Mewcha",
  ],
  "Benishangul-Gumuz": ["Assosa", "Metekel", "Kamashi", "Gilgel Beles", "Manbuk"],
  "Central Ethiopia": ["Butajira", "Wolkite", "Hossana", "Worabe", "Durame", "Alaba Kulito"],
  "Dire Dawa": ["Dire Dawa"],
  Gambela: ["Gambela", "Itang", "Abobo", "Gog"],
  Harari: ["Harar", "Jugol"],
  Oromia: [
    "Adama",
    "Jimma",
    "Nekemte",
    "Shashamane",
    "Bishoftu",
    "Asella",
    "Ambo",
    "Bale Robe",
    "Gimbi",
    "Ginir",
    "Dembi Dolo",
    "Goba",
    "Moyale",
    "Negele Borana",
    "Naqamte",
    "Weliso",
    "Burayu",
    "Sebeta",
    "Holeta",
    "Modjo",
    "Shashemene",
  ],
  Sidama: ["Hawassa", "Yirgalem", "Aleta Wendo", "Wondo Genet"],
  Somali: ["Jijiga", "Degeh Bur", "Gode", "Kebri Dahar", "Werder", "Dolo Odo"],
  "South Ethiopia": [
    "Arba Minch",
    "Wolaita Sodo",
    "Dilla",
    "Yabelo",
    "Chencha",
    "Alaba",
    "Sawla",
    "Bule Hora",
  ],
  "Southwest Ethiopia Peoples'": ["Bonga", "Mizan Teferi", "Tepi", "Jimma (Zone)", "Dima"],
  Tigray: ["Mekelle", "Axum", "Adwa", "Adigrat", "Shire", "Alamata", "Maychew", "Wukro"],
}

export const SUB_CITIES_BY_CITY: Record<string, string[]> = {
  "Addis Ababa": [
    "Addis Ketema",
    "Akaki Kality",
    "Arada",
    "Bole",
    "Gullele",
    "Kirkos",
    "Kolfe Keranio",
    "Lideta",
    "Nifas Silk-Lafto",
    "Yeka",
  ],
  "Dire Dawa": [
    "Addis Ketema",
    "Ayubet",
    "Dechatu",
    "Ganda Mota",
    "Gende Kore",
    "Goro",
    "Legehare",
    "Melka Jebdu",
    "Sabian",
  ],
}

const AA_WOREDAS: Record<string, string[]> = {
  "Addis Ketema": Array.from({ length: 10 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  "Akaki Kality": Array.from({ length: 13 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  Arada: Array.from({ length: 8 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  Bole: Array.from({ length: 14 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  Gullele: Array.from({ length: 10 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  Kirkos: Array.from({ length: 11 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  "Kolfe Keranio": Array.from({ length: 15 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  Lideta: Array.from({ length: 9 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  "Nifas Silk-Lafto": Array.from({ length: 13 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
  Yeka: Array.from({ length: 14 }, (_, i) => `Woreda ${String(i + 1).padStart(2, "0")}`),
}

export const WOREDAS_BY_SUB_CITY: Record<string, string[]> = AA_WOREDAS

export function getCitiesForRegion(region: string): string[] {
  return CITIES_BY_REGION[region] ?? []
}

export function getSubCitiesForCity(city: string): string[] {
  return SUB_CITIES_BY_CITY[city] ?? []
}

export function getWoredasForSubCity(subCity: string): string[] {
  return WOREDAS_BY_SUB_CITY[subCity] ?? []
}
