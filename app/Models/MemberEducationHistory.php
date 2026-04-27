<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberEducationHistory extends Model
{
    protected $table = 'member_education_history';
    protected $fillable = [
        'member_id',
        'school_name',
        'education_level',
        'education_department',
        'is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    /**
     * Get the member that owns this education record.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
