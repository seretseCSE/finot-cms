import { fileURLToPath } from "node:url"

import { defineConfig } from "vitest/config"

const alias = { "@": fileURLToPath(new URL("./", import.meta.url)) }

/**
 * Two projects, because the suite has two kinds of test:
 *
 *  - `logic` — pure functions (dates, validators, enum parity). Plain node,
 *    no DOM, fastest.
 *  - `dom`   — React components rendered with Testing Library. Needs jsdom
 *    plus the setup file that installs the jest-dom matchers and the few
 *    browser APIs jsdom is missing (see vitest.setup.ts).
 *
 * Split so a component test can never silently pass in a node environment,
 * and so the logic tests keep running without jsdom's startup cost.
 */
export default defineConfig({
  resolve: { alias },
  test: {
    projects: [
      {
        resolve: { alias },
        test: {
          name: "logic",
          environment: "node",
          include: ["lib/**/*.test.ts"],
        },
      },
      {
        resolve: { alias },
        test: {
          name: "dom",
          environment: "jsdom",
          setupFiles: ["./vitest.setup.ts"],
          include: ["components/**/*.test.{ts,tsx}", "hooks/**/*.test.{ts,tsx}"],
        },
      },
    ],
  },
})
