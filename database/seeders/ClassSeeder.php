<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::where('email', 'admin@finot.org')->first()?->id
            ?? User::first()?->id
            ?? 1;

        DB::table('classes')->delete();

        $classes = [];
        for ($year = 1; $year <= 5; $year++) {
            $payload = [
                'name' => "Year {$year}",
                'description' => "Program year {$year} (university-style progression)",
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('classes', 'program_year')) {
                $payload['program_year'] = $year;
            }

            $classes[] = $payload;
        }

        DB::table('classes')->insert($classes);

        $this->command?->info('Created 5 program years (Year 1 to Year 5).');
    }
}
