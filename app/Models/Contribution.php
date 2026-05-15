<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'academic_year_id',
        'month',
        'month_name',
        'is_paid',
        'amount',
        'payment_date',
        'payment_method',
        'custom_payment_method',
        'notes',
        'recorded_by',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_archived' => 'boolean',
        'amount' => 'decimal:2',
    ];

    /**
     * Scope to get non-archived contributions.
     */
    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
