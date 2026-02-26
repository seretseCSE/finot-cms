# Education Module Testing Guide

## Quick Start

### 1. Setup Test Environment
```bash
# Install dependencies
composer install

# Run migrations with seed data
php artisan migrate:fresh --seed

# Create test users (if not in seeder)
php artisan tinker
>>> User::factory()->create(['email' => 'eduhead@test.com'])->assignRole('education_head');
>>> User::factory()->create(['email' => 'edumonitor@test.com'])->assignRole('education_monitor');
```

### 2. Run Integration Tests
```bash
# Interactive checklist
php artisan checklist:education-testing

# Automated tests
php artisan test:education-integration

# PHPUnit tests
php artisan test tests/Feature/EducationIntegrationTest.php

# Clean up test data
php artisan test:education-integration --cleanup
```

### 3. Manual Testing Checklist

#### Academic Year Management
- [ ] Create new academic year (Draft status)
- [ ] Activate academic year → verify previous year deactivated
- [ ] Check deactivation modal shows summary statistics
- [ ] Verify audit log entry (Tier-2) created
- [ ] Confirm old enrollments marked as Completed
- [ ] Confirm old attendance sessions marked as Completed

#### Student Enrollment
- [ ] Create student enrollment in active year
- [ ] Attempt duplicate enrollment → should fail with validation error
- [ ] Test EnrollmentUniquePerYear rule
- [ ] Verify enrollment appears in student timeline
- [ ] Check Ethiopian date formatting

#### Attendance Sessions
- [ ] Create attendance session (Open status)
- [ ] Mark student attendance (Present/Absent)
- [ ] Change status to Completed
- [ ] Change status to Locked
- [ ] Attempt to edit locked session → should be read-only
- [ ] Test unlock action with justification
- [ ] Verify Tier-2 audit log for unlock

#### Auto-Lock System
- [ ] Create test session 35 days old (Open status)
- [ ] Create test session 25 days old (Open status)
- [ ] Run dry-run: `php artisan attendance:auto-lock --dry-run`
- [ ] Verify only 35-day session selected
- [ ] Execute: `php artisan attendance:auto-lock`
- [ ] Verify old session locked, recent session unchanged
- [ ] Check Tier-1 audit logs created
- [ ] Test reminder command: `php artisan attendance:send-lock-reminders`

#### Teacher Attendance
- [ ] Mark teacher as Present/Absent/Late
- [ ] Verify teacher absence triggers session cancellation
- [ ] Check student attendance disabled for cancelled sessions
- [ ] Test Teacher Attendance Report permissions

#### Bulk Operations
- [ ] Select class with enrolled students
- [ ] Use Promote Students bulk action
- [ ] Verify old enrollments marked Completed
- [ ] Verify new enrollments created
- [ ] Check promotion audit trail

#### Permission Testing
- [ ] Login as Education Monitor → verify limited access
- [ ] Login as Education Head → verify full access
- [ ] Test Teacher Attendance Report access control
- [ ] Verify role-based UI elements

## Test Data Management

### Create Test Scenarios
```bash
# Create comprehensive test data
php artisan tinker

# Active academic year
>>> AcademicYear::factory()->active()->create();

# Multiple enrollments
>>> StudentEnrollment::factory()->enrolled()->count(10)->create();

# Mixed attendance sessions
>>> AttendanceSession::factory()->old(35)->open()->create();
>>> AttendanceSession::factory()->recent(10)->completed()->create();
>>> AttendanceSession::factory()->locked()->count(5)->create();

# Teacher attendance records
>>> TeacherAttendance::factory()->present()->count(20)->create();
>>> TeacherAttendance::factory()->absent()->count(5)->create();
```

### Verify Test Data
```bash
# Check data counts
php artisan tinker
>>> AcademicYear::count();
>>> StudentEnrollment::count();
>>> AttendanceSession::where('status', 'Locked')->count();
>>> TeacherAttendance::where('status', 'Absent')->count();
```

## Automated Testing

### PHPUnit Integration
```bash
# Run all Education tests
php artisan test --filter=EducationIntegrationTest

# Run specific test
php artisan test --filter=it_can_create_and_activate_academic_year

# Generate coverage report
php artisan test --coverage-html
```

### Custom Commands
```bash
# Full integration test suite
php artisan test:education-integration

# Interactive checklist
php artisan checklist:education-testing

# Cleanup test artifacts
php artisan test:education-integration --cleanup
```

## Expected Results

### Academic Year Tests
- Only one year can be active at a time
- Deactivation modal shows accurate statistics
- Audit logs capture all state changes

### Enrollment Tests
- Duplicate enrollments blocked by validation
- Timeline updates in real-time
- Ethiopian dates display correctly

### Attendance Tests
- Sessions progress through Open → Completed → Locked
- Locked sessions are immutable
- Unlock requires justification and audit

### Auto-Lock Tests
- Only sessions >30 days old are locked
- Notifications sent before auto-lock
- Audit trails maintained

### Permission Tests
- Role-based access enforced
- Graceful handling of unauthorized access
- UI reflects user permissions

## Troubleshooting

### Common Test Failures
1. **Missing Roles**: Ensure roles exist in database
   ```bash
   php artisan tinker
   >>> Spatie\Permission\Models\Role::create(['name' => 'education_head']);
   >>> Spatie\Permission\Models\Role::create(['name' => 'education_monitor']);
   ```

2. **Factory Errors**: Check model relationships and factories
   ```bash
   php artisan tinker
   >>> User::factory()->create(); // Test basic factory
   ```

3. **Permission Issues**: Verify user has correct role
   ```bash
   php artisan tinker
   >>> $user = User::find(1);
   >>> $user->hasRole('education_head');
   ```

4. **Date Issues**: Check Ethiopian date helper
   ```bash
   php artisan tinker
   >>> EthiopianDateHelper::toEthiopian(now());
   ```

### Debug Commands
```bash
# Check active year
php artisan tinker
>>> AcademicYear::where('is_active', true)->first();

# Verify session ages
php artisan tinker
>>> AttendanceSession::where('status', 'Open')
    ->get()
    ->map(fn($s) => ['id' => $s->id, 'days_old' => $s->created_at->diffInDays()]);

# Test auto-lock query
php artisan tinker
>>> AttendanceSession::where('status', 'Open')
    ->where('created_at', '<=', now()->subDays(30))
    ->count();
```

## Performance Testing

### Load Testing
```bash
# Create large dataset
php artisan tinker
>>> StudentEnrollment::factory()->enrolled()->count(1000)->create();
>>> AttendanceSession::factory()->count(500)->create();

# Test dashboard performance
php artisan tinker
>>> $sessions = AttendanceSession::with(['schoolClass', 'attendances'])->get();
```

### Memory Usage
```bash
# Monitor memory during tests
php artisan test:education-integration
# Watch memory usage in Laravel logs
```

## Documentation

- [Integration Testing Checklist](EDUCATION_INTEGRATION_TESTING_CHECKLIST.md)
- [Automated Test Command](app/Console/Commands/TestEducationIntegrationCommand.php)
- [Interactive Checklist Command](app/Console/Commands/EducationTestingChecklistCommand.php)
- [PHPUnit Test Suite](tests/Feature/EducationIntegrationTest.php)
- [Model Factories](database/factories/)

## Support

For testing issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Verify database state
3. Run individual test components
4. Check environment configuration

Remember to clean up test data before production deployment!
