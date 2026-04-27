<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = App\Models\User::factory()->create([
    'phone' => '+251911234567',
    'password' => bcrypt('CorrectPassword1'),
    'is_locked' => true,
    'locked_until' => now()->subMinute(),
    'temp_password_changed' => true,
]);

echo 'Stored hash: ' . $u->password . PHP_EOL;
echo 'Check result: ' . (Hash::check('CorrectPassword1', $u->password) ? 'true' : 'false') . PHP_EOL;
echo 'isCurrentlyLocked: ' . ($u->isCurrentlyLocked() ? 'true' : 'false') . PHP_EOL;
echo 'is_locked after check: ' . ($u->is_locked ? 'true' : 'false') . PHP_EOL;
echo 'locked_until after check: ' . ($u->locked_until ?? 'null') . PHP_EOL;

$u->delete();
