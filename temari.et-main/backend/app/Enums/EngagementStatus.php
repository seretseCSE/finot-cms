<?php

namespace App\Enums;

/**
 * A tutoring contract's lifecycle. Rates/commission are SNAPSHOTTED on the
 * engagement at acceptance — later profile or policy changes never rewrite
 * a running contract. Paused engagements skip cycle generation; ended ones
 * are terminal (history + wallet remain).
 */
enum EngagementStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
}
