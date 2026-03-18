<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * 星系距离计算服务
 *
 * 提供基于星系连接关系的跳跃路径计算
 * 使用 BFS 算法计算最短跳跃路径
 */
class SystemDistanceService
{
    /**
     * 星系连接数据文件路径
     *
     * @var string
     */
    protected $jumpsFile;

    /**
     * ESI 基础 URL
     *
     * @var string
     */
    protected $esiBaseUrl;

    /**
     * 数据缓存时间(秒)
     *
     * @var int
     */
    protected $cacheTtl = 86400; // 24小时

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->jumpsFile = base_path('data/solar_system_jumps.json');
        $this->esiBaseUrl = config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/');
    }

    /**
     * 获取星系连接邻接表
     *
     * @return array
     */
    protected function getAdjacencyList(): array
    {
        $cacheKey = 'eve_system_adjacency_list';

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            if (!file_exists($this->jumpsFile)) {
                throw new Exception("星系连接数据文件不存在: {$this->jumpsFile}");
            }

            $data = file_get_contents($this->jumpsFile);
            $adjacency = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON 解析错误: " . json_last_error_msg());
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
        $cacheKey = "eve_system_name_{$systemId}_{$language}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($systemId, $language) {
            $languageParam = $language === 'zh' ? 'zh' : 'en';
            $baseUrl = $this->esiBaseUrl;

            try {
                $response = Http::timeout(10)
                    ->get("{$baseUrl}universe/systems/{$systemId}/", [
                        'datasource' => 'serenity',
                        'language' => $languageParam,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['name'] ?? "Unknown (ID: {$systemId})";
                }
            } catch (Exception $e) {
                // Failed to fetch from ESI, return fallback
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
        $cacheKey = "eve_system_coordinates_{$systemId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($systemId) {
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
                // Return null if coordinates not found
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

        // 获取邻接表
        $adjacency = $this->getAdjacencyList();

        // 检查起点和终点是否存在
        $fromKey = (string) $fromSystemId;
        $toKey = (string) $toSystemId;

        if (!isset($adjacency[$fromKey]) && !isset($adjacency[$toKey])) {
            return [null, []];
        }

        // BFS 队列
        $queue = [$fromSystemId];
        // 记录访问状态和前驱节点
        $visited = [$fromSystemId => true];
        $predecessors = [$fromSystemId => null];
        $distance = [$fromSystemId => 0];

        while (!empty($queue)) {
            $current = array_shift($queue);

            // 获取当前节点的邻居
            $currentKey = (string) $current;
            $neighbors = $adjacency[$currentKey] ?? [];

            foreach ($neighbors as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $predecessors[$neighbor] = $current;
                    $distance[$neighbor] = $distance[$current] + 1;
                    $queue[] = $neighbor;

                    // 找到目标
                    if ($neighbor === $toSystemId) {
                        // 构建路径
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

        // 反转路径
        return array_reverse($path);
    }

    /**
     * 获取两个星系之间的所有路径(限制最大深度)
     *
     * @param int $fromSystemId
     * @param int $toSystemId
     * @param int $maxDepth
     * @param int $maxPaths
     * @return array
     */
    public function getAllPaths(int $fromSystemId, int $toSystemId, int $maxDepth = 10, int $maxPaths = 10): array
    {
        $adjacency = $this->getAdjacencyList();
        $allPaths = [];
        $visited = [$fromSystemId => true];

        $this->dfsFindPaths(
            $adjacency,
            $fromSystemId,
            $toSystemId,
            [$fromSystemId],
            $visited,
            $maxDepth,
            $maxPaths,
            $allPaths
        );

        return $allPaths;
    }

    /**
     * DFS 查找所有路径(辅助方法)
     *
     * @param array $adjacency
     * @param int $current
     * @param int $target
     * @param array $path
     * @param array $visited
     * @param int $maxDepth
     * @param int $maxPaths
     * @param array &$allPaths
     * @return void
     */
    protected function dfsFindPaths(
        array $adjacency,
        int $current,
        int $target,
        array $path,
        array $visited,
        int $maxDepth,
        int $maxPaths,
        array &$allPaths
    ): void {
        // 达到最大路径数
        if (count($allPaths) >= $maxPaths) {
            return;
        }

        // 达到最大深度
        if (count($path) > $maxDepth) {
            return;
        }

        // 找到目标路径
        if ($current === $target) {
            $allPaths[] = $path;
            return;
        }

        $currentKey = (string) $current;
        $neighbors = $adjacency[$currentKey] ?? [];

        foreach ($neighbors as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $visited[$neighbor] = true;
                $this->dfsFindPaths(
                    $adjacency,
                    $neighbor,
                    $target,
                    array_merge($path, [$neighbor]),
                    $visited,
                    $maxDepth,
                    $maxPaths,
                    $allPaths
                );
                unset($visited[$neighbor]);
            }
        }
    }

    /**
     * 获取路径的详细信息(包含星系名称和坐标)
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
        $adjacency = $this->getAdjacencyList();
        return isset($adjacency[(string) $systemId]);
    }

    /**
     * 计算路径上的总跳跃距离(基于欧几里得距离)
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
}
