<?php

$dbPath = $argv[1] ?? __DIR__ . '/../database/fitting_official.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$queries = [
    'restriction-like attributes' => "
        SELECT attribute_id, name, display_name_en, display_name_cn
        FROM fitting_attribute_types
        WHERE lower(name) LIKE '%max%group%'
           OR lower(name) LIKE '%max%type%'
           OR lower(name) LIKE '%canfit%'
           OR lower(name) LIKE '%disallow%'
           OR lower(name) LIKE '%online%'
           OR lower(name) LIKE '%limit%'
        ORDER BY attribute_id
        LIMIT 120
    ",
    'restriction-like effects' => "
        SELECT effect_id, name
        FROM fitting_effects
        WHERE lower(name) LIKE '%online%'
           OR lower(name) LIKE '%active%'
           OR lower(name) LIKE '%max%'
           OR lower(name) LIKE '%fit%'
           OR lower(name) LIKE '%disallow%'
           OR lower(name) LIKE '%limit%'
        ORDER BY effect_id
        LIMIT 160
    ",
    'attributes with small integer values used by modules' => "
        SELECT a.attribute_id, fat.name, fat.display_name_en, fat.display_name_cn, COUNT(*) AS count
        FROM fitting_attributes a
        JOIN fitting_types t ON t.type_id = a.type_id
        LEFT JOIN fitting_attribute_types fat ON fat.attribute_id = a.attribute_id
        WHERE t.category_id IN (7, 18, 32)
          AND a.value BETWEEN 1 AND 10
          AND (
              lower(fat.name) LIKE '%max%'
              OR lower(fat.name) LIKE '%online%'
              OR lower(fat.name) LIKE '%group%'
              OR lower(fat.name) LIKE '%limit%'
              OR lower(fat.name) LIKE '%canfit%'
          )
        GROUP BY a.attribute_id
        ORDER BY count DESC, a.attribute_id
        LIMIT 80
    ",
    'damage control sample attrs' => "
        SELECT t.type_id, t.name_cn, t.name_en, a.attribute_id, fat.name, fat.display_name_en, a.value
        FROM fitting_types t
        JOIN fitting_attributes a ON a.type_id = t.type_id
        LEFT JOIN fitting_attribute_types fat ON fat.attribute_id = a.attribute_id
        WHERE (t.name_en LIKE '%Damage Control%' OR t.name_cn LIKE '%损伤控制%')
          AND (
              lower(fat.name) LIKE '%max%'
              OR lower(fat.name) LIKE '%online%'
              OR lower(fat.name) LIKE '%limit%'
              OR lower(fat.name) LIKE '%group%'
          )
        ORDER BY t.type_id, a.attribute_id
        LIMIT 80
    ",
    'assault damage control sample attrs' => "
        SELECT t.type_id, t.name_cn, t.name_en, a.attribute_id, fat.name, fat.display_name_en, a.value
        FROM fitting_types t
        JOIN fitting_attributes a ON a.type_id = t.type_id
        LEFT JOIN fitting_attribute_types fat ON fat.attribute_id = a.attribute_id
        WHERE (t.name_en LIKE '%Assault Damage Control%' OR t.name_cn LIKE '%突击损伤控制%')
          AND (
              lower(fat.name) LIKE '%max%'
              OR lower(fat.name) LIKE '%online%'
              OR lower(fat.name) LIKE '%limit%'
              OR lower(fat.name) LIKE '%group%'
          )
        ORDER BY t.type_id, a.attribute_id
        LIMIT 80
    ",
    'max group fitted groups' => "
        SELECT t.group_id, g.name_cn, g.name_en, COUNT(*) AS count, MIN(a.value) AS limit_value
        FROM fitting_types t
        JOIN fitting_attributes a ON a.type_id = t.type_id
        LEFT JOIN fitting_groups g ON g.group_id = t.group_id
        WHERE a.attribute_id = 1544
          AND t.category_id IN (7, 32)
        GROUP BY t.group_id
        ORDER BY count DESC, t.group_id
        LIMIT 100
    ",
    'max type fitted modules' => "
        SELECT t.type_id, t.name_cn, t.name_en, t.group_id, a.value
        FROM fitting_types t
        JOIN fitting_attributes a ON a.type_id = t.type_id
        WHERE a.attribute_id = 2431
          AND t.category_id IN (7, 32)
        ORDER BY t.type_id
    ",
];

foreach ($queries as $title => $sql) {
    echo PHP_EOL . "## {$title}" . PHP_EOL;
    foreach ($db->query($sql)->fetchAll() as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
