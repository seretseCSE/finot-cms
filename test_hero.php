<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $html = view('public.home', [
        'heroStats' => ['active_members' => 2847, 'sunday_school' => 1204, 'active_classes' => 8, 'volunteers' => 156, 'active_campaigns' => 3],
        'announcements' => collect([]),
        'events' => collect([]),
        'departments' => collect([]),
        'campaigns' => collect([]),
        'recentPhotos' => collect([]),
        'upcomingTours' => collect([]),
        'featuredLibraryResources' => collect([]),
        'faqs' => collect([]),
        'monthlyMembershipData' => [],
        'totalLibraryResources' => 0,
    ])->render();
    echo 'RENDER OK' . PHP_EOL;
    // Check if hero section is present
    if (strpos($html, 'hero-premium') !== false) {
        echo 'hero-premium class found' . PHP_EOL;
    } else {
        echo 'hero-premium class MISSING' . PHP_EOL;
    }
    if (strpos($html, 'heroSection') !== false) {
        echo 'heroSection() found' . PHP_EOL;
    } else {
        echo 'heroSection() MISSING' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
