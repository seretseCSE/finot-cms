<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupRestore;
use App\Filament\Resources\MemberResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityHygieneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_cannot_view_or_create_filament_resources(): void
    {
        $this->assertFalse(MemberResource::canViewAny());
        $this->assertFalse(MemberResource::canCreate());
        $this->assertFalse(MemberResource::canDeleteAny());
        $this->assertFalse(BackupRestore::canAccess());
    }

    #[Test]
    public function guests_cannot_download_exports(): void
    {
        $this->get(route('exports.download', ['filename' => 'donations_2026-01-01_120000.xlsx']))
            ->assertRedirect();
    }

    #[Test]
    public function authenticated_users_cannot_traverse_export_paths(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/donations_2026-01-01_120000.xlsx', 'ok');

        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get('/exports/download/'.rawurlencode('../.env'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/exports/download/'.rawurlencode('..\\..\\.env'))
            ->assertNotFound();
    }

    #[Test]
    public function authenticated_users_can_download_a_safe_export_filename(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/donations_2026-01-01_120000.xlsx', 'xlsx-bytes');

        $user = $this->createAdminUser();

        $this->actingAs($user)
            ->get(route('exports.download', ['filename' => 'donations_2026-01-01_120000.xlsx']))
            ->assertOk();
    }

    #[Test]
    public function guests_cannot_create_backups(): void
    {
        $this->post('/admin/backup-restore/create', [
            'confirm_restore' => 'CONFIRM RESTORE',
        ])->assertRedirect();
    }

    #[Test]
    public function product_tour_api_requires_authentication(): void
    {
        $this->getJson('/api/product-tour/status')
            ->assertUnauthorized();
    }
}
