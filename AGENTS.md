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
   - Role-specific tour steps in `pwa-tour.js`

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

- `npm run build` – Vite production build with code-splitting (vendor + admin-tour chunks)
- `composer run dev` – Runs Laravel server, Horizon, Pail, and Vite dev server concurrently
- Horizon dashboard is available at `/horizon` (middleware: `web`)
