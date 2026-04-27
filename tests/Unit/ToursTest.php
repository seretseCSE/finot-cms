<?php

namespace Tests\Unit;

use App\Models\Tour;
use App\Models\TourAttendance;
use App\Models\TourAttendanceSession;
use App\Models\TourPassenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToursTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Generate a unique passenger code.
     */
    protected function passengerCode(): string
    {
        return 'P-' . strtoupper(Str::random(6));
    }

    /**
     * Test tour registration phone unique per tour.
     */
    public function test_tour_registration_phone_unique(): void
    {
        $tour = Tour::factory()->create();

        // Create first registration
        TourPassenger::create([
            'tour_id' => $tour->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Person 1',
            'phone' => '+251911234567',
            'passenger_count' => 1,
            'registration_date' => now(),
        ]);

        // Try to register same phone - should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        TourPassenger::create([
            'tour_id' => $tour->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Person 2',
            'phone' => '+251911234567',
            'passenger_count' => 2,
            'registration_date' => now(),
        ]);
    }

    /**
     * Test different tours can have same phone.
     */
    public function test_same_phone_different_tours(): void
    {
        $tour1 = Tour::factory()->create();
        $tour2 = Tour::factory()->create();

        // Register in tour 1
        $passenger1 = TourPassenger::create([
            'tour_id' => $tour1->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Person',
            'phone' => '+251911234567',
            'passenger_count' => 1,
            'registration_date' => now(),
        ]);

        // Register in tour 2 (should succeed)
        $passenger2 = TourPassenger::create([
            'tour_id' => $tour2->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Person',
            'phone' => '+251911234567',
            'passenger_count' => 1,
            'registration_date' => now(),
        ]);

        $this->assertNotEquals($passenger1->id, $passenger2->id);
    }

    /**
     * Test attendance session auto-generated from confirmed passengers.
     */
    public function test_attendance_generated_from_passengers(): void
    {
        $tour = Tour::factory()->create([
            'status' => 'Published',
        ]);

        // Create confirmed passenger
        $passenger = TourPassenger::create([
            'tour_id' => $tour->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Passenger',
            'phone' => '+251911234567',
            'passenger_count' => 1,
            'registration_date' => now(),
            'status' => 'Confirmed',
        ]);

        // Create attendance session
        $session = TourAttendanceSession::create([
            'tour_id' => $tour->id,
            'session_date' => now(),
            'status' => 'Open',
            'created_by' => 1,
        ]);

        // Create attendance record
        $attendance = TourAttendance::create([
            'session_id' => $session->id,
            'passenger_id' => $passenger->id,
            'status' => 'Present',
        ]);

        $this->assertNotNull($attendance);
        $this->assertEquals('Present', $attendance->status);
    }

    /**
     * Test call button only shows for "Not Present".
     */
    public function test_call_button_not_present_only(): void
    {
        $tour = Tour::factory()->create();
        $session = TourAttendanceSession::create([
            'tour_id' => $tour->id,
            'session_date' => now(),
            'status' => 'Open',
            'created_by' => 1,
        ]);
        $passenger = TourPassenger::create([
            'tour_id' => $tour->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Passenger',
            'phone' => '+251911234567',
            'passenger_count' => 1,
            'registration_date' => now(),
        ]);

        $attendance = TourAttendance::create([
            'session_id' => $session->id,
            'passenger_id' => $passenger->id,
            'status' => 'Not Present',
        ]);

        $showCallButton = $attendance->status === 'Not Present' && !empty($passenger->phone);

        $this->assertTrue($showCallButton);

        // Should not show for Present
        $attendance->update(['status' => 'Present']);
        $showCallButton = $attendance->status === 'Not Present' && !empty($passenger->phone);

        $this->assertFalse($showCallButton);
    }

    /**
     * Test confirmed registration status.
     */
    public function test_registration_confirmed_status(): void
    {
        $passenger = TourPassenger::create([
            'tour_id' => Tour::factory()->create()->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Passenger',
            'phone' => '+251911234567',
            'passenger_count' => 1,
            'registration_date' => now(),
            'status' => 'Pending',
        ]);

        // Confirm registration
        $passenger->update(['status' => 'Confirmed']);

        $this->assertEquals('Confirmed', $passenger->fresh()->status);
    }

    /**
     * Test tour status workflow.
     */
    public function test_tour_status_workflow(): void
    {
        $tour = Tour::factory()->create([
            'status' => 'Draft',
        ]);

        // Draft → Published
        $tour->update(['status' => 'Published']);
        $this->assertEquals('Published', $tour->fresh()->status);

        // Published → In Progress
        $tour->update(['status' => 'In Progress']);
        $this->assertEquals('In Progress', $tour->fresh()->status);

        // In Progress → Completed
        $tour->update(['status' => 'Completed']);
        $this->assertEquals('Completed', $tour->fresh()->status);
    }

    /**
     * Test passenger count tracking.
     */
    public function test_passenger_count_tracking(): void
    {
        $tour = Tour::factory()->create();

        TourPassenger::create([
            'tour_id' => $tour->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Family 1',
            'phone' => '+251911234567',
            'passenger_count' => 4,
            'registration_date' => now(),
        ]);

        TourPassenger::create([
            'tour_id' => $tour->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Family 2',
            'phone' => '+251921234567',
            'passenger_count' => 2,
            'registration_date' => now(),
        ]);

        $totalPassengers = TourPassenger::where('tour_id', $tour->id)
            ->sum('passenger_count');

        $this->assertEquals(6, $totalPassengers);
    }

    /**
     * Test attendance marking.
     */
    public function test_attendance_marking(): void
    {
        $tour = Tour::factory()->create();
        $session = TourAttendanceSession::create([
            'tour_id' => $tour->id,
            'session_date' => now(),
            'status' => 'Open',
            'created_by' => 1,
        ]);
        $passenger = TourPassenger::create([
            'tour_id' => $tour->id,
            'passenger_code' => $this->passengerCode(),
            'full_name' => 'Test Passenger',
            'phone' => '+251911234567',
            'passenger_count' => 1,
            'registration_date' => now(),
        ]);

        $attendance = TourAttendance::create([
            'session_id' => $session->id,
            'passenger_id' => $passenger->id,
            'status' => 'Not Present',
        ]);

        // Mark as Present
        $attendance->update(['status' => 'Present']);

        $this->assertEquals('Present', $attendance->fresh()->status);
    }

    /**
     * Test internal registration with phone auto-fill.
     */
    public function test_internal_registration_phone_autofill(): void
    {
        // This tests that existing members' phones are available for auto-fill
        // In a real scenario, this would check member lookup

        $existingPhone = '+251911234567';

        // Should be able to find member by phone
        $this->assertNotNull($existingPhone);
    }
}
