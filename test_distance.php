<?php

require __DIR__.'/vendor/autoload.php';

// 创建应用实例
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\SystemDistanceService;

echo "=== SystemDistanceService 测试 ===\n\n";

$service = new SystemDistanceService();

// Test 1: Get path distance
echo "Test 1: 计算 Jita(30000142) 到 Amarr(30002187) 的路径\n";
list($distance, $path) = $service->getPathDistance(30000142, 30002187);
echo "跳跃次数: " . ($distance ?? '无路径') . "\n";
echo "路径: " . implode(' -> ', $path) . "\n";
echo "\n";

// Get path details
if ($path) {
    $details = $service->getPathDetails($path);
    echo "  总跳跃次数: {$details['jump_count']}\n";
    echo "  总欧几里得距离: " . round($details['total_euclidean_distance'], 2) . " 光年\n";
    echo "\n  星系列表:\n";
    foreach ($details['systems'] as $sys) {
        echo "    [{$sys['index']}] {$sys['name']} (ID: {$sys['system_id']})\n";
    }
}
echo "\n";

// Test 2: Short distance
echo "Test 2: 计算 Jita(30000142) 到 Perimeter(30000144) 的路径\n";
list($distance2, $path2) = $service->getPathDistance(30000142, 30000144);
echo "跳跃次数: " . ($distance2 ?? '无路径') . "\n";
if ($path2) {
    echo "路径: " . implode(' -> ', $path2) . "\n";
    echo "欧几里得距离: " . round($service->calculateTotalDistance($path2), 4) . " 光年\n";
}
echo "\n";

// Test 3: System name
echo "Test 3: 获取星系名称\n";
echo "Jita (中文): " . $service->getSystemName(30000142) . "\n";
echo "Jita (English): " . $service->getSystemName(30000142, 'en') . "\n";
echo "Amarr: " . $service->getSystemName(30002187, 'en') . "\n";
echo "\n";

// Test 4: System exists check
echo "Test 4: 验证星系存在\n";
echo "30000142 (Jita) 存在: " . ($service->systemExists(30000142) ? '是' : '否') . "\n";
echo "99999999 (不存在) 存在: " . ($service->systemExists(99999999) ? '是' : '否') . "\n";
echo "\n";

// Test 5: Calculate distance with coordinates
echo "Test 5: 计算欧几里得距离\n";
$dist = $service->getEuclideanDistanceLightYears(30000142, 30002187);
if ($dist !== null) {
    echo "Jita -> Amarr: " . round($dist, 2) . " 光年\n";
} else {
    echo "无法获取坐标\n";
}
echo "\n";

echo "=== 所有测试完成 ===\n";
