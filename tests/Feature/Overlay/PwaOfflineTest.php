<?php

namespace Tests\Feature\Overlay;

use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PwaOfflineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function public_library_exposes_opt_in_offline_download(): void
    {
        $this->get('/library')->assertOk()->assertSee('data-offline-clear', false);
        $this->get('/service-worker.js')
            ->assertOk()
            ->assertSee('finot-media-opt-in', false)
            ->assertSee('/login', false)
            ->assertSee('skipCachePaths', false);
    }

    #[Test]
    public function student_dashboard_has_learning_links(): void
    {
        $enrollment = StudentEnrollment::factory()->enrolled()->create();
        $user = User::query()->where('member_id', $enrollment->member_id)->first();
        $user->update(['temp_password_changed' => true]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('My Results')
            ->assertSee('My Attendance')
            ->assertSee('Class Announcements')
            ->assertSee('Homework')
            ->assertDontSee('Church library books', false);

        $this->actingAs($user)->get('/admin/my-results')->assertOk();
        $this->actingAs($user)->get('/admin/my-attendance')->assertOk();
        $this->actingAs($user)->get(route('portal.offline-snapshot'))->assertOk()->assertJsonStructure(['results', 'attendance']);
    }

    #[Test]
    public function guest_uses_the_single_public_login_page(): void
    {
        $this->get(route('portal.login'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertSee('Sign in');

        $this->get('/')
            ->assertOk()
            ->assertSee(route('login'), false);
    }

    #[Test]
    public function visiting_public_home_records_a_page_view(): void
    {
        $this->get('/');
        $this->assertDatabaseHas('page_views', ['path' => '/']);
    }
}
