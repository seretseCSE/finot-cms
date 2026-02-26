# FINOT CHURCH MANAGEMENT SYSTEM
> 60-Day Detailed Developer Implementation Plan

> Stack: Laravel 12  ·  Filament 5  ·  Driver.js  ·  PWA  ·  Ethiopian Calendar  ·  MySQL  ·  Every feature, field, rule & validation documented


## Overview

| Phase | Focus Area | Duration |
|-------|-----------|----------|
| Phase 1 · Days 1–7 | Foundation & Infrastructure | 7 days |
| Phase 2 · Days 8–17 | Membership, Parents & Groups | 10 days |
| Phase 3 · Days 18–29 | Education & Sunday School | 12 days |
| Phase 4 · Days 30–36 | Contributions, Donations & Finance | 7 days |
| Phase 5 · Days 37–41 | Tours & Registration | 5 days |
| Phase 6 · Days 42–46 | Worship, Rehearsals & Media | 5 days |
| Phase 7 · Days 47–50 | Inventory & Archives | 4 days |
| Phase 8 · Days 51–54 | Events, Fundraising & Charity | 4 days |
| Phase 9 · Days 55–58 | Security, Audit & Notifications | 4 days |
| Phase 10 · Days 59–60 | Public Website & Final Polish | 2 days |

### Day 1 – Project Bootstrap & Environment Setup
*Phase 1 – Foundation · Dependencies:
None – Starting point*

**Project Scaffolding**

- Run: composer create-project laravel/laravel finot-cms && cd finot-cms
- Install Filament 5: composer require filament/filament && php artisan filament:install --panels
- Set panel ID to "admin" in AdminPanelProvider, configure path to /admin
- Install spatie/laravel-permission: composer require spatie/laravel-permission
- Install andegna/ethiopian-calendar: composer require andegna/ethiopian-calendar
- Install intervention/image for file processing: composer require intervention/image
- Install maatwebsite/excel for exports: composer require maatwebsite/excel
- Install barryvdh/laravel-dompdf for PDF exports: composer require barryvdh/laravel-dompdf
- Install league/flysystem-local for file storage
- Run: php artisan icons:cache (for Filament icons)

**.env & Configuration**

- Set APP_NAME="FINOT Church Management System", APP_ENV=production, APP_DEBUG=false
- Set DB_CONNECTION=mysql, DB_HOST, DB_PORT=3306, DB_DATABASE=finot_cms
- Set APP_TIMEZONE=Africa/Addis_Ababa in config/app.php
- Set QUEUE_CONNECTION=database (for background jobs like exports)
- Set SESSION_LIFETIME=30 (30-minute inactivity timeout)
- Set FILESYSTEM_DISK=local for all file uploads (no cloud in MVP)
- Set APP_LOCALE=am (Amharic default), APP_FALLBACK_LOCALE=en
- Configure mail driver as "log" for MVP (no real email sending)
- Set SESSION_DRIVER=database for session tracking

**Base Migrations**

- Run: php artisan migrate (creates default Laravel tables)
- Run: php artisan queue:table && php artisan migrate (jobs table)
- Run: php artisan session:table && php artisan migrate (sessions table)
- Run: php artisan notifications:table && php artisan migrate (notifications table)
- Create departments migration: id, name_en, name_am, is_active, timestamps
- Create users migration modifications: add phone VARCHAR(20) UNIQUE NOT NULL, add is_active BOOLEAN default true, add is_locked BOOLEAN default false, add temp_password_changed BOOLEAN default false, add failed_login_attempts INT default 0, add locked_until TIMESTAMP nullable, add department_id FK, add language_preference ENUM(am,en) default am
- Run: php artisan vendor:publish --tag=filament-config
- Run: php artisan vendor:publish --tag=spatie-permission-migrations && php artisan migrate

**AppServiceProvider Setup**

- Register EthiopianDateHelper as singleton in AppServiceProvider
- Register Carbon macro: ethiopian() → returns converted Ethiopian date string
- Set default string length to 191 for MySQL compatibility: Schema::defaultStringLength(191)
- Register custom validation rule: ethiopian_date for date validation
- Register FilamentServiceProvider customizations
- Configure Filament default avatar provider

**File Storage Structure**

- Create storage directories: storage/app/public/members, /tours, /media, /documents, /library, /blog, /events, /fundraising, /songs, /inventory, /receipts
- Run: php artisan storage:link
- Configure filesystems.php with named disks per module
- Set max file sizes in config: photos=10MB, videos=50MB, audio=20MB, documents=unlimited

### Day 2 – Ethiopian Calendar Integration
*Phase 1 – Foundation · Dependencies:
Day 1*

**EthiopianDateHelper Class (app/Helpers/EthiopianDateHelper.php)**

- Method: toEthiopian(Carbon|string $gregorianDate): array → returns [year, month, day, month_name_am, month_name_en]
- Method: toGregorian(int $year, int $month, int $day): Carbon
- Method: validate(int $year, int $month, int $day): bool → validates month 1–13, day 1–30 (months 1–12), day 1–5/6 (Pagume based on leap year)
- Method: isLeapYear(int $ethiopianYear): bool → Ethiopian leap year every 4 years
- Method: formatDisplay(Carbon $date, string $locale = "am"): string → "01 መስከረም 2017"
- Method: getCurrentEthiopianYear(): int
- Method: getMonthName(int $month, string $locale): string → returns Amharic or English month name
- Method: getMonthsForContribution(): array → returns 12 months (excludes Pagume)
- Method: getAllMonths(): array → returns all 13 months (for general pickers)
- Constants: MONTH_NAMES_AM array [Meskerem=>መስከረም, Tikimt=>ጥቅምት, Hidar=>ኅዳር, Tahsas=>ታኅሣሥ, Tir=>ጥር, Yekatit=>የካቲት, Megabit=>መጋቢት, Miazia=>ሚያዝያ, Ginbot=>ግንቦት, Sene=>ሰኔ, Hamle=>ሐምሌ, Nehasse=>ነሐሴ, Pagume=>ጳጉሜን]
- Constants: MONTH_NAMES_EN array (Meskerem, Tikimt, Hidar, ...)

**EthiopianDatePicker Filament Component (app/Filament/Forms/Components/EthiopianDatePicker.php)**

- Extend Filament DatePicker component
- Override displayFormat() to show Ethiopian date format
- Override dehydrateStateUsing() to convert Ethiopian → Gregorian before DB save
- Override hydrateStateUsing() to convert Gregorian → Ethiopian when loading form
- Add excludePagume() modifier → removes Pagume from month dropdown (for contribution pickers)
- Add showAllMonths() modifier → shows all 13 months (default behavior)
- Render custom Alpine.js picker: year/month/day dropdowns in Ethiopian format
- Client-side validation: prevent day > 30 for months 1–12, day > 6 for Pagume
- Support both Amharic and English label display based on session locale

**Ethiopian Date Blade Partial (resources/views/components/ethiopian-date.blade.php)**

- Accept $date (Carbon/string), $format (short/long/full), $locale (am/en)
- Short: "01/01/2017", Long: "01 Meskerem 2017", Full: "Monday, 01 Meskerem 2017 E.C."
- Register as Blade component: <x-ethiopian-date :date="$member->birth_date" />
- Create EthiopianDateColumn Filament table column for displaying dates in tables

**Validation Rule (app/Rules/EthiopianDate.php)**

- Implement: validate(string $attribute, mixed $value, Closure $fail): void
- Accept format: "YYYY-MM-DD" in Ethiopian calendar
- Validate year range: 1900–2100 Ethiopian
- Validate month: 1–13
- Validate day: 1–30 for months 1–12, 1–5 or 1–6 for Pagume
- Error message in both Amharic and English
- Register in AppServiceProvider as Validator::extend("ethiopian_date", ...)

**Unit Tests (tests/Unit/EthiopianDateTest.php)**

- Test toEthiopian: Gregorian 2024-09-11 → Ethiopian 2017-01-01 (Meskerem 1)
- Test toGregorian: Ethiopian 2017-01-01 → Gregorian 2024-09-11
- Test leap year: Ethiopian 2011 is leap year (Pagume has 6 days)
- Test Pagume day 6 valid in leap year, invalid in non-leap year
- Test Pagume day 7 always invalid
- Test month 13 day 31 always invalid
- Test edge case: Ethiopian new year calculation
- Test month names returned correctly in both locales
- Test excludePagume returns exactly 12 months

### Day 3 – Phone-Based Authentication System
*Phase 1 – Foundation · Dependencies:
Day 1*

**Login System (Phone Only)**

- Modify Filament login form: replace email field with phone field
- Phone field label: "Phone Number / ስልክ ቁጥር", placeholder: "+251XXXXXXXXX"
- Validate phone format: regex /^\+251[0-9]{9}$/ (Ethiopian mobile numbers only)
- Update User model: change username field from email to phone
- Modify DatabaseUserProvider to find user by phone instead of email
- Create custom FilamentPhoneLoginAction extending Filament default
- Override Filament authentication to use phone+password credential
- Email field on User model: keep as nullable (optional profile metadata only, NOT for login)

**Password Requirements & Validation**

- Minimum 8 characters
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- Create custom PasswordStrength validation rule enforcing all above
- Apply validation on both client side (Alpine.js) and server side
- Cannot reuse last 3 passwords: store password_history JSON column on users table
- When changing password: check bcrypt_check against stored last 3 hashes

**First-Login Password Change**

- Check temp_password_changed flag on every authenticated request (middleware)
- Create ForcePasswordChange middleware: if flag is false → redirect to /admin/change-password
- Build ChangeInitialPassword Filament page: current password + new password + confirm
- On successful change: set temp_password_changed = true, add old hash to password_history
- Register middleware in AdminPanelProvider for all authenticated routes

**Session Timeout (30 minutes)**

- Set SESSION_LIFETIME=30 in .env
- Create SessionActivityMiddleware: update last_activity timestamp on every request
- Auto-logout after 30 minutes of inactivity (no requests made)
- Show "Session expired" flash message on redirect to login
- Exempt: API endpoints for PWA background sync (use token auth separately)

**Progressive Account Lockout**

- Track failed_login_attempts and locked_until on users table
- After 5 failed attempts: set locked_until = now() + 1 minute
- For all subsequent groups of 5 failed attempts: set locked_until = now() + 5 minutes
- On each login attempt: check if locked_until > now() → show "Account locked, try in X minutes"
- On successful login: reset failed_login_attempts = 0, locked_until = null
- Log all failed login attempts to Tier 1 audit log (30-day retention)
- Display remaining lockout time in login error message

**Manual Account Lock/Unlock**

- is_locked boolean column on users table (separate from auto-lockout)
- Admin/Superadmin can set is_locked = true manually
- Locked users cannot log in regardless of lockout timer
- Show "Account disabled" message (different from lockout message)
- Unlock action available on UserResource for Admin/Superadmin
- Log manual lock/unlock to Tier 1 audit log

### Day 4 – RBAC – Roles, Departments & Permissions
*Phase 1 – Foundation · Dependencies:
Day 3*

**Department Seeder (database/seeders/DepartmentSeeder.php)**

- Seed exactly 7 FIXED departments (hardcoded, no UI to add/remove):
- 1. Internal Relations (ውስጣዊ ግንኙነት) – manages HR, AV, media, blog
- 2. Nibret ena Hisab (ንብረትና ሂሳብ) – Finance and Inventory
- 3. Education (ትምህርት) – Sunday school, classes, teachers
- 4. Revenue & Charity (ገቢና ልግስና) – Charity, Tours
- 5. Mezmur (መዝሙር) – Worship/Choir
- 6. Foreign Affairs (የውጭ ጉዳይ) – Generic department
- 7. Kinetibeb (ቅን ጠባይ) – Generic department
- Admin is NOT a department (it is a role only) – do not seed Admin as department

**Role Definitions (16 roles)**

- Create all 16 roles using Spatie: superadmin, admin, hr_head, finance_head, nibret_hisab_head, inventory_staff, education_head, education_monitor, worship_monitor, mezmur_head, av_head, charity_head, tour_head, internal_relations_head, department_secretary, staff
- Superadmin: wildcard permission (*) – full system access including system settings, backups
- Admin: all operational permissions across all departments EXCEPT system settings, backups, error logs
- HR Head: member CRUD, group CRUD, group assignment, member export – scoped to all members
- Finance Head: contribution amounts, donations CRUD, financial reports, export – dept scoped
- Nibret Hisab Head: all Finance Head permissions + inventory CRUD + inventory reports
- Inventory Staff: inventory CRUD, movements, analytics – dept scoped
- Education Head: academic year CRUD, class/subject CRUD, enrollment, promotion, teacher management, unlock attendance, view teacher reports
- Education Monitor: attendance session create/mark, lock sessions, view sync conflicts
- Worship Monitor: song CRUD, rehearsal schedule, rehearsal attendance, media visibility (own)
- Mezmur Head: all Worship Monitor permissions + manage song/rehearsal
- AV Head: media CRUD, blog posts, announcements, FAQ, media categories
- Charity Head: beneficiary CRUD, aid distribution, contribution recording (members only), view reports
- Tour Head: tour CRUD, registration management, attendance, tour reports
- Internal Relations Head: member group CRUD (all departments), media delete, document management
- Department Secretary: create/update only (NO delete) for their department resources
- Staff: read-only access to own department resources

**Permission Matrix Implementation**

- Create RoleSeeder: define all permission strings e.g. members.create, members.update, members.delete, members.view, groups.create, etc.
- Build permission naming convention: {resource}.{action} e.g. contributions.record, tours.create, attendance.mark
- Assign permission sets to each role via givePermissionTo() in seeder
- Department Head inherits all Sub-Department Head permissions: e.g. Internal Relations Head has all HR Head + AV Head permissions
- Department Secretary: receives .create and .update permissions only for their dept resources
- Use Filament canCreate(), canEdit(), canDelete(), canViewAny() overrides in each Resource
- Create HasDepartmentScope trait: applies Eloquent global scope based on auth user department_id

**Global Department Scope**

- Create DepartmentScope class implementing Scope interface
- apply() method: adds WHERE department_id = auth()->user()->department_id to all queries
- Exception: Superadmin and Admin bypass scope (check role before applying)
- Apply scope via ScopedByDepartment trait on relevant models
- Models that need dept scope: Member, Document, AttendanceSession, InventoryItem, etc.
- Models that do NOT need dept scope: AcademicYear, Class, Subject, Tour (shared resources)

### Day 5 – User Model, Seeder & Base Relationships
*Phase 1 – Foundation · Dependencies:
Day 4*

**User Model Enhancements**

- Add to User model: belongsTo(Department::class), hasRole() via Spatie HasRoles trait
- Add accessors: getDisplayNameAttribute(), getEthiopianJoinDateAttribute()
- Add method: isActive() → returns is_active boolean
- Add method: getDepartmentScope() → returns department_id for query scoping
- Override: canAccessPanel(Panel $panel) → check is_active && temp_password_changed
- Add language_preference column and getPreferredLocale() method
- Add password_history JSON column, addToPasswordHistory() method
- Cast columns: is_active (boolean), is_locked (boolean), temp_password_changed (boolean)

**User Seeder (16 test users per spec)**

- superadmin@finot.org → role: superadmin, phone: +251911000001, no department
- admin@finot.org → role: admin, phone: +251911000002, no department
- hr_head@finot.org → role: hr_head, phone: +251911000003, dept: Internal Relations
- finance_head@finot.org → role: finance_head, phone: +251911000004, dept: Nibret ena Hisab
- nibret_hisab_head@finot.org → role: nibret_hisab_head, phone: +251911000005, dept: Nibret ena Hisab
- inventory_staff@finot.org → role: inventory_staff, phone: +251911000006, dept: Nibret ena Hisab
- education_head@finot.org → role: education_head, phone: +251911000007, dept: Education
- education_monitor@finot.org → role: education_monitor, phone: +251911000008, dept: Education
- worship_monitor@finot.org → role: worship_monitor, phone: +251911000009, dept: Mezmur
- mezmur_head@finot.org → role: mezmur_head, phone: +251911000010, dept: Mezmur
- av_head@finot.org → role: av_head, phone: +251911000011, dept: Internal Relations
- charity_head@finot.org → role: charity_head, phone: +251911000012, dept: Revenue & Charity
- tour_head@finot.org → role: tour_head, phone: +251911000013, dept: Revenue & Charity
- internal_relations_head@finot.org → role: internal_relations_head, phone: +251911000014, dept: Internal Relations
- department_secretary@finot.org → role: department_secretary, phone: +251911000015, dept: Education (example)
- staff@finot.org → role: staff, phone: +251911000016, dept: Education (example)
- All seeded with temp password "Admin1234" and temp_password_changed = false

**Department Model**

- Model: Department (id, name_en, name_am, is_active, timestamps)
- Relationships: hasMany(User::class), hasMany(Document::class)
- Scope: active() → whereIsActive(true)
- Cast is_active as boolean
- No soft deletes (departments are hardcoded, never deleted)

**Base Model Traits**

- Create HasEthiopianDates trait: adds ethiopianCreatedAt() and ethiopianUpdatedAt() accessors
- Create HasAuditLog trait: overrides boot() to listen for created/updated/deleted events and log to audit_logs
- Create ScopedByDepartment trait: applies DepartmentScope global scope
- Create GeneratesAutoId trait: boot() method to auto-generate IDs in M-000001 format
- Apply HasAuditLog to: Member, Contribution, Donation, GroupAssignment, AcademicYear models
- Apply ScopedByDepartment to: Member, Document, MediaItem, Song, AttendanceSession models

### Day 6 – Filament Panel Configuration & Navigation
*Phase 1 – Foundation · Dependencies:
Day 5*

**AdminPanelProvider Configuration**

- Set panel ID: admin, path: /admin
- Configure login: use custom FilamentPhoneLogin page
- Set brand name: "FINOT ቤ/ክ" (short form), full name in profile
- Upload church logo: configure brandLogo() to serve from storage
- Set brandLogoHeight: 40px
- Configure colors: primary = #1B4F72, danger = #C0392B, success = #1E8449, warning = #D4AC0D
- Set font: Noto Sans Ethiopic (supports Amharic/Geez) + Noto Sans for English
- Configure default avatar via UI Avatars (initials from user name)
- Set topNavigation: false (use sidebar navigation)
- Configure collapsibleNavigationGroups: true
- Register global search: enable with ->globalSearch()

**Role-Based Navigation Groups**

- Navigation group "Membership" → show only for: admin, superadmin, hr_head, internal_relations_head
- Navigation group "Education" → show only for: admin, superadmin, education_head, education_monitor
- Navigation group "Finance" → show only for: admin, superadmin, finance_head, nibret_hisab_head, charity_head
- Navigation group "Tours" → show only for: admin, superadmin, tour_head
- Navigation group "Worship & Media" → show only for: admin, superadmin, worship_monitor, mezmur_head, av_head
- Navigation group "Inventory" → show only for: admin, superadmin, inventory_staff, nibret_hisab_head
- Navigation group "Archives" → show only for: admin, superadmin, department heads, department secretaries
- Navigation group "Events & Fundraising" → show only for: admin, superadmin
- Navigation group "Charity" → show only for: admin, superadmin, charity_head
- Navigation group "Security" → show only for: admin, superadmin
- Navigation group "System" → show only for: superadmin
- Implement canAccess() on each Resource using auth()->user()->hasRole([...])

**Dashboard Shell**

- Create base Dashboard page with role-based widget registration
- Superadmin Dashboard: system health, total users, audit log summary, error rate
- Admin Dashboard: member count, pending custom options badge, recent registrations, active tours
- Department-specific dashboards: render different widgets based on role
- Create DashboardWidgetFactory: returns widget array based on authenticated user role
- All widgets use Filament Stats Overview or Charts widgets
- Add "Pending Custom Options" badge widget visible to Admin (shows count)

**Language & Locale Middleware**

- Create SetLocaleMiddleware: reads user language_preference from DB (staff) or cookie (guests)
- Register in Kernel.php as global web middleware
- Set App::setLocale() based on resolved locale
- Create resources/lang/am/ directory with all translation keys
- Create resources/lang/en/ directory (fallback)
- Key translation files: auth.php, validation.php, members.php, education.php, finance.php, navigation.php

### Day 7 – Session Management, Profile & Driver.js Skeleton
*Phase 1 – Foundation · Dependencies:
Day 6*

**Active Session Tracking**

- Create user_sessions table: id, user_id FK, session_token VARCHAR(255), device_info (user agent), ip_address, last_activity TIMESTAMP, created_at
- Create RecordUserSession listener: fires on Login event, inserts row into user_sessions
- Enforce max 3 devices: before inserting, count active sessions for user. If = 3 → delete oldest session first
- On Logout event: delete the matching session_token row
- On session timeout (30 min): middleware deletes expired session row
- Define "active" as last_activity within last 30 minutes

**Manage Sessions Page (Filament)**

- Create ManageActiveSessions Filament page (custom page, not resource)
- Display table: Device Info (parsed user agent: browser + OS), IP Address, Last Active (Ethiopian format), Location (current session marker)
- Show "Current" badge on the active session
- Revoke button on each non-current session row: deletes session, invalidates token
- Cannot revoke current session from this page (only from Logout)
- Page accessible by all authenticated users under "My Account" navigation

**My Profile Edit Page (Filament)**

- Create EditProfile Filament page with form fields:
- Display Name (read-only, taken from member profile or user creation name)
- Phone Number: editable, must still be unique, Ethiopian format validation
- Email: optional, no login function, informational only
- Language Preference: select between Amharic (አማርኛ) and English
- Current Password + New Password + Confirm (with PasswordStrength rule)
- On save: if language changed → reload page with new locale
- Show Ethiopian date of account creation

**Driver.js Product Tour Infrastructure**

- Install Driver.js via CDN in Filament layout: <script src="https://cdn.jsdelivr.net/npm/driver.js@latest/dist/driver.js.iife.js">
- Include CSS: <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@latest/dist/driver.css"/>
- Create app/Filament/Widgets/ProductTourWidget.php: Filament View widget
- Create JS file: public/js/tours/{role}.js for each role (16 files)
- Create product_tour_completed table: user_id, tour_id, completed_at
- Middleware: after login, if no completed tour for user role → inject tour JS into page
- Each tour file exports: array of steps with element selector, title, description, side
- Add "Restart Tour" button in user profile dropdown for re-running the tour

**PWA Manifest Skeleton**

- Create public/manifest.json with: name, short_name, start_url: /admin, display: standalone, theme_color: #1B4F72, background_color: #FFFFFF, icons array (192x192, 512x512)
- Create public/service-worker.js (empty skeleton, routes to be added on Day 60)
- Add <link rel="manifest" href="/manifest.json"> to main layout
- Create install_prompt_shown table: user_identifier (cookie/user_id), visit_count, prompt_shown_at
- Add Alpine.js install prompt logic: show banner after 3rd visit, dismissable (hide 7 days cookie)

### Day 8 – Member Model, Migration & Auto-ID
*Phase 2 – Membership · Dependencies:
Day 5*

**Members Migration (Full Schema)**

- Table: members
- id (bigint PK auto-increment), member_code VARCHAR(10) UNIQUE (M-000001 format)
- member_type ENUM(Kids, Youth, Adult) NOT NULL
- status ENUM(Draft, Member, Active, Former) DEFAULT Draft
- -- Common Required Fields:
- title VARCHAR(50) (Mr/Mrs/Dr/Dn/etc.), first_name VARCHAR(100), father_name VARCHAR(100), grandfather_name VARCHAR(100), mother_name VARCHAR(100)
- date_of_birth DATE (stored Gregorian), gender ENUM(Male, Female)
- christian_name VARCHAR(100) nullable
- -- Address:
- city VARCHAR(100), sub_city VARCHAR(100), woreda VARCHAR(50), zone VARCHAR(100) nullable, block VARCHAR(50) nullable, neighborhood VARCHAR(200) nullable
- -- Contact:
- phone VARCHAR(20) UNIQUE NOT NULL, email VARCHAR(191) nullable
- -- Emergency:
- emergency_contact_name VARCHAR(200), emergency_contact_phone VARCHAR(20)
- -- Spiritual:
- confession_father_name VARCHAR(200) nullable, confession_father_phone VARCHAR(20) nullable
- -- Kids-specific:
- spiritual_education_level VARCHAR(100) nullable, special_talents TEXT nullable
- -- Youth/Adult-specific (all nullable for backward compat):
- family_size INT nullable, brothers_count INT nullable, sisters_count INT nullable
- family_confession_father VARCHAR(200) nullable, sunday_school_entry_year DATE nullable
- past_service_departments TEXT nullable
- occupation_status ENUM(Student, Employee) nullable
- employment_status VARCHAR(100) nullable, company_name VARCHAR(200) nullable, job_role VARCHAR(200) nullable, company_address TEXT nullable
- marital_status ENUM(Single, Married) nullable
- marriage_year DATE nullable, spouse_name VARCHAR(200) nullable, spouse_phone VARCHAR(20) nullable, children_count INT nullable
- -- Metadata:
- photo VARCHAR(500) nullable, consent_for_photography BOOLEAN DEFAULT false
- department_id BIGINT FK nullable (which dept manages this member)
- deleted_at TIMESTAMP nullable (soft delete), created_at, updated_at

**Auto-ID Generation (M-000001)**

- Create GenerateMemberCode observer in MemberObserver::creating()
- Query: SELECT MAX(id) FROM members → pad to 6 digits with leading zeros → prefix M-
- Use database transaction + SELECT FOR UPDATE to prevent race conditions
- Alternatively: use separate sequences table with atomic increment
- Store in member_code column (not the PK)
- member_code is read-only after generation (cannot be changed)
- Display member_code as the primary identifier throughout the UI

**Member Model Relationships**

- hasMany(ParentGuardian::class) through member_parent_guardian pivot
- hasMany(MemberGroupAssignment::class)
- hasOne(CurrentGroupAssignment::class) → latest active group assignment
- hasMany(StudentEnrollment::class)
- hasMany(Contribution::class)
- hasMany(AttendanceRecord::class)
- hasMany(TourPassenger::class) via phone matching
- belongsTo(Department::class)
- hasMany(ChildInfo::class) → children repeater data
- hasMany(EducationInfo::class) → student education repeater data

**Related Tables Migrations**

- Table: member_parent_guardians – id, member_id FK, parent_name, relationship ENUM(Father,Mother,Guardian,GrandFather,GrandMother,Uncle,Brother,Aunt,Sister,Other), phone, created_at
- Table: member_children – id, member_id FK, child_name, birth_order INT, created_at
- Table: member_education_history – id, member_id FK, school_name, education_level, education_department, is_current BOOLEAN, created_at
- Table: password_histories – id, user_id FK, password_hash VARCHAR(255), created_at (keep last 3)

### Day 9 – MemberResource – Tabs 1 & 2 (Personal & Address)
*Phase 2 – Membership · Dependencies:
Day 8*

**MemberResource Setup**

- Create app/Filament/Resources/MemberResource.php extending Resource
- Model: Member, navigationIcon: heroicon-o-users
- Navigation group: "Membership", navigation label: "Members / አባላት"
- canViewAny(): hasRole([admin, superadmin, hr_head, education_head, finance_head, charity_head, internal_relations_head, department_secretary, nibret_hisab_head])
- canCreate() / canEdit() / canDelete(): hr_head, admin, superadmin only
- Apply ScopedByDepartment: hr_head sees all, others see dept-scoped
- Default sort: created_at descending
- Enable global search on: first_name, father_name, phone, member_code

**Tab 1 – Personal Information**

- Section header: "Personal Information / የግል መረጃ"
- Title/ማዕረግ: Select field, options: Dn. (ዲ.), Br. (ወ.), Sr. (ሰ.), Mr. (አቶ), Mrs. (ወ/ሮ), Ms. (ወ/ሪት), Dr., Other
- Member Type: Select (Kids/Youth/Adult) – drives conditional field visibility
- First Name / ስም: TextInput, required, maxLength 100
- Father's Name / የአባት ስም: TextInput, required, maxLength 100
- Grandfather's Name / የአያት ስም: TextInput, required, maxLength 100
- Mother's Name / የእናት ስም: TextInput, required, maxLength 100
- Date of Birth / የትውልድ ቀን: EthiopianDatePicker, required, maxDate today
- Gender / ፆታ: Radio (Male/Female), required
- Christian Name / የክርስትና ስም: TextInput, nullable
- Member Code: TextInput, disabled (auto-generated, displayed read-only)
- Status: Select (Draft/Member/Active/Former), default Draft
- Photo: FileUpload, disk: members, acceptedFileTypes: [image/*], maxSize: 5120
- Consent for Photography: Toggle, label "Parent/Guardian has given consent for photography"

**Tab 2 – Address & Contact**

- Section "Residential Address / የመኖሪያ አድራሻ":
- City / የመኖሪያ ከተማ: TextInput, required
- Sub-City / ክ/ከተማ: TextInput, required
- Woreda / ወረዳ: TextInput, required
- Zone/Keten / ቀጠና: TextInput, nullable
- Block / ብሎክ: TextInput, nullable
- Neighborhood Specific Name / የሠፈር ልዩ ስም: TextInput, nullable
- Section "Contact Information / የግንኙነት መረጃ":
- Personal Phone / ስልክ: TextInput, required, regex +251XXXXXXXXX, UNIQUE validation rule
- Email (Optional): TextInput, email type, nullable
- Validation: unique:members,phone,{id} (exclude current record on edit)
- Show real-time phone uniqueness check using Filament live() validation

**MemberResource Table Columns**

- member_code: TextColumn, sortable, searchable, label "Member ID"
- Full Name: TextColumn using getFullNameAttribute() accessor (first + father + grandfather)
- member_type: BadgeColumn, colors: Kids=info, Youth=warning, Adult=success
- status: BadgeColumn, colors: Draft=gray, Member=info, Active=success, Former=danger
- phone: TextColumn, searchable
- Current Group: TextColumn via currentGroup relationship
- created_at: EthiopianDateColumn
- Filters: SelectFilter for status, member_type, group_id
- Bulk actions: Export (triggers MemberExporter)

### Day 10 – MemberResource – Tabs 3 & 4 (Emergency, Spiritual & Kids)
*Phase 2 – Membership · Dependencies:
Day 9*

**Tab 3 – Emergency & Spiritual**

- Section "Emergency Contact / የቅርብ ጓደኛ":
- Emergency Contact Name / የቅርብ ጓደኛ ስም: TextInput, required
- Emergency Contact Phone / የቅርብ ጓደኛ ስልክ: TextInput, required, phone format
- Section "Spiritual Information / መንፈሳዊ መረጃ":
- Confession Father's Name / የንስሀ አባት ስም: TextInput, nullable
- Confession Father's Phone / የንሰሐ አባት ስልክ: TextInput, nullable

**Tab 4 – Kids: Parent/Guardian Information**

- Visible only when member_type = Kids
- Repeater component: "Parent/Guardian Information / የወላጅ/አሳዲጊ መረጃ"
- Minimum 1 parent, maximum 10 parents per member
- Repeater fields per entry:
- – Parent/Guardian Name / ስም: TextInput, required
- – Relationship / ግንኙነት: Select with options: Father, Mother, Guardian, GrandFather, GrandMother, Uncle, Brother, Aunt, Sister, Other (triggers custom text input if Other selected)
- – Phone / ስልክ: TextInput, required, phone format
- Add Another button label: "+ Add Parent/Guardian / ወላጅ/አሳዲጊ ጨምር"
- Delete row button on each repeater entry
- Section "Additional Kids Information":
- Spiritual Education Level / የመንፈሳዊ ት/ት ደረጃ: Select or TextInput (configurable)
- Special Talents / ልዩ ተሰጥዖ: Textarea, nullable

**Tab 4 – Youth/Adult: Family & Occupation**

- Visible only when member_type = Youth OR Adult
- Section "Family Information / የቤተሰብ መረጃ":
- Total Family Size / ቤተሰብ ብዛት: TextInput type=number, min=1
- Number of Brothers / ወንድም ብዛት: TextInput type=number, min=0
- Number of Sisters / እህት ብዛት: TextInput type=number, min=0
- Family Confession Father Name: TextInput nullable
- Sunday School Entry Year / ሰንበት ት/ቤት ዓ.ም: EthiopianDatePicker (year only picker), nullable
- Past Service Departments / ያገለገሉባቸው: Textarea nullable
- Section "Occupation / ሙያ":
- Occupation Status: Radio (Student / Employee)
- IF Student: Education History Repeater (School Name, Level, Dept, is_current toggle)
- IF Employee: Employment Status Select (Hired/Not Hired/Private Sector/Other)
- IF Hired or Private Sector: Company Name (required), Job Role (required), Company Address (textarea)

**Conditional Field Logic (Alpine.js / Filament reactive)**

- Use Filament reactive() fields: visible() callbacks based on sibling field values
- member_type changes: show/hide tab 4 variants without page reload
- occupation_status changes: show/hide student OR employee fields instantly
- employment_status changes: show/hide company details section
- marital_status changes: show/hide spouse + children fields
- children_count changes: dynamically show/hide child name fields in repeater
- Relationship "Other": show inline TextInput for custom relationship type

### Day 11 – MemberResource – Tab 4b Marital/Children, Tab 5 & Type Transition
*Phase 2 – Membership · Dependencies:
Day 10*

**Tab 4 Continued – Marital Status & Children**

- Marital Status / የትዳር ሁኔታ: Select (Single/Married), visible for Youth and Adult only
- IF Married:
- – Marriage Year / ጋብቻ ዓ.ም: EthiopianDatePicker (year only), required
- – Spouse Name / የባለቤት ስም: TextInput, required
- – Spouse Phone / የባለቤት ስልክ: TextInput, phone format
- – Number of Children / ልጆች ብዛት: TextInput type=number, min=0
- – IF children_count > 0: Repeater for child names
- Child Name repeater: dynamic count matches children_count value
- Each row: Child N Name TextInput (label auto-updates: "Child 1 / ልጅ 1")

**Tab 5 – Status & History**

- Member Status: Select (Draft/Member/Active/Former), editable by HR Head only
- Member Since / አባልነት ጀምሮ: EthiopianDatePicker (date they formally became a member)
- Notes: Textarea for HR notes (nullable)
- Read-only section "Assignment History": shows last 5 group assignments with dates
- Link to full timeline: "View Full Timeline →" button
- Cannot change member status to Draft once it was Active or Member (HR Head must justify)
- Status change to Former: requires confirmation dialog "Are you sure?"
- Status changes logged to Tier 2 audit trail automatically

**Kid → Youth/Adult Type Transition**

- HR Head can change member_type from Kids to Youth or Adult on Tab 5 or Tab 1
- On type change: confirmation dialog: "Changing type will show new fields. Existing Kids data is preserved."
- Parent/Guardian records preserved in database (hidden from UI but stored)
- Special Talents and Spiritual Education Level preserved in their columns
- New Youth/Adult tab fields become available and empty (pending data entry)
- Log type transition in Tier 2 audit trail: old_value="Kids", new_value="Youth"
- If member was enrolled in Kids class: enrollment remains, can re-enroll in Youth class for next year

**MemberResource Actions & Guards**

- Delete: soft delete only (sets deleted_at), confirmation required. Former members remain searchable.
- Restore: Admin can restore soft-deleted member
- View: all roles with member access can view, edit restricted to HR Head/Admin/Superadmin
- Export from list: filters respected, scoped to user department
- Infolist (View page): show all fields read-only, with Ethiopian date formatting throughout
- Custom action: "View Timeline" → navigates to member timeline page
- Breadcrumb: Members > [Member Name]

### Day 12 – Parent/Guardian Separate Resource & Pivot Management
*Phase 2 – Membership · Dependencies:
Day 10*

**Parents Table & Model**

- Table: parents – id, full_name VARCHAR(200), phone VARCHAR(20) UNIQUE, relationship_type VARCHAR(100), member_count INT (computed), is_active BOOLEAN default true, notes TEXT nullable, created_at, updated_at, deleted_at
- Parents are stored separately from members (not all parents are church members)
- One parent can link to many children (members)
- Parent phone must be unique across all parents
- Model: Parent with hasMany(Member::class) through member_parent_guardian pivot

**ParentResource (Filament)**

- Navigation: under "Membership" group, icon: heroicon-o-heart
- Access: HR Head, Admin, Superadmin
- Form fields: Full Name (required), Phone (required, unique, Ethiopian format), Notes (optional)
- Relationship type shown in context of linked children (not on parent itself)
- Table columns: Full Name, Phone, Linked Children count, Active badge
- Action: "View Linked Children" → opens modal or navigates to filtered member list
- Cannot delete parent if linked to any active member: canDelete() checks member links
- Soft delete: sets is_active = false (parent record preserved for member history)

**Member-Parent Pivot Management**

- Table: member_parent_guardians – id, member_id, parent_id nullable, parent_name VARCHAR(200), relationship ENUM, phone VARCHAR(20), is_external BOOLEAN (true if parent not in parents table), created_at
- Two paths: (1) Link existing parent from parents table by selecting from dropdown, (2) Enter new parent inline (creates entry in parents table automatically)
- On inline entry: check if phone exists in parents table → link existing record, else create new
- Show on Kids tab 4: repeater pulls from member_parent_guardians for this member
- Parent deletion check: cannot delete if child is Active or Member status
- Each member_parent_guardian entry is soft-deleted only (never hard deleted)

### Day 13 – Member Groups – Model, CRUD & Assignment
*Phase 2 – Membership · Dependencies:
Day 8*

**Member Groups Migration**

- Table: member_groups – id, name VARCHAR(200), group_type VARCHAR(100) nullable (e.g. Kids, Youth, Adult, Ministry), description TEXT nullable, is_active BOOLEAN default true, created_by INT FK to users, created_at, updated_at, deleted_at
- Table: member_group_assignments – id, member_id FK, group_id FK, effective_from DATE NOT NULL, effective_to DATE nullable, assigned_by INT FK to users, removed_by INT FK nullable, created_at, updated_at
- Index on member_group_assignments: (member_id, effective_to) for fast active group lookup
- Composite unique constraint NOT applied (historical records allowed for same member + group at different times)

**MemberGroupResource (Filament)**

- Navigation: "Membership" group, icon: heroicon-o-user-group
- canViewAny(): admin, superadmin, hr_head, internal_relations_head
- canCreate() / canEdit(): admin, superadmin, hr_head, internal_relations_head
- canDelete(): admin, superadmin only
- Form: Group Name (required), Group Type (select with Others option), Description (textarea nullable)
- Table: Name, Type badge, Active Member Count (computed), Active badge, Created At
- canDelete() ALSO checks: no active assignments → if any member has effective_to IS NULL for this group → block delete, show "Members still assigned" error
- Soft delete: sets is_active = false, deleted_at, only when no active members

**Assign Member to Group Action**

- Action button "Assign Member" on GroupResource → Edit page → Members tab
- Modal form: Select Member (searchable dropdown, shows unassigned members or those in other groups), Effective From Date (EthiopianDatePicker, defaults to today)
- Business rule: one member = one group at a time. Before saving: check if selected member has any active assignment (effective_to IS NULL) in ANY group → if yes, show error "Member already in group X. Remove them first."
- On save: INSERT into member_group_assignments with effective_to = null
- Log to Tier 2 audit trail: action=group_assigned, entity=member, old_value=null, new_value={group_id, group_name, effective_from}

**Remove Member from Group Action**

- Action button "Remove from Group" on each member row within group
- Confirmation dialog: "Remove [Member Name] from [Group Name]?"
- On confirm: UPDATE member_group_assignments SET effective_to = CURDATE(), removed_by = auth()->id() WHERE member_id = X AND effective_to IS NULL
- Member can be reassigned to same or different group immediately after
- Log to Tier 2 audit trail: action=group_removed, old_value={group_name}, new_value={effective_to date}
- Group assignment history is NEVER deleted (permanent soft record)

### Day 14 – Bulk Assignment & Group History
*Phase 2 – Membership · Dependencies:
Day 13*

**Bulk Assignment Action**

- Implement bulk action on MemberResource list: "Assign to Group"
- On trigger: modal with Group Select + Effective From Date
- Validation before modal: max 100 members selected (show warning if more selected)
- Pre-check before commit: iterate all selected members, verify none have active group assignment
- If ANY member has active assignment: show per-member error list (do not commit any)
- All-or-nothing: wrap entire operation in DB::transaction() with rollback on any error
- On success: create all member_group_assignments rows with same effective_from, assigned_by = auth()->id()
- Show success message: "X members assigned to [Group Name] successfully"
- Show failure message with list: "Assignment failed. The following members already have groups: [list]"
- Log each individual assignment to Tier 2 audit trail within transaction

**Group Assignment History Table**

- Sub-page on GroupResource: "Assignment History" tab
- Show ALL assignments (including those with effective_to set) for this group
- Columns: Member Name (linked), Member Code, Assigned Date (Ethiopian), Removed Date (Ethiopian or "Active"), Duration (calculated), Assigned By, Removed By
- Sort by effective_from DESC (most recent first)
- Filter: Active Only toggle, Date Range
- This view is READ-ONLY (no editing assignments from history)
- Also show on MemberResource view page: "Group History" section with all their past groups

**Active Group Display**

- Current Group computed column on MemberResource: JOIN member_group_assignments WHERE effective_to IS NULL → show group name
- If no group: show "Unassigned" badge in gray
- Filter on MemberResource: "Filter by Group" → Select from all active groups
- MemberGroupAssignment model: scope active() → whereNull('effective_to')
- MemberGroupAssignment model: scope historical() → whereNotNull('effective_to')

### Day 15 – Member Timeline Page
*Phase 2 – Membership · Dependencies:
Day 14*

**ViewMemberTimeline Filament Page**

- Create app/Filament/Resources/MemberResource/Pages/ViewMemberTimeline.php
- Page layout: search/filter bar at top, timeline events below
- MANDATORY filters: must select at least one before results shown
- Filter fields: Name search (first_name LIKE + father_name LIKE), Member ID (exact), Phone (exact), Group name (current or historical), Parent/Guardian name
- Show placeholder when no filter applied: "Please apply at least one filter to view timeline"
- Submit button: "Search Timeline / ፈልግ"

**Timeline Event Types**

- Group Events: join (green icon), removal (orange icon) – from member_group_assignments
- Enrollment Events: enrolled (blue icon), withdrawn (red icon), promoted (purple icon) – from student_enrollments
- Attendance Events: session summary (count present this month) – aggregated from student_attendance
- Contribution Events: payment recorded (green), outstanding noted (yellow) – from contributions
- Status Changes: Draft→Active, Active→Former etc. – from audit_logs Tier 2
- Each event card: Date (Ethiopian), Event Type badge, Description, Performed By (user name)

**Timeline Pagination & Display**

- Default: show latest 10 events across all types, sorted by date DESC
- "Load More" button: loads next 10 events (append to list, do not replace)
- Event type filter tabs: "All | Groups | Education | Attendance | Contributions"
- UNION query: combine all event sources into unified timeline ordered by event_date DESC
- Cache query result for 5 minutes per member_id to prevent slow repeated queries
- Timeline is READ-ONLY: no edit links from timeline events
- Role-scoped: Education Head sees only education events for their students, Finance Head sees contribution events, HR Head sees all events

### Day 16 – Member Search, Export & Department Scoping
*Phase 2 – Membership · Dependencies:
Day 15*

**Advanced Search Filters on MemberResource**

- SelectFilter: Status (Draft/Member/Active/Former/All)
- SelectFilter: Member Type (Kids/Youth/Adult/All)
- SelectFilter: Current Group (dynamic from member_groups)
- SelectFilter: Department
- DateRange filter: Date of Birth range (Ethiopian date pickers)
- TextFilter: City, Occupation Status
- BooleanFilter: Has Photo, Has Confession Father
- Combine filters: all applied simultaneously (AND logic)
- Filter panel collapsible (hidden by default, expandable)
- Show active filter count badge on filter button

**MemberExporter Implementation**

- Create app/Filament/Exports/MemberExporter.php extending ExcelExporter
- Column definitions: Member ID, Full Name (first+father+grandfather), Member Type, Status, Date of Birth (Ethiopian), Gender, Phone, City, Current Group, Enrolled Class, Enrolled Academic Year, Created Date (Ethiopian)
- Export formats: Excel (.xlsx), PDF, CSV
- Excel: frozen header row, auto-width columns, date cells formatted as dates, status column with color highlighting
- PDF: Church logo top-left, report title, generated by user + date, portrait for <8 columns else landscape, page numbers "Page X of Y"
- CSV: raw data, UTF-8 BOM for Ethiopian characters
- Export includes ONLY fields user has permission to view (Finance Head: no spiritual fields)

**Export Audit Logging**

- Create export_logs table: id, user_id FK, export_type VARCHAR(100), table_name, filters_json JSON, format ENUM(xlsx,pdf,csv), record_count INT, file_size_kb INT, exported_at TIMESTAMP
- MemberExporter::afterExport(): INSERT into export_logs with all metadata
- Admin and Superadmin can view export logs under Security > Export Logs
- Department Heads see own export logs only
- Retention: 1 year auto-purge (scheduled command)

**Eloquent Global Scope Enforcement**

- Verify DepartmentScope applied: HR Head query shows ALL members (no dept filter)
- Education Head: sees only members enrolled in their classes (via student_enrollments JOIN)
- Finance Head: sees all members with contribution records
- Charity Head: sees all active members (for contribution recording)
- Tour Head: sees all members (for internal tour registration phone auto-fill)
- Write integration test: verify each role sees only appropriate member subset

### Day 17 – "Others" Custom Option Management System
*Phase 2 – Membership · Dependencies:
Day 6*

**Custom Options Table & Model**

- Table: custom_options – id, field_name VARCHAR(100) (e.g. payment_method, relationship, donation_type), option_value VARCHAR(255), status ENUM(pending, approved, rejected) DEFAULT pending, added_by INT FK users, approved_by INT FK nullable, added_at TIMESTAMP, approved_at TIMESTAMP nullable, usage_count INT DEFAULT 0, display_order INT nullable, deleted_at
- Index on (field_name, status) for fast dropdown queries
- Model: CustomOption with scope approved() → status=approved, scope pending() → status=pending
- Method: static getOptionsForField(string $fieldName): array → returns predefined + approved custom options
- Method: static recordUsage(string $fieldName, string $value): void → increments usage_count

**Custom Option Filament Select Component**

- Create CustomOptionSelect extending Filament Select component
- Constructor: accepts $fieldName and $predefinedOptions array
- getOptions(): merges predefined + approved custom (with "(Pending)" suffix for pending) + "Other" always last
- When "Other" selected: shows inline TextInput below the select
- On form save: if "Other" was selected + text entered → INSERT into custom_options with status=pending, add_by = auth()->id()
- Value stored in main record as the text value entered (not foreign key)
- Custom value immediately available in dropdown for all users (with Pending suffix)
- Apply CustomOptionSelect to: payment_method, relationship, donation_type, employment_status, removal_reason, inventory_category, inventory_unit

**Admin Custom Options Management Page**

- Create app/Filament/Pages/ManageCustomOptions.php
- Table with all custom_options grouped by field_name
- Columns: Field Name, Option Value, Status badge, Added By, Added Date, Usage Count, Actions
- Bulk action: Approve Selected, Reject Selected
- Individual actions per row:
- 1. Approve: set status=approved, approved_by=auth()->id(), approved_at=now()
- 2. Reject: set status=rejected, option removed from dropdowns (data in existing records preserved)
- 3. Merge: modal to select target option → UPDATE all records using merged values to target, DELETE merged option
- 4. Reorder: drag-and-drop interface (using Filament sortable), affects display_order column
- 5. Delete: only approved options with usage_count = 0, confirmation required
- Dashboard widget: CustomOptionsPendingWidget shows count, links to manage page
- "Other" option ALWAYS last in any dropdown (display_order cannot move it)

### Day 18 – Academic Year Resource & Lifecycle Management
*Phase 3 – Education · Dependencies:
Day 5*

**Academic Year Migration**

- Table: academic_years – id, name VARCHAR(200) (e.g. "2017 E.C." or "2024/2025"), start_date DATE, end_date DATE, is_active BOOLEAN DEFAULT false, status ENUM(Draft, Active, Deactivated) DEFAULT Draft, activated_at TIMESTAMP nullable, deactivated_at TIMESTAMP nullable, activated_by INT FK nullable, deactivated_by INT FK nullable, created_by INT FK, created_at, updated_at
- Constraint: only one row with is_active = true at any time (enforced in application logic + unique partial index)
- start_date must be before end_date (database check constraint)
- No overlapping date ranges (enforce in application validation)

**AcademicYearResource (Filament)**

- Navigation: "Education" group, icon: heroicon-o-academic-cap, label "Academic Years"
- canViewAny(): education_head, admin, superadmin
- canCreate() / canEdit(): education_head, admin, superadmin
- canDelete(): admin, superadmin (only if no enrollments, contributions, attendance)
- Form fields: Name (TextInput, required), Start Date (EthiopianDatePicker, required), End Date (EthiopianDatePicker, required), Status (Select – read-only in form, managed via actions)
- Actions on list: Activate (if Draft or Deactivated → Active), Deactivate (if Active → Deactivated)
- Table columns: Name, Status badge (Draft=gray, Active=green, Deactivated=red), Start Date (Ethiopian), End Date (Ethiopian), Students Count

**Activation Business Logic**

- canActivate(): status must be Draft or Deactivated, no date overlap with currently active year
- On Activate: DB::transaction → (1) UPDATE academic_years SET is_active=false, status=Deactivated WHERE is_active=true (deactivate previous), (2) UPDATE this year SET is_active=true, status=Active, activated_at=now(), activated_by=auth()->id()
- On Activate: UPDATE student_enrollments SET status=Completed WHERE academic_year_id = previous year id AND status=Enrolled (archive previous year enrollments)
- On Activate: Trigger GenerateEndOfYearReport job for previous year (queued)
- Validation: cannot activate if start_date overlaps with active year dates
- Log to Tier 2 audit trail: action=academic_year_activated

**Deactivation Business Logic**

- canDeactivate(): education_head role, year must be Active
- On Deactivate: show confirmation modal with summary stats (enrolled students count, attendance sessions count, outstanding contributions count)
- On confirm: DB::transaction → (1) UPDATE academic_years SET is_active=false, status=Deactivated, deactivated_at=now(), deactivated_by=auth()->id(), (2) UPDATE student_enrollments SET status=Completed, (3) UPDATE contributions SET is_archived=true WHERE academic_year_id=this id
- Attendance sessions: remain accessible, READ-ONLY after deactivation (no new sessions can be created for inactive year)
- Admin can reactivate: adds a "Reactivate" action visible to admin/superadmin on Deactivated records
- Log to Tier 2 audit trail: action=academic_year_deactivated with summary stats in new_value JSON

### Day 19 – Classes & Subjects Resources
*Phase 3 – Education · Dependencies:
Day 18*

**Classes Migration & Model**

- Table: classes – id, name VARCHAR(200) UNIQUE, description TEXT nullable, is_active BOOLEAN DEFAULT true, created_by INT FK, created_at, updated_at, deleted_at
- Classes are PERMANENT (not tied to academic year)
- Model: ClassModel (avoid class name conflict with PHP reserved word)
- Relationships: hasMany(StudentEnrollment::class), hasMany(TeacherAssignment::class), hasMany(AttendanceSession::class)
- canDelete() business rule: check no active enrollments AND no attendance records
- Scope: active() → whereIsActive(true)
- Example seed data: "Grade 1", "Grade 2", "Grade 3", "Grade 4", "Grade 5", "Grade 6", "Grade 7", "Grade 8", "Beginners", "Youth Class", "Advanced"

**ClassModelResource (Filament)**

- Navigation: "Education" group, icon: heroicon-o-building-library
- canViewAny() / canCreate() / canEdit(): education_head, admin, superadmin
- canDelete(): education_head, admin, superadmin – with business rule check
- Form: Name (required, unique validation), Description (Textarea nullable)
- Table columns: Name, Active badge, Current Enrollment Count, Teacher Count, Created At
- Action: Archive (soft delete) – replaces Delete when has records
- Action: Restore (shows on archived classes)
- Filter: Active/Archived toggle

**Subjects Migration & Model**

- Table: subjects – id, name VARCHAR(200) UNIQUE, description TEXT nullable, is_active BOOLEAN DEFAULT true, created_by INT FK, created_at, updated_at, deleted_at
- Subjects are PERMANENT (not tied to academic year)
- Model: Subject
- Relationships: hasMany(TeacherAssignment::class)
- canDelete() rule: no teacher assignments AND not assigned to any class via teacher_assignments
- Example seed: "Bible Study", "Church History", "Amharic", "English", "Tigrinya", "Church Music", "Christian Living", "Prayer & Fasting"

**SubjectResource (Filament)**

- Navigation: "Education" group, icon: heroicon-o-book-open
- Same access as ClassModelResource
- Form: Name (required, unique), Description (Textarea nullable)
- Table columns: Name, Active badge, Assigned Teacher Count, Created At
- Archive/Restore actions same as classes

### Day 20 – Student Enrollment CRUD & Validation
*Phase 3 – Education · Dependencies:
Days 18, 19*

**Student Enrollments Migration**

- Table: student_enrollments – id, member_id INT FK NOT NULL, class_id INT FK NOT NULL, academic_year_id INT FK NOT NULL, enrolled_date DATE, completion_date DATE nullable, status ENUM(Enrolled, Withdrawn, Completed, Promoted) DEFAULT Enrolled, withdrawal_reason ENUM(Moved Away, Transferred, Graduated, Other) nullable, withdrawal_notes TEXT nullable, enrolled_by INT FK, completed_by INT FK nullable, created_at, updated_at
- Unique constraint: UNIQUE(member_id, academic_year_id) WHERE status IN (Enrolled) → one class per student per year
- Unique constraint: UNIQUE(member_id, class_id, academic_year_id) → prevents duplicate enrollment in same class same year

**StudentEnrollmentResource (Filament)**

- Navigation: "Education" group, icon: heroicon-o-user-plus, label "Enrollments"
- canViewAny() / canCreate() / canEdit(): education_head, admin, superadmin
- Form fields: Student (searchable Select from members – filter by status Active/Member, search by name/phone/member_code), Class (Select from active classes), Academic Year (Select – only active academic year shown, read-only), Enrolled Date (EthiopianDatePicker, defaults today)
- Create validation: check member not already enrolled in active year → custom rule EnrollmentUniquePerYear
- Error message: "This member is already enrolled in [Class Name] for [Year Name]"
- Table: Student Name, Member Code, Class, Academic Year, Status badge, Enrolled Date, Completion Date
- Filters: SelectFilter for status, class, academic_year

**Withdraw Student Action**

- Action "Withdraw" available on each Enrolled record
- Modal form: Withdrawal Reason (Select: Moved Away/Transferred/Graduated/Other), Notes (Textarea optional, max 500 chars)
- On confirm: UPDATE status=Withdrawn, completion_date=today, completed_by=auth()->id()
- Withdrawn students can be re-enrolled: allow new enrollment for same member in same/different class
- Show withdrawn students in enrollment list with "Withdrawn" red badge
- Log withdrawal to Tier 2 audit trail

**Enrollment Audit Trail**

- On enrollment create: log action=enrolled, entity=student_enrollment, new_value={member_id, class_id, academic_year_id}
- On enrollment withdraw: log action=withdrawn, new_value={reason, notes, completion_date}
- On enrollment promote: log action=promoted, new_value={from_class, to_class}
- Visible in Member Timeline under Education events
- Visible in Class roster view with history

### Day 21 – Student Promotion Logic (Individual & Bulk)
*Phase 3 – Education · Dependencies:
Day 20*

**Individual Student Promotion**

- Action "Promote" on each Enrolled record in StudentEnrollmentResource
- Only available at end-of-year (Education Head judgment – no technical date lock)
- Modal: Target Class (Select from active classes, exclude current class), Notes (optional)
- On confirm: DB::transaction → (1) UPDATE current enrollment: status=Promoted, completion_date=today, completed_by=auth()->id(), (2) INSERT new enrollment: member_id, target_class_id, active academic_year_id, status=Enrolled, enrolled_date=today, enrolled_by=auth()->id()
- Log: action=promoted, old_value={from_class}, new_value={to_class}

**Bulk Promotion Wizard**

- Action "Bulk Promote" at top of class-filtered enrollment list
- Step 1: Select source class (shows all enrolled students in that class)
- Step 2: Select target class (for promotion) OR choose "Make Classless" (no new enrollment)
- Step 3: Review list – checkboxes to DESELECT students who should NOT be promoted (repeat year)
- Step 4: For deselected students (repeating): radio choice "Keep in same class" OR "Make classless"
- Step 5: Confirm – shows summary before executing
- Execution: atomic transaction for all selected students
- On success: show per-student result table (Promoted/Repeated/Classless)
- "Last level" handling: if promoted to a class with no next class above it, Education Head notes it manually
- Log each individual promotion/repeat action separately in audit trail

**Promotion Guards & Validation**

- Cannot promote to non-existent class (target_class_id must exist in active classes)
- Cannot promote if academic year is Deactivated
- Cannot promote already Promoted/Completed/Withdrawn records
- Show warning if student was absent > 50% of sessions (attendance rate check) – warning only, not block
- Promotion available only in Active academic year context

### Day 22 – Teacher Management – Profiles & Types
*Phase 3 – Education · Dependencies:
Days 8, 19*

**Teachers Migration**

- Table: teachers – id, member_id INT FK nullable (links to members if member-teacher), full_name VARCHAR(255) NOT NULL, phone VARCHAR(20) UNIQUE NOT NULL, qualifications TEXT nullable, status ENUM(Active, Inactive, On Leave, Former) DEFAULT Active, teacher_code VARCHAR(20) (auto T-000001), created_by INT FK, created_at, updated_at, deleted_at
- phone unique across all teachers (both external and member-teachers)
- When member_id set: full_name and phone auto-synced from member record via Observer
- canDelete() rule: no teaching assignment history AND no attendance records

**TeacherResource (Filament)**

- Navigation: "Education" group, icon: heroicon-o-academic-cap, label "Teachers"
- canViewAny() / canCreate() / canEdit() / canDelete(): education_head, admin, superadmin
- Form with two modes (Tab selection): "External Teacher" | "Member Teacher"
- External Teacher tab: Full Name (required), Phone (required, unique), Qualifications (textarea), Status
- Member Teacher tab: Select Member (searchable from members, status Active/Member only), auto-populate full_name + phone from member, Qualifications (additional field)
- On save Member Teacher: create teacher record with member_id FK, full_name and phone copied from member
- MemberObserver: if member phone changes and has linked teacher → sync teacher.phone automatically
- Table columns: Teacher Code, Full Name, Type badge (External/Member), Phone, Status, Assigned Classes count
- Filter: Type (External/Member), Status

**Teacher Status Management**

- Status ENUM: Active (teaching), Inactive (temporarily not teaching), On Leave (temporary absence), Former (no longer teaching)
- Status change action available on teacher record
- Former status = soft delete behavior (hidden from active views, preserved in DB)
- Restore action: Former → Active (Admin/Education Head)
- Cannot hard delete teacher with assignment history or attendance records
- On status change: log to audit trail (who changed, old→new status)

### Day 23 – Teacher Assignments (Class + Subject + Year)
*Phase 3 – Education · Dependencies:
Day 22*

**Teacher Assignments Migration**

- Table: teacher_assignments – id, teacher_id INT FK, class_id INT FK, subject_id INT FK, academic_year_id INT FK, assigned_date DATE, effective_from DATE, effective_to DATE nullable, assignment_status ENUM(Active, Inactive, On Leave) DEFAULT Active, created_by INT FK, created_at, updated_at
- No unique constraint (teacher can teach same class+subject across different years)
- Active assignment: effective_to IS NULL AND assignment_status = Active
- Allow multiple teachers per class+subject in same year (team teaching)

**Teacher Assignment Sub-Page (within TeacherResource)**

- RelationManager: TeacherAssignmentsRelationManager on TeacherResource Edit/View page
- Displayed as "Assignments" tab
- Form fields: Class (Select, required), Subject (Select, required), Academic Year (Select, defaults to active year), Effective From (EthiopianDatePicker), Status
- Table: Class, Subject, Academic Year, Effective From, Effective To, Status badge, Actions
- Action "Deactivate Assignment": sets effective_to = today, status = Inactive
- Action "Set On Leave": sets status = On Leave with reason notes
- History view: show Inactive assignments with restore option

**Assignment Validation & Business Rules**

- One teacher can teach: multiple subjects in same class, same subject in multiple classes, any combination across classes/subjects
- Show warning (not block) if teacher is assigned to overlapping session times (schedule conflict detection)
- Assignment displayed in: class roster view (teachers column), attendance sheet (responsible teacher), academic year summary report
- External teachers: no portal access granted by assignment (assignment is information only)
- Member teachers: assignment does not automatically grant admin portal login (they need a staff user account)

**Class Roster View Enhancement**

- Add "Teachers" tab to ClassModelResource view page
- Show: teacher name, subjects they teach in this class, assignment status, effective dates
- Link to TeacherResource for each teacher row
- Shows current and historical teacher assignments for this class

### Day 24 – Attendance Sessions & Student Attendance
*Phase 3 – Education · Dependencies:
Days 20, 23*

**Attendance Sessions Migration**

- Table: attendance_sessions – id, class_id INT FK, session_date DATE, academic_year_id INT FK, status ENUM(Open, Completed, Locked) DEFAULT Open, locked_at TIMESTAMP nullable, locked_by INT FK nullable, unlock_justification TEXT nullable, unlocked_at TIMESTAMP nullable, unlocked_by INT FK nullable, created_by INT FK, created_at, updated_at
- Unique constraint: UNIQUE(class_id, session_date, academic_year_id) → one session per class per date
- Index on: (class_id, session_date) for fast session lookup
- Table: student_attendance – id, student_id INT FK, session_id INT FK, status ENUM(Present, Absent, Excused, Late, Permission), marked_by INT FK, marked_at TIMESTAMP, sync_timestamp TIMESTAMP nullable, is_synced BOOLEAN DEFAULT true, created_at, updated_at
- Unique: UNIQUE(student_id, session_id)

**AttendanceSessionResource (Filament)**

- Navigation: "Education" group, icon: heroicon-o-clipboard-list
- canViewAny(): education_head, education_monitor, admin, superadmin
- canCreate(): education_monitor, admin, superadmin (only for active academic year)
- Form: Class (Select active classes), Session Date (EthiopianDatePicker, required), Academic Year (auto-filled from active year, read-only)
- On create: validate UNIQUE(class_id, session_date) → error "Session already exists for this class on [date]"
- Table: Class, Session Date (Ethiopian), Status badge, Student Attendance Summary (X/Y present), Teacher Attendance Summary, Created By
- Filters: Class, Date Range, Status

**Mark Attendance Sub-Page**

- On session row: "Mark Attendance" action → navigates to dedicated attendance marking page
- Page shows: list of ALL currently Enrolled students in that class (for active academic year)
- Each row: Student Name, Member Code, Current Status badge, Status select (Present/Absent/Excused/Late/Permission)
- "Mark All Present" bulk action button
- "Mark All Absent" bulk action button
- Auto-save on change (no Submit button needed – each status change saved immediately via AJAX)
- Show attendance summary: "X Present / Y Absent / Z Excused / W Late" at top
- Locked sessions: all selects disabled, read-only mode with "Session Locked" banner
- Session from inactive year: read-only with "Academic Year Closed" banner

### Day 25 – Teacher Attendance & Substitute Teacher Logic
*Phase 3 – Education · Dependencies:
Days 23, 24*

**Teacher Attendance Migration**

- Table: teacher_attendance – id, teacher_id INT FK, session_id INT FK, attendance_status ENUM(Present, Absent, Late, Permission), marked_by INT FK (Education Monitor, never teacher themselves), marked_at TIMESTAMP, session_outcome ENUM(Normal, Cancelled, Substitute_Assigned) DEFAULT Normal, substitute_teacher_name VARCHAR(255) nullable, notes TEXT nullable, created_at, updated_at
- Unique: UNIQUE(teacher_id, session_id)

**Teacher Attendance on Attendance Marking Page**

- Add "Teacher Attendance" section ABOVE student list on session marking page
- Shows all teachers assigned to this class for the active academic year
- For each teacher: Name, Subject, Status select (Present/Absent/Late/Permission)
- IF Absent selected: shows two radio buttons: "Session Cancelled" | "Substitute Assigned"
- – If Cancelled: session_outcome = Cancelled, student attendance section shows warning "Session cancelled – student attendance will not be recorded"
- – If Substitute: shows text input "Substitute Teacher Name", session_outcome = Substitute_Assigned
- – If Substitute: student attendance proceeds normally, substitute name stored in teacher_attendance.substitute_teacher_name
- Late threshold: 15 minutes after session start time (show threshold info near status select)
- Education Monitor marks teacher attendance: teachers cannot mark their own attendance (permission check)

**Teacher Attendance Rate Calculation**

- Formula: (COUNT where status=Present AND session_outcome != Cancelled) / (COUNT total assigned sessions) × 100
- Calculated per teacher, per academic year, optionally per class/subject
- Result displayed on TeacherResource view page as "Attendance Rate: X%"
- Color coding: >90% green, 70-90% yellow, <70% red
- Visible to Education Head only (privacy from other roles)
- Also shown in Teacher Attendance Report (Day 28)

### Day 26 – Session Locking, Auto-Lock & Unlock Workflow
*Phase 3 – Education · Dependencies:
Day 24*

**Manual Session Lock/Unlock**

- Lock action: available to Education Monitor on Open or Completed sessions
- On lock: UPDATE status=Locked, locked_at=now(), locked_by=auth()->id()
- Locked session: all attendance modifications disabled, visual "Locked 🔒" banner
- Unlock action: available ONLY to Education Head (not Education Monitor)
- Unlock modal: mandatory Justification field (TextArea, required, min 20 chars)
- On unlock: UPDATE status=Open, unlock_justification=text, unlocked_at=now(), unlocked_by=auth()->id()
- Unlock action logged to BOTH session record AND Tier 2 audit trail
- Audit log entry: action=session_unlocked, entity=attendance_session, new_value={justification, unlocked_by, unlocked_at}

**Auto-Lock Scheduler**

- Create command: php artisan attendance:auto-lock
- Query: SELECT * FROM attendance_sessions WHERE status IN (Open, Completed) AND session_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
- For each result: UPDATE status=Locked, locked_at=now(), locked_by=NULL (null = system auto-lock)
- Log each auto-lock to Tier 1 audit trail (system action)
- Schedule in Console/Kernel.php: $schedule->command("attendance:auto-lock")->daily()
- Show "Auto-locked (system)" badge on locked sessions where locked_by IS NULL

**Approaching Lock Deadline Notification**

- Create command: php artisan attendance:send-lock-reminders
- Query: sessions where status=Open AND session_date = DATE_SUB(NOW(), INTERVAL 27 DAY) (3 days before auto-lock)
- For each: create in-app notification for Education Monitor assigned to that class
- Notification: type=session_lock_reminder, title="Session Lock Reminder / ክፍለ ጊዜ ይዘጋል", message="Attendance session for [Class] on [Ethiopian Date] will auto-lock in 3 days", action_url=/admin/attendance-sessions/{id}
- Schedule: $schedule->command("attendance:send-lock-reminders")->daily()
- Do NOT send duplicate reminder if already notified (check notifications table)

**Sync Conflict Tracking**

- Table: attendance_sync_conflicts – id, student_id INT FK, session_id INT FK, first_user_id INT FK, first_value ENUM(Present,Absent,Excused,Late,Permission), first_synced_at TIMESTAMP, second_user_id INT FK, second_value ENUM (winner), second_synced_at TIMESTAMP (winner time), winner_value ENUM, created_at
- On server receiving sync: check if attendance record already exists for student+session
- If exists: record conflict, update to new value (last sync wins), create conflict log entry
- Education Monitor can view conflicts on "Sync Conflicts" page (read-only)
- Page shows: Student, Session, First Value, Second Value (winner), Both Users, Conflict Time
- No manual conflict resolution (viewing only)

### Day 27 – Offline Attendance – PWA & IndexedDB Sync
*Phase 3 – Education · Dependencies:
Day 26*

**Service Worker Configuration**

- public/service-worker.js: register sync event handler for "attendance-sync"
- Cache strategy: CacheFirst for static assets (CSS, JS, fonts)
- Cache strategy: NetworkFirst for attendance API endpoints
- Precache on install: current open sessions for Education Monitor's assigned classes
- Cache key: attendance-session-{id}-{academic_year_id}
- Install service worker in main layout: navigator.serviceWorker.register("/service-worker.js")
- Handle offline detection: navigator.onLine event listener, show "Offline Mode" banner

**IndexedDB Schema for Offline Data**

- IndexedDB database: "FinotOffline" version 1
- Object store: "pending_attendance" → keyPath: {student_id, session_id}
- Fields: student_id, session_id, status, marked_by, marked_at (local timestamp), synced (false)
- Object store: "cached_sessions" → keyPath: session_id, with student roster
- Object store: "sync_queue" → keyPath: auto-increment, fields: endpoint, method, body, retry_count
- JavaScript module: public/js/offline/attendance.js
- Function: saveAttendanceOffline(studentId, sessionId, status) → writes to IndexedDB
- Function: syncPendingAttendance() → reads unsync records, POSTs to /api/attendance/sync, marks synced

**Background Sync & Retry**

- Service worker: self.addEventListener("sync", (event) => { if (event.tag === "attendance-sync") event.waitUntil(syncAttendance()); })
- Trigger sync: navigator.serviceWorker.ready.then(sw => sw.sync.register("attendance-sync"))
- Retry logic: max 3 attempts, 30-second delay between retries (use setTimeout in service worker)
- After 3 failures: create notification in IndexedDB "sync_errors" store, show toast to user
- On reconnect: also trigger sync immediately (navigator.onLine event)
- API endpoint: POST /api/v1/attendance/sync → accepts array of {student_id, session_id, status, marked_at, local_device_id}
- API returns: {synced: [], conflicts: [], errors: []} per record
- Show sync status toasts: "Syncing attendance..." → "Synced X records" OR "Sync failed, retrying..."

### Day 28 – Education Reports & Analytics
*Phase 3 – Education · Dependencies:
Days 25, 26*

**Student Attendance Report**

- Page: Reports > Student Attendance Report
- Filters: Academic Year (required), Class (multi-select), Date Range (EthiopianDatePicker), Student (search), Status (multi-select: Present/Absent/etc.)
- Table: Student Name, Class, Session Date (Ethiopian), Status, Marked By, Sync Status
- Summary cards: Total Sessions, Total Present, Total Absent, Attendance Rate %
- Chart: attendance rate per class (bar chart), attendance trend over months (line chart)
- Export: Excel (formatted, conditional color for absent=red, present=green), PDF (landscape)
- Access: education_head, admin, superadmin

**Teacher Attendance Report**

- Page: Reports > Teacher Attendance Report (Education Head only)
- Filters: Academic Year, Teacher (multi-select), Class (multi-select), Date Range
- Table: Teacher Name, Class, Subject, Session Date, Attendance Status, Session Outcome, Substitute Name
- Summary: per-teacher attendance rate, total sessions assigned, sessions taught, sessions cancelled
- Rank teachers by attendance rate (highest to lowest)
- Export: Excel, PDF
- Access: education_head, admin, superadmin ONLY (privacy gate)

**Class Roster Report**

- Shows all enrolled students per class for selected academic year
- Columns: Member Code, Student Name, Enrollment Date, Status, Group, Contact Phone
- Also shows assigned teachers per subject
- Export as class list PDF (formatted for printing)

**Sync Conflicts Report**

- Page: Education > Sync Conflicts (Education Monitor, Education Head)
- Show all conflicts from attendance_sync_conflicts table
- Columns: Student, Session, Class, Date, First Value, Second Value (winner), Both Users, Conflict Time
- Filter by date range, class
- Read-only, no resolution actions
- Export to CSV for external investigation

### Day 29 – Education Dashboard, Driver.js Tours & Integration
*Phase 3 – Education · Dependencies:
Days 21, 28*

**Education Dashboard Widgets**

- Widget: ActiveAcademicYearWidget – shows current year name, start/end dates, days remaining
- Widget: EnrollmentStatsWidget – total enrolled, by class breakdown (stats overview cards)
- Widget: AttendanceRateWidget – this week's attendance rate across all classes (gauge chart)
- Widget: PendingSessionLocksWidget – count of sessions approaching 30-day auto-lock, link to sessions list
- Widget: RecentAttendanceWidget – last 7 days attendance sessions with rate (table)
- Education Head sees: all widgets above + teacher attendance rate summary
- Education Monitor sees: pending sessions widget + recent attendance for their classes

**Driver.js Tour – Education Head (public/js/tours/education_head.js)**

- Step 1: Welcome → "Education Department Overview" (highlight dashboard)
- Step 2: Academic Years menu item → "Manage academic years here. Only one can be active at a time."
- Step 3: Classes menu item → "Create and manage your class levels."
- Step 4: Subjects menu item → "Create subjects that teachers will be assigned to."
- Step 5: Teachers menu item → "Register and assign teachers to classes and subjects."
- Step 6: Enrollments menu item → "Enroll students into classes for the active year."
- Step 7: Attendance Sessions menu item → "View and manage all attendance sessions."
- Step 8: Reports menu item → "Access student and teacher attendance reports here."
- Step 9: Promotion tip → "Use Bulk Promote action on Enrollments at end of year."

**Driver.js Tour – Education Monitor (public/js/tours/education_monitor.js)**

- Step 1: Dashboard → "Your key tasks at a glance"
- Step 2: Attendance Sessions → "Create sessions and mark student attendance here"
- Step 3: Mark Attendance button → "Click to open the attendance marking form"
- Step 4: Offline mode tip → "You can mark attendance offline. It will sync when you reconnect."
- Step 5: Lock session → "Remember to lock sessions before the 30-day deadline."
- Step 6: Sync Conflicts → "Review any sync conflicts that occurred during offline mode."

**Integration Testing Checklist**

- Test: Create academic year, activate it, verify previous year deactivated
- Test: Enroll student, attempt duplicate enrollment in same year (should fail)
- Test: Create attendance session, verify it appears in student timeline
- Test: Mark student absent, lock session, attempt to edit (should fail)
- Test: Unlock session as Education Head with justification, verify audit log
- Test: Schedule auto-lock command, verify 30+ day sessions are locked
- Test: Teacher marked absent → session Cancelled → student attendance disabled
- Test: Bulk promote class, verify old enrollment Completed + new enrollment created
- Test: Education Monitor cannot view Teacher Attendance Report (permission check)

### Day 30 – Contribution Amounts Setup
*Phase 4 – Financial · Dependencies:
Days 13, 18*

**Contribution Amounts Migration**

- Table: contribution_amounts – id, group_id INT FK, month_name VARCHAR(50) (stored as name e.g. "Meskerem" or "September"), amount DECIMAL(10,2) NOT NULL, effective_from DATE NOT NULL, effective_to DATE nullable, created_by INT FK, created_at, updated_at
- No overlapping periods: UNIQUE(group_id, month_name, effective_from) – validate in application that no two records have same group+month with overlapping effective_from/effective_to range
- Amount must be >= 0.01 (CHECK constraint)
- Cannot delete if contributions recorded against this group+month combination (check via contributions table)

**ContributionAmountResource (Filament)**

- Navigation: "Finance" group, icon: heroicon-o-currency-dollar, label "Contribution Settings"
- canViewAny() / canCreate() / canEdit() / canDelete(): finance_head, nibret_hisab_head, admin, superadmin
- Form: Group (Select from active member_groups), Month Name (Select – 12 months only using EthiopianDateHelper::getMonthsForContribution(), Pagume NOT shown), Amount (TextInput type=number, step=0.01, min=0.01), Effective From (EthiopianDatePicker), Effective To (EthiopianDatePicker nullable)
- Validation on save: check no overlapping period for same group + month combination
- Error message: "An amount is already defined for [Group] in [Month] from [date] to [date]"
- Table: Group, Month, Amount (formatted Birr), Effective From, Effective To, Status (Current/Historical)
- Filter: Group, Month, Active Only toggle

**Contribution Month Configuration**

- EthiopianDateHelper::getMonthsForContribution() returns exactly 12 months: Meskerem through Nehasse (Pagume excluded)
- Dropdown allows choosing between Ethiopian month names (Meskerem, Tikimt...) OR Gregorian names (September, October...) – determined by the person creating the amount setting
- Store as month_name string in DB (not a number) for flexibility
- Display consistency: show month names in the language the user selected when creating
- Month ordering in dropdown: Ethiopian order (Meskerem=1 through Nehasse=12)

### Day 31 – Individual Contribution Recording
*Phase 4 – Financial · Dependencies:
Day 30*

**Contributions Migration**

- Table: contributions – id, member_id INT FK NOT NULL, academic_year_id INT FK NOT NULL, amount DECIMAL(10,2) NOT NULL, month_name VARCHAR(50) NOT NULL, payment_date DATE NOT NULL, payment_method ENUM(Cash,Check,Mobile Money,Bank Transfer,Other) DEFAULT Cash, custom_payment_method VARCHAR(100) nullable, notes TEXT nullable, recorded_by INT FK NOT NULL, is_archived BOOLEAN DEFAULT false, archived_at TIMESTAMP nullable, created_at, updated_at
- No hard deletes: once recorded, contributions are permanent (soft archived only)
- Unique constraint consideration: UNIQUE(member_id, academic_year_id, month_name) for full payment – BUT allow multiple records for same month (partial payments sum to total). Remove unique constraint, handle via application warning logic
- Index on: (member_id, academic_year_id) for fast outstanding calculation
- Index on: (academic_year_id, is_archived) for report queries

**ContributionResource (Filament)**

- Navigation: "Finance" group, icon: heroicon-o-banknotes, label "Contributions"
- canViewAny(): finance_head, nibret_hisab_head, charity_head, admin, superadmin
- canCreate() / canEdit(): charity_head ONLY (per business rule 5.2)
- canDelete(): admin, superadmin only (logged to Tier 2 audit trail)
- Form: Member (searchable Select, filter by status Active/Member only – NOT Draft/Former), Amount (TextInput number, min 0.01), Month Name (Select – 12 months only, Pagume excluded), Payment Method (CustomOptionSelect field)
- Auto-filled (disabled/read-only): Academic Year (active year name), Payment Date (today, EthiopianDatePicker, editable), Recorded By (current user name)
- Notes (Textarea, optional, max 500 chars)

**Contribution Warnings & Validation**

- Member status validation: reject if status is Draft or Former, show "Cannot record for Draft/Former members"
- Active academic year required: if no active year → block form, show "No active academic year. Contact Education Head."
- Already paid warning: after selecting member + month, check if any contribution exists for member+month+active_year. If yes: show yellow banner "Warning: [Member Name] has already paid [amount] for [Month]. Proceed to record additional payment?"
- Unusual amount warning: if amount differs >50% from group's expected amount for that month → show yellow banner "Note: Amount differs significantly from expected [X] Birr for [Group Name]"
- Implementation: use Filament afterStateUpdated() on member_id and month_name fields to trigger AJAX validation and show/hide warning components

**Multi-Month Recording**

- In form: allow "Add Another Month" repeater to record multiple months in one transaction
- Each row: month_name + amount + payment_method (share the same member, academic_year, payment_date, recorded_by)
- On save: insert multiple contribution records in DB::transaction()
- Label: "Record Multiple Months / ብዙ ወሮችን ያስቀምጡ"
- Or: save one, then the form resets with member pre-filled for next entry (quick repeat entry)

### Day 32 – Donations (Separate from Contributions)
*Phase 4 – Financial · Dependencies:
Day 5*

**Donations Migration**

- Table: donations – id, donor_name VARCHAR(255) nullable (null or "Anonymous" allowed), amount DECIMAL(10,2) NOT NULL, donation_date DATE NOT NULL, donation_type VARCHAR(100) NOT NULL (General Fund/Building Fund/Missionary Support/Charity∕Aid/Other + custom), custom_donation_type VARCHAR(100) nullable, notes TEXT nullable, recorded_by INT FK NOT NULL, created_at, updated_at
- Donations have NO academic_year_id (never archived)
- Donations are NEVER soft deleted (permanent records)
- No unique constraints (same donor can donate many times)
- amount CHECK >= 0.01

**DonationResource (Filament)**

- Navigation: "Finance" group, icon: heroicon-o-gift, label "Donations"
- canViewAny(): finance_head, nibret_hisab_head, admin, superadmin
- canCreate() / canEdit(): finance_head ONLY (per business rule 5.3)
- canDelete(): superadmin ONLY (extremely rare, logged permanently to Tier 2)
- Form: Donor Name (TextInput nullable, placeholder "Leave empty or type Anonymous"), Amount (number, min 0.01), Donation Date (EthiopianDatePicker, defaults today), Donation Type (CustomOptionSelect: General Fund/Building Fund/Missionary Support/Charity∕Aid/Other)
- Notes (Textarea optional, max 500 chars)
- Recorded By: auto-filled, read-only
- Table: Donor Name (shows "Anonymous" if null), Amount, Donation Type badge, Donation Date (Ethiopian), Recorded By, Notes snippet
- Filter: Date Range, Donation Type
- Clearly labeled as SEPARATE from contributions in navigation and UI

**Donation Display Rules**

- Donations appear in all-time view (never filtered by academic year)
- Donations do NOT contribute to "Outstanding Contributions" calculation
- Donations appear in their own Donation Report (Day 34)
- Donations included in Financial Statement alongside contributions (Day 34)
- Export: separate from contribution exports

### Day 33 – Outstanding Contributions & Archival Logic
*Phase 4 – Financial · Dependencies:
Days 30, 31*

**Outstanding Contributions Page**

- Page: Finance > Outstanding Contributions
- Access: finance_head, nibret_hisab_head, charity_head, admin, superadmin
- ONLY shows data for the currently ACTIVE academic year (archive data accessed via reports)
- For each active member with a group: calculate Expected = contribution_amounts.amount for their group×month, Paid = SUM(contributions.amount) for member+month+active_year, Outstanding = Expected – Paid
- Show members where Outstanding > 0
- Display table: Member Name, Member Code, Group, Month, Expected Amount, Paid Amount, Outstanding Amount
- Summary at top: Total Expected (active year), Total Collected, Total Outstanding, Collection Rate %
- Filter: Group, Month, Class
- Sort by Outstanding Amount descending

**Outstanding Calculation Logic**

- Only calculate for members with status Active or Member (not Draft, not Former)
- Members with no group: Expected = 0, Outstanding = 0 (no requirement without group)
- Members with group but no contribution_amount defined for their group+month: Expected = 0 (no obligation set)
- No carry-forward to new academic year: when year deactivates, outstanding is archived with the year, fresh start for new year
- Monthly outstanding notification: scheduled monthly notification to finance_head about outstanding totals

**Contribution Archival Automation**

- Archival triggered by academic year deactivation (Day 18)
- Archive command: UPDATE contributions SET is_archived=true, archived_at=NOW() WHERE academic_year_id = {deactivated_year_id}
- Run inside DB::transaction with academic year deactivation
- Archived contributions: is_archived=true → excluded from active reports/dashboard
- Still accessible: "View by Academic Year" filter in Contribution Report shows archived data
- Archived contributions are READ-ONLY: edit/delete actions hidden for archived records
- No un-archive method (permanent archival by design)

### Day 34 – Financial Reports & Statement Generation
*Phase 4 – Financial · Dependencies:
Days 31, 32, 33*

**Contribution Report Page**

- Filters: Academic Year (Select, includes all years + "All Years" option), Group (multi-select), Class (multi-select), Date Range (EthiopianDatePicker pair), Month (multi-select from 12 months), Member Status (multi-select), Payment Method (multi-select)
- Metrics cards: Total Expected (for filtered members/year), Total Collected, Outstanding Amount, Collection Rate %, Top 5 Contributors
- Table: Member Name, Group, Month, Amount, Payment Method, Payment Date (Ethiopian), Recorded By
- Chart: Collection rate per group (bar chart), Monthly collection trend (line chart), Payment method distribution (pie chart)
- Archived year data: accessible via Academic Year filter, shows "Archived" badge on data from inactive years

**Donation Report Page**

- Filters: Date Range, Donation Type (multi-select)
- Metrics: Total Donated (all time), Total This Year (Gregorian), Total by Type breakdown
- Table: Donor Name, Amount, Type, Date (Ethiopian), Recorded By, Notes
- All-time view (no archival, no year filter required)
- Chart: Donations by type (pie), Monthly donation trend (bar)

**Financial Statement Generation**

- Page: Finance > Financial Statement, Access: finance_head, nibret_hisab_head, admin, superadmin
- Period Selection: Monthly (select year + month), Quarterly (select year + quarter Q1-Q4), Annual (select year)
- Statement PDF includes:
- Section 1: Total Contributions (breakdown by group, by month within period)
- Section 2: Total Donations (breakdown by type)
- Section 3: Outstanding Contributions (current academic year only)
- Section 4: Collection Trends (month-over-month comparison)
- PDF format: Church logo top-left, FINOT Church title, Statement Period, Generated By (user name + role), Generated Date (Ethiopian), CONFIDENTIAL footer
- PDF export ONLY (no Excel for statements – formatted presentation document)
- Statement generation logged to Tier 2 audit trail

### Day 35 – Financial Exports, Audit Logging & Archival Scheduler
*Phase 4 – Financial · Dependencies:
Day 34*

**Contribution Export Implementation**

- ContributionExporter: Excel with columns: Member ID, Full Name, Group, Month, Amount (number cell type), Payment Method, Payment Date (Ethiopian string), Academic Year, Recorded By, Is Archived flag
- Excel formatting: frozen header row, amount column formatted as currency (ETB), outstanding amounts highlighted in orange via conditional formatting (outstanding > 0)
- PDF export: landscape A4, church logo header, table with all columns, page breaks between academic years, totals row at bottom of each group
- CSV: UTF-8 BOM, raw data for external accounting systems
- DonationExporter: similar format, no academic year column, includes donor name

**Financial Audit Trail**

- Contribution events logged to Tier 2 (permanent): create, update amount/month/method, delete (admin only), archive (system)
- Each audit entry: user_id, action (contribution_created/updated/deleted/archived), entity_id, old_value JSON, new_value JSON, ip_address, timestamp
- Donation events: same structure as contributions (permanent Tier 2)
- Financial statement generation: logged with period, generated_by, record_count
- Export events: logged to export_logs table (Day 16)
- Audit log accessible to finance_head via Finance > Audit Trail menu (filtered to financial events only)

**Archival Scheduler (Linked to Academic Year)**

- Command: php artisan contributions:archive {academic_year_id}
- Called automatically from AcademicYear model Observer when status changes to Deactivated
- Runs in background queue job: ArchiveContributionsJob
- Updates is_archived=true, archived_at=NOW() for all contributions of that academic_year_id
- After archival: dispatches notification to finance_head "Contributions archived for [Year Name]"
- Logs archival to Tier 2 audit trail with record count

### Day 36 – Finance Dashboard & Nibret Hisab Widgets
*Phase 4 – Financial · Dependencies:
Day 35*

**Finance Dashboard Widgets**

- Widget: CurrentYearCollectionWidget – Total Expected vs Collected vs Outstanding with progress bar for active year
- Widget: MonthlyTrendWidget – Bar chart: last 6 months collection amounts
- Widget: DonationSummaryWidget – Total donations this year, all-time total, last donation date
- Widget: TopContributorsWidget – Table of top 5 contributors this year with amounts
- Widget: CollectionRateByGroupWidget – Mini table: each group, rate %, color-coded
- Widget: OutstandingMembersCountWidget – Count of members with outstanding > 0
- Finance Head and Nibret Hisab Head both see all widgets
- Charity Head sees: simplified view (outstanding counts only, no raw amounts)

**Monthly Notification – Outstanding Contributions**

- Command: php artisan finance:notify-outstanding
- Schedule: 1st day of each Ethiopian month ($schedule->cron("0 8 11 * *") – approximate Gregorian mapping)
- Calculates total outstanding for active year
- Creates in-app notification for finance_head, nibret_hisab_head, admin: title="Monthly Outstanding Contributions Report", message="[Total] Birr outstanding from [Count] members for [Year]", action_url=/admin/outstanding-contributions

### Day 37 – Tour Model, CRUD & Lifecycle
*Phase 5 – Tours · Dependencies:
Day 5*

**Tours Migration**

- Table: tours – id, place VARCHAR(255) NOT NULL, description TEXT, tour_date DATE NOT NULL, start_time TIME NOT NULL, cost_per_person DECIMAL(10,2) nullable, registration_deadline DATE nullable, max_capacity INT nullable, status ENUM(Draft, Published, In Progress, Completed, Cancelled) DEFAULT Draft, cancelled_at TIMESTAMP nullable, cancelled_by INT FK nullable, cancellation_reason TEXT nullable, created_by INT FK, created_at, updated_at, deleted_at
- Table: tour_passengers – id, passenger_code VARCHAR(20) (TP-000001), tour_id INT FK, full_name VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, passenger_count INT DEFAULT 1, receipt_image VARCHAR(500) nullable, member_id INT FK nullable, registration_type ENUM(Public, Internal) DEFAULT Public, status ENUM(Pending, Confirmed, Cancelled) DEFAULT Pending, registration_date DATE, registered_by INT FK nullable, cancellation_reason TEXT nullable, created_at, updated_at
- Unique: UNIQUE(tour_id, phone) – same phone cannot register twice for same tour

**TourResource (Filament)**

- Navigation: "Tours" group, icon: heroicon-o-map, label "Tours"
- canViewAny() / canCreate() / canEdit() / canDelete(): tour_head, admin, superadmin
- canDelete() rule: if any tour_passengers exist → block delete, show "Cannot delete – use Cancel action instead"
- Form: Place (TextInput, required), Description (Textarea), Tour Date (EthiopianDatePicker, required), Start Time (TimePicker, required), Cost Per Person (number, nullable), Registration Deadline (EthiopianDatePicker, nullable), Max Capacity (number, nullable), Status (Select – restricted: certain transitions only)
- Edit restriction: if status is In Progress or Completed → tour_date field becomes read-only
- Table: Place, Tour Date (Ethiopian), Start Time, Status badge, Registered/Confirmed counts, Max Capacity
- Filter: Status, Date Range

**Tour Status Transitions & Cancel Action**

- Draft → Published: "Publish Tour" action (makes visible on public website)
- Published → In Progress: manual or can auto-trigger on tour_date
- In Progress → Completed: "Mark Completed" action
- Any status → Cancelled: "Cancel Tour" action
- Cancel modal: requires Cancellation Reason (Textarea, required)
- On Cancel: UPDATE tours SET status=Cancelled, cancelled_at=now(), cancelled_by=auth()->id()
- On Cancel: UPDATE tour_passengers SET status=Cancelled WHERE tour_id=X AND status=Confirmed
- On Cancel: Dispatch CancelTourNotification job → creates in-app notification for each confirmed registrant who is a linked member
- Log to audit trail: action=tour_cancelled, old_status, reason

### Day 38 – Tour Registration Management
*Phase 5 – Tours · Dependencies:
Day 37*

**Internal Registration (Tour Head)**

- Action "Add Passenger" on TourResource Edit page → opens modal
- Modal form: Phone Number (TextInput, required, Ethiopian format)
- Live phone lookup: afterStateUpdated on phone field → AJAX to /api/tour/lookup-phone
- Lookup logic: (1) Check members table → if found: auto-fill Full Name from member, link member_id, show green "Member found" badge, (2) If not in members, check tour_passengers for previous tours → auto-fill name from most recent record, (3) If not found anywhere: show "New passenger – enter details manually"
- Full Name (auto-filled or manual), Passenger Count (number, default 1)
- Registration Type: Internal (auto-set)
- On save: validate phone unique for this tour (UNIQUE constraint), create tour_passenger record with status=Pending
- Auto-link member_id if phone matches members.phone

**View & Confirm Registrations**

- Tab "Registrations" on TourResource Edit page
- Table: Passenger Code, Full Name, Phone, Passenger Count, Registration Type (Public/Internal) badge, Status badge, Receipt (view link), Registration Date (Ethiopian), Actions
- Filter: Status (Pending/Confirmed/Cancelled)
- Action "Confirm": change status Pending → Confirmed, log action
- Action "Cancel Registration": change status → Cancelled with reason
- Bulk action: "Confirm Selected", "Cancel Selected"
- Show total summary: X Pending, Y Confirmed, Z Cancelled, Total passengers = SUM(passenger_count) for Confirmed
- On new public registration: create in-app notification for tour_head: "New registration from [Name] for [Tour Place] tour"
- On confirm: create in-app notification for linked member (if member_id set): "Your tour registration for [Tour Place] on [Date] has been confirmed"

**Receipt Management**

- receipt_image file upload on public registration form: accept PDF, JPG, PNG, max 5MB
- Store in storage/app/public/receipts/tours/{tour_id}/
- View receipt action: opens file in modal or new tab
- Receipt icon in registrations table (if uploaded)
- Tour Head can upload receipt on behalf of internal registrant

### Day 39 – Public Tour Listing & Registration Form
*Phase 5 – Tours · Dependencies:
Day 38*

**Public Tours Page (resources/views/public/tours.blade.php)**

- Route: GET /tours → TourController@index
- Show only tours with status = Published, ordered by tour_date ASC
- Each tour card: Place name, Description snippet, Tour Date (Ethiopian format), Start Time, Cost Per Person (or "Free"), Registration Deadline countdown, "Register Now" button
- If max_capacity set: show "X spots remaining" (max_capacity - confirmed passenger count)
- If registration_deadline passed: show "Registration Closed" badge instead of button
- If tour is full: show "Tour Full" badge instead of button
- Ethiopian date display throughout using x-ethiopian-date component

**Public Registration Form (resources/views/public/tour-register.blade.php)**

- Route: GET /tours/{id}/register, POST /tours/{id}/register → TourController@showRegister, @register
- Form fields: Full Name (required), Phone (required, +251 format, unique per tour), Number of Passengers (required, number min 1), Receipt Upload (optional, PDF/JPG/PNG, max 5MB)
- NO email field (removed per business rule)
- CSRF token, honeypot field for bot prevention
- Validation: phone unique for this tour → error "This phone number is already registered for this tour"
- On submit: INSERT tour_passenger with status=Pending, registration_type=Public, registered_by=NULL
- Success page: "Registration submitted! Your registration is pending confirmation. Reference: [TP-XXXXXX]"
- Tour Head receives in-app notification immediately after public registration
- Language: form labels in Amharic with English fallback

### Day 40 – Tour Attendance & Call Button
*Phase 5 – Tours · Dependencies:
Day 39*

**Generate Attendance Session**

- Action "Generate Attendance List" on TourResource Edit page (visible when tour has Confirmed passengers)
- Confirmation modal: "Generate attendance list from [X] confirmed passengers?"
- Creates record in attendance_sessions: tour_id, session_date=tour_date, status=Open (special type for tours)
- Actually: separate tour_attendance_sessions table or reuse with type flag
- Create tour_attendance_sessions table: id, tour_id INT FK, session_date DATE, status ENUM(Open, Completed), created_by INT FK, created_at
- Auto-creates tour_attendance records: one per confirmed passenger (by passenger_id)
- Table: tour_attendance – id, session_id INT FK, passenger_id INT FK, status ENUM(Present, Not Present) DEFAULT "Not Present", marked_at TIMESTAMP nullable, marked_by INT FK nullable, notes TEXT nullable, created_at, updated_at
- If attendance already generated: action changes to "View Attendance" (no duplicate generation)

**Attendance Marking Page**

- Navigate to: TourResource > Attendance tab or linked page
- Two-status only: Present / Not Present (radio or toggle per row)
- "Not Present" is default (passenger hasn't arrived yet)
- Table: Passenger Name, Phone, Passenger Count, Current Status toggle, Call Button (if Not Present), Notes input
- Mark All Present bulk button
- Summary: X Present / Y Not Present out of Z total
- "Complete Attendance" action: changes session status to Completed

**Call Button Implementation**

- Call button: visible ONLY on rows where status = Not Present
- Button icon: heroicon-o-phone, color: blue
- onClick: window.location.href = "tel:" + phone_number (opens device dialer)
- NO call logs created (privacy, per business rule 7.6)
- Call history NOT in any reports
- Notes field per passenger: Tour Head can manually type note after calling "Called at 9:15 AM, on the way"
- Notes saved to tour_attendance.notes column
- Call button disappears when passenger marked Present (no need to call)

### Day 41 – Tour Reports & Integration
*Phase 5 – Tours · Dependencies:
Day 40*

**Tour Report Page**

- Filters: Date Range (EthiopianDatePicker), Tour (select specific tour), Registration Status (multi-select), Tour Status (multi-select)
- Metrics: Total Tours, Total Registrations, Total Confirmed, Total Attended, Average Attendance Rate %
- Table: Tour Place, Tour Date (Ethiopian), Total Registered, Total Confirmed, Total Attended, Attendance Rate %, Status
- Drill down: click tour row → shows passenger list for that tour with attendance status
- Export: Excel (passenger lists per tour), PDF (summary report with church header)
- Access: tour_head, admin, superadmin

**Tour Search**

- Search filters on TourResource list: Place (text search), Date Range, Status
- Global search: enabled on tours.place field
- Admin search: same filters

**Member Timeline Integration**

- If tour_passenger.member_id is set: show tour participation in member timeline
- Timeline event: Tour – "Participated in tour to [Place] on [Date], Status: Present/Not Present"
- Link from tour attendance back to member profile via member_id FK

### Day 42 – Song Categories & Song Library CRUD
*Phase 6 – Worship & Media · Dependencies:
Day 5*

**Songs Migration**

- Table: song_categories – id, name VARCHAR(100) UNIQUE, description TEXT nullable, display_order INT DEFAULT 0, status ENUM(Active, Inactive) DEFAULT Active, created_by INT FK, created_at, updated_at
- Table: song_subcategories – id, category_id INT FK, name VARCHAR(100), description TEXT nullable, display_order INT DEFAULT 0, status ENUM(Active, Inactive), created_by INT FK, created_at
- Unique: UNIQUE(category_id, name) – unique name within parent
- Table: songs – id, song_code VARCHAR(20) (SONG-000001), title VARCHAR(255) NOT NULL, lyrics LONGTEXT nullable, category_id INT FK NOT NULL, subcategory_id INT FK NOT NULL, audio_file VARCHAR(500) nullable, video_file VARCHAR(500) nullable, artist VARCHAR(255) nullable, is_active BOOLEAN DEFAULT true, created_by INT FK, created_at, updated_at, deleted_at
- All songs are PUBLIC (no visibility field – all displayed on public website)
- Audio max 20MB (MP3/WAV), Video max 50MB (MP4)

**SongCategoryResource (Filament)**

- Navigation: "Worship & Media" group, icon: heroicon-o-tag
- canViewAny() / canCreate() / canEdit() / canDelete(): worship_monitor, mezmur_head, admin, superadmin
- Form: Name (required, unique in songs categories), Description (Textarea), Display Order (number), Sub-categories (HasMany RelationManager with same fields)
- canDelete() rule: no songs assigned to this category or any of its sub-categories
- Soft delete (set Inactive) when has songs (preserves existing songs)
- Sub-category CRUD: nested within category edit page

**SongResource (Filament)**

- Navigation: "Worship & Media" group, icon: heroicon-o-musical-note
- canViewAny(): all authenticated users
- canCreate() / canEdit() / canDelete(): worship_monitor, mezmur_head, admin, superadmin
- Form: Title (required), Category (Select, required – only Active categories), Sub-category (Select, required – filtered by selected category, only Active sub-categories), Lyrics (RichEditor – allow basic HTML: bold/italic/lists, no scripts, no inline CSS), Audio File (FileUpload, disk: songs-audio, accept: audio/mp3/wav, max: 20480KB), Video File (FileUpload, disk: songs-video, accept: video/mp4, max: 51200KB), Artist (TextInput nullable)
- Table: Song Code, Title, Category, Sub-category, Has Audio badge, Has Video badge, Created At
- Filter: Category, Sub-category
- canDelete(): soft delete only (sets deleted_at)

### Day 43 – Rehearsal Scheduling & Attendance
*Phase 6 – Worship & Media · Dependencies:
Day 42*

**Rehearsals Migration**

- Table: rehearsals – id, date_time DATETIME NOT NULL, location VARCHAR(255) NOT NULL, status ENUM(Scheduled, Completed, Cancelled) DEFAULT Scheduled, recurrence_type ENUM(None, Weekly, Biweekly, Monthly) DEFAULT None, recurrence_end_date DATE nullable, songs JSON nullable (array of song_ids), notes TEXT nullable, created_by INT FK, created_at, updated_at
- Table: rehearsal_attendance – id, rehearsal_id INT FK, member_id INT FK, status ENUM(Present, Absent, Excused, Late, Permission), marked_by INT FK, marked_at TIMESTAMP, created_at
- NOT linked to academic_year (ongoing year-round)
- Unique: UNIQUE(rehearsal_id, member_id)

**RehearsalResource (Filament)**

- Navigation: "Worship & Media" group, icon: heroicon-o-calendar
- canViewAny() / canCreate() / canEdit(): mezmur_head, worship_monitor, admin, superadmin
- Form: Date & Time (DateTimePicker with Ethiopian date display), Location (TextInput), Songs to Practice (MultiSelect from songs table, optional), Notes (Textarea), Recurrence Type (Select: None/Weekly/Biweekly/Monthly), IF recurrence: Recurrence End Date (EthiopianDatePicker)
- On save with recurrence: generate series of rehearsal records (one per occurrence until end date)
- Mark Attendance sub-page: shows choir members (members in Mezmur department group), same 5-status options
- Table: Date (Ethiopian), Location, Status badge, Attendance Rate %, Songs Count

**Rehearsal Reminder Notification**

- Command: php artisan rehearsals:send-reminders
- Schedule: daily at 8 AM
- Query: rehearsals WHERE date_time BETWEEN now()+23hours AND now()+25hours AND status=Scheduled
- For each: create in-app notification for all choir members (members with Mezmur dept) AND worship_monitor, mezmur_head
- Notification: type=rehearsal_reminder, title="Rehearsal Tomorrow / ነገ ልምምድ አለ", message="Rehearsal at [Location] on [Date] at [Time]. Songs: [list]", action_url=/admin/rehearsals/{id}

### Day 44 – Media Categories & Gallery CRUD
*Phase 6 – Worship & Media · Dependencies:
Day 5*

**Media Migration**

- Table: media_categories – id, name VARCHAR(100), description TEXT nullable, display_order INT DEFAULT 0, status ENUM(Active, Inactive), created_by INT FK, created_at
- Table: media_subcategories – id, category_id INT FK, name VARCHAR(100), display_order INT, status ENUM(Active, Inactive), created_by INT FK, created_at
- Table: media_items – id, title VARCHAR(255) NOT NULL, type ENUM(Photo, Video) NOT NULL, category_id INT FK NOT NULL, subcategory_id INT FK nullable, description TEXT nullable, file_path VARCHAR(500) NOT NULL, file_size_kb INT, event_album VARCHAR(255) nullable, tags TEXT nullable, visibility ENUM(Public, Members Only, Department Only) DEFAULT Public, department_id INT FK nullable (for Dept Only), uploaded_by INT FK, created_at, updated_at, deleted_at
- Photos max 10MB (JPG/PNG/GIF/WEBP), Videos max 50MB (MP4/MOV/AVI)
- File path stored relative to storage root

**MediaResource (Filament)**

- Navigation: "Worship & Media" group, icon: heroicon-o-photo
- canViewAny(): all staff (scoped by visibility)
- canCreate() / canEdit() / canDelete(): av_head for own uploads, internal_relations_head for delete, admin, superadmin
- Form: Title (required), Type (Photo/Video radio), Category (required, Select), Sub-category (optional, filtered by category), File Upload (FileUpload, accept varies by type, size limits enforced), Description, Event Album (TextInput, optional), Tags (TagsInput component, comma-separated), Visibility (Select: Public/Members Only/Department Only)
- IF Department Only selected: show Department Select (auto-fill uploader's department, editable by Admin)
- Visibility can be changed after upload (Edit form)
- Table: Thumbnail, Title, Type badge, Category, Visibility badge, Uploaded By, Upload Date, File Size

**Media Auto-Archive Scheduler**

- Command: php artisan media:auto-archive
- Schedule: annually (January 1)
- Query: media_items WHERE created_at < DATE_SUB(NOW(), INTERVAL 5 YEAR) AND deleted_at IS NULL
- For each: set deleted_at = NOW() (soft delete = archive)
- Log to Tier 1 audit trail: action=media_auto_archived, count of archived items

### Day 45 – Blog Posts & Announcements Management
*Phase 6 – Worship & Media · Dependencies:
Day 5*

**Blog & Announcements Migration**

- Table: blog_posts – id, title VARCHAR(255), title_am VARCHAR(255) nullable (Amharic version), content LONGTEXT, content_am LONGTEXT nullable, author_id INT FK, publish_date DATE nullable, featured_image VARCHAR(500) nullable, tags TEXT nullable, status ENUM(Draft, Scheduled, Published, Archived) DEFAULT Draft, published_at TIMESTAMP nullable, created_at, updated_at, deleted_at
- Table: announcements – id, title VARCHAR(255), title_am VARCHAR(255) nullable, content LONGTEXT, content_am LONGTEXT nullable, start_date DATE NOT NULL, end_date DATE nullable, is_urgent BOOLEAN DEFAULT false, status ENUM(Active, Expired, Archived) DEFAULT Active, created_by INT FK, created_at, updated_at, deleted_at

**BlogPostResource (Filament)**

- Navigation: "Worship & Media" group, icon: heroicon-o-document-text
- canViewAny() / canCreate() / canEdit() / canDelete(): av_head, admin, superadmin
- Form: Title EN (required), Title AM (optional Amharic), Content EN (RichEditor), Content AM (RichEditor, optional), Publish Date (EthiopianDatePicker, optional – leave empty for immediate publish), Featured Image (FileUpload, optional), Tags (TagsInput), Status (Select: Draft/Scheduled/Published/Archived)
- Status = Scheduled + future Publish Date → will auto-publish on that date
- Only AV Head/Admin/Superadmin can set status to Published
- Soft delete changes status to Archived (deleted_at set)
- Table: Title, Status badge, Author, Publish Date (Ethiopian), Tags

**AnnouncementResource (Filament)**

- Navigation: "Worship & Media" group, icon: heroicon-o-megaphone
- Form: Title EN (required), Title AM (optional), Content EN (RichEditor), Content AM (optional), Start Date (EthiopianDatePicker required), End Date (EthiopianDatePicker nullable), Is Urgent (Toggle)
- Urgent flag: red border on homepage, pinned to top of announcements list
- Auto-hide: scheduled command checks end_date daily, sets status=Expired when end_date < today
- No end_date: remains visible indefinitely
- Table: Title, Is Urgent badge, Start Date, End Date (or "Ongoing"), Status badge

**Content Publication Scheduler**

- Command: php artisan content:publish-scheduled
- Schedule: daily at midnight
- Blog: UPDATE blog_posts SET status=Published, published_at=NOW() WHERE status=Scheduled AND publish_date <= CURDATE()
- Announcements: UPDATE announcements SET status=Expired WHERE end_date < CURDATE() AND status=Active
- Log both operations to Tier 1 audit trail (system action)

### Day 46 – FAQ Management & AV Head Dashboard
*Phase 6 – Worship & Media · Dependencies:
Day 45*

**FAQ Migration & Resource**

- Table: faqs – id, question TEXT NOT NULL, question_am TEXT nullable, answer LONGTEXT NOT NULL, answer_am LONGTEXT nullable, display_order INT DEFAULT 0, is_featured BOOLEAN DEFAULT false, is_active BOOLEAN DEFAULT true, created_by INT FK, created_at, updated_at, deleted_at
- FAQResource: Navigation "Worship & Media" group
- canViewAny() / canCreate() / canEdit() / canDelete(): av_head, admin, superadmin
- Form: Question EN, Question AM (optional), Answer EN (RichEditor), Answer AM (optional), Display Order (number), Is Featured (Toggle – shown prominently on landing page)
- Drag-to-reorder table (Filament sortable() on display_order column)
- Soft delete: sets is_active=false (hidden from public but preserved)
- Table: Question snippet, Featured badge, Active badge, Display Order, Actions

**AV Head Dashboard**

- Widget: ContentSummaryWidget – Published posts count, Active announcements count, Total songs, Total media items
- Widget: RecentPublicationsWidget – Last 5 published blog posts with dates
- Widget: ScheduledContentWidget – Upcoming scheduled posts/announcements
- Widget: MediaByVisibilityWidget – Pie chart: Public vs Members Only vs Department Only
- Widget: UpcomingRehearsalsWidget (visible to Mezmur Head/Worship Monitor): next 3 rehearsals

### Day 47 – Inventory Items CRUD
*Phase 7 – Inventory & Archives · Dependencies:
Day 5*

**Inventory Migration**

- Table: inventory_items – id, item_code VARCHAR(20) UNIQUE nullable (INV-000001), name VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL (Electronics/Furniture/Books/Supplies/Equipment/Other), quantity DECIMAL(10,2) DEFAULT 0, unit VARCHAR(50) NOT NULL (pieces/boxes/sets/kg/liters/Other), purchase_date DATE nullable, purchase_price DECIMAL(10,2) nullable, supplier VARCHAR(255) nullable, location VARCHAR(255) nullable, status ENUM(Active, Damaged, Lost, Disposed) DEFAULT Active, notes TEXT nullable, created_by INT FK, created_at, updated_at, deleted_at
- Table: inventory_movements – id, item_id INT FK, movement_type ENUM(Stock In, Stock Out), sub_type VARCHAR(100) (Purchase/Donation/Return/Usage/Distribution/Loan/Loss), quantity DECIMAL(10,2) NOT NULL, movement_date DATE NOT NULL, recipient_source VARCHAR(255) nullable, reference_number VARCHAR(100) nullable, notes TEXT nullable, recorded_by INT FK, override_justification TEXT nullable, created_at
- Current stock computed: initial quantity + SUM(Stock In) – SUM(Stock Out)

**InventoryResource (Filament)**

- Navigation: "Inventory" group, icon: heroicon-o-archive-box
- canViewAny() / canCreate() / canEdit() / canDelete(): inventory_staff, nibret_hisab_head, admin, superadmin
- Form: Name (required), Category (CustomOptionSelect with predefined), Quantity (initial, number), Unit (CustomOptionSelect), Purchase Date (EthiopianDatePicker nullable), Purchase Price (number nullable), Supplier, Location, Notes
- Status field: read-only in form, changed via actions (Mark Damaged, Mark Lost, Mark Disposed)
- canDelete() rule: if any movement history → block, show "Use 'Mark Disposed' instead"
- Table: Item Code, Name, Category badge, Current Stock (computed), Unit, Status badge, Location, Purchase Date (Ethiopian)
- Filter: Category, Status, Location

### Day 48 – Inventory Movements & Analytics
*Phase 7 – Inventory & Archives · Dependencies:
Day 47*

**Record Movement Action**

- Action "Record Movement" on InventoryResource → modal or linked page
- Form: Movement Type (Stock In / Stock Out radio), Sub-type (changes options based on type: StockIn → Purchase/Donation/Return, StockOut → Usage/Distribution/Loan/Loss), Quantity (required, positive), Movement Date (EthiopianDatePicker, defaults today), Recipient/Source (TextInput nullable), Reference Number (nullable), Notes (Textarea nullable)
- Validation for Stock Out: calculate current_stock = initial_qty + SUM(StockIn.qty) – SUM(StockOut.qty). If movement quantity > current_stock: show error "Insufficient stock. Available: [X]. Requested: [Y]"
- Admin override: if current_stock validation fails and user is Admin/Superadmin → show warning with "Override with Justification" button → opens justification modal → saves with override_justification text
- Override logged to Tier 2 audit trail with justification
- Movements sub-tab on InventoryResource view: shows all movements chronologically with running balance

**Inventory Analytics Page**

- Page: Inventory > Analytics
- Widget: TotalItemsByCategory – bar chart (Electronics: 5, Furniture: 12, etc.)
- Widget: TotalInventoryValue – SUM(purchase_price × current_quantity) where purchase_price is not null
- Widget: MostUsedItems – top 10 by Stock Out frequency
- Widget: LowStockItems – items where current_quantity < 5 (configurable threshold)
- Widget: RecentMovements – last 10 movements table
- Export: Excel (full inventory list with current stock, values), PDF (formatted report)

### Day 49 – Department Documents & Archives
*Phase 7 – Inventory & Archives · Dependencies:
Day 5*

**Documents Migration & Model**

- Table: documents – id, title VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, file_size_kb INT, file_type VARCHAR(50), description TEXT nullable, tags TEXT nullable, document_date DATE nullable, visibility ENUM(Public, Members Only, Department Only) DEFAULT Department Only, department_id INT FK NOT NULL, uploaded_by INT FK NOT NULL, created_at, updated_at, deleted_at
- Allowed types: PDF, DOCX, XLSX, PPTX, JPG, PNG (validate mime type on upload)
- No file size limit (natural server limit applies)
- Tags stored as comma-separated string for simplicity

**DocumentResource (Filament)**

- Navigation: "Archives" group, icon: heroicon-o-folder
- canViewAny(): all authenticated users (scoped by dept + visibility)
- canCreate(): dept heads, dept secretaries, admin, superadmin
- canEdit() (change visibility): original uploader OR dept head OR admin/superadmin
- canDelete(): dept head (own dept only), admin, superadmin
- Form: Title (required), File Upload (required, validate file type), Description (Textarea), Tags (TagsInput), Document Date (EthiopianDatePicker nullable), Visibility (Select: Public/Members Only/Department Only)
- Department auto-set from auth user's department (read-only for non-admin)
- View scope: Dept Only docs visible only to users of same department (global scope)
- Public docs: visible to all staff + public website (if linked)
- Table: Title, File Type icon, Visibility badge, Department, Tags, Upload Date (Ethiopian), File Size, Actions
- Search: title LIKE, description LIKE, tags LIKE, document_date range
- Filter: Visibility, Document Date range, Tags

**Contact Messages View**

- Table: contact_messages – id, name VARCHAR(255), email VARCHAR(191) nullable, phone VARCHAR(20) nullable, subject VARCHAR(255), message TEXT, is_read BOOLEAN DEFAULT false, created_at
- Filled by public contact form (Day 59)
- ContactMessageResource: Navigation "Archives" or "Communications"
- canViewAny(): admin, internal_relations_head
- Table: Name, Subject, Message snippet, Date (Ethiopian), Read/Unread badge
- Action: Mark as Read, Delete (admin only, 2-year retention)
- No reply functionality in MVP (reply externally)

### Day 50 – Library Resources & Categories
*Phase 7 – Inventory & Archives · Dependencies:
Day 5*

**Library Migration**

- Table: library_categories – id, name VARCHAR(100), description TEXT nullable, display_order INT DEFAULT 0, status ENUM(Active, Inactive), created_by INT FK, created_at
- Table: library_subcategories – id, category_id INT FK, name VARCHAR(100), display_order INT, status ENUM(Active, Inactive), created_by INT FK, created_at
- Table: library_resources – id, title VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, category_id INT FK NOT NULL, subcategory_id INT FK nullable, description TEXT nullable, is_featured BOOLEAN DEFAULT false, is_active BOOLEAN DEFAULT true, file_size_kb INT, uploaded_by INT FK, created_at, updated_at, deleted_at
- Public download (no auth required) → store in storage/app/public/library/
- PDF only for library resources

**LibraryResource & CategoryResource (Filament)**

- LibraryCategoryResource: canViewAny() / canCreate() / canEdit() / canDelete(): education_head, admin, superadmin
- LibraryResource: canCreate() / canEdit() / canDelete(): education_head, admin, superadmin (per business rule 9.5)
- LibraryResource form: Title, File (PDF only, FileUpload), Category (required), Sub-category (optional), Description, Is Featured (Toggle)
- Featured resources highlighted on public library page
- Table: Title, Category, Featured badge, File Size, Upload Date

### Day 51 – Event Management
*Phase 8 – Events & Fundraising · Dependencies:
Day 5*

**Events Migration**

- Table: events – id, name VARCHAR(255) NOT NULL, date_time DATETIME NOT NULL, location VARCHAR(500) NOT NULL, description TEXT nullable, featured_image VARCHAR(500) nullable, registration_required BOOLEAN DEFAULT false, max_capacity INT nullable, registration_deadline DATE nullable, status ENUM(Draft, Published, Full, Ongoing, Completed, Cancelled) DEFAULT Draft, recurrence_type ENUM(None, Weekly, Monthly, Custom) DEFAULT None, recurrence_end_date DATE nullable, parent_event_id INT FK nullable (for recurring instances), created_by INT FK, created_at, updated_at, deleted_at

**EventResource (Filament)**

- Navigation: "Events & Fundraising" group, icon: heroicon-o-calendar-days
- canViewAny() / canCreate() / canEdit() / canDelete(): admin, superadmin
- Form: Name (required), Date & Time (DateTimePicker with Ethiopian display), Location (TextInput), Description (RichEditor nullable), Featured Image (FileUpload nullable), Registration Required (Toggle), Max Capacity (number nullable, shows when Registration Required = true), Registration Deadline (EthiopianDatePicker nullable), Status, Recurrence Type, Recurrence End Date
- On save with recurrence: generate recurring event instances (separate rows linked by parent_event_id)
- Status transitions: Draft → Published, Published → Ongoing, Ongoing → Completed, Any → Cancelled
- Public calendar: shows Published + Ongoing + Full events

### Day 52 – Fundraising Campaigns
*Phase 8 – Events & Fundraising · Dependencies:
Day 5*

**Fundraising Migration**

- Table: fundraising_campaigns – id, name VARCHAR(255) NOT NULL, target_amount DECIMAL(12,2) NOT NULL, total_raised DECIMAL(12,2) DEFAULT 0, start_date DATE NOT NULL, end_date DATE nullable, description TEXT nullable, featured_image VARCHAR(500) nullable, category ENUM(Building, Missionary, Charity, General) DEFAULT General, bank_account_info TEXT nullable, status ENUM(Draft, Active, Completed, Cancelled) DEFAULT Draft, created_by INT FK, updated_by INT FK nullable, created_at, updated_at, deleted_at

**FundraisingResource (Filament)**

- Navigation: "Events & Fundraising" group, icon: heroicon-o-heart
- canViewAny() / canCreate() / canEdit() / canDelete(): admin, superadmin
- Form: Name, Target Amount, Start Date (EthiopianDatePicker), End Date (nullable), Description (RichEditor), Featured Image, Category, Bank Account Info (Textarea – displayed on website for offline donations), Status
- Action: "Update Total Raised" → modal with Amount input, on save: UPDATE total_raised, log to Tier 2 audit trail (who updated, old value, new value)
- Computed fields: percentage = (total_raised/target_amount×100), days_remaining = (end_date - today) if end_date set
- Table: Name, Target, Total Raised, Progress %, Status badge, Start Date
- Public website display: progress bar, percentage, days remaining, bank account details

### Day 53 – Beneficiaries & Aid Distribution
*Phase 8 – Events & Fundraising · Dependencies:
Day 5*

**Beneficiaries Migration**

- Table: beneficiaries – id, beneficiary_code VARCHAR(20) (B-000001), full_name VARCHAR(255) NOT NULL, phone VARCHAR(20) UNIQUE NOT NULL, address TEXT NOT NULL, type ENUM(Individual, Family, Organization), need_category VARCHAR(100) (Food/Medical/Education/Housing/Other), email VARCHAR(191) nullable, id_number VARCHAR(100) nullable, dependents_count INT nullable, monthly_income DECIMAL(10,2) nullable, notes TEXT nullable, status ENUM(Active, Inactive, Completed) DEFAULT Active, created_by INT FK, created_at, updated_at, deleted_at
- Table: aid_distributions – id, beneficiary_id INT FK NOT NULL, distribution_date DATE NOT NULL, aid_type VARCHAR(100) NOT NULL (Cash/Food/Clothing/Medical/Education/Housing/Other), amount DECIMAL(10,2) NOT NULL, distributed_by INT FK NOT NULL, receipt_number VARCHAR(100) nullable, notes TEXT nullable, is_locked BOOLEAN DEFAULT false, locked_at TIMESTAMP nullable, created_at, updated_at
- Distribution locked after 30 days (auto + override by Charity Head)
- Distribution date cannot be in the future (validation)

**BeneficiaryResource & AidDistributionResource (Filament)**

- Navigation: "Charity" group, icon: heroicon-o-hand-raised
- Access: charity_head, admin, superadmin
- BeneficiaryResource form: Full Name, Phone (unique), Address, Type (Select), Need Category (CustomOptionSelect), Email (nullable), ID Number, Dependents Count, Monthly Income, Notes, Status
- canDelete(): block if any aid distributions exist → use "Mark Completed" action instead
- "Mark Completed" action: sets status=Completed, hides from active lists
- AidDistributionResource (as RelationManager on BeneficiaryResource): Distribution Date (EthiopianDatePicker, max=today), Aid Type (CustomOptionSelect), Amount (required, >0), Receipt Number (optional), Notes
- Distributed By: auto-filled from auth user
- Edit locked after 30 days: canEdit() checks created_at > 30 days → only charity_head can unlock with justification
- Auto-lock command: php artisan aid:auto-lock – daily, locks distributions > 30 days

### Day 54 – Beneficiary Reports & Charity Dashboard
*Phase 8 – Events & Fundraising · Dependencies:
Day 53*

**Beneficiary Report**

- Filters: Date Range (distribution_date), Beneficiary Type, Need Category, Aid Type, Beneficiary Status
- Metrics: Total Active Beneficiaries, Total Aid Distributed (sum amounts), Average Aid Per Beneficiary, Aid by Type breakdown
- Table: Beneficiary Name, Type, Total Received, Last Distribution Date, Status
- Drill-down: click beneficiary → shows full distribution history
- Chart: Monthly distribution trend (bar), Aid by type (pie), Beneficiary status distribution (donut)
- Export: Excel (full distribution records), PDF (summary with charts as images), CSV
- Access: charity_head, internal_relations_head, admin, superadmin

**Charity Dashboard Widgets**

- Widget: ActiveBeneficiariesWidget – count of Active beneficiaries
- Widget: AidDistributedThisMonthWidget – total amount + count of distributions
- Widget: AidByTypeWidget – bar chart of aid types distributed
- Widget: RecentDistributionsWidget – last 10 distributions table
- Widget: BeneficiaryStatusWidget – pie chart (Active/Inactive/Completed)

### Day 55 – User Management (Admin Full CRUD)
*Phase 9 – Security & Governance · Dependencies:
Days 4, 5*

**UserResource (Filament)**

- Navigation: "Security" group, icon: heroicon-o-shield-check, label "User Management"
- canViewAny() / canCreate() / canEdit() / canDelete(): admin, superadmin
- Create form: Display Name, Phone (required, +251, unique), Email (optional), Role (Select from all 16 roles), Department (Select from 7 departments – required for non admin/superadmin roles), Temporary Password (auto-generate 10-char random, show once)
- Password shown once on creation → admin shares via secure channel (phone call, in person)
- On create: set temp_password_changed=false (forces user to change on first login)
- Edit form: same fields, password field shows "Reset Password" action (not inline edit)
- Role change action: confirmation "Changing role will immediately log out this user." → force invalidate all sessions for that user, log to Tier 1 audit trail
- Only one user per sub-dept head role (e.g., one hr_head) – enforce via unique role+dept constraint with warning

**Lock/Unlock, Activate/Deactivate Actions**

- Lock action (Admin/Superadmin): sets is_locked=true, adds reason note, logs to Tier 1
- Unlock action: sets is_locked=false, resets failed_login_attempts=0, logs to Tier 1
- Deactivate action: sets is_active=false, immediately invalidates all sessions, logs to Tier 1
- Activate action: sets is_active=true, logs to Tier 1
- Reset Password action: generate new temp password, set temp_password_changed=false, show password once
- Cannot delete users with any historical records: canDelete() checks contributions, enrollments, tour_passengers (as registered_by), audit_logs (as user_id)
- If records exist: only Deactivate is allowed (not delete)

### Day 56 – Two-Tier Audit Log System
*Phase 9 – Security & Governance · Dependencies:
Day 55*

**Audit Log Migration**

- Table: audit_logs – id BIGINT PK auto-increment, tier ENUM(security, financial) NOT NULL, user_id INT FK nullable (null for system actions), action_type VARCHAR(100) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT nullable, old_value JSON nullable, new_value JSON nullable, ip_address VARCHAR(45), user_agent TEXT nullable, notes TEXT nullable, created_at TIMESTAMP NOT NULL
- NO updated_at, NO deleted_at (immutable – never edit or delete manually)
- Index on: (tier, created_at) for retention cleanup, (entity_type, entity_id) for timeline queries, (user_id, created_at) for user activity
- Partitioning consideration (future): partition by tier for performance

**AuditLogResource (Filament)**

- Navigation: "Security" group, icon: heroicon-o-clipboard-document-list
- canViewAny(): admin, superadmin
- READ-ONLY resource (no create, edit, delete actions from UI)
- Table: Tier badge (Security=yellow/Financial=green), Action Type, Entity Type, Entity ID, User (linked), Old Value snippet, New Value snippet, IP Address, Created At (Ethiopian)
- Filters: Tier (Security/Financial), Action Type, Entity Type, User, Date Range
- Detail view: show full old_value and new_value JSON in formatted code block
- Export (Superadmin only): export to CSV with all fields, logged to export_logs

**Auto-Purge Schedulers**

- Command: php artisan logs:purge-security-audit
- Deletes: DELETE FROM audit_logs WHERE tier=security AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
- Schedule: $schedule->command("logs:purge-security-audit")->daily()->at("02:00")
- Command: php artisan logs:purge-session-logs
- Deletes: DELETE FROM user_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
- Command: php artisan logs:purge-error-logs
- Deletes from error_logs table where created_at < 2 months ago
- Command: php artisan logs:purge-export-logs
- Deletes from export_logs where created_at < 1 year ago
- All purge commands log their execution count to Tier 1 audit before purging

### Day 57 – System Health Dashboard & Error Logs
*Phase 9 – Security & Governance · Dependencies:
Day 56*

**Error Logs Migration & Handler**

- Table: error_logs – id, error_type VARCHAR(255), error_message TEXT, stack_trace LONGTEXT, user_id INT nullable, url VARCHAR(500), http_method VARCHAR(10), request_data JSON nullable (sanitized – remove passwords), user_agent TEXT, created_at
- Modify app/Exceptions/Handler.php: override register() → add Reportable callback that INSERTs to error_logs
- Sanitize request data: remove fields: password, password_confirmation, token, _token from stored data
- Critical error threshold: if same error_type occurs > 10 times in 1 hour → create in-app notification for superadmin
- Retention: 2 months (purge command, Day 56)

**ErrorLogResource (Filament)**

- Navigation: "System" group (Superadmin only), icon: heroicon-o-bug-ant
- canViewAny(): superadmin ONLY
- READ-ONLY table: Error Type, Error Message snippet, URL, User (if authenticated), Created At (Ethiopian)
- Detail view: full stack trace in code block, full request data, user agent
- Filter: Date Range, Error Type, URL
- Bulk delete: Superadmin can clear old logs manually

**System Health Dashboard**

- Page: System > System Health (Superadmin only)
- Widget: ServerUptimeWidget – reads server uptime via exec("uptime") or file_get_contents("/proc/uptime")
- Widget: DatabaseResponseTimeWidget – execute SELECT 1 + measure elapsed milliseconds, show current + 24hr average
- Widget: StorageUsageWidget – disk_total_space("/") and disk_free_space("/") → show % used, bar indicator (yellow >40%, red >70%)
- Widget: ActiveSessionsWidget – COUNT from user_sessions where last_activity > now()-30min
- Widget: FailedLoginsWidget – COUNT from audit_logs where action_type=login_failed AND created_at > NOW()-24hr
- Widget: ErrorRateWidget – COUNT from error_logs WHERE created_at > NOW()-1hr → show per-hour rate
- Alerts: if storage >40% OR error_rate >10/hr OR db_response >2000ms → in-app notification to superadmin
- Alert check command: php artisan system:check-health – schedule every 15 minutes
- No export for system health (live dashboard only per business rule)

### Day 58 – In-App Notification System (All Triggers)
*Phase 9 – Security & Governance · Dependencies:
Days 37, 43, 26*

**Notification Infrastructure**

- Laravel built-in notifications: php artisan make:notification BaseNotification
- All notifications use: toDatabase() channel (stores in notifications table)
- PWA push: toWebPush() channel (requires minishlink/web-push: composer require minishlink/web-push)
- Table: notifications (Laravel default: id UUID, type, notifiable_type, notifiable_id, data JSON, read_at, created_at)
- Store in data JSON: {type, title, message, action_url, context_data{}} for each notification type
- Filament notification bell: configure via Filament built-in database notifications or custom widget
- Bell icon shows count of unread (read_at IS NULL) notifications
- Click notification: marks read_at = NOW(), redirects to action_url

**All Notification Triggers (Complete List)**

- 1. Tour: New public registration → TourHead: "New registration from [Name] for [Place]"
- 2. Tour: Registration confirmed → Linked Member: "Your registration for [Place] tour is confirmed"
- 3. Tour: Registration cancelled → Linked Member: "Your registration for [Place] tour was cancelled"
- 4. Tour: Tour cancelled → All confirmed registrants: "Tour to [Place] on [Date] has been cancelled"
- 5. Tour: Tour full (max_capacity reached) → TourHead: "Tour to [Place] is now full"
- 6. Rehearsal: Reminder 24hrs before → Choir members + Worship Monitor + Mezmur Head
- 7. Attendance: 3 days before auto-lock → Education Monitor: "Session for [Class] on [Date] locks in 3 days"
- 8. Attendance: Sync conflicts detected → Education Monitor: "X sync conflicts detected for [Class]"
- 9. Finance: Monthly outstanding summary → Finance Head + Nibret Hisab Head
- 10. System: Storage >40% → Superadmin: "Storage usage at [X]%"
- 11. System: Error rate >10/hr → Superadmin: "Error rate: [X] errors/hour"
- 12. System: DB response >2s → Superadmin: "Database slow: [X]ms response time"
- 13. Content: Contributions archived → Finance Head: "Contributions for [Year] archived"
- 14. Teacher: New class assignment → Teacher (if member-teacher with account): "Assigned to [Class]"
- 15. Admin: Custom option pending → Admin: "X custom dropdown options pending approval"

**PWA Push Notifications**

- Table: push_subscriptions – id, user_id FK, endpoint VARCHAR(500), p256dh VARCHAR(255), auth_key VARCHAR(255), created_at
- Service worker: self.addEventListener("push", ...) → self.registration.showNotification()
- Laravel: implement toWebPush() in notifications with title, body, icon, data (action_url)
- User registration: JavaScript requestNotificationPermission() on first admin load
- On permission granted: send subscription data to POST /api/push/subscribe
- Send push only if user has installed PWA (push_subscriptions record exists)
- 90-day auto-purge of read notifications: command php artisan notifications:purge-read scheduled daily

### Day 59 – Complete Public Website (All Pages)
*Phase 10 – Public Website · Dependencies:
Days 37, 42, 44, 45, 50, 51, 52*

**Public Route Structure**

- GET / → HomeController@index (landing page)
- GET /about → AboutController@index
- GET /programs → ProgramsController@index
- GET /blog → BlogController@index | GET /blog/{slug} → BlogController@show
- GET /songs → SongLibraryController@index | GET /songs/{id} → SongLibraryController@show
- GET /media → MediaGalleryController@index
- GET /events → EventCalendarController@index
- GET /library → LibraryController@index
- GET /fundraising → FundraisingController@index
- GET /tours → TourController@index | GET /tours/{id}/register → TourController@showRegister | POST /tours/{id}/register → TourController@register
- GET /contact → ContactController@index | POST /contact → ContactController@submit
- POST /language/{locale} → LanguageController@switch
- All public routes: rate-limited (60 req/min), CSRF on POST forms

**Home/Landing Page**

- Hero section: Church name (Amharic + English), tagline, mission statement
- Latest Announcements section: top 3 active announcements, urgent ones highlighted in red
- Upcoming Events section: next 3 events from events table (Published + Ongoing) with Ethiopian dates
- Featured FAQs section: FAQs where is_featured=true
- Active Fundraising Campaigns: campaigns where status=Active, with progress bars
- Quick links: Song Library, Media Gallery, Contact Us, Tours
- Navigation header: Logo, menu links, Language Switcher (EN/አማ toggle)
- Language Switcher: POST /language/{locale}, stores in cookie (guests) or user profile (logged-in)
- Fallback: if Amharic content missing → show English with "(English)" indicator

**Song Library Public Page**

- Organized by category (tabs or accordion)
- Sub-categories shown nested under categories
- Each song: title, artist, lyrics snippet, play buttons (audio/video if available)
- Lyrics view modal or dedicated page: full lyrics with audio/video player
- Filter by category/sub-category
- Search by title or lyrics keywords
- All songs shown (no visibility filter – all songs are public)

**Media Gallery Public Page**

- Show only Public visibility media items
- Organized by category and album/event
- Photo grid with lightbox on click (use PhotoSwipe or similar CDN library)
- Video items: thumbnail with play icon, opens video player modal
- Filter by category, sub-category, album
- No login required for Public media

**Contact Form**

- Fields: Name, Email (optional), Phone (optional), Subject, Message
- Honeypot field (hidden, must be empty to pass validation)
- Rate limit: 3 submissions per IP per hour
- On submit: INSERT contact_messages, show success message "Thank you! We'll be in touch."
- Admin/Internal Relations Head notified via in-app notification of new contact message

### Day 60 – PWA Config, Driver.js Tours, Data Retention & Final QA
*Phase 10 – Final Polish · Dependencies:
All previous days*

**PWA Full Configuration**

- public/manifest.json: name="FINOT Church", short_name="FINOT", start_url="/admin", display="standalone", theme_color="#1B4F72", background_color="#FFFFFF", orientation="portrait", icons: [{src:"/icons/192.png",sizes:"192x192",type:"image/png"},{src:"/icons/512.png",sizes:"512x512",type:"image/png",purpose:"any maskable"}]
- Service worker final caches: static pages (/, /about, /programs, /contact), Filament CSS/JS, offline fallback page
- Install prompt: Alpine.js component checks visit_count cookie. On 3rd visit, show install banner. Dismiss button: sets dismissed cookie (7 days), do not show again. Accept button: calls prompt.prompt() (browser native)
- Install tracking: log to install_prompt_shown table on both show and accept events

**Driver.js Tours – All 16 Roles**

- Complete remaining role tours (partial list – complete all):
- hr_head.js: Members, Groups, Bulk Assign, Export, Timeline steps
- finance_head.js: Contribution Settings, Contributions, Donations, Outstanding, Reports, Export steps
- charity_head.js: Beneficiaries, Aid Distribution, Record Contribution, Reports steps
- tour_head.js: Tours, Registrations, Attendance, Call Button, Reports steps
- av_head.js: Media, Blog, Announcements, FAQ steps
- inventory_staff.js: Inventory Items, Record Movement, Analytics steps
- admin.js: User Management, Custom Options, Contact Messages, Events, Fundraising steps
- superadmin.js: System Health, Error Logs, Audit Logs, Backup, all admin features steps
- All tours: trigger only if product_tour_completed table has no entry for that user + role
- Restart Tour button in user profile dropdown (clears completed flag)

**Data Retention – All Schedulers Complete**

- Verify all scheduled commands are registered in Console/Kernel.php:
- Daily: attendance:auto-lock, attendance:send-lock-reminders, content:publish-scheduled, rehearsals:send-reminders, aid:auto-lock, system:check-health (every 15 min), notifications:purge-read, logs:purge-security-audit, logs:purge-session-logs, logs:purge-error-logs, logs:purge-export-logs
- Monthly: finance:notify-outstanding
- Annually: media:auto-archive
- Test each command: php artisan schedule:test {command-name}
- Confirm retention periods enforced: security logs 30d, session logs 90d, read notifications 90d, error logs 2mo, export logs 1yr, media flags 5yr

**Final QA Checklist – All 16 Roles**

- Test login with phone number for each of 16 users
- Test first-login password change flow (temp_password_changed)
- Test progressive lockout (5 fails = 1min lock, 10 fails = 5min lock)
- Test max 3 active sessions (4th login revokes oldest session)
- Verify department scope: each role only sees their department data
- Test all 60-day features with correct role access
- Test Ethiopian date display throughout all pages (no Gregorian dates showing)
- Test Pagume exclusion from contribution months and inclusion in all other pickers
- Test PWA install prompt on 3rd visit
- Test offline attendance sync with network simulation (Chrome DevTools)
- Test all export formats (Excel/PDF/CSV) for each major report
- Test all Driver.js tours render correctly for each role
- Verify all scheduled commands run without errors
- Test auto-lock fires after 30-day session
- Test tour cancellation notifies all confirmed passengers
- Test all "Others" custom option workflow (add, pending badge, approve, merge)
- Run php artisan test to execute all unit/integration tests
- Check php artisan icons:cache runs without error
- Performance: verify dashboard loads in < 3 seconds for all roles
- Security: verify no SQL injection via role-based scope tests, CSRF tokens on all forms

**Deployment Checklist**

- Set APP_ENV=production, APP_DEBUG=false in .env
- Run: php artisan config:cache, php artisan route:cache, php artisan view:cache
- Run: php artisan icons:cache
- Run: php artisan storage:link
- Run: php artisan migrate --force
- Run: php artisan db:seed --class=DepartmentSeeder --class=RoleSeeder --class=UserSeeder
- Set up cron for Laravel scheduler: * * * * * php /path/to/artisan schedule:run
- Set up queue worker: php artisan queue:work --daemon (or supervisor)
- Configure web server (Nginx/Apache) to point to /public directory
- Set correct file permissions: storage/ and bootstrap/cache/ writable by web server
- Test SSL certificate in production
- Final: login as superadmin, verify all 16 user logins work
