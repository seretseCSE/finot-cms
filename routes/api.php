<?php

use App\Http\Controllers\Api\AttendanceSyncController;
use App\Http\Controllers\Api\DemoContactMessageController;
use App\Http\Controllers\Api\OfflineAttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/attendance/sync', [AttendanceSyncController::class, 'sync']);
    Route::post('/offline-attendance/sync', [OfflineAttendanceController::class, 'sync']);
    Route::get('/offline-attendance/status', [OfflineAttendanceController::class, 'status']);
    Route::post('/offline-attendance/process', [OfflineAttendanceController::class, 'process']);
});

// Demo routes for HTTP testing demonstration (testing and local environments only)
if (app()->environment('testing', 'local')) {
    Route::apiResource('demo/contact-messages', DemoContactMessageController::class)
        ->names([
            'index' => 'api.demo.contact-messages.index',
            'show' => 'api.demo.contact-messages.show',
            'store' => 'api.demo.contact-messages.store',
            'update' => 'api.demo.contact-messages.update',
            'destroy' => 'api.demo.contact-messages.destroy',
        ]);
}
