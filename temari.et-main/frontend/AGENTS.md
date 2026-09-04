<!-- BEGIN:nextjs-agent-rules -->
# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing any code. Heed deprecation notices.
<!-- END:nextjs-agent-rules -->

# Temari Frontend Architecture

> How this app is structured and why. Visual rules live in `DESIGN.md` — read both before
> touching UI. Backend contracts live in the root `CLAUDE.md` + `knowledge/dev-guidelines.md`.

## Stack

Next.js (App Router, all app screens are client components behind auth) · React 19 ·
Tailwind v4 (tokens in `app/globals.css`, no tailwind.config) · shadcn (radix-nova) ·
react-hook-form + zod v3 (NOT v4 — resolver incompatible) · sonner · lucide.

## Directory map

```
app/
  (marketing)/    PUBLIC marketing site (see "Marketing site" below) — server components
  (auth)/         login, forgot-pin, reset-pin, set-password, deactivated — AuthLayout hero
  (app)/          everything behind AppShell (sidebar + bottom nav + workspace context)
  auth/impersonate  token hand-off page (no shell)
  sitemap.ts / robots.ts   marketing SEO; robots disallows all app/auth routes
  not-found.tsx / error.tsx   branded system pages
components/
  ui/             design-system primitives (shadcn + PageHeader, EmptyState, StatCard,
                  Logo, ThemeToggle, DataTable…) — generic, no domain knowledge
  app-shell/      sidebar, bottom-nav, mobile-header, workspace/context switchers, nav-config
  <domain>/       feature components (students/, users/, schools/…) — may know the API
lib/
  api.ts          apiFetch: base URL, auth token, X-School-Id/X-Branch-Id headers, ApiError
  auth/           auth-context (session), school-context (workspace), use-effective-permissions
  i18n/           provider + en/am/om JSON per domain
  use-server-table.ts  server-driven list state (search/filter/sort/page → query params)
  types.ts        API types — keep in sync with backend resources
```

## The three context layers (why the UI is role-safe)

1. **Session** (`useAuth`): the user + `roles`, `permissions` (coarse global union),
   `role_permissions` (role → permissions map), `memberships` — all derived from the
   backend's membership kernel (ADR-010).
2. **Workspace** (`useSchoolContext`): the active school/branch. Stored client-side, sent
   on every request as `X-School-Id`/`X-Branch-Id` (validated server-side — the client can
   only *narrow* itself). Every list/screen reacts to workspace changes.
3. **Effective permissions** (`useEffectivePermissions`): `role_permissions` ×
   memberships-that-apply-to-the-active-workspace. **This is the ONLY thing UI gating may
   read.** Never gate on `user.roles`/`user.permissions` directly — a principal at School B
   must not see principal UI while operating in School A.

Navigation derives from `app-shell/nav-config.ts` (`permission` per item) — add new pages
there, never hand-roll nav. The backend re-checks everything; frontend gating is UX, not
security.

Relationship hats (`user.is_parent` / `user.is_student`) are separate from staff
permissions: parent/student surfaces consume the `/api/v1/me/*` endpoints and must never
reuse staff pages or permission checks (backend ADR-012).

## Data layer rules

- **Only `apiFetch`** (`lib/api.ts`) — never raw `fetch`. It attaches the token + workspace
  headers and throws typed `ApiError` (`.errors` for 422 field maps).
- List pages: `useServerTable` + `<DataTable serverMode>` for server-driven
  search/filter/sort/pagination/export. Effects that fetch must handle cancellation
  (`let cancelled = …`) and re-run on `active.schoolId`/`active.branchId`.
- **Paging is the table's job, not the page's.** Client-mode DataTables slice their own
  rows; server mode gets `pagination` from `useServerTable`. Both footers carry the
  rows-per-page picker (25/50/75/100 — `lib/use-page-size.ts`, one preference for the whole
  app, stored per device). A page hand-rolling `pagination` must pass `pageSize` +
  `onPageSizeChange` too, or the picker silently disappears. `paginated={false}` is the
  only way to render every row at once, and needs a reason.
- **DataTable contract (every table):** each action sets `icon:` (no kebab menus — the
  actions column renders inline tooltip'd buttons and sticks to the right edge on
  horizontal scroll); exactly one non-destructive action is `primary: true` so clicking
  the row performs it (view first, else edit); dependent filters declare
  `dependsOn: "<parentKey>"` (section→grade, branch→school — child hidden until the
  parent is picked, options may be a function of the parent value). Phones/emails/bank
  accounts/receipt+reference numbers render via `ContactActionCell`
  (Call/Email + Copy + Share popover; `kind="value"` for non-contact values); public IDs
  stay `CopyableId`.
- Forms: RHF + zod; map backend 422s via `form.setError`; `toast.error` only when no field
  errors came back.
- **Reference data (dropdown/filter sources) goes through the shared store** —
  `lib/shared-data.ts` is a tiny stale-while-revalidate cache keyed by workspace and
  tagged by resource. Use `useRefList<T>("/academic-years")` (`lib/data/use-ref-list.ts`)
  instead of `useEffect + apiFetch + useState` for any list that feeds a select/filter;
  workspace contexts come from `useContextsResponse()` (`lib/auth/school-context.tsx`,
  also behind `useBranchScope`/`BranchField`), grade levels from `useGradeLevels()`.
  Every successful mutation through `apiFetch` auto-invalidates its resource tag (plus
  ripples in `RIPPLES`) — so a branch/year/semester created anywhere shows up in every
  open picker without a reload, and the tab revalidates on refocus. When a mutation on
  resource A must refresh cached resource B, add the pair to `RIPPLES` in
  `lib/shared-data.ts` — never hand-roll a second cache or a manual refetch bus.
- No other client caches/global stores beyond the three contexts — page-specific state
  lives in the page that owns it. Introduce a query library only as a deliberate,
  documented decision.

## Adding a page (checklist)

1. Route under `app/(app)/<feature>/page.tsx`, client component.
2. Nav entry in `nav-config.ts` with its backend permission.
3. Anatomy per DESIGN.md §5: `<PageHeader>` + `page-gutter` sections; all four states
   (loading skeletons, `EmptyState`, errors, no-workspace panel for branch-scoped screens).
4. Gate actions with `useEffectivePermissions()`; branch-scoped fetches wait for
   `active.branchId`.
5. i18n domain file per feature — en/am/om added together, registered in `lib/i18n/index.tsx`.
6. Types in `lib/types.ts` matching the backend resource.
7. Verify: `npm run lint`, `npx tsc --noEmit`, mobile 375px + desktop, light + dark.

## Performance (3G-first market)

- Ship less: `next/dynamic` for heavy, below-the-fold, or rarely-opened components
  (charts, big sheets); keep list pages lean.
- Paginate everything server-side (the backend caps at 100/page); debounce search inputs
  (`useServerTable` does).
- No layout thrash: skeletons reserve space; animate transform/opacity only.
- Images through `next/image`; icons are tree-shaken lucide imports (never the barrel).
- Avoid client-fetch waterfalls: fire independent requests in parallel in one effect.

## Marketing site (`app/(marketing)/`)

The public website (home, features, pricing, exam prep, audiences, about/contact/faq,
legal). Rules differ from the app:

- **Server components, statically prerendered** — near-zero client JS (only the nav,
  `Reveal` scroll animation and `SetHtmlLang` are client leaves). Do NOT use the app's
  client i18n here.
- **Locales are routes:** `/` is canonical English; `/am/...` and `/om/...` are fully
  translated at build time. Content lives in `lib/marketing/content/{en,am,om}.ts`
  (typed by `content/types.ts`) — add copy in all three files together.
  `lib/marketing/site.ts` owns SITE_URL, locales, slugs and path helpers; adding a page
  means: content in all 3 dicts + an `(en)` route file + a branch in
  `[locale]/[...slug]/page.tsx` + `LOCALIZED_PATHS`.
- **SEO:** every page builds metadata through `lib/marketing/seo.tsx`
  (`marketingMetadata` = canonical + hreflang alternates + OG; JSON-LD helpers for
  Organization / SoftwareApplication / FAQ / breadcrumbs). `app/sitemap.ts` emits all
  locale URLs; `app/robots.ts` blocks the app. Keep them in sync with new pages.
- Shared chrome: `components/marketing/` (shell, nav, footer, section primitives,
  product previews). Product depictions are real component previews built from design
  tokens — never fake-screenshot images. Brand voice rules in `DESIGN.md` §1 apply
  (no invented numbers/testimonials, no clichés).

## Known sharp edges

- Zod stays on v3 (`@hookform/resolvers` incompatibility).
- Select/dropdowns inside modals: overlay-dismiss is centrally guarded in the Sheet/Drawer/
  Dialog primitives — don't add per-component outside-click hacks.
- `use-media-query` uses `useSyncExternalStore` — don't regress it to setState-in-effect.
- The i18n `t()` returns the key when missing — a literal `dashboard.foo` on screen means a
  missing translation, usually only in am/om.
