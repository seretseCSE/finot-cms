<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One raw card tap as reported by a terminal. Immutable except for the
 * processing outcome columns (status, id_card_id, holder) stamped by
 * ProcessDeviceEventsJob.
 */
#[Fillable([
    'device_id', 'school_id', 'branch_id', 'card_uid', 'event_uid',
    'scanned_at', 'received_at', 'id_card_id', 'holder_type', 'holder_id', 'status',
])]
class DeviceEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_UNKNOWN_CARD = 'unknown_card';

    public const STATUS_INACTIVE_CARD = 'inactive_card';

    public const STATUS_NO_ENROLLMENT = 'no_enrollment';

    public const STATUS_CLOSED_TERM = 'closed_term';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }
}
