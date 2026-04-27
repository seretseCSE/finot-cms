<?php

use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Backup management routes
    Route::post('/backup', [BackupController::class, 'store'])
        ->name('backup.store')
        ->middleware('role:Superadmin');

    Route::get('/backup/download/{backup}', [BackupController::class, 'download'])
        ->name('backup.download')
        ->middleware('role:Superadmin');
});
