<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class CustomOption extends Model
{
    use SoftDeletes;

    protected $table = 'custom_options';

    public $timestamps = false;

    protected $fillable = [
        'field_name',
        'option_value',
        'status',
        'added_by',
        'approved_by',
        'added_at',
        'approved_at',
        'usage_count',
        'display_order',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'approved_at' => 'datetime',
        'usage_count' => 'integer',
        'display_order' => 'integer',
    ];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public static function getOptionsForField(string $fieldName): array
    {
        $predefined = (array) (config('custom_options.predefined.' . $fieldName) ?? []);

        $approved = static::query()
            ->approved()
            ->where('field_name', $fieldName)
            ->orderByRaw('display_order is null')
            ->orderBy('display_order')
            ->orderBy('usage_count', 'desc')
            ->orderBy('option_value')
            ->pluck('option_value')
            ->all();

        $merged = array_values(array_unique(array_merge($predefined, $approved)));

        return array_combine($merged, $merged);
    }

    public static function recordUsage(string $fieldName, string $value): void
    {
        static::query()
            ->where('field_name', $fieldName)
            ->where('option_value', $value)
            ->whereIn('status', ['approved', 'pending'])
            ->update([
                'usage_count' => DB::raw('usage_count + 1'),
            ]);
    }
}
