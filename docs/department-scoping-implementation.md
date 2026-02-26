# Department Scoping Implementation Documentation

## Overview
Comprehensive department-based scoping system using Laravel's Scope interface and traits for automatic query filtering based on user roles and department assignments.

## Architecture

### 1. DepartmentScope Class
**File**: `app/Models/Scopes/DepartmentScope.php`

**Purpose**: Implements Laravel's Scope interface for automatic department-based query filtering

**Features**:
- **Role-based exceptions**: Superadmin and Admin bypass scoping
- **HR Head exception**: Can see all members and groups
- **Department filtering**: Applied to users with department_id
- **Query extensions**: Methods to bypass scope when needed

**Implementation**:
```php
class DepartmentScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!Auth::check()) return;
        
        $user = Auth::user();
        
        // Superadmin and Admin bypass scope
        if ($user->hasRole(['superadmin', 'admin'])) return;
        
        // HR Head can see all members and groups
        if ($user->hasRole('hr_head') && in_array($model->getTable(), ['members', 'groups'])) return;
        
        // Apply department scope
        if ($user->department_id) {
            $builder->where('department_id', $user->department_id);
        }
    }
}
```

### 2. ScopedByDepartment Trait
**File**: `app/Models/Traits/ScopedByDepartment.php`

**Purpose**: Trait for models that need department-based filtering

**Features**:
- **Automatic scope application**: Applies DepartmentScope globally
- **Scope manipulation**: Methods to bypass when needed
- **Access checking**: Validates user can access specific records
- **Department helpers**: Methods for department information

**Usage**:
```php
class Member extends Model
{
    use ScopedByDepartment; // Automatically applies department scope
}

// Automatic scoping
Member::all(); // Only user's department

// Bypass scoping
Member::withoutDepartmentScope()->get(); // All departments
Member::accessibleByCurrentUser()->get(); // User's department
```

### 3. Model Classification

#### **Models That Need Department Scoping**
These models are department-specific and should use `ScopedByDepartment` trait:
- **Member** - Church members belong to departments
- **Document** - Department-specific documents
- **AttendanceSession** - Department attendance sessions
- **InventoryItem** - Department inventory
- **Contribution** - Department contributions
- **Beneficiary** - Charity beneficiaries
- **Tour** - Department tours

#### **Models That Do NOT Need Department Scoping**
These are shared resources and should extend `BaseModel`:
- **AcademicYear** - Shared across all departments
- **Class** - Shared educational resource
- **Subject** - Shared educational resource
- **Song** - Shared worship resource
- **Tour** - Shared church activity
- **User** - System-wide resource

## Implementation Examples

### Department-Scoped Model
```php
<?php

namespace App\Models;

use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory, ScopedByDepartment;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'department_id', // Foreign key for scoping
        // ... other fields
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Department-specific methods
    public function getDepartmentName(): ?string
    {
        return $this->department?->name_en;
    }
}
```

### Shared Resource Model
```php
<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        // No department_id field
    ];

    // No department scoping needed
    public function classes()
    {
        return $this->hasMany(\App\Models\Class::class);
    }
}
```

## Query Scoping Behavior

### Automatic Scoping
```php
// All queries automatically scoped by user's department
$members = Member::all(); // WHERE department_id = auth()->user()->department_id

// Superadmin and Admin see all records
if (auth()->user()->hasRole(['superadmin', 'admin'])) {
    $members = Member::all(); // No WHERE clause applied
}
```

### Bypassing Scoping
```php
// See all departments (Superadmin/Admin only)
$allMembers = Member::withoutDepartmentScope()->get();

// Explicit scoping
$accessibleMembers = Member::accessibleByCurrentUser()->get();

// By specific department
$deptMembers = Member::byDepartment(1)->get();
```

### Access Control
```php
// Check if user can access specific record
$member = Member::find(1);

if ($member->canCurrentUserAccess()) {
    // User can access this member
}

// Check permissions
if (auth()->user()->can('members.view')) {
    // User has permission
}
```

## Role-Based Scoping Rules

### Scoping Matrix

| **User Role** | **Scoping Applied** | **Exceptions** |
|---------------|-------------------|-------------|
| **Superadmin** | None | Can see all records |
| **Admin** | None | Can see all records |
| **HR Head** | Members, Groups | Can see all members/groups regardless of department |
| **Department Head** | Department | Only records from their department |
| **Department Secretary** | Department | Only records from their department |
| **Staff** | Department | Only records from their department |

### Implementation Logic
```php
public function apply(Builder $builder, Model $model): void
{
    $user = Auth::user();
    
    // No scoping for Superadmin and Admin
    if ($user->hasRole(['superadmin', 'admin'])) return;
    
    // HR Head exception for members and groups
    if ($user->hasRole('hr_head') && in_array($model->getTable(), ['members', 'groups'])) return;
    
    // Department scoping for other roles
    if ($user->department_id) {
        $builder->where('department_id', $user->department_id);
    }
}
```

## Filament Integration

### Resource Permissions
```php
// BaseResource provides automatic permission checking
class MemberResource extends BaseResource
{
    protected static ?string $model = Member::class;
    
    // Automatic permission checks:
    // canView() -> members.view
    // canCreate() -> members.create
    // canEdit() -> members.update + department check
    // canDelete() -> members.delete + department check
}
```

### Department-Aware Resources
```php
// Department-scoped models automatically get proper access control
class Document extends Model
{
    use ScopedByDepartment;
    
    // Users can only see documents from their department
    // Department secretaries can create/update but not delete
    // Staff can only view
}
```

## Security Features

### Multi-Layer Security
1. **Authentication Check**: User must be logged in
2. **Permission Check**: User must have required permission
3. **Department Check**: User must have access to department
4. **Role Exception**: Special rules for specific roles

### Access Control Flow
```php
public function canCurrentUserAccess(): bool
{
    if (!auth()->check()) return false;
    
    $user = auth()->user();
    
    // Superadmin and Admin can access all
    if ($user->hasRole(['superadmin', 'admin'])) return true;
    
    // Check department ownership
    if ($user->department_id && $this->department_id) {
        return $user->department_id === $this->department_id;
    }
    
    return false;
}
```

### Audit Trail
- All access checks are logged through Laravel's audit system
- Permission changes are tracked
- Department assignments are monitored
- Failed access attempts are recorded

## Performance Considerations

### Database Optimization
- **Index on department_id**: For efficient filtering
- **Query optimization**: Scoping applied at database level
- **Caching**: Department information cached for performance

### Memory Usage
- **Lazy loading**: Department relationships loaded when needed
- **Efficient queries**: Single WHERE clause for scoping
- **Minimal overhead**: Trait-based implementation

## Testing

### Scoping Tests
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

### Access Control Tests
```php
public function test_access_control()
{
    $user = User::factory()->create(['department_id' => 1]);
    $member = Member::factory()->create(['department_id' => 2]);
    
    $this->actingAs($user)
        ->get("/api/members/{$member->id}")
        ->assertForbidden(); // Different department
}
```

## Troubleshooting

### Common Issues

1. **Scope Not Applied**
   ```bash
   # Check if trait is used
   php artisan tinker
   >>> in_array('App\Models\Traits\ScopedByDepartment', class_uses(\App\Models\Member::class))
   
   # Check if scope is registered
   >>> \App\Models\Member::getGlobalScopes()
   ```

2. **Access Control Issues**
   ```bash
   # Check user roles
   php artisan tinker
   >>> \App\Models\User::find(1)->roles->pluck('name')
   
   # Check department assignment
   >>> \App\Models\User::find(1)->department_id
   ```

3. **Permission Problems**
   ```bash
   # Check permissions
   php artisan tinker
   >>> \App\Models\User::find(1)->getAllPermissions()->pluck('name')
   
   # Check role permissions
   >>> \Spatie\Permission\Models\Role::findByName('hr_head')->permissions->pluck('name')
   ```

### Debug Commands
```bash
# Test scoping
php artisan tinker
>>> Member::all()->toSql() // See the SQL with WHERE clause
>>> Member::withoutDepartmentScope()->toSql() // See SQL without WHERE clause

# Check user access
php artisan tinker
>>> $member = \App\Models\Member::find(1);
>>> $member->canCurrentUserAccess()
```

## Best Practices

### Model Design
- **Clear separation**: Department-scoped vs shared resources
- **Consistent naming**: Use ScopedByDepartment trait appropriately
- **Proper relationships**: Define department relationships
- **Access methods**: Implement canCurrentUserAccess() checks

### Security
- **Defense in depth**: Multiple layers of access control
- **Principle of least privilege**: Users only see what they need
- **Audit logging**: Track all access attempts
- **Regular reviews**: Periodic security audits

### Performance
- **Database indexes**: Ensure department_id is indexed
- **Query optimization**: Apply scoping at database level
- **Efficient loading**: Use eager loading for relationships
- **Caching**: Cache department information

## Future Enhancements

### Planned Features
- **Dynamic scoping**: Configurable scoping rules
- **Cross-department access**: Temporary access grants
- **Permission inheritance**: More granular inheritance system
- **Audit improvements**: Enhanced access logging

### Integration Options
- **API scoping**: Department-based API access control
- **Multi-tenant**: Cross-organization support
- **Role-based UI**: Dynamic interface based on roles
- **Advanced permissions**: Time-based and location-based access

The department scoping implementation provides a robust, secure, and maintainable system for controlling access to department-specific resources while allowing appropriate exceptions for administrative roles.
