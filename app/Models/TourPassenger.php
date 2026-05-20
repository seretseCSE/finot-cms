<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TourPassenger extends BaseModel
{
    use HasFactory;
    use HasAuditLog;

    protected $fillable = [
        'passenger_code',
        'tour_id',
        'full_name',
        'phone',
        'passenger_count',
        'receipt_image',
        'member_id',
        'registration_type',
        'status',
        'total_tours',
        'registration_date',
        'registered_by',
        'cancellation_reason',
    ];

    /**
     * Validation rules for tour passenger booking.
     */
    public static function rules(array $context = []): array
    {
        $rules = [
            'passenger_count' => [
                'required',
                'integer',
                'min:1',
                'max:500',
            ],
            'tour_id' => [
                'required',
                'exists:tours,id',
            ],
            'full_name' => [
                'required',
                'string',
                'max:255',
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
            ],
        ];

        // Add capacity validation when tour_id is provided
        if (isset($context['tour_id'])) {
            $tour = Tour::find($context['tour_id']);
            if ($tour && $tour->max_capacity) {
                $rules['passenger_count'][] = function ($attribute, $value, $fail) use ($tour) {
                    $currentConfirmed = $tour->confirmedPassengers->sum('passenger_count');
                    $requestedTotal = $currentConfirmed + $value;

                    if ($requestedTotal > $tour->max_capacity) {
                        $remaining = $tour->max_capacity - $currentConfirmed;
                        return $fail("Cannot book {$value} passengers. Only {$remaining} spots remaining out of {$tour->max_capacity} total capacity.");
                    }

                    return true;
                };
            }
        }

        return $rules;
    }

    protected $casts = [
        'passenger_count' => 'integer',
        'registration_date' => 'date',
    ];

    protected $dates = [
        'registration_date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $passenger) {
            if (! $passenger->passenger_code) {
                $lastPassenger = static::orderBy('id', 'desc')->first();
                $lastCode = $lastPassenger ? intval(substr($lastPassenger->passenger_code, 3)) : 0;
                $prefix = config('finot.tour_passenger_code_prefix', 'TP-');
                $passenger->passenger_code = $prefix . str_pad($lastCode + 1, 6, '0', STR_PAD_LEFT);
            }

            if ($passenger->phone) {
                $passenger->total_tours = static::where('phone', $passenger->phone)
                    ->where('tour_id', '!=', $passenger->tour_id ?? 0)
                    ->distinct('tour_id')
                    ->count('tour_id') + 1;
            }
        });
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
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

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'Pending' => 'yellow',
            'Confirmed' => 'green',
            'Cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get registration type badge color
     */
    public function getRegistrationTypeColorAttribute(): string
    {
        return match($this->registration_type) {
            'Public' => 'blue',
            'Internal' => 'purple',
            default => 'gray',
        };
    }

    /**
     * Get receipt URL
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (! $this->receipt_image) {
            return null;
        }

        // Handle both old format (just filename) and new format (full path)
        if (str_starts_with($this->receipt_image, 'receipts/tours/')) {
            return asset('storage/' . $this->receipt_image);
        }

        return asset('storage/receipts/tours/' . $this->tour_id . '/' . $this->receipt_image);
    }

    /**
     * Confirm passenger registration
     */
    public function confirm(): void
    {
        $this->update(['status' => 'Confirmed']);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_passenger_confirmed',
            'entity_id' => $this->id,
            'entity_type' => 'tour_passenger',
            'old_value' => json_encode(['status' => 'Pending']),
            'new_value' => json_encode(['status' => 'Confirmed']),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Cancel passenger registration
     */
    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'Cancelled',
            'cancellation_reason' => $reason,
        ]);

        // Log to audit trail
        Log::channel('audit')->info('Tier 1 Audit Log', [
            'tier' => 1,
            'action' => 'tour_passenger_cancelled',
            'entity_id' => $this->id,
            'entity_type' => 'tour_passenger',
            'old_value' => json_encode(['status' => $this->getOriginal('status')]),
            'new_value' => json_encode(['status' => 'Cancelled', 'reason' => $reason]),
            'user_id' => Auth::id(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
