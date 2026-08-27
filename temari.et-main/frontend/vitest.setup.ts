import "@testing-library/jest-dom/vitest"

import { cleanup } from "@testing-library/react"
import { afterEach, beforeAll, vi } from "vitest"

afterEach(() => cleanup())

/**
 * The app hides inactive wizard steps with Tailwind's `hidden` utility rather
 * than unmounting them. Tailwind's stylesheet is not loaded in jsdom, so
 * without this rule every step counts as visible and `getByRole` would happily
 * match a field on a step the user cannot see — the tests would prove nothing.
 *
 * Only the handful of utilities whose *visibility* semantics the tests depend
 * on belong here. This is not a place to reimplement Tailwind.
 */
beforeAll(() => {
  const style = document.createElement("style")
  style.textContent = `
    .hidden { display: none; }
    [hidden] { display: none; }
    /* jsdom has no viewport, so responsive utilities never resolve. Tests run
       at jsdom's default 1024px width — i.e. above Tailwind's sm breakpoint —
       so the sm: variants that toggle visibility must win over .hidden, as
       they do on a real desktop. Declared after .hidden: same specificity,
       source order decides. */
    .sm\\:inline { display: inline; }
    .sm\\:block { display: block; }
    .sm\\:flex { display: flex; }
    .sr-only {
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      clip: rect(0 0 0 0);
      white-space: nowrap;
    }
  `
  document.head.appendChild(style)
})

// ── Browser APIs jsdom does not implement ────────────────────────────────
// Radix primitives (Select, Combobox, Popover) call into the Pointer Capture
// and scroll APIs on open; jsdom has neither, so they throw without these.
beforeAll(() => {
  const proto = window.Element.prototype as unknown as Record<string, unknown>
  proto.hasPointerCapture ??= () => false
  proto.setPointerCapture ??= () => {}
  proto.releasePointerCapture ??= () => {}
  proto.scrollIntoView ??= () => {}

  // jsdom declares scrollTo but throws "Not implemented" when called. Wizards
  // scroll to the top on every step change, which would flood the output.
  window.scrollTo = (() => {}) as typeof window.scrollTo

  window.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }

  window.matchMedia ??= ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })) as typeof window.matchMedia

  // The photo step previews the picked file through an object URL.
  URL.createObjectURL ??= vi.fn(() => "blob:preview")
  URL.revokeObjectURL ??= vi.fn()
})
