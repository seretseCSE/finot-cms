<?php

use App\Http\Controllers\Api\AttendanceSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/attendance/sync', [AttendanceSyncController::class, 'sync']);
});
