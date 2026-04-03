<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ServerStatusController extends Controller
{
    private const SERVERS = [
        [
            'name' => '晨曦',
            'name_en' => 'Serenity',
            'url' => 'https://ali-esi.evepc.163.com/latest/status/?datasource=serenity',
        ],
        [
            'name' => '曙光',
            'name_en' => 'Infinity',
            'url' => 'https://ali-esi.evepc.163.com/latest/status/?datasource=infinity',
        ],
        [
            'name' => '欧服',
            'name_en' => 'Tranquility',
            'url' => 'https://esi.evetech.net/latest/status/?datasource=tranquility',
        ],
    ];

    public function index()
    {
        $data = Cache::remember('public_server_status', 300, function () {
            $responses = Http::pool(function ($pool) {
                foreach (self::SERVERS as $i => $server) {
                    $pool->as("server_{$i}")->timeout(5)->get($server['url']);
                }
            });

            $result = [];
            $now = time();

            foreach (self::SERVERS as $i => $server) {
                try {
                    $response = $responses["server_{$i}"] ?? null;
                    if ($response instanceof \Illuminate\Http\Client\Response && $response->ok()) {
                        $json = $response->json();
                        $startTime = $json['start_time'] ?? null;
                        $uptimeSeconds = $startTime ? max(0, $now - strtotime($startTime)) : 0;

                $result[] = [
                            'name' => $server['name'],
                            'name_en' => $server['name_en'],
                            'is_online' => true,
                            'players' => $json['players'] ?? 0,
                            'server_version' => $json['server_version'] ?? '',
                            'start_time' => $startTime,
                            'uptime_seconds' => $uptimeSeconds,
                            'is_maintenance' => ($json['vip'] ?? false) || ($json['players'] ?? 0) === 0,
                        ];
                    } else {
                        $result[] = self::offlineEntry($server);
                    }
                } catch (\Exception $e) {
                    $result[] = self::offlineEntry($server);
                }
            }

            return $result;
        });

        return response()->json($data);
    }

    private static function offlineEntry(array $server): array
    {
        return [
            'name' => $server['name'],
            'name_en' => $server['name_en'],
            'is_online' => false,
            'players' => 0,
            'server_version' => '',
            'start_time' => null,
            'uptime_seconds' => 0,
            'is_maintenance' => false,
        ];
    }
}
