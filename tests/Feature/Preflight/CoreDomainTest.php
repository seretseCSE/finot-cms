<?php

namespace Tests\Feature\Preflight;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoreDomainTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function timezone_is_addis_ababa(): void
    {
        $this->assertSame('Africa/Addis_Ababa', config('app.timezone'));
    }

    #[Test]
    public function production_queue_default_is_database(): void
    {
        $example = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $example);
        $this->assertSame('database', config('queue.connections.database.driver'));
    }

    #[Test]
    public function overlay_schema_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('student_enrollments', 'removed_at'));
        $this->assertTrue(Schema::hasColumn('users', 'member_id'));
        $this->assertTrue(Schema::hasTable('terms'));
        $this->assertTrue(Schema::hasTable('marklists'));
        $this->assertTrue(Schema::hasTable('marklist_items'));
        $this->assertTrue(Schema::hasTable('withdrawal_requests'));
        $this->assertTrue(Schema::hasTable('facilities'));
        $this->assertTrue(Schema::hasTable('bookings'));
        $this->assertTrue(Schema::hasTable('in_app_notifications'));
        $this->assertTrue(Schema::hasTable('platform_settings'));
        $this->assertTrue(Schema::hasTable('page_views'));
        $this->assertFalse(Schema::hasTable('member_imports'));
        $this->assertFalse(Schema::hasTable('member_import_rows'));
        $this->assertDatabaseHas('platform_settings', ['key' => 'notifications.sms_whitelist']);
    }
}
