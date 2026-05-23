# Agent Context

## Authentication & Authorization Patterns

### Dual-Auth Pattern

The codebase uses **two complementary authorization strategies**:

1. **BaseResource (permission-driven)**  
   `App\Filament\Resources\BaseResource` centralizes CRUD policy checks for Filament Resources. It first grants `superadmin` full access, then falls back to `spatie/laravel-permission` checks via the model's `getPermissionName($action)` method (e.g. `members.view`, `members.create`). If the model does not implement `getPermissionName()`, access is denied by default.  
   BaseResource also applies department scoping in `getEloquentQuery()` when the model uses `HasDepartmentTrait` or `ScopedByDepartment`.

2. **Inline role checks (page & UI-level)**  
   Filament Pages, custom form fields, and action visibility often use inline `Auth::user()?->hasRole([...])` checks. This is common for:
   - `canAccess()` on Pages (e.g. `DonationReportPage`, `BeneficiaryReportPage`)
    - Form component `->visible()` / `->disabled()` closures (e.g. in `MemberResource`, `UserResource`)

**Rule of thumb:** Resources extend `BaseResource`; Pages and one-off UI elements use inline role checks.

---

## Widget Factory Architecture

There is **no dedicated widget factory class**. Widget registration follows Filament's native convention:

- `AdminPanelProvider` globally sets `->widgets([])`.
- Individual Pages override widget hook methods:
  - `getHeaderWidgets(): array`
  - `getFooterWidgets(): array`
  - `getWidgets(): array`

Examples:
- `DonationReportPage::getHeaderWidgets()`
- `BeneficiaryReportPage::getHeaderWidgets()`
- `Dashboard::getWidgets()`

If you add a widget, import the class in the appropriate Page and return it from one of these arrays.

---

## Department Scoping Traits

### ScopedByDepartment (primary)
Used by models that need row-level department scoping:
- `App\Models\Department`
- `App\Models\Member`
- `App\Models\Promotion`
- `App\Models\Group`
- `App\Models\Enrollment`

Provides:
- Global scope `DepartmentScope`
- `accessibleByCurrentUser()` query scope
- `canCurrentUserAccess()` instance check
- `withoutDepartmentScope()` / `withAllDepartments()` helpers

### HasDepartmentTrait (legacy)
Exists in `app/Models/Traits/HasDepartmentTrait.php` but is **not currently used by any model**. `BaseResource::getEloquentQuery()` still checks for both traits for backward compatibility.

---

## Phone-Number Formatting Convention

All phone numbers are normalized to the Ethiopian international format (`+251...`) via `App\Services\PhoneFormattingService`.

| Method | Purpose |
|--------|---------|
| `prefix()` | Returns `config('finot.phone_prefix', '+251')` |
| `formatStateUsing($state)` | Strips prefix + leading zeros for **display/editing** |
| `dehydrateStateUsing($state)` | Prepends prefix before **saving** to DB |
| `formatForDisplay($phone)` | Ensures prefix is present for presentation |
| `helperText()` | Returns `'Enter 9 digits after +251'` |

**Usage in forms:**
```php
TextInput::make('phone')
    ->prefix(PhoneFormattingService::prefix())
    ->formatStateUsing(fn ($state) => PhoneFormattingService::formatStateUsing($state))
    ->dehydrateStateUsing(fn ($state) => PhoneFormattingService::dehydrateStateUsing($state))
```

Raw 9-digit input (e.g. `911234567`) is stored as `+251911234567`.

---

## Build & Dev Commands

- `npm run build` – Vite production build with code-splitting (vendor chunk)
- `composer run dev` – Runs Laravel server, Horizon, Pail, and Vite dev server concurrently
- Horizon dashboard is available at `/horizon` (middleware: `web`)

## Product Tour / Onboarding System

A driver.js-based product tour system provides role-aware onboarding, contextual tours, and feature discovery.

### Architecture

- **Config-driven tours** — All tours defined in `config/product-tour.php` with role/page mapping
- **Backend services** — `app/Services/ProductTour/` contains `ProductTourService`, `TourRegistry`, `TourAnalyticsService`, `TourStateService`, `FeatureDiscoveryService`
- **API endpoints** — Throttled Sanctum-authenticated endpoints at `/api/product-tour/*`

### Database Tables

| Table | Purpose |
|-------|---------|
| `product_tour_completions` | Tracks per-user tour progress (unique on `[user_id, role, panel, tour_key]`) |
| `product_tour_analytics` | Event log for all tour interactions (started, completed, skipped, etc.) |

### Frontend Structure (`resources/js/tours/`)

```
core/          — TourManager, DriverAdapter, StepRegistry, TourStateManager, AnalyticsManager, DOMObserver, AccessibilityManager, FeatureDiscoveryManager
roles/         — Per-role onboarding tours (superAdmin, admin, finance, hr, registrar, teacher, parent)
pages/         — Page-level contextual tours (dashboard, members, donations, attendance, financeOverview)
components/    — TourTooltip, TourProgress, TourControls, TourBadge
styles/        — tours.css, dark-mode.css, mobile.css
```

### Filament Integration

- **Render hook** `BODY_START` outputs `filament.components.tour-init` (hidden root div + whats-new modal + Vite assets)
- **User menu** "Restart Tour" item (visible when tours are enabled)
- **Dashboard widget** `OnboardingProgressWidget` shows per-tour status for superadmin/admin
- **SPA reinitialization** — `filament-init.js` listens for Livewire navigated events and re-runs tour checks

### CSS Entry Points

Imported via `filament-init.js`:
- `resources/css/tours/tours.css` — Base tooltip/progress/controls styles
- `resources/css/tours/dark-mode.css` — Dark mode overrides
- `resources/css/tours/mobile.css` — Responsive adaptations

### Vite Code Splitting

Tour code splits into chunks:
- `tour-vendor` — driver.js library
- `tour-core` — Core engine modules
- `tour-roles` — Role-based tour definitions (lazy loaded)
- `tour-pages` — Page-level tour definitions (lazy loaded)
- `tour-main` / `tour-components` — Bootstrap and UI components

### Custom Data Attributes

Use `data-tour`, `data-tour-group`, `data-tour-role` on DOM elements for stable step selectors. NEVER use nth-child or generated IDs.

### Env Controls

| Variable | Default | Purpose |
|----------|---------|---------|
| `PRODUCT_TOUR_ENABLED` | `true` | Global toggle |
| `PRODUCT_TOUR_VERSION` | `1.0.0` | Bump to trigger update tours |

## Performance Optimization

### Critical Fixes Applied

1. **Session cleanup moved out of request lifecycle** (`SessionTimeoutMiddleware`) — The `DELETE FROM user_sessions WHERE last_activity < ?` query ran on EVERY web request. Moved to `session:cleanup` scheduled command (every 5 min).
2. **Added database index** on `user_sessions.last_activity` for fast cleanup queries.
3. **Font 404 eliminated** — Changed from Bunny Fonts CDN to `LocalFontProvider` for `Noto Sans Ethiopic`, removing render-blocking external font request.
4. **Dashboard cache warming** — `dashboard:cache-warm` command pre-fills all widget caches every 5 minutes, so first page load after deploy doesn't run cold queries.
5. **Static asset caching** — `.htaccess` configured with `Cache-Control: public, max-age=31536000, immutable` for all CSS/JS/font/image assets.
6. **Production Vite build** — Code-splitting with vendor chunk.

### Performance Cache Commands

After deploying or updating config/routes, run in order:
```bash
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan event:cache
npm run build
```

> **Note:** `php artisan view:cache` is incompatible with Filament Blade components and will fail. Do not run it.

### PHP Configuration (XAMPP/Production)

Enable **OPcache** in `php.ini` for significant PHP file compilation speedup (1-3s TTFB improvement):
```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### Scheduler (Required)

The `session:cleanup` and `dashboard:cache-warm` commands run via the Laravel scheduler. Ensure the scheduler is running:
```bash
php artisan schedule:work
```
Or add this cron entry on production:
```
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```
