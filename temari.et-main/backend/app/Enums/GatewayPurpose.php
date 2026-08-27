<?php

namespace App\Enums;

/**
 * What a gateway payment is FOR. The gateway collects Temari.et's own money
 * only — tutoring escrow, the parent/student AI upgrade, tutor profile
 * boosts, School Plan billing. School fee payments NEVER flow through a
 * gateway (hard rule, CLAUDE.md §11), and the 200 ETB/student/yr core fee is
 * collected by the schools themselves for now.
 *
 * Which gateways serve which purpose is an operator decision — the purposes
 * matrix lives in the `payments.gateways` platform setting (PaymentGateways).
 */
enum GatewayPurpose: string
{
    case TutoringCycle = 'tutoring_cycle';
    case AiSubscription = 'ai_subscription';
    case ProfileBoost = 'profile_boost';
    case SchoolPlan = 'school_plan';

    public function label(): string
    {
        return match ($this) {
            self::TutoringCycle => 'Tutoring payments',
            self::AiSubscription => 'AI exam-prep subscription',
            self::ProfileBoost => 'Tutor profile boosts',
            self::SchoolPlan => 'School Plan billing',
        };
    }
}
