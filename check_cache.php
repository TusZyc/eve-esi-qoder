<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tree = Cache::get('market_groups_tree', []);
echo "Market groups cache: " . (empty($tree) ? "EMPTY" : count($tree) . " items") . "\n";

// 检查缓存大小
$cacheKey = config('cache.prefix') . 'market_groups_tree';
echo "Cache key: $cacheKey\n";

// Redis 检查
try {
    $redis = app('redis')->connection();
    $exists = $redis->exists($cacheKey);
    echo "Redis key exists: " . ($exists ? "YES" : "NO") . "\n";
    if ($exists) {
        $ttl = $redis->ttl($cacheKey);
        echo "TTL: $ttl seconds\n";
        $size = strlen($redis->get($cacheKey));
        echo "Size: " . round($size / 1024, 2) . " KB\n";
    }
} catch (Exception $e) {
    echo "Redis error: " . $e->getMessage() . "\n";
}