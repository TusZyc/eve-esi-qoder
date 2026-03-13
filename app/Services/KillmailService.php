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

    // ========================================================
    // Protobuf 解码工具 (用于解析 beta KB API 响应)
    // ========================================================

    /**
     * 解码 protobuf varint
     */
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

    /**
     * 解析 protobuf message 为字段数组
     * 返回 [['field' => N, 'wire' => W, 'value' => V], ...]
     */
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
                case 0: // varint
                    $value = $this->pbDecodeVarint($data, $offset, $offset);
                    break;
                case 1: // 64-bit (double)
                    if ($offset + 8 > $len) return $fields;
                    $value = substr($data, $offset, 8);
                    $offset += 8;
                    break;
                case 2: // length-delimited
                    $length = $this->pbDecodeVarint($data, $offset, $offset);
                    if ($offset + $length > $len) return $fields;
                    $value = substr($data, $offset, $length);
                    $offset += $length;
                    break;
                case 5: // 32-bit
                    if ($offset + 4 > $len) return $fields;
                    $value = substr($data, $offset, 4);
                    $offset += 4;
                    break;
                default:
                    return $fields; // 未知类型，停止解析
            }

            $fields[] = ['field' => $fieldNum, 'wire' => $wireType, 'value' => $value];
        }
        return $fields;
    }

    /**
     * 从 protobuf nested message 中按 field number 提取 varint 值
     */
    protected function pbGetVarint(array $fields, int $fieldNum): ?int
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 0) {
                return (int) $f['value'];
            }
        }
        return null;
    }

    /**
     * 从 protobuf nested message 中按 field number 提取子 message 的第一个字符串
     * beta KB 的名称字段结构: field N (wire 2) -> 内部 field 1 (wire 2) -> UTF-8 字符串
     */
    protected function pbGetString(array $fields, int $fieldNum): ?string
    {
        foreach ($fields as $f) {
            if ($f['field'] === $fieldNum && $f['wire'] === 2) {
                $inner = $f['value'];
                // 如果是合法 UTF-8 且不含控制字符（除空格），直接返回
                if (preg_match('/^[\x20-\x7E\xC0-\xFF][\x20-\x7E\x80-\xFF]*$/s', $inner) && mb_check_encoding($inner, 'UTF-8')) {
                    return $inner;
                }
                // 否则当作嵌套 message，提取 field 1 的字符串
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

    /**
     * 从 protobuf nested message 中按 field number 提取 double 值
     */
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
    // 搜索角色
    // ========================================================

    public function searchCharacter(string $query): array
    {
        return Cache::remember("kb:search:" . md5($query), 300, function () use ($query) {
            $results = [];

            // 方案 1: KB 自动补全 + ESI 搜索获取 character_id
            try {
                $results = $this->searchViaKbAndEsi($query);
            } catch (\Exception $e) {
                Log::warning('KB+ESI 搜索失败: ' . $e->getMessage());
            }

            // 方案 2: 本地用户数据库
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

    /**
     * KB 自动补全 + ESI 搜索组合
     */
    protected function searchViaKbAndEsi(string $query): array
    {
        // Step 1: KB 自动补全获取匹配的角色名
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

        // 限制只处理前 10 个名称
        $names = array_slice($names, 0, 10);

        // Step 2: 用 ESI universe/ids 批量将名称转换为 character_id
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

    /**
     * 搜索本地已注册用户
     */
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
    // 获取角色 KM 列表
    // ========================================================

    public function getPilotKills(int $pilotId, string $mode = 'kills'): array
    {
        $cacheKey = "kb:pilot:{$pilotId}:{$mode}";

        return Cache::remember($cacheKey, 600, function () use ($pilotId) {
            // 方案 1: Beta KB API (protobuf，数据更丰富)
            try {
                $kills = $this->fetchBetaKillList($pilotId);
                if (!empty($kills)) {
                    return $kills;
                }
            } catch (\Exception $e) {
                Log::debug("Beta KB kill list 失败 (pilot {$pilotId}): " . $e->getMessage());
            }

            // 方案 2: 旧 KB HTML 解析 (降级)
            try {
                $url = "{$this->kbUrl}/pilot/{$pilotId}/";
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
                Log::debug("旧 KB kill list 也失败 (pilot {$pilotId}): " . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * 从 Beta KB API 获取角色 KM 列表 (protobuf 格式)
     * 端点: /app/list/pilot/{id}/kill
     *
     * Protobuf 字段映射:
     *   F1(varint)  kill_id
     *   F2(msg)     victim: f1=character_id, f2=name
     *   F8(msg)     ship: f1=type_id, f2=ship_name, f4=group_name
     *   F9(msg)     kill_time: f1=unix_timestamp
     *   F10(msg)    solar_system: f1=system_id, f2=system_name
     *   F13(double) total_value (ISK)
     *   F16(msg)    esi_hash: f1=40-char-hex
     */
    protected function fetchBetaKillList(int $pilotId): array
    {
        $response = Http::timeout(10)
            ->get("{$this->betaKbUrl}/app/list/pilot/{$pilotId}/kill");

        if (!$response->ok() || strlen($response->body()) === 0) {
            return [];
        }

        $entries = $this->pbParseMessage($response->body());
        $kills = [];

        foreach ($entries as $entry) {
            if ($entry['field'] !== 1 || $entry['wire'] !== 2) continue;

            $fields = $this->pbParseMessage($entry['value']);
            $killId = $this->pbGetVarint($fields, 1);
            if (!$killId || $killId <= 0) continue;

            // 受害者 (F2)
            $victimName = null;
            foreach ($fields as $f) {
                if ($f['field'] === 2 && $f['wire'] === 2) {
                    $victimName = $this->pbGetString($this->pbParseMessage($f['value']), 2);
                    break;
                }
            }

            // 舰船 (F8)
            $shipName = null;
            foreach ($fields as $f) {
                if ($f['field'] === 8 && $f['wire'] === 2) {
                    $shipName = $this->pbGetString($this->pbParseMessage($f['value']), 2);
                    break;
                }
            }

            // 星系 (F10)
            $systemName = null;
            foreach ($fields as $f) {
                if ($f['field'] === 10 && $f['wire'] === 2) {
                    $systemName = $this->pbGetString($this->pbParseMessage($f['value']), 2);
                    break;
                }
            }

            // 时间 (F9 -> f1=timestamp)
            $killTime = null;
            foreach ($fields as $f) {
                if ($f['field'] === 9 && $f['wire'] === 2) {
                    $ts = $this->pbGetVarint($this->pbParseMessage($f['value']), 1);
                    if ($ts && $ts > 1000000000) {
                        $killTime = date('Y-m-d H:i:s', $ts);
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
                'victim_name' => $victimName,
                'ship_name' => $shipName,
                'system_name' => $systemName,
                'kill_time' => $killTime,
                'total_value' => $totalValue ? round($totalValue, 2) : null,
                'esi_hash' => $esiHash,
            ];
        }

        return $kills;
    }

    /**
     * 解析 KB 角色页的 KM 列表
     * 从表格行中提取 kill_id + 基础信息
     */
    protected function parseKillList(string $html): array
    {
        $kills = [];
        $seen = [];

        // 按 <tr> 行分割，逐行解析
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $html, $rows)) {
            foreach ($rows[1] as $row) {
                // 提取 kill_id
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

                // 提取舰船名称 - 从 invtype 链接或 title 属性
                if (preg_match('/href="\/invtype\/\d+\/"[^>]*>([^<]+)/i', $row, $m)) {
                    $kill['ship_name'] = trim(strip_tags($m[1]));
                } elseif (preg_match('/title="([^"]+)"[^>]*class="[^"]*ship/i', $row, $m)) {
                    $kill['ship_name'] = trim($m[1]);
                }

                // 提取受害者名称 - 从 pilot 链接
                if (preg_match_all('/href="\/pilot\/\d+\/"[^>]*>([^<]+)/i', $row, $m)) {
                    // 通常第一个 pilot 链接是受害者
                    foreach ($m[1] as $name) {
                        $name = trim(strip_tags($name));
                        if (!empty($name) && $name !== '未知') {
                            $kill['victim_name'] = $name;
                            break;
                        }
                    }
                }

                // 提取星系名称
                if (preg_match('/href="\/system\/\d+\/"[^>]*>([^<]+)/i', $row, $m)) {
                    $kill['system_name'] = trim(strip_tags($m[1]));
                }

                // 提取时间
                if (preg_match('/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?)/', $row, $m)) {
                    $kill['kill_time'] = $m[1];
                }

                $kills[] = $kill;
            }
        }

        // 如果按行解析没找到，回退到简单的 kill_id 提取
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
    // 获取 KM 详情（核心方法）
    // ========================================================

    public function getKillDetails(int $killId): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, 3600, function () use ($killId) {
            // Step 1: 从 KB 提取 ESI hash
            $hash = $this->extractEsiHash($killId);

            if ($hash === null) {
                // 降级：使用 HTML 解析
                return $this->getKillDetailsFallback($killId);
            }

            // Step 2: 调用 ESI API 获取结构化数据
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

                // Step 3: 收集所有需要解析的 ID
                $idsToResolve = $this->collectIds($esiData);

                // Step 4: 批量名称解析
                $names = $this->resolveNames($idsToResolve);

                // Step 5: 组装返回数据
                return $this->buildKillDetailResponse($esiData, $names, $killId);

            } catch (\Exception $e) {
                Log::warning("ESI killmail 处理异常: " . $e->getMessage());
                return $this->getKillDetailsFallback($killId);
            }
        });
    }

    /**
     * 通过前端提供的 hash 直接调用 ESI 获取 KM 详情
     */
    public function getKillDetailsByHash(int $killId, string $hash): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, 3600, function () use ($killId, $hash) {
            // 缓存 hash
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

    /**
     * 从 Beta KB API 或旧 KB 击杀页提取 ESI hash
     * 优先使用 beta.ceve-market.org/app/kill/{id}/info (protobuf 响应中包含 hash)
     * 降级使用旧 KB HTML 页面正则提取
     */
    protected function extractEsiHash(int $killId): ?string
    {
        // hash 永不变化，长期缓存
        $cacheKey = "kb:esi_hash:{$killId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached ?: null; // 空字符串表示无 hash
        }

        // 方案 1: Beta KB API (protobuf 响应，从二进制中提取 40 位 hex hash)
        try {
            $response = Http::timeout(10)->get("{$this->betaKbUrl}/app/kill/{$killId}/info");

            if ($response->ok() && strlen($response->body()) > 0) {
                if (preg_match('/[a-f0-9]{40}/', $response->body(), $m)) {
                    $hash = $m[0];
                    Cache::put($cacheKey, $hash, 86400);
                    Log::debug("Beta KB 提取 hash 成功: kill {$killId}");
                    return $hash;
                }
            }
        } catch (\Exception $e) {
            Log::debug("Beta KB hash 提取失败 (kill {$killId}): " . $e->getMessage());
        }

        // 方案 2: 旧 KB HTML 页面 (降级)
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

        // 没找到 hash，缓存空值避免重复请求
        Cache::put($cacheKey, '', 3600);
        return null;
    }

    /**
     * 从 ESI 数据中收集所有需要解析的 ID
     */
    protected function collectIds(array $esiData): array
    {
        $ids = [];

        // 星系
        if (!empty($esiData['solar_system_id'])) {
            $ids[] = (int) $esiData['solar_system_id'];
        }

        // 受害者
        $victim = $esiData['victim'] ?? [];
        foreach (['character_id', 'corporation_id', 'alliance_id', 'ship_type_id'] as $field) {
            if (!empty($victim[$field])) {
                $ids[] = (int) $victim[$field];
            }
        }

        // 受害者物品
        foreach ($victim['items'] ?? [] as $item) {
            if (!empty($item['item_type_id'])) {
                $ids[] = (int) $item['item_type_id'];
            }
        }

        // 攻击者
        foreach ($esiData['attackers'] ?? [] as $attacker) {
            foreach (['character_id', 'corporation_id', 'alliance_id', 'ship_type_id', 'weapon_type_id'] as $field) {
                if (!empty($attacker[$field])) {
                    $ids[] = (int) $attacker[$field];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * 三级名称解析：本地JSON → Redis缓存 → ESI API
     */
    protected function resolveNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $names = [];
        $missing = [];

        // 第一级：本地物品数据库（type_id 效果好）
        $itemDb = $this->eveData->getItemDatabase();
        foreach ($ids as $id) {
            if (isset($itemDb[$id])) {
                $names[$id] = $itemDb[$id];
            }
        }

        // 第二级：Redis 缓存
        foreach ($ids as $id) {
            if (isset($names[$id])) continue;
            $cached = Cache::get("eve_name_{$id}");
            if ($cached !== null) {
                $names[$id] = $cached;
            } else {
                $missing[] = $id;
            }
        }

        // 第三级：ESI API 批量查询
        if (!empty($missing)) {
            $this->resolveNamesFromEsi($missing, $names);
        }

        return $names;
    }

    /**
     * 通过 ESI API 批量解析名称
     */
    protected function resolveNamesFromEsi(array $ids, array &$names): void
    {
        // 分批处理，每批最多 1000 个
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
                    // universe/names 对无效 ID 可能返回 404，逐个重试
                    $this->resolveNamesFallback($chunk, $names);
                }
            } catch (\Exception $e) {
                Log::warning('ESI universe/names 批量查询失败: ' . $e->getMessage());
                $this->resolveNamesFallback($chunk, $names);
            }
        }
    }

    /**
     * 逐个 ID 回退解析（universe/types 用于 type_id）
     */
    protected function resolveNamesFallback(array $ids, array &$names): void
    {
        foreach ($ids as $id) {
            if (isset($names[$id])) continue;

            try {
                // 先尝试 universe/names 单个
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

                // 回退到 universe/types（适用于 type_id）
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

    /**
     * 将 ESI flag 映射为中文槽位名
     */
    protected function mapItemFlag(int $flag): string
    {
        // 高能量槽
        if ($flag >= 27 && $flag <= 34) return '高槽 ' . ($flag - 26);
        // 中能量槽
        if ($flag >= 19 && $flag <= 26) return '中槽 ' . ($flag - 18);
        // 低能量槽
        if ($flag >= 11 && $flag <= 18) return '低槽 ' . ($flag - 10);
        // 改装件
        if ($flag >= 92 && $flag <= 94) return '改装件 ' . ($flag - 91);
        // 子系统
        if ($flag >= 125 && $flag <= 132) return '子系统 ' . ($flag - 124);
        // 特殊槽位
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
     * 组装 KM 详情返回数据
     */
    protected function buildKillDetailResponse(array $esiData, array $names, int $killId): array
    {
        $victim = $esiData['victim'] ?? [];

        // 处理受害者物品
        $items = [];
        foreach ($victim['items'] ?? [] as $item) {
            $typeId = $item['item_type_id'] ?? 0;
            $qty = ($item['quantity_destroyed'] ?? 0) + ($item['quantity_dropped'] ?? 0);
            $status = isset($item['quantity_dropped']) && $item['quantity_dropped'] > 0 ? 'dropped' : 'destroyed';

            // 如果同时有 destroyed 和 dropped，拆成两条
            if (!empty($item['quantity_destroyed']) && !empty($item['quantity_dropped'])) {
                $items[] = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => (int) $item['quantity_destroyed'],
                    'status' => 'destroyed',
                    'flag' => $item['flag'] ?? 0,
                    'flag_name' => $this->mapItemFlag($item['flag'] ?? 0),
                ];
                $items[] = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => (int) $item['quantity_dropped'],
                    'status' => 'dropped',
                    'flag' => $item['flag'] ?? 0,
                    'flag_name' => $this->mapItemFlag($item['flag'] ?? 0),
                ];
            } else {
                $items[] = [
                    'item_type_id' => $typeId,
                    'item_name' => $names[$typeId] ?? "未知#{$typeId}",
                    'quantity' => $qty > 0 ? $qty : 1,
                    'status' => $status,
                    'flag' => $item['flag'] ?? 0,
                    'flag_name' => $this->mapItemFlag($item['flag'] ?? 0),
                ];
            }
        }

        // 处理攻击者
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

        // 按伤害降序排列
        usort($attackers, function ($a, $b) {
            return $b['damage_done'] <=> $a['damage_done'];
        });

        $solarSystemId = $esiData['solar_system_id'] ?? null;

        return [
            'kill_id' => $killId,
            'kill_time' => $esiData['killmail_time'] ?? null,
            'solar_system_id' => $solarSystemId,
            'solar_system_name' => $solarSystemId ? ($names[$solarSystemId] ?? "未知#{$solarSystemId}") : null,
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

    /**
     * HTML 解析降级（保留旧逻辑）
     */
    protected function parseKillPageFallback(string $html, int $killId): array
    {
        $data = [
            'kill_id' => $killId,
            'kill_time' => null,
            'solar_system_id' => null,
            'solar_system_name' => null,
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
            'attackers' => [],
            'attacker_count' => 0,
        ];

        // 受害者名称
        if (preg_match('/受害人.*?href="\/pilot\/(\d+)\/"[^>]*>.*?data-alt="([^"]+)"/si', $html, $m)) {
            $data['victim']['character_id'] = (int) $m[1];
            $data['victim']['character_name'] = html_entity_decode($m[2]);
        } elseif (preg_match('/受害人.*?href="\/pilot\/(\d+)\/"[^>]*>\s*<span[^>]*>(?:<span>)?([^<]+)/si', $html, $m)) {
            $data['victim']['character_id'] = (int) $m[1];
            $data['victim']['character_name'] = trim(strip_tags($m[2]));
        }

        // 军团
        if (preg_match('/受害人.*?军团.*?href="\/corp\/(\d+)[^"]*"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim']['corporation_id'] = (int) $m[1];
            $data['victim']['corporation_name'] = trim($m[2]);
        }

        // 联盟
        if (preg_match('/受害人.*?联盟.*?href="\/alliance\/(\d+)[^"]*"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim']['alliance_id'] = (int) $m[1];
            $data['victim']['alliance_name'] = trim($m[2]);
        }

        // 舰船
        if (preg_match('/舰船.*?href="\/invtype\/(\d+)\/"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim']['ship_type_id'] = (int) $m[1];
            $data['victim']['ship_name'] = trim($m[2]);
        }

        // 星系
        if (preg_match('/href="\/system\/(\d+)\/"[^>]*>([^<]+)/si', $html, $m)) {
            $data['solar_system_name'] = trim($m[2]);
        }

        // 时间
        if (preg_match('/时间[：:]\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/u', $html, $m)) {
            $data['kill_time'] = $m[1];
        }

        // 伤害
        if (preg_match('/所受伤害[：:]\s*([\d,]+)/u', $html, $m)) {
            $data['victim']['damage_taken'] = (int) str_replace(',', '', $m[1]);
        }

        // 参与者
        if (preg_match('/参与者\s*\(?\s*(\d+)\s*\)?/u', $html, $m)) {
            $data['attacker_count'] = (int) $m[1];
        }

        // 最后一击
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
                ];
            }
        }

        return $data;
    }
}
