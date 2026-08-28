<?php

namespace App\Models;

use App\Enums\MemberImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberImportRow extends Model
{
    protected $fillable = [
        'member_import_id', 'row_number', 'data', 'status', 'issues',
        'duplicate_member_id', 'resolution', 'member_id', 'error',
    ];

    protected $casts = [
        'status' => MemberImportRowStatus::class,
        'data' => 'array',
        'issues' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(MemberImport::class, 'member_import_id');
    }
}
