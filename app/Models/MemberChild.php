<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberChild extends Model
{
    protected $fillable = [
        'member_id',
        'child_name',
        'birth_order',
    ];

    /**
     * Get the member that owns this child record.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
