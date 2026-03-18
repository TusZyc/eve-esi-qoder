<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * 纯 ESI 版星系距离计算服务
 *
 * 不依赖外部 SDE 数据,完全通过 ESI API 动态获取星系连接关系
 * 使用星门(destination)信息构建邻接表
 */
class EsiSystemDistanceService
{
    /**
     * ESI 基础 URL
     *
     * @var string
     */
    protected $esiBaseUrl;

    /**
     * 邻接表缓存时间(秒)
     *
     * @var int
     */
    protected $adjacencyCacheTtl = 86400; // 24小时

    /**
     * 星系信息缓存时间(秒)
     *
     * @var int
     */
    protected $systemCacheTtl = 86400; // 24小时

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->esiBaseUrl = config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/');
    }

    /**
     * 获取星系的星门列表
     *
     * @param int $systemId
     * @return array
     */
    protected function getSystemStargates(int $systemId): array
    {
        $cacheKey = "esi_system_stargates_{$systemId}";

        return Cache::remember($cacheKey, $this->systemCacheTtl, function () use ($systemId) {
            try {
                $response = Http::timeout(10)
                    ->get("{$this->esiBaseUrl}universe/systems/{$systemId}/", [
                        'datasource' => 'tranquility',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['stargates'] ?? [];
                }
            } catch (Exception $e) {
                // 返回空数组
            }

            return [];
        });
    }

    /**
     * 获取星门的目标星系 ID
     *
     * @param int $stargateId
     * @return int|null
     */
    protected function getStargateDestination(int $stargateId): ?int
    {
        $cacheKey = "esi_stargate_destination_{$stargateId}";

        return Cache::remember($cacheKey, $this->systemCacheTtl, function () use ($stargateId) {
            try {
                $response = Http::timeout(10)
                    ->get("{$this->esiBaseUrl}universe/stargates/{$stargateId}/", [
                        'datasource' => 'tranquility',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['destination']['system_id'])) {
                        return (int) $data['destination']['system_id'];
                    }
                }
            } catch (Exception $e) {
                // 返回 null
            }

            return null;
        });
    }

    /**
     * 获取星系的邻接表(相邻星系列表)
     *
     * @param int $systemId
     * @return array
     */
    protected function getSystemAdjacency(int $systemId): array
    {
        $cacheKey = "esi_system_adjacency_{$systemId}";

        return Cache::remember($cacheKey, $this->adjacencyCacheTtl, function () use ($systemId) {
            $stargates = $this->getSystemStargates($systemId);
            $adjacentSystems = [];

            foreach ($stargates as $stargateId) {
                $destSystemId = $this->getStargateDestination($stargateId);
                if ($destSystemId !== null && $destSystemId !== $systemId) {
                    $adjacentSystems[] = $destSystemId;
                }
            }

            // 去重
            return array_values(array_unique($adjacentSystems));
        });
    }

    /**
     * 获取星系的完整邻接表(所有星系及其邻居)
     * 注意: 这会查询所有已知星系,可能需要较长时间
     *
     * @return array
     */
    protected function getCompleteAdjacencyList(): array
    {
        $cacheKey = 'esi_complete_adjacency_list';

        return Cache::remember($cacheKey, $this->adjacencyCacheTtl * 7, function () {
            $adjacency = [];
            $checked = [];

            // 获取 SDE 提供的起始星系列表
            try {
                $response = Http::timeout(30)
                    ->get("{$this->esiBaseUrl}universe/systems/", [
                        'datasource' => 'tranquility',
                    ]);

                if (!$response->successful()) {
                    throw new Exception('Failed to get system list');
                }

                $allSystemIds = $response->json();
                echo "Found " . count($allSystemIds) . " systems\n";

                // 只处理前 100 个作为示例(避免超时)
                $sampleSize = min(100, count($allSystemIds));
                $sampleIds = array_slice($allSystemIds, 0, $sampleSize);

                foreach ($sampleIds as $systemId) {
                    $adjacency[$systemId] = $this->getSystemAdjacency($systemId);
                    $checked[$systemId] = true;

                    // 打印进度
                    if (count($checked) % 20 === 0) {
                        echo "Processed " . count($checked) . " systems\n";
                    }
                }

            } catch (Exception $e) {
                // 如果 ESI 请求失败,返回空数组
            }

            return $adjacency;
        });
    }

    /**
     * 获取星系名称
     *
     * @param int $systemId
     * @param string $language
     * @return string
     */
    public function getSystemName(int $systemId, string $language = 'zh'): string
    {
        $cacheKey = "esi_system_name_{$systemId}_{$language}";

        return Cache::remember($cacheKey, $this->systemCacheTtl, function () use ($systemId, $language) {
            try {
                $langParam = $language === 'zh' ? 'zh' : 'en';
                $response = Http::timeout(10)
                    ->get("{$this->esiBaseUrl}universe/systems/{$systemId}/", [
                        'datasource' => 'serenity',
                        'language' => $langParam,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['name'] ?? "Unknown (ID: {$systemId})";
                }
            } catch (Exception $e) {
                // Fallback
            }

            return "Unknown (ID: {$systemId})";
        });
    }

    /**
     * 获取星系坐标
     *
     * @param int $systemId
     * @return array|null
     */
    public function getSystemCoordinates(int $systemId): ?array
    {
        $cacheKey = "esi_system_coordinates_{$systemId}";

        return Cache::remember($cacheKey, $this->systemCacheTtl, function () use ($systemId) {
            try {
                $response = Http::timeout(10)
                    ->get("{$this->esiBaseUrl}universe/systems/{$systemId}/", [
                        'datasource' => 'tranquility',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['position'])) {
                        return [
                            'x' => $data['position']['x'],
                            'y' => $data['position']['y'],
                            'z' => $data['position']['z'],
                        ];
                    }
                }
            } catch (Exception $e) {
                // Return null
            }

            return null;
        });
    }

    /**
     * 计算两个星系之间的欧几里得距离(米)
     *
     * @param int $fromSystemId
     * @param int $toSystemId
     * @return float|null
     */
    public function getEuclideanDistance(int $fromSystemId, int $toSystemId): ?float
    {
        $fromCoords = $this->getSystemCoordinates($fromSystemId);
        $toCoords = $this->getSystemCoordinates($toSystemId);

        if (!$fromCoords || !$toCoords) {
            return null;
        }

        $dx = $toCoords['x'] - $fromCoords['x'];
        $dy = $toCoords['y'] - $fromCoords['y'];
        $dz = $toCoords['z'] - $fromCoords['z'];

        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    /**
     * 计算两个星系之间的欧几里得距离(光年)
     *
     * @param int $fromSystemId
     * @param int $toSystemId
     * @return float|null
     */
    public function getEuclideanDistanceLightYears(int $fromSystemId, int $toSystemId): ?float
    {
        $distanceMeters = $this->getEuclideanDistance($fromSystemId, $toSystemId);

        if ($distanceMeters === null) {
            return null;
        }

        // 1 光年 = 9.461e+15 米
        return $distanceMeters / 9.461e+15;
    }

    /**
     * 使用 BFS 算法查找两个星系之间的最短跳跃路径
     *
     * 通过 ESI 动态获取星系连接关系
     *
     * @param int $fromSystemId
     * @param int $toSystemId
     * @return array [distance, path] or [null, []] if no path found
     */
    public function getPathDistance(int $fromSystemId, int $toSystemId): array
    {
        // 验证输入
        if ($fromSystemId === $toSystemId) {
            return [0, [$fromSystemId]];
        }

        // 检查起点是否存在相邻星系
        $fromAdjacency = $this->getSystemAdjacency($fromSystemId);
        if (empty($fromAdjacency)) {
            $fromSystems = $this->getAllSystems();
            if (!in_array($fromSystemId, $fromSystems)) {
                return [null, []];
            }
        }

        // BFS 队列
        $queue = [$fromSystemId];
        $visited = [$fromSystemId => true];
        $predecessors = [$fromSystemId => null];
        $distance = [$fromSystemId => 0];

        while (!empty($queue)) {
            $current = array_shift($queue);

            // 获取当前节点的邻居
            $neighbors = $this->getSystemAdjacency($current);

            foreach ($neighbors as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $predecessors[$neighbor] = $current;
                    $distance[$neighbor] = $distance[$current] + 1;
                    $queue[] = $neighbor;

                    // 找到目标
                    if ($neighbor === $toSystemId) {
                        $path = $this->reconstructPath($predecessors, $fromSystemId, $toSystemId);
                        return [$distance[$toSystemId], $path];
                    }
                }
            }
        }

        // 未找到路径
        return [null, []];
    }

    /**
     * 获取所有已知星系列表(通过 SDE 缓存或 ESI)
     *
     * @return array
     */
    protected function getAllSystems(): array
    {
        // 优先使用 SDE 数据(如果存在)
        $sdeFile = base_path('data/solar_system_jumps.json');
        if (file_exists($sdeFile)) {
            $data = file_get_contents($sdeFile);
            $sdeSystems = json_decode($data, true);
            if ($sdeSystems && json_last_error() === JSON_ERROR_NONE) {
                return array_map('intval', array_keys($sdeSystems));
            }
        }

        // Fallback: 从 ESI 获取
        try {
            $response = Http::timeout(30)
                ->get("{$this->esiBaseUrl}universe/systems/", [
                    'datasource' => 'tranquility',
                ]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (Exception $e) {
            // Return empty
        }

        return [];
    }

    /**
     * 重构 BFS 路径
     *
     * @param array $predecessors
     * @param int $start
     * @param int $end
     * @return array
     */
    protected function reconstructPath(array $predecessors, int $start, int $end): array
    {
        $path = [];
        $current = $end;

        while ($current !== null) {
            $path[] = $current;
            $current = $predecessors[$current];
        }

        return array_reverse($path);
    }

    /**
     * 获取两个星系之间是否直接相连
     *
     * @param int $fromSystemId
     * @param int $toSystemId
     * @return bool
     */
    public function isConnected(int $fromSystemId, int $toSystemId): bool
    {
        $neighbors = $this->getSystemAdjacency($fromSystemId);
        return in_array($toSystemId, $neighbors);
    }

    /**
     * 获取星系的邻居数量
     *
     * @param int $systemId
     * @return int
     */
    public function getNeighborCount(int $systemId): int
    {
        return count($this->getSystemAdjacency($systemId));
    }

    /**
     * 获取路径的详细信息
     *
     * @param array $path
     * @return array
     */
    public function getPathDetails(array $path): array
    {
        $details = [];
        $totalDistance = 0;

        for ($i = 0; $i < count($path); $i++) {
            $systemId = $path[$i];
            $name = $this->getSystemName($systemId);
            $coords = $this->getSystemCoordinates($systemId);

            $details[] = [
                'system_id' => $systemId,
                'name' => $name,
                'coordinates' => $coords,
                'index' => $i,
            ];

            // 计算相邻星系间的距离
            if ($i > 0) {
                $prevSystemId = $path[$i - 1];
                $distance = $this->getEuclideanDistanceLightYears($prevSystemId, $systemId);
                if ($distance !== null) {
                    $totalDistance += $distance;
                }
            }
        }

        return [
            'jump_count' => count($path) - 1,
            'total_euclidean_distance' => $totalDistance,
            'systems' => $details,
        ];
    }

    /**
     * 验证星系是否存在
     *
     * @param int $systemId
     * @return bool
     */
    public function systemExists(int $systemId): bool
    {
        $systems = $this->getAllSystems();
        return in_array($systemId, $systems);
    }

    /**
     * 计算路径上的总跳跃距离
     *
     * @param array $path
     * @return float|null
     */
    public function calculateTotalDistance(array $path): ?float
    {
        if (count($path) < 2) {
            return 0;
        }

        $total = 0;
        for ($i = 1; $i < count($path); $i++) {
            $distance = $this->getEuclideanDistanceLightYears($path[$i - 1], $path[$i]);
            if ($distance !== null) {
                $total += $distance;
            }
        }

        return $total;
    }

    /**
     * 清除星系相关缓存
     *
     * @param int|null $systemId 可选,清除特定星系的缓存
     * @return void
     */
    public function clearCache(?int $systemId = null): void
    {
        if ($systemId !== null) {
            // 清除特定星系的缓存
            Cache::forget("esi_system_stargates_{$systemId}");
            Cache::forget("esi_system_adjacency_{$systemId}");
            Cache::forget("esi_system_name_{$systemId}_zh");
            Cache::forget("esi_system_name_{$systemId}_en");
            Cache::forget("esi_system_coordinates_{$systemId}");
            Cache::forget("esi_stargate_destination_*");
        } else {
            // 清除所有缓存
            Cache::tags(['esi_system_adjacency'])->flush();
        }
    }
}
