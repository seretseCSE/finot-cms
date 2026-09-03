<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScale extends Model
{
    protected $fillable = [
        'name',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function bands(): HasMany
    {
        return $this->hasMany(GradingScaleBand::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function defaultScale(): ?self
    {
        return static::query()->where('is_default', true)->with('bands')->first()
            ?? static::query()->with('bands')->latest('id')->first();
    }
}
