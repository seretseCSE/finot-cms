<?php

use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Legacy backup routes
    Route::post('/backup', [BackupController::class, 'store'])
        ->name('backup.store');

    Route::get('/backup/download/{backup}', [BackupController::class, 'download'])
        ->name('backup.download');

    // Admin backup-restore routes (used by tests and Filament page actions)
    Route::post('/admin/backup-restore/create', [BackupController::class, 'store'])
        ->name('backup-restore.create');

    Route::post('/admin/backup-restore/{backup}/restore', [BackupController::class, 'restore'])
        ->name('backup-restore.restore');

    Route::delete('/admin/backup-restore/{backup}', [BackupController::class, 'destroy'])
        ->name('backup-restore.destroy');

    Route::get('/admin/backup-restore/{backup}/download', [BackupController::class, 'download'])
        ->name('backup-restore.download');
});
