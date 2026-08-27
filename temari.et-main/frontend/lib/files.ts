/**
 * Shared file/attachment helpers: kind detection, size formatting, and the
 * download/share actions used by the attachment tiles and the media preview.
 * Attachment URLs are short-lived signed R2 links — download fetches the
 * bytes eagerly and share prefers Web Share with the file payload.
 */

export type FileKind =
  | "image"
  | "pdf"
  | "doc"
  | "sheet"
  | "video"
  | "audio"
  | "archive"
  | "other"

/** A previewable/downloadable file — the common shape of all attachment types. */
export interface MediaFile {
  name: string
  /** Short-lived signed URL; null when the caller may not access the file. */
  url: string | null
  mime_type?: string | null
  size?: number | null
}

const EXTENSION_BY_MIME: Record<string, string> = {
  "image/jpeg": "jpg",
  "image/png": "png",
  "image/webp": "webp",
  "image/gif": "gif",
  "image/svg+xml": "svg",
  "application/pdf": "pdf",
  "application/msword": "doc",
  "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
    "docx",
  "application/vnd.ms-excel": "xls",
  "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": "xlsx",
  "application/vnd.ms-powerpoint": "ppt",
  "application/vnd.openxmlformats-officedocument.presentationml.presentation":
    "pptx",
  "application/vnd.oasis.opendocument.text": "odt",
  "application/vnd.oasis.opendocument.spreadsheet": "ods",
  "application/vnd.oasis.opendocument.presentation": "odp",
  "application/rtf": "rtf",
  "text/csv": "csv",
  "text/markdown": "md",
  "text/plain": "txt",
  "application/zip": "zip",
  "image/heic": "heic",
  "video/mp4": "mp4",
  "video/webm": "webm",
  "audio/mpeg": "mp3",
  "audio/mp4": "m4a",
  "audio/aac": "aac",
  "audio/amr": "amr",
  "audio/ogg": "ogg",
  "audio/wav": "wav",
}

/**
 * What classwork uploads accept — teacher reference files on an assignment and
 * student turn-ins alike. MIRRORS `App\Support\CourseworkFiles::EXTENSIONS`;
 * change one and change the other, or the picker offers a type the endpoint
 * refuses and the user only finds out on save.
 */
export const COURSEWORK_ACCEPT = [
  ".pdf,.doc,.docx,.odt,.rtf,.txt,.md",
  ".ppt,.pptx,.odp",
  ".xls,.xlsx,.ods,.csv",
  ".jpg,.jpeg,.png,.webp,.gif,.heic",
  ".mp3,.m4a,.aac,.ogg,.amr,.wav",
  ".mp4,.webm",
  ".zip",
].join(",")

/** `CourseworkFiles::MAX_KB` in bytes. */
export const COURSEWORK_MAX_BYTES = 20480 * 1024

export function fileKind(mimeType?: string | null): FileKind {
  const mime = (mimeType ?? "").toLowerCase()
  if (mime.startsWith("image/")) return "image"
  if (mime === "application/pdf") return "pdf"
  if (mime.startsWith("video/")) return "video"
  if (mime.startsWith("audio/")) return "audio"
  // Sheets before docs: a .csv is `text/csv`, which the text catch-all below
  // would otherwise claim.
  if (/(ms-excel|spreadsheetml|csv|opendocument\.spreadsheet)/.test(mime))
    return "sheet"
  if (
    /(msword|wordprocessingml|rtf|opendocument\.text)/.test(mime) ||
    mime.startsWith("text/")
  )
    return "doc"
  if (/(zip|rar|7z|x-tar|gzip)/.test(mime)) return "archive"
  return "other"
}

export function formatFileSize(bytes?: number | null): string {
  if (!bytes) return ""
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/** Display names have their extension stripped on upload — restore it from the mime type. */
export function downloadFileName(file: MediaFile): string {
  const name = file.name.trim() || "file"
  const ext = EXTENSION_BY_MIME[(file.mime_type ?? "").toLowerCase()]
  if (!ext || name.toLowerCase().endsWith(`.${ext}`)) return name
  return `${name}.${ext}`
}

/**
 * Download the file bytes and save under its display name. Signed R2 URLs are
 * cross-origin, so a plain `download` attribute is ignored — fetch to a blob
 * instead, and fall back to opening the URL when CORS blocks the fetch.
 */
export async function downloadFile(
  file: MediaFile
): Promise<"saved" | "opened" | "failed"> {
  if (!file.url) return "failed"
  try {
    const res = await fetch(file.url)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const blob = await res.blob()
    const href = URL.createObjectURL(blob)
    const anchor = document.createElement("a")
    anchor.href = href
    anchor.download = downloadFileName(file)
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(href)
    return "saved"
  } catch {
    const win = window.open(file.url, "_blank", "noopener")
    return win ? "opened" : "failed"
  }
}

/**
 * Native share with the file payload where supported (mobile), falling back
 * to sharing the link, then to copying it. The caller toasts on "copied".
 */
export async function shareFile(
  file: MediaFile
): Promise<"shared" | "copied" | "failed"> {
  if (!file.url) return "failed"
  try {
    if (typeof navigator.share === "function") {
      if (typeof navigator.canShare === "function") {
        try {
          const res = await fetch(file.url)
          if (res.ok) {
            const blob = await res.blob()
            const payload = new File([blob], downloadFileName(file), {
              type: file.mime_type ?? blob.type,
            })
            if (navigator.canShare({ files: [payload] })) {
              await navigator.share({ files: [payload], title: file.name })
              return "shared"
            }
          }
        } catch (error) {
          if (error instanceof DOMException && error.name === "AbortError")
            return "shared"
          // CORS or share failure — fall through to link sharing.
        }
      }
      await navigator.share({ title: file.name, url: file.url })
      return "shared"
    }
    await navigator.clipboard.writeText(file.url)
    return "copied"
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError")
      return "shared"
    return "failed"
  }
}
