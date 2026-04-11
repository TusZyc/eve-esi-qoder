<?php

$dbPath = $argv[1] ?? __DIR__ . '/../database/fitting_official.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$queries = [
    'slot effects' => "
        SELECT effect_id, name
        FROM fitting_effects
        WHERE name IN ('hiPower','medPower','loPower','rigSlot','subSystem','launcherFitted','turretFitted')
        ORDER BY effect_id
    ",
    'ship core attrs' => "
        SELECT attribute_id, name, display_name_cn
        FROM fitting_attribute_types
        WHERE attribute_id IN (11,12,13,14,48,101,102,283,1132,1137,1154,1271,1547,1785)
        ORDER BY attribute_id
    ",
    'module core attrs' => "
        SELECT attribute_id, name, display_name_cn
        FROM fitting_attribute_types
        WHERE attribute_id IN (30,50,128,1153,1272,1298,1299,1300,1301,1302,1303,1304,1305,182,183,184,277,278,279)
        ORDER BY attribute_id
    ",
    'can fit attributes used' => "
        SELECT a.attribute_id, fat.name, fat.display_name_cn, COUNT(*) AS count
        FROM fitting_attributes a
        LEFT JOIN fitting_attribute_types fat ON fat.attribute_id = a.attribute_id
        WHERE a.attribute_id IN (1298,1299,1300,1301,1872,1879,1880,1881,2065,2396,2476,1302,1303,1304,1305,1944,2103,2463,2486,2487,2488,5948)
          AND a.value > 0
        GROUP BY a.attribute_id
        ORDER BY a.attribute_id
    ",
    'charge size high modules' => "
        SELECT CAST(a.value AS INTEGER) AS charge_size, COUNT(*) AS count
        FROM fitting_attributes a
        JOIN fitting_types t ON t.type_id = a.type_id
        WHERE a.attribute_id = 128
          AND t.slot_type = 'high'
        GROUP BY CAST(a.value AS INTEGER)
        ORDER BY charge_size
    ",
    'rig size rigs' => "
        SELECT CAST(a.value AS INTEGER) AS rig_size, COUNT(*) AS count
        FROM fitting_attributes a
        JOIN fitting_types t ON t.type_id = a.type_id
        WHERE a.attribute_id = 1547
          AND t.slot_type = 'rig'
        GROUP BY CAST(a.value AS INTEGER)
        ORDER BY rig_size
    ",
    'unknown slot examples' => "
        SELECT type_id, name_cn, name_en, group_id, category_id
        FROM fitting_types
        WHERE slot_type IS NULL
        LIMIT 30
    ",
    'subsystem examples' => "
        SELECT type_id, name_cn, name_en, local_category_path_json
        FROM fitting_types
        WHERE slot_type = 'subsystem'
        LIMIT 10
    ",
    'entropic disintegrators' => "
        SELECT
            t.type_id,
            t.name_cn,
            t.name_en,
            t.local_category_4 AS size_label,
            MAX(CASE WHEN a.attribute_id = 128 THEN a.value END) AS charge_size,
            GROUP_CONCAT(CASE WHEN a.attribute_id IN (1302,1303,1304,1305,1944,2103,2463,2486,2487,2488,5948) THEN CAST(a.value AS INTEGER) END) AS can_fit_types
        FROM fitting_types t
        LEFT JOIN fitting_attributes a ON a.type_id = t.type_id
        WHERE t.name_en LIKE '%Entropic Disintegrator%'
        GROUP BY t.type_id
        ORDER BY charge_size, t.type_id
        LIMIT 40
    ",
    'compression modules' => "
        SELECT
            t.type_id,
            t.name_cn,
            t.name_en,
            t.slot_type,
            GROUP_CONCAT(CASE WHEN a.attribute_id IN (1298,1299,1300,1301,1872,1879,1880,1881,2065,2396,2476) THEN CAST(a.value AS INTEGER) END) AS can_fit_groups,
            GROUP_CONCAT(CASE WHEN a.attribute_id IN (1302,1303,1304,1305,1944,2103,2463,2486,2487,2488,5948) THEN CAST(a.value AS INTEGER) END) AS can_fit_types
        FROM fitting_types t
        LEFT JOIN fitting_attributes a ON a.type_id = t.type_id
        WHERE t.local_category_path_json LIKE '%压缩%'
        GROUP BY t.type_id
        LIMIT 20
    ",
];

foreach ($queries as $title => $sql) {
    echo PHP_EOL . "## {$title}" . PHP_EOL;
    foreach ($db->query($sql)->fetchAll() as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
