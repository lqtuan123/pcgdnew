<?php
// public/clear_cache.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Laravel root
$laravelRoot = __DIR__ . '/../';

// Load autoload và bootstrap
require $laravelRoot . 'vendor/autoload.php';
$app = require_once $laravelRoot . 'bootstrap/app.php';

// Boot application kernel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Clear caches
$kernel->call('cache:clear');
echo "Cache cleared.<br>";

$kernel->call('config:clear');
echo "Config cleared.<br>";

$kernel->call('route:clear');
echo "Route cleared.<br>";

$kernel->call('view:clear');
echo "View cache cleared.<br>";

// Optional: tự xóa file sau khi chạy
// unlink(__FILE__);
