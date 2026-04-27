<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$perms = Spatie\Permission\Models\Permission::pluck('name')->toArray();
echo implode(PHP_EOL, $perms);
