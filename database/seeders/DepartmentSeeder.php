<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing departments
        DB::table('departments')->delete();
        
        // Reset auto-increment
        DB::statement('ALTER TABLE departments AUTO_INCREMENT = 1');
        
        // Seed the 7 fixed departments
        $departments = [
            [
                'name_en' => 'Internal Relations',
                'name_am' => 'ውስጣዊ ግንኙነት',
                'description' => 'Manages HR, AV, media, blog',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_en' => 'Nibret ena Hisab',
                'name_am' => 'ንብረትና ሂሳብ',
                'description' => 'Finance and Inventory',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_en' => 'Education',
                'name_am' => 'ትምህርት',
                'description' => 'Sunday school, classes, teachers',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_en' => 'Revenue & Charity',
                'name_am' => 'ገቢና ልግስና',
                'description' => 'Charity, Tours',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_en' => 'Mezmur',
                'name_am' => 'መዝሙር',
                'description' => 'Worship/Choir',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_en' => 'Foreign Affairs',
                'name_am' => 'የውጭ ጉዳይ',
                'description' => 'Generic department',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name_en' => 'Kinetibeb',
                'name_am' => 'ቅን ጠባይ',
                'description' => 'Generic department',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('departments')->insert($departments);
        
        $this->command->info('Seeded 7 fixed departments successfully.');
    }
}
