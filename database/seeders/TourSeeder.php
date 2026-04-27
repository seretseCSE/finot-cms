<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourPassenger;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing tour data
        DB::table('tour_passengers')->delete();
        DB::table('tours')->delete();

        $creator = User::first();

        // Create tours
        $tours = [
            [
                'place' => 'Lalibela',
                'description' => 'Journey to the sacred rock-hewn churches of Lalibela, one of Ethiopia\'s most important pilgrimage sites. Experience the spiritual significance and architectural marvels of these ancient churches carved from solid rock.',
                'tour_date' => now()->addMonths(2),
                'start_time' => '06:00',
                'cost_per_person' => 2500.00,
                'registration_deadline' => now()->addMonth(),
                'max_capacity' => 45,
                'status' => 'Published',
                'created_by' => $creator->id,
            ],
            [
                'place' => 'Axum',
                'description' => 'Explore the ancient city of Axum, home to the legendary Queen of Sheba and the supposed resting place of the Ark of the Covenant. Discover obelisks, ancient ruins, and rich biblical history.',
                'tour_date' => now()->addMonths(3),
                'start_time' => '05:30',
                'cost_per_person' => 3200.00,
                'registration_deadline' => now()->addMonths(2),
                'max_capacity' => 40,
                'status' => 'Published',
                'created_by' => $creator->id,
            ],
            [
                'place' => 'Lake Tana Monasteries',
                'description' => 'Visit the historic monasteries on Lake Tana\'s islands, including the storied Tana Kirkos where the Ark of the Covenant was allegedly kept for centuries. Experience ancient manuscripts and religious artifacts.',
                'tour_date' => now()->addMonths(1),
                'start_time' => '07:00',
                'cost_per_person' => 1800.00,
                'registration_deadline' => now()->addWeeks(2),
                'max_capacity' => 35,
                'status' => 'Published',
                'created_by' => $creator->id,
            ],
            [
                'place' => 'Debre Libanos',
                'description' => 'A spiritual journey to the Debre Libanos Monastery, founded by Saint Tekle Haymanot in the 13th century. Experience the breathtaking Portuguese Bridge and the rich monastic history.',
                'tour_date' => now()->addWeeks(3),
                'start_time' => '08:00',
                'cost_per_person' => 800.00,
                'registration_deadline' => now()->addWeeks(1),
                'max_capacity' => 50,
                'status' => 'Published',
                'created_by' => $creator->id,
            ],
            [
                'place' => 'Mount Entoto',
                'description' => 'A pilgrimage to Mount Entoto, where Emperor Menelik II established Addis Ababa. Visit St. Mary Church, Menelik\'s Palace, and enjoy panoramic views of the capital city.',
                'tour_date' => now()->addWeeks(2),
                'start_time' => '09:00',
                'cost_per_person' => 500.00,
                'registration_deadline' => now()->addDays(5),
                'max_capacity' => 60,
                'status' => 'Published',
                'created_by' => $creator->id,
            ],
        ];

        $createdTours = [];
        foreach ($tours as $tourData) {
            $tour = Tour::create($tourData);
            $createdTours[] = $tour;
        }

        // Create some tour passengers for demonstration
        foreach ($createdTours as $index => $tour) {
            // Add 5-10 passengers per tour
            $passengerCount = rand(5, 10);

            for ($i = 1; $i <= $passengerCount; $i++) {
                TourPassenger::create([
                    'tour_id' => $tour->id,
                    'passenger_code' => 'TP' . str_pad(($index + 1) . $i, 4, '0', STR_PAD_LEFT),
                    'full_name' => "Passenger " . chr(65 + $i) . " Tour" . ($index + 1),
                    'phone' => '9' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                    'passenger_count' => rand(1, 3),
                    'registration_type' => rand(1, 100) <= 70 ? 'Internal' : 'Public',
                    'status' => 'Confirmed',
                    'registration_date' => now()->subDays(rand(1, 20)),
                    'registered_by' => $creator->id,
                ]);
            }
        }

        $this->command->info('5 tours with passengers seeded successfully!');
    }
}
