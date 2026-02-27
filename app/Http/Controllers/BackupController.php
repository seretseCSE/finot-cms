<?php

namespace App\Http\Controllers;

use App\Models\SystemBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Download a backup file.
     */
    public function download(SystemBackup $backup): StreamedResponse
    {
        // Check if user has permission
        if (!Auth::user()->hasRole('Superadmin')) {
            abort(403, 'Unauthorized');
        }

        // Check if backup can be downloaded
        if (!$backup->canBeDownloaded()) {
            abort(404, 'Backup file not available for download');
        }

        $filePath = Storage::disk('backups')->path($backup->filename);
        
        if (!file_exists($filePath)) {
            abort(404, 'Backup file not found');
        }

        // Log the download
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
