# Permission Matrix Implementation Documentation

## Overview
Comprehensive permission matrix system with standardized naming conventions, role inheritance, department-based scoping, and Filament integration for the church management system.

## Permission Naming Convention

### Format: `{resource}.{action}`

#### **Resource Names**
- `users` - User management
- `members` - Member management
- `groups` - Member groups
- `contributions` - Financial contributions
- `inventory` - Inventory management
- `academic_years` - Academic years
- `classes` - Classes
- `subjects` - Subjects
- `enrollments` - Student enrollments
- `promotions` - Student promotions
- `teachers` - Teachers
- `attendance` - Attendance tracking
- `songs` - Worship songs
- `rehearsals` - Worship rehearsals
- `media` - Media files
- `blog` - Blog posts
- `announcements` - Announcements
- `faq` - FAQ items
- `beneficiaries` - Charity beneficiaries
- `tours` - Church tours
- `documents` - Documents
- `department_resources` - Department-specific resources

#### **Action Names**
- `view` - View records
- `create` - Create new records
- `update` - Update existing records
- `delete` - Delete records
- `export` - Export data
- `record` - Special action for contributions
- `mark` - Special action for attendance
- `lock` - Special action for attendance
- `unlock` - Special action for attendance
- `manage` - Special management action
- `publish` - Publish content
- `visibility` - Media visibility control
- `distribution` - Aid distribution
- `registration` - Tour registration
- `upload` - File upload
- `download` - File download

### Example Permissions
```php
// Standard CRUD
'users.view'        // View users
'users.create'      // Create users
'users.update'      // Update users
'users.delete'      // Delete users

// Special actions
'contributions.record'  // Record contributions
'attendance.mark'      // Mark attendance
'rehearsals.attendance' // Rehearsal attendance
'media.visibility'     // Media visibility control
```

## Role Inheritance System

### Permission Sets for Reusability
```php
$permissionSets = [
    'hr_head_permissions' => [
        'members.view', 'members.create', 'members.update', 'members.delete',
        'groups.view', 'groups.create', 'groups.update', 'groups.delete',
        // ... more permissions
    ],
    
    'finance_head_permissions' => [
        'contributions.view', 'contributions.record', 'contributions.update',
        'contributions.reports',
        // ... more permissions
    ],
];
```

### Department Head Inheritance
```php
// Nibret Hisab Head inherits Finance Head + Inventory Staff permissions
'nibret_hisab_head' => array_merge(
    $permissionSets['finance_head_permissions'],
    $permissionSets['inventory_staff_permissions']
),

// Internal Relations Head inherits HR Head + AV Head permissions
'internal_relations_head' => array_merge(
    $permissionSets['hr_head_permissions'],
    ['media.delete', 'documents.create', 'documents.update', 'documents.delete']
),
```

## Department-Based Scoping

### HasDepartmentScope Trait
**File**: `app/Models/Scopes/HasDepartmentScope.php`

**Features**:
- Automatic global scope for department-based filtering
- Excludes Superadmin and Admin from scoping
- Special handling for HR Head (can see all members/groups)
- Department-based filtering for other roles

```php
public function apply(Builder $builder, Model $model): void
{
    if (!Auth::check()) return;
    
    $user = Auth::user();
    
    // Superadmin and Admin can see all
    if ($user->hasRole(['superadmin', 'admin'])) return;
    
    // HR Head can see all members and groups
    if ($user->hasRole('hr_head') && in_array($model->getTable(), ['members', 'groups'])) return;
    
    // Apply department scope
    if ($user->department_id) {
        $builder->where('department_id', $user->department_id);
    }
}
```

### HasDepartmentTrait
**File**: `app/Models/Traits/HasDepartmentTrait.php`

**Features**:
- Applies HasDepartmentScope globally
- Methods to bypass scope when needed
- Access checking methods
- Scoping methods for queries

```php
// Apply to model
class User extends Authenticatable {
    use HasDepartmentTrait;
}

// Usage
User::all();                    // Automatically scoped
User::withoutDepartmentScope(); // Bypass scope
User::accessibleByCurrentUser(); // Explicit scoping
```

## Filament Integration

### BaseResource Class
**File**: `app/Filament/Resources/BaseResource.php`

**Features**:
- Automatic permission checking for all CRUD operations
- Department-based access control
- Inherited by all resource classes

```php
abstract class BaseResource extends Resource
{
    public static function canCreate(): bool
    {
        $permission = static::getModel()::getPermissionName('create');
        return auth()->user()->can($permission);
    }
    
    public static function canEdit(Model $record): bool
    {
        $permission = static::getModel()::getPermissionName('update');
        
        if (!auth()->user()->can($permission)) return false;
        
        // Check department access
        if (method_exists($record, 'canCurrentUserAccess')) {
            return $record->canCurrentUserAccess();
        }
        
        return true;
    }
}
```

### BaseModel Class
**File**: `app/Models/BaseModel.php`

**Features**:
- Permission name generation
- Navigation configuration
- Access checking methods

```php
abstract class BaseModel extends Model
{
    public static function getPermissionName(string $action): string
    {
        $resourceName = static::getResourceName();
        return "{$resourceName}.{$action}";
    }
    
    public function currentUserCan(string $action): bool
    {
        $permission = static::getPermissionName($action);
        return auth()->user()->can($permission);
    }
}
```

## Implementation Files

### 1. RoleSeeder
**File**: `database/seeders/RoleSeeder.php`

**Features**:
- Creates 16 roles with proper inheritance
- Uses permission sets for reusability
- Implements department head inheritance
- Wildcard permission for Superadmin

**Key Methods**:
```php
// Permission sets for inheritance
private function getAdminPermissions(): array
private function getInternalRelationsHeadPermissions(): array

// Role creation with inheritance
'nibret_hisab_head' => array_merge(
    $permissionSets['finance_head_permissions'],
    $permissionSets['inventory_staff_permissions']
),
```

### 2. HasDepartmentScope
**File**: `app/Models/Scopes/HasDepartmentScope.php`

**Features**:
- Global scope implementation
- Role-based exceptions
- Department filtering logic

### 3. HasDepartmentTrait
**File**: `app/Models/Traits/HasDepartmentTrait.php`

**Features**:
- Trait for easy model integration
- Access checking methods
- Scope manipulation methods

### 4. BaseResource
**File**: `app/Filament/Resources/BaseResource.php`

**Features**:
- Filament permission integration
- CRUD operation permission checks
- Department-based access control

### 5. BaseModel
**File**: `app/Models/BaseModel.php`

**Features**:
- Permission name generation
- Navigation configuration
- Access checking utilities

## Usage Examples

### Model Integration
```php
// User model with department scoping
class User extends Authenticatable {
    use HasDepartmentTrait;
}

// Department model
class Department extends Model {
    use HasDepartmentTrait;
    
    public static function getResourceName(): string
    {
        return 'departments';
    }
}
```

### Resource Integration
```php
// Extend BaseResource for automatic permission checking
class UserResource extends BaseResource
{
    protected static ?string $model = User::class;
    
    // canView, canCreate, canEdit, canDelete automatically implemented
}
```

### Permission Checking
```php
// In controllers
public function index()
{
    $this->authorize('users.view');
    return User::accessibleByCurrentUser()->get();
}

// In blade templates
@can('users.create')
    <button>Create User</button>
@endcan

// In models
if ($user->currentUserCan('update')) {
    // Can update user
}
```

### Department Scoping
```php
// Automatic scoping
$users = User::all(); // Only user's department

// Bypass scoping
$allUsers = User::withoutDepartmentScope()->get();

// Explicit scoping
$accessibleUsers = User::accessibleByCurrentUser()->get();
```

## Role Matrix with Inheritance

### Department Head Inheritance Examples

#### **Internal Relations Head**
```php
// Inherits HR Head + AV Head permissions
'internal_relations_head' => array_merge(
    $permissionSets['hr_head_permissions'],  // Member/group management
    ['media.delete', 'documents.create', 'documents.update', 'documents.delete']  // AV Head subset
),
```

#### **Nibret Hisab Head**
```php
// Inherits Finance Head + Inventory Staff permissions
'nibret_hisab_head' => array_merge(
    $permissionSets['finance_head_permissions'],  // Financial operations
    $permissionSets['inventory_staff_permissions']  // Inventory management
),
```

#### **Mezmur Head**
```php
// Inherits Worship Monitor + additional permissions
'mezmur_head' => array_merge(
    $permissionSets['worship_monitor_permissions'],  // Worship operations
    ['teacher_reports.view']  // Additional reporting
),
```

### Department Secretary Scoping
```php
// Only create/update permissions for their department
'department_secretary' => [
    'department_resources.view',
    'department_resources.create',
    'department_resources.update',
    // NO delete permission
],
```

### Staff Scoping
```php
// Read-only access to own department
'staff' => [
    'department_resources.view',
    // NO create, update, delete permissions
],
```

## Security Features

### Permission Inheritance
- **Reusability**: Permission sets prevent duplication
- **Maintainability**: Changes propagate to inheriting roles
- **Clarity**: Clear inheritance hierarchy

### Department Scoping
- **Automatic**: Applied globally to models with trait
- **Role-aware**: Different rules for different roles
- **Bypassable**: Can be overridden when needed

### Access Control
- **Multi-layer**: Permission + department + role checks
- **Contextual**: Different rules for different contexts
- **Audit-ready**: All access checks logged

## Testing

### Permission Testing
```php
public function test_department_scoping()
{
    $hrHead = User::factory()->create(['department_id' => 1]);
    $hrHead->assignRole('hr_head');
    
    $staff = User::factory()->create(['department_id' => 2]);
    $staff->assignRole('staff');
    
    // HR Head can see all members
    $this->actingAs($hrHead)
        ->get('/api/members')
        ->assertOk();
    
    // Staff can only see own department
    $this->actingAs($staff)
        ->get('/api/members')
        ->assertJsonCount(0); // No members from other departments
}
```

### Role Inheritance Testing
```php
public function test_role_inheritance()
{
    $nibretHead = User::factory()->create();
    $nibretHead->assignRole('nibret_hisab_head');
    
    // Should have finance head permissions
    $this->assertTrue($nibretHead->can('contributions.view'));
    
    // Should have inventory permissions
    $this->assertTrue($nibretHead->can('inventory.create'));
}
```

### Department Access Testing
```php
public function test_department_access()
{
    $user = User::factory()->create(['department_id' => 1]);
    $user->assignRole('staff');
    
    $otherDeptResource = DepartmentResource::factory()->create(['department_id' => 2]);
    
    // Cannot access other department resources
    $this->assertFalse($user->can('department_resources.update', $otherDeptResource));
}
```

## Migration and Seeding

### Fresh Installation
```bash
# Fresh migration with all seeders
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=RoleSeeder
```

### Production Deployment
```bash
# Clear caches
php artisan cache:clear
php artisan config:clear

# Seed roles and permissions
php artisan db:seed --class=RoleSeeder
```

## Troubleshooting

### Common Issues

1. **Department Scoping Not Working**
   ```bash
   # Check if trait is applied
   php artisan tinker
   >>> in_array('App\Models\Traits\HasDepartmentTrait', class_uses(\App\Models\User::class))
   
   # Check user department
   >>> \App\Models\User::find(1)->department_id
   ```

2. **Permission Inheritance Issues**
   ```bash
   # Check role permissions
   php artisan tinker
   >>> \Spatie\Permission\Models\Role::findByName('nibret_hisab_head')->permissions->pluck('name')
   
   # Check permission sets
   >>> \Database\Seeders\RoleSeeder::class
   ```

3. **Filament Resource Permissions**
   ```bash
   # Check resource permissions
   php artisan tinker
   >>> \App\Models\User::getPermissionName('create')
   
   # Check user permissions
   >>> \App\Models\User::find(1)->getAllPermissions()->pluck('name')
   ```

### Debug Commands
```bash
# List all permissions
php artisan tinker
>>> \Spatie\Permission\Models\Permission::all()->pluck('name')->toArray()

# Check role inheritance
php artisan tinker
>>> $role = \Spatie\Permission\Models\Role::findByName('internal_relations_head');
>>> $role->permissions->pluck('name')->toArray()

# Test department scoping
php artisan tinker
>>> $user = \App\Models\User::find(1);
>>> $user->department_id;
>>> $user->hasRole('hr_head');
```

## Future Enhancements

### Planned Features
- **Dynamic Permission Sets**: Configurable permission sets
- **Role Templates**: Predefined role combinations
- **Permission Groups**: Group permissions for easier management
- **Department Permissions**: Department-specific permission sets

### Integration Options
- **API Permissions**: Role-based API access control
- **Webhook Permissions**: Permission-based webhook access
- **Mobile App Support**: Department-scoped mobile access
- **Multi-tenant**: Cross-organization permission management

The permission matrix implementation provides a robust, scalable, and maintainable access control system with standardized naming conventions, role inheritance, and department-based scoping specifically designed for church management operations.
