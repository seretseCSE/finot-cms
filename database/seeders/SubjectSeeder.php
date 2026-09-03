<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('subjects')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE subjects AUTO_INCREMENT = 1');
        }

        $adminUser = User::where('email', 'admin@finot.org')->first()
            ?? User::first();

        if (! $adminUser) {
            $adminUser = User::create([
                'name' => 'Default Admin',
                'email' => 'admin@finot.org',
                'phone' => '+251911000000',
                'password' => Hash::make('Admin1234'),
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
        $hasYear = Schema::hasColumn('subjects', 'program_year');
        $hasSemester = Schema::hasColumn('subjects', 'semester_number');
        $hasMax = Schema::hasColumn('subjects', 'max_score');

        $catalog = [
            [1, 1, 'የእግዜር መጽሐፍቲ ትምህርት', 'Old Testament Studies'],
            [1, 1, 'የቤተ ክርስቲያን ታሪክ መግቢያ', 'Introduction to Church History'],
            [1, 2, 'የአዲስ ኪዳን መጽሐፍቲ ትምህርት', 'New Testament Studies'],
            [1, 2, 'የእለም ክርስትያን ድንጋግ', 'Christian Ethics'],
            [2, 3, 'የኦሪየን ተማሪያን ትምህርት', 'Church Fathers Studies'],
            [2, 3, 'የምስጋን ትምህርት', 'Liturgy Studies'],
            [2, 4, 'የተረት ትምህርት', 'Canon Law Studies'],
            [2, 4, 'የማርያም ዘማሪያን ትምህርት', 'Mariamology'],
            [3, 5, 'የመዝሙር ትምህርት', 'Hymnology Studies'],
            [3, 5, 'የአምሣል ትምህርት', 'Iconography Studies'],
            [3, 6, 'የቤተ ክርስቲያን ታሪክ', 'Church History'],
            [3, 6, 'የግዕዝ ቋንቋ', 'Geʽez Language'],
            [4, 7, 'የሥነ መለኮት ትምህርት', 'Dogmatic Theology'],
            [4, 7, 'የአርብቶ አደር አገልግሎት', 'Pastoral Care'],
            [4, 8, 'የስብከት ትምህርት', 'Homiletics'],
            [4, 8, 'የቤተ ክርስቲያን አስተዳደር', 'Church Administration'],
            [5, 9, 'የቅዱሳን አባቶች ጥናት', 'Patristics Seminar'],
            [5, 9, 'የቅዳሴ ዜማ', 'Liturgical Music'],
            [5, 10, 'የወንጌል ስርጭት', 'Mission Studies'],
            [5, 10, 'የመዝጊያ ጥናት', 'Comprehensive Review'],
        ];

        $subjects = [];
        foreach ($catalog as [$year, $semester, $name, $description]) {
            $row = [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasYear) {
                $row['program_year'] = $year;
            }
            if ($hasSemester) {
                $row['semester_number'] = $semester;
            }
            if ($hasMax) {
                $row['max_score'] = 100;
            }
            $subjects[] = $row;
        }

        DB::table('subjects')->insert($subjects);

        $this->command?->info('Seeded '.count($subjects).' subjects across 5 program years / 10 semesters.');
    }
}
