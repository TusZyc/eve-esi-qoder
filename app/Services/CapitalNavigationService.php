<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Exception;

class CapitalNavigationService
{
    /**
     * 舰船跳跃基础属性
     * highsec: true 表示该舰船可进入高安（战略货舰/长须鲸级/黑隐特勤舰）
     */
    const SHIP_DATA = [
        'jump_freighter' => ['name' => '战略货舰',     'base_range' => 5.0, 'base_fuel' => 10000, 'highsec' => true],
        'rorqual'        => ['name' => '长须鲸级',     'base_range' => 5.0, 'base_fuel' => 4000,  'highsec' => true],
        'bowhead'        => ['name' => '座头鲸级',     'base_range' => 6.0, 'base_fuel' => 12500, 'highsec' => true],
        'black_ops'      => ['name' => '黑隐特勤舰',   'base_range' => 4.0, 'base_fuel' => 700,   'highsec' => true],
        'carrier'        => ['name' => '航空母舰',     'base_range' => 3.5, 'base_fuel' => 3000,  'highsec' => false],
        'dreadnought'    => ['name' => '无畏舰',       'base_range' => 3.5, 'base_fuel' => 3000,  'highsec' => false],
        'supercarrier'   => ['name' => '超级航母',     'base_range' => 3.0, 'base_fuel' => 3000,  'highsec' => false],
        'titan'          => ['name' => '泰坦',         'base_range' => 3.0, 'base_fuel' => 3000,  'highsec' => false],
    ];

    /**
     * 波赫文 (Pochven) 星系 ID 集合 — 旗舰禁入
     */
    const POCHVEN_SYSTEMS = [
        30000157, 30000192, 30001372, 30001445, 30002079, 30002737,
        30005005, 30010141, 30031392, 30000021, 30001413, 30002225,
        30002411, 30002770, 30003495, 30003504, 30040141, 30045328,
        30000206, 30001381, 30002652, 30002702, 30002797, 30003046,
        30005029, 30020141, 30045329,
    ];

    const LIGHT_YEAR_IN_METERS = 9.461e15;

    protected string $systemsFile;
    protected string $jumpsFile;

    public function __construct()
    {
        $this->systemsFile = base_path('data/eve_systems_full.json');
        $this->jumpsFile = base_path('data/solar_system_jumps.json');
    }

    /**
     * 加载全量星系数据（2小时缓存）
     */
    public function loadUniverseData(): array
    {
        return Cache::remember('capital_nav_universe_data', 7200, function () {
            if (!file_exists($this->systemsFile)) {
                throw new Exception("星系数据文件不存在，请先运行 php artisan eve:sync-universe");
            }
            $data = json_decode(file_get_contents($this->systemsFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("星系数据 JSON 解析错误: " . json_last_error_msg());
            }
            return $data;
        });
    }

    /**
     * 加载星门邻接表（24小时缓存）
     */
    public function getStargateAdjacency(): array
    {
        return Cache::remember('capital_nav_stargate_adjacency', 86400, function () {
            if (!file_exists($this->jumpsFile)) {
                return [];
            }
            $data = json_decode(file_get_contents($this->jumpsFile), true);
            return $data ?: [];
        });
    }

    /**
     * 获取舰船类型列表
     */
    public function getShipTypes(): array
    {
        $result = [];
        foreach (self::SHIP_DATA as $key => $data) {
            $result[] = ['key' => $key, 'name' => $data['name'], 'base_range' => $data['base_range'], 'base_fuel' => $data['base_fuel']];
        }
        return $result;
    }

    /**
     * 计算最大跳跃距离
     * JDC: 每级 +20%
     */
    public function calculateJumpRange(string $shipType, int $jdcLevel): float
    {
        $ship = self::SHIP_DATA[$shipType] ?? null;
        if (!$ship) {
            throw new Exception("未知舰船类型: {$shipType}");
        }
        return $ship['base_range'] * (1 + 0.20 * $jdcLevel);
    }

    /**
     * 计算每光年燃料消耗
     * 燃效: 每级 -10%
     * JF技能: 每级 -10%（战略货舰和座头鲸级）
     */
    public function calculateFuelRate(string $shipType, int $fuelEffLevel, int $jfLevel = 0): int
    {
        $ship = self::SHIP_DATA[$shipType] ?? null;
        if (!$ship) {
            throw new Exception("未知舰船类型: {$shipType}");
        }

        $fuelEffReduction = 1 - 0.10 * $fuelEffLevel;
        // 战略货舰和座头鲸级都可以享受战略货舰概论技能加成
        $jfReduction = ($shipType === 'jump_freighter' || $shipType === 'bowhead') ? (1 - 0.10 * $jfLevel) : 1;

        return (int) ceil($ship['base_fuel'] * $fuelEffReduction * $jfReduction);
    }

    /**
     * 计算两个坐标之间的光年距离
     */
    public function distanceLY(array $pos1, array $pos2): float
    {
        $dx = $pos2['x'] - $pos1['x'];
        $dy = $pos2['y'] - $pos1['y'];
        $dz = $pos2['z'] - $pos1['z'];
        return sqrt($dx * $dx + $dy * $dy + $dz * $dz) / self::LIGHT_YEAR_IN_METERS;
    }

    /**
     * 判断星系是否允许指定舰船存在（用于起点验证和星门经过）
     * 战略货舰/长须鲸级/黑隐特勤舰可进入高安，其余旗舰不可
     */
    public function isSystemAllowed(int $systemId, array $systemData, string $shipType = ''): bool
    {
        // 排除 Pochven
        if (in_array($systemId, self::POCHVEN_SYSTEMS)) {
            return false;
        }
        // 高安检查：仅对不可进入高安的舰船排除
        $displaySec = round($systemData['sec'] * 10) / 10;
        if ($displaySec >= 0.5) {
            $canEnterHighsec = !empty($shipType) && (self::SHIP_DATA[$shipType]['highsec'] ?? false);
            if (!$canEnterHighsec) {
                return false;
            }
        }
        return true;
    }

    /**
     * 判断星系是否可以作为跳跃目的地（高安不能开诱导，所有舰船都不能跳到高安）
     */
    public function isJumpTargetAllowed(int $systemId, array $systemData): bool
    {
        if (in_array($systemId, self::POCHVEN_SYSTEMS)) {
            return false;
        }
        $displaySec = round($systemData['sec'] * 10) / 10;
        if ($displaySec >= 0.5) {
            return false;
        }
        return true;
    }

    /**
     * 获取安等显示分类
     */
    public function getSecurityClass(float $security): string
    {
        $display = round($security * 10) / 10;
        if ($display >= 0.5) return 'high';
        if ($display > 0) return 'low';
        return 'null';
    }

    /**
     * 计算两个星系之间的欧几里得距离
     */
    public function getDistance(int $fromId, int $toId): ?array
    {
        $systems = $this->loadUniverseData();
        $from = $systems[(string) $fromId] ?? null;
        $to = $systems[(string) $toId] ?? null;

        if (!$from || !$to) {
            return null;
        }

        $distance = $this->distanceLY(
            ['x' => $from['x'], 'y' => $from['y'], 'z' => $from['z']],
            ['x' => $to['x'], 'y' => $to['y'], 'z' => $to['z']]
        );

        return [
            'from' => [
                'id' => $fromId,
                'name' => $from['name'],
                'security' => $from['sec'],
                'region' => $from['rn'],
            ],
            'to' => [
                'id' => $toId,
                'name' => $to['name'],
                'security' => $to['sec'],
                'region' => $to['rn'],
            ],
            'distance_ly' => round($distance, 4),
        ];
    }

    /**
     * 获取一跳可达星系列表
     */
    public function getReachableSystems(int $originId, string $shipType, int $jdcLevel, int $fuelEffLevel, int $jfLevel = 0): array
    {
        $systems = $this->loadUniverseData();
        $origin = $systems[(string) $originId] ?? null;
        if (!$origin) {
            throw new Exception("起始星系不存在: {$originId}");
        }

        // 检查起始星系是否允许该舰船存在
        if (!$this->isSystemAllowed($originId, $origin, $shipType)) {
            $displaySec = round($origin['sec'] * 10) / 10;
            throw new Exception("起始星系 {$origin['name']} 安等 {$displaySec}，该舰船无法进入");
        }

        $maxRange = $this->calculateJumpRange($shipType, $jdcLevel);
        $fuelRate = $this->calculateFuelRate($shipType, $fuelEffLevel, $jfLevel);
        $originPos = ['x' => $origin['x'], 'y' => $origin['y'], 'z' => $origin['z']];

        $reachable = [];
        foreach ($systems as $sysId => $sys) {
            $sysIdInt = (int) $sysId;
            if ($sysIdInt === $originId) continue;
            if (!$this->isJumpTargetAllowed($sysIdInt, $sys)) continue;
            $dist = $this->distanceLY($originPos, ['x' => $sys['x'], 'y' => $sys['y'], 'z' => $sys['z']]);
            if ($dist <= $maxRange) {
                $reachable[] = [
                    'system_id' => $sysIdInt,
                    'name' => $sys['name'],
                    'distance_ly' => round($dist, 4),
                    'fuel' => (int) ceil($dist * $fuelRate),
                    'region_name' => $sys['rn'],
                    'security' => $sys['sec'],
                ];
            }
        }

        // 按距离排序
        usort($reachable, fn($a, $b) => $a['distance_ly'] <=> $b['distance_ly']);

        return [
            'jump_range' => round($maxRange, 2),
            'fuel_rate' => $fuelRate,
            'origin' => [
                'id' => $originId,
                'name' => $origin['name'],
                'security' => $origin['sec'],
                'region' => $origin['rn'],
            ],
            'systems' => $reachable,
            'count' => count($reachable),
        ];
    }

    /**
     * 规划旗舰路线
     *
     * @param bool $useStargates true=混合模式(Dijkstra最小燃料), false=纯跳跃(BFS最少跳跃)
     */
    public function planRoute(int $fromId, int $toId, string $shipType, int $jdcLevel, int $fuelEffLevel, int $jfLevel, bool $useStargates): array
    {
        $systems = $this->loadUniverseData();
        $from = $systems[(string) $fromId] ?? null;
        $to = $systems[(string) $toId] ?? null;

        if (!$from || !$to) {
            return ['found' => false, 'reason' => '起始或目标星系不存在'];
        }

        // 检查起始星系是否允许该舰船存在
        if (!$this->isSystemAllowed($fromId, $from, $shipType)) {
            $displaySec = round($from['sec'] * 10) / 10;
            return ['found' => false, 'reason' => "起始星系 {$from['name']} 安等 {$displaySec}，该舰船无法进入"];
        }
        // 检查目标星系：纯跳跃模式目标必须能开诱导，混合模式可通过星门到达高安
        if ($useStargates) {
            if (!$this->isSystemAllowed($toId, $to, $shipType)) {
                $displaySec = round($to['sec'] * 10) / 10;
                return ['found' => false, 'reason' => "目标星系 {$to['name']} 安等 {$displaySec}，该舰船无法进入"];
            }
        } else {
            if (!$this->isJumpTargetAllowed($toId, $to)) {
                $displaySec = round($to['sec'] * 10) / 10;
                return ['found' => false, 'reason' => "目标星系 {$to['name']} 安等 {$displaySec}，高安无法开启诱导力场"];
            }
        }

        $maxRange = $this->calculateJumpRange($shipType, $jdcLevel);
        $fuelRate = $this->calculateFuelRate($shipType, $fuelEffLevel, $jfLevel);

        if ($useStargates) {
            $route = $this->dijkstraHybridRoute($fromId, $toId, $maxRange, $fuelRate, $systems, $shipType);
        } else {
            $route = $this->bfsJumpRoute($fromId, $toId, $maxRange, $fuelRate, $systems, $shipType);
        }

        $route['jump_range'] = round($maxRange, 2);
        $route['fuel_rate'] = $fuelRate;

        return $route;
    }

    /**
     * BFS 纯跳跃路径（最少跳跃次数）
     */
    protected function bfsJumpRoute(int $fromId, int $toId, float $maxRange, int $fuelRate, array $systems, string $shipType = ''): array
    {
        if ($fromId === $toId) {
            return ['found' => true, 'jumps' => 0, 'gates' => 0, 'total_fuel' => 0, 'total_distance' => 0, 'path' => []];
        }

        $visited = [$fromId => true];
        $queue = [['id' => $fromId, 'path' => [], 'totalFuel' => 0, 'totalDist' => 0]];
        $maxIterations = 50000;
        $iterations = 0;

        while (!empty($queue) && $iterations < $maxIterations) {
            $current = array_shift($queue);
            $iterations++;

            $curSys = $systems[(string) $current['id']];
            $curPos = ['x' => $curSys['x'], 'y' => $curSys['y'], 'z' => $curSys['z']];

            // 查找跳跃可达星系（高安不能开诱导，所有舰船都不能跳到高安）
            foreach ($systems as $sysId => $sys) {
                $sysIdInt = (int) $sysId;
                if (isset($visited[$sysIdInt])) continue;
                if (!$this->isJumpTargetAllowed($sysIdInt, $sys)) continue;

                $dist = $this->distanceLY($curPos, ['x' => $sys['x'], 'y' => $sys['y'], 'z' => $sys['z']]);
                if ($dist > $maxRange) continue;

                $fuel = (int) ceil($dist * $fuelRate);
                $step = [
                    'type' => 'jump',
                    'from_id' => $current['id'],
                    'from_name' => $curSys['name'],
                    'to_id' => $sysIdInt,
                    'to_name' => $sys['name'],
                    'distance_ly' => round($dist, 4),
                    'fuel' => $fuel,
                    'region_name' => $sys['rn'],
                    'security' => $sys['sec'],
                ];

                $newPath = array_merge($current['path'], [$step]);
                $newFuel = $current['totalFuel'] + $fuel;
                $newDist = $current['totalDist'] + $dist;

                if ($sysIdInt === $toId) {
                    return [
                        'found' => true,
                        'jumps' => count($newPath),
                        'gates' => 0,
                        'total_fuel' => $newFuel,
                        'total_distance' => round($newDist, 4),
                        'path' => $newPath,
                    ];
                }

                $visited[$sysIdInt] = true;
                $queue[] = ['id' => $sysIdInt, 'path' => $newPath, 'totalFuel' => $newFuel, 'totalDist' => $newDist];
            }
        }

        return ['found' => false, 'reason' => '无法通过纯跳跃到达目标星系'];
    }

    /**
     * Dijkstra 混合路径（星门+跳跃，最小燃料）
     */
    protected function dijkstraHybridRoute(int $fromId, int $toId, float $maxRange, int $fuelRate, array $systems, string $shipType = ''): array
    {
        if ($fromId === $toId) {
            return ['found' => true, 'jumps' => 0, 'gates' => 0, 'total_fuel' => 0, 'total_distance' => 0, 'path' => []];
        }

        $adjacency = $this->getStargateAdjacency();

        // Dijkstra: cost = fuel consumption
        $dist = [$fromId => 0];
        $prev = []; // $prev[nodeId] = [prevId, stepInfo]
        $visited = [];
        // 使用数组模拟优先队列 [cost, nodeId]
        $pq = [[0, $fromId]];
        $maxIterations = 100000;
        $iterations = 0;

        while (!empty($pq) && $iterations < $maxIterations) {
            $iterations++;
            // 取出最小 cost 节点
            usort($pq, fn($a, $b) => $a[0] <=> $b[0]);
            $entry = array_shift($pq);
            $currentCost = $entry[0];
            $currentId = $entry[1];

            if (isset($visited[$currentId])) continue;
            $visited[$currentId] = true;

            if ($currentId === $toId) {
                // 回溯路径
                return $this->reconstructDijkstraPath($prev, $fromId, $toId, $systems);
            }

            $curSys = $systems[(string) $currentId] ?? null;
            if (!$curSys) continue;
            $curPos = ['x' => $curSys['x'], 'y' => $curSys['y'], 'z' => $curSys['z']];

            // 星门邻居（cost = 0）
            $gateNeighbors = $adjacency[(string) $currentId] ?? [];
            foreach ($gateNeighbors as $neighborId) {
                if (isset($visited[$neighborId])) continue;
                $neighborSys = $systems[(string) $neighborId] ?? null;
                if (!$neighborSys) continue;
                if (!$this->isSystemAllowed($neighborId, $neighborSys, $shipType)) continue;

                $newCost = $currentCost; // 星门无燃料成本
                if (!isset($dist[$neighborId]) || $newCost < $dist[$neighborId]) {
                    $dist[$neighborId] = $newCost;
                    $prev[$neighborId] = [$currentId, [
                        'type' => 'stargate',
                        'distance_ly' => 0,
                        'fuel' => 0,
                    ]];
                    $pq[] = [$newCost, $neighborId];
                }
            }

            // 跳跃邻居（cost = fuel，高安不能开诱导）
            foreach ($systems as $sysId => $sys) {
                $sysIdInt = (int) $sysId;
                if (isset($visited[$sysIdInt])) continue;
                if ($sysIdInt === $currentId) continue;
                if (!$this->isJumpTargetAllowed($sysIdInt, $sys)) continue;

                $jumpDist = $this->distanceLY($curPos, ['x' => $sys['x'], 'y' => $sys['y'], 'z' => $sys['z']]);
                if ($jumpDist > $maxRange) continue;

                $fuel = (int) ceil($jumpDist * $fuelRate);
                $newCost = $currentCost + $fuel;

                if (!isset($dist[$sysIdInt]) || $newCost < $dist[$sysIdInt]) {
                    $dist[$sysIdInt] = $newCost;
                    $prev[$sysIdInt] = [$currentId, [
                        'type' => 'jump',
                        'distance_ly' => round($jumpDist, 4),
                        'fuel' => $fuel,
                    ]];
                    $pq[] = [$newCost, $sysIdInt];
                }
            }
        }

        return ['found' => false, 'reason' => '无法到达目标星系'];
    }

    /**
     * 回溯 Dijkstra 路径
     */
    protected function reconstructDijkstraPath(array $prev, int $fromId, int $toId, array $systems): array
    {
        $path = [];
        $current = $toId;

        while ($current !== $fromId && isset($prev[$current])) {
            [$prevId, $stepInfo] = $prev[$current];
            $fromSys = $systems[(string) $prevId] ?? [];
            $toSys = $systems[(string) $current] ?? [];

            array_unshift($path, [
                'type' => $stepInfo['type'],
                'from_id' => $prevId,
                'from_name' => $fromSys['name'] ?? '',
                'to_id' => $current,
                'to_name' => $toSys['name'] ?? '',
                'distance_ly' => $stepInfo['distance_ly'],
                'fuel' => $stepInfo['fuel'],
                'region_name' => $toSys['rn'] ?? '',
                'security' => $toSys['sec'] ?? 0,
            ]);

            $current = $prevId;
        }

        $jumps = 0;
        $gates = 0;
        $totalFuel = 0;
        $totalDist = 0;

        foreach ($path as $step) {
            if ($step['type'] === 'jump') {
                $jumps++;
                $totalFuel += $step['fuel'];
                $totalDist += $step['distance_ly'];
            } else {
                $gates++;
            }
        }

        return [
            'found' => true,
            'jumps' => $jumps,
            'gates' => $gates,
            'total_fuel' => $totalFuel,
            'total_distance' => round($totalDist, 4),
            'path' => $path,
        ];
    }
}
