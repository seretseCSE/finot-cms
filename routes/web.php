<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\ProductTourController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\ProgramsController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\FundraisingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LanguageController;

// Apply rate limiting to all public routes (60 requests per minute)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/', function () {
        return view('public.home');
    });

    // Public page routes
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/programs', [ProgramsController::class, 'index'])->name('programs');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/songs', [SongController::class, 'index'])->name('songs.index');
    Route::get('/songs/{id}', [SongController::class, 'show'])->name('songs.show');
    Route::get('/media', [MediaController::class, 'index'])->name('media');
    Route::get('/events', [EventController::class, 'index'])->name('events');
    Route::get('/library', [LibraryController::class, 'index'])->name('library');
    Route::get('/fundraising', [FundraisingController::class, 'index'])->name('fundraising');
    Route::get('/contact', [ContactController::class, 'index'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

    // Language switch endpoint
    Route::post('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

    // Public tour routes
    Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
    Route::get('/tours/{id}/register', [TourController::class, 'showRegister'])->name('tour.register');
    Route::post('/tours/{id}/register', [TourController::class, 'register'])->name('tour.register.submit');

    // API route for phone lookup
    Route::get('/api/tour/lookup-phone', [TourController::class, 'lookupPhone'])->name('tour.lookup-phone');
});

// Password change routes
Route::middleware(['auth'])->group(function () {
    Route::post('/user/change-password', [PasswordChangeController::class, 'changePassword'])->name('password.change');
    Route::get('/user/password-requirements', [PasswordChangeController::class, 'getPasswordRequirements'])->name('password.requirements');
});

// Session management API routes (for PWA background sync and session extension)
Route::middleware(['auth'])->group(function () {
    Route::post('/api/session/extend', [SessionController::class, 'extendSession'])->name('session.extend');
    Route::get('/api/session/status', [SessionController::class, 'getSessionStatus'])->name('session.status');
});

// Product tour routes
Route::middleware(['auth', 'web'])->group(function () {
    Route::post('/api/tour/restart', [ProductTourController::class, 'restart'])->name('tour.restart');
    Route::post('/api/tour/complete', [ProductTourController::class, 'complete'])->name('tour.complete');
    Route::get('/api/tour/status', [ProductTourController::class, 'status'])->name('tour.status');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');
