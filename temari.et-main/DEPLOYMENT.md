# Deployment

Two independent deployments:

| Part | Where | How |
|------|-------|-----|
| `backend/` (Laravel API) | Coolify (self-hosted VPS) | Nixpacks build pack — `backend/nixpacks.toml` |
| `frontend/` (Next.js) | Cloudflare Workers | OpenNext adapter — `frontend/wrangler.jsonc` |

---

## Backend → Coolify (Nixpacks)

### Coolify application settings

- **Build pack:** Nixpacks
- **Base directory:** `/backend`
- **Health check path:** `/up`
- **Port:** leave default — nginx binds to Coolify's injected `$PORT`

`backend/nixpacks.toml` does the rest: composer install (`--no-dev`), Vite asset
build, then at boot runs `storage:link`, config/route/view/event caches and
`migrate --force`, and starts **supervisor** with five programs in one container:

1. `worker-nginx` — public entrypoint on `$PORT`
2. `worker-phpfpm` — PHP-FPM (64M uploads, 512M memory)
3. `worker-laravel` — 2× `queue:work` (database queue)
4. `worker-scheduler` — `schedule:work` (attendance auto-absent, recurring fees, penalties, reminders…)
5. `worker-reverb` — `reverb:start` on 127.0.0.1:8080 (chat websockets, ADR-019).
   nginx proxies `/app` (WS handshake) and `/apps` (publish API) to it, so
   `wss://api.temari.et/app/{key}` rides the same domain and the TLS Traefik
   already terminates — no extra Coolify service or subdomain needed.

PHP version comes from `composer.json` (`^8.4` → PHP 8.4). Nix PHP ships with
`pdo_pgsql`, `intl`, `bcmath`, `gd`, `zip`, `pcntl` etc. — no extra extension config needed.

> **Do not add a `pnpm-lock.yaml` to `backend/`.** The asset build intentionally
> uses npm (`package-lock.json`): a pnpm lockfile makes nixpacks install pnpm via
> nix, which fails the image build with a `LICENSE` file collision against the
> composer package. Node is pinned to 22 in `nixpacks.toml` (Vite 8 requirement).

### Required environment variables (set in Coolify)

Generate the key locally with `php artisan key:generate --show`.

```env
APP_NAME=Temari.et
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # php artisan key:generate --show
APP_URL=https://api.temari.et
FRONTEND_URL=https://app.temari.et
ADMIN_PASSWORD=...            # required for destructive operator commands

LOG_CHANNEL=stderr            # container logs → Coolify log viewer
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=...                   # Coolify PostgreSQL resource (internal network hostname)
DB_PORT=5432
DB_DATABASE=temari
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# Cloudflare R2 (private files, signed URLs)
CLOUDFLARE_R2_PRIVATE_KEY=...
CLOUDFLARE_R2_PRIVATE_SECRET=...
CLOUDFLARE_R2_PRIVATE_REGION=auto
CLOUDFLARE_R2_PRIVATE_BUCKET=temari-et
CLOUDFLARE_R2_PRIVATE_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
FILESYSTEM_DISK=r2

# Cloudflare Browser Rendering (official PDFs)
CLOUDFLARE_ACCOUNT_ID=...
CLOUDFLARE_API_TOKEN=...

# SMS (tiltek.et)
SMS_ENABLED=true
SMS_BASE_URL=https://tiltek.et
SMS_ACCOUNT_ID=...
SMS_TOKEN=...
SMS_CODE_ID=...

# check.et payment verification
CHECK_ET_API_KEY=chk_...
CHECK_ET_ENABLED=true

# Chat websockets (Laravel Reverb — the worker-reverb supervisor program).
# The app publishes to Reverb over loopback; clients connect through nginx
# on the public domain. Generate ID/KEY/SECRET once (any random values:
# 6-digit id, 20-hex key, 32-hex secret) and mirror the KEY in the frontend.
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=127.0.0.1        # where the API publishes (loopback → worker-reverb)
REVERB_PORT=8080
REVERB_SCHEME=http

MAIL_MAILER=...               # real mailer creds when ready; `log` until then
```

Notes:

- Migrations run automatically on every deploy (`migrate --force` in the start
  script), followed by `db:seed --class=RolePermissionSeeder --force` — the
  role → permission catalog is code-owned, so every deploy re-syncs it from
  `RolePermissionSeeder`. (Both commands need `--force`: `APP_ENV=production`
  makes them prompt, and the container has no TTY, so an unforced command exits
  non-zero and `set -e` kills the boot before supervisor starts.) Editing role
  permissions directly in the production DB is therefore pointless — the next
  deploy reverts them; change the seeder instead.
- First deploy against an empty DB: run the remaining platform seed data once
  from Coolify's terminal — `php artisan db:seed --force` (grade levels,
  subjects, banks, school directory, health conditions, grading scales, and the
  super-admin account from `SUPER_ADMIN_*`). Set `SUPER_ADMIN_PASSWORD` before
  running it — the fallback is `12345678`.
- `bootstrap/app.php` trusts the reverse proxy (`trustProxies '*'`) so signed
  URLs and `/verify/{token}` QR links generate with the correct `https://` origin.
- Scale note: when Redis is added later, flip `QUEUE_CONNECTION` /
  `CACHE_STORE` to `redis` — the workers need no config change.

---

## Frontend → Cloudflare Workers (OpenNext)

Adapter: [`@opennextjs/cloudflare`](https://opennext.js.org/cloudflare) — config
in `frontend/open-next.config.ts` + `frontend/wrangler.jsonc` (worker name
`temari-frontend`, assets served from `.open-next/assets`).

### Deploy

```bash
cd frontend
pnpm deploy:cf        # opennextjs-cloudflare build && deploy
```

Other scripts: `pnpm preview:cf` (local Workers runtime preview),
`pnpm upload:cf` (upload a new version without routing traffic — for
gradual deployments), `pnpm cf-typegen` (regenerate `CloudflareEnv` types).

First deploy needs `npx wrangler login` (or `CLOUDFLARE_API_TOKEN` +
`CLOUDFLARE_ACCOUNT_ID` in CI).

### API URL

`NEXT_PUBLIC_API_URL` is inlined into the client bundle **at build time** —
setting it as a Worker variable does nothing. It lives in
`frontend/.env.production` (git-ignored, created on each deploy machine):

```env
NEXT_PUBLIC_API_URL=https://api.temari.et/api/v1

# Chat websockets — same build-time rule as the API URL. The KEY mirrors the
# backend's REVERB_APP_KEY; host/port point at the PUBLIC API domain (nginx
# proxies /app + /apps to the reverb worker there).
NEXT_PUBLIC_REVERB_APP_KEY=...
NEXT_PUBLIC_REVERB_HOST=api.temari.et
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

Leave `NEXT_PUBLIC_REVERB_APP_KEY` empty to ship without websockets — the app
degrades to HTTP polling silently (polling is always the reliability floor;
the socket is only a latency upgrade).

### Custom domain

Uncomment the `routes` block in `wrangler.jsonc` once the `temari.et` zone is
on the Cloudflare account, or attach the domain in the Cloudflare dashboard
(Workers & Pages → temari-frontend → Domains & Routes).

### Caching

`open-next.config.ts` deliberately skips the incremental cache: the app is an
auth-gated, fully dynamic dashboard. If ISR/SSG pages are added later, wire up
the R2 incremental cache per the OpenNext docs.

---

## Local development — chat websockets (Reverb)

Herd serves the API, but Reverb is its own process. To get realtime chat
locally, run it alongside the queue worker:

```bash
cd backend
php artisan reverb:start          # ws://localhost:8080
php artisan queue:work            # broadcasts + channel fan-out are queued
```

Everything is already wired in the env files: `backend/.env` has
`BROADCAST_CONNECTION=reverb` + generated `REVERB_*` credentials, and
`frontend/.env.local` mirrors the key as `NEXT_PUBLIC_REVERB_*`
(localhost:8080). Without `reverb:start` running, chat still works — the UI
polls every 20–30s; the socket only makes it instant. `--debug` on
`reverb:start` prints every connection and message while developing.
