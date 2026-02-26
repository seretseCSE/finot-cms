# Education Phase 3 – Integration Testing Checklist

## Academic Years
- [ ] Test: Create academic year, activate it, verify previous year deactivated
- [ ] Test: Attempt to activate overlapping dates (should fail with validation)
- [ ] Test: Reactivate a deactivated year (sets status Draft)

## Classes
- [ ] Test: Create class, archive it, restore it
- [ ] Test: Delete class with active enrollments (should be blocked)
- [ ] Test: Delete class with attendance sessions (should be blocked)

## Subjects
- [ ] Test: Create subject, archive it, restore it
- [ ] Test: Delete subject with active teacher assignments (should be blocked)

## Teachers
- [ ] Test: Create External Teacher (manual name/phone)
- [ ] Test: Create Member Teacher (auto-fill name/phone from member)
- [ ] Test: Change status to Former (soft-deletes)
- [ ] Test: Restore Former teacher (sets Active)
- [ ] Test: Teacher attendance rate column visible to Education Head only
- [ ] Test: Teacher attendance rate color thresholds (>90 green, 70–90 yellow, <70 red)

## Enrollments
- [ ] Test: Create enrollment (no active year → blocked)
- [ ] Test: Duplicate enrollment for same member/year (should fail)
- [ ] Test: Withdraw student (sets Withdrawn + completion date + reason)
- [ ] Test: Promote student (old Completed + new Enrolled)
- [ ] Test: Bulk promote class (transactional)

## Attendance Sessions
- [ ] Test: Create session (unique class+date+year)
- [ ] Test: Mark attendance (teacher + student sections)
- [ ] Test: Teacher marked Absent → Session Cancelled → student UI disabled
- [ ] Test: Teacher marked Absent → Substitute Assigned → student UI enabled
- [ ] Test: Lock session (status Locked, locked_at, locked_by)
- [ ] Test: Unlock session (requires justification, logs Tier-2 audit)
- [ ] Test: Edit locked session (should be blocked)

## Offline / Sync
- [ ] Test: Mark attendance offline (IndexedDB saved)
- [ ] Test: Reconnect → auto-sync (API endpoint called)
- [ ] Test: Sync conflict (last sync wins, conflict row created)
- [ ] Test: Service Worker registration (console logs)
- [ ] Test: Offline banner appears/disappears

## Auto-Lock & Reminders
- [ ] Test: Schedule auto-lock command (30+ day sessions locked)
- [ ] Test: Lock reminder command (creates in-app notifications)
- [ ] Test: Kernel scheduling (daily tasks registered)

## Dashboard Widgets
- [ ] Test: ActiveAcademicYearWidget shows active year + days remaining
- [ ] Test: EnrollmentStatsWidget shows total + class breakdown
- [ ] Test: AttendanceRateWidget shows weekly rate + color
- [ ] Test: PendingSessionLocksWidget counts sessions approaching deadline
- [ ] Test: RecentAttendanceWidget shows last 7 days with rate

## Tours
- [ ] Test: Education Head tour starts at #tour or first visit
- [ ] Test: Education Monitor tour starts at #tour or first visit
- [ ] Test: Navigation highlights and tooltips appear correctly
- [ ] Test: Tour state persists across page navigation

## Permissions
- [ ] Test: Education Monitor cannot view Teacher Attendance Report
- [ ] Test: Charity Head cannot create/edit contributions
- [ ] Test: Non-education roles cannot access Education resources

## Reports (when implemented)
- [ ] Test: Student Attendance Report filters + export
- [ ] Test: Teacher Attendance Report privacy gate
- [ ] Test: Class Roster PDF export
- [ ] Test: Sync Conflicts read-only view

---

### How to run tests
1. Ensure all migrations are run: `php artisan migrate`
2. Seed test data: `php artisan db:seed --class=TestUserSeeder`
3. Login as appropriate role:
   - Education Head: `education_head@example.com`
   - Education Monitor: `education_monitor@example.com`
   - Charity Head: `charity_head@example.com`
4. Open Filament admin and run each checklist item above.
5. Verify audit logs in `storage/logs/laravel.log` and `audit` channel.
6. Verify IndexedDB entries in browser DevTools > Application > IndexedDB.
7. Verify Service Worker registration in DevTools > Application > Service Workers.
