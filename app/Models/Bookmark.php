<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Bookmark extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bookmarkable_type',
        'bookmarkable_id',
        'section_anchor',
        'section_title',
        'scroll_percentage',
        'session_id',
    ];

    protected $casts = [
        'scroll_percentage' => 'float',
    ];

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
