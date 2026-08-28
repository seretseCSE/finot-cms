<?php

namespace App\Models;

use App\Enums\BulkMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkMessage extends Model
{
    protected $fillable = [
        'sender_id', 'department_id', 'category_id', 'body', 'channels',
        'status', 'scheduled_at', 'quiet_hours_bypassed', 'confirm_global', 'audience', 'sent_at',
    ];

    protected $casts = [
        'status' => BulkMessageStatus::class,
        'channels' => 'array',
        'audience' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'quiet_hours_bypassed' => 'boolean',
        'confirm_global' => 'boolean',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MessageCategory::class, 'category_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BulkMessageRecipient::class);
    }

    public static function getPermissionName(string $action): string
    {
        return 'messages.broadcast';
    }
}
