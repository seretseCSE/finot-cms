<?php

namespace Database\Seeders;

use App\Enums\HealthConditionCategory;
use App\Models\HealthCondition;
use Illuminate\Database\Seeder;

/**
 * Platform catalog of health conditions commonly tracked by K-12 school
 * systems, adapted to the Ethiopian context. Registrars pick from this list
 * during registration; "Other" categories carry the specifics in the
 * per-student notes. Idempotent — safe to re-run.
 */
class HealthConditionSeeder extends Seeder
{
    public function run(): void
    {
        $conditions = [
            // Allergies
            ['Food allergy', HealthConditionCategory::Allergy],
            ['Drug / medication allergy', HealthConditionCategory::Allergy],
            ['Insect sting allergy', HealthConditionCategory::Allergy],
            ['Severe allergy (anaphylaxis risk)', HealthConditionCategory::Allergy],
            ['Allergic rhinitis / hay fever', HealthConditionCategory::Allergy],

            // Chronic conditions
            ['Asthma', HealthConditionCategory::Chronic],
            ['Diabetes (Type 1)', HealthConditionCategory::Chronic],
            ['Diabetes (Type 2)', HealthConditionCategory::Chronic],
            ['Heart condition', HealthConditionCategory::Chronic],
            ['Kidney condition', HealthConditionCategory::Chronic],
            ['Tuberculosis (under treatment)', HealthConditionCategory::Chronic],
            ['Chronic skin condition (eczema, psoriasis)', HealthConditionCategory::Chronic],
            ['Frequent migraines', HealthConditionCategory::Chronic],

            // Neurological
            ['Epilepsy / seizure disorder', HealthConditionCategory::Neurological],
            ['Cerebral palsy', HealthConditionCategory::Neurological],

            // Physical
            ['Physical disability / mobility impairment', HealthConditionCategory::Physical],
            ['Amputation / limb difference', HealthConditionCategory::Physical],

            // Sensory
            ['Visual impairment', HealthConditionCategory::Sensory],
            ['Blindness', HealthConditionCategory::Sensory],
            ['Hearing impairment', HealthConditionCategory::Sensory],
            ['Deafness', HealthConditionCategory::Sensory],
            ['Speech difficulty', HealthConditionCategory::Sensory],

            // Mental health & development
            ['ADHD', HealthConditionCategory::MentalHealth],
            ['Autism spectrum', HealthConditionCategory::MentalHealth],
            ['Learning difficulty (dyslexia, dyscalculia)', HealthConditionCategory::MentalHealth],
            ['Intellectual disability', HealthConditionCategory::MentalHealth],
            ['Anxiety / depression', HealthConditionCategory::MentalHealth],

            // Blood disorders
            ['Sickle cell disease', HealthConditionCategory::Blood],
            ['Hemophilia', HealthConditionCategory::Blood],
            ['Anemia (chronic)', HealthConditionCategory::Blood],

            // Other
            ['Other (see notes)', HealthConditionCategory::Other],
        ];

        foreach ($conditions as [$name, $category]) {
            HealthCondition::withTrashed()->updateOrCreate(
                ['name' => $name],
                ['category' => $category->value, 'is_active' => true, 'deleted_at' => null],
            );
        }
    }
}
