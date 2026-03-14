<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketService
{
    private string $baseUrl;
    private string $datasource;

    public function __construct()
    {
        $this->baseUrl = config('esi.base_url');
        $this->datasource = config('esi.datasource', 'serenity');
    }

    public function getMarketGroupsTree(): array
    {
        return Cache::remember('market_groups_tree', 86400, function () {
            try {
                $response = Http::timeout(15)->get($this->baseUrl . 'markets/groups/', [
                    'datasource' => $this->datasource,
                    'language' => 'zh',
                ]);
                if (!$response->ok()) return [];
                $allGroups = $response->json();
                $groupIds = is_array($allGroups) && isset($allGroups[0]) && is_numeric($allGroups[0])
                    ? $allGroups : array_column($allGroups, 'market_group_id');
                return $this->buildTree($this->fetchGroupDetails($groupIds));
            } catch (\Exception $e) { return []; }
        });
    }

    private function fetchGroupDetails(array $groupIds): array
    {
        $details = [];
        foreach (array_chunk($groupIds, 20) as $batch) {
            $responses = Http::pool(function ($pool) use ($batch) {
                foreach ($batch as $id) {
                    $pool->as("group_$id")->timeout(5)->get($this->baseUrl . "markets/groups/$id/", [
                        'datasource' => $this->datasource, 'language' => 'zh']);
                }
            });
            foreach ($batch as $id) {
                $r = $responses["group_$id"] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                    $d = $r->json();
                    $details[$id] = ['id' => $d['market_group_id'], 'name' => $d['name'],
                        'parent_id' => $d['parent_group_id'], 'types' => $d['types'] ?? [], 'children' => []];
                }
            }
        }
        return $details;
    }

    private function buildTree(array $groups): array
    {
        $tree = []; $lookup = [];
        foreach ($groups as $id => $group) $lookup[$id] = $group;
        foreach ($lookup as $id => &$group) {
            if ($group['parent_id'] === null) $tree[] = &$group;
            elseif (isset($lookup[$group['parent_id']])) $lookup[$group['parent_id']]['children'][] = &$group;
        }
        usort($tree, fn($a, $b) => $a['name'] <=> $b['name']);
        return $tree;
    }

    public function getOrders(int $regionId, int $typeId): array
    {
        return Cache::remember("market_orders_{$regionId}_{$typeId}", 300, function () use ($regionId, $typeId) {
            try {
                $r = Http::timeout(15)->get($this->baseUrl . "markets/$regionId/orders/", [
                    'datasource' => $this->datasource, 'type_id' => $typeId, 'page' => 1]);
                if (!$r->ok()) return ['sell' => [], 'buy' => []];
                $orders = $r->json();
                $sell = []; $buy = [];
                foreach ($orders as $o) { if ($o['is_buy_order']) $buy[] = $o; else $sell[] = $o; }
                usort($sell, fn($a, $b) => $a['price'] <=> $b['price']);
                usort($buy, fn($a, $b) => $b['price'] <=> $a['price']);
                return ['sell' => $sell, 'buy' => $buy];
            } catch (\Exception $e) { return ['sell' => [], 'buy' => []]; }
        });
    }

    public function getPriceHistory(int $regionId, int $typeId): array
    {
        return Cache::remember("market_history_{$regionId}_{$typeId}", 3600, function () use ($regionId, $typeId) {
            try {
                $r = Http::timeout(15)->get($this->baseUrl . "markets/$regionId/history/", [
                    'datasource' => $this->datasource, 'type_id' => $typeId]);
                return $r->ok() ? $r->json() : [];
            } catch (\Exception $e) { return []; }
        });
    }

    public function getTypeDetail(int $typeId): ?array
    {
        return Cache::remember("type_detail_$typeId", 86400, function () use ($typeId) {
            try {
                $r = Http::timeout(10)->get($this->baseUrl . "universe/types/$typeId/", [
                    'datasource' => $this->datasource, 'language' => 'zh']);
                return $r->ok() ? $r->json() : null;
            } catch (\Exception $e) { return null; }
        });
    }

    public function getCharacterOrders(string $token, int $charId): array
    {
        return Cache::remember("character_orders_$charId", 300, function () use ($token, $charId) {
            try {
                $r = Http::timeout(15)->withToken($token)
                    ->get($this->baseUrl . "characters/$charId/orders/", ['datasource' => $this->datasource]);
                return $r->ok() ? $r->json() : [];
            } catch (\Exception $e) { return []; }
        });
    }

    public function enrichOrdersWithLocation(array $orders): array { return $orders; }
}