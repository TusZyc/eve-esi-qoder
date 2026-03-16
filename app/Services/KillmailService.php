<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\EveDataService;

class KillmailService
{
    protected $kbUrl = 'https://kb.ceve-market.org';
    protected $betaKbUrl = 'https://beta.ceve-market.org';
    protected $esiBaseUrl;
    protected $datasource;
    protected $eveData;

    public function __construct(EveDataService $eveData)
    {
        $this->eveData = $eveData;
        $this->esiBaseUrl = rtrim(config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/'), '/') . '/';
        $this->datasource = config('esi.datasource', 'serenity');
    }

    protected function kbHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
            'Referer' => $this->kbUrl . '/',
        ];
    }

    /**
     * 获取 beta KB XSRF cookies (带缓存, 避免重复请求)
     * @return array [reDive, gbf] 或 ['', ''] 失败时
     */
    protected function getBetaKbXsrfCookies(): array
    {
        $cached = Cache::get('kb:xsrf_cookies');
        if ($cached && !empty($cached[0]) && !empty($cached[1])) {
            return $cached;
        }

        // 负缓存: 最近刚失败过，跳过重试避免阻塞请求
        if (Cache::get('kb:xsrf_cookies_fail')) {
            return ['', ''];
        }

        // 最多重试 2 次 (首次超时可能是 DNS/TLS 冷启动)
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                // 用根路径获取 cookies (比 /search 快 10x, 返回相同 cookies)
                $cookieResponse = Http::timeout(10)
                    ->withHeaders($this->kbHeaders())
                    ->get("{$this->betaKbUrl}/");

                $reDive = '';
                $gbf = '';
                $setCookies = $cookieResponse->headers()['set-cookie'] ?? [];
                foreach ($setCookies as $cookie) {
                    if (preg_match('/^ReDive=([^;]+)/', $cookie, $m)) $reDive = $m[1];
                    if (preg_match('/^GranblueFantasy=([^;]+)/', $cookie, $m)) $gbf = $m[1];
                }

                if (!empty($reDive) && !empty($gbf)) {
                    Cache::put('kb:xsrf_cookies', [$reDive, $gbf], 1800); // 缓存 30 分钟
                    Cache::forget('kb:xsrf_cookies_fail');
                    return [$reDive, $gbf];
                }
            } catch (\Exception $e) {
                Log::debug("getBetaKbXsrfCookies attempt {$attempt} failed: " . $e->getMessage());
                if ($attempt < 2) {
                    usleep(500000); // 500ms 后重试
                }
            }
        }

        // 负缓存 60 秒, 避免每次搜索都阻塞 20s+ 等待超时
        Cache::put('kb:xsrf_cookies_fail', true, 60);
        Log::warning("getBetaKbXsrfCookies: beta.ceve-market.org 不可用, 60s 内跳过重试");

        return ['', ''];
    }

    // ========================================================
    // Protobuf 解码工具 (用于解析 beta KB API 响应)
    // ========================================================

    protected function pbDecodeVarint(string $data, int $offset, int &$newOffset): int
    {
        $result = 0;
        $shift = 0;
        $len = strlen($data);
        while ($offset < $len) {
            $byte = ord($data[$offset]);
            $result |= ($byte & 0x7F) << $shift;
            $offset++;
            $shift += 7;
            if (($byte & 0x80) === 0) break;
        }
        $newOffset = $offset;
        return $result;
    }

    protected function pbParseMessage(string $data): array
    {
        $fields = [];
        $offset = 0;
        $len = strlen($data);

        while ($offset < $len) {
            if ($offset >= $len) break;
            $tag = $this->pbDecodeVarint($data, $offset, $offset);
            $fieldNum = $tag >> 3;
            $wireType = $tag & 0x07;

            switch ($wireType) {
                case 0:
                    $value = $this->pbDecodeVarint($data, $offset, $offset);
                    break;
                case 1:
                    if ($offset + 8 > $len) return $fields;
                    $value = substr($data, $offset, 8);
                    $offset += 8;
                    break;
                case 2:
                    $length = $this->pbDecodeVarint($data, $offset, $offset);
                    if ($offset + $length > $len) return $fields;
                    $value = substr($data, $offset, $length);
                    $offset += $length;
                    break;
                case 5:
                    if ($offset + 4 > $len) return $fields;
                    $value = substr($data, $offset, 4);
                    $offset += 4;
                    break;
                default:
                    return $fields;
            }

            $fields[] = ['field' => $fieldNum, 'wire' => $wireType, 'value' => $value];
        }
        return $fields;
    }

    protected function pbGetVarint(array $fields, int $fieldNum): ?int
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 0) {
                return (int) $f['value'];
            }
        }
        return null;
    }

    protected function pbGetString(array $fields, int $fieldNum): ?string
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 2) {
                $inner = $f['value'];
                if (preg_match('/^[\x20-\x7E\xC0-\xFF][\x20-\x7E\x80-\xFF]*$/s', $inner) && mb_check_encoding($inner, 'UTF-8')) {
                    return $inner;
                }
                $sub = $this->pbParseMessage($inner);
                foreach ($sub as $sf) {
                    if ($sf['field'] === 1 && $sf['wire'] === 2 && mb_check_encoding($sf['value'], 'UTF-8')) {
                        return $sf['value'];
                    }
                }
                return null;
            }
        }
        return null;
    }

    protected function pbGetDouble(array $fields, int $fieldNum): ?float
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 1 && strlen($f['value']) === 8) {
                return unpack('e', $f['value'])[1];
            }
        }
        return null;
    }

    // ========================================================
    // Protobuf 编码工具 (用于构造 beta KB search API 请求)
    // ========================================================

    protected function pbEncodeVarint(int $val): string
    {
        $bytes = '';
        if ($val === 0) return chr(0);
        while ($val > 0) {
            $byte = $val & 0x7F;
            $val >>= 7;
            if ($val > 0) $byte |= 0x80;
            $bytes .= chr($byte);
        }
        return $bytes;
    }

    protected function pbEncodeString(int $fieldNum, string $str): string
    {
        return $this->pbEncodeVarint(($fieldNum << 3) | 2) . $this->pbEncodeVarint(strlen($str)) . $str;
    }

    protected function pbEncodePackedInt64(int $fieldNum, array $values): string
    {
        $packed = '';
        foreach ($values as $v) {
            $packed .= $this->pbEncodeVarint($v);
        }
        return $this->pbEncodeVarint(($fieldNum << 3) | 2) . $this->pbEncodeVarint(strlen($packed)) . $packed;
    }

    /**
     * 编码嵌套消息 (wire type 2)
     */
    protected function pbEncodeMessage(int $fieldNum, string $inner): string
    {
        return $this->pbEncodeVarint(($fieldNum << 3) | 2) . $this->pbEncodeVarint(strlen($inner)) . $inner;
    }

    /**
     * 编码 Google Timestamp (seconds since epoch)
     */
    protected function pbEncodeTimestamp(int $fieldNum, int $seconds): string
    {
        $inner = $this->pbEncodeVarint((1 << 3) | 0) . $this->pbEncodeVarint($seconds);
        return $this->pbEncodeMessage($fieldNum, $inner);
    }

    // ========================================================
    // 自动补全搜索
    // ========================================================

    /**
     * 统一自动补全接口
     * @param string $query 搜索关键词
     * @param string $type character|corporation|alliance|ship|system
     * @return array [{id, name, type}]
     */
    public function searchAutocomplete(string $query, string $type): array
    {
        $cacheKey = "kb:ac:" . md5("{$type}:{$query}");

        return Cache::remember($cacheKey, 300, function () use ($query, $type) {
            switch ($type) {
                case 'character':
                case 'corporation':
                case 'alliance':
                    return $this->searchKbAutocomplete($query, $type);
                case 'ship':
                    return $this->searchKbShipAutocomplete($query);
                case 'system':
                    $local = $this->searchLocalEntities($query, 'system');
                    if (!empty($local)) return $local;
                    return $this->searchEsiSystems($query);
                default:
                    return [];
            }
        });
    }

    /**
     * KB 自动补全 (character/corporation/alliance)
     */
    protected function searchKbAutocomplete(string $query, string $type): array
    {
        $kbTypeMap = [
            'character' => 'char',
            'corporation' => 'corp',
            'alliance' => 'alliance',
        ];
        $kbType = $kbTypeMap[$type] ?? 'char';

        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->kbHeaders()['User-Agent'],
                'Accept' => 'application/json',
            ])->timeout(10)->get("{$this->kbUrl}/ajax_search/name", [
                'q' => $query,
                't' => $kbType,
            ]);

            if (!$response->ok()) {
                return [];
            }

            $names = $response->json();
            if (!is_array($names) || empty($names)) {
                return [];
            }

            $names = array_slice($names, 0, 15);

            // ESI universe/ids 批量转换为 ID
            $esiResponse = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->esiBaseUrl . 'universe/ids/', $names);

            if (!$esiResponse->ok()) {
                // 无法获取 ID，仅返回名称
                return array_map(function ($n) use ($type) {
                    return ['id' => 0, 'name' => $n, 'type' => $type];
                }, $names);
            }

            $esiData = $esiResponse->json();
            $results = [];
            $esiKey = $type === 'character' ? 'characters' : ($type === 'corporation' ? 'corporations' : 'alliances');

            foreach ($esiData[$esiKey] ?? [] as $item) {
                if (!empty($item['id']) && !empty($item['name'])) {
                    $results[] = [
                        'id' => (int) $item['id'],
                        'name' => $item['name'],
                        'type' => $type,
                    ];
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::debug("KB autocomplete failed ({$type}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * 使用 KB API 搜索舰船名称, 并补充本地舰船分组搜索
     */
    protected function searchKbShipAutocomplete(string $query): array
    {
        $results = [];
        $validIds = $this->getValidShipAndStructureIds();

        // 1. 从 KB API 搜索舰船名称
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept' => 'application/json',
                'Referer' => 'https://kb.ceve-market.org/ajax_search/',
            ])->timeout(8)->get('https://kb.ceve-market.org/ajax_search/ship', [
                'q' => $query,
                't' => 'shiptype',
            ]);

            if ($response->ok()) {
                $shipNames = $response->json();
                if (is_array($shipNames) && !empty($shipNames)) {
                    $ids = $this->resolveNamesToIds($shipNames);
                    foreach ($shipNames as $name) {
                        $typeId = $ids[$name] ?? null;
                        // 白名单校验：只允许舰船和建筑
                        if ($typeId && (!empty($validIds) ? isset($validIds[$typeId]) : true)) {
                            $results[] = [
                                'id' => $typeId,
                                'name' => $name,
                                'type' => 'ship',
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // KB API 失败，降级到本地搜索
        }

        // 2. 搜索舰船分组和建筑分组
        $allGroups = array_merge($this->getShipGroups(), $this->getStructureGroups());
        $queryLower = mb_strtolower($query, 'UTF-8');
        $seenGroupNames = [];
        foreach ($allGroups as $group) {
            $gName = $group['name'];
            if (isset($seenGroupNames[$gName])) continue;
            if (mb_strpos(mb_strtolower($gName, 'UTF-8'), $queryLower) !== false) {
                $exists = false;
                foreach ($results as $r) {
                    if ($r['name'] === $gName) { $exists = true; break; }
                }
                if (!$exists) {
                    $results[] = [
                        'id' => $group['group_id'],
                        'name' => $gName,
                        'type' => 'ship_group',
                    ];
                    $seenGroupNames[$gName] = true;
                }
            }
            if (count($results) >= 15) break;
        }

        // 3. 如果 KB API 没返回结果，降级到本地搜索 (使用白名单过滤)
        if (empty($results)) {
            $results = $this->searchLocalEntities($query, 'ship', $validIds);
        }

        return array_slice($results, 0, 15);
    }

    /**
     * 将名称列表转换为 ID (通过 ESI universe/ids)
     */
    protected function resolveNamesToIds(array $names): array
    {
        $result = [];
        if (empty($names)) return $result;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'EVE-ESI-Admin/1.0',
                'Accept' => 'application/json',
            ])->timeout(10)->post("{$this->esiBaseUrl}universe/ids/?datasource=serenity", $names);

            if ($response->ok()) {
                $data = $response->json();
                foreach ($data['inventory_types'] ?? [] as $item) {
                    $result[$item['name']] = $item['id'];
                }
            }
        } catch (\Exception $e) {
            // 降级: 从本地数据库查找
            $database = $this->eveData->getItemDatabase();
            $nameMap = array_flip($database);
            foreach ($names as $name) {
                if (isset($nameMap[$name])) {
                    $result[$name] = (int) $nameMap[$name];
                }
            }
        }

        return $result;
    }

    /**
     * 获取所有舰船分组列表 (从 ESI 缓存)
     */
    protected function getShipGroups(): array
    {
        return Cache::remember('eve:ship_groups', 86400, function () {
            $groups = [];

            try {
                // ESI 获取 Ship 类别 (category_id=6) 的所有分组
                $catResponse = Http::withHeaders([
                    'User-Agent' => 'EVE-ESI-Admin/1.0',
                ])->timeout(15)->get("{$this->esiBaseUrl}universe/categories/6/?datasource=serenity");

                if (!$catResponse->ok()) return $this->getStaticShipGroups();

                $catData = $catResponse->json();
                $groupIds = $catData['groups'] ?? [];

                // 批量获取分组信息
                $pool = Http::pool(function ($pool) use ($groupIds) {
                    foreach ($groupIds as $gid) {
                        $pool->as("g{$gid}")->withHeaders([
                            'User-Agent' => 'EVE-ESI-Admin/1.0',
                        ])->timeout(10)->get("{$this->esiBaseUrl}universe/groups/{$gid}/?datasource=serenity&language=zh");
                    }
                });

                foreach ($groupIds as $gid) {
                    $resp = $pool["g{$gid}"] ?? null;
                    if ($resp && $resp->ok()) {
                        $gData = $resp->json();
                        $groups[] = [
                            'group_id' => $gid,
                            'name' => $gData['name'] ?? "Group#{$gid}",
                            'type_ids' => $gData['types'] ?? [],
                        ];
                    }
                }
            } catch (\Exception $e) {
                return $this->getStaticShipGroups();
            }

            return !empty($groups) ? $groups : $this->getStaticShipGroups();
        });
    }

    /**
     * 静态舰船分组列表 (ESI 不可用时的降级)
     */
    protected function getStaticShipGroups(): array
    {
        $staticGroups = [
            25 => '护卫舰', 26 => '巡洋舰', 27 => '战列舰', 28 => '工业舰',
            29 => '太空舱', 30 => '泰坦', 31 => '穿梭机', 237 => '货舰',
            324 => '突击护卫舰', 358 => '重型突击巡洋舰', 380 => '深空运输舰',
            381 => '封锁运输舰', 419 => '战列巡洋舰', 420 => '驱逐舰',
            463 => '采矿驳船', 513 => '超级航母', 540 => '指挥舰',
            541 => '拦截舰', 543 => '采掘者', 547 => '航空母舰',
            659 => '后勤巡洋舰', 830 => '隐形轰炸舰', 831 => '电子攻击护卫舰',
            832 => '后勤护卫舰', 833 => '武装工业舰', 834 => '远征护卫舰',
            893 => '侦察舰', 894 => '重型拦截巡洋舰', 898 => '黑隐特勤舰',
            900 => '无畏舰', 906 => '战斗侦察舰', 941 => '工业指挥舰',
            963 => '战略巡洋舰', 1022 => '旗舰工业舰', 1201 => '突击驱逐舰',
            1202 => '指挥驱逐舰', 1283 => '战术驱逐舰',
            1534 => '指挥突击舰', 1538 => '先驱者战列舰', 1972 => '旗舰级战列舰',
        ];

        return array_map(function ($name, $gid) {
            return ['group_id' => $gid, 'name' => $name, 'type_ids' => []];
        }, $staticGroups, array_keys($staticGroups));
    }

    /**
     * 获取建筑分组 (ESI category 65 = Structures)
     */
    protected function getStructureGroups(): array
    {
        return Cache::remember('eve:structure_groups', 86400, function () {
            $groups = [];

            try {
                $catResponse = Http::withHeaders([
                    'User-Agent' => 'EVE-ESI-Admin/1.0',
                ])->timeout(15)->get("{$this->esiBaseUrl}universe/categories/65/?datasource=serenity");

                if (!$catResponse->ok()) return $this->getStaticStructureGroups();

                $catData = $catResponse->json();
                $groupIds = $catData['groups'] ?? [];

                $pool = Http::pool(function ($pool) use ($groupIds) {
                    foreach ($groupIds as $gid) {
                        $pool->as("g{$gid}")->withHeaders([
                            'User-Agent' => 'EVE-ESI-Admin/1.0',
                        ])->timeout(10)->get("{$this->esiBaseUrl}universe/groups/{$gid}/?datasource=serenity&language=zh");
                    }
                });

                foreach ($groupIds as $gid) {
                    $resp = $pool["g{$gid}"] ?? null;
                    if ($resp && $resp->ok()) {
                        $gData = $resp->json();
                        $groups[] = [
                            'group_id' => $gid,
                            'name' => $gData['name'] ?? "Group#{$gid}",
                            'type_ids' => $gData['types'] ?? [],
                        ];
                    }
                }
            } catch (\Exception $e) {
                return $this->getStaticStructureGroups();
            }

            return !empty($groups) ? $groups : $this->getStaticStructureGroups();
        });
    }

    /**
     * 静态建筑分组列表 (ESI 不可用时的降级)
     */
    protected function getStaticStructureGroups(): array
    {
        $staticGroups = [
            1657 => '铁壁', // Citadel
            1404 => '工程复合体', // Engineering Complex
            1406 => '精炼厂', // Refinery
            1408 => 'FLEX 建筑', // FLEX structures
            2016 => '主权建筑', // Sovereignty structures
            2017 => '基础设施枢纽', // Infrastructure Hub
        ];

        return array_map(function ($name, $gid) {
            return ['group_id' => $gid, 'name' => $name, 'type_ids' => []];
        }, $staticGroups, array_keys($staticGroups));
    }

    /**
     * 获取所有合法的舰船+建筑 type_id 集合 (白名单)
     */
    protected function getValidShipAndStructureIds(): array
    {
        return Cache::remember('eve:valid_ship_structure_ids', 86400, function () {
            $validIds = [];

            foreach ($this->getShipGroups() as $group) {
                foreach ($group['type_ids'] as $typeId) {
                    $validIds[$typeId] = true;
                }
            }

            foreach ($this->getStructureGroups() as $group) {
                foreach ($group['type_ids'] as $typeId) {
                    $validIds[$typeId] = true;
                }
            }

            return $validIds;
        });
    }

    /**
     * 判断 type_id 是否为建筑 (ESI category 65)
     */
    protected function isStructureTypeId(int $typeId): bool
    {
        $structureIds = Cache::remember('eve:structure_type_ids', 86400, function () {
            $ids = [];
            foreach ($this->getStructureGroups() as $group) {
                foreach ($group['type_ids'] as $tid) {
                    $ids[$tid] = true;
                }
            }
            return $ids;
        });
        return isset($structureIds[$typeId]);
    }

    /**
     * 本地数据搜索 (ship/system)
     */
    protected function searchLocalEntities(string $query, string $category, array $validIds = []): array
    {
        $results = $this->eveData->searchByName($query, $category, 15, $validIds);
        return array_map(function ($item) use ($category) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'type' => $category,
            ];
        }, $results);
    }

    /**
     * 通过 ESI universe/ids 搜索星系（中英文精确匹配）
     */
    protected function searchEsiSystems(string $query): array
    {
        $results = [];

        try {
            // 先用中文搜索
            $response = Http::timeout(8)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->esiBaseUrl . 'universe/ids/?datasource=serenity&language=zh', [$query]);

            if ($response->ok()) {
                foreach ($response->json()['systems'] ?? [] as $sys) {
                    $results[] = [
                        'id' => (int) $sys['id'],
                        'name' => $sys['name'],
                        'type' => 'system',
                    ];
                }
            }

            // 如果中文没结果，再尝试英文
            if (empty($results)) {
                $response = Http::timeout(8)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->esiBaseUrl . 'universe/ids/?datasource=serenity&language=en', [$query]);

                if ($response->ok()) {
                    foreach ($response->json()['systems'] ?? [] as $sys) {
                        $results[] = [
                            'id' => (int) $sys['id'],
                            'name' => $sys['name'],
                            'type' => 'system',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug("ESI system search failed: " . $e->getMessage());
        }

        return $results;
    }

    // ========================================================
    // 搜索角色 (保留旧接口兼容)
    // ========================================================

    public function searchCharacter(string $query): array
    {
        return Cache::remember("kb:search:" . md5($query), 300, function () use ($query) {
            $results = [];

            try {
                $results = $this->searchViaKbAndEsi($query);
            } catch (\Exception $e) {
                Log::warning('KB+ESI 搜索失败: ' . $e->getMessage());
            }

            if (empty($results)) {
                try {
                    $results = $this->searchLocalUsers($query);
                } catch (\Exception $e) {
                    // 忽略
                }
            }

            return $results;
        });
    }

    protected function searchViaKbAndEsi(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => $this->kbHeaders()['User-Agent'],
            'Accept' => 'application/json',
        ])->timeout(10)->get("{$this->kbUrl}/ajax_search/name", [
            'q' => $query,
            't' => 'char',
        ]);

        if (!$response->ok()) {
            return [];
        }

        $names = $response->json();
        if (!is_array($names) || empty($names)) {
            return [];
        }

        $names = array_slice($names, 0, 10);

        $results = [];
        try {
            $esiResponse = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->esiBaseUrl . 'universe/ids/', $names);

            if ($esiResponse->ok()) {
                $esiData = $esiResponse->json();
                foreach ($esiData['characters'] ?? [] as $char) {
                    if (!empty($char['id']) && !empty($char['name'])) {
                        $results[] = [
                            'pilot_id' => (int) $char['id'],
                            'name' => $char['name'],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('ESI universe/ids 失败: ' . $e->getMessage());
        }

        return $results;
    }

    protected function searchLocalUsers(string $query): array
    {
        $escapedQuery = str_replace(['%', '_'], ['\\%', '\\_'], $query);
        $users = DB::table('users')
            ->where('name', 'like', "%{$escapedQuery}%")
            ->whereNotNull('eve_character_id')
            ->select('eve_character_id', 'name')
            ->limit(20)
            ->get();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'pilot_id' => (int) $user->eve_character_id,
                'name' => $user->name,
            ];
        }
        return $results;
    }

    // ========================================================
    // 获取实体 KM 列表 (通用)
    // ========================================================

    /**
     * 通用的 beta KB 列表获取
     * @param string $entityType pilot|corporation|alliance|ship|system
     * @param int $entityId 实体 ID
     * @return array KM 基础列表
     */
    public function getEntityKills(string $entityType, int $entityId): array
    {
        $cacheKey = "kb:entity:{$entityType}:{$entityId}";

        return Cache::remember($cacheKey, 600, function () use ($entityType, $entityId) {
            try {
                $kills = $this->fetchBetaEntityKillList($entityType, $entityId);
                if (!empty($kills)) {
                    return $kills;
                }
            } catch (\Exception $e) {
                Log::debug("Beta KB entity list failed ({$entityType} {$entityId}): " . $e->getMessage());
            }

            // 降级: 仅 pilot 有旧 KB HTML 备选
            if ($entityType === 'pilot') {
                try {
                    $url = "{$this->kbUrl}/pilot/{$entityId}/";
                    $response = Http::withHeaders($this->kbHeaders())
                        ->timeout(15)
                        ->get($url);

                    if ($response->ok()) {
                        $html = $response->body();
                        if (strpos($html, '世界线已发生混乱') === false && strpos($html, '404') === false) {
                            return $this->parseKillList($html);
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug("旧 KB kill list 也失败 (pilot {$entityId}): " . $e->getMessage());
                }
            }

            return [];
        });
    }

    /**
     * 保留旧接口兼容
     */
    public function getPilotKills(int $pilotId, string $mode = 'kills'): array
    {
        return $this->getEntityKills('pilot', $pilotId);
    }

    /**
     * 从 Beta KB API 获取实体 KM 列表 (protobuf 格式)
     * 仅支持 pilot (corp/alli 使用不同 URL 路径)
     */
    protected function fetchBetaEntityKillList(string $entityType, int $entityId): array
    {
        $kills = [];
        $seen = [];

        // Beta KB API 的 URL 路径与我们的 entityType 不同
        $apiTypeMap = [
            'pilot' => 'pilot',
            'corporation' => 'corp',
            'alliance' => 'alli',
        ];
        $apiType = $apiTypeMap[$entityType] ?? null;
        if (!$apiType) {
            // ship/system 不支持 list API，需用 search API
            return [];
        }

        // 获取击杀列表 (实体为攻击者)
        try {
            $response = Http::timeout(10)
                ->get("{$this->betaKbUrl}/app/list/{$apiType}/{$entityId}/kill");

            if ($response->ok() && strlen($response->body()) > 0 && ord($response->body()[0]) !== 0x3C) {
                foreach ($this->parseBetaKillListProtobuf($response->body()) as $km) {
                    if (!isset($seen[$km['kill_id']])) {
                        $kills[] = $km;
                        $seen[$km['kill_id']] = true;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug("Beta KB kill list failed ({$entityType} {$entityId}): " . $e->getMessage());
        }

        // 获取损失列表 (实体为受害者)
        try {
            $lossResponse = Http::timeout(10)
                ->get("{$this->betaKbUrl}/app/list/{$apiType}/{$entityId}/loss");

            if ($lossResponse->ok() && strlen($lossResponse->body()) > 0 && ord($lossResponse->body()[0]) !== 0x3C) {
                foreach ($this->parseBetaKillListProtobuf($lossResponse->body()) as $km) {
                    if (!isset($seen[$km['kill_id']])) {
                        $kills[] = $km;
                        $seen[$km['kill_id']] = true;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug("Beta KB loss list failed ({$entityType} {$entityId}): " . $e->getMessage());
        }

        return $kills;
    }

    /**
     * 通过 beta KB search API 获取按角色类型过滤的 KM 列表
     *
     * 使用 /app/search/search 端点，支持 chartype 服务端过滤：
     *   lost = 受害者, win = 最后一击, atk = 参与者
     *
     * @param string $entityType pilot/corporation/alliance
     * @param int $entityId 实体 ID
     * @param string $chartype lost/win/atk
     * @return array KM 列表 (与 parseBetaKillListProtobuf 格式相同)
     */
    protected function fetchBetaSearchKills(string $entityType, int $entityId, string $chartype): array
    {
        $cacheKey = "kb:search:{$entityType}:{$entityId}:{$chartype}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        try {
            // Step 1: 获取 XSRF cookies (带缓存)
            [$reDive, $gbf] = $this->getBetaKbXsrfCookies();

            if (empty($reDive) || empty($gbf)) {
                Log::debug("fetchBetaSearchKills: 无法获取 XSRF cookies");
                return [];
            }

            // Step 2: 构造 protobuf 请求体
            // Proto: { Allis: int64[] (F1), Corps: int64[] (F2), Chars: int64[] (F3), chartype: string (F4) }
            $body = '';
            switch ($entityType) {
                case 'pilot':
                    $body = $this->pbEncodePackedInt64(3, [$entityId]);
                    break;
                case 'corporation':
                    $body = $this->pbEncodePackedInt64(2, [$entityId]);
                    break;
                case 'alliance':
                    $body = $this->pbEncodePackedInt64(1, [$entityId]);
                    break;
                default:
                    return [];
            }
            $body .= $this->pbEncodeString(4, $chartype);

            // Step 3: 调用 search API
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/alicegearaegis',
                    'Accept' => 'application/alicegearaegis',
                    'FinalFantasy-XIV' => $reDive,
                    'Cookie' => "GranblueFantasy={$gbf}; ReDive={$reDive}",
                    'Origin' => $this->betaKbUrl,
                    'Referer' => $this->betaKbUrl . '/search',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                ])
                ->send('POST', "{$this->betaKbUrl}/app/search/search", [
                    'body' => $body,
                ]);

            if ($response->ok() && strlen($response->body()) > 0) {
                $kills = $this->parseBetaKillListProtobuf($response->body());
                Log::debug("fetchBetaSearchKills: {$entityType}:{$entityId} chartype={$chartype} 返回 " . count($kills) . " 条");
                Cache::put($cacheKey, $kills, 300);
                return $kills;
            }

            Log::debug("fetchBetaSearchKills: HTTP {$response->status()} (body " . strlen($response->body()) . "b)");
        } catch (\Exception $e) {
            Log::debug("fetchBetaSearchKills failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * 高级搜索: 支持所有 Beta KB Search API 参数
     *
     * Proto schema:
     *   F1: Allis []int64, F2: Corps []int64, F3: Chars []int64,
     *   F4: chartype string, F5: types []int32, F6: groups []int32,
     *   F7: systems []int32, F8: regions []int32,
     *   F9: startdate Timestamp, F10: enddate Timestamp
     *
     * 注意: API 不支持同时传 entity(F1-F3) + types(F5)/systems(F7)，会返回空
     *       但 entity + chartype + daterange 可以组合
     *
     * @param array $params 搜索参数:
     *   entity_type: pilot/corporation/alliance (可选)
     *   entity_id: int (可选)
     *   chartype: lost/win/atk (可选)
     *   types: int[] 舰船/建筑 type_id (可选)
     *   systems: int[] 星系 ID (可选)
     *   start_date: 'YYYY-MM-DD' (可选)
     *   end_date: 'YYYY-MM-DD' (可选)
     */
    protected function fetchBetaSearchKillsAdvanced(array $params): array
    {
        $cacheKey = "kb:advsearch:" . md5(json_encode($params));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        try {
            // Step 1: 获取 XSRF cookies (带缓存)
            [$reDive, $gbf] = $this->getBetaKbXsrfCookies();

            if (empty($reDive) || empty($gbf)) {
                Log::debug("fetchBetaSearchKillsAdvanced: 无法获取 XSRF cookies");
                return [];
            }

            // Step 2: 构造 protobuf 请求体
            $body = '';

            // Entity (F1-F3)
            $entityType = $params['entity_type'] ?? null;
            $entityId = $params['entity_id'] ?? null;
            if ($entityType && $entityId) {
                switch ($entityType) {
                    case 'pilot':
                        $body .= $this->pbEncodePackedInt64(3, [$entityId]);
                        break;
                    case 'corporation':
                        $body .= $this->pbEncodePackedInt64(2, [$entityId]);
                        break;
                    case 'alliance':
                        $body .= $this->pbEncodePackedInt64(1, [$entityId]);
                        break;
                }
            }

            // Chartype (F4)
            $chartype = $params['chartype'] ?? '';
            if (!empty($chartype)) {
                $body .= $this->pbEncodeString(4, $chartype);
            }

            // Types - ship/structure type IDs (F5)
            $types = $params['types'] ?? [];
            if (!empty($types)) {
                $body .= $this->pbEncodePackedInt64(5, $types);
            }

            // Systems - system IDs (F7)
            $systems = $params['systems'] ?? [];
            if (!empty($systems)) {
                $body .= $this->pbEncodePackedInt64(7, $systems);
            }

            // Start date (F9) - Protobuf Timestamp
            $startDate = $params['start_date'] ?? null;
            if ($startDate) {
                $ts = strtotime($startDate);
                if ($ts) {
                    $body .= $this->pbEncodeTimestamp(9, $ts);
                }
            }

            // End date (F10) - Protobuf Timestamp
            $endDate = $params['end_date'] ?? null;
            if ($endDate) {
                // 如果已包含时间部分（datetime-local 格式），直接使用；否则设为当天 23:59:59
                if (strpos($endDate, 'T') !== false || strpos($endDate, ':') !== false) {
                    $ts = strtotime($endDate);
                } else {
                    $ts = strtotime($endDate . ' 23:59:59');
                }
                if ($ts) {
                    $body .= $this->pbEncodeTimestamp(10, $ts);
                }
            }

            if (empty($body)) {
                Log::debug("fetchBetaSearchKillsAdvanced: 无搜索参数");
                return [];
            }

            // Step 3: 调用 search API
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/alicegearaegis',
                    'Accept' => 'application/alicegearaegis',
                    'FinalFantasy-XIV' => $reDive,
                    'Cookie' => "GranblueFantasy={$gbf}; ReDive={$reDive}",
                    'Origin' => $this->betaKbUrl,
                    'Referer' => $this->betaKbUrl . '/search',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                ])
                ->send('POST', "{$this->betaKbUrl}/app/search/search", [
                    'body' => $body,
                ]);

            if ($response->ok() && strlen($response->body()) > 0) {
                $kills = $this->parseBetaKillListProtobuf($response->body());
                Log::debug("fetchBetaSearchKillsAdvanced: 返回 " . count($kills) . " 条 (params: " . json_encode(array_diff_key($params, ['entity_id' => 1])) . ")");
                // 有结果缓存 5 分钟; 空结果只缓存 30 秒 (避免 API 瞬时故障导致长时间无结果)
                Cache::put($cacheKey, $kills, !empty($kills) ? 300 : 30);
                return $kills;
            }

            Log::debug("fetchBetaSearchKillsAdvanced: HTTP {$response->status()} (body " . strlen($response->body()) . "b)");
        } catch (\Exception $e) {
            Log::debug("fetchBetaSearchKillsAdvanced failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * 解析 beta KB protobuf KM 列表
     */
    protected function parseBetaKillListProtobuf(string $body): array
    {
        $entries = $this->pbParseMessage($body);
        $kills = [];

        foreach ($entries as $entry) {
            if ($entry['field'] !== 1 || $entry['wire'] !== 2) continue;

            $fields = $this->pbParseMessage($entry['value']);
            $killId = $this->pbGetVarint($fields, 1);
            if (!$killId || $killId <= 0) continue;

            // 受害者 (F2)
            $victimName = null;
            $victimId = null;
            foreach ($fields as $f) {
                if ($f['field'] === 2 && $f['wire'] === 2) {
                    $victimFields = $this->pbParseMessage($f['value']);
                    $victimId = $this->pbGetVarint($victimFields, 1);
                    $victimName = $this->pbGetString($victimFields, 2);
                    break;
                }
            }

            // 舰船 (F8)
            $shipName = null;
            $shipTypeId = null;
            foreach ($fields as $f) {
                if ($f['field'] === 8 && $f['wire'] === 2) {
                    $shipFields = $this->pbParseMessage($f['value']);
                    $shipTypeId = $this->pbGetVarint($shipFields, 1);
                    $shipName = $this->pbGetString($shipFields, 2);
                    break;
                }
            }

            // 星系 (F10)
            $systemName = null;
            $systemId = null;
            foreach ($fields as $f) {
                if ($f['field'] === 10 && $f['wire'] === 2) {
                    $sysFields = $this->pbParseMessage($f['value']);
                    $systemId = $this->pbGetVarint($sysFields, 1);
                    $systemName = $this->pbGetString($sysFields, 2);
                    break;
                }
            }

            // 时间 (F9)
            $killTime = null;
            $killTimestamp = null;
            foreach ($fields as $f) {
                if ($f['field'] === 9 && $f['wire'] === 2) {
                    $ts = $this->pbGetVarint($this->pbParseMessage($f['value']), 1);
                    if ($ts && $ts > 1000000000) {
                        $killTimestamp = $ts;
                        // 转为北京时间
                        $killTime = gmdate('Y-m-d H:i:s', $ts + 8 * 3600);
                    }
                    break;
                }
            }

            // ISK 价值 (F13)
            $totalValue = $this->pbGetDouble($fields, 13);

            // ESI hash (F16)
            $esiHash = null;
            foreach ($fields as $f) {
                if ($f['field'] === 16 && $f['wire'] === 2) {
                    $hashStr = $this->pbGetString($this->pbParseMessage($f['value']), 1);
                    if ($hashStr && preg_match('/^[a-f0-9]{40}$/', $hashStr)) {
                        $esiHash = $hashStr;
                        Cache::put("kb:esi_hash:{$killId}", $esiHash, 86400);
                    }
                    break;
                }
            }

            $kills[] = [
                'kill_id' => $killId,
                'victim_id' => $victimId,
                'victim_name' => $victimName,
                'ship_type_id' => $shipTypeId,
                'ship_name' => $shipName,
                'system_id' => $systemId,
                'system_name' => $systemName,
                'kill_time' => $killTime,
                'kill_timestamp' => $killTimestamp,
                'total_value' => $totalValue ? round($totalValue, 2) : null,
                'esi_hash' => $esiHash,
            ];
        }

        return $kills;
    }

    /**
     * 解析旧 KB HTML KM 列表
     */
    protected function parseKillList(string $html): array
    {
        $kills = [];
        $seen = [];

        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $html, $rows)) {
            foreach ($rows[1] as $row) {
                if (!preg_match('/href="\/kill\/(\d+)\/"/', $row, $killMatch)) {
                    continue;
                }
                $killId = (int) $killMatch[1];
                if (isset($seen[$killId]) || $killId <= 0) continue;
                $seen[$killId] = true;

                $kill = [
                    'kill_id' => $killId,
                    'victim_name' => null,
                    'ship_name' => null,
                    'system_name' => null,
                    'kill_time' => null,
                ];

                if (preg_match('/href="\/invtype\/\d+\/"[^>]*>([^<]+)/i', $row, $m)) {
                    $kill['ship_name'] = trim(strip_tags($m[1]));
                }

                if (preg_match_all('/href="\/pilot\/\d+\/"[^>]*>([^<]+)/i', $row, $m)) {
                    foreach ($m[1] as $name) {
                        $name = trim(strip_tags($name));
                        if (!empty($name) && $name !== '未知') {
                            $kill['victim_name'] = $name;
                            break;
                        }
                    }
                }

                if (preg_match('/href="\/system\/\d+\/"[^>]*>([^<]+)/i', $row, $m)) {
                    $kill['system_name'] = trim(strip_tags($m[1]));
                }

                if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?)/', $row, $m)) {
                    $kill['kill_time'] = $m[1];
                }

                $kills[] = $kill;
            }
        }

        if (empty($kills)) {
            if (preg_match_all('/href="\/kill\/(\d+)\/"/', $html, $matches)) {
                foreach ($matches[1] as $killId) {
                    $killId = (int) $killId;
                    if (isset($seen[$killId]) || $killId <= 0) continue;
                    $seen[$killId] = true;
                    $kills[] = [
                        'kill_id' => $killId,
                        'victim_name' => null,
                        'ship_name' => null,
                        'system_name' => null,
                        'kill_time' => null,
                    ];
                }
            }
        }

        return $kills;
    }

    // ========================================================
    // 高级搜索 (带过滤 + 富化)
    // ========================================================

    /**
     * 高级搜索
     *
     * 搜索策略:
     * 1) entity_type=ship → Search API types[] (支持建筑)
     * 2) entity_type=system → Search API systems[]
     * 3) entity_type=pilot/corporation/alliance:
     *    a) 正向: Search API entity+chartype+daterange → 客户端过滤 ship/system
     *    b) 反向补充: 当有 ship/system 过滤且正向结果不足时,
     *       从 ship/system 方向搜索并验证 entity involvement, 合并结果
     *    c) Search API 全部失败 → 回退 list API + 客户端过滤
     */
    public function advancedSearch(array $params): array
    {
        $entityType = $params['entity_type'] ?? null;
        $entityId = $params['entity_id'] ?? null;
        $involvement = $params['involvement'] ?? null;
        $shipId = $params['ship_id'] ?? null;
        $systemId = $params['system_id'] ?? null;
        $timeStart = $params['time_start'] ?? null;
        $timeEnd = $params['time_end'] ?? null;

        if (!$entityType || !$entityId) {
            // 仅时间搜索: 直接用 Search API 的日期范围
            if ($timeStart || $timeEnd) {
                $searchParams = [];
                if ($shipId) $searchParams['types'] = [(int) $shipId];
                if ($systemId) $searchParams['systems'] = [(int) $systemId];
                if ($timeStart) $searchParams['start_date'] = $timeStart;
                if ($timeEnd) $searchParams['end_date'] = $timeEnd;

                if (empty($searchParams)) {
                    throw new \Exception('缺少搜索条件');
                }

                $kills = $this->fetchBetaSearchKillsAdvanced($searchParams);
                if (!empty($kills)) {
                    $kills = array_slice($kills, 0, 50);
                    [$enriched, $detailsMap] = $this->enrichKillList($kills);
                    return $enriched;
                }
                return [];
            }
            throw new \Exception('缺少搜索条件');
        }

        $chartypeMap = ['victim' => 'lost', 'finalblow' => 'win', 'attacker' => 'atk'];

        // 判断是否为建筑搜索 (建筑忽略 involvement)
        $isStructure = false;
        if ($entityType === 'ship') {
            $isStructure = $this->isStructureTypeId((int) $entityId);
        } elseif ($shipId) {
            $isStructure = $this->isStructureTypeId((int) $shipId);
        }

        // ====== 策略 1: 舰船/建筑搜索 (Search API types[]) ======
        if ($entityType === 'ship') {
            Log::debug("advancedSearch: 舰船/建筑搜索, type_id={$entityId}, isStructure=" . ($isStructure ? 'Y' : 'N'));

            $kills = $this->fetchBetaSearchKillsAdvanced([
                'types' => [(int) $entityId],
            ]);

            if (!empty($kills)) {
                // 客户端过滤 system + time (API 不支持与 types 组合)
                $kills = $this->filterKills($kills, [
                    'system_id' => $systemId,
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                ]);
                $kills = array_slice($kills, 0, 50);
                [$enriched, $detailsMap] = $this->enrichKillList($kills);
                Log::debug("advancedSearch: 舰船搜索富化后 " . count($enriched) . " 条");
                return $enriched;
            }

            return [];
        }

        // ====== 策略 2: 星系搜索 (Search API systems[]) ======
        if ($entityType === 'system') {
            Log::debug("advancedSearch: 星系搜索, system_id={$entityId}");

            $kills = $this->fetchBetaSearchKillsAdvanced([
                'systems' => [(int) $entityId],
            ]);

            if (!empty($kills)) {
                // 客户端过滤 ship + time
                $kills = $this->filterKills($kills, [
                    'ship_id' => $shipId,
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                ]);
                $kills = array_slice($kills, 0, 50);
                [$enriched, $detailsMap] = $this->enrichKillList($kills);
                Log::debug("advancedSearch: 星系搜索富化后 " . count($enriched) . " 条");
                return $enriched;
            }

            return [];
        }

        // ====== 策略 3: 角色/军团/联盟搜索 ======
        if (!in_array($entityType, ['pilot', 'corporation', 'alliance'])) {
            throw new \Exception('不支持的实体类型: ' . $entityType);
        }

        // Search API 支持 entity + chartype + types + systems + daterange 全部组合
        $chartype = '';
        if ($involvement && !$isStructure && isset($chartypeMap[$involvement])) {
            $chartype = $chartypeMap[$involvement];
        }

        $searchParams = [
            'entity_type' => $entityType,
            'entity_id' => (int) $entityId,
        ];
        if (!empty($chartype)) {
            $searchParams['chartype'] = $chartype;
        }
        if ($shipId) {
            $searchParams['types'] = [(int) $shipId];
        }
        if ($systemId) {
            $searchParams['systems'] = [(int) $systemId];
        }
        if ($timeStart) $searchParams['start_date'] = $timeStart;
        if ($timeEnd) $searchParams['end_date'] = $timeEnd;

        $kills = $this->fetchBetaSearchKillsAdvanced($searchParams);

        Log::debug("advancedSearch: Search API 返回 " . count($kills) . " 条 (entity={$entityType}, id={$entityId}, chartype={$chartype}, ship={$shipId}, system={$systemId})");

        if (!empty($kills)) {
            $kills = array_slice($kills, 0, 50);
            [$enriched, $detailsMap] = $this->enrichKillList($kills);
            Log::debug("advancedSearch: 富化后 " . count($enriched) . " 条");
            return $enriched;
        }

        // Search API 无结果 → 回退到 list API
        Log::debug("advancedSearch: Search API 无结果, 回退到 list API");

        $kills = $this->getEntityKills($entityType, (int) $entityId);

        Log::debug("advancedSearch: list API 获取到 " . count($kills) . " 条 KM");

        if (empty($kills)) {
            return [];
        }

        // 客户端过滤 (ship/system/time 全部在客户端)
        $kills = $this->filterKills($kills, $params);

        // 基于 protobuf 数据的预过滤
        if ($involvement && !$isStructure && in_array($entityType, ['pilot', 'corporation', 'alliance'])) {
            $kills = $this->preFilterByInvolvement($kills, $entityType, (int) $entityId, $involvement);
            Log::debug("advancedSearch: 预过滤后 " . count($kills) . " 条 KM");
        }

        $kills = array_slice($kills, 0, 50);
        [$enriched, $detailsMap] = $this->enrichKillList($kills);

        // involvement 过滤 (list API 回退路径才需要)
        if ($involvement && !$isStructure && in_array($entityType, ['pilot', 'corporation', 'alliance'])) {
            if (!($involvement === 'victim' && $entityType === 'pilot')) {
                $beforeFilter = count($enriched);
                $enriched = $this->filterByInvolvement($enriched, $entityType, (int) $entityId, $involvement, $detailsMap);
                Log::debug("advancedSearch: ESI involvement 过滤 {$beforeFilter} -> " . count($enriched) . " 条");
            }
        }

        return $enriched;
    }

    /**
     * 反向搜索: 从 ship/system 方向搜索, 验证 entity involvement 后返回富化结果
     *
     * 当正向搜索 (entity → filter ship/system) 结果不足时调用。
     * 从 ship/system 的 50 条最新 KM 中找出涉及目标 entity 的记录。
     *
     * @param int|null $shipId 舰船 type_id
     * @param int|null $systemId 星系 ID
     * @param string|null $timeStart 开始日期
     * @param string|null $timeEnd 结束日期
     * @param string $entityType pilot/corporation/alliance
     * @param int $entityId 实体 ID
     * @param string|null $involvement victim/finalblow/attacker
     * @param bool $isStructure 是否为建筑搜索
     * @param array $excludeKillIds 排除已有的 kill_id 列表
     * @return array 富化后的 KM 列表 (已验证 entity involvement)
     */
    protected function reverseSearchByShipSystem(
        ?int $shipId, ?int $systemId,
        ?string $timeStart, ?string $timeEnd,
        string $entityType, int $entityId,
        ?string $involvement, bool $isStructure,
        array $excludeKillIds = []
    ): array {
        // 构造反向搜索参数 (优先用 ship, 因为更具体)
        $reverseParams = [];
        if ($shipId) {
            $reverseParams['types'] = [(int) $shipId];
        } elseif ($systemId) {
            $reverseParams['systems'] = [(int) $systemId];
        }
        if ($timeStart) $reverseParams['start_date'] = $timeStart;
        if ($timeEnd) $reverseParams['end_date'] = $timeEnd;

        if (empty($reverseParams)) return [];

        $reverseRaw = $this->fetchBetaSearchKillsAdvanced($reverseParams);
        if (empty($reverseRaw)) return [];

        Log::debug("reverseSearch: API 返回 " . count($reverseRaw) . " 条 (params=" . json_encode(array_diff_key($reverseParams, ['start_date' => 1, 'end_date' => 1])) . ")");

        // 排除正向搜索已有的 kill_id
        $excludeMap = array_flip($excludeKillIds);
        $reverseRaw = array_values(array_filter($reverseRaw, function ($k) use ($excludeMap) {
            return !isset($excludeMap[$k['kill_id']]);
        }));

        // 如果用 ship 搜索, 还需要客户端过滤 system; 反之亦然
        if ($shipId && $systemId) {
            $reverseRaw = array_values(array_filter($reverseRaw, function ($k) use ($systemId) {
                return empty($k['system_id']) || (int) $k['system_id'] === (int) $systemId;
            }));
        }

        if (empty($reverseRaw)) return [];

        // 快速路径: victim + pilot 可以用 protobuf victim_id 直接过滤 (无需 ESI)
        if ($involvement === 'victim' && $entityType === 'pilot') {
            $matched = array_values(array_filter($reverseRaw, function ($k) use ($entityId) {
                return !empty($k['victim_id']) && (int) $k['victim_id'] === $entityId;
            }));
            Log::debug("reverseSearch: victim+pilot protobuf 快速过滤, " . count($matched) . " 条匹配");
            if (empty($matched)) return [];
            $matched = array_slice($matched, 0, 50);
            [$enriched, $_] = $this->enrichKillList($matched);
            return $enriched;
        }

        // 通用路径: 需要 ESI 富化后验证 entity involvement
        $reverseRaw = array_slice($reverseRaw, 0, 50);
        [$enrichedReverse, $detailsMapR] = $this->enrichKillList($reverseRaw);

        if (empty($enrichedReverse)) return [];

        // 按 involvement 过滤 entity 参与
        if ($involvement && !$isStructure) {
            $result = $this->filterByInvolvement($enrichedReverse, $entityType, $entityId, $involvement, $detailsMapR);
        } else {
            // 无 involvement 过滤 → 检查 entity 以任意角色参与
            $result = $this->filterByAnyInvolvement($enrichedReverse, $entityType, $entityId, $detailsMapR);
        }

        Log::debug("reverseSearch: involvement 过滤后 " . count($result) . " 条");
        return $result;
    }

    /**
     * 检查 entity 是否以任意角色 (受害者/攻击者) 参与 KM
     */
    protected function filterByAnyInvolvement(array $enrichedKills, string $entityType, int $entityId, array $detailsMap = []): array
    {
        return array_values(array_filter($enrichedKills, function ($kill) use ($entityType, $entityId, $detailsMap) {
            $killId = $kill['kill_id'];
            $detail = $detailsMap[$killId] ?? Cache::get("kb:kill:{$killId}");
            if (!$detail || empty($detail['victim'])) return false;

            // 检查受害者
            if ($this->entityMatchesParticipant($detail['victim'], $entityType, $entityId)) {
                return true;
            }

            // 检查攻击者
            foreach ($detail['attackers'] ?? [] as $atk) {
                if ($this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * 基于 protobuf 列表数据的预过滤 (在 ESI 富化之前)
     * 用于在 50 条限制前缩小结果集，避免目标 KM 被截断
     *
     * pilot+victim: protobuf 包含 victim_id，可直接匹配
     * 其他情况: 需要 ESI 详情，无法预过滤
     */
    protected function preFilterByInvolvement(array $kills, string $entityType, int $entityId, string $involvement): array
    {
        if ($involvement === 'victim' && $entityType === 'pilot') {
            // protobuf 数据中 victim_id 可直接判断该角色是否为受害者
            $filtered = array_values(array_filter($kills, function ($kill) use ($entityId) {
                return !empty($kill['victim_id']) && (int) $kill['victim_id'] === $entityId;
            }));
            Log::debug("preFilterByInvolvement: pilot+victim 预过滤, 输入 " . count($kills) . " 条, 输出 " . count($filtered) . " 条 (entityId={$entityId})");
            return $filtered;
        }

        // finalblow/attacker 需要 ESI 攻击者详情，无法从 protobuf 预过滤
        // corporation/alliance 的 victim 需要查询受害者的军团/联盟归属
        return $kills;
    }

    /**
     * 根据参与类型过滤富化后的 KM 列表
     *
     * 受害者: victim 中的 id 匹配输入实体
     * 最后一击: attackers 中 final_blow=true 的 id 匹配输入实体
     * 参与者: attackers 中 final_blow=false 的 id 匹配输入实体
     */
    protected function filterByInvolvement(array $enrichedKills, string $entityType, int $entityId, string $involvement, array $detailsMap = []): array
    {
        $matchCount = 0;
        $noDetailCount = 0;

        $result = array_values(array_filter($enrichedKills, function ($kill) use ($entityType, $entityId, $involvement, $detailsMap, &$matchCount, &$noDetailCount) {
            $killId = $kill['kill_id'];

            // 优先从传入的 detailsMap 获取，其次从缓存，最后尝试直接获取
            $detail = $detailsMap[$killId] ?? Cache::get("kb:kill:{$killId}");
            if (!$detail) {
                try {
                    $detail = $this->getKillDetails($killId);
                } catch (\Exception $e) {
                    $noDetailCount++;
                    return false;
                }
            }

            if (!$detail || empty($detail['victim'])) {
                $noDetailCount++;
                return false;
            }

            $matched = false;
            switch ($involvement) {
                case 'victim':
                    $matched = $this->entityMatchesParticipant($detail['victim'], $entityType, $entityId);
                    break;

                case 'finalblow':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (!empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;

                case 'attacker':
                    foreach ($detail['attackers'] ?? [] as $atk) {
                        if (empty($atk['final_blow']) && $this->entityMatchesParticipant($atk, $entityType, $entityId)) {
                            $matched = true;
                            break;
                        }
                    }
                    break;

                default:
                    $matched = true;
            }

            if ($matched) $matchCount++;
            return $matched;
        }));

        Log::debug("filterByInvolvement: involvement={$involvement}, entity={$entityType}:{$entityId}, 输入 " . count($enrichedKills) . " 条, 匹配 {$matchCount} 条, 无详情 {$noDetailCount} 条");

        return $result;
    }

    /**
     * 判断某个参与者是否匹配搜索的实体
     * pilot: character_id 匹配
     * corporation: corporation_id 匹配
     * alliance: alliance_id 匹配
     */
    protected function entityMatchesParticipant(array $participant, string $entityType, int $entityId): bool
    {
        switch ($entityType) {
            case 'pilot':
                return !empty($participant['character_id']) && (int) $participant['character_id'] === $entityId;
            case 'corporation':
                return !empty($participant['corporation_id']) && (int) $participant['corporation_id'] === $entityId;
            case 'alliance':
                return !empty($participant['alliance_id']) && (int) $participant['alliance_id'] === $entityId;
            default:
                return false;
        }
    }

    /**
     * 按条件过滤 KM 列表
     */
    protected function filterKills(array $kills, array $params): array
    {
        $shipId = $params['ship_id'] ?? null;
        $systemId = $params['system_id'] ?? null;
        $timeStart = $params['time_start'] ?? null;
        $timeEnd = $params['time_end'] ?? null;

        return array_values(array_filter($kills, function ($kill) use ($shipId, $systemId, $timeStart, $timeEnd) {
            // 按舰船过滤
            if ($shipId && !empty($kill['ship_type_id']) && (int) $kill['ship_type_id'] !== (int) $shipId) {
                return false;
            }

            // 按星系过滤
            if ($systemId && !empty($kill['system_id']) && (int) $kill['system_id'] !== (int) $systemId) {
                return false;
            }

            // 按时间过滤
            if ($timeStart && !empty($kill['kill_timestamp'])) {
                $startTs = strtotime($timeStart);
                if ($startTs && $kill['kill_timestamp'] < $startTs) {
                    return false;
                }
            }
            if ($timeEnd && !empty($kill['kill_timestamp'])) {
                if (strpos($timeEnd, 'T') !== false || strpos($timeEnd, ':') !== false) {
                    $endTs = strtotime($timeEnd);
                } else {
                    $endTs = strtotime($timeEnd . ' 23:59:59');
                }
                if ($endTs && $kill['kill_timestamp'] > $endTs) {
                    return false;
                }
            }

            return true;
        }));
    }

    // ========================================================
    // KM 列表富化 (批量 ESI 获取详情)
    // ========================================================

    /**
     * 批量富化 KM 列表数据
     * 对每条 KM 获取 ESI 详情，提取受害者军团/联盟、最后一击信息、安等、星域
     * @return array [enrichedList, detailsMap] detailsMap 以 kill_id 为键
     */
    public function enrichKillList(array $kills): array
    {
        if (empty($kills)) {
            return [[], []];
        }

        // 分离已缓存和需要请求的
        $enriched = [];
        $toFetch = [];
        $needHash = []; // 无 hash 的 kill，需要尝试获取
        $detailsMap = []; // kill_id => full detail

        foreach ($kills as $idx => $kill) {
            $killId = $kill['kill_id'];
            $cached = Cache::get("kb:kill:{$killId}");

            if ($cached) {
                // 从缓存的详情中提取列表需要的字段
                $enriched[$idx] = $this->extractListDataFromDetail($kill, $cached);
                $detailsMap[$killId] = $cached;
            } elseif (!empty($kill['esi_hash'])) {
                $toFetch[$idx] = $kill;
            } else {
                // 无 hash，尝试从 hash 缓存获取
                $cachedHash = Cache::get("kb:esi_hash:{$killId}");
                if ($cachedHash) {
                    $kill['esi_hash'] = $cachedHash;
                    $toFetch[$idx] = $kill;
                } else {
                    $needHash[$idx] = $kill;
                }
            }
        }

        // 对无 hash 的 kill 尝试从 KB 获取 hash（限制数量避免过慢）
        foreach (array_slice($needHash, 0, 20, true) as $idx => $kill) {
            $hash = $this->extractEsiHash($kill['kill_id']);
            if ($hash) {
                $kill['esi_hash'] = $hash;
                $toFetch[$idx] = $kill;
                unset($needHash[$idx]);
            }
        }

        // 剩余无法获取 hash 的用基础数据
        foreach ($needHash as $idx => $kill) {
            $enriched[$idx] = $this->buildBasicListItem($kill);
        }

        // 批量获取 ESI 详情
        if (!empty($toFetch)) {
            $batchDetails = $this->batchFetchEsiKills($toFetch);

            foreach ($batchDetails as $idx => $detail) {
                if ($detail) {
                    $enriched[$idx] = $this->extractListDataFromDetail($toFetch[$idx], $detail);
                    $detailsMap[$toFetch[$idx]['kill_id']] = $detail;
                } else {
                    $enriched[$idx] = $this->buildBasicListItem($toFetch[$idx]);
                }
            }

            // 处理批量获取中遗漏的 kills (ESI 请求失败等)
            foreach ($toFetch as $idx => $kill) {
                if (!isset($enriched[$idx])) {
                    $enriched[$idx] = $this->buildBasicListItem($kill);
                }
            }
        }

        // 按原始顺序排列
        ksort($enriched);
        return [array_values($enriched), $detailsMap];
    }

    /**
     * 批量从 ESI 获取 KM 详情
     */
    protected function batchFetchEsiKills(array $kills): array
    {
        $results = [];
        $chunks = array_chunk($kills, 20, true);

        foreach ($chunks as $chunk) {
            $responses = Http::pool(function ($pool) use ($chunk) {
                foreach ($chunk as $idx => $kill) {
                    $url = $this->esiBaseUrl . "killmails/{$kill['kill_id']}/{$kill['esi_hash']}/";
                    $pool->as((string) $idx)->timeout(15)->get($url, [
                        'datasource' => $this->datasource,
                    ]);
                }
            });

            // 收集所有需要解析的 ID
            $allIds = [];
            $validResponses = [];

            foreach ($responses as $idx => $response) {
                if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                    $esiData = $response->json();
                    if (!empty($esiData) && isset($esiData['victim'])) {
                        $validResponses[(int) $idx] = $esiData;
                        $allIds = array_merge($allIds, $this->collectIds($esiData));
                    }
                }
            }

            // 批量名称解析
            $names = $this->resolveNames(array_values(array_unique(array_filter($allIds))));

            // 组装详情并缓存
            foreach ($validResponses as $idx => $esiData) {
                $killId = $kills[$idx]['kill_id'] ?? 0;
                if ($killId > 0) {
                    $detail = $this->buildKillDetailResponse($esiData, $names, $killId);
                    Cache::put("kb:kill:{$killId}", $detail, 3600);
                    $results[$idx] = $detail;
                }
            }
        }

        return $results;
    }

    /**
     * 从已有的详情缓存中提取列表需要的字段
     */
    protected function extractListDataFromDetail(array $kill, array $detail): array
    {
        $victim = $detail['victim'] ?? [];
        $finalBlow = null;
        foreach ($detail['attackers'] ?? [] as $atk) {
            if (!empty($atk['final_blow'])) {
                $finalBlow = $atk;
                break;
            }
        }

        return [
            'kill_id' => $kill['kill_id'],
            'ship_type_id' => $victim['ship_type_id'] ?? ($kill['ship_type_id'] ?? null),
            'ship_name' => $victim['ship_name'] ?? ($kill['ship_name'] ?? null),
            'victim_id' => $victim['character_id'] ?? ($kill['victim_id'] ?? null),
            'victim_name' => $victim['character_name'] ?? ($kill['victim_name'] ?? null),
            'victim_corporation_id' => $victim['corporation_id'] ?? null,
            'victim_corp' => $victim['corporation_name'] ?? null,
            'victim_alliance' => $victim['alliance_name'] ?? null,
            'final_blow_name' => $finalBlow['character_name'] ?? null,
            'final_blow_corp' => $finalBlow['corporation_name'] ?? null,
            'final_blow_alliance' => $finalBlow['alliance_name'] ?? null,
            'final_blow_ship' => $finalBlow['ship_name'] ?? null,
            'system_id' => $detail['solar_system_id'] ?? ($kill['system_id'] ?? null),
            'system_name' => $detail['solar_system_name'] ?? ($kill['system_name'] ?? null),
            'system_sec' => $detail['system_sec'] ?? null,
            'region_name' => $detail['region_name'] ?? null,
            'kill_time' => $detail['kill_time'] ?? ($kill['kill_time'] ?? null),
            'total_value' => $kill['total_value'] ?? null,
            'esi_hash' => $kill['esi_hash'] ?? null,
            'attacker_count' => $detail['attacker_count'] ?? null,
        ];
    }

    /**
     * 构建基础列表项 (无 ESI 详情时)
     */
    protected function buildBasicListItem(array $kill): array
    {
        return [
            'kill_id' => $kill['kill_id'],
            'ship_type_id' => $kill['ship_type_id'] ?? null,
            'ship_name' => $kill['ship_name'] ?? null,
            'victim_id' => $kill['victim_id'] ?? null,
            'victim_name' => $kill['victim_name'] ?? null,
            'victim_corporation_id' => null,
            'victim_corp' => null,
            'victim_alliance' => null,
            'final_blow_name' => null,
            'final_blow_corp' => null,
            'final_blow_alliance' => null,
            'final_blow_ship' => null,
            'system_id' => $kill['system_id'] ?? null,
            'system_name' => $kill['system_name'] ?? null,
            'system_sec' => null,
            'region_name' => null,
            'kill_time' => $kill['kill_time'] ?? null,
            'total_value' => $kill['total_value'] ?? null,
            'esi_hash' => $kill['esi_hash'] ?? null,
            'attacker_count' => null,
        ];
    }

    // ========================================================
    // 星系信息查询 (安等 + 星域)
    // ========================================================

    /**
     * 获取星系安等和星域信息
     * @return array {security_status, region_name}
     */
    public function getSystemInfo(int $systemId): array
    {
        $cacheKey = "eve_system_info:{$systemId}";

        return Cache::remember($cacheKey, 86400, function () use ($systemId) {
            $info = ['security_status' => null, 'region_name' => null];

            try {
                // 查询星系
                $sysResponse = Http::timeout(10)->get(
                    $this->esiBaseUrl . "universe/systems/{$systemId}/",
                    ['datasource' => $this->datasource, 'language' => 'zh']
                );

                if (!$sysResponse->ok()) return $info;

                $sysData = $sysResponse->json();
                $info['security_status'] = round($sysData['security_status'] ?? 0, 1);

                $constellationId = $sysData['constellation_id'] ?? null;
                if (!$constellationId) return $info;

                // 查询星座 → 星域
                $constResponse = Http::timeout(10)->get(
                    $this->esiBaseUrl . "universe/constellations/{$constellationId}/",
                    ['datasource' => $this->datasource]
                );

                if (!$constResponse->ok()) return $info;

                $constData = $constResponse->json();
                $regionId = $constData['region_id'] ?? null;
                if (!$regionId) return $info;

                // 查询星域名称
                $regionResponse = Http::timeout(10)->get(
                    $this->esiBaseUrl . "universe/regions/{$regionId}/",
                    ['datasource' => $this->datasource, 'language' => 'zh']
                );

                if ($regionResponse->ok()) {
                    $regionData = $regionResponse->json();
                    $info['region_name'] = $regionData['name'] ?? null;
                }
            } catch (\Exception $e) {
                Log::debug("获取星系信息失败 ({$systemId}): " . $e->getMessage());
            }

            return $info;
        });
    }

    // ========================================================
    // 市场价格查询 (用于物品估价)
    // ========================================================

    /**
     * 获取 ESI 市场平均价格表 (全量缓存)
     * @return array {type_id => average_price}
     */
    protected function getMarketPrices(): array
    {
        return Cache::remember('esi_market_prices', 86400, function () {
            try {
                $response = Http::timeout(30)->get(
                    $this->esiBaseUrl . 'markets/prices/',
                    ['datasource' => $this->datasource]
                );

                if (!$response->ok()) return [];

                $prices = [];
                foreach ($response->json() as $item) {
                    $typeId = $item['type_id'] ?? 0;
                    // 优先 average_price，其次 adjusted_price
                    $price = $item['average_price'] ?? $item['adjusted_price'] ?? 0;
                    if ($typeId > 0 && $price > 0) {
                        $prices[$typeId] = round($price, 2);
                    }
                }
                return $prices;
            } catch (\Exception $e) {
                Log::debug('获取市场价格失败: ' . $e->getMessage());
                return [];
            }
        });
    }

    // ========================================================
    // 获取 KM 详情（核心方法）
    // ========================================================

    public function getKillDetails(int $killId): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, 3600, function () use ($killId) {
            $hash = $this->extractEsiHash($killId);

            if ($hash === null) {
                return $this->getKillDetailsFallback($killId);
            }

            try {
                $esiUrl = $this->esiBaseUrl . "killmails/{$killId}/{$hash}/";
                $response = Http::timeout(15)->get($esiUrl, [
                    'datasource' => $this->datasource,
                ]);

                if (!$response->ok()) {
                    Log::warning("ESI killmail 请求失败: HTTP {$response->status()} for kill {$killId}");
                    return $this->getKillDetailsFallback($killId);
                }

                $esiData = $response->json();
                if (empty($esiData) || !isset($esiData['victim'])) {
                    return $this->getKillDetailsFallback($killId);
                }

                $idsToResolve = $this->collectIds($esiData);
                $names = $this->resolveNames($idsToResolve);

                return $this->buildKillDetailResponse($esiData, $names, $killId);
            } catch (\Exception $e) {
                Log::warning("ESI killmail 处理异常: " . $e->getMessage());
                return $this->getKillDetailsFallback($killId);
            }
        });
    }

    public function getKillDetailsByHash(int $killId, string $hash): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, 3600, function () use ($killId, $hash) {
            Cache::put("kb:esi_hash:{$killId}", $hash, 86400);

            $esiUrl = $this->esiBaseUrl . "killmails/{$killId}/{$hash}/";
            $response = Http::timeout(15)->get($esiUrl, [
                'datasource' => $this->datasource,
            ]);

            if (!$response->ok()) {
                throw new \Exception("ESI 请求失败: HTTP {$response->status()}");
            }

            $esiData = $response->json();
            if (empty($esiData) || !isset($esiData['victim'])) {
                throw new \Exception('ESI 返回数据无效');
            }

            $idsToResolve = $this->collectIds($esiData);
            $names = $this->resolveNames($idsToResolve);

            return $this->buildKillDetailResponse($esiData, $names, $killId);
        });
    }

    protected function extractEsiHash(int $killId): ?string
    {
        $cacheKey = "kb:esi_hash:{$killId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null;
        }

        try {
            $response = Http::timeout(10)->get("{$this->betaKbUrl}/app/kill/{$killId}/info");

            if ($response->ok() && strlen($response->body()) > 0) {
                if (preg_match('/[a-f0-9]{40}/', $response->body(), $m)) {
                    $hash = $m[0];
                    Cache::put($cacheKey, $hash, 86400);
                    return $hash;
                }
            }
        } catch (\Exception $e) {
            Log::debug("Beta KB hash 提取失败 (kill {$killId}): " . $e->getMessage());
        }

        try {
            $url = "{$this->kbUrl}/kill/{$killId}/";
            $response = Http::withHeaders($this->kbHeaders())
                ->timeout(15)
                ->get($url);

            if ($response->ok()) {
                $html = $response->body();
                if (preg_match('/ali-esi\.evepc\.163\.com\/latest\/killmails\/\d+\/([a-f0-9]+)/i', $html, $m)) {
                    $hash = $m[1];
                    Cache::put($cacheKey, $hash, 86400);
                    return $hash;
                }
            }
        } catch (\Exception $e) {
            Log::warning("旧 KB hash 提取也失败 (kill {$killId}): " . $e->getMessage());
        }

        Cache::put($cacheKey, '', 3600);
        return null;
    }

    protected function collectIds(array $esiData): array
    {
        $ids = [];

        if (!empty($esiData['solar_system_id'])) {
            $ids[] = (int) $esiData['solar_system_id'];
        }

        $victim = $esiData['victim'] ?? [];
        foreach (['character_id', 'corporation_id', 'alliance_id', 'ship_type_id'] as $field) {
            if (!empty($victim[$field])) {
                $ids[] = (int) $victim[$field];
            }
        }

        foreach ($victim['items'] ?? [] as $item) {
            if (!empty($item['item_type_id'])) {
                $ids[] = (int) $item['item_type_id'];
            }
        }

        foreach ($esiData['attackers'] ?? [] as $attacker) {
            foreach (['character_id', 'corporation_id', 'alliance_id', 'ship_type_id', 'weapon_type_id'] as $field) {
                if (!empty($attacker[$field])) {
                    $ids[] = (int) $attacker[$field];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function resolveNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $names = [];
        $missing = [];

        $itemDb = $this->eveData->getItemDatabase();
        foreach ($ids as $id) {
            if (isset($itemDb[$id])) {
                $names[$id] = $itemDb[$id];
            }
        }

        foreach ($ids as $id) {
            if (isset($names[$id])) continue;
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }

        if (!empty($missing)) {
            $this->resolveNamesFromEsi($missing, $names);
        }

        return $names;
    }

    protected function resolveNamesFromEsi(array $ids, array &$names): void
    {
        $chunks = array_chunk($ids, 1000);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::timeout(10)->post(
                    $this->esiBaseUrl . 'universe/names/',
                    $chunk
                );

                if ($response->ok()) {
                    foreach ($response->json() as $item) {
                        if (isset($item['id']) && isset($item['name'])) {
                            $names[(int) $item['id']] = $item['name'];
                            Cache::put("eve_name_{$item['id']}", $item['name'], 86400);
                        }
                    }
                } else {
                    $this->resolveNamesFallback($chunk, $names);
                }
            } catch (\Exception $e) {
                Log::warning('ESI universe/names 批量查询失败: ' . $e->getMessage());
                $this->resolveNamesFallback($chunk, $names);
            }
        }
    }

    protected function resolveNamesFallback(array $ids, array &$names): void
    {
        foreach ($ids as $id) {
            if (isset($names[$id])) continue;

            try {
                $response = Http::timeout(5)->post(
                    $this->esiBaseUrl . 'universe/names/',
                    [$id]
                );

                if ($response->ok()) {
                    $result = $response->json();
                    if (!empty($result[0]['name'])) {
                        $names[$id] = $result[0]['name'];
                        Cache::put("eve_name_{$id}", $result[0]['name'], 86400);
                        continue;
                    }
                }

                $response = Http::timeout(5)->get(
                    $this->esiBaseUrl . "universe/types/{$id}/",
                    ['datasource' => $this->datasource, 'language' => 'zh']
                );

                if ($response->ok()) {
                    $data = $response->json();
                    if (!empty($data['name'])) {
                        $names[$id] = $data['name'];
                        Cache::put("eve_name_{$id}", $data['name'], 86400);
                    }
                }
            } catch (\Exception $e) {
                // 忽略单个失败
            }
        }
    }

    // ========================================================
    // 物品槽位映射
    // ========================================================

    protected function mapItemFlag(int $flag): string
    {
        if ($flag >= 27 && $flag <= 34) return '高槽 ' . ($flag - 26);
        if ($flag >= 19 && $flag <= 26) return '中槽 ' . ($flag - 18);
        if ($flag >= 11 && $flag <= 18) return '低槽 ' . ($flag - 10);
        if ($flag >= 92 && $flag <= 94) return '改装件 ' . ($flag - 91);
        if ($flag >= 125 && $flag <= 132) return '子系统 ' . ($flag - 124);

        $map = [
            5 => '货柜舱',
            87 => '无人机舱',
            88 => '无人机舱',
            89 => '弹药舱',
            90 => '弹药舱',
        ];

        return $map[$flag] ?? '';
    }

    /**
     * 将 ESI flag 映射为槽位分组名
     */
    protected function mapSlotGroup(int $flag): string
    {
        if ($flag >= 27 && $flag <= 34) return '高槽';
        if ($flag >= 19 && $flag <= 26) return '中槽';
        if ($flag >= 11 && $flag <= 18) return '低槽';
        if ($flag >= 92 && $flag <= 94) return '改装件';
        if ($flag >= 125 && $flag <= 132) return '子系统';
        if ($flag >= 87 && $flag <= 88) return '无人机舱';
        if ($flag === 5) return '货柜舱';
        if ($flag >= 89 && $flag <= 90) return '弹药舱';
        return '其他';
    }

    // ========================================================
    // 组装 KM 详情返回数据 (增强版)
    // ========================================================

    protected function buildKillDetailResponse(array $esiData, array $names, int $killId): array
    {
        $victim = $esiData['victim'] ?? [];

        // 处理时间 - 转为北京时间
        $killTimeRaw = $esiData['killmail_time'] ?? null;
        $killTimeBj = null;
        if ($killTimeRaw) {
            try {
                $dt = new \DateTime($killTimeRaw, new \DateTimeZone('UTC'));
                $dt->setTimezone(new \DateTimeZone('Asia/Shanghai'));
                $killTimeBj = $dt->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $killTimeBj = $killTimeRaw;
            }
        }

        // 获取市场价格表 (用于物品估价)
        $marketPrices = $this->getMarketPrices();

        // 处理受害者物品 - 同时构建按槽位分组
        $items = [];
        $itemsBySlot = [];
        $slotOrder = ['高槽', '中槽', '低槽', '改装件', '子系统', '无人机舱', '弹药舱', '货柜舱', '其他'];

        foreach ($victim['items'] ?? [] as $item) {
            $typeId = $item['item_type_id'] ?? 0;
            $flag = $item['flag'] ?? 0;
            $slotGroup = $this->mapSlotGroup($flag);
            $unitPrice = $marketPrices[$typeId] ?? 0;

            if (!empty($item['quantity_destroyed']) && !empty($item['quantity_dropped'])) {
                $destroyedQty = (int) $item['quantity_destroyed'];
                $droppedQty = (int) $item['quantity_dropped'];
                $destroyedItem = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => $destroyedQty,
                    'status' => 'destroyed',
                    'flag' => $flag,
                    'flag_name' => $this->mapItemFlag($flag),
                    'slot_group' => $slotGroup,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $destroyedQty, 2),
                ];
                $droppedItem = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => $droppedQty,
                    'status' => 'dropped',
                    'flag' => $flag,
                    'flag_name' => $this->mapItemFlag($flag),
                    'slot_group' => $slotGroup,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $droppedQty, 2),
                ];
                $items[] = $destroyedItem;
                $items[] = $droppedItem;
                $itemsBySlot[$slotGroup][] = $destroyedItem;
                $itemsBySlot[$slotGroup][] = $droppedItem;
            } else {
                $qty = ($item['quantity_destroyed'] ?? 0) + ($item['quantity_dropped'] ?? 0);
                $status = isset($item['quantity_dropped']) && $item['quantity_dropped'] > 0 ? 'dropped' : 'destroyed';
                $qty = $qty > 0 ? $qty : 1;
                $singleItem = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => $qty,
                    'status' => $status,
                    'flag' => $flag,
                    'flag_name' => $this->mapItemFlag($flag),
                    'slot_group' => $slotGroup,
                    'unit_price' => $unitPrice,
                    'total_price' => round($unitPrice * $qty, 2),
                ];
                $items[] = $singleItem;
                $itemsBySlot[$slotGroup][] = $singleItem;
            }
        }

        // 按预定义顺序排列槽位
        $orderedItemsBySlot = [];
        foreach ($slotOrder as $slot) {
            if (!empty($itemsBySlot[$slot])) {
                $orderedItemsBySlot[$slot] = $itemsBySlot[$slot];
            }
        }

        // 处理攻击者 - 排序: final_blow 首位 → 最高伤害 → 其余按伤害降序
        $attackers = [];
        foreach ($esiData['attackers'] ?? [] as $atk) {
            $attackers[] = [
                'character_id' => $atk['character_id'] ?? null,
                'character_name' => isset($atk['character_id']) ? ($names[$atk['character_id']] ?? "未知#{$atk['character_id']}") : null,
                'corporation_id' => $atk['corporation_id'] ?? null,
                'corporation_name' => isset($atk['corporation_id']) ? ($names[$atk['corporation_id']] ?? null) : null,
                'alliance_id' => $atk['alliance_id'] ?? null,
                'alliance_name' => isset($atk['alliance_id']) ? ($names[$atk['alliance_id']] ?? null) : null,
                'ship_type_id' => $atk['ship_type_id'] ?? null,
                'ship_name' => isset($atk['ship_type_id']) ? ($names[$atk['ship_type_id']] ?? "未知#{$atk['ship_type_id']}") : null,
                'weapon_type_id' => $atk['weapon_type_id'] ?? null,
                'weapon_name' => isset($atk['weapon_type_id']) ? ($names[$atk['weapon_type_id']] ?? "未知#{$atk['weapon_type_id']}") : null,
                'damage_done' => $atk['damage_done'] ?? 0,
                'final_blow' => $atk['final_blow'] ?? false,
                'security_status' => round($atk['security_status'] ?? 0, 1),
            ];
        }

        // 排序: final_blow 首位, 然后按伤害降序
        usort($attackers, function ($a, $b) {
            if ($a['final_blow'] && !$b['final_blow']) return -1;
            if (!$a['final_blow'] && $b['final_blow']) return 1;
            return $b['damage_done'] <=> $a['damage_done'];
        });

        $solarSystemId = $esiData['solar_system_id'] ?? null;

        // 获取星系安等和星域
        $systemSec = null;
        $regionName = null;
        if ($solarSystemId) {
            $sysInfo = $this->getSystemInfo((int) $solarSystemId);
            $systemSec = $sysInfo['security_status'];
            $regionName = $sysInfo['region_name'];
        }

        return [
            'kill_id' => $killId,
            'kill_time' => $killTimeBj,
            'kill_time_raw' => $killTimeRaw,
            'solar_system_id' => $solarSystemId,
            'solar_system_name' => $solarSystemId ? ($names[$solarSystemId] ?? "未知#{$solarSystemId}") : null,
            'system_sec' => $systemSec,
            'region_name' => $regionName,
            'esi_verified' => true,
            'victim' => [
                'character_id' => $victim['character_id'] ?? null,
                'character_name' => isset($victim['character_id']) ? ($names[$victim['character_id']] ?? "未知#{$victim['character_id']}") : null,
                'corporation_id' => $victim['corporation_id'] ?? null,
                'corporation_name' => isset($victim['corporation_id']) ? ($names[$victim['corporation_id']] ?? null) : null,
                'alliance_id' => $victim['alliance_id'] ?? null,
                'alliance_name' => isset($victim['alliance_id']) ? ($names[$victim['alliance_id']] ?? null) : null,
                'ship_type_id' => $victim['ship_type_id'] ?? null,
                'ship_name' => isset($victim['ship_type_id']) ? ($names[$victim['ship_type_id']] ?? "未知#{$victim['ship_type_id']}") : null,
                'damage_taken' => $victim['damage_taken'] ?? 0,
                'items' => $items,
            ],
            'items_by_slot' => $orderedItemsBySlot,
            'attackers' => $attackers,
            'attacker_count' => count($attackers),
        ];
    }

    // ========================================================
    // 降级方案：HTML 解析（无 ESI hash 时使用）
    // ========================================================

    protected function getKillDetailsFallback(int $killId): array
    {
        try {
            $url = "{$this->kbUrl}/kill/{$killId}/";
            $response = Http::withHeaders($this->kbHeaders())
                ->timeout(15)
                ->get($url);

            if (!$response->ok()) {
                throw new \Exception("获取 KM 详情失败: HTTP {$response->status()}");
            }

            $html = $response->body();

            if (strpos($html, '世界线已发生混乱') !== false || strpos($html, '404') !== false) {
                throw new \Exception('KM 不存在或页面暂时不可用');
            }

            return $this->parseKillPageFallback($html, $killId);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function parseKillPageFallback(string $html, int $killId): array
    {
        $data = [
            'kill_id' => $killId,
            'kill_time' => null,
            'kill_time_raw' => null,
            'solar_system_id' => null,
            'solar_system_name' => null,
            'system_sec' => null,
            'region_name' => null,
            'esi_verified' => false,
            'victim' => [
                'character_id' => null,
                'character_name' => null,
                'corporation_id' => null,
                'corporation_name' => null,
                'alliance_id' => null,
                'alliance_name' => null,
                'ship_type_id' => null,
                'ship_name' => null,
                'damage_taken' => 0,
                'items' => [],
            ],
            'items_by_slot' => [],
            'attackers' => [],
            'attacker_count' => 0,
        ];

        if (preg_match('/受害人.*?href="\/pilot\/(\d+)\/"[^>]*>.*?data-alt="([^"]+)"/si', $html, $m)) {
            $data['victim']['character_id'] = (int) $m[1];
            $data['victim']['character_name'] = html_entity_decode($m[2]);
        } elseif (preg_match('/受害人.*?href="\/pilot\/(\d+)\/"[^>]*>\s*<span[^>]*>(?:<span>)?([^<]+)/si', $html, $m)) {
            $data['victim']['character_id'] = (int) $m[1];
            $data['victim']['character_name'] = trim(strip_tags($m[2]));
        }

        if (preg_match('/受害人.*?军团.*?href="\/corp\/(\d+)[^"]*"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim']['corporation_id'] = (int) $m[1];
            $data['victim']['corporation_name'] = trim($m[2]);
        }

        if (preg_match('/受害人.*?联盟.*?href="\/alliance\/(\d+)[^"]*"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim']['alliance_id'] = (int) $m[1];
            $data['victim']['alliance_name'] = trim($m[2]);
        }

        if (preg_match('/舰船.*?href="\/invtype\/(\d+)\/"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim']['ship_type_id'] = (int) $m[1];
            $data['victim']['ship_name'] = trim($m[2]);
        }

        if (preg_match('/href="\/system\/(\d+)\/"[^>]*>([^<]+)/si', $html, $m)) {
            $data['solar_system_name'] = trim($m[2]);
        }

        if (preg_match('/时间[：:]\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/u', $html, $m)) {
            $data['kill_time'] = $m[1];
        }

        if (preg_match('/所受伤害[：:]\s*([\d,]+)/u', $html, $m)) {
            $data['victim']['damage_taken'] = (int) str_replace(',', '', $m[1]);
        }

        if (preg_match('/参与者\s*\(?\s*(\d+)\s*\)?/u', $html, $m)) {
            $data['attacker_count'] = (int) $m[1];
        }

        if (preg_match('/最后一击.*?href="\/pilot\/(\d+)\/"[^>]*>(.*?)<\/a>/si', $html, $m)) {
            $attackerName = trim(strip_tags($m[2]));
            if (preg_match('/最后一击.*?data-alt="([^"]+)"/si', $html, $m2)) {
                $attackerName = html_entity_decode($m2[1]);
            }

            $data['attackers'][] = [
                'character_id' => (int) $m[1],
                'character_name' => $attackerName,
                'corporation_id' => null,
                'corporation_name' => null,
                'alliance_id' => null,
                'alliance_name' => null,
                'ship_type_id' => null,
                'ship_name' => null,
                'weapon_type_id' => null,
                'weapon_name' => null,
                'damage_done' => 0,
                'final_blow' => true,
                'security_status' => 0,
            ];
        }

        // 掉落物品
        if (preg_match_all('/<tr[^>]*style="[^"]*background-color:\s*#006000[^"]*"[^>]*>.*?title="([^"]+)".*?<\/tr>/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $qty = 1;
                if (preg_match('/<td[^>]*>\s*(\d+)\s*<\/td>/si', $m[0], $qm)) {
                    $qty = (int) $qm[1];
                }
                $data['victim']['items'][] = [
                    'item_type_id' => null,
                    'item_name' => html_entity_decode($m[1]),
                    'quantity' => $qty,
                    'status' => 'dropped',
                    'flag' => 0,
                    'flag_name' => '',
                    'slot_group' => '其他',
                ];
            }
        }

        // 损毁物品
        if (preg_match_all('/<tr[^>]*>(?=.*destroyed\.png).*?title="([^"]+)".*?<\/tr>/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $qty = 1;
                if (preg_match('/<td[^>]*>\s*(\d+)\s*<\/td>/si', $m[0], $qm)) {
                    $qty = (int) $qm[1];
                }
                $data['victim']['items'][] = [
                    'item_type_id' => null,
                    'item_name' => html_entity_decode($m[1]),
                    'quantity' => $qty,
                    'status' => 'destroyed',
                    'flag' => 0,
                    'flag_name' => '',
                    'slot_group' => '其他',
                ];
            }
        }

        return $data;
    }
}
