<?php

namespace Database\Seeders;

use App\Models\SchoolDirectoryEntry;
use Illuminate\Database\Seeder;

/**
 * Starter directory of well-known Ethiopian schools (off-platform), so the
 * "previous school" picker is useful from day one. No official public MoE
 * registry exists, so this list grows two ways: Temari-hosted schools get an
 * auto row (CreateSchoolAction), and registrars add missing schools inline as
 * unverified entries that platform staff verify later. Idempotent.
 */
class SchoolDirectorySeeder extends Seeder
{
    public function run(): void
    {
        // [name, region, city]
        $schools = [
            // Addis Ababa — well-known public & private schools
            ['Addis Ababa Lycée Guebre-Mariam', 'Addis Ababa', 'Addis Ababa'],
            ['St. Joseph School', 'Addis Ababa', 'Addis Ababa'],
            ['Sandford International School', 'Addis Ababa', 'Addis Ababa'],
            ['International Community School of Addis Ababa', 'Addis Ababa', 'Addis Ababa'],
            ['Nazareth School', 'Addis Ababa', 'Addis Ababa'],
            ['Cathedral School', 'Addis Ababa', 'Addis Ababa'],
            ['Menelik II Secondary School', 'Addis Ababa', 'Addis Ababa'],
            ['Kokebe Tsibah Secondary School', 'Addis Ababa', 'Addis Ababa'],
            ['Bole Community School', 'Addis Ababa', 'Addis Ababa'],
            ['Hillside School', 'Addis Ababa', 'Addis Ababa'],
            ['One Planet International School', 'Addis Ababa', 'Addis Ababa'],
            ['Safari Academy', 'Addis Ababa', 'Addis Ababa'],
            ['School of Tomorrow', 'Addis Ababa', 'Addis Ababa'],
            ['Andinet International School', 'Addis Ababa', 'Addis Ababa'],
            ["Ethio Parents' School", 'Addis Ababa', 'Addis Ababa'],
            ['Dandii Boru School', 'Addis Ababa', 'Addis Ababa'],
            ['Abune Gorgorios School', 'Addis Ababa', 'Addis Ababa'],
            ['Medhanealem Secondary School', 'Addis Ababa', 'Addis Ababa'],
            ['Entoto Amba Secondary School', 'Addis Ababa', 'Addis Ababa'],
            ['Yekatit 12 Secondary School', 'Addis Ababa', 'Addis Ababa'],

            // Regional cities
            ['Hawassa Tabor Secondary School', 'Sidama', 'Hawassa'],
            ['Adama Model Secondary School', 'Oromia', 'Adama'],
            ['Bahir Dar Academy', 'Amhara', 'Bahir Dar'],
            ['Tana Haik Secondary School', 'Amhara', 'Bahir Dar'],
            ['Fasilides Secondary School', 'Amhara', 'Gondar'],
            ['Mekelle Elementary and Secondary School', 'Tigray', 'Mekelle'],
            ['Atse Yohannes Secondary School', 'Tigray', 'Mekelle'],
            ['Jimma Model Secondary School', 'Oromia', 'Jimma'],
            ['Dire Dawa Comprehensive Secondary School', 'Dire Dawa', 'Dire Dawa'],
            ['Harar Senior Secondary School', 'Harari', 'Harar'],
            ['Arba Minch Secondary School', 'South Ethiopia', 'Arba Minch'],
            ['Dessie Comprehensive Secondary School', 'Amhara', 'Dessie'],
            ['Jijiga Secondary School', 'Somali', 'Jijiga'],
            ['Asosa Secondary School', 'Benishangul-Gumuz', 'Asosa'],
            ['Gambella Secondary School', 'Gambella', 'Gambella'],
            ['Semera Secondary School', 'Afar', 'Semera'],
            ['Debre Birhan Model Secondary School', 'Amhara', 'Debre Birhan'],
            ['Shashemene Secondary School', 'Oromia', 'Shashemene'],
            ['Wolaita Sodo Secondary School', 'South Ethiopia', 'Sodo'],
            ['Nekemte Comprehensive Secondary School', 'Oromia', 'Nekemte'],
        ];

        foreach ($schools as [$name, $region, $city]) {
            SchoolDirectoryEntry::withTrashed()->updateOrCreate(
                ['name' => $name, 'region' => $region],
                ['city' => $city, 'is_verified' => true, 'deleted_at' => null],
            );
        }
    }
}
