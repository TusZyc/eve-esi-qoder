<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KillmailService
{
    protected $kbUrl = 'https://kb.ceve-market.org';

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
     * 搜索角色
     */
    public function searchCharacter(string $query): array
    {
        return Cache::remember("kb:search:" . md5($query), 300, function () use ($query) {
            $results = [];

            // 方案 1: 尝试 KB 搜索
            try {
                $results = $this->searchKB($query);
            } catch (\Exception $e) {
                // KB 搜索失败, 忽略
            }

            // 方案 2: 搜索本地用户数据库
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
     * 通过 KB 网搜索角色
     */
    protected function searchKB(string $query): array
    {
        // 尝试 POST 到 ajax_search
        $response = Http::withHeaders($this->kbHeaders())
            ->asForm()
            ->timeout(10)
            ->post("{$this->kbUrl}/ajax_search/", [
                'search_type' => '角色',
                'name' => $query,
            ]);

        if (!$response->ok()) {
            // 尝试 GET 方式
            $response = Http::withHeaders($this->kbHeaders())
                ->timeout(10)
                ->get("{$this->kbUrl}/ajax_search/", [
                    'search_type' => '角色',
                    'name' => $query,
                ]);
        }

        if (!$response->ok()) {
            return [];
        }

        $html = $response->body();
        return $this->parsePilotLinks($html);
    }

    /**
     * 从 HTML 中解析角色链接
     */
    protected function parsePilotLinks(string $html): array
    {
        $results = [];
        $seen = [];

        // 匹配 /pilot/{id}/ 链接及其文本内容
        if (preg_match_all('/<a[^>]*href="\/pilot\/(\d+)\/"[^>]*>(.*?)<\/a>/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $pilotId = (int) $m[1];
                if (isset($seen[$pilotId]) || $pilotId <= 0) continue;
                $seen[$pilotId] = true;

                // 清理名称: 去掉 HTML 标签
                $name = trim(strip_tags($m[2]));
                if (empty($name) || $name === '未知') continue;

                $results[] = [
                    'pilot_id' => $pilotId,
                    'name' => $name,
                ];

                if (count($results) >= 20) break;
            }
        }

        // 也尝试匹配 data-alt 属性中的名称
        if (empty($results) && preg_match_all('/href="\/pilot\/(\d+)\/".*?data-alt="([^"]+)"/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $pilotId = (int) $m[1];
                if (isset($seen[$pilotId]) || $pilotId <= 0) continue;
                $seen[$pilotId] = true;

                $results[] = [
                    'pilot_id' => $pilotId,
                    'name' => html_entity_decode($m[2]),
                ];

                if (count($results) >= 20) break;
            }
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

    /**
     * 获取角色 KM 列表
     */
    public function getPilotKills(int $pilotId, string $mode = 'kills'): array
    {
        $cacheKey = "kb:pilot:{$pilotId}:{$mode}";

        return Cache::remember($cacheKey, 600, function () use ($pilotId, $mode) {
            // 尝试爬取 KB 角色页面
            $url = "{$this->kbUrl}/pilot/{$pilotId}/";

            $response = Http::withHeaders($this->kbHeaders())
                ->timeout(15)
                ->get($url);

            if (!$response->ok()) {
                return [];
            }

            $html = $response->body();

            // 检查是否为错误页面
            if (strpos($html, '世界线已发生混乱') !== false || strpos($html, '404') !== false) {
                return [];
            }

            return $this->parseKillList($html);
        });
    }

    /**
     * 从 HTML 中解析 KM 列表
     */
    protected function parseKillList(string $html): array
    {
        $kills = [];
        $seen = [];

        // 匹配 /kill/{id}/ 链接
        if (preg_match_all('/<a[^>]*href="\/kill\/(\d+)\/"[^>]*>/si', $html, $matches)) {
            foreach ($matches[1] as $killId) {
                $killId = (int) $killId;
                if (isset($seen[$killId]) || $killId <= 0) continue;
                $seen[$killId] = true;

                $kill = [
                    'kill_id' => $killId,
                    'victim_name' => null,
                    'ship_name' => null,
                    'system_name' => null,
                    'kill_time' => null,
                ];

                $kills[] = $kill;
            }
        }

        return $kills;
    }

    /**
     * 获取 KM 详情
     */
    public function getKillDetails(int $killId): array
    {
        $cacheKey = "kb:kill:{$killId}";

        return Cache::remember($cacheKey, 3600, function () use ($killId) {
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

            return $this->parseKillPage($html, $killId);
        });
    }

    /**
     * 解析 KM 详情页面 HTML
     */
    protected function parseKillPage(string $html, int $killId): array
    {
        $data = [
            'kill_id' => $killId,
            'victim_name' => null,
            'victim_corp' => null,
            'victim_alliance' => null,
            'ship_name' => null,
            'system_name' => null,
            'region_name' => null,
            'kill_time' => null,
            'damage_taken' => 0,
            'total_value' => 0,
            'attacker_count' => 0,
            'attackers' => [],
            'items_dropped' => [],
            'items_destroyed' => [],
        ];

        // 受害人名称 - 从 data-alt 属性或 filtered_name 类中提取
        if (preg_match('/受害人.*?href="\/pilot\/\d+\/"[^>]*>.*?data-alt="([^"]+)"/si', $html, $m)) {
            $data['victim_name'] = html_entity_decode($m[1]);
        } elseif (preg_match('/受害人.*?href="\/pilot\/\d+\/"[^>]*>\s*<span[^>]*>(?:<span>)?([^<]+)/si', $html, $m)) {
            $data['victim_name'] = trim(strip_tags($m[1]));
        }

        // 军团
        if (preg_match('/受害人.*?军团.*?href="\/corp\/\d+[^"]*"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim_corp'] = trim($m[1]);
        }

        // 联盟
        if (preg_match('/受害人.*?联盟.*?href="\/alliance\/\d+[^"]*"[^>]*>([^<]+)/si', $html, $m)) {
            $data['victim_alliance'] = trim($m[1]);
        }

        // 舰船名称 - 从 invtype 链接提取
        if (preg_match('/舰船.*?href="\/invtype\/\d+\/"[^>]*>([^<]+)/si', $html, $m)) {
            $data['ship_name'] = trim($m[1]);
        } elseif (preg_match('/<title>([^<]*?)在.*?损失的\s*([^<]+)/u', $html, $m)) {
            $data['ship_name'] = trim($m[2]);
        }

        // 星系
        if (preg_match('/href="\/system\/\d+\/"[^>]*>([^<]+)/si', $html, $m)) {
            $data['system_name'] = trim($m[1]);
        }

        // 星域
        if (preg_match('/(\S+)\s*星域/u', $html, $m)) {
            $data['region_name'] = trim($m[1]);
        }

        // 时间
        if (preg_match('/时间[：:]\s*(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/u', $html, $m)) {
            $data['kill_time'] = $m[1];
        }

        // 所受伤害
        if (preg_match('/所受伤害[：:]\s*([\d,]+)/u', $html, $m)) {
            $data['damage_taken'] = (int) str_replace(',', '', $m[1]);
        }

        // 损失价值
        if (preg_match('/损失价值[：:]\s*([\d,.]+)\s*ISK/ui', $html, $m)) {
            $data['total_value'] = (float) str_replace(',', '', $m[1]);
        } elseif (preg_match('/([\d,.]+)\s*亿\s*ISK/u', $html, $m)) {
            $data['total_value'] = (float) str_replace(',', '', $m[1]) * 100000000;
        }

        // 参与者数量
        if (preg_match('/参与者\s*\(?\s*(\d+)\s*\)?/u', $html, $m)) {
            $data['attacker_count'] = (int) $m[1];
        }

        // 解析最后一击
        if (preg_match('/最后一击.*?href="\/pilot\/(\d+)\/"[^>]*>(.*?)<\/a>/si', $html, $m)) {
            $attackerName = trim(strip_tags($m[2]));
            // 也尝试 data-alt
            if (preg_match('/最后一击.*?data-alt="([^"]+)"/si', $html, $m2)) {
                $attackerName = html_entity_decode($m2[1]);
            }

            $attackerShip = '';
            if (preg_match('/最后一击.*?title="([^"]+)"[^>]*>/si', $html, $m3)) {
                $attackerShip = $m3[1];
            }

            $data['attackers'][] = [
                'name' => $attackerName,
                'ship' => $attackerShip,
                'damage' => 0,
                'final_blow' => true,
            ];
        }

        // 解析参与者列表 - 匹配 "造成伤害" 模式
        if (preg_match_all('/href="\/pilot\/(\d+)\/"[^>]*>.*?(?:data-alt="([^"]*)".*?)?<\/a>.*?造成伤害\s*:\s*([\d,]+)\s*\(([0-9.]+)%\)/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $name = !empty($m[2]) ? html_entity_decode($m[2]) : "Pilot #{$m[1]}";
                $data['attackers'][] = [
                    'name' => $name,
                    'ship' => '',
                    'damage' => (int) str_replace(',', '', $m[3]),
                    'final_blow' => false,
                ];
            }
        }

        // 解析物品 - 掉落 (绿色背景 #006000)
        if (preg_match_all('/<tr[^>]*style="[^"]*background-color:\s*#006000[^"]*"[^>]*>.*?title="([^"]+)".*?<\/tr>/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $itemName = html_entity_decode($m[1]);
                $qty = 1;
                if (preg_match('/<td[^>]*>\s*(\d+)\s*<\/td>/si', $m[0], $qm)) {
                    $qty = (int) $qm[1];
                }
                $data['items_dropped'][] = [
                    'name' => $itemName,
                    'qty' => $qty,
                ];
            }
        }

        // 解析物品 - 损毁 (含 destroyed.png)
        if (preg_match_all('/<tr[^>]*>(?=.*destroyed\.png).*?title="([^"]+)".*?<\/tr>/si', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $itemName = html_entity_decode($m[1]);
                $qty = 1;
                if (preg_match('/<td[^>]*>\s*(\d+)\s*<\/td>/si', $m[0], $qm)) {
                    $qty = (int) $qm[1];
                }
                $data['items_destroyed'][] = [
                    'name' => $itemName,
                    'qty' => $qty,
                ];
            }
        }

        return $data;
    }
}
