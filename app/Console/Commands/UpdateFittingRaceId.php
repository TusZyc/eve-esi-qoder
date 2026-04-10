<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PDO;

class UpdateFittingRaceId extends Command
{
    protected $signature = 'fitting:update-race';
    protected $description = '更新fitting_groups表的race_id字段';

    // 势力ID映射（基于group_id的前缀规则）
    private const GROUP_RACE_MAP = [
        // 加达里 (race_id = 1)
        'Caldari' => 1,
        // 米玛塔尔 (race_id = 2)
        'Minmatar' => 2,
        // 艾玛 (race_id = 4)
        'Amarr' => 4,
        // 盖伦特 (race_id = 8)
        'Gallente' => 8,
    ];

    public function handle()
    {
        $dbPath = database_path('fitting.sqlite');
        if (!file_exists($dbPath)) {
            $this->error('fitting.sqlite 不存在');
            return 1;
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        
        // 检查race_id列是否存在
        $columns = $pdo->query("PRAGMA table_info(fitting_groups)")->fetchAll(PDO::FETCH_ASSOC);
        $hasRaceId = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'race_id') {
                $hasRaceId = true;
                break;
            }
        }

        if (!$hasRaceId) {
            $this->info('添加 race_id 列...');
            $pdo->exec('ALTER TABLE fitting_groups ADD COLUMN race_id INTEGER');
        }

        // 获取所有groups
        $groups = $pdo->query("SELECT group_id, name_en FROM fitting_groups WHERE category_id = 6")->fetchAll(PDO::FETCH_ASSOC);
        
        $this->info('更新舰船group的race_id...');
        $updateStmt = $pdo->prepare('UPDATE fitting_groups SET race_id = ? WHERE group_id = ?');
        
        foreach ($groups as $group) {
            $name = $group['name_en'];
            $raceId = $this->determineRaceId($name);
            $updateStmt->execute([$raceId, $group['group_id']]);
        }

        // 添加舰船的race_id（从group继承）
        $this->info('更新舰船type的race_id...');
        
        // 检查fitting_types是否有race_id
        $typeColumns = $pdo->query("PRAGMA table_info(fitting_types)")->fetchAll(PDO::FETCH_ASSOC);
        $typeHasRaceId = false;
        foreach ($typeColumns as $col) {
            if ($col['name'] === 'race_id') {
                $typeHasRaceId = true;
                break;
            }
        }

        if (!$typeHasRaceId) {
            $pdo->exec('ALTER TABLE fitting_types ADD COLUMN race_id INTEGER');
        }

        // 从group继承race_id
        $pdo->exec('UPDATE fitting_types SET race_id = (SELECT race_id FROM fitting_groups WHERE fitting_groups.group_id = fitting_types.group_id)');

        $this->info('完成！');
        return 0;
    }

    private function determineRaceId(string $name): ?int
    {
        // 根据名称前缀判断势力
        foreach (self::GROUP_RACE_MAP as $prefix => $raceId) {
            if (strpos($name, $prefix) !== false) {
                return $raceId;
            }
        }
        
        // 特殊处理
        if (strpos($name, 'Caldari') !== false) return 1;
        if (strpos($name, 'Minmatar') !== false) return 2;
        if (strpos($name, 'Amarr') !== false) return 4;
        if (strpos($name, 'Gallente') !== false) return 8;
        
        // 特殊势力舰船
        if (strpos($name, 'Triglavian') !== false) return 128; // 三神裔
        if (strpos($name, 'Polar') !== false) return 255; // 极地
        if (strpos($name, 'ORE') !== false) return 136; // ORE
        if (strpos($name, 'Serpentis') !== false) return 16; // 天蛇
        if (strpos($name, 'Angel') !== false) return 32; // 天使
        if (strpos($name, 'Blood') !== false) return 64; // 血袭者
        if (strpos($name, 'Sansha') !== false) return 48; // 萨沙
        if (strpos($name, 'Guristas') !== false) return 80; // 古斯塔斯
        
        return null; // 非帝国
    }
}