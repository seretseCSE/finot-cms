# Finote CMS — Role & Use-Case Workflows

Reference guide for every role and the step-by-step workflows they perform.  
Mirrors the in-app **User Manual** (`/admin/user-manual`) and is suitable for training, onboarding, and SOP documentation.

---

## Table of contents

1. [How Finote works](#how-finote-works)
2. [Cross-office use cases](#cross-office-use-cases)
3. [Who to ask](#who-to-ask)
4. [Role workflows](#role-workflows)
   - [Super Admin](#super-admin)
   - [Admin](#admin)
   - [HR Head](#hr-head)
   - [Internal Relations Head](#internal-relations-head)
   - [Finance Head](#finance-head)
   - [Nibret Hisab Head](#nibret-hisab-head)
   - [Inventory Staff](#inventory-staff)
   - [Education Head](#education-head)
   - [Education Monitor](#education-monitor)
   - [Data Encoder](#data-encoder)
   - [Student](#student)
   - [Parent](#parent)
   - [Mezmur Head](#mezmur-head)
   - [Worship Monitor](#worship-monitor)
   - [AV Head](#av-head)
   - [Charity Head](#charity-head)
   - [Tour Head](#tour-head)
   - [Revenue and Charity Head](#revenue-and-charity-head)

---

## How Finote works

- Each user may hold **one or more roles**. The **active role** (top bar switcher) controls the dashboard and sidebar menus.
- **Permissions** are checked per action (`members.view`, `results.record`, etc.). Super Admin has full access.
- **Department scoping** applies to many lists: Super Admin and Admin see all departments; most staff see their department. HR is church-wide for membership.
- **Hub pages** group related screens (e.g. Student movement → Enrollments, Promotion board, Withdrawals). Open them from sidebar hub links.
- Staff see only their role guide in the User Manual. Super Admin / Admin see all guides for training.

---

## Cross-office use cases

These workflows involve more than one role. Use this section when explaining hand-offs.

### School marks (semester → roster)

| Step | Role | Action |
|------|------|--------|
| 1 | Education Head | Create batch, semester, subject offerings, and assessments; **Activate** the semester |
| 2 | Data Encoder (or Education Head) | Enter scores on the active semester — **live on save**, no approval queue |
| 3 | Education Head | **Compute results** when an official roster is needed; open Roster / Marklist reports; export Excel, CSV, or PDF |

### Student withdrawal

| Step | Role | Action |
|------|------|--------|
| 1 | Student | Apply from **My Learning → Request Withdrawal** |
| 2 | Education Head or Admin | Approve or reject |
| 3 | HR Head | **Finalize** — only then does enrollment become **Withdrawn** |

### New member → student

| Step | Role | Action |
|------|------|--------|
| 1 | HR Head (or Education Head / Admin) | Create member record and assign department |
| 2 | Admin or Super Admin | Create user login if portal access is needed |
| 3 | Education Head | Enroll into batch + batch year + class |
| 4 | Other offices | Finance, Mezmur, Charity, etc. use the same member record |

### End-of-year promotion

| Step | Role | Action |
|------|------|--------|
| 1 | Education Head | Close semester; compute results |
| 2 | Education Head | Open **Promotion board** → load academic year, batch, class |
| 3 | Education Head | Accept suggestions or set **Pass** / **Fail (leave batch)** per student |
| 4 | Education Head | Set next class for passers; set destination batch + class for failers; **Apply promotions** |

**Pass** = same batch, next program year class.  
**Fail (leave batch)** = join another batch at the **same program year**; passed subjects remain as credits.

### Facility booking

| Step | Role | Action |
|------|------|--------|
| 1 | Any role with `facilities.book` | Create booking request |
| 2 | Super Admin or Admin | Approve or reject booking |

### Bulk messaging

| Step | Role | Action |
|------|------|--------|
| 1 | Role with `messages.broadcast` | Compose message; filter by department/group |
| 2 | Global broadcast roles | Super Admin, Admin, HR Head, Internal Relations Head, Education Head — can reach church-wide when permitted |

---

## Who to ask

| Need | Ask |
|------|-----|
| Member record | HR Head |
| Batch, enrollment, scores setup | Education Head |
| Daily attendance | Education Monitor |
| Type scores | Data Encoder |
| Approve withdrawal | Education Head or Admin |
| Finalize withdrawal | HR Head |
| Payments / contributions | Finance Head |
| Store / inventory | Inventory Staff or Nibret Hisab Head |
| Aid to a person | Charity Head |
| Tours | Tour Head |
| Website / media publish | AV Head |
| Songs / rehearsals | Mezmur Head |
| Unlock account / assign roles | Admin or Super Admin |
| System settings / backup | Super Admin |

---

## Role workflows

---

### Super Admin

**Who:** Platform owner (IT / leadership). Can open every screen. Not for daily data entry.

**Can do:** Users & roles, lock/unlock accounts, sessions, audit logs, emergency override, global church settings, backups, system health, error logs, all department data.

**Cannot / should not:** Daily contributions, class attendance, shared password use, casual backup restores.

#### Use cases

**UC-SA-01 — Give someone a new login**

1. Go to **Users & Access → User Management → New User**
2. Enter name, email, phone (9 digits after +251)
3. Assign **department** (controls member visibility)
4. Choose one or more **roles**
5. Save — user receives temporary password and must change it on first login

**UC-SA-02 — Keep the system safe**

1. Open **System Health** if the site is slow
2. Open **Error Log Viewer** if a screen fails
3. Create backup from **Backup & Restore** weekly and before major changes
4. Review **Audit Logs** monthly

**UC-SA-03 — Unblock a department**

1. Confirm user has correct role and department
2. If member is invisible, check member’s department assignment
3. Step in for Education enrollments/scores if needed — withdrawal still ends with HR finalize

**UC-SA-04 — End a suspicious session**

1. Profile menu → **Manage Active Sessions**
2. End the session for the affected user

**UC-SA-05 — Restore from backup (last resort)**

1. **Backup & Restore** → choose backup → restore
2. Warn: replaces current data and logs everyone out

---

### Admin

**Who:** Daily church office manager across departments. No system back room (settings, backups, error logs). No inventory.

**Can do:** Users, members, groups, parents, finance reports, education (batches through promotion), content, charity, tours, events, messaging, facilities, visitor analytics.

**Cannot:** Global settings, backups, error logs, inventory, **finalize** withdrawal.

#### Use cases

**UC-AD-01 — Typical week**

1. Review dashboard (members, income, attendance)
2. Help stuck departments (wrong role, missing member, locked user)
3. Cover Education if Head is away (enrollments, scores, semester activation)
4. Approve/reject pending withdrawals; notify HR to finalize if approved
5. Review contact messages and recent transactions

**UC-AD-02 — Add a staff user**

1. **Users → New User**
2. Name, email, phone, department, role(s)
3. Save — invitation with temporary password

**UC-AD-03 — Approve student withdrawal**

1. Open withdrawal request (Education / Student movement hub)
2. Review reason and enrollment
3. Approve or reject
4. If approved, notify HR Head to finalize

**UC-AD-04 — Record assessment scores (cover)**

1. Confirm active semester and assessments exist
2. **Results → Record assessments**
3. Select semester, offering, assessment → enter scores → **Save scores**

**UC-AD-05 — Send department message**

1. **Content Management → Bulk Messages**
2. Compose message; filter by department or group
3. Send

---

### HR Head

**Who:** Church membership office. Owns the register.

**Can do:** Full member CRUD, groups, parents, documents, withdrawal **finalize**, teacher attendance report, messages, facility booking. Church-wide department scope.

**Cannot:** Money, inventory, education setup, marks, approve withdrawal (only finalize), tours, charity, system settings.

#### Use cases

**UC-HR-01 — Register a new member**

1. **Membership Management → Members → New Member**
2. Full name, DOB, gender, marital status
3. Phone: 9 digits after +251 (e.g. `911234567`)
4. Complete address, emergency contact, spiritual tabs
5. Choose **department** → Save
6. Assign groups and parent records if needed

**UC-HR-02 — Import members from Excel**

1. **Members** list → **Import** action
2. Download template if needed; fill rows
3. Upload file → review validation → confirm import

**UC-HR-03 — Put people in groups**

1. Create group under **Member Groups** if missing (leader, schedule, department)
2. **Group Assignments** → add/remove members and roles
3. Use bulk assign for many people at once

**UC-HR-04 — Finish a student withdrawal**

1. Wait for Education Head or Admin approval
2. Open withdrawal record
3. Confirm reason and effective date
4. **Finalize** — enrollment becomes Withdrawn
5. Do not finalize while status is still Pending

**UC-HR-05 — Link parent to student**

1. **Parents → New Parent** (or edit existing)
2. Link to member/student records
3. Parent can then log in and see linked children

---

### Internal Relations Head

**Who:** Fellowship connections — groups, parents, documents, contact messages. Views members; does not create them from scratch.

**Can do:** View members, groups, group assignments, parents, documents, contact messages, departments, attendance summary reports, delete media, messages, facilities.

**Cannot:** Create new members, full HR member export, money, inventory, marks, finalize withdrawal.

#### Use cases

**UC-IR-01 — Place someone in a group**

1. Confirm HR created the member
2. **Member Groups** → create group if needed
3. **Group Assignments** → add member and role (leader, member, etc.)

**UC-IR-02 — Answer a contact message**

1. **Operations → Contact Messages**
2. Read message; route to HR if new member needed
3. File related documents under **Documents**

**UC-IR-03 — Upload fellowship document**

1. **Documents → New**
2. Upload file, set title, access level, department
3. Save

---

### Finance Head

**Who:** Church books — offerings, donations, bank, reports.

**Can do:** Contributions, donations, transactions, bank accounts, financial reports, member view/export (finance timeline), charity/tour reports (read), documents, messages, facilities.

**Cannot:** Edit member personal details, inventory, education, tours/charity operations, system settings.

#### Use cases

**UC-FH-01 — Record a contribution**

1. Confirm payer is a member (else → HR)
2. **Contributions → Contribution follow-up → Contribution Form**
3. Filter by academic year and group
4. Check month for each member who paid → Save
5. Amounts come from **Contribution Setup → Contribution Settings**

**UC-FH-02 — Set expected contribution amounts**

1. **Contributions → Contribution Setup → Contribution Settings**
2. Define amounts per group/period
3. Powers Contribution Form and Outstanding Contributions report

**UC-FH-03 — Record a donation**

1. **Donations → New Donation**
2. Donor, type, amount, date, method → Save
3. Review under **Donation Report**

**UC-FH-04 — Monthly close**

1. Update **Bank Accounts**
2. Record deposits/withdrawals/transfers under **Financial Transactions**
3. Open **Financial Overview**, **Statement**, **Audit Trail**
4. Run **Contribution Form** and **Outstanding Contributions** for the period

**UC-FH-05 — Review outstanding givers**

1. **Contributions → Contribution follow-up → Outstanding Contributions**
2. Filter by year/group → follow up with members

---

### Nibret Hisab Head

**Who:** Finance Head **plus** inventory / property register.

**Can do:** Everything Finance Head can do, plus full inventory (items, movements, losses, analytics).

**Cannot:** Register beneficiaries, record aid, education, HR, system settings.

#### Use cases

**UC-NH-01 — Typical day (money + store)**

1. Record offerings and donations (same as Finance Head)
2. When items arrive or leave, update **Inventory** hub
3. Create **Loss Record** for damage or missing stock
4. Check dashboard for low stock

**UC-NH-02 — Add a store item**

1. **Inventory Management → Inventory → Inventory Items → New**
2. Name, category, quantity, unit, unit price, location, minimum stock
3. Save — movements adjust quantity later

**UC-NH-03 — Record stock in**

1. **Stock Movements → New** → type **In**
2. Item, quantity, date, reason (purchase, donation)

**UC-NH-04 — Record stock out**

1. **Stock Movements → New** → type **Out**
2. Item, quantity, recipient, reason

**UC-NH-05 — Record a loss**

1. **Loss/Damage Records → New**
2. Item, quantity, reason, date → stock count reduced

---

### Inventory Staff

**Who:** Storekeepers. Property counts only — no cash or members.

**Can do:** Inventory items, movements, losses, analytics, documents.

**Cannot:** Finance, members, education, tours, charity, broadcasts (unless another role added).

#### Use cases

**UC-IS-01 — Something arrives**

1. New item? **Inventory Items → New** (name, category, unit, location, min stock)
2. **Stock Movement → In** with quantity, date, reason
3. Verify on-shelf count matches system

**UC-IS-02 — Something is issued**

1. **Stock Movement → Out** — who received it and why
2. Watch low-stock alerts on dashboard

**UC-IS-03 — Something lost or damaged**

1. **Loss Record** with quantity, reason, date
2. Notify Nibret Hisab Head if significant

---

### Education Head

**Who:** School operations — batches, semesters, offerings, enrollments, promotions, reports.

**Can do:** Full academic calendar, subject offerings, assessments, enrollments, promotion board, compute results, marklist/roster reports (with Excel/CSV/PDF export), attendance pages, class work, library upload, withdrawal approve, grading scale.

**Cannot:** Finalize withdrawal (HR), contributions, inventory, finance, tours, system settings.

#### Use cases

**UC-EH-01 — Start a new school year**

1. **Education Management → Academic calendar → Batches → New** (e.g. Class of 2026)
2. Finote creates program years (Year 1…N)
3. Add **Semester** on current batch year → **Activate**
4. **Classes & subjects** hub → subject offerings (subject + teacher + class) and assessments
5. **Student movement → Enrollments** → enroll students into batch, year, class

**UC-EH-02 — Run an active semester**

1. Ensure semester is **Active**
2. Data Encoder enters scores (or you do)
3. Publish class announcements / homework / materials as needed
4. Education Monitor takes daily attendance

**UC-EH-03 — Produce official roster**

1. **Results → Compute results** (or from marklist workflow)
2. **Roster Report** → filter year, batch, class, semester
3. Download **Excel**, **CSV**, or **PDF**

**UC-EH-04 — Produce marklist for active term**

1. **Marklist Report** → filter semester, class, subject
2. Download **Excel**, **CSV**, or **PDF**

**UC-EH-05 — Close semester and open next**

1. **Academic calendar → Semesters** → **Close** current semester
2. Create and **Activate** next semester
3. Repeat offerings/assessments as needed for new term

**UC-EH-06 — Promote a whole class (Promotion board)**

1. **Results → Promotion board** (or **Student movement** hub)
2. Select **Academic year**, **Batch**, **Class** → **Load class**
3. Review averages and suggestions (pass mark default: **50%**)
4. **Accept suggestions** or set **Pass** / **Fail (leave batch)** per row
5. Set **Next class** for passers
6. If any fail: set **destination batch** + **class** (same program year)
7. **Apply promotions**

**UC-EH-07 — Pass one student (enrollment row)**

1. **Student movement → Enrollments**
2. Row action **Pass (next class)** → confirm next class

**UC-EH-08 — Fail student (leave batch)**

1. **Enrollments** → **Fail (leave batch)**
2. Choose destination batch and class at same program year
3. Passed credits transfer; new batch subjects may differ

**UC-EH-09 — Approve withdrawal**

1. **Student movement → Withdrawals**
2. Review student request → Approve or reject
3. If approved, notify HR to finalize

**UC-EH-10 — Assign teacher to subject**

1. **Teachers** hub → **Assignments → New**
2. Teacher, class, subject, academic year/semester

**UC-EH-11 — Configure grading scale**

1. **Results → Grading Scale**
2. Set grade boundaries used in reports

---

### Education Monitor

**Who:** Daily attendance and class-work publishing. Read-only on enrollments.

**Can do:** Attendance sessions, student/teacher attendance pages, lock/unlock sessions, class announcements/homework/materials (publish), course management, attendance reports.

**Cannot:** Batches, enroll, promote, enter marks, roster reports, members, finance.

#### Use cases

**UC-EM-01 — Take student attendance**

1. **Attendance → Mark Student Attendance** (or Create Attendance Session)
2. Choose class, date, session type
3. Mark Present / Absent / Late / Excused
4. Save → **Lock** session when complete

**UC-EM-02 — Record teacher attendance**

1. **Attendance → Mark Teacher Attendance**
2. Mark each teacher; assign substitute if absent
3. Save and lock

**UC-EM-03 — Publish homework**

1. **Class Work → Homework Assignments → New**
2. Class, title, due date, attachment → Publish

**UC-EM-04 — Publish class announcement**

1. **Class Work → Class Announcements → New**
2. Target class, message, optional expiry → Publish

**UC-EM-05 — Attendance summary report**

1. **Attendance → Attendance Summary Report**
2. Filter date range and class

---

### Data Encoder

**Who:** Score entry on the active semester only.

**Can do:** Record assessments / marklist, profile, user manual.

**Cannot:** Batches, enrollments, semester activation, offerings, reports, other offices.

#### Use cases

**UC-DE-01 — Enter assessment scores**

1. Confirm Education Head activated semester and created assessments
2. **Results → Record assessments**
3. Select active semester, subject offering, assessment (e.g. Midterm)
4. Load roster → enter each score; mark absent if needed
5. **Save scores** — students see updates immediately

**UC-DE-02 — Update scores after save**

1. Re-open same assessment in **Record assessments**
2. Change scores → **Save scores** again

**UC-DE-03 — Semester closed (blocked)**

1. Cannot save — ask Education Head to activate correct semester or reopen if policy allows

---

### Student

**Who:** Portal user — own learning only.

**Can do:** Class announcements, homework, materials, my results, my attendance, request withdrawal, profile.

**Cannot:** Other students’ data, staff menus, library CMS, change enrollment.

#### Use cases

**UC-ST-01 — Check results**

1. **My Learning → My Results**
2. Filter academic year, semester, batch, subject
3. Expand subject to see each assessment score

**UC-ST-02 — Check attendance**

1. **My Learning → My Attendance**
2. Review session history

**UC-ST-03 — Download homework**

1. **My Learning → Homework**
2. Open assignment → download attachment

**UC-ST-04 — Request withdrawal**

1. **My Learning → Request Withdrawal**
2. Enter reason → Submit
3. Wait for Education approval, then HR finalize

**UC-ST-05 — Read class announcement**

1. **My Learning → Class Announcements**
2. Notifications also sent to phone/app if configured

---

### Parent

**Who:** Guardian portal for linked children.

**Can do:** My Children, linked announcements, homework, materials, results and attendance summaries per child.

**Cannot:** Change enrollments, grades, attendance, staff menus.

#### Use cases

**UC-PA-01 — View child overview**

1. **My Children → My Children**
2. Select child card for results and attendance summary

**UC-PA-02 — Help child with homework**

1. **My Learning → Homework** (linked child context)
2. Download files

**UC-PA-03 — Monitor announcements**

1. **My Learning → Class Announcements**
2. Same notifications as student app

---

### Mezmur Head

**Who:** Song book and rehearsals owner.

**Can do:** Songs, categories, rehearsals, rehearsal attendance, worship media visibility, members view, documents, messages, facilities, reports.

**Cannot:** Edit member records, money, inventory, education, public site publish (AV Head).

#### Use cases

**UC-MZ-01 — Add a song**

1. **Content Management → Songs → New Song**
2. Title, lyrics, author, category, language, key → Save

**UC-MZ-02 — Run a rehearsal**

1. **Rehearsals → New Rehearsal** — date, time, place, choir
2. Attach songs from library
3. Book facility if needed
4. After rehearsal: mark **Rehearsal Attendance** (offline sync supported)
5. Set related media visibility

**UC-MZ-03 — Message the choir**

1. **Bulk Messages** → filter Mezmur department/group → send

---

### Worship Monitor

**Who:** Assists Mezmur Head — songs and rehearsals without full member access.

**Can do:** Songs, rehearsals, rehearsal attendance, worship media visibility, offline attendance, documents, reports.

**Cannot:** Member list, broadcasts, facility booking, money, education.

#### Use cases

**UC-WM-01 — Prepare rehearsal**

1. Add/update songs Mezmur Head selected
2. Open or create rehearsal; attach songs
3. Ask Mezmur Head to book hall if needed

**UC-WM-02 — Take rehearsal attendance (offline)**

1. Mark attendees during rehearsal without network
2. Sync when connection returns

**UC-WM-03 — Adjust media visibility**

1. **Media** hub → set visibility per Mezmur Head instruction

---

### AV Head

**Who:** Communications — media, blog, announcements, FAQs, public content.

**Can do:** Media library, blog, announcements, FAQs, documents, scheduled publish, member export, website traffic analytics, messages, facilities.

**Cannot:** Edit member records, money, inventory, marks, song book ownership.

#### Use cases

**UC-AV-01 — Publish photo or video**

1. **Content Management → Media → New Media Item**
2. Upload, title, description, category, visibility → Save

**UC-AV-02 — Post announcement**

1. **Site notices → Announcements → New**
2. Set expiry and audience (department or church-wide)

**UC-AV-03 — Schedule blog post**

1. **Blog Posts → New**
2. Content, featured image, **schedule** future publish date

**UC-AV-04 — Update public FAQs**

1. **Site notices → FAQs**
2. Edit questions; set display order

**UC-AV-05 — Review website traffic**

1. **Website Traffic** page
2. Filter date range for visitor stats

---

### Charity Head

**Who:** Beneficiaries and aid distributions.

**Can do:** Beneficiaries, aid distributions, charity/beneficiary reports, view/create contributions, record donations, member view, documents, messages, facilities.

**Cannot:** Tours, inventory, bank accounts, education, member personal edits.

#### Use cases

**UC-CH-01 — Register a beneficiary**

1. **Charity Management → Beneficiaries → New**
2. Name, demographics, type, need category, status, notes → Save

**UC-CH-02 — Record aid distribution**

1. **Aid Distributions → New**
2. Beneficiary, aid type (money/food/clothing/medical), amount or items, date → Save
3. After verification, **lock** distribution (Charity Head can unlock)

**UC-CH-03 — Monthly charity report**

1. **Charity reports → Charity Report** and **Beneficiary Report**
2. Filter period → present to leadership

**UC-CH-04 — Record charity donation**

1. **Donations → New** — earmark for charity
2. Coordinate with Finance Head for bank book if large

---

### Tour Head

**Who:** Church trips — plan, passengers, attendance, reports.

**Can do:** Tours, passengers, registrations, tour attendance, call button, tour report, documents, messages, facilities.

**Cannot:** Charity, bank books, member creation, system settings.

#### Use cases

**UC-TH-01 — Plan a tour**

1. **Tour Management → Tours → New**
2. Name, destination, dates, departure time, cost, max seats → Save
3. Book briefing room via **Facilities** if needed

**UC-TH-02 — Register passengers**

1. Open tour → **Passengers → Add Passenger**
2. Select member (HR creates if missing)
3. Record payment status → **Confirm** registration
4. Use **call button** for unconfirmed passengers

**UC-TH-03 — Trip day attendance**

1. Create **Tour Attendance** for the date
2. Check off who actually travelled

**UC-TH-04 — Post-trip report**

1. **Tour Report**
2. Review counts, attendance, revenue → share with Finance if fees collected

---

### Revenue and Charity Head

**Who:** Combined tours **and** charity department.

**Can do:** Everything Tour Head and Charity Head can do.

**Cannot:** Full finance books, inventory, education, HR, system settings.

#### Use cases

**UC-RC-01 — Fundraising trip that funds aid**

1. Create tour and register passengers (Tour Head workflow)
2. Record donations/contributions from the trip
3. Take tour attendance; run tour report
4. Register/update beneficiaries; record aid distributions
5. Notify Finance Head for bank statements; Inventory Staff if goods left store

**UC-RC-02 — Ordinary aid week (no tour)**

1. Beneficiary → distribution → lock when verified
2. Charity Report and Beneficiary Report for department meeting

---

## Navigation quick reference

| Sidebar group | Primary roles |
|---------------|---------------|
| Membership Management | superadmin, admin, hr_head, internal_relations_head |
| Education Management | superadmin, admin, education_head |
| Attendance | superadmin, admin, education_head, education_monitor |
| Results | superadmin, admin, education_head, education_monitor, data_encoder |
| Class Work | superadmin, admin, education_head, education_monitor |
| My Learning | student, parent |
| My Children | parent |
| Donations / Contributions / Finance | finance_head, nibret_hisab_head (+ admin) |
| Charity Management | charity_head, revenue_and_charity_head |
| Inventory Management | inventory_staff, nibret_hisab_head |
| Tour Management | tour_head, revenue_and_charity_head |
| Content Management | av_head, worship_monitor, mezmur_head |
| Users & Access / Operations / Settings | superadmin, admin |

---

## Document info

| Field | Value |
|-------|-------|
| Source | Finote CMS user manual partials + Filament nav/permission config |
| In-app manual | Admin panel → footer link **User Manual** |
| Last aligned | September 2026 |

For permission changes, see `database/seeders/PermissionSeeder.php` and `app/Enums/Roles.php`.
