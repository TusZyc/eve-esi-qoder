<?php
require "/var/www/html/vendor/autoload.php";
$app = require_once "/var/www/html/bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

$svc = app(\App\Services\KillmailService::class);
$pilotId = 2112016162;

echo "====== KM 搜索验证测试 ======\n\n";

echo "--- 1. pilot 搜索 (无筛选) ---\n";
$r = $svc->advancedSearch(["entity_type"=>"pilot", "entity_id"=>$pilotId]);
echo "结果: " . count($r) . " 条\n\n";

echo "--- 2. pilot + 受害者 ---\n";
$r = $svc->advancedSearch(["entity_type"=>"pilot", "entity_id"=>$pilotId, "involvement"=>"victim"]);
echo "结果: " . count($r) . " 条\n\n";

echo "--- 3. pilot + 最后一击 ---\n";
$r = $svc->advancedSearch(["entity_type"=>"pilot", "entity_id"=>$pilotId, "involvement"=>"finalblow"]);
echo "结果: " . count($r) . " 条\n";
$ships = []; $systems = [];
foreach ($r as $k) {
    $sid = $k["ship_type_id"] ?? 0;
    if ($sid) $ships[$sid] = $k["ship_name"] ?? "?";
    $sysId = $k["system_id"] ?? 0;
    if ($sysId) $systems[$sysId] = $k["system_name"] ?? "?";
}
echo "涉及 " . count($ships) . " 种舰船, " . count($systems) . " 个星系\n\n";

if (!empty($ships)) {
    $testShip = array_key_first($ships);
    $testShipName = $ships[$testShip];
    echo "--- 4. pilot + 最后一击 + 舰船={$testShipName}({$testShip}) ---\n";
    $r = $svc->advancedSearch(["entity_type"=>"pilot", "entity_id"=>$pilotId, "involvement"=>"finalblow", "ship_id"=>$testShip]);
    echo "结果: " . count($r) . " 条\n\n";
}

if (!empty($systems)) {
    $testSys = array_key_first($systems);
    $testSysName = $systems[$testSys];
    echo "--- 5. pilot + 最后一击 + 星系={$testSysName}({$testSys}) ---\n";
    $r = $svc->advancedSearch(["entity_type"=>"pilot", "entity_id"=>$pilotId, "involvement"=>"finalblow", "system_id"=>$testSys]);
    echo "结果: " . count($r) . " 条\n\n";
}

echo "--- 6. pilot + 受害者 + 时间范围 ---\n";
$r = $svc->advancedSearch(["entity_type"=>"pilot", "entity_id"=>$pilotId, "involvement"=>"victim", "time_start"=>"2025-01-01", "time_end"=>"2026-03-15"]);
echo "结果: " . count($r) . " 条\n\n";

echo "--- 7. 舰船搜索 ship=太空舱(670) ---\n";
$r = $svc->advancedSearch(["entity_type"=>"ship", "entity_id"=>670]);
echo "结果: " . count($r) . " 条\n\n";

echo "--- 8. 星系搜索 system=吉他(30000142) ---\n";
$r = $svc->advancedSearch(["entity_type"=>"system", "entity_id"=>30000142]);
echo "结果: " . count($r) . " 条\n\n";

echo "--- 9. 建筑搜索 ship=星城(35834) ---\n";
$r = $svc->advancedSearch(["entity_type"=>"ship", "entity_id"=>35834]);
echo "结果: " . count($r) . " 条\n\n";

echo "====== 测试完成 ======\n";
