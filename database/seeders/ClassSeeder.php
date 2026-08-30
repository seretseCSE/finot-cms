<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $createdBy = User::where('email', 'admin@finot.org')->first()?->id
            ?? User::first()?->id
            ?? 1;

        DB::table('classes')->delete();

        $classes = [];
        for ($i = 1; $i <= 12; $i++) {
            $classes[] = [
                'name' => "Grade {$i}",
                'description' => "Grade {$i} class",
                'is_active' => true,
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('classes')->insert($classes);

        $this->command?->info('Created 12 classes (Grade 1 to Grade 12).');
    }
}
