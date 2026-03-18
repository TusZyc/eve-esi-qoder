<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class BuildLpFactions extends Command
{
    protected $signature = 'lp:build-factions';
    protected $description = '基于静态映射和 ESI 数据生成 data/lp_factions.json';

    private string $baseUrl;
    private string $datasource = 'serenity';
    private int $concurrency = 15;

    public function handle()
    {
        $this->baseUrl = rtrim(config('lpstore.esi_base_url', 'https://ali-esi.evepc.163.com/latest'), '/');
        
        // 1. 读取静态映射文件
        $mapPath = base_path('data/faction_corps_map.json');
        if (!file_exists($mapPath)) {
            $this->error("静态映射文件不存在: {$mapPath}");
            return 1;
        }
        
        $mapData = json_decode(file_get_contents($mapPath), true);
        if (!$mapData || !isset($mapData['factions'])) {
            $this->error('静态映射文件格式错误');
            return 1;
        }
        
        $this->info('读取到 ' . count($mapData['factions']) . ' 个势力的静态映射');
        
        // 2. 从 ESI 获取势力中文名称
        $this->info('正在从 ESI 获取势力中文名称...');
        $esiFactions = $this->fetchFactions();
        $factionNameMap = [];
        foreach ($esiFactions as $faction) {
            $factionNameMap[$faction['faction_id']] = $faction['name'];
        }
        $this->info('获取到 ' . count($factionNameMap) . ' 个势力的 ESI 数据');
        
        // 3. 收集所有需要查询的公司 ID
        $allCorpIds = [];
        foreach ($mapData['factions'] as $faction) {
            foreach ($faction['corporations'] as $corp) {
                $allCorpIds[] = $corp['id'];
            }
        }
        $this->info('需要获取 ' . count($allCorpIds) . ' 个公司的中文名称');
        
        // 4. 并发获取公司中文名称
        $this->info('正在获取公司详情...');
        $corpNameMap = $this->fetchCorporationNames($allCorpIds);
        $this->info('获取到 ' . count($corpNameMap) . ' 个公司名称');
        
        // 5. 构建最终数据
        $this->info('正在构建最终数据...');
        $result = [];
        foreach ($mapData['factions'] as $factionData) {
            $factionId = $factionData['faction_id'];
            $factionName = $factionNameMap[$factionId] ?? $factionData['name'];
            
            $corporations = [];
            foreach ($factionData['corporations'] as $corp) {
                $corpId = $corp['id'];
                $corpName = $corpNameMap[$corpId] ?? $corp['name'];
                $corporations[] = [
                    'corporation_id' => $corpId,
                    'name' => $corpName,
                ];
            }
            
            // 按公司名称排序
            usort($corporations, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            $result[] = [
                'faction_id' => $factionId,
                'faction_name' => $factionName,
                'corporations' => $corporations,
            ];
        }
        
        // 按势力名称排序
        usort($result, function ($a, $b) {
            return strcmp($a['faction_name'], $b['faction_name']);
        });
        
        // 6. 输出到文件
        $outputPath = base_path('data/lp_factions.json');
        if (!is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }
        
        $jsonContent = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($outputPath, $jsonContent);
        
        // 7. 统计信息
        $totalCorps = 0;
        foreach ($result as $faction) {
            $totalCorps += count($faction['corporations']);
        }
        
        $this->newLine();
        $this->info('========== 完成 ==========');
        $this->info('势力数量: ' . count($result));
        $this->info('公司总数: ' . $totalCorps);
        $this->info('输出文件: ' . $outputPath);
        
        return 0;
    }

    /**
     * 获取所有势力（用于获取中文名称）
     */
    private function fetchFactions(): array
    {
        try {
            $response = Http::timeout(30)->get($this->baseUrl . '/universe/factions/', [
                'datasource' => $this->datasource,
                'language' => 'zh',
            ]);

            if ($response->ok()) {
                return $response->json() ?: [];
            }

            $this->error('势力 API 返回: ' . $response->status());
            return [];
        } catch (\Exception $e) {
            $this->error('获取势力异常: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 使用 Guzzle Pool 并发获取公司名称
     */
    private function fetchCorporationNames(array $corpIds): array
    {
        $client = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);

        $corpNameMap = [];
        $batches = array_chunk($corpIds, $this->concurrency);
        $bar = $this->output->createProgressBar(count($corpIds));
        $bar->start();

        foreach ($batches as $batch) {
            $requests = function () use ($batch) {
                foreach ($batch as $corpId) {
                    $url = $this->baseUrl . '/corporations/' . $corpId . '/?datasource=' . $this->datasource . '&language=zh';
                    yield $corpId => new Request('GET', $url);
                }
            };

            $pool = new Pool($client, $requests(), [
                'concurrency' => $this->concurrency,
                'fulfilled' => function ($response, $corpId) use (&$corpNameMap, $bar) {
                    try {
                        $data = json_decode($response->getBody()->getContents(), true);
                        if ($data && isset($data['name'])) {
                            $corpNameMap[$corpId] = $data['name'];
                        }
                    } catch (\Exception $e) {
                        Log::warning("解析公司 {$corpId} 详情失败: " . $e->getMessage());
                    }
                    $bar->advance();
                },
                'rejected' => function ($reason, $corpId) use ($bar) {
                    Log::warning("获取公司 {$corpId} 详情失败: " . $reason->getMessage());
                    $bar->advance();
                },
            ]);

            $pool->promise()->wait();
            
            // 批次间短暂延迟，避免触发限流
            usleep(100000); // 100ms
        }

        $bar->finish();
        $this->newLine();

        return $corpNameMap;
    }
}
