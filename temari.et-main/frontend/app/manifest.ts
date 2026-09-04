import type { MetadataRoute } from "next"

/**
 * PWA manifest: makes Temari installable on Android/iOS home screens and
 * desktop. Colors match the root viewport theme (light surface); icons are
 * served in both maskable and plain variants so launchers never letterbox.
 */
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Temari — Ethiopian School Platform",
    short_name: "Temari",
    description:
      "Attendance, fees, report cards, exams and SMS to parents for Ethiopian schools — plus Grade 6, 8 and 12 exam prep. In Amharic, Afaan Oromo and English.",
    id: "/",
    start_url: "/",
    scope: "/",
    display: "standalone",
    background_color: "#f8f8f5",
    theme_color: "#f8f8f5",
    lang: "en-ET",
    dir: "ltr",
    categories: ["education", "productivity"],
    prefer_related_applications: false,
    icons: [
      {
        src: "/web-app-manifest-192x192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "maskable",
      },
      {
        src: "/web-app-manifest-512x512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "maskable",
      },
      {
        src: "/web-app-manifest-192x192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/web-app-manifest-512x512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "any",
      },
    ],
    screenshots: [
      {
        src: "/og.png",
        sizes: "1200x630",
        type: "image/png",
        form_factor: "wide",
        label: "Temari — the school platform built for Ethiopia",
      },
    ],
    shortcuts: [
      {
        name: "Open the app",
        short_name: "Dashboard",
        description: "Go to your Temari home screen",
        url: "/dashboard",
        icons: [{ src: "/web-app-manifest-192x192.png", sizes: "192x192" }],
      },
      {
        name: "Exam prep",
        short_name: "Exam prep",
        description: "Practice for Grade 6, 8 and 12 national exams",
        url: "/exam-prep",
        icons: [{ src: "/web-app-manifest-192x192.png", sizes: "192x192" }],
      },
    ],
  }
}
