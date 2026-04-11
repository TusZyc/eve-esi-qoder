<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

class BuildOfficialFittingSubset extends Command
{
    protected $signature = 'fitting:build-official-subset
        {--source= : Official SDE zip file or extracted directory}
        {--output= : Output sqlite path, default database/fitting_official.sqlite}
        {--force : Overwrite existing output file}';

    protected $description = 'Build a fitting-first sqlite subset from the official SDE JSONL files';

    private const INCLUDED_CATEGORY_IDS = [6, 7, 8, 16, 18, 24, 32];

    private const EXCLUDED_GROUP_IDS = [353, 1306];

    private const GROUP_SLOT_MAP = [
        53 => 'high',
        55 => 'high',
        74 => 'high',
    ];

    private const SLOT_CATEGORY_MAP = [
        '采集设备' => 'high',
        '船体和装甲' => 'low',
        '电子学和感应器升级' => 'low',
        '电子战' => 'med',
        '工程装备' => 'low',
        '护盾' => 'med',
        '舰队辅助装备' => 'high',
        '立体炸弹' => 'high',
        '炮台和发射器' => 'high',
        '扫描设备' => 'med',
        '推进器' => 'med',
        '无人机升级模块' => 'low',
        '压缩装置' => 'high',
        '建筑' => 'high',
        '改装件' => 'rig',
        '子系统' => 'subsystem',
        '无人机' => 'drone',
    ];

    public function handle(): int
    {
        set_time_limit(0);

        $source = $this->resolveSourcePath($this->option('source'));
        $output = $this->option('output') ?: database_path('fitting_official.sqlite');

        if (!$source) {
            $this->error('Official SDE source not found. Please pass --source.');
            return self::FAILURE;
        }

        if (file_exists($output) && !$this->option('force')) {
            $this->error("Output already exists: {$output}. Use --force to overwrite.");
            return self::FAILURE;
        }

        $this->info('=== Build fitting subset from official SDE ===');
        $this->line("Source: {$source}");
        $this->line("Output: {$output}");

        $localItems = $this->loadLocalItems();
        $attributeDefinitions = $this->loadDefinitionMap($source, 'dogmaAttributes.jsonl');
        $effectDefinitions = $this->loadDefinitionMap($source, 'dogmaEffects.jsonl');

        $this->createDatabase($output);
        $pdo = $this->connectSqlite($output);

        $this->importCategories($pdo, $source);
        $groupCategoryMap = $this->importGroups($pdo, $source);
        [$allowedTypes, $usedMarketGroups, $typeCategoryMap, $typeGroupMap] = $this->importTypes($pdo, $source, $groupCategoryMap, $localItems);
        $this->importMarketGroups($pdo, $source, $usedMarketGroups);

        [$usedAttributeIds, $usedEffectIds, $typeUpdates] = $this->importDogma(
            $pdo,
            $source,
            $allowedTypes,
            $typeCategoryMap,
            $typeGroupMap,
            $localItems
        );

        $this->importAttributeDefinitions($pdo, $attributeDefinitions, $usedAttributeIds);
        $this->importEffectDefinitions($pdo, $effectDefinitions, $usedEffectIds);
        $this->applyTypeUpdates($pdo, $typeUpdates);
        $this->applyFinalSlotFallbacks($pdo);
        $this->writeBuildMeta($pdo, $source);

        $this->showStats($pdo, $output);
        $this->info('=== Build complete ===');

        return self::SUCCESS;
    }

    private function resolveSourcePath(?string $option): ?string
    {
        $candidates = array_filter([
            $option,
            base_path('../official-sde/eve-online-static-data-latest-jsonl.zip'),
            storage_path('app/sde/eve-online-static-data-latest-jsonl.zip'),
            storage_path('app/sde/official/eve-online-static-data-latest-jsonl.zip'),
            storage_path('app/sde/jsonl'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate) || is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function loadLocalItems(): array
    {
        $path = base_path('data/eve_items.json');
        if (!file_exists($path)) {
            $this->warn('data/eve_items.json not found, local category hints disabled.');
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function createDatabase(string $output): void
    {
        if (file_exists($output)) {
            unlink($output);
        }

        $dir = dirname($output);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = $this->connectSqlite($output);
        $pdo->exec("
            PRAGMA journal_mode = DELETE;
            PRAGMA synchronous = OFF;
            PRAGMA temp_store = MEMORY;

            CREATE TABLE fitting_categories (
                category_id INTEGER PRIMARY KEY,
                name_en TEXT,
                name_cn TEXT,
                published INTEGER DEFAULT 1
            );

            CREATE TABLE fitting_groups (
                group_id INTEGER PRIMARY KEY,
                category_id INTEGER NOT NULL,
                name_en TEXT,
                name_cn TEXT,
                published INTEGER DEFAULT 1
            );
            CREATE INDEX idx_fitting_groups_category ON fitting_groups(category_id);

            CREATE TABLE fitting_market_groups (
                market_group_id INTEGER PRIMARY KEY,
                parent_group_id INTEGER,
                name_en TEXT,
                name_cn TEXT,
                has_types INTEGER DEFAULT 0
            );
            CREATE INDEX idx_fitting_market_groups_parent ON fitting_market_groups(parent_group_id);

            CREATE TABLE fitting_types (
                type_id INTEGER PRIMARY KEY,
                group_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                market_group_id INTEGER,
                name_en TEXT NOT NULL,
                name_cn TEXT,
                published INTEGER DEFAULT 1,
                mass REAL,
                volume REAL,
                capacity REAL,
                portion_size INTEGER,
                radius REAL,
                icon_id INTEGER,
                meta_level REAL,
                slot_type TEXT,
                slot_source TEXT,
                local_category_path_json TEXT,
                local_category_1 TEXT,
                local_category_2 TEXT,
                local_category_3 TEXT,
                local_category_4 TEXT,
                local_category_5 TEXT
            );
            CREATE INDEX idx_fitting_types_group ON fitting_types(group_id);
            CREATE INDEX idx_fitting_types_category ON fitting_types(category_id);
            CREATE INDEX idx_fitting_types_market_group ON fitting_types(market_group_id);
            CREATE INDEX idx_fitting_types_slot_type ON fitting_types(slot_type);

            CREATE TABLE fitting_attributes (
                type_id INTEGER NOT NULL,
                attribute_id INTEGER NOT NULL,
                value REAL,
                PRIMARY KEY (type_id, attribute_id)
            );
            CREATE INDEX idx_fitting_attributes_attr ON fitting_attributes(attribute_id);

            CREATE TABLE fitting_type_effects (
                type_id INTEGER NOT NULL,
                effect_id INTEGER NOT NULL,
                is_default INTEGER DEFAULT 0,
                PRIMARY KEY (type_id, effect_id)
            );
            CREATE INDEX idx_fitting_type_effects_effect ON fitting_type_effects(effect_id);

            CREATE TABLE fitting_attribute_types (
                attribute_id INTEGER PRIMARY KEY,
                name TEXT,
                display_name_en TEXT,
                display_name_cn TEXT,
                unit_id INTEGER,
                stackable INTEGER DEFAULT 1,
                high_is_good INTEGER DEFAULT 1,
                published INTEGER DEFAULT 1
            );

            CREATE TABLE fitting_effects (
                effect_id INTEGER PRIMARY KEY,
                name TEXT,
                effect_category_id INTEGER,
                is_assistance INTEGER DEFAULT 0,
                is_offensive INTEGER DEFAULT 0,
                published INTEGER DEFAULT 1
            );

            CREATE TABLE fitting_build_meta (
                meta_key TEXT PRIMARY KEY,
                meta_value TEXT
            );
        ");
    }

    private function connectSqlite(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function loadDefinitionMap(string $source, string $entryName): array
    {
        $result = [];

        $this->readJsonl($source, $entryName, function (array $row) use (&$result): void {
            $result[(int) ($row['_key'] ?? 0)] = $row;
        });

        return $result;
    }

    private function importCategories(PDO $pdo, string $source): void
    {
        $this->info('Import categories...');

        $insert = $pdo->prepare(
            'INSERT INTO fitting_categories (category_id, name_en, name_cn, published) VALUES (?, ?, ?, ?)'
        );

        $count = 0;
        $pdo->beginTransaction();
        try {
            $this->readJsonl($source, 'categories.jsonl', function (array $row) use ($insert, &$count): void {
                $categoryId = (int) ($row['_key'] ?? 0);
                if (!in_array($categoryId, self::INCLUDED_CATEGORY_IDS, true)) {
                    return;
                }

                $insert->execute([
                    $categoryId,
                    $row['name']['en'] ?? null,
                    $row['name']['zh'] ?? ($row['name']['zh-cn'] ?? null),
                    !empty($row['published']) ? 1 : 0,
                ]);
                $count++;
            });
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Categories: {$count}");
    }

    private function importGroups(PDO $pdo, string $source): array
    {
        $this->info('Import groups...');

        $groupCategoryMap = [];
        $insert = $pdo->prepare(
            'INSERT INTO fitting_groups (group_id, category_id, name_en, name_cn, published) VALUES (?, ?, ?, ?, ?)'
        );

        $count = 0;
        $pdo->beginTransaction();
        try {
            $this->readJsonl($source, 'groups.jsonl', function (array $row) use ($insert, &$groupCategoryMap, &$count): void {
                $categoryId = (int) ($row['categoryID'] ?? 0);
                if (!in_array($categoryId, self::INCLUDED_CATEGORY_IDS, true)) {
                    return;
                }

                $groupId = (int) ($row['_key'] ?? 0);
                $groupCategoryMap[$groupId] = $categoryId;

                $insert->execute([
                    $groupId,
                    $categoryId,
                    $row['name']['en'] ?? null,
                    $row['name']['zh'] ?? ($row['name']['zh-cn'] ?? null),
                    !empty($row['published']) ? 1 : 0,
                ]);
                $count++;
            });
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Groups: {$count}");

        return $groupCategoryMap;
    }

    private function importTypes(PDO $pdo, string $source, array $groupCategoryMap, array $localItems): array
    {
        $this->info('Import types...');

        $allowedTypes = [];
        $usedMarketGroups = [];
        $typeCategoryMap = [];
        $typeGroupMap = [];

        $insert = $pdo->prepare('
            INSERT INTO fitting_types (
                type_id, group_id, category_id, market_group_id, name_en, name_cn, published,
                mass, volume, capacity, portion_size, radius, icon_id, slot_type, slot_source,
                local_category_path_json, local_category_1, local_category_2, local_category_3,
                local_category_4, local_category_5
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $count = 0;
        $pdo->beginTransaction();
        try {
            $this->readJsonl($source, 'types.jsonl', function (array $row) use (
                $insert,
                $groupCategoryMap,
                $localItems,
                &$allowedTypes,
                &$usedMarketGroups,
                &$typeCategoryMap,
                &$count
            ): void {
                $groupId = (int) ($row['groupID'] ?? 0);
                if (in_array($groupId, self::EXCLUDED_GROUP_IDS, true)) {
                    return;
                }

                $categoryId = $groupCategoryMap[$groupId] ?? null;
                if ($categoryId === null) {
                    return;
                }

                $typeId = (int) ($row['_key'] ?? 0);
                $allowedTypes[$typeId] = true;
                $typeCategoryMap[$typeId] = $categoryId;
                $typeGroupMap[$typeId] = $groupId;

                $localItem = $localItems[(string) $typeId] ?? null;
                $localCategory = is_array($localItem['category'] ?? null) ? $localItem['category'] : [];
                $marketGroupId = isset($row['marketGroupID']) ? (int) $row['marketGroupID'] : null;
                $initialSlot = $this->inferSlotFromTypeCategory($localCategory, $categoryId, $groupId);
                $initialSlotSource = $this->inferInitialSlotSource($localCategory, $categoryId, $groupId, $initialSlot);

                if ($marketGroupId) {
                    $usedMarketGroups[$marketGroupId] = true;
                }

                $insert->execute([
                    $typeId,
                    $groupId,
                    $categoryId,
                    $marketGroupId,
                    $row['name']['en'] ?? null,
                    $localItem['name'] ?? ($row['name']['zh'] ?? ($row['name']['zh-cn'] ?? null)),
                    !empty($row['published']) ? 1 : 0,
                    $row['mass'] ?? null,
                    $row['volume'] ?? null,
                    $row['capacity'] ?? null,
                    $row['portionSize'] ?? null,
                    $row['radius'] ?? null,
                    $row['iconID'] ?? null,
                    $initialSlot,
                    $initialSlotSource,
                    !empty($localCategory) ? json_encode($localCategory, JSON_UNESCAPED_UNICODE) : null,
                    $localCategory[0] ?? null,
                    $localCategory[1] ?? null,
                    $localCategory[2] ?? null,
                    $localCategory[3] ?? null,
                    $localCategory[4] ?? null,
                ]);
                $count++;
            });
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Types: {$count}");

        return [$allowedTypes, $usedMarketGroups, $typeCategoryMap, $typeGroupMap];
    }

    private function importMarketGroups(PDO $pdo, string $source, array $usedMarketGroups): void
    {
        $this->info('Import market groups...');

        $allGroups = [];
        $this->readJsonl($source, 'marketGroups.jsonl', function (array $row) use (&$allGroups): void {
            $allGroups[(int) ($row['_key'] ?? 0)] = $row;
        });

        $required = [];
        foreach (array_keys($usedMarketGroups) as $marketGroupId) {
            $current = $marketGroupId;
            while ($current && isset($allGroups[$current]) && !isset($required[$current])) {
                $required[$current] = true;
                $current = (int) ($allGroups[$current]['parentGroupID'] ?? 0);
            }
        }

        $insert = $pdo->prepare(
            'INSERT INTO fitting_market_groups (market_group_id, parent_group_id, name_en, name_cn, has_types) VALUES (?, ?, ?, ?, ?)'
        );

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach (array_keys($required) as $marketGroupId) {
                $row = $allGroups[$marketGroupId];
                $insert->execute([
                    $marketGroupId,
                    isset($row['parentGroupID']) ? (int) $row['parentGroupID'] : null,
                    $row['name']['en'] ?? null,
                    $row['name']['zh'] ?? ($row['name']['zh-cn'] ?? null),
                    !empty($row['hasTypes']) ? 1 : 0,
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Market groups: {$count}");
    }

    private function importDogma(
        PDO $pdo,
        string $source,
        array $allowedTypes,
        array $typeCategoryMap,
        array $typeGroupMap,
        array $localItems
    ): array {
        $this->info('Import dogma...');

        $attrInsert = $pdo->prepare('INSERT INTO fitting_attributes (type_id, attribute_id, value) VALUES (?, ?, ?)');
        $effectInsert = $pdo->prepare('INSERT INTO fitting_type_effects (type_id, effect_id, is_default) VALUES (?, ?, ?)');

        $usedAttributeIds = [];
        $usedEffectIds = [];
        $typeUpdates = [];
        $attributeCount = 0;
        $effectCount = 0;

        $pdo->beginTransaction();
        try {
            $this->readJsonl($source, 'typeDogma.jsonl', function (array $row) use (
                $allowedTypes,
                $typeCategoryMap,
                $typeGroupMap,
                $localItems,
                $attrInsert,
                $effectInsert,
                &$usedAttributeIds,
                &$usedEffectIds,
                &$typeUpdates,
                &$attributeCount,
                &$effectCount
            ): void {
                $typeId = (int) ($row['_key'] ?? 0);
                if (!isset($allowedTypes[$typeId])) {
                    return;
                }

                $metaLevel = null;
                $categoryId = $typeCategoryMap[$typeId] ?? null;
                $groupId = $typeGroupMap[$typeId] ?? null;
                $categorySlot = $this->inferSlotFromTypeCategory(
                    $localItems[(string) $typeId]['category'] ?? [],
                    $categoryId,
                    $groupId
                );
                $fixedCategorySlot = $this->hasFixedCategorySlot($categoryId);
                $slotType = $fixedCategorySlot ? $categorySlot : null;
                $slotSource = $fixedCategorySlot && $categorySlot ? 'category' : null;

                foreach (($row['dogmaAttributes'] ?? []) as $attribute) {
                    $attributeId = (int) ($attribute['attributeID'] ?? 0);
                    $value = isset($attribute['value']) ? (float) $attribute['value'] : null;

                    $attrInsert->execute([$typeId, $attributeId, $value]);
                    $usedAttributeIds[$attributeId] = true;
                    $attributeCount++;

                    if ($attributeId === 422) {
                        $metaLevel = $value;
                    }
                }

                foreach (($row['dogmaEffects'] ?? []) as $effect) {
                    $effectId = (int) ($effect['effectID'] ?? 0);
                    $isDefault = !empty($effect['isDefault']) ? 1 : 0;

                    $effectInsert->execute([$typeId, $effectId, $isDefault]);
                    $usedEffectIds[$effectId] = true;
                    $effectCount++;

                    if (!$fixedCategorySlot && $slotType === null) {
                        $slotType = $this->inferSlotFromEffectId($effectId);
                        if ($slotType !== null) {
                            $slotSource = 'effect';
                        }
                    }
                }

                if (!$fixedCategorySlot && $slotType === null && $categorySlot !== null) {
                    $slotType = $categorySlot;
                    $slotSource = 'category';
                }

                $typeUpdates[$typeId] = [
                    'meta_level' => $metaLevel,
                    'slot_type' => $slotType,
                    'slot_source' => $slotSource,
                ];
            });
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Dogma attributes: {$attributeCount}");
        $this->line("Dogma effects: {$effectCount}");

        return [$usedAttributeIds, $usedEffectIds, $typeUpdates];
    }

    private function importAttributeDefinitions(PDO $pdo, array $definitions, array $usedAttributeIds): void
    {
        $this->info('Import attribute definitions...');

        $insert = $pdo->prepare('
            INSERT INTO fitting_attribute_types (
                attribute_id, name, display_name_en, display_name_cn, unit_id, stackable, high_is_good, published
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach (array_keys($usedAttributeIds) as $attributeId) {
                $row = $definitions[$attributeId] ?? null;
                if (!$row) {
                    continue;
                }

                $insert->execute([
                    $attributeId,
                    $row['name'] ?? null,
                    $row['displayName']['en'] ?? null,
                    $row['displayName']['zh'] ?? ($row['displayName']['zh-cn'] ?? null),
                    $row['unitID'] ?? null,
                    isset($row['stackable']) ? (int) $row['stackable'] : 1,
                    isset($row['highIsGood']) ? (int) $row['highIsGood'] : 1,
                    isset($row['published']) ? (int) $row['published'] : 1,
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Attribute definitions: {$count}");
    }

    private function importEffectDefinitions(PDO $pdo, array $definitions, array $usedEffectIds): void
    {
        $this->info('Import effect definitions...');

        $insert = $pdo->prepare('
            INSERT INTO fitting_effects (
                effect_id, name, effect_category_id, is_assistance, is_offensive, published
            ) VALUES (?, ?, ?, ?, ?, ?)
        ');

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach (array_keys($usedEffectIds) as $effectId) {
                $row = $definitions[$effectId] ?? null;
                if (!$row) {
                    continue;
                }

                $insert->execute([
                    $effectId,
                    $row['name'] ?? null,
                    $row['effectCategoryID'] ?? null,
                    isset($row['isAssistance']) ? (int) $row['isAssistance'] : 0,
                    isset($row['isOffensive']) ? (int) $row['isOffensive'] : 0,
                    isset($row['published']) ? (int) $row['published'] : 1,
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Effect definitions: {$count}");
    }

    private function applyTypeUpdates(PDO $pdo, array $typeUpdates): void
    {
        $this->info('Apply derived type fields...');

        $update = $pdo->prepare(
            'UPDATE fitting_types SET meta_level = ?, slot_type = ?, slot_source = ? WHERE type_id = ?'
        );

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($typeUpdates as $typeId => $updateData) {
                $update->execute([
                    $updateData['meta_level'],
                    $updateData['slot_type'],
                    $updateData['slot_source'],
                    $typeId,
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->line("Updated types: {$count}");
    }

    private function writeBuildMeta(PDO $pdo, string $source): void
    {
        $insert = $pdo->prepare('INSERT INTO fitting_build_meta (meta_key, meta_value) VALUES (?, ?)');

        $rows = [
            'built_at' => now()->toDateTimeString(),
            'source_path' => $source,
            'included_categories' => json_encode(self::INCLUDED_CATEGORY_IDS),
            'builder' => static::class,
        ];

        $pdo->beginTransaction();
        try {
            foreach ($rows as $key => $value) {
                $insert->execute([$key, (string) $value]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function applyFinalSlotFallbacks(PDO $pdo): void
    {
        $this->info('Apply final slot fallbacks...');

        $pdo->beginTransaction();
        try {
            foreach (self::GROUP_SLOT_MAP as $groupId => $slot) {
                $stmt = $pdo->prepare('
                    UPDATE fitting_types
                    SET slot_type = ?, slot_source = COALESCE(slot_source, ?)
                    WHERE slot_type IS NULL
                      AND group_id = ?
                      AND published = 1
                      AND (
                          market_group_id IS NOT NULL
                          OR local_category_1 IS NOT NULL
                          OR EXISTS (
                              SELECT 1
                              FROM fitting_type_effects te
                              WHERE te.type_id = fitting_types.type_id
                          )
                      )
                ');
                $stmt->execute([$slot, 'group', $groupId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function inferSlotFromEffectId(int $effectId): ?string
    {
        return match (true) {
            $effectId === 11 => 'low',
            $effectId === 12 => 'high',
            $effectId === 13 => 'med',
            $effectId === 2663 => 'rig',
            $effectId >= 3772 && $effectId <= 3872 => 'subsystem',
            default => null,
        };
    }

    private function inferSlotFromTypeCategory(array $localCategoryPath, ?int $categoryId, ?int $groupId): ?string
    {
        $slot = match ($categoryId) {
            6 => 'ship',
            8 => 'charge',
            16 => 'skill',
            18 => 'drone',
            24 => 'implant',
            32 => 'subsystem',
            default => $this->inferSlotFromLocalCategoryPath($localCategoryPath),
        };

        if ($slot !== null) {
            return $slot;
        }

        return null;
    }

    private function inferInitialSlotSource(array $localCategoryPath, ?int $categoryId, ?int $groupId, ?string $slot): ?string
    {
        if ($slot === null) {
            return null;
        }

        if (in_array($categoryId, [6, 8, 16, 18, 24, 32], true)) {
            return 'category';
        }

        if ($this->inferSlotFromLocalCategoryPath($localCategoryPath) !== null) {
            return 'category';
        }

        return 'category';
    }

    private function hasFixedCategorySlot(?int $categoryId): bool
    {
        return in_array($categoryId, [6, 8, 16, 18, 24, 32], true);
    }

    private function inferSlotFromLocalCategoryPath(array $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $level1 = $path[0] ?? null;
        $level2 = $path[1] ?? null;

        if ($level1 === '改装件') {
            return 'rig';
        }

        if ($level1 === '无人机') {
            return 'drone';
        }

        if ($level1 !== '舰船装备') {
            return null;
        }

        return $level2 ? (self::SLOT_CATEGORY_MAP[$level2] ?? null) : null;
    }

    private function showStats(PDO $pdo, string $output): void
    {
        $tables = [
            'fitting_categories' => 'categories',
            'fitting_groups' => 'groups',
            'fitting_market_groups' => 'market groups',
            'fitting_types' => 'types',
            'fitting_attributes' => 'attributes',
            'fitting_type_effects' => 'type effects',
            'fitting_attribute_types' => 'attribute definitions',
            'fitting_effects' => 'effect definitions',
        ];

        $this->newLine();
        $this->info('=== Subset stats ===');

        foreach ($tables as $table => $label) {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            $this->line("{$label}: {$count}");
        }

        $slotRows = $pdo->query("
            SELECT COALESCE(slot_type, 'unknown') AS slot_type, COUNT(*) AS count
            FROM fitting_types
            GROUP BY COALESCE(slot_type, 'unknown')
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->newLine();
        $this->info('=== Slot stats ===');
        foreach ($slotRows as $row) {
            $this->line($row['slot_type'] . ': ' . $row['count']);
        }

        $size = round(filesize($output) / 1024 / 1024, 2);
        $this->newLine();
        $this->line("Output size: {$size} MB");
    }

    private function readJsonl(string $source, string $entryName, callable $callback): void
    {
        if (is_dir($source)) {
            $path = rtrim($source, '\\/') . DIRECTORY_SEPARATOR . $entryName;
            if (!file_exists($path)) {
                throw new RuntimeException("JSONL file not found: {$path}");
            }

            $handle = fopen($path, 'rb');
            if (!$handle) {
                throw new RuntimeException("Cannot read JSONL file: {$path}");
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    $decoded = $this->decodeJsonLine($line, $entryName);
                    if ($decoded !== null) {
                        $callback($decoded);
                    }
                }
            } finally {
                fclose($handle);
            }

            return;
        }

        $zip = new ZipArchive();
        $opened = $zip->open($source);
        if ($opened !== true) {
            throw new RuntimeException("Cannot open zip: {$source}");
        }

        $stream = $zip->getStream($entryName);
        if (!$stream) {
            $zip->close();
            throw new RuntimeException("Zip entry not found: {$entryName}");
        }

        try {
            while (($line = fgets($stream)) !== false) {
                $decoded = $this->decodeJsonLine($line, $entryName);
                if ($decoded !== null) {
                    $callback($decoded);
                }
            }
        } finally {
            fclose($stream);
            $zip->close();
        }
    }

    private function decodeJsonLine(string $line, string $entryName): ?array
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return null;
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to decode {$entryName}: {$e->getMessage()}");
        }

        return is_array($decoded) ? $decoded : null;
    }
}
