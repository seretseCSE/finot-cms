<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ActiveRoleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InAppNotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Middleware\RecordPageView;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EditProfileController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FundraisingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\WithdrawalPrintController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/backup.php';

Route::get('/manifest.json', [PwaController::class, 'manifest']);
Route::get('/service-worker.js', [PwaController::class, 'serviceWorker']);
Route::get('/build-info.json', [PwaController::class, 'buildInfo']);
Route::get('/offline', [PwaController::class, 'offline'])->name('offline');

// Public reading — generous so browsing and assets-in-page don't 429
Route::middleware(['throttle:public-browse', RecordPageView::class])->group(function () {
    // Legacy public URLs — 301 to the combined News / Media / Learn / Tours pages
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/events', [EventController::class, 'index'])->name('events');
    Route::get('/shop', [ProductController::class, 'index'])->name('shop.index');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/songs', [SongController::class, 'index'])->name('songs.index');
    Route::get('/gallery', [MediaController::class, 'legacyIndex'])->name('gallery.index');
    Route::redirect('/study', '/courses', 301);
    Route::redirect('/course', '/courses', 301);
    Route::get('/study/{path}', fn ($path) => redirect('/courses/'.$path, 301))->where('path', '.*');
    Route::get('/course/{path}', fn ($path) => redirect('/courses/'.$path, 301))->where('path', '.*');

    Route::get('/', [HomeController::class, 'index']);
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/news', [NewsController::class, 'index'])->name('news');
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/shop/{slug}', [ProductController::class, 'show'])->name('shop.show');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/songs/{id}', [SongController::class, 'show'])->name('songs.show');
    Route::get('/media', [MediaController::class, 'index'])->name('media');
    Route::get('/media/{mediaItem}', [MediaController::class, 'show'])->name('media.show');
    Route::get('/library', [LibraryController::class, 'index'])->name('library');
    Route::get('/library/subcategories', [LibraryController::class, 'subcategories'])->name('library.subcategories');
    Route::get('/library/download/{resource}', [LibraryController::class, 'download'])->name('library.download');
    Route::get('/contact', [ContactController::class, 'index'])->name('contact');
    Route::get('/fundraising', [FundraisingController::class, 'index'])->name('fundraising.index');
    Route::get('/tours', [TourController::class, 'index'])->name('tours.index');
    Route::get('/tours/{id}/register', [TourController::class, 'showRegister'])->name('tour.register');
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/{id}', [CourseController::class, 'show'])->name('courses.show')->where('id', '[0-9]+');
    Route::get('/courses/{slug}', [CourseController::class, 'browse'])->name('courses.browse')->where('slug', '[a-z0-9\-]+');
    Route::get('/courses/{course}/lesson/{lesson}', [CourseController::class, 'lesson'])->name('courses.lesson');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::get('/favorites/status', [FavoriteController::class, 'status'])->name('favorites.status');

    // Legacy public URLs (keep 301s for bookmarks and search engines)
});

Route::middleware('throttle:public-search')->group(function () {
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/api/fundraising', [FundraisingController::class, 'api'])->name('fundraising.api');
});

Route::middleware('throttle:public-write')->group(function () {
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::post('/blog/{slug}/comment', [BlogController::class, 'storeComment'])->name('blog.comment.store');
    Route::post('/tours/{id}/register', [TourController::class, 'register'])->name('tour.register.submit');
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');
});

Route::middleware('throttle:public-lookup')->group(function () {
    Route::get('/api/tour/lookup-phone', [TourController::class, 'lookupPhone'])->name('tour.lookup-phone');
});

Route::middleware(['auth', 'throttle:5,1'])->group(function () {
    Route::post('/user/change-password', [PasswordChangeController::class, 'changePassword'])->name('password.change');
    Route::get('/user/password-requirements', [PasswordChangeController::class, 'getPasswordRequirements'])->name('password.requirements');
});

Route::middleware(['auth'])->group(function () {
    Route::post('/admin/active-role', [ActiveRoleController::class, 'update'])->name('admin.active-role');
    Route::get('/admin/profile', [EditProfileController::class, '__invoke'])->name('admin.edit-profile');
    Route::get('/withdrawals/{withdrawal}/print', WithdrawalPrintController::class)->name('withdrawals.print');
    Route::get('/exports/download/{filename}', ExportDownloadController::class)
        ->where('filename', '[A-Za-z0-9._-]+')
        ->name('exports.download')
        ->middleware('signed');
});

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::post('/api/session/extend', [SessionController::class, 'extendSession'])->name('session.extend');
    Route::get('/api/session/status', [SessionController::class, 'getSessionStatus'])->name('session.status');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

Route::get('/portal/login', [PortalAuthController::class, 'showLogin'])->name('portal.login');
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/portal/login', [PortalAuthController::class, 'login'])->name('portal.login.submit');
});
Route::post('/portal/logout', [PortalAuthController::class, 'logout'])->middleware('auth')->name('portal.logout');

Route::middleware(['auth', 'student'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', fn () => redirect('/admin'))->name('home');
    Route::get('/results', fn () => redirect('/admin/my-results'))->name('results');
    Route::get('/attendance', fn () => redirect('/admin/my-attendance'))->name('attendance');
    Route::get('/offline-snapshot', [PortalController::class, 'offlineSnapshot'])->name('offline-snapshot');
    Route::get('/withdrawal', fn () => redirect('/admin/request-withdrawal'))->name('withdrawal');
    Route::post('/withdrawal', fn () => redirect('/admin/request-withdrawal'))->name('withdrawal.apply');
    Route::get('/withdrawal/{withdrawal}/print', function (\App\Models\WithdrawalRequest $withdrawal) {
        return redirect()->route('withdrawals.print', $withdrawal);
    })->name('withdrawal.print');
    Route::get('/profile', fn () => redirect('/admin'))->name('profile');
    Route::post('/profile', fn () => redirect('/admin'))->name('profile.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications/in-app', [InAppNotificationController::class, 'index'])->name('notifications.in-app');
    Route::post('/notifications/in-app/{notification}/read', [InAppNotificationController::class, 'markRead'])->name('notifications.in-app.read');
    Route::get('/push/vapid-public-key', [PushSubscriptionController::class, 'publicKey'])->name('push.vapid-public-key');
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/change-initial-password', [AuthController::class, 'showChangeInitialPassword'])->name('change-initial-password');
    Route::post('/change-initial-password', [AuthController::class, 'changeInitialPassword'])
        ->middleware('throttle:10,1')
        ->name('change-initial-password.submit');
});
