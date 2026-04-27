<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'email',
        'phone',
        'registration_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'registration_date' => 'datetime',
    ];

    protected $dates = [
        'registration_date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the registrant's name
     */
    public function getRegistrantNameAttribute(): string
    {
        return $this->name ?? 'Unknown Registrant';
    }

    /**
     * Get formatted registration date in Ethiopian
     */
    public function getEthiopianRegistrationDateAttribute(): string
    {
        return app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->registration_date)['month_name_am'] . ' ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->registration_date)['day'] . ', ' .
            app(\App\Helpers\EthiopianDateHelper::class)
            ->toEthiopian($this->registration_date)['year'];
    }
}
