<?php

require __DIR__.'/vendor/autoload.php';

// 创建应用实例
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

// 测试获取星系ID
echo "=== 获取星系ID测试 ===\n\n";

// 测试1: 使用 POST /universe/ids/ 接口
$response = Http::post('https://ali-esi.evepc.163.com/latest/universe/ids/', [
    '埃玛马克',
    '艾瑟特'
]);

echo "请求URL: " . $response->effectiveUri() . "\n";
echo "状态码: " . $response->status() . "\n";
echo "响应内容: " . $response->body() . "\n\n";

// 解析JSON
if ($response->successful()) {
    $data = $response->json();
    echo "解析结果:\n";
    print_r($data);
}