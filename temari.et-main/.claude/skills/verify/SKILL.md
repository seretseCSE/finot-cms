# Verify — drive the Temari app end-to-end

## Handles
- Frontend: Next.js dev server usually already running on `http://localhost:3000` (check `curl -s -o /dev/null -w "%{http_code}" http://localhost:3000`).
- Backend API: `php artisan serve` on `http://localhost:8000` (`frontend/.env.local` → `NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1`). The Herd domain `temari.et.test` is NOT what the frontend talks to.
- Demo data: `cd backend && php artisan db:seed --class=DemoSeeder` — every seeded account's PIN is `DemoSeeder::PASSWORD_PLAIN` (currently `123456`; check the constant, it has drifted before). Beware: the artisan CLI and the long-running `artisan serve` process may see different env/DB state; trust what the API on :8000 returns, not tinker.
- Second dev server (another session already holds port 3000 + the Next 16 dev lock): use the `temari-frontend-alt` launch config — it sets `NEXT_DIST_DIR=.next-alt` so a parallel `next dev` can start on an auto port.

## Login gotcha
Log in programmatically — POST `/api/v1/auth/login` `{identifier, password}` and plant the token:

```js
localStorage.setItem("temari_token", json.meta.token)   // then reload any app page
```

Useful accounts (from DemoSeeder, may shift after reseed — query `memberships` for roles): director of Unity Academy Main branch had phone `0912000003`. Staff API calls need `X-School-Id` / `X-Branch-Id` headers.

## Driving headless
Claude-in-Chrome works when connected; when it drops, use playwright-core with the system Chrome (no browser download):

```js
import { chromium } from "playwright-core"
const browser = await chromium.launch({
  executablePath: "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
  headless: true,
})
```

`page.setInputFiles('input[type=file]', path)` works on the hidden attachment inputs. Radix selects open with a click on the trigger, options are `getByRole("option")`. Uploads round-trip to real Cloudflare R2 (`temari-et-local` bucket) even in dev — clean up test uploads via the DELETE endpoints afterwards.

## Known noise
- `tsc --noEmit` currently fails on pre-existing `catalogs` errors (untracked in-progress work) — filter those out before judging a change.
- The Next dev overlay shows a persistent "1 Issue" badge from the same pre-existing errors.
