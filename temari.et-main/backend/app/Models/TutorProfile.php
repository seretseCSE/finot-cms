<?php

namespace App\Models;

use App\Enums\TutorStatus;
use App\Support\Marketplace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * The tutor marketplace identity (see the migration). Relationship-lane
 * access (ADR-012): owning THIS row is what makes a user a tutor — no
 * membership ever. Aggregates (rating, hours, wallet) have single writers
 * (TutorRating / TutorLedger / CycleReleaser); nothing else may set them.
 */
#[Fillable([
    'user_id', 'headline', 'bio', 'video_url', 'hourly_rate', 'additional_child_rate',
    'mode', 'region', 'city', 'sub_city', 'languages', 'education_level',
    'experience_years', 'fayda_id', 'fayda_hash', 'status', 'submitted_at',
    'reviewed_at', 'reviewed_by', 'decline_reason', 'suspend_reason', 'slug',
    'payout_bank_code', 'payout_bank_name', 'payout_account_number', 'payout_account_name',
    'commission_percent', 'boosted_until',
])]
#[Hidden(['fayda_id', 'fayda_hash'])]
class TutorProfile extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TutorStatus::class,
            'fayda_id' => 'encrypted',
            'languages' => 'array',
            'hourly_rate' => 'decimal:2',
            'additional_child_rate' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'hours_taught' => 'decimal:1',
            'wallet_balance' => 'decimal:2',
            'experience_years' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'boosted_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<TutorSubject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(TutorSubject::class);
    }

    /**
     * @return HasMany<TutorAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TutorAttachment::class);
    }

    /**
     * @return HasMany<TutoringEngagement, $this>
     */
    public function engagements(): HasMany
    {
        return $this->hasMany(TutoringEngagement::class);
    }

    /**
     * @return HasMany<TutorReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(TutorReview::class);
    }

    /**
     * @return HasMany<TutorLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TutorLedgerEntry::class);
    }

    /** Directory visibility: approved profiles only. */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('status', TutorStatus::Approved->value)
            ->whereNotNull('hourly_rate');
    }

    public function isBoosted(): bool
    {
        return $this->boosted_until !== null && $this->boosted_until->isFuture();
    }

    /** Per-tutor override wins; else the platform knob. */
    public function effectiveCommissionPercent(): float
    {
        return $this->commission_percent !== null
            ? (float) $this->commission_percent
            : Marketplace::commissionPercent();
    }

    /** SEO slug from the person's name, allocated once at approval. */
    public function allocateSlug(): string
    {
        $base = Str::slug(trim(($this->user->first_name ?? 'tutor').' '.($this->user->father_name ?? '')));
        $base = $base !== '' ? $base : 'tutor';
        $slug = $base;

        for ($i = 2; self::query()->where('slug', $slug)->whereKeyNot($this->id)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public static function hashFayda(string $faydaId): string
    {
        return hash('sha256', preg_replace('/\D+/', '', $faydaId) ?? $faydaId);
    }
}
