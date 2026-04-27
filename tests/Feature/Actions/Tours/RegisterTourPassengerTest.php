<?php

namespace Tests\Feature\Actions\Tours;

use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTourPassengerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[Test]
    public function registration_creates_primary_and_guest_passengers_with_sequential_codes(): void
    {
        $tour = Tour::factory()->published()->create([
            'max_capacity' => 10,
            'registration_deadline' => now()->addWeek(),
        ]);

        $response = $this->post(route('tour.register.submit', $tour), [
            'full_name' => 'John Doe',
            'phone' => '912345678',
            'passenger_count' => 3,
            'passenger_names' => ['Jane Doe', 'Jim Doe'],
            'receipt_image' => null,
            'honeypot' => '',
        ]);

        $response->assertRedirect(route('tours.index'));
        $response->assertSessionHas('success');

        $prefix = config('finot.tour_passenger_code_prefix', 'TP-');

        $passengers = TourPassenger::where('tour_id', $tour->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(3, $passengers);

        // Primary passenger
        $this->assertEquals('John Doe', $passengers[0]->full_name);
        $this->assertEquals(config('finot.phone_prefix', '+251').'912345678', $passengers[0]->phone);
        $this->assertEquals(1, $passengers[0]->passenger_count);
        $this->assertStringStartsWith($prefix, $passengers[0]->passenger_code);

        // Guest passengers
        $this->assertEquals('Jane Doe', $passengers[1]->full_name);
        $this->assertNull($passengers[1]->phone);
        $this->assertEquals(1, $passengers[1]->passenger_count);

        $this->assertEquals('Jim Doe', $passengers[2]->full_name);
        $this->assertNull($passengers[2]->phone);

        // Sequential codes
        $numericParts = $passengers->map(fn ($p) => (int) substr($p->passenger_code, strlen($prefix)))->toArray();
        $this->assertEquals($numericParts[0] + 1, $numericParts[1]);
        $this->assertEquals($numericParts[1] + 1, $numericParts[2]);
    }

    #[Test]
    public function registration_stores_receipt_file_correctly(): void
    {
        $tour = Tour::factory()->published()->create([
            'max_capacity' => 10,
            'registration_deadline' => now()->addWeek(),
        ]);

        $receipt = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->post(route('tour.register.submit', $tour), [
            'full_name' => 'John Doe',
            'phone' => '912345678',
            'passenger_count' => 1,
            'passenger_names' => [],
            'receipt_image' => $receipt,
            'honeypot' => '',
        ]);

        $response->assertRedirect(route('tours.index'));

        $passenger = TourPassenger::where('tour_id', $tour->id)->first();
        $this->assertNotNull($passenger->receipt_image);

        Storage::disk('public')->assertExists('receipts/tours/'.$tour->id.'/'.$passenger->receipt_image);
    }

    #[Test]
    public function capacity_limits_prevent_over_registration(): void
    {
        $tour = Tour::factory()->published()->create([
            'max_capacity' => 2,
            'registration_deadline' => now()->addWeek(),
        ]);

        // Fill the tour to capacity with confirmed passengers
        TourPassenger::factory()->confirmed()->create([
            'tour_id' => $tour->id,
            'passenger_count' => 2,
        ]);

        $response = $this->post(route('tour.register.submit', $tour), [
            'full_name' => 'John Doe',
            'phone' => '912345678',
            'passenger_count' => 1,
            'passenger_names' => [],
            'receipt_image' => null,
            'honeypot' => '',
        ]);

        $response->assertSessionHasErrors('passenger_count');
        $this->assertStringContainsString('Not enough capacity', collect(session('errors')->get('passenger_count'))->first());
    }

    #[Test]
    public function duplicate_phone_numbers_are_rejected_for_same_tour(): void
    {
        $tour = Tour::factory()->published()->create([
            'max_capacity' => 10,
            'registration_deadline' => now()->addWeek(),
        ]);

        $phone = config('finot.phone_prefix', '+251').'912345678';

        TourPassenger::factory()->create([
            'tour_id' => $tour->id,
            'phone' => $phone,
        ]);

        $response = $this->post(route('tour.register.submit', $tour), [
            'full_name' => 'John Doe',
            'phone' => '912345678',
            'passenger_count' => 1,
            'passenger_names' => [],
            'receipt_image' => null,
            'honeypot' => '',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertStringContainsString('already registered for this tour', collect(session('errors')->get('phone'))->first());
    }

    #[Test]
    public function guest_passengers_get_default_names_when_not_provided(): void
    {
        $tour = Tour::factory()->published()->create([
            'max_capacity' => 10,
            'registration_deadline' => now()->addWeek(),
        ]);

        $response = $this->post(route('tour.register.submit', $tour), [
            'full_name' => 'John Doe',
            'phone' => '912345678',
            'passenger_count' => 3,
            'passenger_names' => [],
            'receipt_image' => null,
            'honeypot' => '',
        ]);

        $response->assertRedirect(route('tours.index'));

        $passengers = TourPassenger::where('tour_id', $tour->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(3, $passengers);
        $this->assertEquals('John Doe', $passengers[0]->full_name);
        $this->assertStringContainsString('Guest', $passengers[1]->full_name);
        $this->assertStringContainsString('Guest', $passengers[2]->full_name);
    }

    #[Test]
    public function only_primary_passenger_gets_receipt_image(): void
    {
        $tour = Tour::factory()->published()->create([
            'max_capacity' => 10,
            'registration_deadline' => now()->addWeek(),
        ]);

        $receipt = UploadedFile::fake()->image('receipt.jpg');

        $this->post(route('tour.register.submit', $tour), [
            'full_name' => 'John Doe',
            'phone' => '912345678',
            'passenger_count' => 2,
            'passenger_names' => ['Jane Doe'],
            'receipt_image' => $receipt,
            'honeypot' => '',
        ]);

        $passengers = TourPassenger::where('tour_id', $tour->id)
            ->orderBy('id', 'asc')
            ->get();

        $this->assertNotNull($passengers[0]->receipt_image);
        $this->assertNull($passengers[1]->receipt_image);
    }
}
