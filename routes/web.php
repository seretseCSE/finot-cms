<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\ProductTourController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Include backup routes
require __DIR__.'/backup.php';

// PWA routes
Route::get('/manifest.json', [PwaController::class, 'manifest']);
Route::get('/service-worker.js', [PwaController::class, 'serviceWorker']);
Route::get('/build-info.json', [PwaController::class, 'buildInfo']);
Route::get('/offline', [PwaController::class, 'offline'])->name('offline');

use App\Http\Controllers\ContactController;
use App\Http\Controllers\EditProfileController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\FundraisingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SongController;

// Apply rate limiting to all public routes (10 requests per minute)
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/', [HomeController::class, 'index']);

    // Public page routes
    Route::get('/about', [AboutController::class, 'index'])->name('about');

    // Combined News & Events page
    Route::get('/news', [NewsController::class, 'index'])->name('news');

    // Redirect old index pages to combined pages (keep names for backward compat)
    Route::get('/announcements', fn () => redirect('/news', 301))->name('announcements.index');
    Route::get('/events', function () {
        $params = request()->query();
        $params['tab'] = 'events';
        return redirect()->route('news', $params, 301);
    })->name('events');
    Route::get('/shop', function () {
        $params = request()->query();
        $params['tab'] = 'shop';
        return redirect('/tours?' . http_build_query($params), 301);
    })->name('shop.index');

    // Detail routes
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/shop/{slug}', [ProductController::class, 'show'])->name('shop.show');

    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::post('/blog/{slug}/comment', [BlogController::class, 'storeComment'])->name('blog.comment.store');
    Route::get('/songs', [SongController::class, 'index'])->name('songs.index');
    Route::get('/songs/{id}', [SongController::class, 'show'])->name('songs.show');
    Route::get('/media', [MediaController::class, 'index'])->name('media');
    Route::get('/media/{mediaItem}', [MediaController::class, 'show'])->name('media.show');
    Route::get('/library', [LibraryController::class, 'index'])->name('library');
    Route::get('/library/subcategories', [LibraryController::class, 'subcategories'])->name('library.subcategories');
    Route::get('/library/download/{resource}', [LibraryController::class, 'download'])->name('library.download');
    Route::get('/contact', [ContactController::class, 'index'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

    // Fundraising routes
    Route::get('/fundraising', [FundraisingController::class, 'index'])->name('fundraising.index');
    Route::get('/api/fundraising', [FundraisingController::class, 'api'])->name('fundraising.api');

    // Language switch endpoint
    Route::post('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

    // Edit profile route
    Route::get('/admin/profile', [EditProfileController::class, '__invoke'])->name('admin.edit-profile');

    // Public tour routes
    Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
    Route::get('/tours/{id}/register', [TourController::class, 'showRegister'])->name('tour.register');
    Route::post('/tours/{id}/register', [TourController::class, 'register'])->name('tour.register.submit');

    // API route for phone lookup
    Route::get('/api/tour/lookup-phone', [TourController::class, 'lookupPhone'])->name('tour.lookup-phone');
});

// Password change routes
Route::middleware(['auth', 'throttle:5,1'])->group(function () {
    Route::post('/user/change-password', [PasswordChangeController::class, 'changePassword'])->name('password.change');
    Route::get('/user/password-requirements', [PasswordChangeController::class, 'getPasswordRequirements'])->name('password.requirements');
});

// Export download route
Route::middleware(['auth'])->group(function () {
    Route::get('/exports/download/{filename}', ExportDownloadController::class)->name('exports.download');
});

// Session management API routes (for PWA background sync and session extension)
Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::post('/api/session/extend', [SessionController::class, 'extendSession'])->name('session.extend');
    Route::get('/api/session/status', [SessionController::class, 'getSessionStatus'])->name('session.status');
});

// Product tour routes
Route::middleware(['auth', 'web', 'throttle:10,1'])->group(function () {
    Route::post('/api/tour/restart', [ProductTourController::class, 'restart'])->name('tour.restart');
    Route::post('/api/tour/complete', [ProductTourController::class, 'complete'])->name('tour.complete');
    Route::get('/api/tour/status', [ProductTourController::class, 'status'])->name('tour.status');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

// Apply stricter rate limiting to authentication routes (5 requests per minute)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'throttle:5,1'])->group(function () {
    Route::get('/change-initial-password', [AuthController::class, 'showChangeInitialPassword'])->name('change-initial-password');
    Route::post('/change-initial-password', [AuthController::class, 'changeInitialPassword'])->name('change-initial-password.submit');
});
