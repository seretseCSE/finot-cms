import { fireEvent, render, screen } from "@testing-library/react"
import { beforeEach, describe, expect, it, vi } from "vitest"

import { I18nProvider } from "@/lib/i18n"

/**
 * Behavioural tests for the shared upload dropzone.
 *
 * Every file picker in the app routes both its picked and its dropped files
 * through `useFileDrop`, so the rules asserted here — what is accepted, how
 * big it may be, which of two nested zones takes a file, and that a stray
 * drop never navigates the browser away from a half-filled form — hold
 * everywhere at once. They are written against what a user does (drag a file
 * onto a region) rather than the hook's shape.
 */

const toastError = vi.fn()
vi.mock("sonner", () => ({ toast: { error: (...args: unknown[]) => toastError(...args) } }))

const { DROP_ACTIVE, useFileDrop } = await import("@/components/ui/dropzone")

/** A drag payload that looks like the browser's: files plus the "Files" type. */
function transfer(files: File[]) {
  return { files, types: ["Files"], dropEffect: "none" }
}

function file(name: string, { type = "", size = 1024 } = {}) {
  const made = new File(["x"], name, { type })
  Object.defineProperty(made, "size", { value: size })
  return made
}

/** A minimal surface: one drop region plus the hidden picker beside it. */
function Zone({
  onFiles,
  ...options
}: Omit<Parameters<typeof useFileDrop>[0], "onFiles"> & { onFiles: (files: File[]) => void }) {
  const { dragOver, dropProps, takeFiles } = useFileDrop({ onFiles, ...options })
  return (
    <div>
      <div {...dropProps} data-testid="zone" className={dragOver ? DROP_ACTIVE : ""}>
        <span data-testid="zone-label">drop here</span>
      </div>
      <input
        type="file"
        data-testid="picker"
        onChange={(e) => takeFiles(e.target.files)}
      />
    </div>
  )
}

function renderZone(props: Parameters<typeof Zone>[0]) {
  return render(
    <I18nProvider>
      <Zone {...props} />
    </I18nProvider>,
  )
}

beforeEach(() => toastError.mockClear())

describe("dropped files", () => {
  it("hands an accepted file to the same handler the picker uses", () => {
    const onFiles = vi.fn()
    renderZone({ onFiles, accept: ".pdf" })

    fireEvent.drop(screen.getByTestId("zone"), { dataTransfer: transfer([file("receipt.pdf")]) })

    expect(onFiles).toHaveBeenCalledTimes(1)
    expect(onFiles.mock.calls[0][0][0].name).toBe("receipt.pdf")
  })

  it("matches `accept` by extension, by mime type and by wildcard", () => {
    const onFiles = vi.fn()
    renderZone({ onFiles, accept: "image/*,.pdf", multiple: true })

    fireEvent.drop(screen.getByTestId("zone"), {
      dataTransfer: transfer([
        file("photo.jpg", { type: "image/jpeg" }),
        file("scan.pdf"),
        file("notes.txt", { type: "text/plain" }),
      ]),
    })

    expect(onFiles.mock.calls[0][0].map((f: File) => f.name)).toEqual(["photo.jpg", "scan.pdf"])
    expect(toastError).toHaveBeenCalledTimes(1)
  })

  it("rejects a file over the size cap and keeps the rest", () => {
    const onFiles = vi.fn()
    renderZone({ onFiles, maxSize: 2048, multiple: true })

    fireEvent.drop(screen.getByTestId("zone"), {
      dataTransfer: transfer([file("small.pdf", { size: 1024 }), file("huge.pdf", { size: 9999 })]),
    })

    expect(onFiles.mock.calls[0][0].map((f: File) => f.name)).toEqual(["small.pdf"])
    expect(toastError).toHaveBeenCalledTimes(1)
  })

  it("takes only the first file when the surface holds one", () => {
    const onFiles = vi.fn()
    renderZone({ onFiles })

    fireEvent.drop(screen.getByTestId("zone"), {
      dataTransfer: transfer([file("first.pdf"), file("second.pdf")]),
    })

    expect(onFiles.mock.calls[0][0].map((f: File) => f.name)).toEqual(["first.pdf"])
  })

  it("ignores a disabled region", () => {
    const onFiles = vi.fn()
    renderZone({ onFiles, disabled: true })

    fireEvent.drop(screen.getByTestId("zone"), { dataTransfer: transfer([file("a.pdf")]) })

    expect(onFiles).not.toHaveBeenCalled()
  })
})

describe("the highlight", () => {
  it("appears while a file hovers and clears once it leaves", () => {
    renderZone({ onFiles: vi.fn() })
    const zone = screen.getByTestId("zone")
    const dragged = { dataTransfer: transfer([file("a.pdf")]) }

    fireEvent.dragEnter(zone, dragged)
    fireEvent.dragOver(zone, dragged)
    expect(zone.className).toContain("outline-dashed")

    fireEvent.dragLeave(zone, dragged)
    expect(zone.className).not.toContain("outline-dashed")
  })

  it("stays off for a drag that carries no files", () => {
    renderZone({ onFiles: vi.fn() })
    const zone = screen.getByTestId("zone")

    // Dragging text, or an exam question being reordered — not an upload.
    fireEvent.dragEnter(zone, { dataTransfer: { files: [], types: ["text/plain"] } })
    fireEvent.dragOver(zone, { dataTransfer: { files: [], types: ["text/plain"] } })

    expect(zone.className).not.toContain("outline-dashed")
  })

  it("survives the pointer crossing a child element", () => {
    renderZone({ onFiles: vi.fn() })
    const zone = screen.getByTestId("zone")
    const dragged = { dataTransfer: transfer([file("a.pdf")]) }

    fireEvent.dragEnter(zone, dragged)
    fireEvent.dragOver(zone, dragged)

    // Moving onto the label: the child's enter bubbles, then the region
    // reports a leave. Net zero — the highlight must hold.
    fireEvent.dragEnter(screen.getByTestId("zone-label"), dragged)
    fireEvent.dragLeave(zone, dragged)

    expect(zone.className).toContain("outline-dashed")
  })
})

describe("nested regions", () => {
  /** A photo tile inside a documents region — the everyday nesting. */
  function Nested({ onInner, onOuter }: { onInner: () => void; onOuter: () => void }) {
    const outer = useFileDrop({ onFiles: onOuter })
    const inner = useFileDrop({ onFiles: onInner })
    return (
      <div {...outer.dropProps} data-testid="outer" className={outer.dragOver ? DROP_ACTIVE : ""}>
        <div {...inner.dropProps} data-testid="inner" className={inner.dragOver ? DROP_ACTIVE : ""}>
          photo
        </div>
      </div>
    )
  }

  it("gives the file to the innermost region only", () => {
    const onInner = vi.fn()
    const onOuter = vi.fn()
    render(
      <I18nProvider>
        <Nested onInner={onInner} onOuter={onOuter} />
      </I18nProvider>,
    )

    fireEvent.drop(screen.getByTestId("inner"), { dataTransfer: transfer([file("face.jpg")]) })

    expect(onInner).toHaveBeenCalledTimes(1)
    expect(onOuter).not.toHaveBeenCalled()
  })

  it("lights up only the innermost region", () => {
    render(
      <I18nProvider>
        <Nested onInner={vi.fn()} onOuter={vi.fn()} />
      </I18nProvider>,
    )

    const dragged = { dataTransfer: transfer([file("face.jpg")]) }
    fireEvent.dragEnter(screen.getByTestId("inner"), dragged)
    fireEvent.dragOver(screen.getByTestId("inner"), dragged)

    expect(screen.getByTestId("inner").className).toContain("outline-dashed")
    expect(screen.getByTestId("outer").className).not.toContain("outline-dashed")
  })
})

describe("a drop that misses the region", () => {
  it("is swallowed so the browser never opens the file over the form", () => {
    renderZone({ onFiles: vi.fn() })

    const stray = new Event("drop", { bubbles: true, cancelable: true })
    Object.defineProperty(stray, "dataTransfer", { value: transfer([file("a.pdf")]) })
    window.dispatchEvent(stray)

    expect(stray.defaultPrevented).toBe(true)
  })

  it("leaves non-file drags alone", () => {
    renderZone({ onFiles: vi.fn() })

    const stray = new Event("drop", { bubbles: true, cancelable: true })
    Object.defineProperty(stray, "dataTransfer", { value: { files: [], types: ["text/plain"] } })
    window.dispatchEvent(stray)

    expect(stray.defaultPrevented).toBe(false)
  })
})
