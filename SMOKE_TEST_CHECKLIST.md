---
description: Role-based smoke test checklist
---

# Finot CMS — Role-Based Smoke Test Checklist

## How to use this document
- **Goal**: quickly confirm the system works for each role after a deploy.
- **Mark**: check the boxes as you test.
- **If something fails**: capture
  - URL
  - role used
  - exact steps
  - screenshot (if UI)
  - error text / stack trace

## Test environment info
- **Base URL**: ______________________________
- **Build/Commit**: __________________________
- **Date tested**: ___________________________
- **Tester name**: ___________________________
- **Browser**: _______________________________

## Global smoke tests (run once)
- [ ] **Home page loads** (`/`)
- [ ] **About page loads** (`/about`)
- [ ] **Blog page loads** (`/blog`)
- [ ] **Media page loads** (`/media`)
- [ ] **Songs page loads** (`/songs`)
- [ ] **Library page loads** (`/library`)
- [ ] **Tours page loads** (`/tours`)
- [ ] **Fundraising page loads** (`/fundraising`)
- [ ] **Contact page loads** (`/contact`)
- [ ] **Language switch works** (switch to Amharic + refresh, switch back + refresh)

---

## Shared admin smoke tests (run for every role below)
### Login / session
- [ ] Can open login page
- [ ] Can login with phone + password
- [ ] Can logout
- [ ] Session stays valid while browsing

### Basic navigation
- [ ] Dashboard loads
- [ ] Menu items shown match role expectations (no missing critical items / no forbidden items)

### Profile
- [ ] Can open profile
- [ ] Can update profile fields (if allowed)
- [ ] Can change language and it persists after refresh

---

# Role Checklists

> Default seeded password for these test accounts (per `database/seeders/UserSeeder.php`): `Admin1234`

## 1) Super Admin
**What this role does (non-technical)**
- Full control of the whole system. Can manage users, data, settings, and recovery.

**Test account**
- **Email**: `superadmin@finot.org`
- **Phone**: `+251911000001`
- **Password**: `Admin1234`

**What they can do (high level)**
- Everything, including system-wide settings and recovery (backups/restore).

**Checklist**
- [ ] Login works
- [ ] **Users Management**: Can view/create/update/delete users
- [ ] **User Sessions**: Can view/manage user sessions
- [ ] **Members**: Can view/create/update/delete members
- [ ] **Member Groups**: Can view/create/update/delete member groups
- [ ] **Group Assignments**: Can view/create/update/delete group assignments
- [ ] **Parents**: Can view/create/update/delete parent/guardian info
- [ ] **Finance - Contributions**: Can view/create/update/delete contributions
- [ ] **Finance - Donations**: Can view/create/update/delete donations
- [ ] **Finance - Transactions**: Can view/create/update/delete financial transactions
- [ ] **Finance - Bank Accounts**: Can view/create/update/delete bank accounts
- [ ] **Reports**: Can view/generate/export all reports
- [ ] **Education - Academic Years**: Can view/create/update/delete academic years
- [ ] **Education - Classes**: Can view/create/update/delete classes
- [ ] **Education - Subjects**: Can view/create/update/delete subjects
- [ ] **Education - Enrollments**: Can view/create/update/delete enrollments
- [ ] **Education - Promotions**: Can view/create/update/delete promotions
- [ ] **Education - Students**: Can enroll/remove/promote/bulk promote students
- [ ] **Education - Attendance Sessions**: Can view attendance sessions
- [ ] **Education - Library**: Can upload library resources, manage categories/subcategories
- [ ] **Worship - Songs**: Can view/create/update/delete songs
- [ ] **Worship - Rehearsals**: Can view/create/update/delete rehearsals
- [ ] **Worship - Song Categories**: Can manage song categories/subcategories
- [ ] **Media - Items**: Can view/create/update/delete media items
- [ ] **Media - Categories**: Can manage media categories/subcategories
- [ ] **Content - Blog Posts**: Can view/create/update/delete blog posts
- [ ] **Content - Announcements**: Can view/create/update/delete announcements
- [ ] **Content - FAQs**: Can view/create/update/delete FAQs
- [ ] **Content - Documents**: Can view/create/update/delete documents
- [ ] **Content - Schedule**: Can schedule content publication
- [ ] **Charity - Beneficiaries**: Can view/create/update/delete beneficiaries
- [ ] **Charity - Aid Distributions**: Can view/create/update/delete aid distributions
- [ ] **Charity - General**: Can manage charity settings
- [ ] **Tours**: Can view/create/update/delete tours
- [ ] **Tour Attendances**: Can manage tour attendances
- [ ] **Tour Passengers**: Can manage tour passengers
- [ ] **Events**: Can view/create/update/delete events
- [ ] **Event Registrations**: Can manage event registrations
- [ ] **Fundraising**: Can create/update/delete fundraising
- [ ] **System - Departments**: Can manage departments and assign roles
- [ ] **System - Contact Messages**: Can view/create/update/delete contact messages
- [ ] **System - Custom Options**: Can manage custom options
- [ ] **System - Pages**: Can manage pages
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation
- [ ] **System Settings**: Can access system settings (if present)
- [ ] **Backups**: Can create/restore backups (if present)
- [ ] **Audit Logs**: Can view audit logs (if present)

---

## 2) Admin
**What this role does (non-technical)**
- Runs day-to-day operations across modules (members, finance, education, content, etc.).

**Test account**
- **Email**: `admin@finot.org`
- **Phone**: `+251911000002`
- **Password**: `Admin1234`

**What they can do (high level)**
- Broad operational access across all modules.
- May not have some “system settings” level privileges.

**Checklist**
- [ ] Login works
- [ ] **Users**: Can view/create/update/delete users
- [ ] **User Sessions**: Can view/manage user sessions
- [ ] **Members**: Can view/create/update/delete members
- [ ] **Member Groups**: Can view/create/update/delete member groups
- [ ] **Group Assignments**: Can view/create/update/delete group assignments
- [ ] **Parents**: Can view/create/update/delete parent/guardian info
- [ ] **Finance - Contributions**: Can view/create/update/delete contributions
- [ ] **Finance - Donations**: Can view/create/update/delete donations
- [ ] **Finance - Transactions**: Can view/create/update/delete financial transactions
- [ ] **Finance - Bank Accounts**: Can view/create/update/delete bank accounts
- [ ] **Reports**: Can view/generate/export all reports
- [ ] **Education - Academic Years**: Can view/create/update/delete academic years
- [ ] **Education - Classes**: Can view/create/update/delete classes
- [ ] **Education - Subjects**: Can view/create/update/delete subjects
- [ ] **Education - Enrollments**: Can view/create/update/delete enrollments
- [ ] **Education - Promotions**: Can view/create/update/delete promotions
- [ ] **Education - Students**: Can enroll/remove/promote/bulk promote students
- [ ] **Education - Attendance Sessions**: Can view attendance sessions
- [ ] **Education - Library**: Can upload library resources, manage categories/subcategories
- [ ] **Worship - Songs**: Can view/create/update/delete songs
- [ ] **Worship - Rehearsals**: Can view/create/update/delete rehearsals
- [ ] **Worship - Song Categories**: Can manage song categories/subcategories
- [ ] **Media - Items**: Can view/create/update/delete media items
- [ ] **Media - Categories**: Can manage media categories/subcategories
- [ ] **Content - Blog Posts**: Can view/create/update/delete blog posts
- [ ] **Content - Announcements**: Can view/create/update/delete announcements
- [ ] **Content - FAQs**: Can view/create/update/delete FAQs
- [ ] **Content - Documents**: Can view/create/update/delete documents
- [ ] **Content - Schedule**: Can schedule content publication
- [ ] **Charity - Beneficiaries**: Can view/create/update/delete beneficiaries
- [ ] **Charity - Aid Distributions**: Can view/create/update/delete aid distributions
- [ ] **Charity - General**: Can manage charity settings
- [ ] **Tours**: Can view/create/update/delete tours
- [ ] **Tour Attendances**: Can manage tour attendances
- [ ] **Tour Passengers**: Can manage tour passengers
- [ ] **Events**: Can view/create/update/delete events
- [ ] **Event Registrations**: Can manage event registrations
- [ ] **Fundraising**: Can create/update/delete fundraising
- [ ] **System - Departments**: Can manage departments and assign roles
- [ ] **System - Contact Messages**: Can view/create/update/delete contact messages
- [ ] **System - Custom Options**: Can manage custom options
- [ ] **System - Pages**: Can manage pages
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 3) HR Head (`hr_head`)
**What this role does (non-technical)**
- Manages people information: members, parents, groups, assignments.

**Test account**
- **Email**: `hr_head@finot.org`
- **Phone**: `+251911000003`
- **Password**: `Admin1234`

**What they can do (high level)**
- Full member management and grouping.
- Can view reports.

**Checklist**
- [ ] Login works
- [ ] **Members**: Can view/create/update/delete members
- [ ] **Member Groups**: Can view/create/update/delete member groups
- [ ] **Group Assignments**: Can view/create/update/delete group assignments
- [ ] **Parents**: Can view/create/update/delete parent/guardian info
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 4) Finance Head (`finance_head`)
**What this role does (non-technical)**
- Records and reviews contributions/donations and produces financial reports.

**Test account**
- **Email**: `finance_head@finot.org`
- **Phone**: `+251911000004`
- **Password**: `Admin1234`

**What they can do (high level)**
- Finance module + reporting.
- Limited member viewing/export.

**Checklist**
- [ ] Login works
- [ ] **Finance - Contributions**: Can view/create/update/delete contributions
- [ ] **Finance - Contribution Amounts**: Can manage contribution amounts
- [ ] **Finance - Donations**: Can view/create/update/delete donations
- [ ] **Finance - Transactions**: Can view/create/update/delete financial transactions
- [ ] **Finance - Bank Accounts**: Can view/create/update/delete bank accounts
- [ ] **Reports**: Can view all reports
- [ ] **Charity - Beneficiaries**: Can view beneficiaries
- [ ] **Charity - Reports**: Can view charity reports
- [ ] **Tours - Reports**: Can view tour reports
- [ ] **Fundraising**: Can update fundraising total
- [ ] **Members**: Can view and export members
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 5) Nibret Hisab Head (`nibret_hisab_head`)
**What this role does (non-technical)**
- Handles both money tracking and inventory/property tracking.

**Test account**
- **Email**: `nibret_hisab_head@finot.org`
- **Phone**: `+251911000005`
- **Password**: `Admin1234`

**What they can do (high level)**
- Finance + Inventory modules + reporting.

**Checklist**
- [ ] Login works
- [ ] **Finance - Contributions**: Can view/create/update/delete contributions
- [ ] **Finance - Contribution Amounts**: Can manage contribution amounts
- [ ] **Finance - Donations**: Can view/create/update/delete donations
- [ ] **Finance - Transactions**: Can view/create/update/delete financial transactions
- [ ] **Finance - Bank Accounts**: Can view/create/update/delete bank accounts
- [ ] **Inventory - Items**: Can view/create/update/delete inventory items
- [ ] **Inventory - Movements**: Can view/create/update/delete inventory movements
- [ ] **Inventory - Stock Movements**: Can view/create/update/delete stock movements
- [ ] **Inventory - Loss Records**: Can view/create/update/delete loss records
- [ ] **Reports**: Can view all reports
- [ ] **Charity - Beneficiaries**: Can view beneficiaries
- [ ] **Charity - Reports**: Can view charity reports
- [ ] **Tours - Reports**: Can view tour reports
- [ ] **Fundraising**: Can update fundraising total
- [ ] **Members**: Can view and export members
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 6) Inventory Staff (`inventory_staff`)
**What this role does (non-technical)**
- Records items owned by the organization and tracks stock/movements.

**Test account**
- **Email**: `inventory_staff@finot.org`
- **Phone**: `+251911000006`
- **Password**: `Admin1234`

**What they can do (high level)**
- Inventory management + reports.

**Checklist**
- [ ] Login works
- [ ] **Inventory - Items**: Can view/create/update/delete inventory items
- [ ] **Inventory - Movements**: Can view/create/update/delete inventory movements
- [ ] **Inventory - Stock Movements**: Can view/create/update/delete stock movements
- [ ] **Inventory - Loss Records**: Can view/create/update/delete loss records
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 7) Education Head (`education_head`)
**What this role does (non-technical)**
- Runs the education program: classes, students, teachers, attendance, promotions.

**Test account**
- **Email**: `education_head@finot.org`
- **Phone**: `+251911000007`
- **Password**: `Admin1234`

**What they can do (high level)**
- Full Education module control + education reporting.

**Checklist**
- [ ] Login works
- [ ] **Education - Academic Years**: Can view/create/update/delete academic years
- [ ] **Education - Classes**: Can view/create/update/delete classes
- [ ] **Education - Subjects**: Can view/create/update/delete subjects
- [ ] **Education - Enrollments**: Can view/create/update/delete enrollments
- [ ] **Education - Promotions**: Can view/create/update/delete promotions
- [ ] **Education - Students**: Can view/create/update/delete students
- [ ] **Education - Teachers**: Can view/create/update/delete teachers
- [ ] **Education - Teacher Assignments**: Can view/create/update/delete teacher assignments
- [ ] **Education - Teacher Attendances**: Can view/create/update/delete teacher attendances
- [ ] **Education - School Classes**: Can view/create/update/delete school classes
- [ ] **Education - Student Enrollments**: Can view/create/update/delete student enrollments
- [ ] **Education - Attendance Sessions**: Can view/create/update/delete attendance sessions
- [ ] **Education - Attendance Records**: Can view/create/update/delete attendance records
- [ ] **Education - Library**: Can upload library resources, manage categories/subcategories
- [ ] **Reports - Teacher Reports**: Can view teacher reports
- [ ] **Members**: Can view and export members
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 8) Education Monitor (`education_monitor`)
**What this role does (non-technical)**
- Monitors attendance and education activity without full admin power.

**Test account**
- **Email**: `education_monitor@finot.org`
- **Phone**: `+251911000008`
- **Password**: `Admin1234`

**What they can do (high level)**
- Attendance monitoring and limited viewing of education resources.

**Checklist**
- [ ] Login works
- [ ] **Education - Attendance Sessions**: Can view/create/update/delete attendance sessions
- [ ] **Education - Attendance Records**: Can view/create/update/delete attendance records
- [ ] **Education - Teachers**: Can substitute assign teachers
- [ ] **Education - Academic Years**: Can view academic years (view only)
- [ ] **Education - Classes**: Can view classes (view only)
- [ ] **Education - Subjects**: Can view subjects (view only)
- [ ] **Education - Enrollments**: Can view enrollments (view only)
- [ ] **Education - Teachers**: Can view teachers (view only)
- [ ] **Education - School Classes**: Can view school classes (view only)
- [ ] **Education - Student Enrollments**: Can view student enrollments (view only)
- [ ] **Members**: Can view members (view only)
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 9) Worship Monitor (`worship_monitor`)
**What this role does (non-technical)**
- Helps manage songs and rehearsals.

**Test account**
- **Email**: `worship_monitor@finot.org`
- **Phone**: `+251911000009`
- **Password**: `Admin1234`

**What they can do (high level)**
- Songs + rehearsal management.

**Checklist**
- [ ] Login works
- [ ] **Worship - Songs**: Can view/create/update/delete songs
- [ ] **Worship - Song Categories**: Can view/create/update/delete song categories
- [ ] **Worship - Song Subcategories**: Can view/create/update/delete song subcategories
- [ ] **Worship - Rehearsals**: Can view/create/update/delete rehearsals
- [ ] **Worship - Rehearsal Attendances**: Can view/create/update/delete rehearsal attendances
- [ ] **Media - Items**: Can manage media item visibility only
- [ ] **Education - Attendance Records**: Can record offline attendance only
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 10) Mezmur Head (`mezmur_head`)
**What this role does (non-technical)**
- Leads the worship/songs area, managing songs and rehearsals.

**Test account**
- **Email**: `mezmur_head@finot.org`
- **Phone**: `+251911000010`
- **Password**: `Admin1234`

**What they can do (high level)**
- Full worship control (songs, rehearsals), some documents access.

**Checklist**
- [ ] Login works
- [ ] **Worship - Songs**: Can view/create/update/delete songs
- [ ] **Worship - Song Categories**: Can view/create/update/delete song categories
- [ ] **Worship - Song Subcategories**: Can view/create/update/delete song subcategories
- [ ] **Worship - Rehearsals**: Can view/create/update/delete rehearsals
- [ ] **Worship - Rehearsal Attendances**: Can view/create/update/delete rehearsal attendances
- [ ] **Media - Items**: Can manage media item visibility
- [ ] **Education - Attendance Records**: Can record offline attendance
- [ ] **Reports - Teacher Reports**: Can view teacher reports
- [ ] **Content - Documents**: Can view/create/update/delete documents
- [ ] **System - Departments**: Can view departments
- [ ] **Members**: Can view members
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 11) AV Head (`av_head`)
**What this role does (non-technical)**
- Manages media/content and public communications (blog, announcements, documents).

**Test account**
- **Email**: `av_head@finot.org`
- **Phone**: `+251911000011`
- **Password**: `Admin1234`

**What they can do (high level)**
- Media/content management + publishing.

**Checklist**
- [ ] Login works
- [ ] **Media - Items**: Can view/create/update/delete media items
- [ ] **Media - Categories**: Can view/create/update/delete media categories
- [ ] **Media - Subcategories**: Can view/create/update/delete media subcategories
- [ ] **Content - Blog Posts**: Can view/create/update/delete blog posts
- [ ] **Content - Announcements**: Can view/create/update/delete announcements
- [ ] **Content - FAQs**: Can view/create/update/delete FAQs
- [ ] **Content - Documents**: Can view/create/update/delete documents
- [ ] **Content - Schedule**: Can schedule content publication
- [ ] **Members**: Can view and export members
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 12) Charity Head (`charity_head`)
**What this role does (non-technical)**
- Manages beneficiaries and distribution of aid.

**Test account**
- **Email**: `charity_head@finot.org`
- **Phone**: `+251911000012`
- **Password**: `Admin1234`

**What they can do (high level)**
- Charity/beneficiaries/aid distribution + limited finance views.

**Checklist**
- [ ] Login works
- [ ] **Charity - Beneficiaries**: Can view/create/update/delete beneficiaries
- [ ] **Charity - Aid Distributions**: Can view/create/update/delete aid distributions
- [ ] **Charity - General**: Can manage charity settings
- [ ] **Finance - Contributions**: Can view and create contributions only (no update/delete)
- [ ] **Finance - Donations**: Can view/create/update/delete donations
- [ ] **Tours - Reports**: Can view tour reports only
- [ ] **Members**: Can view and export members (view only)
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 13) Tour Head (`tour_head`)
**What this role does (non-technical)**
- Manages tours and passenger registrations.

**Test account**
- **Email**: `tour_head@finot.org`
- **Phone**: `+251911000013`
- **Password**: `Admin1234`

**What they can do (high level)**
- Tour management + registrations + reporting.

**Checklist**
- [ ] Login works
- [ ] **Tours**: Can view/create/update/delete tours
- [ ] **Tour Attendances**: Can view/create/update/delete tour attendances
- [ ] **Tour Passengers**: Can view/create/update/delete tour passengers
- [ ] **Members**: Can view and export members
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 14) Internal Relations Head (`internal_relations_head`)
**What this role does (non-technical)**
- Handles internal communication and document workflows, plus member relations.

**Test account**
- **Email**: `internal_relations_head@finot.org`
- **Phone**: `+251911000014`
- **Password**: `Admin1234`

**What they can do (high level)**
- Member management + documents + contact messages viewing.

**Checklist**
- [ ] Login works
- [ ] **Members**: Can view/create/update/delete members
- [ ] **Member Groups**: Can view/create/update/delete member groups
- [ ] **Group Assignments**: Can view/create/update/delete group assignments
- [ ] **Parents**: Can view/create/update/delete parent/guardian info
- [ ] **Media - Items**: Can delete media items only (delete only)
- [ ] **Content - Documents**: Can view/create/update/delete documents
- [ ] **System - Contact Messages**: Can view contact messages only (view only)
- [ ] **System - Departments**: Can view/update departments (no delete)
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 15) Department Secretary (`department_secretary`)
**What this role does (non-technical)**
- Supports one department by managing its data (limited—no deletes).

**Test account**
- **Email**: `department_secretary@finot.org`
- **Phone**: `+251911000015`
- **Password**: `Admin1234`

**What they can do (high level)**
- Department-level create/update (no delete) for selected resources.

**Checklist**
- [ ] Login works
- [ ] **Department Resources**: Can view/create/update department resources
- [ ] **Content - Documents**: Can upload and search documents
- [ ] **Members**: Can view/create/update members (no delete)
- [ ] **Members**: Can export members
- [ ] **Events**: Can view/create/update events (no delete)
- [ ] **Finance - Contributions**: Can view/create/update contributions (no delete)
- [ ] **Inventory - Items**: Can view/create/update inventory items (no delete)
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation

---

## 16) Staff (`staff`)
**What this role does (non-technical)**
- View-only staff role for seeing department resources.

**Test account**
- **Email**: `staff@finot.org`
- **Phone**: `+251911000016`
- **Password**: `Admin1234`

**What they can do (high level)**
- Read-only access to key screens.

**Checklist**
- [ ] Login works
- [ ] **Department Resources**: Can view department resources
- [ ] **Members**: Can view members
- [ ] **Events**: Can view events
- [ ] **Finance - Contributions**: Can view contributions
- [ ] **Inventory - Items**: Can view inventory items
- [ ] **Charity - Beneficiaries**: Can view beneficiaries
- [ ] **Content - Documents**: Can view documents
- [ ] **Reports**: Can view all reports
- [ ] **Core - Dashboard**: Can view dashboard
- [ ] **Core - Profile**: Can update profile
- [ ] **Core - Sessions**: Can manage sessions
- [ ] **Core - Ethiopian Dates**: Can manage Ethiopian dates
- [ ] **Core - Notifications**: Can manage notifications
- [ ] **Core - Help**: Can access documentation
- [ ] **Restrictions Check**: Cannot create/update/delete restricted data (spot-check one screen)

---

## Results summary
- **Overall pass**: [ ] Yes  [ ] No
- **Notes / bugs found**:
  - ________________________________________________
  - ________________________________________________
  - ________________________________________________
