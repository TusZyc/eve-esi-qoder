<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\EveDataService;

class CacheMarketGroups extends Command
{
    protected $signature = 'market:cache-groups';
    protected $description = 'Fetch and cache all market group details (with item names) and region list';

    public function handle()
    {
        $baseUrl = config('esi.base_url');
        $datasource = config('esi.datasource', 'serenity');

        // ====== 第一部分：缓存市场分组树 ======
        $this->info('Fetching market group IDs...');
        $response = Http::timeout(15)->get($baseUrl . 'markets/groups/', [
            'datasource' => $datasource,
            'language' => 'zh',
        ]);

        if (!$response->ok()) {
            $this->error('Failed to fetch market groups: ' . $response->status());
            return 1;
        }

        $allGroupIds = $response->json();
        $this->info('Found ' . count($allGroupIds) . ' market groups.');

        $details = [];
        $batchSize = 50;
        $batches = array_chunk($allGroupIds, $batchSize);
        $bar = $this->output->createProgressBar(count($batches));
        $bar->start();

        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $datasource) {
                foreach ($batch as $id) {
                    $pool->as("group_{$id}")
                        ->timeout(10)
                        ->get($baseUrl . "markets/groups/{$id}/", [
                            'datasource' => $datasource,
                            'language' => 'zh',
                        ]);
                }
            });

            foreach ($batch as $id) {
                $r = $responses["group_{$id}"] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                    $d = $r->json();
                    $details[$id] = [
                        'id' => $d['market_group_id'],
                        'name' => $d['name'],
                        'parent_id' => $d['parent_group_id'] ?? null,
                        'types' => $d['types'] ?? [],
                        'children' => [],
                    ];
                }
            }

            $bar->advance();
            usleep(100000); // 100ms delay between batches
        }

        $bar->finish();
        $this->newLine();

        $this->info('Fetched ' . count($details) . ' group details.');

        // 收集所有 type_ids
        $allTypeIds = [];
        foreach ($details as $group) {
            if (!empty($group['types']) && is_array($group['types'])) {
                foreach ($group['types'] as $typeId) {
                    if (is_numeric($typeId)) {
                        $allTypeIds[] = (int)$typeId;
                    }
                }
            }
        }
        $allTypeIds = array_unique($allTypeIds);
        $this->info('Found ' . count($allTypeIds) . ' unique type IDs. Checking published status...');

        // 批量查询 type 的 published 状态
        $publishedTypes = [];
        $typeBatches = array_chunk($allTypeIds, 50);
        $barPub = $this->output->createProgressBar(count($typeBatches));
        $barPub->start();

        foreach ($typeBatches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $datasource) {
                foreach ($batch as $typeId) {
                    $pool->as("type_{$typeId}")
                        ->timeout(10)
                        ->get($baseUrl . "universe/types/{$typeId}/", [
                            'datasource' => $datasource,
                            'language' => 'zh',
                        ]);
                }
            });

            foreach ($batch as $typeId) {
                $r = $responses["type_{$typeId}"] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                    $d = $r->json();
                    if (!empty($d['published'])) {
                        $publishedTypes[$typeId] = [
                            'name' => $d['name'] ?? null,
                        ];
                    }
                }
            }

            $barPub->advance();
            usleep(100000); // 100ms delay
        }

        $barPub->finish();
        $this->newLine();
        $this->info('Published types: ' . count($publishedTypes) . ' / ' . count($allTypeIds));

        // 加载物品名称数据库
        $eveData = app(EveDataService::class);
        $itemDb = $eveData->getItemDatabase();
        $this->info('Item database loaded: ' . count($itemDb) . ' entries.');

        // 为叶子分组的 types 附加名称，并过滤掉未发布的物品
        $enrichedCount = 0;
        $filteredCount = 0;
        foreach ($details as $id => &$group) {
            if (!empty($group['types']) && is_array($group['types'])) {
                $namedTypes = [];
                foreach ($group['types'] as $typeId) {
                    if (is_numeric($typeId)) {
                        $tid = (int)$typeId;
                        // 只保留已发布的物品
                        if (!isset($publishedTypes[$tid])) {
                            $filteredCount++;
                            continue;
                        }
                        // 优先使用 ESI 返回的名称，其次使用本地数据库
                        $name = $publishedTypes[$tid]['name'] ?? $itemDb[$tid] ?? ('物品#' . $tid);
                        $namedTypes[] = [
                            'id' => $tid,
                            'name' => $name,
                        ];
                    }
                }
                $group['types'] = $namedTypes;
                if (!empty($namedTypes)) {
                    $enrichedCount++;
                }
            }
        }
        unset($group);
        $this->info("Enriched {$enrichedCount} groups with item names. Filtered {$filteredCount} unpublished items.");

        // Build tree
        $this->info('Building tree...');
        $tree = [];
        $lookup = [];
        foreach ($details as $id => $group) {
            $lookup[$id] = $group;
        }
        foreach ($lookup as $id => &$group) {
            if ($group['parent_id'] === null) {
                $tree[] = &$group;
            } elseif (isset($lookup[$group['parent_id']])) {
                $lookup[$group['parent_id']]['children'][] = &$group;
            }
        }
        usort($tree, fn($a, $b) => $a['name'] <=> $b['name']);

        Cache::put('market_groups_tree', $tree, 86400 * 7);
        $this->info('Market groups tree cached! (' . count($tree) . ' top-level groups)');

        // ====== 第二部分：缓存星域列表 ======
        $this->newLine();
        $this->info('Fetching region list...');

        try {
            $regionResp = Http::timeout(15)->get($baseUrl . 'universe/regions/', [
                'datasource' => $datasource,
            ]);

            if (!$regionResp->ok()) {
                $this->error('Failed to fetch regions: ' . $regionResp->status());
                return 0; // 分组已成功，星域失败不算完全失败
            }

            $regionIds = $regionResp->json();
            $this->info('Found ' . count($regionIds) . ' regions. Fetching names...');

            $regions = [];
            $regionBatches = array_chunk($regionIds, 30);
            $bar2 = $this->output->createProgressBar(count($regionBatches));
            $bar2->start();

            foreach ($regionBatches as $batch) {
                $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $datasource) {
                    foreach ($batch as $rid) {
                        $pool->as("region_{$rid}")
                            ->timeout(10)
                            ->get($baseUrl . "universe/regions/{$rid}/", [
                                'datasource' => $datasource,
                                'language' => 'zh',
                            ]);
                    }
                });

                foreach ($batch as $rid) {
                    $r = $responses["region_{$rid}"] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                        $d = $r->json();
                        
                        // 过滤掉特殊区域（虫洞、深渊、未开放区域等）
                        // 只保留 K-space 星域 (10000001-10000070) 和赞颂之域 (10001000)
                        if ($rid >= 11000000) {
                            // 排除虫洞(11xxxxx)、深渊(12xxxxx)、特殊区域(14xxxxx, 15xxxxx, 19xxxxx)
                            continue;
                        }
                        
                        $regions[] = [
                            'id' => $d['region_id'],
                            'name' => $d['name'],
                        ];
                    }
                }

                $bar2->advance();
                usleep(100000);
            }

            $bar2->finish();
            $this->newLine();

            usort($regions, fn($a, $b) => $a['name'] <=> $b['name']);
            Cache::put('market_regions_list', $regions, 86400 * 7);
            $this->info('Region list cached! (' . count($regions) . ' regions)');

        } catch (\Exception $e) {
            $this->error('Region caching failed: ' . $e->getMessage());
        }

        return 0;
    }
}
