<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing subjects
        DB::table('subjects')->delete();

        // Reset auto-increment
        DB::statement('ALTER TABLE subjects AUTO_INCREMENT = 1');

        // Get admin user for created_by (match email created by UserSeeder)
        $adminUser = User::where('email', 'admin@finot.org')->first()
            ?? User::first();

        if (! $adminUser) {
            $adminUser = User::create([
                'name' => 'Default Admin',
                'email' => 'admin@finot.org',
                'phone' => '+251911000000',
                'password' => \Illuminate\Support\Facades\Hash::make('Admin1234'),
                'is_active' => true,
                'is_locked' => false,
                'failed_login_attempts' => 0,
                'temp_password_changed' => true,
                'password_history' => [],
                'language_preference' => 'en',
                'department_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $createdBy = $adminUser->id;

        // Ethiopian Orthodox subjects
        $subjects = [
            [
                'name' => 'የእግዜር መጽሐፍቲ ትምህርት',
                'description' => 'Old Testament Studies - Comprehensive study of the Old Testament scriptures, prophets, and teachings of the Ethiopian Orthodox Church',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የአዲስ ኪዳን መጽሐፍቲ ትምህርት',
                'description' => 'New Testament Studies - Study of the New Testament, teachings of Jesus Christ, and apostolic writings',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የኦሪየን ተማሪያን ትምህርት',
                'description' => 'Church Fathers Studies - Learning from the teachings and writings of Ethiopian Church Fathers and saints',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የተረት ትምህርት',
                'description' => 'Canon Law Studies - Understanding the canonical laws and regulations of the Ethiopian Orthodox Church',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የምስጋን ትምህርት',
                'description' => 'Liturgy Studies - Study of church services, sacraments, and liturgical practices',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የማርያም ዘማሪያን ትምህርት',
                'description' => 'Mariamology - Study of the Virgin Mary, her role in salvation, and Marian traditions',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የመዝሙር ትምህርት',
                'description' => 'Hymnology Studies - Learning Ethiopian Orthodox church music, Zema, and traditional hymns',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የቤተ ክርስቲያን ታሪክ',
                'description' => 'Church History - Study of Ethiopian Orthodox Church history from ancient times to present',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የእለም ክርስትያን ድንጋግ',
                'description' => 'Christian Ethics - Study of moral principles and ethical teachings based on Orthodox Christianity',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'የአምሣል ትምህርት',
                'description' => 'Iconography Studies - Study of Ethiopian Orthodox church icons, symbolism, and religious art',
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('subjects')->insert($subjects);

        $this->command->info('Seeded ' . count($subjects) . ' Ethiopian Orthodox subjects successfully.');
    }
}
