<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

class Notification extends BaseModel
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'type',
        'title',
        'message',
        'action_url',
        'context_data',
    ];

    protected $casts = [
        'context_data' => 'array',
    ];

    protected $dates = [
        'read_at',
    ];

    public function notifiable(): BelongsTo
    {
        return $this->morphTo();
    }

    /**
     * Get formatted read date in Ethiopian
     */
    public function getEthiopianReadAtAttribute(): ?string
    {
        if (!$this->read_at) {
            return null;
        }

        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->read_at)['month_name_am'] . ' ' . 
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->read_at)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->read_at)['year'];
    }

    /**
     * Get resource name for permissions
     */
    public static function getResourceName(): string
    {
        return 'notifications';
    }

    /**
     * Get navigation label for resource
     */
    public static function getNavigationLabel(): string
    {
        return 'Notifications';
    }

    /**
     * Get navigation icon for resource
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bell';
    }

    /**
     * Get navigation group for resource
     */
    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }
}
