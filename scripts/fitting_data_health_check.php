<?php

$dbPath = $argv[1] ?? __DIR__ . '/../database/fitting_official.sqlite';

if (!is_file($dbPath)) {
    fwrite(STDERR, "Database not found: {$dbPath}\n");
    exit(1);
}

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$rows = static fn (string $sql): array => $db->query($sql)->fetchAll();
$row = static function (string $sql) use ($db): array {
    $result = $db->query($sql)->fetch();
    return $result ?: [];
};

$keyAttrs = [
    11, 12, 13, 14, 30, 48, 50, 101, 102, 128, 182, 183, 184, 277, 278, 279,
    283, 633, 1132, 1137, 1153, 1154, 1271, 1272, 1547, 1692, 1785,
    1298, 1299, 1300, 1301, 1872, 1879, 1880, 1881, 2065, 2396, 2476,
    1302, 1303, 1304, 1305, 1944, 2103, 2463, 2486, 2487, 2488, 5948,
];

$attributeDefinitions = [];
$stmt = $db->prepare('
    SELECT attribute_id, name, display_name_en, display_name_cn, unit_id
    FROM fitting_attribute_types
    WHERE attribute_id = ?
');

foreach ($keyAttrs as $id) {
    $stmt->execute([$id]);
    $attributeDefinitions[$id] = $stmt->fetch() ?: null;
}

$sampleTypes = [
    52907 => 'Zirnitra',
    587 => 'Rifter',
    638 => 'Raven',
    29984 => 'Tengu',
    52915 => 'Ultratidal Entropic Disintegrator I',
    47272 => 'Light Entropic Disintegrator I',
    47273 => 'Heavy Entropic Disintegrator I',
    47274 => 'Supratidal Entropic Disintegrator I',
    496 => '800mm Repeating Cannon I',
    484 => '125mm Gatling AutoCannon I',
    2420 => 'Torpedo Launcher I',
    499 => 'Heavy Missile Launcher I',
    31790 => 'Small Core Defense Field Extender I',
    31792 => 'Medium Core Defense Field Extender I',
    31794 => 'Large Core Defense Field Extender I',
    30040 => 'Tengu Defensive - Adaptive Shielding',
    2454 => 'Hobgoblin I',
];

$samples = [];
foreach ($sampleTypes as $id => $label) {
    $type = $row("
        SELECT type_id, name_en, name_cn, group_id, category_id, slot_type,
               slot_source, local_category_path_json, volume
        FROM fitting_types
        WHERE type_id = {$id}
    ");

    if (!$type) {
        $samples[$id] = ['missing' => $label];
        continue;
    }

    $attrs = $rows("
        SELECT fa.attribute_id, fat.name, fat.display_name_en, fat.display_name_cn, fa.value
        FROM fitting_attributes fa
        LEFT JOIN fitting_attribute_types fat ON fat.attribute_id = fa.attribute_id
        WHERE fa.type_id = {$id}
          AND fa.attribute_id IN (" . implode(',', $keyAttrs) . ")
        ORDER BY fa.attribute_id
    ");

    $effects = $rows("
        SELECT fe.effect_id, fe.name
        FROM fitting_type_effects te
        LEFT JOIN fitting_effects fe ON fe.effect_id = te.effect_id
        WHERE te.type_id = {$id}
        ORDER BY fe.effect_id
    ");

    $samples[$id] = [
        'type' => $type,
        'attributes' => $attrs,
        'effects' => $effects,
    ];
}

$canFitIds = [
    1298, 1299, 1300, 1301, 1872, 1879, 1880, 1881, 2065, 2396, 2476,
    1302, 1303, 1304, 1305, 1944, 2103, 2463, 2486, 2487, 2488, 5948,
];

$canFitUsage = [];
foreach ($canFitIds as $id) {
    $count = (int) ($row("
        SELECT COUNT(*) AS c
        FROM fitting_attributes
        WHERE attribute_id = {$id}
          AND value > 0
    ")['c'] ?? 0);

    $examples = $rows("
        SELECT t.type_id, t.name_cn, t.name_en, t.slot_type, a.value
        FROM fitting_attributes a
        JOIN fitting_types t ON t.type_id = a.type_id
        WHERE a.attribute_id = {$id}
          AND a.value > 0
        LIMIT 5
    ");

    $canFitUsage[] = [
        'attribute' => $attributeDefinitions[$id] ?? null,
        'count' => $count,
        'examples' => $examples,
    ];
}

$output = [
    'summary' => [
        'types' => (int) ($row('SELECT COUNT(*) AS c FROM fitting_types')['c'] ?? 0),
        'attributes' => (int) ($row('SELECT COUNT(*) AS c FROM fitting_attributes')['c'] ?? 0),
        'effects' => (int) ($row('SELECT COUNT(*) AS c FROM fitting_type_effects')['c'] ?? 0),
    ],
    'attribute_definitions' => $attributeDefinitions,
    'slot_counts' => $rows("
        SELECT COALESCE(slot_type, 'null') AS slot_type, COUNT(*) AS count
        FROM fitting_types
        GROUP BY slot_type
        ORDER BY count DESC
    "),
    'charge_size_counts' => $rows("
        SELECT CAST(value AS INTEGER) AS value, COUNT(*) AS count
        FROM fitting_attributes a
        JOIN fitting_types t ON t.type_id = a.type_id
        WHERE a.attribute_id = 128
          AND t.slot_type IN ('high', 'charge')
        GROUP BY CAST(value AS INTEGER)
        ORDER BY value
    "),
    'rig_size_counts' => $rows("
        SELECT CAST(value AS INTEGER) AS value, COUNT(*) AS count
        FROM fitting_attributes a
        JOIN fitting_types t ON t.type_id = a.type_id
        WHERE a.attribute_id = 1547
        GROUP BY CAST(value AS INTEGER)
        ORDER BY value
    "),
    'can_fit_attr_usage' => $canFitUsage,
    'samples' => $samples,
];

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
