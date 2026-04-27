<?php

namespace Database\Seeders;

use App\Models\CustomOption;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomOptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superadmin = User::whereHas('roles', function ($query) {
            $query->where('name', 'Superadmin');
        })->first();

        if (!$superadmin) {
            return;
        }

        // Sample custom options for different fields
        $options = [
            [
                'field_name' => 'department',
                'option_value' => 'Finance',
                'status' => 'approved',
                'added_by' => $superadmin->id,
                'usage_count' => 5,
            ],
            [
                'field_name' => 'department',
                'option_value' => 'Marketing',
                'status' => 'approved',
                'added_by' => $superadmin->id,
                'usage_count' => 3,
            ],
            [
                'field_name' => 'priority',
                'option_value' => 'High',
                'status' => 'approved',
                'added_by' => $superadmin->id,
                'usage_count' => 12,
            ],
            [
                'field_name' => 'priority',
                'option_value' => 'Medium',
                'status' => 'approved',
                'added_by' => $superadmin->id,
                'usage_count' => 8,
            ],
            [
                'field_name' => 'priority',
                'option_value' => 'Low',
                'status' => 'approved',
                'added_by' => $superadmin->id,
                'usage_count' => 4,
            ],
            [
                'field_name' => 'category',
                'option_value' => 'Education',
                'status' => 'pending',
                'added_by' => $superadmin->id,
                'usage_count' => 0,
            ],
            [
                'field_name' => 'category',
                'option_value' => 'Administration',
                'status' => 'pending',
                'added_by' => $superadmin->id,
                'usage_count' => 0,
            ],
            [
                'field_name' => 'category',
                'option_value' => 'Outreach',
                'status' => 'rejected',
                'added_by' => $superadmin->id,
                'usage_count' => 0,
            ],
        ];

        foreach ($options as $option) {
            CustomOption::create($option);
        }
    }
}
