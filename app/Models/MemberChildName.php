<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberChildName extends BaseModel
{
    use HasFactory, HasAuditLog;

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
