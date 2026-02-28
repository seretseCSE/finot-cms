# **User Story** 

\# COMPLETE USER STORIES


Laravel 12 and filament 5
is the versions that I will use
Driver.js for product tour 
light glassy 

# FINOT CHURCH MANAGEMENT SYSTEM - IMPLEMENTATION STATUS

System Users (16 total):
superadmin@finot.org - Super Administrator
admin@finot.org - Administrator
hr_head@finot.org - HR Department Head
finance_head@finot.org - Finance Department Head
nibret_hisab_head@finot.org - Nibret Hisab Department Head
inventory_staff@finot.org - Inventory Staff
education_head@finot.org - Education Department Head
education_monitor@finot.org - Education Monitor
worship_monitor@finot.org - Worship Monitor
mezmur_head@finot.org - Mezmur Department Head
av_head@finot.org - AV Department Head
charity_head@finot.org - Charity Department Head
tour_head@finot.org - Tour Department Head
internal_relations_head@finot.org - Internal Relations Head
department_secretary@finot.org - Department Secretary
staff@finot.org - General Staff

## ✅ COMPLETED (Working Features)

### 1. PUBLIC WEBSITE & CONTENT
- 1.1 View Home/Landing Page - [`HomeController.php`](app/Http/Controllers/HomeController.php), [`home.blade.php`](resources/views/public/home.blade.php)
- 1.2 Read About Us - [`AboutController.php`](app/Http/Controllers/AboutController.php), [`about.blade.php`](resources/views/public/about.blade.php)
- 1.3 Explore Programs & Services - [`ProgramsController.php`](app/Http/Controllers/ProgramsController.php), [`programs.blade.php`](resources/views/public/programs.blade.php)
- 1.4 Read Blog Posts & Announcements - [`BlogController.php`](app/Http/Controllers/BlogController.php)
- 1.5 Access Song Library - [`SongLibraryController.php`](app/Http/Controllers/SongLibraryController.php), [`song-library.blade.php`](resources/views/public/song-library.blade.php)
- 1.6 View Media Gallery - [`MediaGalleryController.php`](app/Http/Controllers/MediaGalleryController.php), [`media-gallery.blade.php`](resources/views/public/media-gallery.blade.php)
- 1.7 View Event Calendar - [`EventCalendarController.php`](app/Http/Controllers/EventCalendarController.php)
- 1.10 Submit Contact/Inquiry Form - [`ContactController.php`](app/Http/Controllers/ContactController.php)
- 1.11 Switch Website Language - [`LanguageController.php`](app/Http/Controllers/LanguageController.php), [`language-switcher.blade.php`](app/Filament/Widgets/LanguageSwitcher.php.bak)

### 2. PLATFORM & ACCESS
- 2.3 Login to Portal - [`LoginController.php`](app/Http/Controllers/Auth/LoginController.php)
- 2.4 Logout from Portal - [`LogoutController.php`](app/Http/Controllers/Auth/LogoutController.php)
- 2.7-2.9 Ethiopian Date Support - [`EthiopianDateHelper.php`](app/Helpers/EthiopianDateHelper.php), [`EthiopianDatePicker.php`](app/Filament/Forms/Components/EthiopianDatePicker.php)

### 3. MEMBERSHIP, PARENTS & GROUPS
- 3.1-3.4 Member Profile Management - [`MemberResource.php`](app/Filament/Resources/MemberResource.php), [`Member.php`](app/Models/Member.php)
- 3.5-3.10 Group Management - [`MemberGroupResource.php`](app/Filament/Resources/MemberGroupResource.php), [`MemberGroup.php`](app/Models/MemberGroup.php)
- 3.11 Member Timeline - [`ViewMemberTimeline.php`](app/Filament/Resources/MemberResource/Pages/ViewMemberTimeline.php)
- 3.12-3.13 Search & Export - [`MemberExporter.php`](app/Filament/Exports/MemberExporter.php)

### 4. EDUCATION & SUNDAY SCHOOL
- 4.1-4.4 Academic Year Management - [`AcademicYearResource.php`](app/Filament/Resources/AcademicYearResource.php)
- 4.5-4.10 Class & Subject Management - [`ClassModelResource.php`](app/Filament/Resources/ClassModelResource.php), [`SubjectResource.php`](app/Filament/Resources/SubjectResource.php)
- 4.11-4.14 Student Enrollment - [`StudentEnrollmentResource.php`](app/Filament/Resources/StudentEnrollmentResource.php)
- 4.15-4.19 Attendance Tracking - [`AttendanceSessionResource.php`](app/filament/Resources/AttendanceSessionResource.php)

### 10. SECURITY & GOVERNANCE
- 10.9 View Audit Logs - [`AuditLogResource.php`](app/Filament/Resources/AuditLogs/AuditLogResource.php)

 

### **PRIORITY 1: Public Website (4 features)**
- 1.8 Access Library Resources - Controller, Views, Routes
- 1.9 View Fundraising Progress - Controller, Views, Routes
- 1.12 View Available Tours (Public) - Controller, Views, Routes
- 1.13 Register for Tour (Public) - Controller, Views, Routes

### **PRIORITY 2: Platform & Access (4 features)**
- 2.1 Install App (PWA) - PWA configuration, service worker, manifest
- 2.2 View Admin Dashboard - Enhanced dashboard with role-based summaries
- 2.5 Update My Profile - User profile edit functionality
- 2.6 Manage Active Sessions - Session management UI

### **PRIORITY 3: Financial System (8 features)**
- 5.1 Define Contribution Amount - Filament Resource
- 5.2 Record Individual Contribution - Filament Resource
- 5.3 Record Donation - Filament Resource
- 5.4-5.8 Financial Reports & Exports - Reports, Analytics

### **PRIORITY 4: Tours Admin (10 features)**
- 6.1-6.6 Tour Management - CRUD operations
- 6.7-6.8 Attendance Tracking - Session & passenger attendance
- 6.9 Use Call Button - Call functionality
- 6.10 Tour Reports - Analytics

### **PRIORITY 5: Worship, Media & Blog (15 features)**
- 7.1-7.4 Song Library Management - CRUD operations
- 7.5-7.6 Rehearsal Scheduling & Attendance
- 7.7-7.10 Media Gallery Management - CRUD operations
- 7.11-7.12 Blog Posts Management - CRUD operations
- 7.13-7.14 Announcements Management - CRUD operations
- 7.15 FAQ Content Management

### **PRIORITY 6: Inventory (5 features)**
- 8.1-8.5 Inventory Tracking & Analytics

### **PRIORITY 7: Documents & Archives (7 features)**
- 9.1-9.4 Department Documents - CRUD, Visibility controls
- 9.5-9.6 Library Resources - CRUD operations
- 9.7 View Contact Messages

### **PRIORITY 8: Events & Fundraising (6 features)**
- 11.1-11.3 Event Management - CRUD operations
- 11.4-11.6 Fundraising Campaign Management - CRUD operations

### **PRIORITY 9: Security & Governance (10 features)**
- 10.1-10.5 User Management - CRUD, Roles, Lock/Unlock
- 10.6-10.8 Superadmin Features - Oversight, Health, Error Logs
- 10.10 Export Audit Logs

### **PRIORITY 10: Teachers (9 features)**
- 16.1-16.9 Teacher Management - Profiles, Assignments, Schedule, Attendance

### **PRIORITY 11: Charity (3 features)**
- 20.1-20.3 Beneficiary & Aid Distribution Management


php artisan icons:cache

## **1\. PUBLIC WEBSITE & CONTENT**

**1.1 View Home/Landing Page**  
 Displays mission, announcements, Ethiopian calendar events, and calls to action.  
 *Actors: Public Visitor*

**1.2 Read About Us**  
 Displays church history, mission, vision, and leadership overview.  
 *Actors: Public Visitor*

**1.3 Explore Programs & Services**  
 Lists church departments, ministries, and services.  
 *Actors: Public Visitor*

**1.4 Read Blog Posts & Announcements**  
 Displays public blog posts and announcements.  
 *Actors: Public Visitor*

**1.5 Access Song Library**  
 View lyrics and play embedded worship audio/video organized by categories.  
 *Actors: Public Visitor, Worship Monitor*

**1.6 View Media Gallery**  
 Browse public photos/videos from events organized by categories.  
 *Actors: Public Visitor*

**1.7 View Event Calendar**  
 View upcoming services, classes, rehearsals, tours (Ethiopian dates).  
 *Actors: Public Visitor*

**1.8 Access Library Resources**  
 Download educational PDFs from Education department.  
 *Actors: Public Visitor*

**1.9 View Fundraising Progress**  
 View fundraising totals and progress (display-only, no payments).  
 *Actors: Public Visitor*

**1.10 Submit Contact/Inquiry Form**  
 Submit inquiries through secure public form.  
 *Actors: Public Visitor*

**1.11 Switch Website Language**  
 Switch between Amharic/English with fallback content.  
 *Actors: Public Visitor*

**1.12 View Available Tours**  
 View active/upcoming tours with Ethiopian dates on public website.  
 *Actors: Public Visitor*

**1.13 Register for Tour**  
 Register via form (name, phone, passenger count, receipt upload).  
 *Actors: Public Visitor*

---

## **2\. PLATFORM & ACCESS**

**2.1 Install App (PWA)**  
 Prompt to install portal as mobile app after 3 visits.  
 *Actors: All Users*

**2.2 View Admin Dashboard**  
 View role-based summaries, analytics, pending tasks.  
 *Actors: All Staff Users*

**2.3 Login to Portal**  
 Authenticate using phone number (no email).  
 *Actors: All Staff Users*

**2.4 Logout from Portal**  
 Securely end session.  
 *Actors: All Staff Users*

**2.5 Update My Profile**  
 Update personal contact information and preferences.  
 *Actors: All Staff Users*

**2.6 Manage Active Sessions**  
 View and revoke active login sessions (max 3 devices).  
 *Actors: All Staff Users*

**2.7 View Ethiopian Dates Throughout System**  
 All dates displayed in Ethiopian calendar format across entire system.  
 *Actors: All Users*

**2.8 Use Ethiopian Date Picker**  
 Select dates using Ethiopian calendar date picker widgets.  
 *Actors: All Staff Users*

**2.9 View Pagume in Applicable Calendars**  
 See Pagume month in all calendars except contribution month selection.  
 *Actors: All Users*

---

## **3\. MEMBERSHIP, PARENTS & GROUPS**

**3.1 Create Member Profile**  
 Register new member with personal details, Ethiopian DOB.  
 *Actors: HR Head*

**3.2 Update Member Profile**  
 Update member details (phone, address, status, family info).  
 *Actors: HR Head*

**3.3 Change Member Status**  
 Update status (Draft, Member, Active, Former).  
 *Actors: HR Head*

**3.4 Manage Parent/Guardian Profile**  
 Create, update parent/guardian profiles (separate from members).  
 *Actors: HR Head*

**3.5 Create Member Group**  
 Create dynamic groups (Kids, Youth, Adults, custom names).  
 *Actors: HR Head, Internal Relations Department Head, Admin*

**3.6 Update Member Group**  
 Edit group name, type, or description.  
 *Actors: HR Head, Internal Relations Department Head, Admin*

**3.7 Delete Member Group**  
 Soft delete group (only if no active members assigned).  
 *Actors: Admin*

**3.8 Assign Member to Group**  
 Assign member to ONE group with effective\_from date.  
 *Actors: HR Head, Internal Relations Department Head*

**3.9 Bulk Assign Members to Group**  
 Assign up to 100 members at once (atomic transaction).  
 *Actors: HR Head, Internal Relations Department Head*

**3.10 Remove Member from Group**  
 Set effective\_to \= current date (soft delete).  
 *Actors: HR Head, Internal Relations Department Head*

**3.11 View Member Timeline**  
 View chronological history of group, class, attendance, contributions.  
 *Actors: HR Head, Department Heads (scoped)*

**3.12 Search Member Profiles**  
 Search by name, ID, phone, group, parent (filters mandatory).  
 *Actors: HR Head, Department Heads (scoped), Admin*

**3.13 Export Member Lists**  
 Export filtered lists to Excel/PDF/CSV.  
 *Actors: Department Head, Department Secretary, Admin*

---

## **4\. EDUCATION & SUNDAY SCHOOL**

**4.1 Create Academic Year**  
 Create new academic year with Ethiopian dates (only one active).  
 *Actors: Education Department Head*

**4.2 Activate Academic Year**  
 Activate new year, deactivate previous, archive enrollments.  
 *Actors: Education Department Head*

**4.3 Deactivate Academic Year**  
 Close year, archive contributions, make attendance read-only.  
 *Actors: Education Department Head*

**4.4 Reactivate Academic Year**  
 Allow Admin to reactivate a deactivated academic year if conditions require.  
 *Actors: Admin*

**4.5 Create Class**  
 Create permanent classes (not tied to academic year).  
 *Actors: Education Department Head*

**4.6 Update Class Details**  
 Edit class name or description.  
 *Actors: Education Department Head*

**4.7 Delete Class**  
 Soft delete class (only if no current enrollments or attendance).  
 *Actors: Education Department Head*

**4.8 Create Subject**  
 Create permanent subjects.  
 *Actors: Education Department Head*

**4.9 Update Subject Details**  
 Edit subject name or description.  
 *Actors: Education Department Head*

**4.10 Delete Subject**  
 Soft delete subject (only if not assigned to any teacher/class).  
 *Actors: Education Department Head*

**4.11 Enroll Student in Class**  
 Enroll member in class for active academic year.  
 *Actors: Education Department Head*

**4.12 Remove Student from Class**  
 Mark as "Withdrawn" (soft delete).  
 *Actors: Education Department Head*

**4.13 Promote Student**  
 End-of-year promotion to next class.  
 *Actors: Education Department Head*

**4.14 Bulk Promote Students**  
 Promote entire class at once (admin can select which students).  
 *Actors: Education Department Head*

**4.15 Create Attendance Session**  
 Create session for class date (not per subject).  
 *Actors: Education Monitor*

**4.16 Record Student Attendance (PWA)**  
 Mark student attendance offline (sync on reconnect).  
 *Actors: Education Monitor*

**4.17 Record Teacher Attendance**  
 Mark teacher present/absent (affects session status).  
 *Actors: Education Monitor*

**4.18 Lock Attendance Session**  
 Lock session manually or auto-lock after 30 days.  
 *Actors: Education Monitor*

**4.19 Unlock Attendance Session**  
 Unlock a locked session with justification (logged in audit trail).  
 *Actors: Education Department Head (with justification)*

**4.20 Review Attendance Sync Conflicts**  
 View attendance conflicts (last sync wins, read-only).  
 *Actors: Education Monitor*

---

## **5\. CONTRIBUTIONS, DONATIONS & REPORTS**

**5.1 Define Contribution Amount**  
 Set amount per group per month (12 Ethiopian months, no Pagume).  
 *Actors: Finance Head*

**5.2 Record Individual Contribution**  
 Record payment for member \+ month \+ active academic year.  
 *Actors: Charity Head*

**5.3 Record Donation**  
 Record one-time donation (no academic year link, never archived).  
 *Actors: Finance Head*

**5.4 View Contribution Reports**  
 Filter by academic year, group, class, date range.  
 *Actors: Finance Head, Nibret ena Hisab Department Head, Admin*

**5.5 View Donation Reports**  
 View all-time donation history (never archived).  
 *Actors: Finance Head, Nibret ena Hisab Department Head, Admin*

**5.6 Track Outstanding Contributions**  
 View members with unpaid amounts (current year only).  
 *Actors: Finance Head, Charity Head, Admin*

**5.7 Generate Financial Statement**  
 Monthly/quarterly statements with summaries.  
 *Actors: Finance Head, Nibret ena Hisab Department Head, Admin*

**5.8 Export Financial Data**  
 Export contributions/donations for audit.  
 *Actors: Finance Head, Nibret ena Hisab Department Head, Superadmin*

---

## **6\. TOURS**

**6.1 Create Tour**  
 Create tour with Ethiopian date, destination, start time.  
 *Actors: Tour Head*

**6.2 Update Tour Details**  
 Edit tour information (destination, date, cost, deadline).  
 *Actors: Tour Head*

**6.3 Delete Tour**  
 Cancel and archive tour (soft delete).  
 *Actors: Tour Head*

**6.4 View Tour Registrations**  
 View public registrations (pending/confirmed).  
 *Actors: Tour Head*

**6.5 Register Tour Passengers (Internal)**  
 Register passengers with phone auto-fill.  
 *Actors: Tour Head*

**6.6 Confirm Registration**  
 Change status from Pending to Confirmed.  
 *Actors: Tour Head*

**6.7 Create Attendance Session**  
 Auto-generate from tour date and confirmed passengers.  
 *Actors: Tour Head*

**6.8 Record Tour Attendance**  
 Mark Present/Not Present for passengers.  
 *Actors: Tour Head*

**6.9 Use Call Button**  
 Call passengers marked "Not Present" (no call logs).  
 *Actors: Tour Head*

**6.10 View Tour Reports**  
 Attendance analytics, passenger lists.  
 *Actors: Tour Head, Revenue & Charity Department Head, Admin*

---

## **7\. WORSHIP, REHEARSAL & MEDIA**

**7.1 Upload Worship Songs**  
 Upload lyrics with audio/video, assign category/sub-category.  
 *Actors: Worship Monitor*

**7.2 Update Song Details**  
 Edit song title, lyrics, audio/video, category.  
 *Actors: Worship Monitor, Mezmur Department Head*

**7.3 Delete Song**  
 Soft delete song from library.  
 *Actors: Worship Monitor, Mezmur Department Head*

**7.4 Manage Song Categories**  
 Create, update, delete categories and sub-categories for songs.  
 *Actors: Worship Monitor, Mezmur Department Head*

**7.5 Schedule Rehearsals**  
 Create rehearsal schedules with Ethiopian dates.  
 *Actors: Mezmur Department Head, Worship Monitor*

**7.6 Record Rehearsal Attendance**  
 Record attendance (not linked to academic year).  
 *Actors: Worship Monitor*

**7.7 Upload Media Content**  
 Upload photos/videos with visibility controls (Public/Members/Department).  
 *Actors: AV Head*

**7.8 Update Media Content**  
 Edit media title, description, visibility, category.  
 *Actors: AV Head*

**7.9 Delete Media Content**  
 Soft delete photos/videos from gallery.  
 *Actors: AV Head, Internal Relations Department Head*

**7.10 Manage Media Categories**  
 Create, update, delete categories and sub-categories for media.  
 *Actors: AV Head*

**7.11 Manage Blog Posts**  
 Create, update, and publish blog posts (Ethiopian dates).  
 *Actors: AV Head*

**7.12 Delete Blog Post**  
 Archive blog post (soft delete).  
 *Actors: AV Head*

**7.13 Schedule Announcements**  
 Schedule with start/end dates, mark as Urgent.  
 *Actors: AV Head*

**7.14 Delete Announcement**  
 Archive announcement (soft delete).  
 *Actors: AV Head*

**7.15 Manage FAQ Content**  
 Create, update, delete FAQs on landing page.  
 *Actors: Admin, AV Head*

---

## **8\. INVENTORY & ASSETS**

**8.1 Create Inventory Item**  
 Register items with Ethiopian purchase date.  
 *Actors: Inventory Staff, Nibret ena Hisab Department Head*

**8.2 Update Inventory Item**  
 Edit item details (name, category, quantity, location).  
 *Actors: Inventory Staff, Nibret ena Hisab Department Head*

**8.3 Delete Inventory Item**  
 Mark inventory item as "Disposed" (soft delete).  
 *Actors: Inventory Staff, Nibret ena Hisab Department Head*

**8.4 Record Inventory Movement**  
 Record stock in/out (cannot exceed available).  
 *Actors: Inventory Staff, Nibret ena Hisab Department Head*

**8.5 View Inventory Analytics**  
 View usage, stock levels, value.  
 *Actors: Inventory Staff, Nibret ena Hisab Department Head, Admin*

---

## **9\. ARCHIVES & DOCUMENTS**

**9.1 Upload Department Documents**  
 Upload with visibility controls (Public/Members/Department).  
 *Actors: Department Head, Department Secretary, Admin*

**9.2 Update Document Visibility**  
 Change document visibility (Public/Members/Department) after upload.  
 *Actors: Document Uploader, Department Head*

**9.3 Search Department Archives**  
 Search within department-scoped documents.  
 *Actors: Department Head, Department Secretary, Admin*

**9.4 Delete Department Document**  
 Soft delete document from archives.  
 *Actors: Department Head, Admin*

**9.5 Upload Library Resources**  
 Upload educational PDFs for public download.  
 *Actors: Education Department Head*

**9.6 Manage Library Categories**  
 Create, update, delete categories and sub-categories for library resources.  
 *Actors: Education Department Head*

**9.7 View Contact Messages**  
 View public contact form submissions.  
 *Actors: Admin, Internal Relations Department Head*

---

## **10\. SECURITY & GOVERNANCE**

**10.1 Manage Users**  
 Create staff accounts, set temporary passwords.  
 *Actors: Admin*

**10.2 Assign Roles**  
 Assign department-scoped roles (force logout on change).  
 *Actors: Admin*

**10.3 Lock/Unlock User Account**  
 Lock after failed attempts, unlock manually.  
 *Actors: Admin, Superadmin*

**10.4 Reset User Password**  
 Manually reset passwords (no self-service).  
 *Actors: Admin, Superadmin*

**10.5 Activate/Deactivate User Account**  
 Activate or deactivate user access to system.  
 *Actors: Admin, Superadmin*

**10.6 Global Oversight View**  
 View all system data across departments.  
 *Actors: Superadmin*

**10.7 Monitor System Health**  
 View performance metrics, storage, error rates.  
 *Actors: Superadmin*

**10.8 View Error Logs**  
 Access error logs (2 month retention).  
 *Actors: Superadmin*

**10.9 View Audit Logs**  
 View financial/member audit logs (permanent) and system logs (30 days).  
 *Actors: Superadmin, Admin*

**10.10 Export Audit Logs**  
 Export audit data for compliance.  
 *Actors: Superadmin*

---

## **11\. EVENTS & FUNDRAISING**

**11.1 Create Event**  
 Create church events with Ethiopian dates.  
 *Actors: Admin*

**11.2 Update Event Details**  
 Edit event information (name, date, location, registration).  
 *Actors: Admin*

**11.3 Delete Event**  
 Cancel and archive event (soft delete).  
 *Actors: Admin*

**11.4 Create Fundraising Campaign**  
 Create display-only campaigns with targets.  
 *Actors: Admin*

**11.5 Update Campaign Total Raised**  
 Manually input amount raised for display on public website.  
 *Actors: Admin*

**11.6 Delete Fundraising Campaign**  
 Archive fundraising campaign (soft delete).  
 *Actors: Admin*

---

## **12\. DATA MANAGEMENT**

**12.1 Export System Backup**  
 Generate manual backup (local storage only).  
 *Actors: Superadmin*

**12.2 Restore from Backup**  
 Restore system with downtime required.  
 *Actors: Superadmin*

**12.3 Manage "Others" Options**  
 Approve/reject custom dropdown options added by users.  
 *Actors: Admin*

**12.4 Merge Duplicate Custom Options**  
 Merge duplicate custom dropdown values.  
 *Actors: Admin*

**12.5 Reorder Dropdown Options**  
 Change display order of approved custom options.  
 *Actors: Admin*

---

## **13\. MOBILE & OFFLINE**

**13.1 Record Attendance Offline**  
 Mark attendance offline (PWA, auto-sync).  
 *Actors: Education Monitor, Worship Monitor, Tour Head*

**13.2 Access Cached Content**  
 View cached songs, documents when offline.  
 *Actors: All Authenticated Users*

**13.3 Download Content for Offline**  
 Manually cache songs/media.  
 *Actors: Public Visitor, Staff Users*

---

## **14\. NOTIFICATIONS**

**14.1 Receive In-App Notifications**  
 Receive tour, event, financial, attendance notifications.  
 *Actors: All Staff Users*

**14.2 Receive PWA Push Notifications**  
 Receive push notifications if app installed.  
 *Actors: All Staff Users with PWA*

---

## **15\. REPORTS & EXPORTS**

**15.1 View Predefined Reports**  
 View system-defined reports.  
 *Actors: Department Heads, Admin, Superadmin*

**15.2 Apply Temporary Filters to Reports**  
 Apply session-based filters to any report (date range, group, class, status) without saving.  
 *Actors: All Report Viewers*

**15.3 Export Report Data**  
 Export any report to Excel/PDF/CSV.  
 *Actors: All Report Viewers*

**15.4 Manual Off-Server Export**  
 Export data for external compliance/backup.  
 *Actors: Superadmin*

---

## **16\. TEACHER MANAGEMENT**

**16.1 Create External Teacher Profile**  
 Create non-member teacher with name and phone only.  
 *Actors: Education Department Head*

**16.2 Assign Member as Teacher**  
 Link existing member to teacher role with subject/class assignments.  
 *Actors: Education Department Head*

**16.3 Update Teacher Profile**  
 Edit teacher information (phone, qualifications, status).  
 *Actors: Education Department Head*

**16.4 Assign Teacher to Class/Subject**  
 Assign teacher to class \+ subject \+ academic year.  
 *Actors: Education Department Head*

**16.5 Update Teacher Assignments**  
 Modify class/subject assignments for teachers.  
 *Actors: Education Department Head*

**16.6 Remove Teacher from Assignment**  
 Set effective\_to date and mark assignment as Inactive (soft delete).  
 *Actors: Education Department Head*

**16.7 Assign Substitute Teacher**  
 Mark session as "Substitute Assigned" when another teacher covers absent teacher.  
 *Actors: Education Monitor*

**16.8 View Teacher Assignment History**  
 View all classes/subjects a teacher has taught across academic years.  
 *Actors: Education Department Head*

**16.9 View Teacher Attendance Rate**  
 View teacher performance metrics and attendance analytics.  
 *Actors: Education Department Head*

---

## **17\. "OTHERS" OPTION MANAGEMENT**

**17.1 Add Custom Option**  
 Select "Other" and enter custom value (pending approval).  
 *Actors: All Staff Users when selecting "Other"*

**17.2 Approve/Reject Custom Options**  
 Manage pending custom dropdown values.  
 *Actors: Admin*

---

## **18\. SEARCH & TIMELINE**

**18.1 Search Member Timeline**  
 Search timeline with mandatory filters (name, ID, phone, group, parent).  
 *Actors: HR Head, Department Heads*

**18.2 Search Tours**  
 Search tours by destination, date, status.  
 *Actors: Tour Head, Admin*

**18.3 Search Inventory**  
 Search inventory by category, location, status.  
 *Actors: Inventory Staff, Nibret ena Hisab Department Head*

**18.4 Search Archives**  
 Search department documents by tags, date, title.  
 *Actors: Department Head, Department Secretary, Admin*

---

## **19\. SYSTEM AUTOMATION**

**19.1 Archive Contributions**  
 Automatic archival when academic year deactivated.  
 *Actors: System (Automated)*

**19.2 Auto-Lock Attendance Sessions**  
 Lock sessions after 30 days automatically.  
 *Actors: System (Automated)*

**19.3 Receive Session Lock Reminder**  
 Notification for approaching 30-day lock deadline.  
 *Actors: Education Monitor (via system notification)*

**19.4 Auto-Archive Media Files**  
 Automatically archive media files older than 5 years (soft delete).  
 *Actors: System (Automated)*

**19.5 Auto-Purge Error Logs**  
 Automatically delete error logs older than 2 months.  
 *Actors: System (Automated)*

**19.6 Auto-Purge Session Logs**  
 Automatically delete session logs older than 90 days.  
 *Actors: System (Automated)*

**19.7 Auto-Purge Read Notifications**  
 Automatically delete read notifications older than 90 days.  
 *Actors: System (Automated)*

---

## **20\. CHARITY & BENEFICIARIES**

**20.1 Manage Beneficiaries**  
 Create, update beneficiary profiles.  
 *Actors: Charity Head*

**20.2 Record Aid Distribution**  
 Log aid distributions to beneficiaries.  
 *Actors: Charity Head*

**20.3 View Beneficiary Reports**  
 View aid distribution history and beneficiary analytics.  
 *Actors: Charity Head, Revenue & Charity Department Head, Admin*

---

## **21\. CONTENT WORKFLOW**

**21.1 Schedule Content Publication**  
 Set future publish dates for blog posts/announcements.  
 *Actors: AV Head*

---

## **22\. DEPARTMENT MANAGEMENT**

**22.1 Manage Departments**  
 Create departments and assign heads and secretaries.  
 *Actors: Admin*

**22.2 Assign Department Roles**  
 Assign specific roles within departments.  
 *Actors: Admin*

---

## **23\. HELP & SUPPORT**

**23.1 Access Help Documentation**  
 View contextual help articles and user guides.  
 *Actors: All Users*

# **COMPLETE BUSINESS RULES & CONSTRAINTS**

  **TABLE OF CONTENTS**

1. Authentication & Access Control  
2. Department & Role Management  
3. Membership & Groups  
4. Academic Year & Education  
5. Attendance & Sync Management  
6. Contributions & Financial Tracking  
7. Tours & Registration  
8. Inventory Management  
9. Worship & Media Content  
10. Events & Fundraising  
11. Archives & Documents  
12. System Settings & Audit  
13. Data Retention & Privacy  
14. Multi-language Support  
15. Mobile & Offline Capabilities  
16. Backup & Disaster Recovery  
17. Ethiopian Calendar Implementation  
18. In-App Notifications  
19. Reports & Exports (No Saved Filters)  
20. "Others" Option Management  
21. Teacher Management  
22. Charity & Beneficiaries  
23. Category & Sub-Category Management

---

## **1\. AUTHENTICATION & ACCESS CONTROL**

### **1.1 Login & Session Rules**

* ✅ **Phone number ONLY for authentication** (no email for login)  
* ✅ Ethiopian phone numbers only (+251 format)  
* ✅ Email remains optional profile metadata only  
* ✅ Password requirements: 8+ characters, uppercase, lowercase, number required  
* ✅ Session timeout: 30 minutes of inactivity  
* ✅ Maximum active sessions: 3 devices simultaneously  
* ✅ Manual session revocation allowed  
* ✅ **Account lockout progressive:**  
  * First 5 failed attempts → 1-minute lock  
  * Next 5 failed attempts → 5-minute lock  
  * All subsequent failed attempts → 5-minute lock

### **1.2 Password Management**

* ✅ **NO self-service password reset**  
* ✅ Admin/Superadmin manually resets passwords only  
* ✅ First-time users must change temporary password on first login  
* ✅ Cannot reuse last 3 passwords  
* ✅ Password complexity validated on client and server

### **1.3 Role-Based Access Control (RBAC)**

* ✅ One user \= One role \+ One department (except Superadmin/Admin)  
* ✅ Roles are department-scoped (users only see their department's data)  
* ✅ **Superadmin:** Full system access (technical \+ configuration \+ all data)  
* ✅ **Admin:** Operational access to all departments (NO system settings/backups)  
* ✅ All data queries auto-filtered by user's department unless Superadmin/Admin  
* ✅ Query scopes enforced at database level (Eloquent global scopes)

### **1.4 Permission & User Management**

* ✅ Department Head inherits all permissions of sub-department heads  
* ✅ Department Secretary: Create/Update only (NO Delete rights)  
* ✅ Superadmin can override any permission for emergency access  
* ✅ Superadmin/Admin can activate/deactivate users  
* ✅ Deactivated users cannot log in but data remains intact

---

## **2\. DEPARTMENT & ROLE MANAGEMENT**

### **2.1 Department Structure**

* ✅ **7 FIXED departments (hardcoded, cannot be changed):**  
  1. **Internal Relations**  
  2. **Nibret ena Hisab** (Finance and Inventory)  
  3. **Education**  
  4. **Revenue & Charity**  
  5. **Mezmur** (Worship/Choir)  
  6. **Foreign Affairs**  
  7. **Kinetibeb**  
* ✅ **Admin is NOT a department** (it is a role only)  
* ✅ Cannot delete department if it has active users or data  
* ✅ Each department must have exactly **one Department Head**  
* ✅ **Unlimited Secretaries** per department allowed  
* ✅ Sub-Department Heads are optional and function-specific

### **2.2 Sub-Department Structure**

**Department → Sub-Department Heads Mapping:**

1. **Internal Relations:**  
   * HR Head (manages member profiles, groups)  
   * AV Head (manages media, blog, announcements)  
2. **Nibret ena Hisab:**  
   * Finance Head (records contributions, donations, financial reports)  
   * Inventory Staff (manages inventory items, movements)  
3. **Education:**  
   * Education Monitor (records class and teacher attendance)  
4. **Revenue & Charity:**  
   * Charity Head (manages beneficiaries, aid distribution)  
   * Tour Head (creates tours, manages registrations and attendance)  
5. **Mezmur:**  
   * Worship Monitor (manages songs, rehearsals, rehearsal attendance)  
6. **Foreign Affairs:**  
   * Generic department functions (no sub-department heads)  
7. **Kinetibeb:**  
   * Generic department functions (no sub-department heads)

### **2.3 Role Assignment Rules**

* ✅ Only **Admin** can assign/change roles  
* ✅ Role changes are logged in audit trail (who changed, when, old→new role)  
* ✅ Role changes force immediate session termination (user must re-login)  
* ✅ Only one user per Sub-Department Head position (e.g., only one HR Head)  
* ✅ Unlimited users for generic "Staff" roles within departments

### **2.4 User Management**

* ✅ **Admin** creates all staff user accounts  
* ✅ Default temporary password provided on account creation  
* ✅ User receives password via secure channel (not email)  
* ✅ Accounts can be locked/unlocked by Admin or Superadmin  
* ✅ Locked accounts cannot log in (all data preserved)  
* ✅ Cannot delete users with historical records (contributions, attendance, edits)  
* ✅ **Soft delete only** (mark as inactive, preserve audit trail)

---

## **3\. MEMBERSHIP & GROUPS**

### **3.1 Member Profile Rules**

#### **General Rules**

* ✅ **Tabbed UI for member data** (not long scrolling forms):  
  * Tab 1: Personal Info  
  * Tab 2: Address & Contact  
  * Tab 3: Emergency & Spiritual  
  * Tab 4 (Kids): Parent/Guardian Information  
  * Tab 4 (Youth/Adults): Family & Occupation  
  * Tab 5: Status & History  
* ✅ Ethiopian calendar for all date fields  
* ✅ Phone number must be unique system-wide  
* ✅ Member ID auto-generated: `M-000001` (sequential)  
* ✅ **Member Status:** Draft, Member, Active, Former  
  * **Draft:** Profile created but not yet finalized  
  * **Member:** Regular member  
  * **Active:** Active participant (default)  
  * **Former:** No longer attending church  
* ✅ HR Head controls all status changes (no approval workflow)  
* ✅ Cannot hard-delete member profiles (soft delete only)  
* ✅ Former members archived but remain searchable

#### **Common Required Fields (All Member Types)**

* Title / ማዕረግ  
* ID Number / መለያ ቁጥር  
* First Name / ስም  
* Father's Name / የአባት ስም  
* Grandfather's Name / የአያት ስም  
* Mother's Name / የእናት ስም  
* Date of Birth / የትውልድ ቀን (Ethiopian calendar)  
* Gender / ፆታ  
* Christian Name / የክርስትና ስም  
* City / የመኖሪያ ከተማ  
* Sub-City / ክ/ከተማ  
* Woreda / ወረዳ  
* Zone/Keten / ቀጠና  
* Block / ብሎክ  
* Neighborhood Specific Name / የሠፈር ልዩ ስም  
* Personal Phone / ስልክ (unique)  
* Emergency Contact Name / የቅርብ ጓደኛ ስም  
* Emergency Contact Phone / የቅርብ ጓደኛ ስልክ  
* Confession Father's Name / የንስሀ አባት ስም  
* Confession Father's Phone / የንሰሐ አባት ስልክ

#### **Kids-Only Fields**

**Parent/Guardian Information (Repeater \- Can add multiple):**

* Parent/Guardian Name / የወላጅ ወይም የአሳዲጊ ስም  
* Relationship / ግንኙነት  
  * **Options:** Father, Mother, Guardian, GrandFather, GrandMother, Uncle, Brother, Aunt, Sister, Other  
* Parent/Guardian Phone / የወላጅ ወይም የአሳዲጊ ስልክ  
* \[+ Add Another Parent/Guardian button\]

**Additional Kids Fields:**

* Spiritual Education Level / የመንፈሳዊ ት/ት ደረጃ  
* Special Talents / ልዩ ተሰጥዖ አሎት

#### **Youth & Adult Fields (Same for both)**

**Family Information:**

* Total Family Size / የቤተሰብ ብዛት  
* Number of Brothers / ወንድም ብዛት  
* Number of Sisters / እህት ብዛት

**Spiritual & Church Info:**

* Family Confession Father / የቤተሰብ ንስሐ አባት ስም  
* Sunday School Entry Year / ሰንበት ትምህርት ቤት የገቡበት ዓ.ም (Ethiopian calendar)  
* Past Service Departments / ያገለገሉባቸው ክፍላት

**Occupation Status:**

* Status / ሁኔታ (dropdown: Student / ተማሪ OR Employee / ሰራተኛ)

**IF STUDENT SELECTED:**

* Education Information (Repeater \- Can add multiple):  
  * School Name / የሚማሩበት ት/ቤት  
  * Education Level / የትምህርት ደረጃ  
  * Education Department / የትምህርት ዘርፍ  
  * \[+ Add Another Education Level button\]

**IF EMPLOYEE SELECTED:**

* Employment Status / የስራ ሁኔታ (dropdown: Hired / ተቀጥሮ, Not Hired / ያልተቀጠረ, Private Sector / የግል ዘርፍ) **IF "HIRED" OR "PRIVATE SECTOR" SELECTED:**  
  * Company Name / የድርጅት ስም  
  * Job Role/Title / የስራ ድርሻ  
  * Company Address / የድርጅት አድራሻ

**Marital Status:**

* Marital Status / የትዳር ሁኔታ (dropdown: Single / ያላገባ, Married / ያገባ)

**IF "MARRIED" SELECTED:**

* Marriage Year / ጋብቻው የተፈፀመበት ዓ.ም (Ethiopian calendar)  
* Spouse Name / የባለቤት ስም  
* Spouse Phone / የባለቤት ስልክ  
* Number of Children / የልጆች ብዛት

**IF NUMBER OF CHILDREN \> 0:**

* Children Information (Repeater based on number):  
  * Child 1 Name / የልጅ 1 ስም  
  * Child 2 Name / የልጅ 2 ስም  
  * \[etc., based on number entered\]

#### **Kid → Youth/Adult Transition**

**What Happens:**

* Admin/HR Head manually changes member type from "Kid" to "Youth" or "Adult"  
* All existing Kid data **RETAINED** (stored for reference):  
  * All common fields preserved  
  * Parent/Guardian repeater records preserved as historical data  
  * Special Talents preserved as historical data  
  * Spiritual Education Level preserved as historical record  
* New Youth/Adult-only fields become available for data entry  
* Kid-only fields hidden in UI but preserved in database

### **3.2 Parent/Guardian Rules**

* ✅ **Parents stored separately from members table**  
* ✅ Not all parents are church members  
* ✅ One parent can be linked to **multiple children**  
* ✅ One child can have **multiple parents/guardians** (up to 10\)  
* ✅ **Required fields:** Full Name, Phone, Relationship Type  
* ✅ **Relationship types:** Father, Mother, Guardian, GrandFather, GrandMother, Uncle, Brother, Aunt, Sister, Other  
* ✅ Cannot delete parent if linked to any active member  
* ✅ Soft delete only (mark as inactive)

### **3.3 Member Group Rules**

* ✅ **Group types are DYNAMIC** (not hardcoded as Kids/Youth/Adults)  
* ✅ Admin, HR Head, Internal Relations Department Head can create custom group types and names  
* ✅ **Example names:** "Little Angels" (Kids type), "Fire Starters" (Youth type), "Pillars" (Adult type), "Deacons" (Ministry type)  
* ✅ **One member \= ONE group at a time** (no simultaneous group assignments)  
* ✅ Group assignment fields:  
  * `effective_from` (defaults to current date if not specified)  
  * `effective_to` (optional when assigning, set when removing from group)  
* ✅ **Group assignment history never deleted** (audit trail preserved)  
* ✅ When removing from group: set `effective_to` \= current date (soft delete)  
* ✅ Cannot delete group if any members currently assigned to it  
* ✅ Can archive group (soft delete) if no active members

### **3.4 Bulk Assignment Rules**

* ✅ Maximum **100 members** per bulk assignment operation  
* ✅ All assignments share same `effective_from` date  
* ✅ **Atomic transaction:** all succeed or entire batch rolls back  
* ✅ System validates no conflicts before commit (no overlapping group assignments)  
* ✅ Success/failure logged with specific error messages per member

### **3.5 Member Timeline & History**

**Timeline Display Rules:**

* ✅ Timeline shows chronological events (newest first):  
  * Group assignments (joins/removals)  
  * Class enrollments (current and historical)  
  * Attendance records  
  * Contribution history  
* ✅ **Timeline is read-only** (historical view only, cannot edit from timeline)  
* ✅ Sorted by date descending (most recent events first)  
* ✅ Can filter by event type (Group, Class, Attendance, Contributions)

**Searchable Fields for Member Timeline (MANDATORY FILTERS):**

1. Name (First Name, Father's Name, Grandfather's Name)  
2. Member ID (M-000001 format)  
3. Phone (personal phone number)  
4. Group (current or historical group names)  
5. Parent (parent/guardian name)

**Display Rules:**

* ✅ Default view: Last 10 events only  
* ✅ "Load More" pagination for additional events (10 events per page)  
* ✅ **Filters are MANDATORY** (must select at least one filter to search timeline)  
* ✅ Cannot view entire timeline without applying filters (performance/privacy)

### **3.6 Member Search & Export**

* ✅ Search by all profile fields listed in 3.1  
* ✅ Search results scoped to user's department access:  
  * HR Head: sees all members  
  * Department Heads: see members relevant to their function  
  * Education Head: see students and teachers  
  * Finance Head: see members with contributions  
* ✅ Export formats: Excel (.xlsx), PDF, CSV  
* ✅ **Export logging:** Who exported, what data (filters applied), when exported, record count  
* ✅ Export includes only fields user has permission to view

---

## **4\. ACADEMIC YEAR & EDUCATION**

### **4.1 Academic Year Rules**

* ✅ **Only ONE academic year can be active at any time**  
* ✅ Academic year naming decided by Education Department Head:  
  * Can use Ethiopian format: "2017 E.C."  
  * OR Gregorian format: "2024/2025"  
  * OR Custom format: "2024-25 Academic Year"  
* ✅ Dates stored as Gregorian internally, displayed as Ethiopian in UI  
* ✅ Start date must be before end date  
* ✅ Academic years **cannot overlap** in date ranges  
* ✅ Cannot delete academic year if:  
  * Has enrollments  
  * Has contributions  
  * Has attendance records  
* ✅ Can only update academic year if status is "Draft" (not yet activated)

### **4.2 Academic Year Activation**

* ✅ When new academic year is activated:  
  * Previous active year automatically deactivated  
  * `is_active` flag set to TRUE for new year, FALSE for previous  
  * System timestamp of activation recorded  
  * All enrollments from previous year marked as "Completed"  
  * System generates end-of-year report for previous year  
* ✅ Cannot activate academic year if dates overlap with currently active year

### **4.3 Academic Year Deactivation**

* ✅ **Only Education Department Head can deactivate** (no approval workflow required)  
* ✅ When academic year is deactivated:  
  * All enrollments archived (status changed to "Completed")  
  * All contributions archived (see Section 6.5)  
  * Attendance sessions remain accessible (read-only)  
  * `is_active` flag set to FALSE  
* ✅ **Admin can reactivate academic year if needed** (override capability for corrections)  
* ✅ System generates summary report before deactivation:  
  * Total students enrolled  
  * Total attendance sessions conducted  
  * Total contributions recorded  
  * Outstanding contributions by group  
  * Promotion statistics

### **4.4 Class & Subject Rules**

* ✅ **Classes are PERMANENT** (not tied to academic year)  
* ✅ **Subjects are PERMANENT** (not tied to academic year)  
* ✅ Classes can be reused across multiple academic years  
* ✅ Subjects can be taught across multiple years  
* ✅ **Only ENROLLMENTS are linked to academic year** (student-class-year relationship)  
* ✅ Class examples: "Grade 1", "Grade 2", "Beginners", "Advanced", "Youth Class"  
* ✅ Subject examples: "Bible Study", "Church History", "Amharic", "English", "Tigrinya"  
* ✅ Cannot delete class if:  
  * Has current enrollments  
  * Has attendance records  
* ✅ Cannot delete subject if:  
  * Assigned to any teacher  
  * Assigned to any class  
* ✅ Can archive class/subject (soft delete) to hide from active views

### **4.5 Student Enrollment Rules**

* ✅ Student must be a church member to enroll  
* ✅ **One student \= ONE class per academic year** (cannot be in multiple classes simultaneously)  
* ✅ Enrollment record includes:  
  * Student (member ID)  
  * Class  
  * Academic Year  
  * `enrolled_date`  
  * `completion_date` (optional, set when withdrawn or promoted)  
  * Status (Enrolled, Withdrawn, Completed, Promoted)  
* ✅ Cannot enroll if student already in another class for same academic year  
* ✅ Enrollment creates audit trail:  
  * Who enrolled the student  
  * When enrolled  
  * Which class and academic year

### **4.6 Student Promotion Rules**

* ✅ **End-of-year promotion ONLY** (no mid-year promotions)  
* ✅ Promotion creates new enrollment record in next grade  
* ✅ Previous enrollment marked as `completed` with completion date  
* ✅ **When students reach last level:**  
  * They leave their current class (marked as completed)  
  * No automatic enrollment in new class  
  * Can remain classless if not promoted  
* ✅ **If students not fit to promote:**  
  * Can stay in current class (repeat year) \- new enrollment record created for same class  
  * OR can become classless (no enrollment for next year)  
* ✅ Cannot promote if target class doesn't exist (must create class first)  
* ✅ Promotion logs include:  
  * Who promoted  
  * When promoted  
  * From which class → To which class  
  * Reason (optional notes)  
* ✅ **Bulk promotion supported:**  
  * Can select entire class for promotion  
  * Admin can select which specific students to promote  
  * Students not selected remain in current class or become classless

### **4.7 Student Removal Rules**

* ✅ Removal sets `completion_date` and marks status as **"Withdrawn"**  
* ✅ **Does NOT delete enrollment record** (soft delete, history preserved)  
* ✅ Student can be re-enrolled later in same or different class  
* ✅ **Removal reasons (dropdown):** Moved Away, Transferred, Graduated, Other  
* ✅ Removal notes field (optional, max 500 characters)  
* ✅ Removal creates audit log entry

---

## **5\. ATTENDANCE & SYNC MANAGEMENT**

### **5.1 Attendance Session Rules**

* ✅ **One session per class per date** (NOT per subject)  
* ✅ **One attendance session for entire class that day** (all subjects combined)  
* ✅ Created by Education Monitor  
* ✅ **Session status:** Open, Completed, Locked  
* ✅ Cannot modify attendance once session is **"Locked"**  
* ✅ Only Education Monitor can manually lock sessions  
* ✅ **Session must be locked within 30 days** of attendance date (grace period)  
* ✅ **If not locked within 30 days → auto-lock automatically**  
* ✅ **Unlocking locked sessions:**  
  * Only Education Department Head can unlock  
  * Requires justification (text field, mandatory)  
  * Unlock action logged in audit trail with justification

### **5.2 Recording Attendance (PWA Offline)**

* ✅ **Attendance options:** Present, Absent, Excused, Late, Permission  
* ✅ Monitors can mark attendance **offline** via PWA  
* ✅ Offline data stored locally in browser IndexedDB  
* ✅ **Auto-sync when connection restored** (background sync)  
* ✅ Sync queue priority: Attendance \> Other data types  
* ✅ Each attendance record includes:  
  * Student ID  
  * Session ID  
  * Status (Present/Absent/Excused/Late/Permission)  
  * Marked by (user ID)  
  * Marked at (timestamp)  
  * Sync timestamp (when uploaded to server)

### **5.3 Sync Conflict Rules**

* ✅ **Conflict occurs when:**  
  * Two users mark same student's attendance offline  
  * Both sync to server with different values  
* ✅ **Conflict resolution:** Last sync wins (overwrites previous value)  
* ✅ All conflicts logged in `attendance_sync_conflicts` table:  
  * Student ID  
  * Session ID  
  * User 1 \+ Value 1 (first sync)  
  * User 2 \+ Value 2 (second sync, winner)  
  * Winner value (what's stored)  
  * Timestamp of conflict  
* ✅ Education Monitor can review conflicts (read-only view)  
* ✅ **No manual conflict resolution** (too complex for MVP, would require complex UI)  
* ✅ Conflicts flagged in reports for manual investigation if critical

### **5.4 Teacher Attendance Rules**

* ✅ **Session created for specific date \+ class \+ subject**  
* ✅ **Education Monitor marks teacher attendance** for assigned classes  
* ✅ **Teachers CANNOT mark their own attendance** (prevents fraud)  
* ✅ **Attendance options:** Present, Absent, Late, Permission  
* ✅ **Late threshold:** \>15 minutes after session start time  
* ✅ **If teacher absent:**  
  * Session marked as "Cancelled" (if no substitute)  
  * OR "Substitute Assigned" (if another teacher covers, recorded in notes)  
* ✅ Teacher attendance rate calculated:  
  * Formula: (Present sessions / Total assigned sessions) × 100  
  * Used for performance reviews  
  * Visible to Education Department Head only

### **5.5 Tour Attendance Rules**

* ✅ **Attendance session auto-created from tour date**  
* ✅ All confirmed tour registrants auto-added to attendance list  
* ✅ **Attendance recorded at tour start time** (not departure time)  
* ✅ **Attendance options:** Present, Not Present (only two options, simplified)  
* ✅ **Call button visible for passengers marked "Not Present"**  
* ✅ Call button opens phone dialer with passenger's number  
* ✅ **No call logs stored** (simplified, privacy)  
* ✅ **Call history NOT visible in tour attendance reports**

### **5.6 Rehearsal Attendance Rules**

* ✅ **Session can be created for:**  
  * Specific dates  
  * Different classes (if class-based rehearsal)  
  * Different groups (if group-based rehearsal)  
  * General rehearsal (all choir members)  
* ✅ Lists shown based on created session parameters  
* ✅ Recorded by Worship Monitor  
* ✅ **Attendance options:** Present, Absent, Excused, Late, Permission (same as class attendance)  
* ✅ **NOT linked to academic year** (ongoing activity, year-round)  
* ✅ Attendance history used for:  
  * Performance reviews  
  * Eligibility for special performances  
  * Scheduling soloists

---

## **6\. CONTRIBUTIONS & FINANCIAL TRACKING**

### **6.1 Core Contribution Rules**

* ✅ **Contributions can be recorded for members WITHOUT group assignment**  
  * Member doesn't need to belong to a group to record contributions  
  * Expected amount \= 0 if no group assigned (all payments \= extra)  
* ✅ Cannot record contribution without active academic year  
* ✅ Contribution amount must be **≥ 0.01** (positive number)  
* ✅ Contribution dates use Ethiopian calendar  
* ✅ Member must be in "Active" or "Member" status to record contribution  
* ✅ Cannot record contributions for "Draft" or "Former" status members

### **6.2 Contribution Amount Settings**

* ✅ **Contribution amounts set per group per MONTH**  
* ✅ **Month names created and managed** (12 Ethiopian months only)  
* ✅ **Pagume EXCLUDED from contribution month selection** (only 12 months shown)  
* ✅ Creator decides whether to use Ethiopian month names (Meskerem, Tikimt...) or Gregorian (January, February...)  
* ✅ Multiple amounts can exist for same group (historical tracking with effective dates)  
* ✅ Each amount setting includes:  
  * Group  
  * Month  
  * Amount  
  * `effective_from` date  
  * `effective_to` date (optional)  
* ✅ Amount periods **cannot overlap** for same group \+ month combination  
* ✅ Cannot delete amount setting if contributions have been recorded against it  
* ✅ **Example structure:**  
  * Kids Group \- Meskerem: 50 Birr (effective 2024-09-01 to 2025-08-31)  
  * Youth Group \- Meskerem: 100 Birr (effective 2024-09-01 to 2025-08-31)

### **6.3 Recording Individual Contributions**

* ✅ **Recorded by:** Charity Head only  
* ✅ **Required fields:**  
  * Member ID (dropdown/search)  
  * Amount (positive number)  
  * Month (dropdown of contribution months)  
* ✅ **Auto-filled fields:**  
  * Academic Year ID (from currently active academic year)  
  * Payment Date (defaults to today, Ethiopian calendar, can be changed)  
  * Recorded By (current user ID)  
* ✅ **Optional fields:**  
  * Payment Method (Cash, Check, Mobile Money, Bank Transfer, Other)  
  * Notes (max 500 characters)  
* ✅ **Each month is independent:**  
  * Member can pay for one month at a time  
  * Member can pay for multiple past months in one transaction (create multiple records)  
  * Member can pay for future months in advance  
* ✅ Cannot record negative contributions  
* ✅ Cannot record zero contributions (use "waived" status instead, not implemented in MVP)  
* ✅ System warns if:  
  * Member already paid for this month  
  * Amount is unusual for member's group (\>50% different from expected)

### **6.4 Outstanding Contribution Logic**

* ✅ **Formula:** `Outstanding = Expected Amount - Sum(Paid Contributions)`  
* ✅ Expected amount based on member's **current group** at calculation time  
* ✅ If member has no group: Expected \= 0 (no outstanding)  
* ✅ **Only calculated for active academic year**  
* ✅ When academic year deactivates, outstanding amounts archived  
* ✅ **No automatic carry-forward** of unpaid contributions to new academic year  
* ✅ Finance Head can manually review archived year outstanding if needed

### **6.5 Contribution Archival Rules**

* ✅ When academic year is deactivated:  
  * All contributions for that year marked as `archived` (flag in database)  
  * Contributions no longer appear in active reports/dashboards  
  * Contributions removed from "Outstanding Contributions" lists  
  * Still accessible via "View by Academic Year" historical reports  
* ✅ **Archived contributions are read-only** (cannot edit or delete)  
* ✅ Cannot un-archive contributions (permanent archival)  
* ✅ Archival is automatic (triggered by academic year deactivation)

### **6.6 Payment Validation Rules**

* ✅ **Contributions can be recorded at any time** (no date restrictions)  
* ✅ **Members can pay for multiple months:**  
  * In one transaction: Create multiple contribution records (one per month)  
  * In separate transactions: Create individual records as paid  
* ✅ Cannot record duplicate contribution (same member \+ same month \+ same academic year)  
* ✅ Can record multiple payments for same month (e.g., partial payments) \- sum shown as total  
* ✅ System warns if:  
  * Member already has full payment for this month  
  * Total payments for month exceed expected amount by \>20%

### **6.7 Donation Rules (Not Contributions)**

* ✅ Donations are **completely separate from contributions**  
* ✅ **Donations do NOT link to academic year**  
* ✅ **Donations are NEVER archived** (always visible in historical view)  
* ✅ Donations can be anonymous (donor name optional)  
* ✅ **Donation types (dropdown):**  
  * General Fund  
  * Building Fund  
  * Missionary Support  
  * Charity/Aid  
  * Other (allows custom text)  
* ✅ Donations recorded by Finance Head only  
* ✅ Separate reporting from contributions (different reports entirely)  
* ✅ Donation fields:  
  * Donor Name (optional, can be "Anonymous")  
  * Amount (required, positive)  
  * Donation Date (Ethiopian calendar)  
  * Donation Type (dropdown)  
  * Notes (optional, max 500 chars)  
  * Recorded By (auto-filled)

### **6.8 Financial Reporting Rules**

* ✅ **Contribution reports filter by:**  
  * Academic Year (dropdown, includes "All Years" for archived view)  
  * Group (multi-select)  
  * Class (multi-select, for student contributions)  
  * Date Range (Ethiopian calendar date picker)  
  * Month (contribution month)  
  * Member Status (Active/Member/Former)  
  * Payment Method (multi-select)  
* ✅ **Key metrics displayed:**  
  * Total expected contributions (based on group amounts × member count)  
  * Total collected contributions  
  * Outstanding contributions (expected \- collected)  
  * Collection rate (%) \= (collected / expected) × 100  
  * Top contributors (members with highest total paid)  
  * Payment method breakdown  
* ✅ **Export formats:** Excel (.xlsx), PDF  
* ✅ **Audit export includes:**  
  * All transaction records  
  * Who recorded each contribution  
  * When recorded (timestamp)  
  * Any modifications (old value → new value)  
  * Recorded by user name and role

### **6.9 Financial Statement Rules**

* ✅ **Can be generated:** Monthly, Quarterly, Annually  
* ✅ **Includes sections:**  
  * Total Contributions (broken down by group and month)  
  * Total Donations (broken down by type)  
  * Outstanding Contributions (current academic year only)  
  * Collection trends (month-over-month comparison)  
* ✅ Can filter by specific academic year or date range  
* ✅ **Export format:** PDF only (formatted for presentation/printing)  
* ✅ Includes church logo, generation date (Ethiopian), and generated by user  
* ✅ **Only Finance Head, Admin, and Nibret ena Hisab Department Head can generate**  
* ✅ Statement generation logged in audit trail

---

## **7\. TOURS & REGISTRATION**

### **7.1 Tour Creation Rules**

* ✅ **Required fields:**  
  * Tour Place (destination name)  
  * Description (tour details, itinerary)  
  * Tour Date (Ethiopian calendar)  
  * Tour Start Time (departure time)  
* ✅ **Optional fields:**  
  * Cost per Person  
  * Registration Deadline (Ethiopian calendar)  
  * Maximum Capacity (passenger limit)  
* ✅ **Tour status (dropdown):**  
  * Draft (not visible to public)  
  * Published (visible on public website)  
  * In Progress (tour is happening)  
  * Completed (tour finished)  
  * Cancelled (tour cancelled)  
* ✅ Cannot delete tour if registrations exist (must cancel instead)  
* ✅ Can only edit tour details if status is Draft or Published

### **7.2 Registration Rules**

#### **Public Registration (Website)**

* ✅ **Only "Published" tours shown on public landing page**  
* ✅ **Public registration form fields:**  
  * Full Name (required)  
  * Phone (required, unique per tour \- same person can't register twice)  
  * Number of Passengers (required, default: 1\)  
  * Receipt Image/Document Upload (proof of payment, optional)  
* ✅ **Email field REMOVED** from public registration  
* ✅ Registration creates record in `tour_passengers` table (see Section 7.4)

#### **Internal Registration (Tour Head)**

* ✅ **Tour passengers can be:**  
  * Church members (select from member list)  
  * Non-members (manual entry)  
* ✅ **Smart auto-fill functionality:**  
  * Tour Head enters phone number  
  * If phone exists in `members` table → auto-fill name and link Member ID  
  * If phone exists in previous `tour_passengers` → auto-fill name and details  
  * If phone doesn't exist → manual entry required  
* ✅ **Registration status (dropdown):**  
  * Pending (awaiting confirmation)  
  * Confirmed (approved by Tour Head)  
  * Cancelled (registration cancelled)

### **7.3 Registration Confirmation**

* ✅ All registrations start as **"Pending"**  
* ✅ Tour Head reviews and manually confirms registrations  
* ✅ Only confirmed registrants included in attendance list  
* ✅ **No email/SMS notification sent** (replaced by in-app notification only)  
* ✅ Confirmation triggers in-app notification to registrant (if they're a member with login)

### **7.4 Tour Passenger Management**

* ✅ **Separate `tour_passengers` table** (independent from `members` table)  
* ✅ **Passenger record structure:**  
  * Passenger ID: `TP-000001` (auto-generated)  
  * Tour ID (foreign key to tours table)  
  * Full Name (required)  
  * Phone (required, unique per tour)  
  * Number of Passengers (required, default 1\)  
  * Receipt Image/Document (file upload, optional)  
  * Member ID (nullable \- only if linked to church member)  
  * Registration Type (Public, Internal)  
  * Registration Status (Pending, Confirmed, Cancelled)  
  * Registration Date (Ethiopian calendar, auto-filled)  
  * Registered By (user ID if internal, null if public registration)  
* ✅ **Smart member linking:**  
  * If passenger phone matches member phone → auto-link Member ID  
  * Member profile shows tour participation history (via linked passengers)  
  * Non-members remain as standalone passenger records  
* ✅ **Attendance recorded against passenger record** (not member directly)  
* ✅ If passenger is linked to member, attendance also visible in member timeline

### **7.5 Attendance from Registration**

* ✅ Tour Head can **auto-generate attendance session** from tour registrations  
* ✅ Click "Generate Attendance" button on tour detail page  
* ✅ All **confirmed** registrants automatically added to attendance list  
* ✅ **Attendance recorded at tour start time** (when tour begins)  
* ✅ **Attendance options:** Present, Not Present (only two options, simplified)

### **7.6 Call Button Rules**

* ✅ **Call button visible ONLY for passengers marked "Not Present"**  
* ✅ Call button opens device's phone dialer with passenger's phone number pre-filled  
* ✅ **No call logs stored in database** (simplified, privacy)  
* ✅ **Call history NOT included in tour attendance reports**  
* ✅ Tour Head can manually add notes if passenger contacted

### **7.7 Tour Modification Rules**

* ✅ Can update tour details if status is **"Draft"** or **"Published"**  
* ✅ Cannot change tour date if status is **"In Progress"** or **"Completed"**  
* ✅ If tour status changed to "Cancelled":  
  * All registrations automatically marked **"Cancelled"**  
  * In-app notification sent to all confirmed registrants  
* ✅ **No email/SMS notification sent** (in-app only)

---

## **8\. INVENTORY MANAGEMENT**

### **8.1 Inventory Item Rules**

* ✅ **Required fields:**  
  * Item Name  
  * Category (dropdown: Electronics, Furniture, Books, Supplies, Equipment, Other)  
  * Quantity (positive number)  
  * Unit (dropdown: pieces, boxes, sets, kg, liters, Other)  
* ✅ **Optional fields:**  
  * Item Code/SKU (unique identifier)  
  * Purchase Date (Ethiopian calendar)  
  * Purchase Price (for valuation)  
  * Supplier (vendor name)  
  * Storage Location (where item is kept)  
  * Notes (max 500 characters)  
* ✅ Item ID auto-generated: `INV-000001` (sequential)  
* ✅ **Item status (dropdown):**  
  * Active (in use/available)  
  * Damaged (needs repair)  
  * Lost (missing)  
  * Disposed (removed from inventory)  
* ✅ Cannot delete item if movement history exists (must mark as Disposed)  
* ✅ Soft delete only (mark as Disposed, preserve history)

### **8.2 Inventory Movement Rules**

* ✅ **Movement types:**  
  * **Stock In:** Purchase, Donation, Return  
  * **Stock Out:** Usage, Distribution, Loan, Loss  
* ✅ **Required fields:**  
  * Item ID (select from inventory list)  
  * Movement Type (dropdown)  
  * Quantity (positive number)  
  * Movement Date (Ethiopian calendar)  
  * Recorded By (auto-filled from user ID)  
* ✅ **Optional fields:**  
  * Recipient/Source (who received or provided item)  
  * Reference Number (invoice, receipt number)  
  * Notes (max 500 characters)  
* ✅ Cannot record negative quantity  
* ✅ **Cannot Stock Out if quantity exceeds available stock:**  
  * System shows warning  
  * Admin/Department Head can override with justification  
  * Override logged in audit trail  
* ✅ **Stock balance auto-calculated:**  
  * Current Stock \= Initial Quantity \+ Sum(Stock In) \- Sum(Stock Out)  
  * Displayed in real-time on item detail page

### **8.3 Inventory Analytics**

* ✅ **Key metrics displayed:**  
  * Total items by category  
  * Total inventory value (if purchase prices recorded)  
  * Most used items (highest Stock Out frequency)  
  * Items with low stock (quantity approaching zero)  
* ✅ **Export formats:** Excel, PDF  
* ✅ Export includes item list, quantities, values, movement summary

---

## **9\. WORSHIP & MEDIA CONTENT**

### **9.1 Worship Songs & Lyrics**

* ✅ **Required fields:**  
  * Song Title  
  * Lyrics (plain text or formatted HTML, rich text editor)  
  * Category (from category management table \- required)  
  * Sub-category (from sub-category management table \- required)  
* ✅ **Optional fields:**  
  * Audio File Upload (for offline playback, MP3/WAV format)  
  * Video File Upload (for offline viewing, MP4 format)  
  * Artist/Composer (name)  
* ✅ Song ID auto-generated: `SONG-000001`  
* ✅ **All songs are PUBLIC** (no visibility options, all songs displayed on public website)  
* ✅ **Category & Sub-category:**  
  * Must be created in separate category management table first  
  * Every song must have category (required)  
  * Every song must have sub-category (required)  
  * Examples: Category "Praise" → Sub-categories "Fast Praise", "Slow Praise"  
* ✅ **Public landing page:**  
  * Songs organized and displayed by categories  
  * Sub-categories shown under each category  
  * Users can filter by category/sub-category  
* ✅ Lyrics must be plain text or basic HTML (no scripts, no inline CSS)  
* ✅ Audio/Video files stored on server (not external URLs like YouTube)  
* ✅ File size limits: Audio 20MB, Video 50MB

### **9.2 Rehearsal Scheduling**

* ✅ **Required fields:**  
  * Rehearsal Date & Time (Ethiopian calendar)  
  * Location (physical location or virtual link)  
* ✅ **Optional fields:**  
  * Songs to Practice (multi-select from song library)  
  * Notes/Instructions (what to prepare, focus areas)  
* ✅ **Rehearsal status (dropdown):**  
  * Scheduled (upcoming)  
  * Completed (finished)  
  * Cancelled (won't happen)  
* ✅ Can schedule recurring rehearsals:  
  * Weekly (every Sunday, etc.)  
  * Bi-weekly (every 2 weeks)  
  * Monthly (first Sunday of month, etc.)  
* ✅ In-app notification sent to choir members 24 hours before rehearsal

### **9.3 Rehearsal Attendance**

* ✅ Recorded by Worship Monitor  
* ✅ **Attendance options:** Present, Absent, Excused, Late, Permission (same as class attendance)  
* ✅ **NOT linked to academic year** (ongoing activity, year-round)  
* ✅ **Attendance history used for:**  
  * Performance reviews  
  * Eligibility for special performances (soloists, featured singers)  
  * Scheduling reliability assessment  
  * Annual choir member evaluations

### **9.4 Media Gallery Rules**

* ✅ **Media types:** Photos, Videos  
* ✅ **Required fields:**  
  * Media Title (descriptive name)  
  * Category (from category management table \- required)  
* ✅ **Auto-filled fields:**  
  * Upload Date (from `created_at` timestamp)  
  * Uploaded By (from user ID)  
* ✅ **Optional fields:**  
  * Description (what's in the media)  
  * Sub-category (from sub-category management table)  
  * Event/Album (group media by event)  
  * Tags (comma-separated keywords for search)  
* ✅ **Category & Sub-category:**  
  * Must be created in separate category management table (same as songs)  
  * Every media item must have category (required)  
  * Sub-category optional but recommended  
  * Examples: Category "Events" → Sub-categories "Christmas", "Easter", "Youth Retreat"  
* ✅ **Visibility controlled by uploader:**  
  * Public (visible on public website)  
  * Members Only (requires login to view)  
  * Department Only (only department members can view)  
  * Can change visibility after upload  
* ✅ **File size limits:**  
  * Photos: Max 10MB per file  
  * Videos: Max 50MB per file  
* ✅ **Allowed formats:**  
  * Photos: JPG, PNG, GIF, WEBP  
  * Videos: MP4, MOV, AVI  
* ✅ **Public landing page:**  
  * Media displayed organized by category and sub-category  
  * Thumbnail view with lightbox for full view  
  * Can filter by event/album

### **9.5 Blog Posts & Announcements**

**Blog Posts:**

* ✅ **Fields:**  
  * Title (post headline)  
  * Content (rich text editor with formatting)  
  * Author (auto-filled from user)  
  * Publish Date (Ethiopian calendar, can schedule future)  
  * Featured Image (optional, thumbnail for post list)  
  * Tags (comma-separated, for categorization)  
* ✅ **Status (dropdown):**  
  * Draft (not visible)  
  * Scheduled (will publish on specified date)  
  * Published (visible on website)  
  * Archived (removed from public view)  
* ✅ Can schedule future publication (Publish Date in future)  
* ✅ Only AV Head can change status to "Published"

**Announcements:**

* ✅ Display on homepage (prominent placement)  
* ✅ **Fields:**  
  * Title (announcement headline)  
  * Content (rich text editor)  
  * Start Date (Ethiopian calendar, when to show)  
  * End Date (Ethiopian calendar, when to hide \- optional)  
  * Urgent Flag (boolean, highlights announcement)  
* ✅ Auto-hide after end date if provided  
* ✅ If no end date provided, remains visible indefinitely  
* ✅ Can mark as **"Urgent"** for red highlighting/pinned position

### **9.6 FAQ Management**

* ✅ **FAQ fields:**  
  * Question (what users ask)  
  * Answer (detailed response, rich text)  
  * Display Order (numeric, for sorting \- lower numbers first)  
  * Featured Flag (boolean, shows prominently on landing page)  
* ✅ FAQs displayed on landing page in order (sorted by Display Order field)  
* ✅ Can mark FAQ as **"Featured"** (larger display, top of list)  
* ✅ Only Admin and AV Head can create/edit FAQs  
* ✅ Soft delete only (mark as inactive, hide from public)

---

## **10\. EVENTS & FUNDRAISING**

### **10.1 Event Management**

* ✅ **Required fields:**  
  * Event Name  
  * Event Date & Time (Ethiopian calendar)  
  * Location (physical address or virtual link)  
* ✅ **Optional fields:**  
  * Description (event details, agenda)  
  * Featured Image (event poster/thumbnail)  
  * Registration Required (boolean \- Yes/No)  
  * Maximum Capacity (if limited seating)  
  * Registration Deadline (Ethiopian calendar)  
* ✅ **Event status (dropdown):**  
  * Draft (not visible to public)  
  * Published (visible on public calendar)  
  * Full (capacity reached, no more registrations)  
  * Ongoing (event is happening)  
  * Completed (event finished)  
  * Cancelled (event cancelled)  
* ✅ Events display on **public calendar** (filterable by month/category)  
* ✅ Can create **recurring events:**  
  * Weekly (every Sunday service, etc.)  
  * Monthly (first Sunday of month, etc.)  
  * Custom recurrence pattern

### **10.2 Fundraising Campaigns**

* ✅ **DISPLAY-ONLY** (no online payment processing)  
* ✅ **Required fields:**  
  * Campaign Name  
  * Target Amount (financial goal)  
  * Start Date (Ethiopian calendar)  
* ✅ **Optional fields:**  
  * End Date (Ethiopian calendar)  
  * Description (campaign purpose, how funds will be used)  
  * Featured Image (campaign poster)  
  * Campaign Category (Building, Missionary, Charity, General)  
  * Bank Account Information (for donations, displayed on website)  
  * Amount Manually Added Field (Admin updates "Total Raised")  
* ✅ **Campaign status (dropdown):**  
  * Draft (not visible)  
  * Active (visible and accepting donations)  
  * Completed (goal reached or campaign ended)  
  * Cancelled (campaign stopped)  
* ✅ **Display on public website:**  
  * Total Raised (manually entered by Admin)  
  * Percentage of Target (auto-calculated: Total Raised / Target × 100\)  
  * Progress Bar (visual representation)  
  * Days Remaining (if End Date provided: End Date \- Today)  
  * Bank Account Details (if provided, formatted for display)  
* ✅ **Admin manually updates "Total Raised":**  
  * Admin enters amount received offline (cash, bank transfers, etc.)  
  * System recalculates percentage and updates progress bar  
  * Update logged in audit trail

---

## **11\. ARCHIVES & DOCUMENTS**

### **11.1 Document Upload Rules**

* ✅ **Documents scoped to department** (each dept has own archive folder)  
* ✅ **Required fields:**  
  * Document Title (descriptive name)  
  * File Upload (actual file)  
* ✅ **Auto-filled fields:**  
  * Uploaded By (from user ID)  
  * Upload Date (from `created_at` timestamp)  
* ✅ **Optional fields:**  
  * Description (what's in the document)  
  * Tags (comma-separated keywords, NO categories)  
  * Document Date (original date of document \- Ethiopian calendar)  
* ✅ **Visibility set on upload (can be changed later):**  
  * Public (anyone can download)  
  * Members Only (requires login)  
  * Department Only (only department members can access)  
* ✅ **Allowed formats:**  
  * PDF, DOCX, XLSX, PPTX, JPG, PNG  
* ✅ **No file size limit enforced** (server/hosting limit applies naturally)  
* ✅ Documents searchable by: Title, Description, Tags, Date Range  
* ✅ **Department Heads can soft delete** own department documents  
* ✅ **Admin can view all** department documents (read-only, cannot delete others' documents)  
* ✅ **Users can change visibility after upload** (edit document to change visibility setting)

### **11.2 Library Resources (Public)**

* ✅ Educational PDFs and documents for public download  
* ✅ **Category & Sub-category:**  
  * Must be created in separate category management table  
  * Every resource must have category (required)  
  * Sub-category optional  
  * Examples: Category "Sunday School" → Sub-categories "Kids", "Youth", "Adults"  
* ✅ **Public download allowed** (no authentication required)  
* ✅ Upload restricted to Education Department Head  
* ✅ Can mark resources as **"Featured"** (highlighted on library page)  
* ✅ Displayed on public landing page organized by category

---

## **12\. SYSTEM SETTINGS & AUDIT**

### **12.1 System Settings (Superadmin Only)**

* ✅ **Global settings (configurable):**  
  * Church Name (English version)  
  * Church Name (Amharic version)  
  * Church Logo (uploaded image file)  
  * Default Language (Amharic or English)  
  * Contact Email  
  * Contact Phone  
  * Church Physical Address  
* ✅ Only **Superadmin** can modify these settings  
* ✅ All setting changes logged in audit trail with:  
  * Who changed  
  * When changed  
  * Old value → New value

### **12.2 Audit Log Rules**

* ✅ **TWO-TIER AUDIT LOG RETENTION:** **Tier 1 \- Security/System Logs (30-day retention):**  
  * User login attempts (successful and failed)  
  * Session creation and termination  
  * Role changes  
  * System access attempts  
  * Permission overrides  
  * Auto-purge after 30 days  
* **Tier 2 \- Financial & Member-Change Audit Logs (PERMANENT retention):**  
  * All contribution transactions (create, update, delete)  
  * All donation transactions  
  * Member profile modifications (any field change)  
  * Member status changes (Draft→Active, etc.)  
  * Group assignments  
  * Academic year activation/deactivation  
  * System setting changes  
  * **Never auto-purged, retained permanently**  
* ✅ **Audit log fields (both tiers):**  
  * User ID (who performed action)  
  * Action Type (create, update, delete, login, logout, etc.)  
  * Entity Type (member, contribution, user, role, etc.)  
  * Entity ID (which specific record)  
  * Old Value (JSON format, for updates)  
  * New Value (JSON format, for updates)  
  * Timestamp (when action performed, Ethiopian calendar display)  
  * IP Address (where action originated)  
* ✅ Audit logs **cannot be edited or deleted** (immutable until auto-purge for Tier 1\)  
* ✅ Only Superadmin and Admin can view audit logs  
* ✅ Audit log exports available for compliance (Superadmin only)

### **12.3 Error Logging**

* ✅ All application errors automatically logged (Laravel exception handler)  
* ✅ **Error log fields:**  
  * Error Type (exception class name)  
  * Error Message (detailed error text)  
  * Stack Trace (full error trace for debugging)  
  * User ID (if authenticated user triggered error)  
  * URL/Route (where error occurred)  
  * Timestamp (when error occurred)  
  * User Agent (browser/device info)  
  * HTTP Method (GET, POST, etc.)  
  * Request Data (sanitized, no passwords)  
* ✅ Only **Superadmin** can view error logs  
* ✅ **Retention: 2 months** (auto-purge older logs)  
* ✅ Critical errors trigger immediate notification to Superadmin (in-app)

### **12.4 System Health Monitoring**

* ✅ **Metrics tracked (real-time):**  
  * Server uptime  
  * Database response time (average query time)  
  * Storage usage (% of disk space used)  
  * Active user sessions (current logged-in users)  
  * Failed login attempts (last 24 hours)  
  * Error rate (errors per hour)  
* ✅ Only **Superadmin** can view system health dashboard  
* ✅ **No export allowed** for system health data (live dashboard only)  
* ✅ **Alerts triggered automatically if:**  
  * Storage usage \>40% (warning threshold)  
  * Error rate \>10 errors/hour  
  * Database response time \>2 seconds  
* ✅ Alerts sent via in-app notification to Superadmin

---

## **13\. DATA RETENTION & PRIVACY**

### **13.1 Data Retention Policy**

| Data Type | Retention Period | Deletion Policy |
| ----- | ----- | ----- |
| **Security/System Audit Logs** | 30 days | Auto-purge after retention period |
| **Financial/Member Audit Logs** | Permanent | Never deleted |
| **Financial Records (Contributions/Donations)** | Permanent | Never deleted |
| **Member Profiles (Active/Member status)** | Permanent | Soft delete only |
| **Member Profiles (Former status)** | 7 years | Soft delete only |
| **Attendance Records** | Permanent | Never deleted |
| **Academic Year Data** | Permanent | Never deleted (archived when inactive) |
| **Media Files** | 5 years | Can soft delete after archive |
| **Session Logs** | 90 days | Auto-purge |
| **Error Logs** | 2 months | Auto-purge |
| **Contact Form Submissions** | 2 years | Can soft delete manually |
| **Read Notifications** | 90 days | Auto-purge |
| **Unread Notifications** | Indefinite | Retained until read |

### **13.2 Privacy & Data Protection**

* ✅ **Personal data collected:**  
  * All fields listed in Section 3.1 (Member Profile Rules)  
  * Phone, Email, Address, Date of Birth, Photo  
  * Parent/Guardian information  
  * Family details, occupation, marital status  
* ✅ **Data usage:**  
  * Church operations only  
  * Not shared with third parties  
  * Not used for marketing  
  * Not sold or monetized  
* ✅ **Data access:**  
  * Members can request copy of own data (contact Admin)  
  * Members can request data deletion (subject to retention policy)  
  * Data deletion requests reviewed by Admin and Superadmin  
* ✅ **Public visibility rules:**  
  * Member names visible in class lists (internal users only)  
  * Member phone/email **NOT displayed publicly**  
  * Contribution amounts **NOT displayed publicly**  
  * Attendance records **NOT displayed publicly**  
* ✅ **Parent/Guardian consent:**  
  * Required for minors (\<18 years old) to be photographed/recorded  
  * Consent flag stored in member profile (boolean field)  
  * Photos of unconsented minors must not be published  
* ✅ **All deletions are SOFT DELETES** (data preserved but hidden)

---

## **14\. MULTI-LANGUAGE SUPPORT**

### **14.1 Supported Languages**

* ✅ **Primary languages:**  
  * **Amharic (አማርኛ)** \- Default language  
  * **English** \- Secondary language  
* ✅ **Fallback language:** English (if Amharic translation missing)

### **14.2 Language Switching**

* ✅ Users can switch language from **website header** (top navigation bar)  
* ✅ Language preference stored differently based on user type:  
  * **Public visitors:** Browser cookie (30-day expiry)  
  * **Staff users:** User profile table (permanent, linked to user account)  
* ✅ Language selection applies to:  
  * Website UI (buttons, menus, labels, placeholders)  
  * Blog posts (if translated version exists for that post)  
  * Announcements (if translated version exists)  
  * Error messages (validation errors, system messages)  
  * Email templates (future feature)

### **14.3 Content Translation**

* ✅ **Static content (UI labels):**  
  * Translated in Laravel language files  
  * Translation keys in code (e.g., `__('auth.login')`)  
  * Falls back to English if Amharic translation missing  
* ✅ **Dynamic content (blog posts, announcements):**  
  * Can have multiple language versions (same content, different language)  
  * Language toggle shown if translations exist  
  * If translation missing, show default language version with indicator  
  * Language indicator shown (e.g., "EN", "አማ")  
* ✅ **User-generated content:**  
  * Members enter content in their preferred language  
  * No automatic translation (user responsibility)

---

## **15\. MOBILE & OFFLINE CAPABILITIES**

### **15.1 PWA (Progressive Web App)**

* ✅ **"Install App" prompt triggers:**  
  * After user's 3rd visit to website  
  * Shown as banner at top of screen  
  * Can be dismissed (won't show again for 7 days)  
* ✅ PWA icon added to device home screen (acts like native app)  
* ✅ **Works on:**  
  * Android (Chrome, Firefox, Edge)  
  * iOS (Safari 11.3+)  
  * Desktop (Chrome, Edge, Opera)  
* ✅ **Offline mode supported for:**  
  * Attendance marking (Education Monitor, Worship Monitor)  
  * Song lyrics viewing (read-only)  
  * Media downloads (manually cached for offline viewing)  
  * Cached pages (home, about, contact)

### **15.2 Offline Attendance**

* ✅ Attendance sessions cached locally when user is online  
* ✅ Can mark attendance when offline (stored in browser IndexedDB)  
* ✅ **Auto-sync triggers:**  
  * When internet connection restored  
  * When user navigates to online page  
  * Background sync (if supported by browser)  
* ✅ **Sync queue priority:** Attendance records \> Other data types  
* ✅ **Sync retry logic:**  
  * Retries up to 3 times if sync fails  
  * 30-second delay between retries  
  * If still fails after 3 retries, shows error notification  
* ✅ User notified of sync status via in-app notification:  
  * "Syncing attendance..." (during sync)  
  * "Attendance synced successfully" (on success)  
  * "Sync failed, will retry" (on failure)

### **15.3 Offline Content**

* ✅ **Always automatically cached:**  
  * User profile (current logged-in user)  
  * Assigned classes (for Education Monitor)  
  * Current attendance sessions (open sessions only)  
  * Static pages (home, about, contact, FAQs)  
* ✅ **Manually cacheable (user chooses to download):**  
  * Song lyrics (for offline viewing)  
  * Documents/PDFs (for offline reading)  
  * Media files (photos/videos for offline viewing)  
  * Library resources  
* ✅ **Cache storage limit:**  
  * Browser-dependent (typically 50-100MB)  
  * System warns when approaching limit  
  * User can view cache usage in settings  
* ✅ **Users can clear cache:**  
  * Settings → Clear Offline Data  
  * Confirmation required before clearing  
  * Clears all cached content except static pages

---

## **16\. BACKUP & DISASTER RECOVERY**

### **16.1 Backup Rules**

* ✅ **Manual backup only** (initiated by Superadmin)  
* ✅ **No automated scheduled backups** (manual trigger required)  
* ✅ **Backup stored locally only** (no cloud storage, server disk only)  
* ✅ **Backup includes:**  
  * Complete database dump (all tables, all data)  
  * All uploaded files (media, documents, images)  
  * System configuration files (`.env`, config files)  
  * Application code (current deployed version)  
* ✅ **Backup retention:**  
  * Last 30 backups kept (rolling window)  
  * Older backups automatically deleted  
  * Each backup labeled with timestamp (Ethiopian calendar)  
  * Backup file size displayed for each  
* ✅ **Backup logs:**  
  * Who initiated backup (Superadmin user ID)  
  * When backup created  
  * Backup file size  
  * Backup status (Success/Failed)

### **16.2 Restore Rules**

* ✅ Only **Superadmin** can initiate restore  
* ✅ **Confirmation required before restore:**  
  * System shows warning: "This will overwrite all current data"  
  * Superadmin must type "CONFIRM RESTORE" to proceed  
  * Cannot be undone  
* ✅ Restore creates audit log entry:  
  * Who initiated restore  
  * When restored  
  * Which backup file used  
  * Restore status (Success/Failed)  
* ✅ **Restore options:**  
  * **Full restore:** Overwrites entire database and all files  
  * **Selective restore:** Restore specific tables only (advanced)  
  * **Database only:** Restore database, keep current uploaded files  
  * **Files only:** Restore uploaded files, keep current database  
* ✅ **System downtime required during restore:**  
  * Maintenance mode enabled (users see "Under Maintenance" page)  
  * All users automatically logged out  
  * Users cannot log in during restore  
  * Maintenance mode disabled after restore completes

---

## **17\. ETHIOPIAN CALENDAR IMPLEMENTATION**

### **17.1 Date Storage & Display**

* ✅ **All dates stored as Gregorian internally** (MySQL `DATE` and `DATETIME` columns)  
  * Ensures database compatibility  
  * Allows standard SQL date functions  
  * Supports date arithmetic and comparisons  
* ✅ **All dates displayed as Ethiopian calendar** throughout entire UI  
  * Admin panel: All date displays and inputs use Ethiopian calendar  
  * Public website: All dates shown in Ethiopian format  
  * Reports: Dates formatted as Ethiopian  
  * Exports: Can choose Ethiopian or Gregorian format  
* ✅ **Use Laravel package:** `andegna/ethiopian-calendar` or `geezorg/ethiopian-calendar`  
  * Handles conversion automatically  
  * Supports leap years  
  * Validates Ethiopian dates  
* ✅ **Ethiopian date pickers** used throughout:  
  * Admin panel forms  
  * Public website registration forms  
  * Report filters  
  * All date input fields

### **17.2 Ethiopian Calendar Specifics**

* ✅ **Ethiopian months (13 total):**  
  * Meskerem (መስከረም) \- 30 days  
  * Tikimt (ጥቅምት) \- 30 days  
  * Hidar (ኅዳር) \- 30 days  
  * Tahsas (ታኅሣሥ) \- 30 days  
  * Tir (ጥር) \- 30 days  
  * Yekatit (የካቲት) \- 30 days  
  * Megabit (መጋቢት) \- 30 days  
  * Miazia (ሚያዝያ) \- 30 days  
  * Ginbot (ግንቦት) \- 30 days  
  * Sene (ሰኔ) \- 30 days  
  * Hamle (ሐምሌ) \- 30 days  
  * Nehasse (ነሐሴ) \- 30 days  
  * Pagume (ጳጉሜን) \- 5 or 6 days (leap year)  
* ✅ **Pagume EXCLUDED ONLY from:**  
  * **Contribution month selection dropdowns** (only 12 months shown)  
  * Reasoning: Contributions tracked monthly, Pagume too short for monthly tracking  
* ✅ **Pagume ALLOWED in:**  
  * General date selections (birthdays, anniversaries, etc.)  
  * Tour dates (tours can happen in Pagume)  
  * Event dates (events can happen in Pagume)  
  * Attendance dates (classes can meet in Pagume)  
  * All other calendar date selections  
  * Ethiopian calendar widget always shows all 13 months  
* ✅ **Academic year naming:**  
  * Decided by Education Department Head  
  * Can use Ethiopian year: "2017 E.C."  
  * OR Gregorian year: "2024/2025"  
  * OR Custom format: "Academic Year 2017"  
* ✅ **Contribution month naming:**  
  * Decided by person creating contribution amount settings  
  * Can use Ethiopian month names: "Meskerem", "Tikimt", etc.  
  * OR Gregorian month names: "September", "October", etc.  
  * Stored in database as month name (not number, for flexibility)

### **17.3 Date Validation**

* ✅ System validates Ethiopian dates before storage:  
  * Ensures month is 1-13 (or 1-12 for contribution months)  
  * Ensures day is valid for that month:  
    * Months 1-12: Day must be 1-30  
    * Pagume: Day must be 1-5 (or 1-6 in leap year)  
  * Rejects invalid dates (e.g., Meskerem 31, Pagume 7\)  
* ✅ Invalid dates show user-friendly error message in Amharic/English  
* ✅ System automatically converts validated Ethiopian date to Gregorian for storage  
* ✅ **Leap year handling:**  
  * Ethiopian leap year calculation automatic  
  * Every 4 years (except divisible by 100, unless also divisible by 400\)  
  * Pagume has 6 days in leap year, 5 days otherwise  
  * System adjusts Pagume day validation based on leap year

---

## **18\. IN-APP NOTIFICATIONS**

### **18.1 Notification Delivery**

* ✅ **All notifications are IN-APP ONLY** (no email, no SMS)  
* ✅ **Two notification delivery methods:**  
  1. **Filament Admin Panel Notifications:**  
     * Bell icon in top navigation bar  
     * Shows unread count badge  
     * Dropdown list of recent notifications  
     * Click to mark as read and navigate to relevant page  
     * For staff users only (logged into admin panel)  
  2. **PWA Push Notifications:**  
     * Browser/device push notifications  
     * Only if user installed PWA on device  
     * Requires user permission (browser prompts on first visit)  
     * Shows even when app not open  
     * For mobile users with PWA installed

### **18.2 Notification Triggers**

**Tour notifications:**

* Tour Head notified when new public registration received  
* Registrant notified when registration confirmed  
* Registrant notified when registration cancelled  
* Tour Head notified when tour is full (capacity reached)

**Upcoming event reminders:**

* Rehearsal reminders sent 24 hours before rehearsal time  
* Class session reminders (optional, for teachers)  
* Tour departure reminders sent 12 hours before tour

**Financial notifications:**

* Members notified of outstanding contributions (monthly)  
* Finance Head notified when monthly collection due  
* Department Heads notified of budget alerts (future feature)

**Admin announcements:**

* System-wide announcements from Admin/Superadmin  
* Department-specific announcements from Department Heads  
* Urgent announcements (red badge, persistent)

**Attendance alerts:**

* Education Monitor notified of unlocked sessions approaching 30-day deadline (3 days before auto-lock)  
* Education Monitor notified of sync conflicts  
* Teachers notified when assigned to new class

### **18.3 Notification Preferences**

* ✅ **Users CANNOT control which notifications they receive**  
* ✅ All or nothing approach (cannot selectively disable notification types)  
* ✅ **Only global controls available:**  
  * Can disable PWA push notifications at OS/browser level  
  * Cannot disable Filament admin panel notifications (always visible)  
* ✅ Reasoning: Ensures critical notifications (attendance deadlines, financial alerts) always reach users

### **18.4 Notification Storage**

* ✅ Notifications stored in `notifications` table (Laravel built-in)  
* ✅ **Notification record fields:**  
  * Notification ID (UUID)  
  * User ID (recipient)  
  * Notification Type (tour\_registration, rehearsal\_reminder, etc.)  
  * Title (short headline)  
  * Message (detailed notification text)  
  * Read Status (boolean: read/unread)  
  * Timestamp (when notification created, Ethiopian calendar display)  
  * Action URL (optional link to related page, e.g., tour detail page)  
  * Data (JSON, stores context like tour\_id, passenger\_name, etc.)  
* ✅ **Notification lifecycle:**  
  * **Unread notifications:** Retained indefinitely until read  
  * **Read notifications:** Auto-purged after 90 days  
* ✅ Notifications marked as "read" when:  
  * User clicks notification in Filament dropdown  
  * User navigates to action URL  
  * User manually marks as read  
* ✅ Users can view notification history (last 90 days of read \+ all unread)

---

## **19\. REPORTS & EXPORTS (NO SAVED FILTERS)**

### **19.1 Reporting Rules**

* ✅ **Predefined reports only** (system-defined, not user-created)  
* ✅ **NO saved filters feature** (removed from requirements)  
* ✅ **NO custom query builders** (too complex for MVP)  
* ✅ **Session-based filters only:**  
  * Users apply filters to reports in current session  
  * Filters not persisted/saved  
  * Filters reset when user logs out or navigates away  
  * Must reapply filters each time viewing report

**Available Predefined Reports:**

* Member List Report (filterable by group, status, department)  
* Contribution Report (filterable by academic year, group, class, date range, month)  
* Donation Report (filterable by date range, donation type)  
* Attendance Report (filterable by academic year, class, date range, student)  
* Teacher Attendance Report (filterable by academic year, teacher, class)  
* Rehearsal Attendance Report (filterable by date range, member)  
* Tour Report (filterable by date range, tour, registration status)  
* Inventory Report (filterable by category, status, location)  
* Beneficiary Report (filterable by date range, aid type, beneficiary)  
* Financial Statement (filterable by date range, academic year)  
* Audit Log Report (filterable by user, action type, entity type, date range)

### **19.2 Export Availability**

* ✅ **Export available for ALL tables and reports**  
* ✅ **Export formats:**  
  * **Excel (.xlsx):** Formatted, with formulas and styling  
  * **PDF:** Print-ready, with church logo and headers  
  * **CSV:** Raw data, for external processing/import  
* ✅ **Tables with export functionality:**  
  * Members list  
  * Contributions report  
  * Donations report  
  * Attendance report (class, tour, rehearsal)  
  * Financial statements  
  * Beneficiary list  
  * Inventory list  
  * Tour passengers list  
  * Department archives/documents list  
  * Audit logs  
  * All predefined reports

### **19.3 Export Features**

* ✅ **Export respects user's department access** (filtered data only exported)  
* ✅ **Export includes:**  
  * **Column headers** (in user's selected language \- Amharic or English)  
  * **Filtered/sorted data** (exactly what user sees on screen)  
  * **Generated date/time** (Ethiopian calendar, at top of export)  
  * **Generated by** (user's name and role)  
  * **Applied filters summary** (which filters were used, displayed in header section)  
* ✅ **Excel export features:**  
  * Formatted cells (dates as dates, currency as currency)  
  * Auto-width columns (readable without manual adjustment)  
  * Frozen header row (first row stays visible when scrolling)  
  * Conditional formatting (optional, e.g., highlight outstanding contributions in red)  
  * Cell borders and grid lines  
  * Church logo in header (if applicable)  
* ✅ **PDF export features:**  
  * Church logo in header (top-left corner)  
  * Report title (centered, bold)  
  * Generated date and user (subtitle, below title)  
  * Page numbers (footer, centered: "Page X of Y")  
  * Landscape orientation for wide tables (auto-detect)  
  * Portrait orientation for narrow tables  
  * Proper page breaks (doesn't cut table rows in half)

### **19.4 Export Logging**

* ✅ **All exports logged in audit trail:**  
  * Who exported (user ID and name)  
  * What data (table name, report name)  
  * Applied filters (JSON, which filters user selected)  
  * When exported (timestamp, Ethiopian calendar)  
  * Export format (Excel/PDF/CSV)  
  * Number of records exported (row count)  
  * Export file size (MB)  
* ✅ **Manual off-server export allowed:**  
  * Superadmin can export for external compliance/backup  
  * Full system data export (all tables, all records)  
  * No filters applied (complete dump)  
  * Logged separately as "System Export" in audit trail  
* ✅ **Export logs retention:** 1 year (auto-purge older logs)  
* ✅ Export logs visible to: Department Heads (own exports), Admin (all exports), Superadmin (all exports)

---

## **20\. "OTHERS" OPTION MANAGEMENT**

### **20.1 "Others" Option Behavior**

* ✅ **All dropdown/select fields with predefined options include "Other" at bottom**  
* ✅ **Examples of fields with "Other" option:**  
  * Payment Method: Cash, Check, Mobile Money, Bank Transfer, **Other**  
  * Relationship: Father, Mother, Guardian, GrandFather, GrandMother, Uncle, Brother, Aunt, Sister, **Other**  
  * Employment Status: Hired, Not Hired, Private Sector, **Other**  
  * Donation Type: General Fund, Building Fund, Missionary Support, Charity/Aid, **Other**  
  * Removal Reason: Moved Away, Transferred, Graduated, **Other**  
  * Category types (songs, media, library)  
  * Inventory categories  
  * Any other dropdown with limited predefined options

### **20.2 Adding Custom Options (User Flow)**

1. User selects **"Other"** from dropdown  
2. Text input field appears below dropdown (inline, same form)  
3. User enters custom value (e.g., "Cryptocurrency" for Payment Method)  
4. User submits form (Save button)  
5. System validates custom value:  
   * Not empty  
   * Under 100 characters  
   * No special characters (alphanumeric only, spaces allowed)  
6. System saves record with custom value  
7. **Custom value automatically added to dropdown options for ALL users**  
8. Custom value flagged as **"pending"** (requires Admin approval for permanence)  
9. Custom value visible immediately in dropdown (before approval)

### **20.3 Custom Option Management (Admin)**

* ✅ **Admin views all custom options** with "pending" flag indicator  
* ✅ Admin dashboard shows: "X pending custom options require review"  
* ✅ **Admin can perform actions:** **1\. Approve Pending Option:**  
  * Removes "pending" flag  
  * Option becomes permanent in dropdown  
  * Cannot be removed by users (only Admin can delete approved options)  
* **2\. Reject Pending Option:**  
  * Removes option from dropdown entirely  
  * Any records using this option keep the value (data not deleted)  
  * Option no longer available for new records  
* **3\. Merge Duplicate Options:**  
  * Admin identifies duplicates (e.g., "Mobile Money" and "Mobile Payment")  
  * Selects options to merge and target option to keep  
  * System updates all records using merged options to use target option  
  * Merged options removed from dropdown  
  * Works for both approved and pending options  
* **4\. Delete Unused Approved Options:**  
  * Only approved options (not pending)  
  * Only if zero records use this option  
  * Confirmation required ("Are you sure? This cannot be undone.")  
* **5\. Reorder Dropdown Options:**  
  * Drag-and-drop interface to change display order  
  * Affects all users immediately  
  * Typically alphabetical order, but Admin can override  
  * "Other" option always remains at bottom (cannot be reordered)  
* ✅ **Custom option display rules:**  
  * Pending options appear at bottom of dropdown (before "Other")  
  * Pending options labeled with "(Pending)" suffix in dropdown  
  * Approved options appear in normal alphabetical/sorted order  
  * Original predefined options always appear first (before custom options)  
  * "Other" option always appears last (cannot be moved)

### **20.4 Custom Options Storage**

* ✅ Stored in `custom_options` table:  
  * Option ID (auto-increment)  
  * Field Name (e.g., "payment\_method", "relationship")  
  * Option Value (e.g., "Cryptocurrency", "Step-Father")  
  * Status (pending, approved, rejected)  
  * Added By (user ID who first added it)  
  * Added Date (when first added)  
  * Approved By (Admin user ID who approved, nullable)  
  * Approved Date (when approved, nullable)  
  * Usage Count (how many records use this option, auto-calculated)  
  * Display Order (for sorting in dropdown, nullable means alphabetical)

---

## **21\. TEACHER MANAGEMENT**

### **21.1 Teacher Profile Rules**

* ✅ **Separate `teachers` table** (independent from `members` table)  
* ✅ **Two types of teachers:** **1\. External Teachers (Non-Members):**  
  * Not church members  
  * Registered only as teachers (for teaching purposes)  
  * Minimal data required (name, phone only)  
  * Cannot access member portal (no login)  
  * Not tracked for contributions, attendance as member  
* **2\. Member Teachers (Existing Members):**  
  * Existing church members who also teach  
  * Linked to member record via `member_id` foreign key  
  * Retain all member functionality PLUS teaching assignments  
  * Can be tracked for contributions, attendance, etc. as member  
  * Teaching assignments separate from member profile

### **21.2 External Teacher Registration**

* ✅ **Minimal required fields for external teachers:**  
  * Full Name (required)  
  * Phone Number (required, unique across all teachers)  
* ✅ **Optional fields for external teachers:**   
  * Qualifications/Experience (text area, notes about teaching background)  
* ✅ **External teachers DO NOT have:**  
  * Member ID (not linked to members table)  
  * Member status  
  * Contribution requirements  
  * Parent/guardian information  
  * Family details  
  * Spiritual information  
  * Group assignments

### **21.3 Member as Teacher**

* ✅ **When assigning existing member as teacher:**  
  * Select member from member dropdown/search  
  * System creates teacher record with `member_id` foreign key  
  * Teacher profile auto-populated from member data (name, phone)  
  * Changes to member profile automatically reflect in teacher profile (name, phone sync)  
* ✅ **Member teachers retain all member functionality:**  
  * Can be enrolled as student (if applicable)  
  * Tracked for contributions  
  * Tracked for attendance as member  
  * Can belong to groups  
  * All member features remain active  
* ✅ **PLUS teaching assignments:**  
  * Can be assigned to classes as teacher  
  * Teacher attendance tracked separately from member attendance  
  * Can teach multiple classes/subjects

### **21.4 Teacher Assignment Rules**

* ✅ **Teacher assigned to:** Class \+ Subject \+ Academic Year  
* ✅ **Teacher assignment record includes:**  
  * Teacher ID (foreign key)  
  * Class ID (foreign key)  
  * Subject ID (foreign key)  
  * Academic Year ID (foreign key)  
  * `assigned_date` (when assignment was made)  
  * `effective_from` (start date of teaching responsibility)  
  * `effective_to` (optional end date, set when removed)  
  * Assignment Status (dropdown: Active, Inactive, On Leave)  
  * Created By (user ID who made assignment)  
* ✅ **One teacher can teach:**  
  * Multiple subjects in same class (e.g., Bible Study \+ Church History in Grade 3\)  
  * Same subject in multiple classes (e.g., Bible Study in Grade 1, 2, and 3\)  
  * Multiple subjects across multiple classes (any combination)  
* ✅ **One class can have:**  
  * Multiple teachers (team teaching)  
  * Different teachers for different subjects  
* ✅ **Teacher assignments visible in:**  
  * Attendance sheets (shows which teacher responsible for session)  
  * Academic year summary reports  
  * Subject-wise teacher lists  
  * Class rosters (for parents/members to see)

### **21.5 Teacher Attendance Rules**

* ✅ **Teacher attendance recorded PER CLASS SESSION** (not per day overall)  
  * Each class session has teacher attendance (separate from student attendance)  
  * If teacher teaches 3 classes in one day, 3 separate attendance records  
* ✅ **Marked by:** Education Monitor ONLY (teachers cannot mark own attendance)  
* ✅ **Attendance options:** Present, Absent, Late, Permission  
  * Same options as student attendance for consistency  
  * Late threshold: \>15 minutes after session start time  
* ✅ **If teacher absent:**  
  * Session marked as **"Cancelled"** if no substitute available  
    * Students not marked (session didn't happen)  
    * Noted in session notes  
  * OR Session marked as **"Substitute Assigned"** if another teacher covers  
    * Substitute teacher's name recorded in session notes  
    * Students still marked as present/absent  
    * Original teacher marked absent, substitute marked present  
* ✅ **Teacher attendance rate tracked:**  
  * Calculated as: `(Present sessions / Total assigned sessions) × 100`  
  * Total assigned sessions \= all sessions for classes/subjects teacher assigned to  
  * Used for performance reviews  
  * **Visible to Education Department Head only** (privacy, not public)  
  * Can be filtered by academic year, class, subject

### **21.6 Teacher Table Structure**

sql

teachers table:

\- teacher\_id (PK, auto-increment)

\- member\_id (FK to members table, nullable \- only if teacher is also member)

\- full\_name (required, VARCHAR 255)

\- phone (required, unique, VARCHAR 20) 

\- qualifications (nullable, TEXT \- notes about teaching experience)

\- status (ENUM: Active, Inactive, Former \- default Active)

\- created\_at (timestamp)

\- updated\_at (timestamp)

\- deleted\_at (nullable, for soft delete)

teacher\_assignments table:

\- assignment\_id (PK, auto-increment)

\- teacher\_id (FK to teachers table)

\- class\_id (FK to classes table)

\- subject\_id (FK to subjects table)

\- academic\_year\_id (FK to academic\_years table)

\- assigned\_date (date, when assignment was made)

\- effective\_from (date, start of teaching)

\- effective\_to (nullable, date, end of teaching)

\- assignment\_status (ENUM: Active, Inactive, On Leave \- default Active)

\- created\_by (FK to users table, who made assignment)

\- created\_at (timestamp)

\- updated\_at (timestamp)

teacher\_attendance table:

\- attendance\_id (PK, auto-increment)

\- teacher\_id (FK to teachers table)

\- class\_session\_id (FK to attendance\_sessions table)

\- attendance\_status (ENUM: Present, Absent, Late, Permission)

\- marked\_by (FK to users table \- Education Monitor)

\- marked\_at (timestamp)

\- notes (nullable, TEXT \- for absences, substitutions, etc.)

\- created\_at (timestamp)

\- updated\_at (timestamp)

### **21.7 Teacher Search & Management**

* ✅ **Search teachers by:**  
  * Name (full name search)  
  * Phone number (exact or partial match)  
  * Subject expertise (subjects they teach)  
  * Status (Active/Inactive/Former)  
  * Class assignment (which classes they teach)  
  * Member status (is member or external teacher)  
* ✅ **Education Department Head can:**  
  * Add new external teachers (name \+ phone minimal)  
  * Assign existing members as teachers (link member\_id)  
  * Update teacher assignments (classes/subjects)  
  * Change teacher status (Active/Inactive/On Leave/Former)  
  * View teacher attendance reports (attendance rate, patterns)  
  * View teacher assignment history (all classes taught over years)  
* ✅ **Cannot delete teacher if:**  
  * Has teaching assignment history (current or past)  
  * Has attendance records (current or historical)  
* ✅ **Soft delete only:**  
  * Status changed to "Former"  
  * Record preserved in database (hidden from active views)  
  * Assignment history and attendance history retained  
  * Can be reactivated if teacher returns

### **21.8 Integration with Existing Systems**

* ✅ **Teacher attendance affects class sessions:**  
  * Teacher marked "Present" → Class proceeds normally, students can be marked  
  * Teacher marked "Absent" with no substitute → Session status "Cancelled", students not marked  
  * Teacher marked "Absent" with substitute → Session continues, students marked, substitute noted  
* ✅ **Teacher assignments appear in:**  
  * Class rosters (for members/parents to see who teaches)  
  * Attendance sheets (shows responsible teacher for session)  
  * Academic reports (teacher workload, classes taught)  
  * Student progress reports (which teachers taught the student)  
* ✅ **No teacher login access:**  
  * External teachers CANNOT log into admin portal  
  * Member teachers can log in only if they have staff role (e.g., if member is also Department Head)  
  * Teaching role does not grant admin portal access  
* ✅ **No financial tracking for teachers:**  
  * Teachers are NOT tracked for contributions (separate from member contributions)  
  * No salary/payment tracking in MVP (future feature)  
  * External teachers completely independent from financial module

---

## **22\. CHARITY & BENEFICIARIES**

### **22.1 Beneficiary Management**

* ✅ **Required fields:**  
  * Full Name  
  * Phone (unique)  
  * Address (physical location)  
  * Beneficiary Type (dropdown: Individual, Family, Organization)  
  * Need Category (dropdown: Food, Medical, Education, Housing, Other)  
* ✅ **Optional fields:**  
  * Email  
  * ID Number (national ID or other identifier)  
  * Number of Dependents (if Family type)  
  * Monthly Income (for need assessment)  
  * Notes (additional context, max 1000 characters)  
* ✅ Beneficiary ID auto-generated: `B-000001` (sequential)  
* ✅ **Beneficiary status (dropdown):**  
  * Active (currently receiving aid)  
  * Inactive (temporarily not receiving aid)  
  * Completed (no longer needs aid, self-sufficient)  
* ✅ Cannot delete beneficiary if aid distribution records exist (soft delete only)  
* ✅ Soft delete changes status to "Completed" and hides from active lists

### **22.2 Aid Distribution Rules**

* ✅ **Required fields:**  
  * Beneficiary ID (select from beneficiary list)  
  * Distribution Date (Ethiopian calendar)  
  * Aid Type (dropdown: Cash, Food, Clothing, Medical, Education, Housing, Other)  
  * Amount/Value (monetary equivalent for reporting)  
  * Distributed By (auto-filled from current user ID)  
* ✅ **Optional fields:**  
  * Receipt Number (if formal receipt issued)  
  * Notes (description of items if non-cash, e.g., "5 bags of rice, 3 blankets")  
* ✅ Cannot record distribution dated in the future (validation error)  
* ✅ Distribution history permanently linked to beneficiary profile  
* ✅ Cannot modify distribution after 30 days (only Charity Head can override with justification)  
* ✅ All distributions logged in audit trail (permanent retention)

### **22.3 Beneficiary Reports**

* ✅ **Key metrics displayed:**  
  * Total beneficiaries (Active/Inactive/Completed count)  
  * Total aid distributed (sum of all amounts, by type)  
  * Aid per beneficiary (average amount per beneficiary)  
  * Distribution trends over time (monthly/quarterly charts)  
* ✅ **Filter reports by:**  
  * Date Range (Ethiopian calendar)  
  * Beneficiary Type (Individual/Family/Organization)  
  * Need Category (Food/Medical/Education/Housing/Other)  
  * Aid Type (Cash/Food/Clothing/Medical/Education/Housing/Other)  
  * Beneficiary Status (Active/Inactive/Completed)  
* ✅ **Export formats:** Excel, PDF, CSV  
* ✅ Export includes beneficiary names, amounts, dates, aid types

---

## **23\. CATEGORY & SUB-CATEGORY MANAGEMENT**

### **23.1 Category System Structure**

* ✅ **Separate category tables for different modules:**  
  * `song_categories` (for worship songs)  
  * `media_categories` (for photos/videos)  
  * `library_categories` (for educational resources)  
* ✅ Each category table has same structure (consistent across modules)  
* ✅ **Category table fields:**  
  * Category ID (auto-increment)  
  * Category Name (required, max 100 characters)  
  * Description (optional, max 500 characters)  
  * Display Order (numeric, for sorting \- lower numbers first)  
  * Status (Active, Inactive)  
  * Created By (user ID)  
  * Created At (timestamp)  
* ✅ **Sub-category table fields:**  
  * Sub-category ID (auto-increment)  
  * Category ID (foreign key to parent category)  
  * Sub-category Name (required, max 100 characters)  
  * Description (optional, max 500 characters)  
  * Display Order (numeric, for sorting within parent category)  
  * Status (Active, Inactive)  
  * Created By (user ID)  
  * Created At (timestamp)

### **23.2 Category Creation Rules**

* ✅ **Who can create categories:**  
  * **Songs:** Worship Monitor, Mezmur Department Head  
  * **Media:** AV Head  
  * **Library:** Education Department Head  
  * **Admin:** Can create categories for all modules (override)  
* ✅ **Category naming rules:**  
  * Must be unique within module (can't have duplicate song categories)  
  * Can have same name across modules (e.g., "Events" category in both Songs and Media)  
  * Max 100 characters  
  * Alphanumeric and spaces only (no special characters except hyphen and apostrophe)  
* ✅ **Sub-category requirements:**  
  * Must belong to a parent category  
  * Must be unique within parent category  
  * Can have same name under different parent categories (e.g., "Beginner" under "Kids" and "Adults")  
* ✅ Cannot delete category if:  
  * Any items (songs/media/library resources) assigned to it  
  * Any sub-categories exist under it  
* ✅ Can soft delete category (mark as Inactive, hide from dropdowns)  
* ✅ Inactive categories still show for existing items (data integrity)

### **23.3 Category Assignment Rules**

* ✅ **Every item MUST have category:**  
  * Songs: Category required, Sub-category required  
  * Media: Category required, Sub-category optional  
  * Library: Category required, Sub-category optional  
* ✅ **Display in public website:**  
  * Items organized by category (category as main navigation)  
  * Sub-categories shown under categories (if present)  
  * Users can filter by category and/or sub-category  
* ✅ **Category dropdown order:**  
  * Sorted by Display Order field (ascending)  
  * Admin can change order via drag-and-drop interface  
  * Inactive categories not shown in dropdowns (hidden)

