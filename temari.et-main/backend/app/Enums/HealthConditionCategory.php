<?php

namespace App\Enums;

enum HealthConditionCategory: string
{
    case Allergy = 'allergy';
    case Chronic = 'chronic';
    case Neurological = 'neurological';
    case Physical = 'physical';
    case Sensory = 'sensory';
    case MentalHealth = 'mental_health';
    case Blood = 'blood';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Allergy',
            self::Chronic => 'Chronic condition',
            self::Neurological => 'Neurological',
            self::Physical => 'Physical',
            self::Sensory => 'Vision / hearing',
            self::MentalHealth => 'Mental health & development',
            self::Blood => 'Blood disorder',
            self::Other => 'Other',
        };
    }
}
