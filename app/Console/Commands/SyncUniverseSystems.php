<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncUniverseSystems extends Command
{
    protected $signature = 'eve:sync-universe';
    protected $description = 'Sync all K-space solar system data (coordinates, security, region) from ESI to data/eve_systems_full.json';

    public function handle()
    {
        $baseUrl = config('esi.base_url', 'https://ali-esi.evepc.163.com/latest/');
        $datasource = config('esi.datasource', 'serenity');

        // Step 1: Fetch all system IDs
        $this->info('Fetching all solar system IDs...');
        $response = Http::timeout(30)->get($baseUrl . 'universe/systems/', [
            'datasource' => $datasource,
        ]);

        if (!$response->ok()) {
            $this->error('Failed to fetch system IDs: ' . $response->status());
            return 1;
        }

        $allIds = $response->json();
        // Filter K-space only (30000000-30999999), exclude J-space (31xxxxxx)
        $kspaceIds = array_filter($allIds, fn($id) => $id >= 30000000 && $id < 31000000);
        $kspaceIds = array_values($kspaceIds);
        $this->info('Total ESI systems: ' . count($allIds) . ', K-space systems: ' . count($kspaceIds));

        // Step 2: Batch fetch system details
        $this->info('Fetching system details...');
        $systems = [];
        $constellationIds = [];
        $batchSize = 50;
        $batches = array_chunk($kspaceIds, $batchSize);
        $bar = $this->output->createProgressBar(count($batches));
        $bar->start();

        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $datasource) {
                foreach ($batch as $id) {
                    $pool->as("sys_{$id}")
                        ->timeout(10)
                        ->get($baseUrl . "universe/systems/{$id}/", [
                            'datasource' => $datasource,
                            'language' => 'zh',
                        ]);
                }
            });

            foreach ($batch as $id) {
                $r = $responses["sys_{$id}"] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                    $d = $r->json();
                    $pos = $d['position'] ?? null;
                    if ($pos) {
                        $cid = $d['constellation_id'] ?? null;
                        $systems[$id] = [
                            'name' => $d['name'] ?? "Unknown-{$id}",
                            'x' => $pos['x'],
                            'y' => $pos['y'],
                            'z' => $pos['z'],
                            'sec' => $d['security_status'] ?? 0,
                            'cid' => $cid,
                            'rid' => null,
                            'rn' => null,
                        ];
                        if ($cid) {
                            $constellationIds[$cid] = true;
                        }
                    }
                }
            }

            $bar->advance();
            usleep(100000); // 100ms delay
        }

        $bar->finish();
        $this->newLine();
        $this->info('Fetched ' . count($systems) . ' systems with coordinates.');

        // Step 3: Fetch constellation -> region mapping
        $uniqueConstellations = array_keys($constellationIds);
        $this->info('Fetching ' . count($uniqueConstellations) . ' constellations for region mapping...');
        $constellationRegionMap = [];
        $regionIds = [];
        $batches = array_chunk($uniqueConstellations, $batchSize);
        $bar = $this->output->createProgressBar(count($batches));
        $bar->start();

        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $datasource) {
                foreach ($batch as $id) {
                    $pool->as("con_{$id}")
                        ->timeout(10)
                        ->get($baseUrl . "universe/constellations/{$id}/", [
                            'datasource' => $datasource,
                        ]);
                }
            });

            foreach ($batch as $id) {
                $r = $responses["con_{$id}"] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                    $d = $r->json();
                    $rid = $d['region_id'] ?? null;
                    if ($rid) {
                        $constellationRegionMap[$id] = $rid;
                        $regionIds[$rid] = true;
                    }
                }
            }

            $bar->advance();
            usleep(100000);
        }

        $bar->finish();
        $this->newLine();

        // Step 4: Fetch region names
        $uniqueRegions = array_keys($regionIds);
        $this->info('Fetching ' . count($uniqueRegions) . ' region names...');
        $regionNames = [];
        $batches = array_chunk($uniqueRegions, $batchSize);

        foreach ($batches as $batch) {
            $responses = Http::pool(function ($pool) use ($batch, $baseUrl, $datasource) {
                foreach ($batch as $id) {
                    $pool->as("reg_{$id}")
                        ->timeout(10)
                        ->get($baseUrl . "universe/regions/{$id}/", [
                            'datasource' => $datasource,
                            'language' => 'zh',
                        ]);
                }
            });

            foreach ($batch as $id) {
                $r = $responses["reg_{$id}"] ?? null;
                if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                    $d = $r->json();
                    $regionNames[$id] = $d['name'] ?? "Unknown-Region-{$id}";
                }
            }

            usleep(100000);
        }

        $this->info('Fetched ' . count($regionNames) . ' region names.');

        // Step 5: Assemble final data
        $this->info('Assembling final data...');
        $highSec = 0;
        $lowSec = 0;
        $nullSec = 0;

        foreach ($systems as $id => &$sys) {
            $cid = $sys['cid'];
            $rid = $constellationRegionMap[$cid] ?? null;
            $sys['rid'] = $rid;
            $sys['rn'] = $regionNames[$rid] ?? null;
            unset($sys['cid']); // Remove constellation_id from output

            $displaySec = round($sys['sec'] * 10) / 10;
            if ($displaySec >= 0.5) {
                $highSec++;
            } elseif ($displaySec > 0) {
                $lowSec++;
            } else {
                $nullSec++;
            }
        }
        unset($sys);

        // Step 6: Write to file
        $outputPath = base_path('data/eve_systems_full.json');
        $json = json_encode($systems, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($outputPath, $json);

        $fileSizeMB = round(filesize($outputPath) / 1024 / 1024, 2);
        $this->info("Written to {$outputPath} ({$fileSizeMB} MB)");
        $this->info("Total: " . count($systems) . " systems (High-sec: {$highSec}, Low-sec: {$lowSec}, Null-sec: {$nullSec})");
        $this->info("Regions: " . count($regionNames));

        return 0;
    }
}
