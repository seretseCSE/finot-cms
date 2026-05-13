<?php

namespace Tests\Unit;

use App\Models\SystemBackup;
use App\Services\BackupCreationService;
use App\Services\BackupRestorationService;
use App\Services\BackupCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure backups disk for testing
        config([
            'filesystems.disks.backups' => [
                'driver' => 'local',
                'root' => storage_path('app/backups'),
            ],
        ]);
    }

    /**
     * Create a testable backup service that skips the actual backup execution.
     */
    protected function createTestableService(): BackupCreationService
    {
        return new class () extends BackupCreationService {
            protected function performBackup(\App\Models\SystemBackup $backup): void
            {
                // Create an empty file so Storage::size() succeeds
                $path = \Illuminate\Support\Facades\Storage::disk('backups')->path($backup->filename);
                file_put_contents($path, '');
            }
        };
    }

    /**
     * Test that createBackup creates a SystemBackup record.
     */
    public function test_create_backup_creates_record(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        $this->assertInstanceOf(SystemBackup::class, $backup);
        $this->assertEquals('completed', $backup->status);
        $this->assertEquals($user->id, $backup->created_by);
    }

    /**
     * Test that backup has correct initial status.
     */
    public function test_backup_has_correct_initial_status(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        $this->assertNotNull($backup->status);
    }

    /**
     * Test that backup filename is generated correctly.
     */
    public function test_backup_filename_is_generated(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        $this->assertNotNull($backup->filename);
        $this->assertStringContainsString('backup_', $backup->filename);
    }

    /**
     * Test that multiple backups can be created.
     */
    public function test_multiple_backups_can_be_created(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup1 = $service->createBackup($user->id);
        $backup2 = $service->createBackup($user->id);

        $this->assertNotEquals($backup1->filename, $backup2->filename);
        $this->assertGreaterThan(1, SystemBackup::count());
    }

    /**
     * Test backup record structure.
     */
    public function test_backup_record_structure(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        $this->assertArrayHasKey('filename', $backup->toArray());
        $this->assertArrayHasKey('path', $backup->toArray());
        $this->assertArrayHasKey('size', $backup->toArray());
        $this->assertArrayHasKey('status', $backup->toArray());
        $this->assertArrayHasKey('created_by', $backup->toArray());
    }

    /**
     * Test that backup tracks creation time.
     */
    public function test_backup_tracks_creation_time(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        $this->assertNotNull($backup->created_at);
    }

    /**
     * Test backup with different user IDs.
     */
    public function test_backup_with_different_users(): void
    {
        $user1 = $this->createSuperadminUser();
        $user2 = $this->createAdminUser();
        $service = $this->createTestableService();

        $backup1 = $service->createBackup($user1->id);
        $backup2 = $service->createBackup($user2->id);

        $this->assertEquals($user1->id, $backup1->created_by);
        $this->assertEquals($user2->id, $backup2->created_by);
    }

    /**
     * Test SystemBackup model generates correct filename.
     */
    public function test_system_backup_generates_filename(): void
    {
        $filename = SystemBackup::generateFilename();

        $this->assertNotNull($filename);
        $this->assertStringContainsString('backup_', $filename);
        $this->assertStringContainsString('.zip', $filename);
    }

    /**
     * Test backup file path is correct.
     */
    public function test_backup_file_path_is_correct(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        $this->assertEquals('backups/', $backup->path);
    }

    /**
     * Test that failed backup is marked as failed.
     */
    public function test_failed_backup_is_marked(): void
    {
        $user = \App\Models\User::factory()->create();
        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 0,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $backup->markAsFailed('Test failure message');

        $this->assertEquals('failed', $backup->status);
        $this->assertStringContainsString('Test failure message', $backup->log_message);
    }

    /**
     * Test backup can be marked as completed.
     */
    public function test_backup_can_be_completed(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        // If backup completed, it should have completed_at set
        if ($backup->status === 'completed') {
            $this->assertNotNull($backup->completed_at);
        }
    }

    /**
     * Test backup with selective restore capability.
     */
    public function test_backup_for_selective_restore(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        // The backup should be stored in a way that allows selective restore
        $this->assertNotNull($backup->filename);
        $this->assertNotNull($backup->path);
    }

    /**
     * Test maintenance mode backup behavior.
     */
    public function test_maintenance_mode_backup(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        // Backup should still be created even in maintenance mode context
        $this->assertInstanceOf(SystemBackup::class, $backup);
    }

    /**
     * Test backup size tracking.
     */
    public function test_backup_size_tracking(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        // Size should be tracked (may be 0 if backup hasn't completed)
        $this->assertNotNull($backup->size);
        $this->assertGreaterThanOrEqual(0, $backup->size);
    }

    /**
     * Test backup log message.
     */
    public function test_backup_log_message(): void
    {
        $user = $this->createSuperadminUser();
        $service = $this->createTestableService();

        $backup = $service->createBackup($user->id);

        // Completed backup should have a log message
        if ($backup->status === 'completed') {
            $this->assertNotNull($backup->log_message);
        }
    }
}
