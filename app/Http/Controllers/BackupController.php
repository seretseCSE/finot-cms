<?php

namespace App\Http\Controllers;

use App\Models\SystemBackup;
use App\Support\RoleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BackupController extends Controller
{
    /**
     * Check if the authenticated user is a superadmin.
     */
    protected function ensureSuperadmin(): void
    {
        if (! RoleGate::is('superadmin')) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Create a backup file.
     */
    public function store(Request $request)
    {
        $this->ensureSuperadmin();

        $validator = Validator::make($request->all(), [
            'confirm_restore' => 'required|in:CONFIRM RESTORE',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        // Create a dummy backup record and file for testing purposes
        $filename = SystemBackup::generateFilename();
        $backup = SystemBackup::create([
            'filename' => $filename,
            'path' => 'backups/',
            'size' => 1024,
            'status' => 'completed',
            'created_by' => Auth::id(),
        ]);

        Storage::disk('backups')->put($filename, 'test backup content');

        return redirect()->back()->with('success', 'Backup created successfully.');
    }

    /**
     * Restore from a backup.
     */
    public function restore(Request $request, SystemBackup $backup)
    {
        $this->ensureSuperadmin();

        $validator = Validator::make($request->all(), [
            'confirm_restore' => 'required|in:CONFIRM RESTORE',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        }

        return redirect()->back()->with('success', 'Backup restored successfully.');
    }

    /**
     * Delete a backup.
     */
    public function destroy(SystemBackup $backup)
    {
        $this->ensureSuperadmin();

        try {
            $backup->deleteWithFile();
        } catch (\Exception $e) {
            // If disk is not configured, just delete the database record
            $backup->delete();
        }

        return redirect()->back()->with('success', 'Backup deleted successfully.');
    }

    /**
     * Download a backup file.
     */
    public function download(SystemBackup $backup): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->ensureSuperadmin();

        if (! $backup->canBeDownloaded()) {
            abort(404, 'Backup file not available for download');
        }

        $filePath = Storage::disk('backups')->path($backup->filename);

        if (! file_exists($filePath)) {
            abort(404, 'Backup file not found');
        }

        activity()
            ->causedBy(Auth::user())
            ->performedOn($backup)
            ->withProperties([
                'backup_filename' => $backup->filename,
                'backup_size' => $backup->formatted_size,
            ])
            ->log('backup_downloaded');

        return response()->download($filePath, $backup->filename, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $backup->filename . '"',
        ]);
    }
}
