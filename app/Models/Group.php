<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\ScopedByDepartment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends BaseModel
{
    use HasFactory, ScopedByDepartment;

    protected $fillable = [
        'name',
        'description',
        'department_id',
        'leader_member_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function leader()
    {
        return $this->belongsTo(Member::class, 'leader_member_id');
    }

    public function memberAssignments()
    {
        return $this->hasMany(MemberGroupAssignment::class);
    }

    public function members()
    {
        return $this->hasManyThrough(
            Member::class,
            MemberGroupAssignment::class,
            'group_id',
            'id',
            'id',
            'member_id'
        )->where('member_group_assignments.is_active', true);
    }
}
