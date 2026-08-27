<?php

namespace App\Enums;

/**
 * How a fee is charged. Registration is the minimal shape (name, year, amount,
 * grades); every other type may carry a billing window, notifications, and a
 * late penalty.
 */
enum FeeType: string
{
    case Registration = 'registration';
    case OneTime = 'one_time';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semester = 'semester';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registration',
            self::OneTime => 'One-time',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::Semester => 'Per semester',
            self::Yearly => 'Yearly',
        };
    }

    /** Registration fees carry no schedule, notifications, or penalties. */
    public function isScheduled(): bool
    {
        return $this !== self::Registration;
    }
}
