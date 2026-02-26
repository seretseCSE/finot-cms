<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user with temporary password
        User::create([
            'name' => 'Test User',
            'phone' => '+251912345678',
            'email' => 'test@example.com',
            'password' => Hash::make('temp123'), // Temporary password
            'temp_password_changed' => false, // Force password change
            'is_active' => true,
            'language_preference' => 'am',
        ]);

        // Create a user with already changed password
        User::create([
            'name' => 'Regular User',
            'phone' => '+251923456789',
            'email' => 'user@example.com',
            'password' => Hash::make('SecurePass123!'),
            'temp_password_changed' => true, // No password change required
            'is_active' => true,
            'language_preference' => 'en',
        ]);
    }
}
