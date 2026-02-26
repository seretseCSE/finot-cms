# Role and Permission System Documentation

## Overview
Comprehensive role-based access control system with 16 predefined roles and granular permissions for the church management system. Built using Spatie Laravel Permission package for flexible permission management.

## Role Hierarchy

### Superadmin
- **Scope**: Full system access
- **Permissions**: Wildcard (`*`) - All permissions
- **Access**: System settings, backups, error logs, all departments

### Admin
- **Scope**: All operational permissions
- **Exclusions**: System settings, backups, error logs
- **Access**: All departments except system-level functions

### Department Heads
- **Scope**: Department-specific management
- **Access**: Full control within their department
- **Examples**: HR Head, Finance Head, Education Head

### Staff Roles
- **Scope**: Limited access based on role
- **Access**: Read-only or create/update within constraints
- **Examples**: Department Secretary, Staff

## Role Definitions

### 1. Superadmin
- **Label**: Super Admin
- **Description**: Full system access including system settings, backups
- **Permissions**: Wildcard (`*`)
- **Key Features**:
  - System configuration
  - Database backups
  - Error log access
  - User management across all departments
  - Role assignment
  - System maintenance

### 2. Admin
- **Label**: Admin
- **Description**: All operational permissions across all departments EXCEPT system settings, backups, error logs
- **Permissions**: 
  - User management (CRUD, export, role assignment)
  - Member management (all departments)
  - Group management (all departments)
  - Finance operations (except advanced inventory)
  - Education operations (except teacher management)
  - Media and content management
  - Charity and tour operations
  - Document management

### 3. HR Head
- **Label**: HR Head
- **Description**: Member CRUD, group CRUD, group assignment, member export – scoped to all members
- **Permissions**:
  - Member management (view, create, edit, delete, export)
  - Group management (view, create, edit, delete, assign members)
  - Attendance viewing
  - User role assignment
  - User editing

### 4. Finance Head
- **Label**: Finance Head
- **Description**: Contribution amounts, donations CRUD, financial reports, export – dept scoped
- **Permissions**:
  - Contribution management (view, create, edit, delete, export)
  - Financial reports
  - Beneficiary management (view)
  - Charity reports
  - Tour reports
  - Member export

### 5. Nibret Hisab Head
- **Label**: Nibret Hisab Head
- **Description**: All Finance Head permissions + inventory CRUD + inventory reports
- **Permissions**:
  - All Finance Head permissions
  - Inventory management (view, create, edit, delete)
  - Inventory movements
  - Inventory analytics
  - Inventory reports
  - Tour reports
  - Member export

### 6. Inventory Staff
- **Label**: Inventory Staff
- **Description**: Inventory CRUD, movements, analytics – dept scoped
- **Permissions**:
  - Inventory management (view, create, edit, delete)
  - Inventory movements
  - Inventory analytics
  - Inventory reports

### 7. Education Head
- **Label**: Education Head
- **Description**: Academic year CRUD, class/subject CRUD, enrollment, promotion, teacher management, unlock attendance, view teacher reports
- **Permissions**:
  - Academic year management
  - Class management
  - Subject management
  - Enrollment and promotion
  - Teacher management (view, create, edit, delete, manage)
  - Attendance management (view, mark, lock, unlock, sync conflicts)
  - Teacher reports
  - Member export

### 8. Education Monitor
- **Label**: Education Monitor
- **Description**: Attendance session create/mark, lock sessions, view sync conflicts
- **Permissions**:
  - Attendance viewing
  - Attendance creation and marking
  - Session locking and unlocking
  - Sync conflict resolution
  - Member viewing

### 9. Worship Monitor
- **Label**: Worship Monitor
- **Description**: Song CRUD, rehearsal schedule, rehearsal attendance, media visibility (own)
- **Permissions**:
  - Song management (view, create, edit, delete)
  - Rehearsal management (view, create, edit, delete)
  - Rehearsal attendance
  - Media visibility control (own content)

### 10. Mezmur Head
- **Label**: Mezmur Head
- **Description**: All Worship Monitor permissions + manage song/rehearsal
- **Permissions**:
  - All Worship Monitor permissions
  - Teacher reports viewing
  - Member export
  - Song and rehearsal management

### 11. AV Head
- **Label**: AV Head
- **Description**: Media CRUD, blog posts, announcements, FAQ, media categories
- **Permissions**:
  - Media management (view, create, edit, delete)
  - Blog management (view, create, edit, delete, publish)
  - Announcement management
  - FAQ management
  - Document management
  - Member export

### 12. Charity Head
- **Label**: Charity Head
- **Description**: Beneficiary CRUD, aid distribution, contribution recording (members only), view reports
- **Permissions**:
  - Beneficiary management
  - Aid distribution
  - Contribution recording (members only)
  - Charity reports
  - Contribution management
  - Tour reports
  - Member export

### 13. Tour Head
- **Label**: Tour Head
- Description**: Tour CRUD, registration management, attendance, tour reports
- **Permissions**:
  - Tour management (view, create, edit, delete)
  - Registration management
  - Attendance tracking
  - Tour reports
  - Member export

### 14. Internal Relations Head
- **Label**: Internal Relations Head
- Description**: Member group CRUD (all departments), media delete, document management
- **Permissions**:
  - Member management (all departments)
  - Group management (all departments)
  - Media deletion
  - Document management (view, create, edit, delete, upload, download)
  - Department resource management
  - Member export

### 15. Department Secretary
- **Label**: Department Secretary
- **Description**: Create/update only (NO delete) for their department resources
- **Permissions**:
  - Department resource viewing
  - Department resource creation
  - Department resource editing

### 16. Staff
- **Label**: Staff
- **Department**: Read-only access to own department resources
- **Permissions**:
  - Department resource viewing

## Permission Categories

### System Permissions
- `system.settings` - System configuration
- `system.backups` - Database backups
- `system.error_logs` - Error log viewing
- `system.maintenance` - Maintenance mode

### User Management Permissions
- `users.view` - View users
- `users.create` - Create users
- `users.edit` - Edit users
- `users.delete` - Delete users
- `users.export` - Export users
- `users.assign_roles` - Assign roles to users
- `users.manage_departments` - Manage user department assignments

### Member Management Permissions
- `members.view` - View members
- `members.create` - Create members
- `members.edit` - Edit members
- `members.delete` - Delete members
- `members.export` - Export members
- `members.assign_groups` - Assign members to groups
- `members.view_attendance` - View member attendance
- `members.manage_groups` - Manage member groups

### Group Management Permissions
- `groups.view` - View groups
- `groups.create` - Create groups
- `groups.edit` - Edit groups
- `groups.delete` - Delete groups
- `groups.assign_members` - Assign members to groups

### Finance Permissions
- `contributions.view` - View contributions
- `contributions.create` - Create contributions
- `contributions.edit` - Edit contributions
- `contributions.delete` - Delete contributions
- `contributions.export` - Export contributions
- `contributions.reports` - View financial reports

### Inventory Permissions
- `inventory.view` - View inventory
- `inventory.create` - Create inventory items
- `inventory.edit` - Edit inventory items
- `inventory.delete` - Delete inventory items
- `inventory.movements` - Track inventory movements
- `inventory.analytics` - View inventory analytics
- `inventory.reports` - Generate inventory reports

### Education Permissions
- `academic_years.view` - View academic years
- `academic_years.create` - Create academic years
- `academic_years.edit` - Edit academic years
- `academic_years.delete` - Delete academic years
- `classes.view` - View classes
- `classes.create` - Create classes
- `classes.edit` - Edit classes
- `classes.delete` - Delete classes
- `subjects.view` - View subjects
- `subjects.create` - Create subjects
- `subjects.edit` - Edit subjects
- `subjects.delete` - Delete subjects
- `enrollments.view` - View enrollments
- `enrollments.create` - Create enrollments
- `enrollments.edit` - Edit enrollments
- `enrollments.delete` - Delete enrollments
- `promotions.view` - View promotions
- `promotions.create` - Create promotions
- `promotions.edit` - Edit promotions
- `promotions.delete` - Delete promotions
- `teachers.view` - View teachers
- `teachers.create` - Create teachers
- `teachers.edit` - Edit teachers
- `teachers.delete` - Delete teachers
- `teachers.manage` - Teacher management
- `attendance.view` - View attendance
- `attendance.create` - Create attendance sessions
- `attendance.mark` - Mark attendance
- `attendance.lock` - Lock attendance sessions
- `attendance.unlock` - Unlock attendance sessions
- `attendance.sync_conflicts` - View sync conflicts
- `teacher_reports.view` - View teacher reports

### Worship Permissions
- `songs.view` - View songs
- `songs.create` - Create songs
- `songs.edit` - Edit songs
- `songs.delete` - Delete songs
- `rehearsals.view` - View rehearsals
- `rehearsals.create` - Create rehearsals
- `rehearsals.edit` - Edit rehearsals
- `rehearsals.delete` - Delete rehearsals
- `rehearsals.attendance` - Rehearsal attendance
- `media.visibility` - Media visibility control

### Media Permissions
- `media.view` - View media files
- `media.create` - Create media files
- `media.edit` - Edit media files
- `media.delete` - Delete media files
- `media.categories` - Manage media categories

### Content Management Permissions
- `blog.view` - View blog posts
- `blog.create` - Create blog posts
- `blog.edit` - Edit blog posts
- `blog.delete` - Delete blog posts
- `blog.publish` - Publish blog posts
- `announcements.view` - View announcements
- `announcements.create` - Create announcements
- `announcements.edit` - Edit announcements
- `announcements.delete` - Delete announcements
- `announcements.publish` - Publish announcements
- `faq.view` - View FAQ
- `faq.create` - Create FAQ
- `faq.edit` - Edit FAQ
- `faq.delete` - Delete FAQ

### Charity Permissions
- `beneficiaries.view` - View beneficiaries
- `beneficiaries.create` - Create beneficiaries
- `beneficiaries.edit` - Edit beneficiaries
- `beneficiaries.delete` - Delete beneficiaries
- `aid.distribution` - Distribute aid
- `charity.reports` - View charity reports

### Tour Permissions
- `tours.view` - View tours
- `tours.create` - Create tours
- `tours.edit` - Edit tours
- `tours.delete` - Delete tours
- `tours.registration` - Tour registration management
- `tours.attendance` - Tour attendance
- `tours.reports` - Tour reports

### Document Management Permissions
- `documents.view` - View documents
- `documents.create` - Create documents
- `documents.edit` - Edit documents
- `documents.delete` - Delete documents
- `documents.upload` - Upload documents
- `documents.download` - Download documents

### Department Resource Permissions
- `department_resources.view` - View department resources
- `department_resources.create` - Create department resources
- `department_resources.edit` - Edit department resources
- `department_resources.delete` - Delete department resources

## Implementation Files

### 1. RolesAndPermissionsSeeder
**File**: `database/seeders/RolesAndPermissions.php`

**Features:**
- Creates 16 predefined roles
- Creates 60+ granular permissions
- Assigns permissions to roles
- Uses wildcard permission for Superadmin
- Provides console feedback

**Key Methods:**
```php
// Permission creation
foreach ($permissions as $permission) {
    Permission::create(['name' => $permission]);
}

// Role creation with permissions
$role = Role::create([
    'name' => $roleData['name'],
    'label' => $roleData['label'],
    'description' => $roleData['description'],
    'guard_name' => 'web',
]);

// Permission assignment
foreach ($roleData['permissions'] as $permissionName) {
    $permission = Permission::where('name', $permissionName)->first();
    if ($permission) {
        $role->givePermissionTo($permission);
    }
}
```

### 2. DatabaseSeeder
**File**: `database/seeders/DatabaseSeeder.php`

**Updated to include:**
```php
$this->call([
    DepartmentSeeder::class,
    RolesAndPermissionsSeeder::class,
]);
```

## Usage Examples

### Role Assignment
```php
// Assign role to user
$user = User::find(1);
$user->assignRole('admin');

// Assign multiple roles
$user->assignRole(['hr_head', 'education_head']);

// Remove role
$user->removeRole('admin');
```

### Permission Checking
```php
// Check if user has permission
if ($user->can('users.create')) {
    // Can create users
}

// Check if user has any of multiple permissions
if ($user->hasAnyPermission(['users.create', 'users.edit'])) {
    // Can create or edit users
}

// Check specific role
if ($user->hasRole('finance_head')) {
    // Is finance head
}
```

### Middleware Implementation
```php
// In routes or controllers
public function __construct()
{
    $this->middleware(['permission:users.create', 'permission:members.view']);
}

// In controllers
public function index()
{
    $this->authorize('members.view');
    return Member::all();
}
```

### Blade Templates
```blade
@can('users.create')
    <a href="{{ route('users.create') }}">Create User</a>
@endcan

@canany(['users.edit', 'users.delete'])
    <div class="flex gap-2">
        <a href="{{ route('users.edit', $user) }}">Edit</a>
        <form method="POST" action="{{ route('users.destroy', $user) }}">
            @csrf
            <button type="submit">Delete</button>
        </form>
    </div>
@endcan
```

## Role Matrix

| **Role** | **Users** | **Groups** | **Finance** | **Inventory** | **Education** | **Worship** | **Media** | **Charity** | **Tours** | **Documents** |
|---------|---------|---------|----------|-----------|-------------|------------|-----------|---------|----------|-----------|------------|-----------|
| **Superadmin** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Admin** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **HR Head** | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Finance Head** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Nibret Hisab Head** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Inventory Staff** | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Education Head** | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Education Monitor** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Worship Monitor** | ✗ | ✗ | ✗ | ✗ | ✓ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Mezmur Head** | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **AV Head** | ✗ | ✗ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Charity Head** | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Tour Head** | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Internal Relations Head** | ✓ | ✓ | ✓ | ✗ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| **Department Secretary** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| **Staff** | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |

## Permission Details

### System Permissions
- **system.settings**: Access system configuration
- **system.backups**: Create and restore database backups
- **system.error_logs**: View system error logs
- **system.maintenance**: Put system in maintenance mode

### User Management Permissions
- **users.assign_roles**: Assign roles to users
- **users.manage_departments**: Manage user department assignments

### Member Management Permissions
- **members.manage_groups**: Create and manage member groups
- **members.view_attendance**: View member attendance records

### Education Permissions
- **teachers.manage**: Teacher management functions
- **attendance.lock/unlock**: Control attendance session locking
- **attendance.sync_conflicts**: Resolve attendance sync conflicts

### Worship Permissions
- **media.visibility**: Control media visibility settings
- **rehearsals.attendance**: Track rehearsal attendance

### Document Management
- **documents.upload/download**: File upload and download capabilities
- **documents.delete**: Document deletion permissions

## Security Considerations

### Permission Inheritance
- Superadmin has wildcard permission (`*`) - inherits all permissions
- Roles inherit permissions through Spatie's role system
- Users can have multiple roles with cumulative permissions

### Department Scoping
- Department heads have full access within their department
- Staff roles are scoped to their assigned department
- Cross-department access requires appropriate roles (Admin, HR Head, etc.)

### Data Protection
- All actions are logged through Laravel's audit system
- Permission changes require authentication
- Role assignments are tracked in the database

## Testing

### Permission Testing
```php
public function test_admin_can_view_users()
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    
    $this->actingAs($admin)
        ->get('/api/users')
        ->assertOk();
}

public function test_staff_cannot_delete_users()
{
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    
    $this->actingAs($staff)
        ->delete('/api/users/' . $staff->id)
        ->assertForbidden();
}
```

### Role Testing
```php
public function test_role_hierarchy()
{
    $superadmin = User::factory()->create();
    $admin = User::factory()->create();
    $staff = User::factory()->create();
    
    $superadmin->assignRole('superadmin');
    $admin->assignRole('admin');
    $staff->assignRole('staff');
    
    $this->assertTrue($superadmin->can('system.settings'));
    $this->assertFalse($admin->can('system.settings'));
    $this->assertFalse($staff->can('users.delete'));
}
```

## Migration and Seeding

### Fresh Installation
```bash
# Fresh migration with all seeders
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Production Deployment
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear

# Seed roles and permissions
php artisan db:seed --class=RolesAndPermissions
```

## Troubleshooting

### Common Issues

1. **Permission Not Working**
   ```bash
   # Check if permissions exist
   php artisan tinker
   >>> Permission::count()
   
   # Check if roles exist
   >>> Role::count()
   
   # Check user permissions
   >>> User::find(1)->getAllPermissions()
   ```

2. **Role Assignment Issues**
   ```bash
   # Check user roles
   php artisan tinker
   >>> User::find(1)->roles->pluck('name')
   
   # Check role permissions
   >>> Role::findByName('admin')->permissions->pluck('name')
   ```

3. **Middleware Issues**
   ```bash
   # Check middleware registration
   php artisan route:list
   
   # Check policy registration
   php artisan about
   ```

### Debug Commands
```bash
# List all permissions
php artisan tinker
>>> Permission::all()->pluck('name')->toArray()

# List all roles
php artisan tinker
>>> Role::all()->pluck('name')->toArray()

# Check user permissions
php artisan tinker
>>> $user = User::find(1);
>>> $user->getAllPermissions()->pluck('name')->toArray()
```

## Future Enhancements

### Planned Features
- **Role Templates**: Predefined role combinations
- **Permission Groups**: Group permissions for easier management
- **Role Expiration**: Time-limited role assignments
- **Audit Logging**: Track role changes and permission assignments
- **Role Requests**: User role request workflow

### Integration Options
- **API Endpoints**: RESTful role management
- **Webhooks**: Role change notifications
- **Email Notifications**: Role assignment confirmations
- **Mobile App Support**: Role-based API access

The role and permission system provides comprehensive access control with granular permissions, clear role hierarchy, and flexible department-based scoping for the church management system.
