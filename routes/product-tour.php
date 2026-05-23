<?php

use App\Http\Controllers\Api\ProductTour\ProductTourController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('product-tour')->group(function () {
    Route::get('status', [ProductTourController::class, 'status']);
    Route::post('start', [ProductTourController::class, 'start']);
    Route::post('complete', [ProductTourController::class, 'complete']);
    Route::post('skip', [ProductTourController::class, 'skip']);
    Route::post('restart', [ProductTourController::class, 'restart']);
    Route::post('progress', [ProductTourController::class, 'progress']);
    Route::get('feature-discovery', [ProductTourController::class, 'featureDiscovery']);
    Route::post('dismiss-hint', [ProductTourController::class, 'dismissHint']);
});
