<?php

namespace App\Models;

use App\Enums\FacilityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'capacity', 'location_notes', 'is_active'];

    protected $casts = [
        'type' => FacilityType::class,
        'is_active' => 'boolean',
    ];

    public static function getResourceName(): string
    {
        return 'facilities';
    }

    public static function getPermissionName(string $action): string
    {
        return match ($action) {
            'view' => 'facilities.view',
            'create', 'update', 'delete' => 'facilities.manage',
            default => 'facilities.'.$action,
        };
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
