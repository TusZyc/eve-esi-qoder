<?php

/**
 * 更新星系连接数据脚本
 * 
 * 从 Fuzzwork SDE 下载最新的 star system jumps 数据
 * 并转换为邻接表格式存储为 JSON
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

echo "=== 开始更新星系连接数据 ===\n\n";

// 下载 CSV 数据
$csvUrl = 'https://www.fuzzwork.co.uk/dump/latest/mapSolarSystemJumps.csv';
echo "从 {$csvUrl} 下载数据...\n";

try {
    $csvContent = file_get_contents($csvUrl);
    if ($csvContent === false) {
        throw new Exception("无法下载 CSV 文件");
    }
    
    echo "✓ 数据下载成功\n";
    echo "文件大小: " . strlen($csvContent) . " 字节\n\n";
    
    // 解析 CSV
    $lines = explode("\n", $csvContent);
    echo "总行数: " . count($lines) . "\n";
    
    // 使用邻接表存储连接关系
    $adjacency = [];
    $jumpCount = 0;
    
    // 跳过 header 行
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) {
            continue;
        }
        
        $parts = explode(',', $line);
        if (count($parts) >= 4) {
            $fromSystem = (int)$parts[2];
            $toSystem = (int)$parts[3];
            
            // 添加双向连接
            if (!isset($adjacency[$fromSystem])) {
                $adjacency[$fromSystem] = [];
            }
            if (!in_array($toSystem, $adjacency[$fromSystem])) {
                $adjacency[$fromSystem][] = $toSystem;
            }
            
            if (!isset($adjacency[$toSystem])) {
                $adjacency[$toSystem] = [];
            }
            if (!in_array($fromSystem, $adjacency[$toSystem])) {
                $adjacency[$toSystem][] = $fromSystem;
            }
            
            $jumpCount++;
        }
    }
    
    echo "跳数统计: {$jumpCount} (单向)\n";
    
    // 统计信息
    $totalSystems = count($adjacency);
    $totalConnections = array_sum(array_map('count', $adjacency)) / 2;
    
    echo "唯一星系数: {$totalSystems}\n";
    echo "总连接数: {$totalConnections} (双向)\n\n";
    
    // 转换为 JSON 格式(字符串键)
    $jsonAdjacency = [];
    foreach ($adjacency as $sysId => $connections) {
        $jsonAdjacency[(string)$sysId] = $connections;
    }
    
    // 保存为 JSON 文件
    $outputFile = __DIR__ . '/data/solar_system_jumps.json';
    echo "保存到: {$outputFile}\n";
    
    $jsonContent = json_encode($jsonAdjacency, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonContent === false) {
        throw new Exception("JSON 编码失败: " . json_last_error_msg());
    }
    
    $result = file_put_contents($outputFile, $jsonContent);
    if ($result === false) {
        throw new Exception("无法保存文件");
    }
    
    echo "✓ 数据保存成功\n";
    echo "文件大小: " . strlen($jsonContent) . " 字节\n\n";
    
    // 显示统计信息
    echo "=== 统计信息 ===\n";
    echo "星系数量: {$totalSystems}\n";
    echo "连接数量: {$totalConnections}\n";
    echo "平均每个星系的连接数: " . round($totalConnections / $totalSystems * 2, 2) . "\n\n";
    
    // 显示示例数据
    echo "=== 示例数据 ===\n";
    $sampleCount = 0;
    foreach ($jsonAdjacency as $sysId => $connections) {
        if ($sampleCount >= 3) break;
        echo "星系 ID {$sysId}: " . implode(', ', array_slice($connections, 0, 5)) . "\n";
        $sampleCount++;
    }
    echo "\n";
    
    // 显示最大连接的星系
    echo "=== 连接最多的星系Top 5 ===\n";
    arsort($adjacency);
    $top5 = array_slice($adjacency, 0, 5, true);
    foreach ($top5 as $sysId => $connections) {
        echo "ID {$sysId}: " . count($connections) . " 个连接\n";
    }
    
    echo "\n=== 更新完成 ===\n";
    
} catch (Exception $e) {
    echo "✗ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
