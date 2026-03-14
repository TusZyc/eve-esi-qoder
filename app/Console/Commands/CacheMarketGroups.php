<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CacheMarketGroups extends Command
{
    protected $signature = 'market:cache-groups';
    protected $description = 'Fetch and cache all market group details for the market tree';

    public function handle()
    {
        $baseUrl = config('esi.base_url');
        $datasource = config('esi.datasource', 'serenity');

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

        $this->info('Fetched ' . count($details) . ' group details. Building tree...');

        // Build tree
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

        Cache::put('market_groups_tree', $tree, 86400);
        $this->info('Market groups tree cached successfully! (' . count($tree) . ' top-level groups)');

        return 0;
    }
}