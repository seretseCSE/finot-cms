<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Favorite extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'favorable_type',
        'favorable_id',
        'session_id',
    ];

    public function favorable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function getResourceName(): string
    {
        return 'favorites';
    }

    public static function getNavigationLabel(): string
    {
        return 'Favorites';
    }
}
