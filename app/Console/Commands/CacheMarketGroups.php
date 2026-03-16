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

        $this->info('Fetched ' . count($details) . ' group details. Enriching with item names...');

        // 加载物品名称数据库
        $eveData = app(EveDataService::class);
        $itemDb = $eveData->getItemDatabase();
        $this->info('Item database loaded: ' . count($itemDb) . ' entries.');

        // 为叶子分组的 types 附加名称
        $enrichedCount = 0;
        foreach ($details as $id => &$group) {
            if (!empty($group['types']) && is_array($group['types'])) {
                $namedTypes = [];
                foreach ($group['types'] as $typeId) {
                    if (is_numeric($typeId)) {
                        $name = $itemDb[(int)$typeId] ?? null;
                        $namedTypes[] = [
                            'id' => (int)$typeId,
                            'name' => $name ?? ('物品#' . $typeId),
                        ];
                    }
                }
                $group['types'] = $namedTypes;
                $enrichedCount++;
            }
        }
        unset($group);
        $this->info("Enriched {$enrichedCount} groups with item names.");

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
