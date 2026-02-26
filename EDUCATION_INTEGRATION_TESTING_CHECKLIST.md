# Education Integration Testing Checklist

## Prerequisites
- Laravel environment running (`php artisan serve`)
- Database migrated and seeded with test users
- Login as user with appropriate role for each test

---

## 1. Academic Year Lifecycle

### Test: Create academic year, activate it, verify previous year deactivated
**Steps:**
1. Login as `education_head` or `admin`
2. Navigate to Education → Academic Years
3. Click "New Academic Year"
   - Name: `2026-2027`
   - Start Date: `2026-09-01`
   - End Date: `2027-07-31`
   - Status: `Draft`
4. Save
5. Edit the new year → set Status to `Active` → Save
6. Verify:
   - New year shows as `Active`
   - Previous year automatically shows as `Inactive`
   - Confirmation modal appeared with summary stats before deactivation
   - Audit log entry created for deactivation (Tier-2)

**Expected Results:**
- Only one academic year can be active at a time
- Deactivation modal shows enrolled students, attendance sessions, teacher attendance counts
- Previous enrollments marked `Completed`
- Previous Open attendance sessions marked `Completed`

---

## 2. Enrollment Validation

### Test: Enroll student, attempt duplicate enrollment in same year (should fail)
**Steps:**
1. Ensure an active academic year exists
2. Navigate to Education → Enrollments
3. Click "New Enrollment"
   - Select a student
   - Select a class
   - Academic Year should auto-select active year
   - Enrollment Date: today
   - Status: `Enrolled`
4. Save
5. Try to create another enrollment for the same student in the same academic year
6. Verify:
   - Validation error appears: "Student already enrolled in this academic year"
   - Duplicate enrollment cannot be saved

**Expected Results:**
- `EnrollmentUniquePerYear` rule prevents duplicates
- Error message is user-friendly

---

## 3. Attendance Session Timeline

### Test: Create attendance session, verify it appears in student timeline
**Steps:**
1. Navigate to Education → Attendance Sessions
2. Click "New Attendance Session"
   - Select class
   - Session Date: today
   - Status: `Open`
3. Save
4. Navigate to Members → select the enrolled student → Timeline tab
5. Verify:
   - Attendance session appears in timeline
   - Shows session date, class, status
   - Timeline entry is properly formatted

**Expected Results:**
- Timeline shows real-time attendance events
- Entries are ordered by date
- Status badges are color-coded

---

## 4. Session Locking & Edit Restrictions

### Test: Mark student absent, lock session, attempt to edit (should fail)
**Steps:**
1. Open an attendance session (status: Open)
2. Mark some students as Absent
3. Change session status to `Completed` → Save
4. Change session status to `Locked` → Save
5. Try to edit the locked session:
   - Attempt to change attendance marks
   - Attempt to change session status
6. Verify:
   - Edit button is disabled/hidden for locked sessions
   - Cannot modify attendance marks
   - Cannot change session status
   - Read-only view is enforced

**Expected Results:**
- Locked sessions are immutable
- UI prevents edit attempts
- Clear visual indication of locked state

---

## 5. Session Unlock with Audit

### Test: Unlock session as Education Head with justification, verify audit log
**Steps:**
1. As `education_head`, find a locked attendance session
2. Click "Unlock" action
3. Fill in justification:
   - Reason: "Correction for data entry error"
   - Notes: "Student was marked incorrectly"
4. Confirm unlock
5. Verify:
   - Session status changes to `Completed` (not Locked)
   - Unlock action appears in session history
   - Tier-2 audit log entry created:
     - action: `session_unlocked`
     - justification recorded
     - performed_by: current user
     - timestamp

**Expected Results:**
- Only authorized roles can unlock
- Justification is mandatory
- Audit trail is complete

---

## 6. Auto-Lock Scheduling

### Test: Schedule auto-lock command, verify 30+ day sessions are locked
**Setup:**
1. Create test attendance sessions with dates:
   - 35 days ago (should be locked)
   - 25 days ago (should remain open)
   - Status: `Open` for both

**Run Command:**
```bash
php artisan attendance:auto-lock --dry-run
```

**Verify Dry Run:**
- Output shows which sessions would be locked
- Only sessions >30 days old are listed

**Execute Command:**
```bash
php artisan attendance:auto-lock
```

**Verify Results:**
- 35-day-old session status changed to `Locked`
- 25-day-old session remains `Open`
- Tier-1 audit logs created for auto-locked sessions
- Notification sent to session creators

**Test Reminder Command:**
```bash
php artisan attendance:send-lock-reminders
```

**Verify:**
- In-app notifications created for sessions 27+ days old
- Email notifications sent (if configured)

---

## 7. Teacher Attendance Impact

### Test: Teacher marked absent → session Cancelled → student attendance disabled
**Steps:**
1. Create attendance session with status `Open`
2. Mark teacher as Absent in Teacher Attendance
3. Verify:
   - Session status automatically changes to `Cancelled`
   - Student attendance marks become disabled/readonly
   - Cannot mark student attendance for cancelled sessions
   - Visual indicator shows session is cancelled

**Expected Results:**
- Teacher absence triggers session cancellation
- Student attendance is blocked for cancelled sessions
- Clear UI feedback about cancellation reason

---

## 8. Bulk Class Promotion

### Test: Bulk promote class, verify old enrollment Completed + new enrollment created
**Steps:**
1. Ensure active academic year exists
2. Navigate to Education → Classes
3. Select a class with enrolled students
4. Click "Promote Students" bulk action
5. Fill promotion form:
   - Target Academic Year: select next year (create if needed)
   - Target Class: select next grade level
   - Promotion Date: today
6. Confirm promotion
7. Verify for each promoted student:
   - Old enrollment status changed to `Completed`
   - Completion date set to promotion date
   - New enrollment created in target class/year
   - New enrollment status: `Enrolled`
   - Enrollment history shows both records

**Expected Results:**
- Bulk action processes all selected students
- No duplicate enrollments created
- Audit trail records promotion activity
- Timeline shows promotion events

---

## 9. Permission Checks

### Test: Education Monitor cannot view Teacher Attendance Report (permission check)
**Steps:**
1. Login as `education_monitor`
2. Navigate to Education → Reports
3. Look for "Teacher Attendance Report" option
4. Verify:
   - Report option is not visible/accessible
   - Access denied message if URL accessed directly
   - Only `education_head`, `admin`, `superadmin` can view

**Test Additional Permissions:**
- `education_monitor` can view but not edit attendance sessions
- `education_head` can unlock sessions and manage academic years
- Regular users cannot access Education section

**Expected Results:**
- Role-based access control enforced
- Graceful handling of unauthorized access
- Clear permission indicators in UI

---

## 10. Cross-Feature Integration

### Test: Ethiopian Date Helper Integration
**Steps:**
1. Create academic year with Ethiopian dates
2. Create attendance session with Ethiopian date
3. View student timeline
4. Verify:
   - Dates display in both Gregorian and Ethiopian formats
   - Date picker supports Ethiopian calendar
   - Timeline shows proper date formatting

### Test: Dashboard Widgets
**Steps:**
1. Login as different roles
2. View Education Dashboard
3. Verify:
   - `ActiveAcademicYearWidget` shows current year
   - `EnrollmentStatsWidget` shows correct counts
   - `AttendanceRateWidget` calculates correctly
   - `PendingSessionLocksWidget` shows sessions nearing auto-lock
   - `RecentAttendanceWidget` shows recent sessions
   - Widgets respect role visibility

---

## Automation Scripts

### Run Full Test Suite
```bash
# Setup test environment
php artisan migrate:fresh --seed
php artisan test:education-integration

# Manual verification checklist
php artisan checklist:education-testing
```

### Test Data Cleanup
```bash
# Remove test data after testing
php artisan cleanup:test-education-data
```

---

## Troubleshooting

### Common Issues
1. **Academic year won't activate**: Check if another year is already active
2. **Enrollment validation fails**: Verify student isn't already enrolled
3. **Session won't lock**: Check if session is older than 30 days
4. **Permission denied**: Verify user has correct role
5. **Timeline not updating**: Check if events are being fired correctly

### Debug Commands
```bash
# Check active academic year
php artisan tinker
>>> AcademicYear::where('is_active', true)->first();

# Check session ages
php artisan tinker
>>> AttendanceSession::where('status', 'Open')->get()->map(fn($s) => ['id' => $s->id, 'days_old' => $s->created_at->diffInDays()]);

# Verify permissions
php artisan tinker
>>> Auth::user()->hasRole('education_head');
```

---

## Sign-off Checklist

- [ ] All academic year lifecycle tests pass
- [ ] Enrollment validation works correctly
- [ ] Attendance sessions appear in timelines
- [ ] Session locking/unlocking functions properly
- [ ] Auto-lock commands run as expected
- [ ] Teacher absence triggers cancellation
- [ ] Bulk promotion creates correct enrollments
- [ ] Permission checks enforced
- [ ] Ethiopian dates display correctly
- [ ] Dashboard widgets show accurate data
- [ ] Audit logs are complete
- [ ] No PHP errors in logs
- [ ] Frontend UI responds correctly

**Testing Completed By:** _________________________
**Date:** _________________________
**Environment:** _________________________
