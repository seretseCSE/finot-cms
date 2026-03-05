<?php

namespace Tests\Feature;

use App\Models\SystemBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test superadmin can access backup page.
     */
    public function test_superadmin_can_access_backup_page(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->get('/admin/backup-restore');

        $response->assertStatus(200);
    }

    /**
     * Test admin cannot access backup page.
     */
    public function test_admin_cannot_access_backup_page(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->get('/admin/backup-restore');

        $response->assertStatus(403);
    }

    /**
     * Test backup creation requires CONFIRM RESTORE validation.
     */
    public function test_backup_creation_without_confirm_restore_fails(): void
    {
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->post('/admin/backup-restore/create', [
                'confirm_restore' => '', // Empty - should fail
            ]);

        $response->assertSessionHasErrors('confirm_restore');
    }

    /**
     * Test backup creation with CONFIRM RESTORE text.
     */
    public function test_backup_creation_with_confirm_restore_succeeds(): void
    {
        Storage::fake('backups');
        
        $user = $this->createSuperadminUser();

        $response = $this->actingAs($user)
            ->post('/admin/backup-restore/create', [
                'confirm_restore' => 'CONFIRM RESTORE',
            ]);

        // Should either succeed or redirect with success message
        $response->assertSessionHas('success');
    }

    /**
     * Test restore requires CONFIRM RESTORE validation.
     */
    public function test_restore_requires_confirm_restore(): void
    {
        $user = $this->createSuperadminUser();
        
        // Create a backup
        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/backup-restore/{$backup->id}/restore", [
                'confirm_restore' => '', // Empty - should fail
            ]);

        $response->assertSessionHasErrors('confirm_restore');
    }

    /**
     * Test restore with correct confirmation.
     */
    public function test_restore_with_correct_confirmation(): void
    {
        Storage::fake('backups');
        
        $user = $this->createSuperadminUser();
        
        // Create a backup
        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/backup-restore/{$backup->id}/restore", [
                'confirm_restore' => 'CONFIRM RESTORE',
            ]);

        // Should attempt restore
        $response->assertSessionHas('success');
    }

    /**
     * Test restore with wrong confirmation text fails.
     */
    public function test_restore_with_wrong_confirmation_fails(): void
    {
        $user = $this->createSuperadminUser();
        
        // Create a backup
        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/backup-restore/{$backup->id}/restore", [
                'confirm_restore' => 'WRONG TEXT',
            ]);

        $response->assertSessionHasErrors('confirm_restore');
    }

    /**
     * Test backup list is displayed.
     */
    public function test_backup_list_is_displayed(): void
    {
        $user = $this->createSuperadminUser();

        // Create some backups
        SystemBackup::factory()->count(3)->create([
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get('/admin/backup-restore');

        $response->assertStatus(200);
        // Should see the backups in the list
    }

    /**
     * Test can delete backup.
     */
    public function test_can_delete_backup(): void
    {
        $user = $this->createSuperadminUser();

        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->delete("/admin/backup-restore/{$backup->id}");

        $response->assertSessionHas('success');
        $this->assertDeleted($backup);
    }

    /**
     * Test non-superadmin cannot create backup.
     */
    public function test_non_superadmin_cannot_create_backup(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)
            ->post('/admin/backup-restore/create', [
                'confirm_restore' => 'CONFIRM RESTORE',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test non-superadmin cannot restore backup.
     */
    public function test_non_superadmin_cannot_restore_backup(): void
    {
        $user = $this->createAdminUser();
        
        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post("/admin/backup-restore/{$backup->id}/restore", [
                'confirm_restore' => 'CONFIRM RESTORE',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test backup status is tracked.
     */
    public function test_backup_status_is_tracked(): void
    {
        $user = $this->createSuperadminUser();

        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 0,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        // Update status
        $backup->update(['status' => 'in_progress']);

        $this->assertEquals('in_progress', $backup->fresh()->status);
    }

    /**
     * Test backup can be downloaded.
     */
    public function test_backup_can_be_downloaded(): void
    {
        Storage::fake('backups');
        
        $user = $this->createSuperadminUser();

        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        // Put a file in the fake storage
        Storage::disk('backups')->put('test-backup.zip', 'test content');

        $response = $this->actingAs($user)
            ->get("/admin/backup-restore/{$backup->id}/download");

        $response->assertStatus(200);
    }

    /**
     * Test selective restore - restore specific components.
     */
    public function test_selective_restore(): void
    {
        $user = $this->createSuperadminUser();

        $backup = SystemBackup::create([
            'filename' => 'test-backup.zip',
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => $user->id,
        ]);

        // Request selective restore (e.g., only database)
        $response = $this->actingAs($user)
            ->post("/admin/backup-restore/{$backup->id}/restore", [
                'confirm_restore' => 'CONFIRM RESTORE',
                'components' => ['database'],
            ]);

        $response->assertSessionHas('success');
    }
}
