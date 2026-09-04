<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassAnnouncement extends BaseModel
{
    use HasAuditLog;
    use SoftDeletes;

    protected $fillable = [
        'class_id',
        'title',
        'body',
        'event_at',
        'published_at',
        'is_published',
        'created_by',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'published_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    public static function getResourceName(): string
    {
        return 'class_announcements';
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->whereNotNull('published_at');
    }
}
