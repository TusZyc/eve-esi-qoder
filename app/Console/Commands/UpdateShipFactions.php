<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PDO;

class UpdateShipFactions extends Command
{
    protected $signature = 'fitting:update-factions';
    protected $description = '通过ESI API更新舰船势力信息';

    private const ESI_BASE = 'https://ali-esi.evepc.163.com/latest';

    private const RACE_NAME_MAP = [
        'caldari' => 1,
        'gallente' => 8,
        'minmatar' => 2,
        'amarr' => 4,
    ];

    private const RACE_NAMES = [
        1 => '加达里',
        8 => '盖伦特',
        2 => '米玛塔尔',
        4 => '艾玛',
        0 => '非帝国',
    ];

    public function handle()
    {
        $dbPath = database_path('fitting.sqlite');
        if (!file_exists($dbPath)) {
            $this->error('fitting.sqlite 不存在');
            return 1;
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        
        $columns = $pdo->query('PRAGMA table_info(fitting_types)')->fetchAll(PDO::FETCH_ASSOC);
        $hasFactionId = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'faction_id') {
                $hasFactionId = true;
                break;
            }
        }

        if (!$hasFactionId) {
            $this->info('添加 faction_id 列...');
            $pdo->exec('ALTER TABLE fitting_types ADD COLUMN faction_id INTEGER');
        }

        $ships = $pdo->query('SELECT type_id, name_en FROM fitting_types WHERE category_id = 6')->fetchAll(PDO::FETCH_ASSOC);
        $this->info('共 ' . count($ships) . ' 艘舰船需要更新');

        $updateStmt = $pdo->prepare('UPDATE fitting_types SET faction_id = ? WHERE type_id = ?');
        
        $count = 0;
        $factionCounts = [1 => 0, 8 => 0, 2 => 0, 4 => 0, 0 => 0];

        foreach ($ships as $ship) {
            $typeId = $ship['type_id'];
            $factionId = $this->getFactionFromESI($typeId);
            
            $factionCounts[$factionId]++;
            $updateStmt->execute([$factionId, $typeId]);
            $count++;
            
            if ($count % 50 == 0) {
                $this->info('已处理 ' . $count . ' 艘舰船...');
            }
        }

        $this->info('');
        $this->info('更新完成！');
        foreach (self::RACE_NAMES as $id => $name) {
            $this->info($name . ': ' . $factionCounts[$id] . ' 艘');
        }
        
        return 0;
    }

    private function getFactionFromESI(int $typeId): int
    {
        try {
            $typeResponse = Http::timeout(10)->get(self::ESI_BASE . '/universe/types/' . $typeId . '/');
            
            if (!$typeResponse->successful()) {
                return 0;
            }
            
            $typeData = $typeResponse->json();
            
            if (!isset($typeData['graphic_id'])) {
                return 0;
            }
            
            $graphicId = $typeData['graphic_id'];
            
            $graphicResponse = Http::timeout(10)->get(self::ESI_BASE . '/universe/graphics/' . $graphicId . '/');
            
            if (!$graphicResponse->successful()) {
                return 0;
            }
            
            $graphicData = $graphicResponse->json();
            
            if (!isset($graphicData['sof_race_name'])) {
                return 0;
            }
            
            $raceName = strtolower($graphicData['sof_race_name']);
            
            return self::RACE_NAME_MAP[$raceName] ?? 0;
            
        } catch (\Exception $e) {
            return 0;
        }
    }
}
