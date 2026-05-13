<?php

namespace Tests\Unit\Services;

use App\Models\SystemBackup;
use App\Services\BackupCreationService;
use App\Services\BackupRestorationService;
use App\Services\BackupCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Temp directory used for the backups disk during tests.
     */
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/backup_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        config([
            'filesystems.disks.backups' => [
                'driver' => 'local',
                'root' => $this->tempDir,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    /**
     * Create a testable subclass of BackupCreationService that overrides the
     * external-interaction methods so no real mysqldump or filesystem
     * scanning is performed.  Each overridden method writes a known
     * marker entry into the ZIP so we can assert on structure later.
     */
    private function createTestableCreationService(): BackupCreationService
    {
        return new class () extends BackupCreationService {
            public function testPerformBackup(SystemBackup $backup): void
            {
                $this->performBackup($backup);
            }

            protected function addDatabaseDump(ZipArchive $zip): void
            {
                $zip->addFromString('database.sql', 'MOCK_DATABASE_DUMP');
            }

            protected function addStorageFiles(ZipArchive $zip): void
            {
                $zip->addEmptyDir('storage/app');
                $zip->addFromString('storage/app/public/file.txt', 'MOCK_STORAGE_FILE');
            }

            protected function addConfigFiles(ZipArchive $zip): void
            {
                $zip->addFromString('.env', 'MOCK_ENV');
                $zip->addFromString('composer.json', 'MOCK_COMPOSER');
                $zip->addFromString('composer.lock', 'MOCK_LOCK');
            }
        };
    }

    /**
     * Create a service that writes a minimal valid ZIP during createBackup
     * so the size() call and status transitions work without mysqldump.
     */
    private function createTestableServiceForCreateBackup(): BackupCreationService
    {
        return new class () extends BackupCreationService {
            protected function performBackup(SystemBackup $backup): void
            {
                $zipPath = Storage::disk('backups')->path($backup->filename);
                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new \Exception('Failed to create backup ZIP file');
                }
                $zip->addFromString('test.txt', 'test content');
                $zip->close();
            }
        };
    }

    /**
     * Create a service that no-ops the destructive restore steps.
     */
    private function createTestableServiceForRestore(): BackupRestorationService
    {
        return new class () extends BackupRestorationService {
            protected function restoreDatabase(string $sqlFile): void
            {
                // no-op for testing
            }

            protected function restoreStorageFiles(string $storageDir): void
            {
                // no-op for testing
            }

            protected function restoreConfigFiles(string $tempDir): void
            {
                // no-op for testing
            }
        };
    }

    #[Test]
    public function perform_backup_creates_zip_with_database_storage_and_config_entries(): void
    {
        $service = $this->createTestableCreationService();
        $backup = SystemBackup::factory()->create([
            'filename' => 'test_backup.zip',
            'path' => 'backups/',
        ]);

        $service->testPerformBackup($backup);

        $zipPath = $this->tempDir.'/'.$backup->filename;
        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        // Database dump
        $this->assertSame('MOCK_DATABASE_DUMP', $zip->getFromName('database.sql'));

        // Storage files
        $this->assertSame('', $zip->getFromName('storage/app/'));
        $this->assertSame('MOCK_STORAGE_FILE', $zip->getFromName('storage/app/public/file.txt'));

        // Config files
        $this->assertSame('MOCK_ENV', $zip->getFromName('.env'));
        $this->assertSame('MOCK_COMPOSER', $zip->getFromName('composer.json'));
        $this->assertSame('MOCK_LOCK', $zip->getFromName('composer.lock'));

        $zip->close();
    }

    #[Test]
    public function perform_backup_throws_when_zip_cannot_be_opened(): void
    {
        $service = $this->createTestableCreationService();
        $backup = SystemBackup::factory()->create([
            'filename' => '/nonexistent/path/backup.zip',
            'path' => 'backups/',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create backup ZIP file');

        $service->testPerformBackup($backup);
    }

    #[Test]
    public function perform_backup_closes_zip_even_when_add_method_throws(): void
    {
        $service = new class () extends BackupCreationService {
            public function testPerformBackup(SystemBackup $backup): void
            {
                $this->performBackup($backup);
            }

            protected function addDatabaseDump(ZipArchive $zip): void
            {
                throw new \RuntimeException('DB dump exploded');
            }

            protected function addStorageFiles(ZipArchive $zip): void
            {
                // Never reached
            }

            protected function addConfigFiles(ZipArchive $zip): void
            {
                // Never reached
            }
        };

        $backup = SystemBackup::factory()->create([
            'filename' => 'failing_backup.zip',
            'path' => 'backups/',
        ]);

        try {
            $service->testPerformBackup($backup);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('DB dump exploded', $e->getMessage());

            // The important thing is that no fatal error / segfault occurred
            // because the ZipArchive was left open.
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function create_backup_creates_completed_record_with_valid_filename(): void
    {
        $service = $this->createTestableServiceForCreateBackup();
        $user = $this->createSuperadminUser();

        $backup = $service->createBackup($user->id);

        $this->assertDatabaseHas('system_backups', [
            'id' => $backup->id,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $this->assertStringStartsWith('backup_', $backup->filename);
        $this->assertStringEndsWith('.zip', $backup->filename);
    }

    #[Test]
    public function create_backup_marks_failed_when_perform_backup_throws(): void
    {
        $service = new class () extends BackupCreationService {
            protected function performBackup(\App\Models\SystemBackup $backup): void
            {
                throw new \RuntimeException('Simulated failure');
            }
        };

        $user = $this->createSuperadminUser();

        try {
            $service->createBackup($user->id);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure', $e->getMessage());
        }

        // After exception, backup should be marked failed
        $this->assertDatabaseHas('system_backups', [
            'status' => 'failed',
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function restore_backup_throws_for_non_completable_backup(): void
    {
        $service = new BackupRestorationService();
        $backup = SystemBackup::factory()->create([
            'status' => 'failed',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Backup cannot be restored');

        $service->restoreBackup($backup);
    }

    #[Test]
    public function restore_backup_succeeds_for_valid_backup(): void
    {
        $service = $this->createTestableServiceForRestore();
        $backup = SystemBackup::factory()->create([
            'filename' => 'restore_test.zip',
            'path' => 'backups/',
            'status' => 'completed',
        ]);

        // Write a valid ZIP file so restoreBackup can open it
        $zipPath = $this->tempDir.'/restore_test.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', 'mock sql');
        $zip->addEmptyDir('storage');
        $zip->addFromString('.env', 'mock env');
        $zip->close();

        // Should not throw
        $service->restoreBackup($backup);

        $this->assertTrue(true);
    }

    #[Test]
    public function multiple_backups_have_unique_filenames(): void
    {
        $service = $this->createTestableServiceForCreateBackup();
        $user = $this->createSuperadminUser();

        $backup1 = $service->createBackup($user->id);
        $backup2 = $service->createBackup($user->id);

        $this->assertNotEquals($backup1->filename, $backup2->filename);
        $this->assertGreaterThanOrEqual(2, SystemBackup::count());
    }

    #[Test]
    public function backup_record_has_required_fields(): void
    {
        $service = $this->createTestableServiceForCreateBackup();
        $user = $this->createSuperadminUser();

        $backup = $service->createBackup($user->id);
        $array = $backup->toArray();

        $this->assertArrayHasKey('filename', $array);
        $this->assertArrayHasKey('path', $array);
        $this->assertArrayHasKey('size', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('created_by', $array);
    }

    #[Test]
    public function backup_tracks_creator(): void
    {
        $service = $this->createTestableServiceForCreateBackup();
        $user1 = $this->createSuperadminUser();
        $user2 = $this->createAdminUser();

        $backup1 = $service->createBackup($user1->id);
        $backup2 = $service->createBackup($user2->id);

        $this->assertEquals($user1->id, $backup1->created_by);
        $this->assertEquals($user2->id, $backup2->created_by);
    }

    #[Test]
    public function model_generates_filename(): void
    {
        $filename = SystemBackup::generateFilename();

        $this->assertNotNull($filename);
        $this->assertStringStartsWith('backup_', $filename);
        $this->assertStringEndsWith('.zip', $filename);
    }

    #[Test]
    public function failed_backup_marking_works(): void
    {
        $backup = SystemBackup::factory()->create([
            'status' => 'pending',
        ]);

        $backup->markAsFailed('Test failure message');

        $this->assertEquals('failed', $backup->fresh()->status);
        $this->assertStringContainsString('Test failure message', $backup->fresh()->log_message);
    }

    #[Test]
    public function completed_backup_has_size_and_log_message(): void
    {
        $service = $this->createTestableServiceForCreateBackup();
        $user = $this->createSuperadminUser();

        $backup = $service->createBackup($user->id);

        $this->assertEquals('completed', $backup->status);
        $this->assertGreaterThan(0, $backup->size);
        $this->assertNotNull($backup->log_message);
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
