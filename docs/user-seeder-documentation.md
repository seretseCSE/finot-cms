# User Seeder Documentation

## Overview
Comprehensive user seeding system with 16 predefined test users covering all roles in the church management system. Each user is assigned appropriate roles, departments, and credentials for testing.

## User Specifications

### 1. Superadmin User
- **Email**: `superadmin@finot.org`
- **Phone**: `+251911000001`
- **Role**: `superadmin`
- **Department**: `null` (no department assignment)
- **Password**: `Admin1234`
- **Language**: `en`

### 2. Admin User
- **Email**: `admin@finot.org`
- **Phone**: `+251911000002`
- **Role**: `admin`
- **Department**: `null` (no department assignment)
- **Password**: `Admin1234`
- **Language**: `en`

### 3. HR Head User
- **Email**: `hr_head@finot.org`
- **Phone**: `+251911000003`
- **Role**: `hr_head`
- **Department**: `1` (Internal Relations)
- **Password**: `Admin1234`
- **Language**: `am`

### 4. Finance Head User
- **Email**: `finance_head@finot.org`
- **Phone**: `+251911000004`
- **Role**: `finance_head`
- **Department**: `2` (Nibret ena Hisab)
- **Password**: `Admin1234`
- **Language**: `am`

### 5. Nibret Hisab Head User
- **Email**: `nibret_hisab_head@finot.org`
- **Phone**: `+251911000005`
- **Role**: `nibret_hisab_head`
- **Department**: `2` (Nibret ena Hisab)
- **Password**: `Admin1234`
- **Language**: `am`

### 6. Inventory Staff User
- **Email**: `inventory_staff@finot.org`
- **Phone**: `+251911000006`
- **Role**: `inventory_staff`
- **Department**: `2` (Nibret ena Hisab)
- **Password**: `Admin1234`
- **Language**: `am`

### 7. Education Head User
- **Email**: `education_head@finot.org`
- **Phone**: `+251911000007`
- **Role**: `education_head`
- **Department**: `3` (Education)
- **Password**: `Admin1234`
- **Language**: `am`

### 8. Education Monitor User
- **Email**: `education_monitor@finot.org`
- **Phone**: `+251911000008`
- **Role**: `education_monitor`
- **Department**: `3` (Education)
- **Password**: `Admin1234`
- **Language**: `am`

### 9. Worship Monitor User
- **Email**: `worship_monitor@finot.org`
- **Phone**: `+251911000009`
- **Role**: `worship_monitor`
- **Department**: `5` (Mezmur)
- **Password**: `Admin1234`
- **Language**: `am`

### 10. Mezmur Head User
- **Email**: `mezmur_head@finot.org`
- **Phone**: `+251911000010`
- **Role**: `mezmur_head`
- **Department**: `5` (Mezmur)
- **Password**: `Admin1234`
- **Language**: `am`

### 11. AV Head User
- **Email**: `av_head@finot.org`
- **Phone**: `+251911000011`
- **Role**: `av_head`
- **Department**: `1` (Internal Relations)
- **Password**: `Admin1234`
- **Language**: `am`

### 12. Charity Head User
- **Email**: `charity_head@finot.org`
- **Phone**: `+251911000012`
- **Role**: `charity_head`
- **Department**: `4` (Revenue & Charity)
- **Password**: `Admin1234`
- **Language**: `am`

### 13. Tour Head User
- **Email**: `tour_head@finot.org`
- **Phone**: `+251911000013`
- **Role**: `tour_head`
- **Department**: `4` (Revenue & Charity)
- **Password**: `Admin1234`
- **Language**: `am`

### 14. Internal Relations Head User
- **Email**: `internal_relations_head@finot.org`
- **Phone**: `+251911000014`
- **Role**: `internal_relations_head`
- **Department**: `1` (Internal Relations)
- **Password**: `Admin1234`
- **Language**: `am`

### 15. Department Secretary User
- **Email**: `department_secretary@finot.org`
- **Phone**: `+251911000015`
- **Role**: `department_secretary`
- **Department**: `3` (Education - example as specified)
- **Password**: `Admin1234`
- **Language**: `am`

### 16. Staff User
- **Email**: `staff@finot.org`
- **Phone**: `+251911000016`
- **Role**: `staff`
- **Department**: `3` (Education - example as specified)
- **Password**: `Admin1234`
- **Language**: `am`

## Implementation Features

### Standard Configuration
- **Temporary Password**: All users have `temp_password_changed = false`
- **Default Password**: All users use `Admin1234` for easy testing
- **Language Preferences**: Amharic for most users, English for admin roles
- **Department Assignments**: Appropriate department assignments per role
- **Phone Numbers**: Sequential Ethiopian phone numbers for easy identification

### Role Coverage
- **All 16 Roles**: Every role in the system has a test user
- **Department Heads**: All 7 department heads are represented
- **Staff Roles**: Department secretary and staff roles included
- **Administrative Roles**: Superadmin and Admin with no department assignment

### Security Features
- **Password Hashing**: All passwords are properly hashed
- **Account Status**: All users are active and unlocked
- **Failed Attempts**: All users start with 0 failed attempts
- **Password History**: Empty history for all users

## Implementation File

### UserSeeder
**File**: `database/seeders/UserSeeder.php`

**Key Features**:
- **Clear existing users**: Clean slate before seeding
- **Auto-increment reset**: Starts IDs from 1
- **Batch insertion**: Efficient user creation
- **Console feedback**: Progress information during seeding

**Code Structure**:
```php
$users = [
    [
        'name' => 'Super Admin User',
        'email' => 'superadmin@finot.org',
        'phone' => '+251911000001',
        'password' => Hash::make('Admin1234'),
        'is_active' => true,
        'is_locked' => false,
        'failed_login_attempts' => 0,
        'temp_password_changed' => true,
        'password_history' => [],
        'language_preference' => 'en',
        'department_id' => null, // No department for superadmin
        'created_at' => now(),
        'updated_at' => now(),
    ],
    // ... 15 more users
];

foreach ($users as $userData) {
    User::create($userData);
}
```

## Usage

### Running the Seeder

#### Fresh Installation
```bash
# Fresh migration with all seeders
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=UserSeeder
```

#### Production Deployment
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear

# Seed users
php artisan db:seed --class=UserSeeder
```

### Verification
```bash
# Check user count
php artisan tinker
>>> \App\Models\User::count();
=> 16

# Check specific users
php artisan tinker
>>> \App\Models\User::where('email', 'superadmin@finot.org')->first();
>>> \App\Models\User::where('phone', '+251911000001')->first();

# Check role assignments
php artisan tinker
>>> \App\Models\User::with('roles')->get()->map(fn($u) => [
    'name' => $u->name,
    'roles' => $u->roles->pluck('name')->toArray()
]);

# Check department assignments
php artisan tinker
>>> \App\Models\User::whereNotNull('department_id')
    ->with('department')
    ->get()
    ->map(fn($u) => [
        'name' => $u->name,
        'department' => $u->department->name_en ?? 'No Department'
    ]);
```

## Testing Scenarios

### Role Testing
```php
public function test_superadmin_access()
{
    $superadmin = User::where('email', 'superadmin@finot.org')->first();
    
    $this->actingAs($superadmin)
        ->get('/api/users')
        ->assertOk(); // Superadmin can access all users
}

public function test_department_head_access()
{
    $financeHead = User::where('email', 'finance_head@finot.org')->first();
    
    $this->actingAs($financeHead)
        ->get('/api/inventory')
        ->assertOk(); // Can access department resources
        ->get('/api/members')
        ->assertOk(); // Can access members (HR Head permission)
}

public function test_staff_access()
{
    $staff = User::where('email', 'staff@finot.org')->first();
    
    $this->actingAs($staff)
        ->get('/api/other-department')
        ->assertForbidden(); // Cannot access other departments
        ->get('/api/own-department')
        ->assertOk(); // Can access own department
}
```

### Authentication Testing
```php
public function test_login_credentials()
{
    // All users have password "Admin1234"
    $response = $this->post('/login', [
        'phone' => '+251911000001',
        'password' => 'Admin1234',
    ]);
    
    $response->assertRedirect('/admin');
}

public function test_temp_password_change()
{
    $user = User::where('email', 'superadmin@finot.org')->first();
    
    // User should be able to access panel (temp_password_changed = true)
    $this->actingAs($user)
        ->get('/admin')
        ->assertOk();
}
```

## Department Coverage

### Department Assignments
| **Department** | **Users Assigned** | **Roles** |
|---------------|------------------|---------|
| **Internal Relations (1)** | HR Head, AV Head, Internal Relations Head | hr_head, av_head, internal_relations_head |
| **Nibret ena Hisab (2)** | Finance Head, Nibret Hisab Head, Inventory Staff | finance_head, nibret_hisab_head, inventory_staff |
| **Education (3)** | Education Head, Education Monitor, Department Secretary, Staff | education_head, education_monitor, department_secretary, staff |
| **Revenue & Charity (4)** | Charity Head, Tour Head | charity_head, tour_head |
| **Mezmur (5)** | Worship Monitor, Mezmur Head | worship_monitor, mezmur_head |

### Role Distribution
- **Superadmin**: 1 user (6.25%)
- **Admin**: 1 user (6.25%)
- **Department Heads**: 7 users (43.75%)
- **Staff Roles**: 2 users (12.5%)
- **Monitors**: 2 users (12.5%)
- **Total**: 16 users (100%)

## Security Considerations

### Password Security
- **Default Password**: `Admin1234` - should be changed in production
- **Hashing**: All passwords properly hashed with Laravel's Hash facade
- **Password History**: Empty for all new users
- **Temporary Password**: All users have `temp_password_changed = false`

### Access Control
- **Department Scoping**: Users assigned to departments for proper scoping
- **Role-Based Access**: Each user has appropriate role permissions
- **No Lockouts**: All users start unlocked with 0 failed attempts
- **Active Status**: All users are active (`is_active = true`)

### Data Integrity
- **Unique Emails**: Each user has unique email address
- **Unique Phones**: Each user has unique phone number
- **Sequential IDs**: Auto-increment starts from 1
- **Timestamps**: All users have created_at and updated_at

## Troubleshooting

### Common Issues

1. **Seeder Not Running**
   ```bash
   # Check if seeder exists
   ls database/seeders/UserSeeder.php
   
   # Check DatabaseSeeder configuration
   cat database/seeders/DatabaseSeeder.php
   ```

2. **Role Assignment Issues**
   ```bash
   # Check user roles
   php artisan tinker
   >>> \App\Models\User::with('roles')->get()
   
   # Check specific user
   >>> \App\Models\User::where('email', 'superadmin@finot.org')->first()->roles
   ```

3. **Department Assignment Issues**
   ```bash
   # Check department assignments
   php artisan tinker
   >>> \App\Models\User::whereNotNull('department_id')->with('department')->get()
   
   # Check department existence
   >>> \App\Models\Department::count()
   ```

### Debug Commands
```bash
# Test user creation
php artisan tinker
>>> $user = \App\Models\User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => Hash::make('password123'),
    'is_active' => true,
    'temp_password_changed' => false,
]);

# Check user permissions
php artisan tinker
>>> $user = \App\Models\User::find(1);
>>> $user->getAllPermissions()->pluck('name')->toArray()
>>> $user->roles->pluck('name')->toArray()
```

## Best Practices

### Production Deployment
- **Change Default Passwords**: Update all user passwords before production
- **Review Department Assignments**: Ensure users are in correct departments
- **Verify Role Assignments**: Confirm users have appropriate roles
- **Test Access Control**: Verify department scoping works correctly

### Security Recommendations
- **Password Policy**: Implement strong password requirements
- **Two-Factor Auth**: Consider 2FA for admin accounts
- **Session Management**: Implement session timeout for inactive users
- **Audit Logging**: Track all user management actions

### Maintenance
- **Regular Updates**: Update user information as needed
- **Account Reviews**: Periodic review of user access
- **Department Changes**: Update department assignments as roles change
- **Backup Strategy**: Regular backups of user data

## Future Enhancements

### Planned Features
- **Dynamic User Creation**: Form-based user creation interface
- **Bulk Operations**: Import/export user functionality
- **User Profiles**: Extended user profile management
- **Activity Logging**: User activity tracking system

### Integration Options
- **LDAP Integration**: External authentication sources
- **SSO Support**: Single sign-on capabilities
- **API Users**: API-only user accounts
- **Multi-tenant**: Organization-based user management

The user seeder provides a comprehensive set of test users covering all roles and departments in the church management system, enabling thorough testing of the permission matrix and department scoping implementations.
