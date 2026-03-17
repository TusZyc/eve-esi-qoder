<?php

namespace App\Services\Killmail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Beta KB API 客户端
 * 
 * 负责与 beta.ceve-market.org 和 kb.ceve-market.org 交互
 * 包括 XSRF cookie 获取、Protobuf API 调用、KM 列表获取等
 */
class BetaKbApiClient
{
    protected string $kbUrl = 'https://kb.ceve-market.org';
    protected string $betaKbUrl = 'https://beta.ceve-market.org';
    protected ProtobufCodec $codec;

    public function __construct(ProtobufCodec $codec)
    {
        $this->codec = $codec;
    }

    /**
     * 获取 KB 请求头
     */
    public function kbHeaders(): array
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
    public function getBetaKbXsrfCookies(): array
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

    /**
     * 从 Beta KB API 获取实体 KM 列表 (protobuf 格式)
     * 仅支持 pilot (corp/alli 使用不同 URL 路径)
     */
    public function fetchBetaEntityKillList(string $entityType, int $entityId): array
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
     */
    public function fetchBetaSearchKills(string $entityType, int $entityId, string $chartype): array
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
            $body = '';
            switch ($entityType) {
                case 'pilot':
                    $body = $this->codec->encodePackedInt64(3, [$entityId]);
                    break;
                case 'corporation':
                    $body = $this->codec->encodePackedInt64(2, [$entityId]);
                    break;
                case 'alliance':
                    $body = $this->codec->encodePackedInt64(1, [$entityId]);
                    break;
                default:
                    return [];
            }
            $body .= $this->codec->encodeString(4, $chartype);

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
     */
    public function fetchBetaSearchKillsAdvanced(array $params): array
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
                        $body .= $this->codec->encodePackedInt64(3, [$entityId]);
                        break;
                    case 'corporation':
                        $body .= $this->codec->encodePackedInt64(2, [$entityId]);
                        break;
                    case 'alliance':
                        $body .= $this->codec->encodePackedInt64(1, [$entityId]);
                        break;
                }
            }

            // Chartype (F4)
            $chartype = $params['chartype'] ?? '';
            if (!empty($chartype)) {
                $body .= $this->codec->encodeString(4, $chartype);
            }

            // Types - ship/structure type IDs (F5)
            $types = $params['types'] ?? [];
            if (!empty($types)) {
                $body .= $this->codec->encodePackedInt64(5, $types);
            }

            // Systems - system IDs (F7)
            $systems = $params['systems'] ?? [];
            if (!empty($systems)) {
                $body .= $this->codec->encodePackedInt64(7, $systems);
            }

            // Start date (F9) - Protobuf Timestamp
            $startDate = $params['start_date'] ?? null;
            if ($startDate) {
                $ts = strtotime($startDate);
                if ($ts) {
                    $body .= $this->codec->encodeTimestamp(9, $ts);
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
                    $body .= $this->codec->encodeTimestamp(10, $ts);
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
    public function parseBetaKillListProtobuf(string $body): array
    {
        $entries = $this->codec->parseMessage($body);
        $kills = [];

        foreach ($entries as $entry) {
            if ($entry['field'] !== 1 || $entry['wire'] !== 2) continue;

            $fields = $this->codec->parseMessage($entry['value']);
            $killId = $this->codec->getVarint($fields, 1);
            if (!$killId || $killId <= 0) continue;

            // 受害者 (F2)
            $victimName = null;
            $victimId = null;
            foreach ($fields as $f) {
                if ($f['field'] === 2 && $f['wire'] === 2) {
                    $victimFields = $this->codec->parseMessage($f['value']);
                    $victimId = $this->codec->getVarint($victimFields, 1);
                    $victimName = $this->codec->getString($victimFields, 2);
                    break;
                }
            }

            // 舰船 (F8)
            $shipName = null;
            $shipTypeId = null;
            foreach ($fields as $f) {
                if ($f['field'] === 8 && $f['wire'] === 2) {
                    $shipFields = $this->codec->parseMessage($f['value']);
                    $shipTypeId = $this->codec->getVarint($shipFields, 1);
                    $shipName = $this->codec->getString($shipFields, 2);
                    break;
                }
            }

            // 星系 (F10)
            $systemName = null;
            $systemId = null;
            foreach ($fields as $f) {
                if ($f['field'] === 10 && $f['wire'] === 2) {
                    $sysFields = $this->codec->parseMessage($f['value']);
                    $systemId = $this->codec->getVarint($sysFields, 1);
                    $systemName = $this->codec->getString($sysFields, 2);
                    break;
                }
            }

            // 时间 (F9)
            $killTime = null;
            $killTimestamp = null;
            foreach ($fields as $f) {
                if ($f['field'] === 9 && $f['wire'] === 2) {
                    $ts = $this->codec->getVarint($this->codec->parseMessage($f['value']), 1);
                    if ($ts && $ts > 1000000000) {
                        $killTimestamp = $ts;
                        // 转为北京时间
                        $killTime = gmdate('Y-m-d H:i:s', $ts + 8 * 3600);
                    }
                    break;
                }
            }

            // ISK 价值 (F13)
            $totalValue = $this->codec->getDouble($fields, 13);

            // ESI hash (F16)
            $esiHash = null;
            foreach ($fields as $f) {
                if ($f['field'] === 16 && $f['wire'] === 2) {
                    $hashStr = $this->codec->getString($this->codec->parseMessage($f['value']), 1);
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
    public function parseKillList(string $html): array
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

    /**
     * 从 beta KB 或旧 KB 提取 ESI hash
     */
    public function extractEsiHash(int $killId): ?string
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

    /**
     * 获取旧 KB 的 KM 详情（HTML 降级方案）
     */
    public function getKillDetailsFallback(int $killId): array
    {
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
    }

    /**
     * 解析旧 KB HTML KM 详情页
     */
    public function parseKillPageFallback(string $html, int $killId): array
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

    /**
     * 获取 KM 列表（旧 KB HTML 方式 - 用于降级）
     */
    public function fetchKbHtmlKillList(int $pilotId): array
    {
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

        return [];
    }

    // Getters
    public function getKbUrl(): string
    {
        return $this->kbUrl;
    }

    public function getBetaKbUrl(): string
    {
        return $this->betaKbUrl;
    }
}
