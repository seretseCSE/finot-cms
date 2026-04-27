<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MemberChildName extends BaseModel
{
    use HasFactory;
    use HasAuditLog;

    protected $fillable = [
        'member_id',
        'name',
        'birth_order',
    ];

    protected $casts = [
        'birth_order' => 'integer',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
