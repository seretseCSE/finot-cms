<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'phase',
        'activated_at',
        'deactivated_at',
        'reactivated_at',
        'activated_by',
        'deactivated_by',
        'reactivated_by',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'reactivated_at' => 'datetime',
    ];

    /**
     * Get the enrollments for this academic year.
     */
    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function terms()
    {
        return $this->hasMany(Term::class);
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function deactivatedBy()
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function reactivatedBy()
    {
        return $this->belongsTo(User::class, 'reactivated_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the resource name for permissions.
     */
    public static function getResourceName(): string
    {
        return 'academic_years';
    }

    /**
     * Get the navigation label for the resource.
     */
    public static function getNavigationLabel(): string
    {
        return 'Academic Years';
    }

    /**
     * Get the navigation icon for the resource.
     */
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-calendar';
    }

    /**
     * Get the navigation group for the resource.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Education';
    }

    /**
     * Check if the academic year is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    /**
     * Scope to get only active academic years.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope to get the current phase academic year.
     */
    public function scopeCurrent($query)
    {
        return $query->where('phase', 'current');
    }

    /**
     * Scope to get the upcoming phase academic year.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('phase', 'upcoming');
    }

    /**
     * Scope to get completed phase academic years.
     */
    public function scopeCompleted($query)
    {
        return $query->where('phase', 'completed');
    }

    /**
     * Get the current academic year (phase = current, else the Active row).
     */
    public static function currentYear(): ?self
    {
        return static::current()->first()
            ?? static::active()->first();
    }

    /**
     * Get the next (upcoming) academic year.
     */
    public static function nextYear(): ?self
    {
        return static::upcoming()->first();
    }

    /**
     * Boot the model and add validation.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($academicYear) {
            // Validate date constraint
            if ($academicYear->start_date && $academicYear->end_date) {
                if ($academicYear->start_date >= $academicYear->end_date) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator()->make([], []),
                        new \Illuminate\Support\MessageBag(['end_date' => 'End date must be after start date.'])
                    );
                }
            }

            // Ensure only one current and one upcoming phase exist
            if ($academicYear->phase === 'current') {
                $existing = static::where('id', '!=', $academicYear->id)
                    ->where('phase', 'current')
                    ->first();
                if ($existing) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator()->make([], []),
                        new \Illuminate\Support\MessageBag(['phase' => "Only one academic year can be 'current'. Existing: {$existing->name}"])
                    );
                }
            }

            if ($academicYear->phase === 'upcoming') {
                $existing = static::where('id', '!=', $academicYear->id)
                    ->where('phase', 'upcoming')
                    ->first();
                if ($existing) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator()->make([], []),
                        new \Illuminate\Support\MessageBag(['phase' => "Only one academic year can be 'upcoming'. Existing: {$existing->name}"])
                    );
                }
            }
        });

        static::updated(function ($academicYear) {
            // If this academic year was just activated, deactivate all others
            if ($academicYear->wasChanged('status') && $academicYear->status === 'Active') {
                static::where('id', '!=', $academicYear->id)
                    ->where('status', 'Active')
                    ->update([
                        'status' => 'Deactivated',
                        'deactivated_at' => now(),
                    ]);
            }
        });

        static::created(function ($academicYear) {
            // If a new academic year is created as Active, deactivate all others
            if ($academicYear->status === 'Active') {
                static::where('id', '!=', $academicYear->id)
                    ->where('status', 'Active')
                    ->update([
                        'status' => 'Deactivated',
                        'deactivated_at' => now(),
                    ]);
            }
        });
    }
}
