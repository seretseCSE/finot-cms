<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkMessageRecipient extends Model
{
    protected $fillable = [
        'bulk_message_id', 'member_id', 'user_id', 'channel', 'status', 'sent_at', 'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(BulkMessage::class, 'bulk_message_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
