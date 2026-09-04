# Temari Design System

> The single UI/UX + branding guideline for the Temari.et frontend. Every screen — new or
> touched — must follow this. Tokens live in `app/globals.css`; change them there, never
> per-component. Architecture (routing, data, roles) lives in `AGENTS.md`.

---

## 0. The one-line brief

**"A calm, trustworthy Ethiopian school office — digitized."** Professional like a banking
app, warm like a staff room. Not playful, not corporate-gray, never flashy for its own sake.
On a phone it must feel like a native app, not a shrunken website.

---

## 1. Brand

- **Name:** always `Temari` or `Temari.et` (the `.et` in primary green). Never "temari",
  "TEMARI", or the old School-X name.
- **Mark:** the Ge'ez letter **ተ** (te, for ተማሪ — "student") on a green gradient squircle.
  Use `<LogoMark />` / `<Logo />` from `components/ui/logo.tsx` — never rebuild it.
- **Voice:** plain, specific, confident. Sentence case everywhere (headers, buttons, labels).
  No exclamation marks in system messages. No "Oops". No marketing clichés ("seamless",
  "elevate", "next-gen"). Never invent numbers, testimonials, or awards — the product speaks
  Ethiopian school reality: attendance, fees, Telebirr, SMS, report cards.
- **Amharic moments:** small Ge'ez accents (the mark, `ተማሪ · ለተማሪ` on auth) are part of the
  identity. Body text renders Amharic via the Noto Sans Ethiopic fallback automatically.

## 2. Typography

| Role | Font | Usage |
|---|---|---|
| Display / headings | **Outfit** (`--font-display`) | h1–h4 get it automatically via base CSS; `font-display` utility elsewhere |
| UI / body | **Geist** (`--font-sans`) | default |
| Data / numbers | **Geist Mono** (`--font-mono`) + `tabular-nums` | tables get tabular figures automatically |
| Amharic / Afaan Oromo | **Noto Sans Ethiopic** (`--font-ethiopic`) | automatic fallback in the sans stack |

Rules: page titles are `text-2xl md:text-[1.75rem] font-semibold tracking-tight` (via
`<PageHeader>`); section labels are `text-xs font-semibold uppercase tracking-wide
text-muted-foreground`; body max width ~65ch (`max-w-2xl` for descriptions); use weights
400/500/600/700 — hierarchy through weight and color before size.

## 3. Color

One accent: **Temari green** (`--primary`, oklch hue ≈155). Everything else is a warm
paper-tinted neutral. All grays carry the same warm/green tint — never mix cool grays in.

- **Never hardcode colors.** Only semantic tokens: `bg-background/card/muted/accent`,
  `text-foreground/muted-foreground`, `border-border`, `bg-primary`, `text-destructive`,
  plus `success` / `warning` / `info` for status. The sole exception: `brand-hero` surfaces
  (auth panel) and the `emerald-300` accents inside them.
- **Dark mode is first-class.** Every screen must work in `.dark` (green-tinted charcoal,
  never pure black). Test both themes before shipping — the toggle is in the sidebar footer
  and the mobile menu.
- Status conventions: green = paid/active/present, amber = pending/partial/late,
  red = overdue/absent/banned, blue = informational. Use tinted chips
  (`bg-success/10 text-success`), not solid blocks.

## 4. Surfaces, radius, elevation

- Cards: `bg-card rounded-2xl border shadow-xs`. Containers get bigger radius than their
  contents (2xl container → lg/xl inner elements). List tables live in a contained card
  (`rounded-2xl border bg-card shadow-xs`) — DataTable brings it; never a bare full-bleed
  table.
- **Shape system (locked):** buttons and toolbar controls are **pills** (`rounded-full`,
  from the Button primitive); form controls (Input/Select/Combobox) are **`rounded-xl`**
  with a soft `bg-muted/30` fill; cards and tables are **`rounded-2xl`**; menus/popovers
  are `rounded-xl`. Never restyle a shape per page.
- Shadows are **green-tinted** and come from the scale (`shadow-2xs … shadow-xl`) — never
  ad-hoc `box-shadow`, never pure black.
- Sticky bars (mobile header, bottom nav) use blur: `bg-background/90 backdrop-blur-xl`.
- `brand-tile` (logo gradient) and `brand-hero` (auth panel) are the only decorated
  surfaces; don't invent new gradients.

## 5. Page anatomy

Every app page renders, in order:

```tsx
<div className="space-y-6">              // or space-y-8 for dashboards
  <PageHeader title description actions backHref? />   // components/ui/page-header
  …content sections…                     // each wrapped in .page-gutter (px-4 md:px-8)
</div>
```

- `page-gutter` is the ONE horizontal padding. Full-bleed elements (DataTable) manage their
  own gutters internally.
- Drill-in pages (`/students/[id]`) must pass `backHref` — no dead ends.
- Content max-width: dashboards `max-w-6xl mx-auto`; detail pages `max-w-4xl`; tables full
  width.

## 6. States — all four, always

A screen isn't done until it handles: **loading** (skeletons that match the final layout —
never spinners; `StatCard` and `DataTable` bring their own), **empty**
(`<EmptyState icon title description action?>` — say what the screen is for and give the
next step; never a bare "No records"), **error** (inline field errors via `<FormMessage>`;
`toast.error` only for requests with no field; the route `error.tsx` catches crashes), and
**no-context** (branch-scoped screens show the dashed "select a branch" panel).

A fifth, in-flight state for actions: every button that fires an async request wears
`loading={busyFlag}` on the shared `Button`/`AlertDialogAction` (spinner + auto-disable +
`aria-busy`). Reserve `disabled={}` for validation-only conditions, and keep sibling
Cancel/Back buttons `disabled` — only the running action spins.

## 7. Motion

One easing family (`--ease-out-quart`), three speeds: 150ms (hover/press), 200ms
(reveals), 300ms (sheets/drawers). Rules: animate `transform`/`opacity` only; every
interactive element has hover + press feedback (`pressable` = `active:scale-[0.98]`);
`prefers-reduced-motion` is respected globally; no scroll-jacking, no entrance animations
on data screens.

## 8. Mobile = native app

- `< md` is the primary experience: bottom tab bar (max 4 tabs + Menu sheet), sticky
  blurred header with workspace switcher, content padded `pb-28` for the bar.
- Touch targets ≥ 44px (`touch-target`), inputs `h-12`+ and `text-base` (prevents iOS
  zoom), full-width buttons in forms.
- Every modal is a bottom sheet on mobile: `Dialog` and `AlertDialog` do it automatically
  (`rounded-t-3xl`, slide-up, safe-area padding); use `ResponsiveSheet` (vaul drawer with
  grab handle) for richer create/edit flows. Tables become the card list built into
  `DataTable`.
- Respect notches: `pt-safe` / `pb-safe` on fixed bars. Never allow horizontal scroll on
  the body.

## 9. Components

- **shadcn is the base.** Extend variants in `components/ui/*`; never fork a copy into a
  feature folder or inline restyle a primitive per page.
- **Icons: Lucide only**, `strokeWidth` 1.75 for decorative tiles, 2 for nav; sizes 3.5/4/5.
  Icon tiles: `size-10 rounded-xl bg-accent` (or `bg-primary/10 text-primary` when active).
- **DataTable** (`components/ui/data-table.tsx`) is mandatory for every list — it provides
  search/filters/sort/export/selection/pagination/mobile cards. Server-driven pages pair it
  with `useServerTable`. Its language: a contained card, pill-shaped toolbar controls
  (rounded-full search/filters/count/export — anything passed via `toolbarSlot` must match),
  hover-revealed row checkboxes, a floating bulk-action pill, numbered pagination. The footer
  is one bar in both modes: rows-per-page pill (25/50/75/100, an app-wide preference from
  `lib/use-page-size.ts`) + range on the left, pager on the right — and it stays hidden until
  a list is longer than one page's worth.
- **Forms:** React Hook Form + Zod v3 + `<Form>` primitives; submit disabled while
  `isSubmitting`; backend field errors land via `form.setError`.
- **Feedback:** `sonner` toasts, top-center, sentence case, no "!".

## 10. Accessibility & i18n

- Visible focus rings (global `outline-ring/50`), `aria-current="page"` on active nav,
  labels on icon-only buttons, WCAG AA contrast in both themes.
- **Zero hardcoded UI strings** — everything through `useTranslation(domain)` with en/am/om
  keys added together. Amharic strings are usually longer: test truncation (`truncate`,
  `min-w-0`) with the am locale.

## 11. Ship checklist

Before any UI change is "done": works in light **and** dark · mobile 375px **and** desktop
1280px · all four states · strings in en/am/om · touch targets ≥44px · no hardcoded colors
· no horizontal scroll · `npm run lint` + `npx tsc --noEmit` clean.
