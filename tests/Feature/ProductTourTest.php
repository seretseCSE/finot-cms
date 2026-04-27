<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTourTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tour_status_returns_json_for_authenticated_user(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $response = $this->getJson('/api/tour/status');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonStructure([
            'should_show_tour',
            'completed_tours',
            'current_role',
        ]);
        $response->assertJsonPath('current_role', 'admin');
        $response->assertJsonPath('should_show_tour', true);
    }

    #[Test]
    public function tour_status_returns_should_show_tour_false_after_completion(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $this->postJson('/api/tour/complete');

        $response = $this->getJson('/api/tour/status');

        $response->assertStatus(200);
        $response->assertJsonPath('should_show_tour', false);
        $response->assertJsonPath('completed_tours', ['admin']);
    }

    #[Test]
    public function tour_restart_resets_completion_and_allows_tour_again(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        // Complete the tour first
        $this->postJson('/api/tour/complete');
        $status = $this->getJson('/api/tour/status');
        $status->assertJsonPath('should_show_tour', false);

        // Restart the tour
        $response = $this->postJson('/api/tour/restart');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Status should now show tour again
        $status = $this->getJson('/api/tour/status');
        $status->assertJsonPath('should_show_tour', true);
        $status->assertJsonPath('completed_tours', []);
    }

    #[Test]
    public function tour_complete_returns_success_json(): void
    {
        $user = $this->createFinanceHeadUser();
        $this->actingAs($user);

        $response = $this->postJson('/api/tour/complete');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Tour marked as completed');
    }

    #[Test]
    public function tour_status_reflects_correct_role_for_superadmin(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $response = $this->getJson('/api/tour/status');

        $response->assertStatus(200);
        $response->assertJsonPath('current_role', 'superadmin');
        $response->assertJsonPath('should_show_tour', true);
    }

    #[Test]
    public function tour_completion_is_role_specific(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->assignRole('finance_head');
        $this->actingAs($user);

        // Complete tour as admin role
        $this->postJson('/api/tour/complete');

        // The first role (admin) should be marked complete
        $response = $this->getJson('/api/tour/status');
        $response->assertStatus(200);
        $response->assertJsonPath('should_show_tour', false);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_tour_api(): void
    {
        $response = $this->getJson('/api/tour/status');

        $response->assertStatus(401);
    }
}
