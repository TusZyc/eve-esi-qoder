<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FittingOfficialDataService
{
    private const SHIP_ATTR_MAP = [
        48 => 'cpuOutput',
        11 => 'powerOutput',
        482 => 'capacitorCapacity',
        55 => 'rechargeRate',
        1132 => 'upgradeCapacity',
        1547 => 'rigSize',
        12 => 'lowSlots',
        13 => 'medSlots',
        14 => 'hiSlots',
        1137 => 'rigSlots',
        102 => 'turretSlotsLeft',
        101 => 'launcherSlotsLeft',
        263 => 'shieldCapacity',
        265 => 'armorHP',
        9 => 'hp',
        37 => 'maxVelocity',
        70 => 'agility',
        552 => 'signatureRadius',
        600 => 'warpSpeedMultiplier',
        76 => 'maxTargetRange',
        564 => 'scanResolution',
        192 => 'maxLockedTargets',
        1271 => 'droneBandwidth',
        283 => 'droneCapacity',
        1785 => 'isCapitalSize',
    ];

    private const CAN_FIT_SHIP_GROUP_ATTRS = [1298, 1299, 1300, 1301, 1872, 1879, 1880, 1881, 2065, 2396, 2476];
    private const CAN_FIT_SHIP_TYPE_ATTRS = [1302, 1303, 1304, 1305, 1944, 2103, 2463, 2486, 2487, 2488, 5948];
    private const SUBSYSTEM_SHIP_TYPE_BY_PREFIX = [
        '金鹏' => 29984,
        '圣卒' => 29986,
        '海神' => 29988,
        '洛基' => 29990,
    ];

    private bool $dbInitialized = false;

    public function isDatabaseReady(): bool
    {
        if ($this->dbInitialized) {
            return true;
        }

        try {
            $tables = DB::connection('fitting_official')
                ->select("SELECT name FROM sqlite_master WHERE type='table' AND name='fitting_types'");

            if (!empty($tables)) {
                $this->dbInitialized = true;
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    public function getSummary(): array
    {
        if (!$this->isDatabaseReady()) {
            return [
                'ready' => false,
                'tables' => [],
                'slot_counts' => [],
            ];
        }

        $db = DB::connection('fitting_official');

        $tables = [
            'fitting_categories',
            'fitting_groups',
            'fitting_market_groups',
            'fitting_types',
            'fitting_attributes',
            'fitting_type_effects',
            'fitting_attribute_types',
            'fitting_effects',
        ];

        $tableCounts = [];
        foreach ($tables as $table) {
            $tableCounts[$table] = $db->table($table)->count();
        }

        $slotCounts = $db->table('fitting_types')
            ->selectRaw("COALESCE(slot_type, 'unknown') AS slot_type, COUNT(*) AS count")
            ->groupByRaw("COALESCE(slot_type, 'unknown')")
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'slot_type' => $row->slot_type,
                'count' => (int) $row->count,
            ])
            ->all();

        return [
            'ready' => true,
            'tables' => $tableCounts,
            'slot_counts' => $slotCounts,
        ];
    }

    public function findType(int $typeId): ?array
    {
        if (!$this->isDatabaseReady()) {
            return null;
        }

        $row = DB::connection('fitting_official')
            ->table('fitting_types')
            ->where('type_id', $typeId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function getSampleTypesBySlot(string $slotType, int $limit = 10): array
    {
        if (!$this->isDatabaseReady()) {
            return [];
        }

        return DB::connection('fitting_official')
            ->table('fitting_types')
            ->select('type_id', 'name_cn', 'name_en', 'group_id', 'slot_type', 'slot_source')
            ->where('slot_type', $slotType)
            ->orderBy('name_cn')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function getShipCategoryTree(): array
    {
        if (!$this->isDatabaseReady()) {
            return [];
        }

        return $this->getShipCatalog()['tree'];
    }

    public function getShipsByCategoryPath(array $categoryPath): array
    {
        if (!$this->isDatabaseReady() || empty($categoryPath)) {
            return [];
        }

        $catalog = $this->getShipCatalog();
        $typeIds = $catalog['path_map'][$this->pathKey($categoryPath)] ?? [];
        if (empty($typeIds)) {
            return [];
        }

        $rows = DB::connection('fitting_official')
            ->table('fitting_types')
            ->whereIn('type_id', $typeIds)
            ->orderByRaw('COALESCE(name_cn, name_en)')
            ->get([
                'type_id',
                'name_en',
                'name_cn',
                'group_id',
                'category_id',
                'local_category_1',
                'local_category_2',
                'local_category_3',
                'local_category_4',
                'local_category_5',
            ]);

        $attrMap = $this->getTypeAttributesMap($typeIds);

        return $rows->map(function ($row) use ($attrMap): array {
            $typeId = (int) $row->type_id;
            $attrs = $attrMap[$typeId] ?? [];

            return [
                'type_id' => $typeId,
                'name' => $row->name_en,
                'name_cn' => $row->name_cn ?: $row->name_en,
                'group_id' => (int) $row->group_id,
                'category_id' => (int) $row->category_id,
                'category_path' => $this->displayShipCategoryPath($this->extractCategoryPath($row)),
                'slots' => [
                    'hi' => (int) ($attrs[14] ?? 0),
                    'med' => (int) ($attrs[13] ?? 0),
                    'low' => (int) ($attrs[12] ?? 0),
                    'rig' => (int) ($attrs[1137] ?? 0),
                ],
                'hardpoints' => [
                    'turret' => (int) ($attrs[102] ?? 0),
                    'launcher' => (int) ($attrs[101] ?? 0),
                ],
                'image_url' => $this->getTypeImageUrl($typeId, 64),
                'render_url' => $this->getShipRenderUrl($typeId, 256),
            ];
        })->all();
    }

    public function getShipDetails(int $typeId): ?array
    {
        if (!$this->isDatabaseReady()) {
            return null;
        }

        return cache()->remember("fitting_official_ship_{$typeId}_v1", 3600, function () use ($typeId): ?array {
            $row = DB::connection('fitting_official')
                ->table('fitting_types')
                ->where('type_id', $typeId)
                ->where('category_id', 6)
                ->first();

            if (!$row) {
                return null;
            }

            $attrs = $this->getTypeAttributesMap([$typeId])[$typeId] ?? [];
            $attrDefs = $this->getAttributeDefinitionMap(array_keys($attrs));
            $effects = $this->getTypeEffectRows($typeId);

            $namedAttrs = [];
            foreach ($attrs as $attributeId => $value) {
                $name = self::SHIP_ATTR_MAP[$attributeId] ?? ($attrDefs[$attributeId]['name'] ?? "attr_{$attributeId}");
                $namedAttrs[$name] = [
                    'attribute_id' => $attributeId,
                    'value' => $value,
                    'name' => $name,
                    'unit' => $attrDefs[$attributeId]['unit_id'] ?? null,
                ];
            }

            return [
                'type_id' => $typeId,
                'name' => $row->name_en,
                'name_cn' => $row->name_cn ?: $row->name_en,
                'group_id' => (int) $row->group_id,
                'category_id' => (int) $row->category_id,
                'description' => null,
                'mass' => (float) ($row->mass ?? 0),
                'volume' => (float) ($row->volume ?? 0),
                'capacity' => (float) ($row->capacity ?? 0),
                'slots' => [
                    'hi' => (int) ($attrs[14] ?? 0),
                    'med' => (int) ($attrs[13] ?? 0),
                    'low' => (int) ($attrs[12] ?? 0),
                    'rig' => (int) ($attrs[1137] ?? 0),
                ],
                'hardpoints' => [
                    'turret' => (int) ($attrs[102] ?? 0),
                    'launcher' => (int) ($attrs[101] ?? 0),
                ],
                'resources' => [
                    'cpu_output' => (float) ($attrs[48] ?? 0),
                    'power_output' => (float) ($attrs[11] ?? 0),
                    'capacitor_capacity' => (float) ($attrs[482] ?? 0),
                    'recharge_rate' => (float) ($attrs[55] ?? 0),
                    'upgrade_capacity' => (float) ($attrs[1132] ?? 350),
                    'rig_size' => (int) ($attrs[1547] ?? 0),
                    'drone_capacity' => (float) ($attrs[283] ?? 0),
                    'drone_bandwidth' => (float) ($attrs[1271] ?? 0),
                    'is_capital_size' => (int) ($attrs[1785] ?? 0) > 0,
                ],
                'attributes' => $namedAttrs,
                'effects' => $effects,
                'image_url' => $this->getTypeImageUrl($typeId, 64),
                'render_url' => $this->getShipRenderUrl($typeId, 256),
            ];
        });
    }

    public function getModuleCategoryTree(?string $slotFilter = null, array $filters = []): array
    {
        if ($this->hasActiveModuleFilters($filters)) {
            return $this->buildFilteredModuleCategoryTree($slotFilter, $filters);
        }

        $catalog = $this->getModuleCatalog();
        $treeKey = $slotFilter ? "tree_{$slotFilter}" : 'tree_all';
        return $catalog[$treeKey] ?? [];
    }

    public function getModulesByCategoryPath(array $categoryPath, ?string $slotFilter = null, array $filters = []): array
    {
        if (!$this->isDatabaseReady() || empty($categoryPath)) {
            return [];
        }

        $catalog = $this->getModuleCatalog();
        $typeIds = $catalog['path_map'][$this->pathKey($categoryPath)] ?? [];
        if (empty($typeIds)) {
            return [];
        }

        $db = DB::connection('fitting_official');
        $rows = $db->table('fitting_types')
            ->select(
                'type_id',
                'name_en',
                'name_cn',
                'group_id',
                'slot_type',
                'slot_source',
                'meta_level',
                'volume',
                'local_category_1',
                'local_category_2',
                'local_category_3',
                'local_category_4',
                'local_category_5'
            )
            ->whereIn('type_id', $typeIds)
            ->orderByRaw('COALESCE(name_cn, name_en)')
            ->get();

        $attrMap = $this->getTypeAttributesMap($typeIds);
        $effectMap = $this->getTypeEffectsMap($typeIds);
        $result = [];
        $allowedSlots = $this->expandSlotFilter($slotFilter);

        foreach ($rows as $row) {
            if (!empty($allowedSlots) && !in_array($row->slot_type, $allowedSlots, true)) {
                continue;
            }

            $typeId = (int) $row->type_id;
            $attrs = $attrMap[$typeId] ?? [];
            $effectIds = $effectMap[$typeId] ?? [];
            $metaGroupId = (int) ($attrs[1692] ?? 0);
            $metaLevelOld = (int) ($attrs[633] ?? 0);
            $category = $this->displayCategoryPath($this->extractCategoryPath($row));
            [$canFitShipGroups, $canFitShipTypes] = $this->extractCanFitTargets($attrs);

            if (!$this->moduleMatchesFilters($row, $attrs, $canFitShipGroups, $canFitShipTypes, $filters)) {
                continue;
            }

            $result[] = [
                'type_id' => $typeId,
                'name' => $row->name_en,
                'name_cn' => $row->name_cn ?: $row->name_en,
                'group_id' => (int) $row->group_id,
                'category' => $category,
                'category_label' => $this->pathKey($category),
                'slot' => $row->slot_type,
                'slot_source' => $row->slot_source,
                'effect_ids' => $effectIds,
                'cpu' => (float) ($attrs[50] ?? 0),
                'power' => (float) ($attrs[30] ?? 0),
                'upgrade_cost' => (float) ($attrs[1153] ?? 0),
                'charge_size' => (int) ($attrs[128] ?? 0),
                'rig_size' => (int) ($attrs[1547] ?? 0),
                'max_group_fitted' => (int) ($attrs[1544] ?? 0),
                'max_type_fitted' => (int) ($attrs[2431] ?? 0),
                'volume' => (float) ($row->volume ?? 0),
                'drone_bandwidth_used' => (float) ($attrs[1272] ?? 0),
                'can_fit_ship_groups' => $canFitShipGroups,
                'can_fit_ship_types' => $canFitShipTypes,
                'meta_level' => (int) ($row->meta_level ?? 0),
                'meta_group_id' => $metaGroupId,
                'rarity_group' => $this->rarityGroupLabel($metaGroupId, $metaLevelOld),
                'image_url' => $this->getTypeImageUrl($typeId, 64),
            ];
        }

        return $result;
    }

    public function searchModules(string $query, ?string $slotFilter = null, int $limit = 50): array
    {
        if (!$this->isDatabaseReady()) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 100));
        $allowedSlots = $this->expandSlotFilter($slotFilter);

        $db = DB::connection('fitting_official');
        $builder = $db->table('fitting_types')
            ->select(
                'type_id',
                'name_en',
                'name_cn',
                'group_id',
                'slot_type',
                'slot_source',
                'meta_level',
                'volume',
                'local_category_1',
                'local_category_2',
                'local_category_3',
                'local_category_4',
                'local_category_5'
            )
            ->whereIn('category_id', [7, 18, 32])
            ->where('published', 1)
            ->whereNotNull('slot_type')
            ->where(function ($search) use ($query) {
                $search->where('name_cn', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%");
            });

        if (!empty($allowedSlots)) {
            $builder->whereIn('slot_type', $allowedSlots);
        }

        $rows = $builder
            ->orderByRaw('COALESCE(name_cn, name_en)')
            ->limit($limit * 4)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $typeIds = $rows->pluck('type_id')->map(fn ($typeId) => (int) $typeId)->all();
        $attrMap = $this->getTypeAttributesMap($typeIds);
        $effectMap = $this->getTypeEffectsMap($typeIds);
        $results = [];

        foreach ($rows as $row) {
            $category = $this->displayCategoryPath($this->extractCategoryPath($row));
            if (empty($category)) {
                continue;
            }

            $typeId = (int) $row->type_id;
            $attrs = $attrMap[$typeId] ?? [];
            $effectIds = $effectMap[$typeId] ?? [];
            $metaGroupId = (int) ($attrs[1692] ?? 0);
            $metaLevelOld = (int) ($attrs[633] ?? 0);
            [$canFitShipGroups, $canFitShipTypes] = $this->extractCanFitTargets($attrs);

            $results[] = [
                'type_id' => $typeId,
                'name' => $row->name_en,
                'name_cn' => $row->name_cn ?: $row->name_en,
                'group_id' => (int) $row->group_id,
                'category' => $category,
                'category_label' => $this->pathKey($category),
                'slot' => $row->slot_type,
                'slot_source' => $row->slot_source,
                'effect_ids' => $effectIds,
                'cpu' => (float) ($attrs[50] ?? 0),
                'power' => (float) ($attrs[30] ?? 0),
                'upgrade_cost' => (float) ($attrs[1153] ?? 0),
                'charge_size' => (int) ($attrs[128] ?? 0),
                'rig_size' => (int) ($attrs[1547] ?? 0),
                'max_group_fitted' => (int) ($attrs[1544] ?? 0),
                'max_type_fitted' => (int) ($attrs[2431] ?? 0),
                'volume' => (float) ($row->volume ?? 0),
                'drone_bandwidth_used' => (float) ($attrs[1272] ?? 0),
                'can_fit_ship_groups' => $canFitShipGroups,
                'can_fit_ship_types' => $canFitShipTypes,
                'meta_level' => (int) ($row->meta_level ?? 0),
                'meta_group_id' => $metaGroupId,
                'rarity_group' => $this->rarityGroupLabel($metaGroupId, $metaLevelOld),
                'image_url' => $this->getTypeImageUrl($typeId, 64),
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    private function buildFilteredModuleCategoryTree(?string $slotFilter, array $filters): array
    {
        if (!$this->isDatabaseReady()) {
            return [];
        }

        $allowedSlots = $this->expandSlotFilter($slotFilter);
        $rows = DB::connection('fitting_official')
            ->table('fitting_types')
            ->whereIn('category_id', [7, 18, 32])
            ->where('published', 1)
            ->whereNotNull('slot_type')
            ->select(
                'type_id',
                'name_cn',
                'name_en',
                'slot_type',
                'volume',
                'local_category_1',
                'local_category_2',
                'local_category_3',
                'local_category_4',
                'local_category_5'
            )
            ->orderByRaw('COALESCE(name_cn, name_en)')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $typeIds = $rows->pluck('type_id')->map(fn ($typeId) => (int) $typeId)->all();
        $attrMap = $this->getTypeAttributesMap($typeIds);
        $tree = [];

        foreach ($rows as $row) {
            if (!empty($allowedSlots) && !in_array($row->slot_type, $allowedSlots, true)) {
                continue;
            }

            $path = $this->displayCategoryPath($this->extractCategoryPath($row));
            if (empty($path)) {
                continue;
            }

            $attrs = $attrMap[(int) $row->type_id] ?? [];
            [$canFitShipGroups, $canFitShipTypes] = $this->extractCanFitTargets($attrs);
            if (!$this->moduleMatchesFilters($row, $attrs, $canFitShipGroups, $canFitShipTypes, $filters)) {
                continue;
            }

            $this->appendTreePath($tree, $path);
        }

        return $this->normalizeTreeNodes($tree);
    }

    private function hasActiveModuleFilters(array $filters): bool
    {
        return !empty($filters['ship_filter']) || !empty($filters['resource_filter']);
    }

    private function moduleMatchesFilters(object $row, array $attrs, array $canFitShipGroups, array $canFitShipTypes, array $filters): bool
    {
        if (!empty($filters['ship_filter']) && !$this->moduleMatchesShipFilter($row, $attrs, $canFitShipGroups, $canFitShipTypes, $filters)) {
            return false;
        }

        if (!empty($filters['resource_filter']) && !$this->moduleMatchesResourceFilter($row, $attrs, $filters)) {
            return false;
        }

        return true;
    }

    private function moduleMatchesShipFilter(object $row, array $attrs, array $canFitShipGroups, array $canFitShipTypes, array $filters): bool
    {
        $slotType = $row->slot_type ?? null;
        if ($slotType === 'subsystem' && !$this->subsystemMatchesShip($row, (int) ($filters['ship_type_id'] ?? 0))) {
            return false;
        }

        if (!$this->shipHasSlotForModule($slotType, $filters)) {
            return false;
        }

        if ($slotType === 'rig' && !$this->rigMatchesShipSize($attrs, $filters)) {
            return false;
        }

        if ($this->moduleRequiresCapitalShip($row, $attrs) && empty($filters['ship_is_capital_size'])) {
            return false;
        }

        if (empty($canFitShipGroups) && empty($canFitShipTypes)) {
            return true;
        }

        $shipTypeId = (int) ($filters['ship_type_id'] ?? 0);
        $shipGroupId = (int) ($filters['ship_group_id'] ?? 0);

        return in_array($shipTypeId, $canFitShipTypes, true) || in_array($shipGroupId, $canFitShipGroups, true);
    }

    private function moduleMatchesResourceFilter(object $row, array $attrs, array $filters): bool
    {
        if (($row->slot_type ?? null) === 'drone') {
            return (float) ($row->volume ?? 0) <= (float) ($filters['remaining_drone_bay'] ?? 0)
                && (float) ($attrs[1272] ?? 0) <= (float) ($filters['remaining_drone_bandwidth'] ?? 0);
        }

        if ((float) ($attrs[50] ?? 0) > (float) ($filters['remaining_cpu'] ?? 0)) {
            return false;
        }

        if ((float) ($attrs[30] ?? 0) > (float) ($filters['remaining_power'] ?? 0)) {
            return false;
        }

        if (in_array($row->slot_type ?? null, ['rig', 'subsystem'], true)
            && (float) ($attrs[1153] ?? 0) > (float) ($filters['remaining_calibration'] ?? 0)) {
            return false;
        }

        return true;
    }

    private function shipHasSlotForModule(?string $slotType, array $filters): bool
    {
        return match ($slotType) {
            'high' => (int) ($filters['slot_high'] ?? 0) > 0,
            'med' => (int) ($filters['slot_med'] ?? 0) > 0,
            'low' => (int) ($filters['slot_low'] ?? 0) > 0,
            'rig', 'subsystem' => (int) ($filters['slot_rig'] ?? 0) > 0,
            'drone' => (float) ($filters['drone_bay_capacity'] ?? 0) > 0,
            default => false,
        };
    }

    private function rigMatchesShipSize(array $attrs, array $filters): bool
    {
        $moduleRigSize = (int) ($attrs[1547] ?? 0);
        $shipRigSize = (int) ($filters['ship_rig_size'] ?? 0);

        if ($moduleRigSize <= 0 || $shipRigSize <= 0) {
            return true;
        }

        return $moduleRigSize === $shipRigSize;
    }

    private function moduleRequiresCapitalShip(object $row, array $attrs): bool
    {
        if (!in_array($row->slot_type ?? null, ['high', 'med', 'low'], true)) {
            return false;
        }

        $text = implode(' ', array_filter([
            $row->name_cn ?? null,
            $row->name_en ?? null,
            $row->local_category_1 ?? null,
            $row->local_category_2 ?? null,
            $row->local_category_3 ?? null,
            $row->local_category_4 ?? null,
            $row->local_category_5 ?? null,
        ]));

        foreach (["\u{65D7}\u{8230}", "\u{8D85}\u{5927}\u{578B}", 'Capital', 'Doomsday'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return (int) ($attrs[128] ?? 0) >= 4;
    }

    private function subsystemMatchesShip(object $row, int $shipTypeId): bool
    {
        $name = (string) (($row->name_cn ?? null) ?: ($row->name_en ?? ''));
        foreach (self::SUBSYSTEM_SHIP_TYPE_BY_PREFIX as $prefix => $typeId) {
            if (str_starts_with($name, $prefix)) {
                return $shipTypeId === $typeId;
            }
        }

        return false;
    }

    private function extractCanFitTargets(array $attrs): array
    {
        $groups = [];
        foreach (self::CAN_FIT_SHIP_GROUP_ATTRS as $attributeId) {
            if (!empty($attrs[$attributeId])) {
                $groups[] = (int) $attrs[$attributeId];
            }
        }

        $types = [];
        foreach (self::CAN_FIT_SHIP_TYPE_ATTRS as $attributeId) {
            if (!empty($attrs[$attributeId])) {
                $types[] = (int) $attrs[$attributeId];
            }
        }

        return [array_values(array_unique($groups)), array_values(array_unique($types))];
    }

    private function getShipCatalog(): array
    {
        return cache()->remember('fitting_official_ship_catalog_v1', 3600, function (): array {
            $rows = DB::connection('fitting_official')
                ->table('fitting_types')
                ->where('category_id', 6)
                ->where('published', 1)
                ->orderByRaw('COALESCE(name_cn, name_en)')
                ->get([
                    'type_id',
                    'local_category_1',
                    'local_category_2',
                    'local_category_3',
                    'local_category_4',
                    'local_category_5',
                ]);

            $tree = [];
            $pathMap = [];

            foreach ($rows as $row) {
                $path = $this->displayShipCategoryPath($this->extractCategoryPath($row));
                if (empty($path)) {
                    continue;
                }

                $this->appendTreePath($tree, $path);
                $pathMap[$this->pathKey($path)][] = (int) $row->type_id;
            }

            return [
                'tree' => $this->normalizeTreeNodes($tree),
                'path_map' => $pathMap,
            ];
        });
    }

    private function getModuleCatalog(): array
    {
        return cache()->remember('fitting_official_module_catalog_v2', 3600, function () {
            $types = DB::connection('fitting_official')
                ->table('fitting_types')
                ->whereIn('category_id', [7, 18, 32])
                ->where('published', 1)
                ->whereNotNull('slot_type')
                ->select(
                    'type_id',
                    'name_en',
                    'name_cn',
                    'slot_type',
                    'local_category_1',
                    'local_category_2',
                    'local_category_3',
                    'local_category_4',
                    'local_category_5'
                )
                ->orderByRaw('COALESCE(name_cn, name_en)')
                ->get();

            $treeAll = [];
            $treeBySlot = [
                'high' => [],
                'med' => [],
                'low' => [],
                'rig' => [],
                'drone' => [],
            ];
            $pathMap = [];

            foreach ($types as $type) {
                $path = $this->extractCategoryPath($type);
                if (empty($path)) {
                    continue;
                }
                $displayPath = $this->displayCategoryPath($path);
                if (empty($displayPath)) {
                    continue;
                }

                $typeId = (int) $type->type_id;
                $slot = $type->slot_type;

                $this->appendTreePath($treeAll, $displayPath);
                $pathMap[$this->pathKey($displayPath)][] = $typeId;

                if (isset($treeBySlot[$slot])) {
                    $this->appendTreePath($treeBySlot[$slot], $displayPath);
                }

                if ($slot === 'subsystem') {
                    $this->appendTreePath($treeBySlot['rig'], $displayPath);
                }
            }

            return [
                'tree_all' => $this->normalizeTreeNodes($treeAll),
                'tree_high' => $this->normalizeTreeNodes($treeBySlot['high']),
                'tree_med' => $this->normalizeTreeNodes($treeBySlot['med']),
                'tree_low' => $this->normalizeTreeNodes($treeBySlot['low']),
                'tree_rig' => $this->normalizeTreeNodes($treeBySlot['rig']),
                'tree_drone' => $this->normalizeTreeNodes($treeBySlot['drone']),
                'path_map' => $pathMap,
            ];
        });
    }

    private function getTypeAttributesMap(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        $rows = DB::connection('fitting_official')
            ->table('fitting_attributes')
            ->whereIn('type_id', $typeIds)
            ->get(['type_id', 'attribute_id', 'value']);

        $map = [];
        foreach ($rows as $row) {
            $typeId = (int) $row->type_id;
            $attributeId = (int) $row->attribute_id;
            $map[$typeId][$attributeId] = $row->value;
        }

        return $map;
    }

    private function getTypeEffectsMap(array $typeIds): array
    {
        if (empty($typeIds)) {
            return [];
        }

        $rows = DB::connection('fitting_official')
            ->table('fitting_type_effects')
            ->whereIn('type_id', $typeIds)
            ->get(['type_id', 'effect_id']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->type_id][] = (int) $row->effect_id;
        }

        return $map;
    }

    private function getTypeEffectRows(int $typeId): array
    {
        return DB::connection('fitting_official')
            ->table('fitting_type_effects')
            ->leftJoin('fitting_effects', 'fitting_effects.effect_id', '=', 'fitting_type_effects.effect_id')
            ->where('fitting_type_effects.type_id', $typeId)
            ->orderBy('fitting_type_effects.effect_id')
            ->get([
                'fitting_type_effects.effect_id',
                'fitting_effects.name',
            ])
            ->map(fn ($row) => [
                'effect_id' => (int) $row->effect_id,
                'name' => $row->name,
            ])
            ->all();
    }

    private function getAttributeDefinitionMap(array $attributeIds): array
    {
        if (empty($attributeIds)) {
            return [];
        }

        return DB::connection('fitting_official')
            ->table('fitting_attribute_types')
            ->whereIn('attribute_id', $attributeIds)
            ->get(['attribute_id', 'name', 'unit_id'])
            ->mapWithKeys(fn ($row) => [
                (int) $row->attribute_id => [
                    'name' => $row->name,
                    'unit_id' => $row->unit_id,
                ],
            ])
            ->all();
    }

    private function extractCategoryPath(object $row): array
    {
        return array_values(array_filter([
            $row->local_category_1 ?? null,
            $row->local_category_2 ?? null,
            $row->local_category_3 ?? null,
            $row->local_category_4 ?? null,
            $row->local_category_5 ?? null,
        ]));
    }

    private function displayShipCategoryPath(array $path): array
    {
        if (($path[0] ?? null) === '舰船') {
            array_shift($path);
        }

        if (($path[0] ?? null) === '运载舰和工业舰') {
            $path[0] = '工业与运载舰';
        }

        return $path;
    }

    private function displayCategoryPath(array $path): array
    {
        if (in_array($path[0] ?? null, ['舰船装备', '舰船和装备改装件'], true)) {
            return array_slice($path, 1);
        }

        return $path;
    }

    private function expandSlotFilter(?string $slotFilter): array
    {
        return match ($slotFilter) {
            'rig' => ['rig', 'subsystem'],
            'high', 'med', 'low', 'drone' => [$slotFilter],
            default => [],
        };
    }

    private function appendTreePath(array &$tree, array $path, int $depth = 0): void
    {
        if (!isset($path[$depth])) {
            return;
        }

        $name = $path[$depth];
        if (!isset($tree[$name])) {
            $tree[$name] = [
                'name' => $name,
                'path' => array_slice($path, 0, $depth + 1),
                'count' => 0,
                'children' => [],
            ];
        }

        $tree[$name]['count']++;

        if (isset($path[$depth + 1])) {
            $this->appendTreePath($tree[$name]['children'], $path, $depth + 1);
        }
    }

    private function normalizeTreeNodes(array $tree): array
    {
        $nodes = array_values($tree);

        usort($nodes, function (array $a, array $b) {
            return strcmp($a['name'], $b['name']);
        });

        foreach ($nodes as &$node) {
            $node['children'] = $this->normalizeTreeNodes($node['children'] ?? []);
        }

        return $nodes;
    }

    private function pathKey(array $path): string
    {
        return implode(' > ', $path);
    }

    private function getTypeImageUrl(int $typeId, int $size = 64): string
    {
        return "https://image.evepc.163.com/Type/{$typeId}_{$size}.png";
    }

    private function getShipRenderUrl(int $typeId, int $size = 256): string
    {
        return "https://image.evepc.163.com/Render/{$typeId}_{$size}.png";
    }

    private function rarityGroupLabel(int $metaGroupId, int $metaLevelOld): ?string
    {
        if (in_array($metaGroupId, [3, 4, 6], true)) {
            return '势力与故事线';
        }

        if ($metaGroupId === 5) {
            return '官员';
        }

        if (in_array($metaLevelOld, [6, 8, 9, 12, 13], true)) {
            return '势力与故事线';
        }

        if ($metaLevelOld >= 15) {
            return '官员';
        }

        return null;
    }
}
