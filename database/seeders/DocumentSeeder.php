<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::query()->pluck('id');
        $users = User::query()->pluck('id');

        if ($departments->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Skipping DocumentSeeder: no departments or users found.');

            return;
        }

        $visibilities = ['Public', 'Members Only', 'Department Only'];
        $fileTypes = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png'];

        foreach (range(1, 20) as $i) {
            $departmentId = $departments->random();
            $uploaderId = $users->random();
            $fileType = $fileTypes[array_rand($fileTypes)];

            Document::factory()->create([
                'department_id' => $departmentId,
                'uploaded_by' => $uploaderId,
                'visibility' => $visibilities[array_rand($visibilities)],
                'file_type' => $fileType,
                'file_path' => 'seeded-document-'.$i.'.'.$fileType,
                'file_size_kb' => rand(100, 5000),
            ]);
        }

        $this->command->info('Seeded 20 documents successfully.');
    }
}
