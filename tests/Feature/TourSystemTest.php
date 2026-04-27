<?php

namespace Tests\Feature;

use App\Models\Tour;
use App\Models\TourPassenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TourSystemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tour_head_can_access_tours_page(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/tours');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_head_can_access_tours_create_page(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/tours/create');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_head_can_access_tour_passengers_page(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/tour-passengers');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_head_can_access_tour_attendances_page(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/tour-attendances');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_report_page_exists(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/tour-report');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_search_page_exists(): void
    {
        $user = $this->createTourHeadUser();
        $this->actingAs($user);

        $response = $this->get('/admin/tour-search');
        $response->assertStatus(200);
    }

    #[Test]
    public function public_tour_listing_page_exists(): void
    {
        $response = $this->get('/tours');
        $response->assertStatus(200);
    }

    #[Test]
    public function tour_model_scopes_work(): void
    {
        Tour::factory()->draft()->create();
        Tour::factory()->published()->create();
        Tour::factory()->cancelled()->create();

        $this->assertEquals(1, Tour::where('status', 'Draft')->count());
        $this->assertEquals(1, Tour::where('status', 'Published')->count());
        $this->assertEquals(1, Tour::where('status', 'Cancelled')->count());
    }

    #[Test]
    public function tour_passenger_factory_creates_records(): void
    {
        $tour = Tour::factory()->published()->create();
        $passenger = TourPassenger::factory()->create(['tour_id' => $tour->id]);

        $this->assertDatabaseHas('tour_passengers', ['id' => $passenger->id]);
    }

    #[Test]
    public function tour_full_prevents_registration(): void
    {
        $tour = Tour::factory()->full()->create();
        TourPassenger::factory()->count(2)->confirmed()->create([
            'tour_id' => $tour->id,
            'passenger_count' => 1,
        ]);
        $this->assertFalse($tour->fresh()->is_registration_open);
    }

    #[Test]
    public function tour_edit_page_accessible(): void
    {
        $user = $this->createTourHeadUser();
        $tour = Tour::factory()->create();
        $this->actingAs($user);

        $response = $this->get("/admin/tours/{$tour->id}/edit");
        $response->assertStatus(200);
    }
}
